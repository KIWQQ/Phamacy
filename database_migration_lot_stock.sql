-- Pharmacy database migration: aggregate stock -> medicine lot stock
-- Compatible with MariaDB 10.4+
-- Run this file after importing the original pharmacy database.

USE `pharmacy`;

-- 1) Source of incoming medicine
CREATE TABLE IF NOT EXISTS `supplier` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(150) NOT NULL,
  PRIMARY KEY (`supplier_id`),
  UNIQUE KEY `uq_supplier_name` (`supplier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Header for each stock receipt / Add Stock operation
CREATE TABLE IF NOT EXISTS `stock_receipt` (
  `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`receipt_id`),
  KEY `idx_receipt_supplier` (`supplier_id`),
  KEY `idx_receipt_received_at` (`received_at`),
  CONSTRAINT `fk_receipt_supplier` FOREIGN KEY (`supplier_id`)
    REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) A medicine is no longer only one stock number. Each incoming batch is a lot.
CREATE TABLE IF NOT EXISTS `medicine_lot` (
  `lot_id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` int(11) NOT NULL,
  `receipt_id` int(11) DEFAULT NULL,
  `lot_number` varchar(100) NOT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'NULL is allowed only for migrated opening stock',
  `received_quantity` int(11) NOT NULL,
  `remaining_quantity` int(11) NOT NULL,
  `status` enum('ACTIVE','DEPLETED','EXPIRED','RECALLED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`lot_id`),
  UNIQUE KEY `uq_medicine_lot_number` (`medicine_id`,`lot_number`),
  KEY `idx_lot_fefo` (`medicine_id`,`status`,`expiry_date`,`remaining_quantity`),
  KEY `idx_lot_receipt` (`receipt_id`),
  CONSTRAINT `fk_lot_medicine` FOREIGN KEY (`medicine_id`)
    REFERENCES `medicine` (`medicine_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_lot_receipt` FOREIGN KEY (`receipt_id`)
    REFERENCES `stock_receipt` (`receipt_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_lot_received_qty` CHECK (`received_quantity` > 0),
  CONSTRAINT `chk_lot_remaining_qty` CHECK (`remaining_quantity` >= 0 AND `remaining_quantity` <= `received_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4) Records which lot was used by each order line.
CREATE TABLE IF NOT EXISTS `order_detail_lot` (
  `allocation_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_detail_id` int(11) NOT NULL,
  `lot_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`allocation_id`),
  UNIQUE KEY `uq_order_detail_lot` (`order_detail_id`,`lot_id`),
  KEY `idx_allocation_lot` (`lot_id`),
  CONSTRAINT `fk_allocation_order_detail` FOREIGN KEY (`order_detail_id`)
    REFERENCES `order_detail` (`order_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_allocation_lot` FOREIGN KEY (`lot_id`)
    REFERENCES `medicine_lot` (`lot_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_allocation_qty` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5) Complete lot-level history: stock in, sale, return, adjustment, expiry, recall.
CREATE TABLE IF NOT EXISTS `stock_movement` (
  `movement_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `lot_id` int(11) NOT NULL,
  `movement_type` enum('OPENING_BALANCE','STOCK_IN','SALE','REFUND','ADJUST_IN','ADJUST_OUT','EXPIRED','RECALL') NOT NULL,
  `quantity_change` int(11) NOT NULL COMMENT 'Positive = in, negative = out',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`movement_id`),
  KEY `idx_movement_lot_date` (`lot_id`,`created_at`),
  KEY `idx_movement_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_movement_lot` FOREIGN KEY (`lot_id`)
    REFERENCES `medicine_lot` (`lot_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_movement_not_zero` CHECK (`quantity_change` <> 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Convert every current aggregate balance into one temporary opening lot.
-- Its expiry is unknown; staff should later replace it with the real lot/expiry data.
INSERT INTO `medicine_lot`
  (`medicine_id`, `receipt_id`, `lot_number`, `expiry_date`,
   `received_quantity`, `remaining_quantity`, `status`)
SELECT
  m.`medicine_id`, NULL, CONCAT('OPEN-', m.`medicine_id`), NULL,
  m.`stock`, m.`stock`,
  CASE WHEN m.`stock` > 0 THEN 'ACTIVE' ELSE 'DEPLETED' END
FROM `medicine` m
WHERE m.`stock` > 0
  AND NOT EXISTS (
    SELECT 1
    FROM `medicine_lot` ml
    WHERE ml.`medicine_id` = m.`medicine_id`
      AND ml.`lot_number` = CONCAT('OPEN-', m.`medicine_id`)
  );

INSERT INTO `stock_movement`
  (`lot_id`, `movement_type`, `quantity_change`, `reference_type`, `note`)
SELECT
  ml.`lot_id`, 'OPENING_BALANCE', ml.`remaining_quantity`, 'MIGRATION',
  'Opening balance migrated from medicine.stock; expiry must be verified'
FROM `medicine_lot` ml
WHERE ml.`lot_number` = CONCAT('OPEN-', ml.`medicine_id`)
  AND NOT EXISTS (
    SELECT 1
    FROM `stock_movement` sm
    WHERE sm.`lot_id` = ml.`lot_id`
      AND sm.`movement_type` = 'OPENING_BALANCE'
  );

-- New Add Stock rows must contain valid quantities and a future/current expiry date.
DROP TRIGGER IF EXISTS `before_medicine_lot_insert`;
DELIMITER $$
CREATE TRIGGER `before_medicine_lot_insert`
BEFORE INSERT ON `medicine_lot`
FOR EACH ROW
BEGIN
  IF NEW.`received_quantity` <= 0
     OR NEW.`remaining_quantity` < 0
     OR NEW.`remaining_quantity` > NEW.`received_quantity` THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Invalid medicine lot quantity';
  END IF;

  IF NEW.`lot_number` NOT LIKE 'OPEN-%' AND NEW.`expiry_date` IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Expiry date is required for a new medicine lot';
  END IF;

  IF NEW.`expiry_date` IS NOT NULL AND NEW.`expiry_date` < CURRENT_DATE() THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cannot receive an already expired medicine lot';
  END IF;
END$$
DELIMITER ;

-- Keep medicine.stock usable by old PHP pages while the UI is being migrated.
DROP TRIGGER IF EXISTS `after_medicine_lot_insert`;
DELIMITER $$
CREATE TRIGGER `after_medicine_lot_insert`
AFTER INSERT ON `medicine_lot`
FOR EACH ROW
BEGIN
  UPDATE `medicine`
  SET `stock` = `stock` + NEW.`remaining_quantity`,
      `status` = CASE
        WHEN `status` <> 'Discontinued' AND NEW.`remaining_quantity` > 0 THEN 'Available'
        ELSE `status`
      END
  WHERE `medicine_id` = NEW.`medicine_id`;

  INSERT INTO `stock_movement`
    (`lot_id`, `movement_type`, `quantity_change`, `reference_type`, `reference_id`, `note`)
  VALUES
    (NEW.`lot_id`, 'STOCK_IN', NEW.`remaining_quantity`, 'STOCK_RECEIPT', NEW.`receipt_id`, 'Medicine lot received');
END$$
DELIMITER ;

-- Replace the old sale triggers. Stock is checked and allocated by FEFO
-- (lot with the nearest expiry date is sold first; unknown legacy expiry is last).
DROP TRIGGER IF EXISTS `before_order_detail_insert`;
DELIMITER $$
CREATE TRIGGER `before_order_detail_insert`
BEFORE INSERT ON `order_detail`
FOR EACH ROW
BEGIN
  DECLARE v_available int DEFAULT 0;

  IF NEW.`quantity` <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Sale quantity must be greater than zero';
  END IF;

  SELECT COALESCE(SUM(`remaining_quantity`), 0)
    INTO v_available
  FROM `medicine_lot`
  WHERE `medicine_id` = NEW.`medicine_id`
    AND `status` = 'ACTIVE'
    AND `remaining_quantity` > 0
    AND (`expiry_date` IS NULL OR `expiry_date` >= CURRENT_DATE());

  IF v_available < NEW.`quantity` THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Usable lot stock is not enough';
  END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `after_order_detail_insert`;
DELIMITER $$
CREATE TRIGGER `after_order_detail_insert`
AFTER INSERT ON `order_detail`
FOR EACH ROW
BEGIN
  DECLARE v_done int DEFAULT 0;
  DECLARE v_remaining int DEFAULT 0;
  DECLARE v_lot_id int;
  DECLARE v_lot_balance int;
  DECLARE v_take int;

  DECLARE lot_cursor CURSOR FOR
    SELECT `lot_id`, `remaining_quantity`
    FROM `medicine_lot`
    WHERE `medicine_id` = NEW.`medicine_id`
      AND `status` = 'ACTIVE'
      AND `remaining_quantity` > 0
      AND (`expiry_date` IS NULL OR `expiry_date` >= CURRENT_DATE())
    ORDER BY (`expiry_date` IS NULL) ASC, `expiry_date` ASC, `lot_id` ASC;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

  SET v_remaining = NEW.`quantity`;

  OPEN lot_cursor;
  allocation_loop: LOOP
    FETCH lot_cursor INTO v_lot_id, v_lot_balance;

    IF v_done = 1 OR v_remaining = 0 THEN
      LEAVE allocation_loop;
    END IF;

    SET v_take = LEAST(v_lot_balance, v_remaining);

    UPDATE `medicine_lot`
    SET `remaining_quantity` = `remaining_quantity` - v_take,
        `status` = CASE
          WHEN `remaining_quantity` - v_take = 0 THEN 'DEPLETED'
          ELSE `status`
        END
    WHERE `lot_id` = v_lot_id;

    INSERT INTO `order_detail_lot` (`order_detail_id`, `lot_id`, `quantity`)
    VALUES (NEW.`order_detail_id`, v_lot_id, v_take);

    INSERT INTO `stock_movement`
      (`lot_id`, `movement_type`, `quantity_change`, `reference_type`, `reference_id`, `note`)
    VALUES
      (v_lot_id, 'SALE', -v_take, 'ORDER_DETAIL', NEW.`order_detail_id`, 'Allocated automatically by FEFO');

    SET v_remaining = v_remaining - v_take;
  END LOOP;
  CLOSE lot_cursor;

  UPDATE `medicine`
  SET `stock` = GREATEST(0, `stock` - NEW.`quantity`)
  WHERE `medicine_id` = NEW.`medicine_id`;

  INSERT INTO `stock_log` (`medicine_id`, `change_amount`, `log_type`)
  VALUES (NEW.`medicine_id`, -NEW.`quantity`, 'OUT');
END$$
DELIMITER ;

-- One safe entry point for the future Add Stock page.
DROP PROCEDURE IF EXISTS `sp_add_stock`;
DELIMITER $$
CREATE PROCEDURE `sp_add_stock`(
  IN p_medicine_id int,
  IN p_lot_number varchar(100),
  IN p_expiry_date date,
  IN p_quantity int,
  IN p_supplier_id int,
  IN p_note varchar(500)
)
BEGIN
  DECLARE v_receipt_id int;
  DECLARE v_lot_id int;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF p_quantity <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Quantity must be greater than zero';
  END IF;

  IF p_lot_number IS NULL OR TRIM(p_lot_number) = '' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Lot number is required';
  END IF;

  IF p_expiry_date IS NULL OR p_expiry_date < CURRENT_DATE() THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'A valid expiry date is required';
  END IF;

  START TRANSACTION;

  INSERT INTO `stock_receipt`
    (`supplier_id`, `note`)
  VALUES
    (p_supplier_id, p_note);

  SET v_receipt_id = LAST_INSERT_ID();

  INSERT INTO `medicine_lot`
    (`medicine_id`, `receipt_id`, `lot_number`, `expiry_date`,
     `received_quantity`, `remaining_quantity`, `status`)
  VALUES
    (p_medicine_id, v_receipt_id, TRIM(p_lot_number), p_expiry_date,
     p_quantity, p_quantity, 'ACTIVE');

  SET v_lot_id = LAST_INSERT_ID();

  COMMIT;

  SELECT v_receipt_id AS `receipt_id`, v_lot_id AS `lot_id`;
END$$
DELIMITER ;

-- Mark expired balances and remove them from the legacy aggregate stock.
-- Run this procedure daily, or when opening the dashboard.
DROP PROCEDURE IF EXISTS `sp_process_expired_lots`;
DELIMITER $$
CREATE PROCEDURE `sp_process_expired_lots`()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  START TRANSACTION;

  INSERT INTO `stock_movement`
    (`lot_id`, `movement_type`, `quantity_change`, `reference_type`, `note`)
  SELECT
    `lot_id`, 'EXPIRED', -`remaining_quantity`, 'EXPIRY_PROCESS',
    CONCAT('Expired on ', DATE_FORMAT(`expiry_date`, '%Y-%m-%d'))
  FROM `medicine_lot`
  WHERE `status` = 'ACTIVE'
    AND `remaining_quantity` > 0
    AND `expiry_date` < CURRENT_DATE();

  INSERT INTO `stock_log` (`medicine_id`, `change_amount`, `log_type`, `log_date`)
  SELECT `medicine_id`, -`remaining_quantity`, 'ADJUST', current_timestamp()
  FROM `medicine_lot`
  WHERE `status` = 'ACTIVE'
    AND `remaining_quantity` > 0
    AND `expiry_date` < CURRENT_DATE();

  UPDATE `medicine` m
  JOIN (
    SELECT `medicine_id`, SUM(`remaining_quantity`) AS expired_qty
    FROM `medicine_lot`
    WHERE `status` = 'ACTIVE'
      AND `remaining_quantity` > 0
      AND `expiry_date` < CURRENT_DATE()
    GROUP BY `medicine_id`
  ) e ON e.`medicine_id` = m.`medicine_id`
  SET m.`stock` = GREATEST(0, m.`stock` - e.expired_qty);

  UPDATE `medicine_lot`
  SET `status` = 'EXPIRED'
  WHERE `status` = 'ACTIVE'
    AND `remaining_quantity` > 0
    AND `expiry_date` < CURRENT_DATE();

  COMMIT;
END$$
DELIMITER ;

-- UI/report query: usable stock, expired stock, and nearest expiry per medicine.
CREATE OR REPLACE VIEW `v_medicine_stock_summary` AS
SELECT
  m.`medicine_id`,
  m.`medicine_name`,
  m.`type_id`,
  m.`price`,
  m.`status`,
  COALESCE(SUM(CASE
    WHEN ml.`status` = 'ACTIVE'
      AND ml.`remaining_quantity` > 0
      AND (ml.`expiry_date` IS NULL OR ml.`expiry_date` >= CURRENT_DATE())
    THEN ml.`remaining_quantity` ELSE 0 END), 0) AS `usable_stock`,
  COALESCE(SUM(CASE
    WHEN ml.`remaining_quantity` > 0
      AND (ml.`status` = 'EXPIRED' OR ml.`expiry_date` < CURRENT_DATE())
    THEN ml.`remaining_quantity` ELSE 0 END), 0) AS `expired_stock`,
  MIN(CASE
    WHEN ml.`status` = 'ACTIVE'
      AND ml.`remaining_quantity` > 0
      AND ml.`expiry_date` >= CURRENT_DATE()
    THEN ml.`expiry_date` END) AS `nearest_expiry_date`
FROM `medicine` m
LEFT JOIN `medicine_lot` ml ON ml.`medicine_id` = m.`medicine_id`
GROUP BY
  m.`medicine_id`, m.`medicine_name`, m.`type_id`, m.`price`, m.`status`;

-- Examples (do not run until IDs have been checked):
-- CALL sp_add_stock(1001, 'LOT-PARA-2026-001', '2027-12-31', 100, 1, 'First lot-based receipt');
-- CALL sp_process_expired_lots();
-- SELECT * FROM v_medicine_stock_summary ORDER BY medicine_id;
-- SELECT * FROM medicine_lot WHERE medicine_id = 1001 ORDER BY expiry_date;
-- SELECT * FROM stock_movement ORDER BY movement_id DESC;
