-- Run ONCE only if the earlier lot-stock migration was already imported.
-- This removes the fields deleted from the Add Stock form.
-- Back up the pharmacy database before running this file.

USE `pharmacy`;

-- Remove Received By and Invoice / Reference No.
ALTER TABLE `stock_receipt`
  DROP FOREIGN KEY `fk_receipt_employee`;

ALTER TABLE `stock_receipt`
  DROP INDEX `idx_receipt_employee`,
  DROP COLUMN `employee_id`,
  DROP COLUMN `reference_no`;

-- Remove Manufactured Date and Unit Cost.
ALTER TABLE `medicine_lot`
  DROP CONSTRAINT `chk_lot_dates`;

ALTER TABLE `medicine_lot`
  DROP COLUMN `manufactured_date`,
  DROP COLUMN `unit_cost`;

-- Employee is no longer stored in the lot movement log.
ALTER TABLE `stock_movement`
  DROP FOREIGN KEY `fk_movement_employee`;

ALTER TABLE `stock_movement`
  DROP INDEX `idx_movement_employee`,
  DROP COLUMN `employee_id`;

-- Supplier keeps only the name required by the form.
ALTER TABLE `supplier`
  DROP COLUMN `phone`,
  DROP COLUMN `email`,
  DROP COLUMN `address`,
  DROP COLUMN `status`,
  DROP COLUMN `created_at`;

-- Replace the old procedure with the simplified six-field version.
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

  INSERT INTO `stock_receipt` (`supplier_id`, `note`)
  VALUES (p_supplier_id, p_note);

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

-- Verification
SELECT `receipt_id`, `supplier_id`, `received_at`, `note`
FROM `stock_receipt`
ORDER BY `receipt_id` DESC
LIMIT 5;

SELECT `lot_id`, `medicine_id`, `receipt_id`, `lot_number`, `expiry_date`,
       `received_quantity`, `remaining_quantity`, `status`
FROM `medicine_lot`
ORDER BY `lot_id` DESC
LIMIT 5;
