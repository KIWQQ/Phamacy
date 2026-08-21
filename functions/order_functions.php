<?php
// functions/order_functions.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/product_functions.php';
require_once __DIR__ . '/customer_functions.php';

function triggerExists($pdo, $triggerName)
{
    $stmt = $pdo->prepare("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_NAME = :t AND TRIGGER_SCHEMA = DATABASE() LIMIT 1");
    $stmt->execute(['t' => $triggerName]);
    return (bool)$stmt->fetch();
}

function restoreOrderDetailLots($pdo, $orderDetailId, $movementType, $referenceType, $referenceId)
{
    if (!lotStockEnabled($pdo)) return false;

    $stmt = $pdo->prepare('SELECT odl.lot_id, odl.quantity, ml.medicine_id, ml.expiry_date
                           FROM order_detail_lot odl
                           JOIN medicine_lot ml ON ml.lot_id = odl.lot_id
                           WHERE odl.order_detail_id = :order_detail_id
                           FOR UPDATE');
    $stmt->execute(['order_detail_id' => $orderDetailId]);
    $allocations = $stmt->fetchAll();
    if (!$allocations) return false;

    $updateLot = $pdo->prepare("UPDATE medicine_lot
                               SET remaining_quantity = LEAST(received_quantity, remaining_quantity + :quantity),
                                   status = :status
                               WHERE lot_id = :lot_id");
    $updateMedicine = $pdo->prepare("UPDATE medicine SET stock = stock + :quantity,
                                    status = CASE WHEN status <> 'Discontinued' THEN 'Available' ELSE status END
                                    WHERE medicine_id = :medicine_id");
    $insertMovement = $pdo->prepare('INSERT INTO stock_movement
        (lot_id, movement_type, quantity_change, reference_type, reference_id, note)
        VALUES (:lot_id, :movement_type, :quantity, :reference_type, :reference_id, :note)');

    foreach ($allocations as $allocation) {
        $quantity = (int)$allocation['quantity'];
        $expired = !empty($allocation['expiry_date']) && $allocation['expiry_date'] < date('Y-m-d');
        $updateLot->execute([
            'quantity' => $quantity,
            'status' => $expired ? 'EXPIRED' : 'ACTIVE',
            'lot_id' => (int)$allocation['lot_id'],
        ]);

        // An expired returned lot remains traceable but is not usable stock.
        if (!$expired) {
            $updateMedicine->execute([
                'quantity' => $quantity,
                'medicine_id' => (int)$allocation['medicine_id'],
            ]);
        }

        $insertMovement->execute([
            'lot_id' => (int)$allocation['lot_id'],
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $expired ? 'Returned to expired lot; not available for sale' : 'Returned to original medicine lot',
        ]);
    }

    return true;
}

function restoreHistoricalStockToOpeningLot($pdo, $medicineId, $quantity, $movementType, $referenceType, $referenceId)
{
    if (!lotStockEnabled($pdo)) return false;

    $find = $pdo->prepare("SELECT lot_id FROM medicine_lot
                           WHERE medicine_id = :medicine_id AND lot_number LIKE 'OPEN-%'
                           ORDER BY lot_id ASC LIMIT 1 FOR UPDATE");
    $find->execute(['medicine_id' => $medicineId]);
    $lotId = (int)$find->fetchColumn();

    if ($lotId <= 0) {
        // Trigger creates the stock movement and synchronizes medicine.stock.
        $insert = $pdo->prepare("INSERT INTO medicine_lot
            (medicine_id, lot_number, expiry_date, received_quantity, remaining_quantity, status)
            VALUES (:medicine_id, :lot_number, NULL, :received_quantity, :remaining_quantity, 'ACTIVE')");
        $insert->execute([
            'medicine_id' => $medicineId,
            'lot_number' => 'OPEN-RETURN-' . $medicineId,
            'received_quantity' => $quantity,
            'remaining_quantity' => $quantity,
        ]);
        return true;
    }

    $updateLot = $pdo->prepare("UPDATE medicine_lot
                                SET received_quantity = received_quantity + :received_quantity,
                                    remaining_quantity = remaining_quantity + :remaining_quantity,
                                    status = 'ACTIVE'
                                WHERE lot_id = :lot_id");
    $updateLot->execute([
        'received_quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'lot_id' => $lotId,
    ]);

    $updateMedicine = $pdo->prepare("UPDATE medicine SET stock = stock + :quantity,
                                    status = CASE WHEN status <> 'Discontinued' THEN 'Available' ELSE status END
                                    WHERE medicine_id = :medicine_id");
    $updateMedicine->execute(['quantity' => $quantity, 'medicine_id' => $medicineId]);

    $movement = $pdo->prepare('INSERT INTO stock_movement
        (lot_id, movement_type, quantity_change, reference_type, reference_id, note)
        VALUES (:lot_id, :movement_type, :quantity, :reference_type, :reference_id, :note)');
    $movement->execute([
        'lot_id' => $lotId,
        'movement_type' => $movementType,
        'quantity' => $quantity,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'note' => 'Historical order restored to migrated opening lot',
    ]);
    return true;
}

function createOrder($pdo, $customer_id, $items, $payment_method = 'Cash', $employee_id = null)
{
    // $items = array of ['medicine_id'=>..., 'quantity'=>..., 'price'=>...]
    if (empty($items)) {
        throw new Exception('No order items');
    }

    // Warnings collected when relational allergy mapping exists
    $warnings = [];

    $pdo->beginTransaction();
    try {
        // insert order
        // include employee_id when available
        if ($employee_id && intval($employee_id) > 0) {
            $stmt = $pdo->prepare('INSERT INTO orders (order_date, customer_id, employee_id, status, payment_method) VALUES (NOW(), :customer_id, :employee_id, :status, :payment_method)');
            try {
                $stmt->execute(['customer_id' => $customer_id, 'employee_id' => (int)$employee_id, 'status' => 'Completed', 'payment_method' => $payment_method]);
            } catch (PDOException $pe) {
                error_log('orders insert failed: ' . $pe->getMessage() . ' - customer_id: ' . json_encode($customer_id) . ' employee_id: ' . json_encode($employee_id));
                throw new Exception('Database error inserting order: ' . $pe->getMessage());
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO orders (order_date, customer_id, status, payment_method) VALUES (NOW(), :customer_id, :status, :payment_method)');
            try {
                $stmt->execute(['customer_id' => $customer_id, 'status' => 'Completed', 'payment_method' => $payment_method]);
            } catch (PDOException $pe) {
                error_log('orders insert failed: ' . $pe->getMessage() . ' - customer_id: ' . json_encode($customer_id));
                throw new Exception('Database error inserting order: ' . $pe->getMessage());
            }
        }
        $order_id = $pdo->lastInsertId();

        // order_detail table matches SQL schema
        $insertDetail = $pdo->prepare('INSERT INTO order_detail (order_id, medicine_id, quantity, price) VALUES (:order_id, :medicine_id, :quantity, :price)');

        $orderTotal = 0;

        foreach ($items as $idx => $it) {
            // normalize incoming keys to support different client names
            if (isset($it['medicine_id'])) {
                $mid_raw = $it['medicine_id'];
            } elseif (isset($it['product_id'])) {
                $mid_raw = $it['product_id'];
            } elseif (isset($it['id'])) {
                $mid_raw = $it['id'];
            } else {
                throw new Exception("Invalid item data at index {$idx}: missing product_id/medicine_id");
            }

            if (isset($it['quantity'])) {
                $qty_raw = $it['quantity'];
            } elseif (isset($it['qty'])) {
                $qty_raw = $it['qty'];
            } elseif (isset($it['amount'])) {
                $qty_raw = $it['amount'];
            } else {
                throw new Exception("Invalid item data at index {$idx}: missing quantity/qty");
            }

            // coerce to integers safely
            $mid = is_numeric($mid_raw) ? (int)$mid_raw : 0;
            $qty = is_numeric($qty_raw) ? (int)$qty_raw : 0;

            if ($mid <= 0) {
                throw new Exception("Invalid item data at index {$idx}: missing or invalid product_id (value: " . json_encode($mid_raw) . ")");
            }
            if ($qty <= 0) {
                throw new Exception("Invalid item data at index {$idx}: missing or invalid quantity (value: " . json_encode($qty_raw) . ")");
            }

            $product = getProductById($pdo, $mid);
            if (!$product) {
                throw new Exception('Product not found: ' . $mid);
            }
            // Prevent ordering products which are discontinued
            if (isset($product['status']) && strcasecmp(trim($product['status']), 'Discontinued') === 0) {
                throw new Exception('Product "' . $product['medicine_name'] . '" is discontinued and cannot be ordered');
            }
            if ($product['stock'] < $qty) {
                throw new Exception('Insufficient stock for: ' . $product['medicine_name']);
            }

            // Check for allergy warning using relational tables if present
            try {
                // In your DB schema customer_allergy links directly to medicine_id
                $chk = $pdo->prepare('SELECT 1 FROM customer_allergy WHERE customer_id = :cid AND medicine_id = :mid LIMIT 1');
                $chk->execute(['cid' => $customer_id, 'mid' => $mid]);
                $found = $chk->fetchColumn();
                if ($found) {
                    $warnings[] = "Warning: Customer is allergic to {$product['medicine_name']}";
                } else {
                    // fallback: also check any allergy string returned by getCustomerById()
                    $customer = getCustomerById($pdo, $customer_id);
                    $allergy = $customer ? trim($customer['allergy'] ?? '') : '';
                    if ($allergy) {
                        $allergyList = array_map('trim', explode(',', $allergy));
                        if (in_array(strtolower(trim($product['medicine_name'])), array_map('strtolower', $allergyList))) {
                            $warnings[] = "Warning: Customer is allergic to {$product['medicine_name']}";
                        }
                    }
                }
            } catch (Exception $_) {
                // fallback to getCustomerById()
                $customer = getCustomerById($pdo, $customer_id);
                $allergy = $customer ? trim($customer['allergy'] ?? '') : '';
                if ($allergy) {
                    $allergyList = array_map('trim', explode(',', $allergy));
                    if (in_array(strtolower(trim($product['medicine_name'])), array_map('strtolower', $allergyList))) {
                        $warnings[] = "Warning: Customer is allergic to {$product['medicine_name']}";
                    }
                }
            }

            // use server price to avoid client tampering
            $unitPrice = $product['price'];
            $orderTotal += $unitPrice * $qty;

            // execute with parameter names WITHOUT leading colons for consistency
            $params = [
                'order_id' => $order_id,
                'medicine_id' => $mid,
                'quantity' => $qty,
                'price' => $unitPrice,
            ];
            try {
                $insertDetail->execute($params);
                if ($insertDetail->rowCount() !== 1) {
                    throw new Exception('Failed to insert order detail for order ' . $order_id . ', medicine ' . $mid);
                }
            } catch (PDOException $pe) {
                error_log('order_detail execute failed: ' . $pe->getMessage() . ' - params: ' . json_encode($params));
                throw new Exception('Database error inserting order detail: ' . $pe->getMessage());
            }

            // verify inserted row exists
            $verifyStmt = $pdo->prepare('SELECT 1 FROM order_detail WHERE order_id = :order_id AND medicine_id = :medicine_id LIMIT 1');
            $verifyStmt->execute(['order_id' => $order_id, 'medicine_id' => $mid]);
            if (!$verifyStmt->fetchColumn()) {
                throw new Exception('Order detail verification failed for order_id=' . $order_id . ', medicine_id=' . $mid);
            }

            // Stock is normally updated by DB trigger `after_order_detail_insert`.
            // If that trigger is not present (e.g. migrations not applied), call
            // `reduceProductStock()` here so stock and `status` are updated.
            $stmt = $pdo->prepare('SELECT stock, medicine_name FROM medicine WHERE medicine_id = :id LIMIT 1');
            $stmt->execute(['id' => $mid]);
            $current = $stmt->fetch();
            // fallback to PHP-side stock update when DB trigger absent
            if (!triggerExists($pdo, 'after_order_detail_insert')) {
                // attempt to reduce stock; if it fails (insufficient stock) throw
                $ok = reduceProductStock($pdo, $mid, $qty);
                if (!$ok) {
                    throw new Exception('Insufficient stock while updating: ' . $product['medicine_name']);
                }
                // refresh current stock after PHP update
                $stmt = $pdo->prepare('SELECT stock, medicine_name FROM medicine WHERE medicine_id = :id LIMIT 1');
                $stmt->execute(['id' => $mid]);
                $current = $stmt->fetch();
            }
            if ($current) {
                maybeCreateLowStockAlert($pdo, $mid, (int)$current['stock']);
                // If stock has reached zero but status wasn't updated (old trigger or drift), fix it here.
                if ((int)$current['stock'] <= 0) {
                    $fix = $pdo->prepare("UPDATE medicine SET status = 'Not available' WHERE medicine_id = :id AND status != 'Not available'");
                    $fix->execute(['id' => $mid]);
                    // refresh current.status for any further logic
                    $stmt = $pdo->prepare('SELECT stock, medicine_name, status FROM medicine WHERE medicine_id = :id LIMIT 1');
                    $stmt->execute(['id' => $mid]);
                    $current = $stmt->fetch();
                }
            }
        }

        // Verify order_detail exists for the order
        $checkDetails = $pdo->prepare('SELECT COUNT(*) AS cnt FROM order_detail WHERE order_id = :order_id');
        $checkDetails->execute(['order_id' => $order_id]);
        $detailCount = (int) $checkDetails->fetchColumn();
        if ($detailCount <= 0) {
            throw new Exception('No order details inserted for order: ' . $order_id);
        }

        // Add points for customer: 1 point per each 100 THB spent
        $points = (int) floor($orderTotal / 100);
        if ($points > 0) {
            addPointTransaction($pdo, $customer_id, $points, 'EARN');
        }

        $pdo->commit();
        return ['order_id' => $order_id, 'warnings' => $warnings];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (Exception $_) { /* ignore rollback errors */ }
        }
        throw $e;
    }
}

function getOrderById($pdo, $order_id)
{
    $stmt = $pdo->prepare('SELECT o.order_id, o.order_date, o.customer_id, o.status, o.payment_method, c.customer_name FROM orders o JOIN customer c ON c.customer_id = o.customer_id WHERE o.order_id = :id');
    $stmt->execute(['id' => $order_id]);
    $order = $stmt->fetch();
    if (!$order) return null;
    $stmt2 = $pdo->prepare('SELECT od.order_detail_id, od.medicine_id, COALESCE(m.medicine_name, "(deleted)") AS medicine_name, od.quantity, COALESCE(m.price, od.price) AS unit_price, od.price AS recorded_price FROM order_detail od LEFT JOIN medicine m ON m.medicine_id = od.medicine_id WHERE od.order_id = :id');
    $stmt2->execute(['id' => $order_id]);
    $order['items'] = $stmt2->fetchAll();

    if (empty($order['items'])) {
        throw new Exception('No order details found for order ' . $order_id);
    }
    return $order;
}

function listOrders($pdo, $limit = 100)
{
    $sql = 'SELECT o.order_id, o.order_date, c.customer_name, o.status, COALESCE(SUM(od.quantity * COALESCE(m.price, od.price)),0) AS total FROM orders o JOIN customer c ON c.customer_id = o.customer_id LEFT JOIN order_detail od ON od.order_id = o.order_id LEFT JOIN medicine m ON m.medicine_id = od.medicine_id GROUP BY o.order_id, o.order_date, c.customer_name, o.status ORDER BY o.order_date DESC LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getSalesByDay($pdo, $days = 14)
{
    $sql = "SELECT DATE(o.order_date) AS date, COALESCE(SUM(od.quantity * od.price), 0) AS total
            FROM orders o
            JOIN order_detail od ON od.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(o.order_date)
            ORDER BY date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTotalSales($pdo, $days = 30)
{
    $sql = "SELECT COALESCE(SUM(od.quantity * od.price), 0) AS total
            FROM orders o
            JOIN order_detail od ON od.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row ? (float)$row['total'] : 0.0;
}

function countOrders($pdo)
{
    $sql = "SELECT COUNT(*) AS cnt FROM orders";
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
}

function getTopProducts($pdo, $limit = 10)
{
        $sql = "SELECT m.medicine_id, m.medicine_name, COALESCE(SUM(od.quantity), 0) AS sold
            FROM order_detail od
            LEFT JOIN medicine m ON m.medicine_id = od.medicine_id
            GROUP BY m.medicine_id
            ORDER BY sold DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTopProductSingle($pdo)
{
        $sql = "SELECT m.medicine_name, COALESCE(SUM(od.quantity), 0) AS sold
            FROM order_detail od
            LEFT JOIN medicine m ON m.medicine_id = od.medicine_id
            GROUP BY m.medicine_id
            ORDER BY sold DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch();
}


function addPointTransaction($pdo, $customer_id, $points, $type = 'EARN')
{
    if ($customer_id <= 0 || $points === 0) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO point_transaction (customer_id, points, type) VALUES (:customer_id, :points, :type)');
    return $stmt->execute([
        'customer_id' => $customer_id,
        'points' => $points,
        'type' => $type
    ]);
}

function maybeCreateLowStockAlert($pdo, $medicine_id, $current_stock, $reorder_level = 20)
{
    if ($current_stock > $reorder_level) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT medicine_name FROM medicine WHERE medicine_id = :id LIMIT 1');
    $stmt->execute(['id' => $medicine_id]);
    $row = $stmt->fetch();
    $name = $row ? $row['medicine_name'] : 'Unknown';
    $message = 'Low stock: ' . $name;

    // Insert only medicine_id, reorder_level and message — current stock is taken from `medicine` table
    $stmt2 = $pdo->prepare('INSERT INTO low_stock_alert (medicine_id, reorder_level, alert_message) VALUES (:medicine_id, :reorder_level, :alert_message)');
    return $stmt2->execute([
        'medicine_id' => $medicine_id,
        'reorder_level' => $reorder_level,
        'alert_message' => $message
    ]);
}

function refundOrder($pdo, $order_id)
{
    // Ensure order exists
    $order = getOrderById($pdo, $order_id);
    if (!$order) {
        throw new Exception('Order not found');
    }

    // Ensure refund table exists and `refund_id` is AUTO_INCREMENT.
    $tableExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'refund'");
        $tableExists = (bool) ($check && $check->fetch());
    } catch (Exception $e) {
        // ignore, will surface later when attempting to insert
    }

    if ($tableExists) {
        $colStmt = $pdo->query("SHOW COLUMNS FROM `refund` LIKE 'refund_id'");
        $col = $colStmt ? $colStmt->fetch() : false;
        if ($col && stripos($col['Extra'] ?? '', 'auto_increment') === false) {
            // Try to enable AUTO_INCREMENT. If ALTER fails, throw an informative exception so caller can repair schema.
            $maxRow = $pdo->query('SELECT COALESCE(MAX(refund_id), 0) AS maxid FROM `refund`')->fetch();
            $next = (int)$maxRow['maxid'] + 1;
            try {
                $pdo->exec("ALTER TABLE `refund` MODIFY `refund_id` INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$next}");
            } catch (Exception $e) {
                throw new Exception('Refund table exists but `refund_id` is not AUTO_INCREMENT and automatic ALTER failed: ' . $e->getMessage() . '\nRun the following SQL in your database to fix it: ALTER TABLE `refund` MODIFY `refund_id` INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=' . $next . ';');
            }
        }
    }

    // Prevent duplicate refunds
    $stmt = $pdo->prepare('SELECT refund_id FROM refund WHERE order_id = :order_id LIMIT 1');
    $stmt->execute(['order_id' => $order_id]);
    if ($stmt->fetch()) {
        throw new Exception('Order has already been refunded');
    }

    $pdo->beginTransaction();
    try {
        $totalAmount = 0;
        foreach ($order['items'] as $item) {
            // Prefer the recorded price from the order_detail row (historical snapshot).
            // getOrderById() exposes this as 'recorded_price'. Fall back to 'unit_price' if needed.
            $price = null;
            if (isset($item['recorded_price'])) {
                $price = $item['recorded_price'];
            } elseif (isset($item['unit_price'])) {
                $price = $item['unit_price'];
            } elseif (isset($item['price'])) {
                $price = $item['price'];
            } else {
                $price = 0;
            }
            $totalAmount += floatval($price) * intval($item['quantity']);
        }

        $insertRefund = $pdo->prepare('INSERT INTO refund (order_id, refund_date, total_amount) VALUES (:order_id, NOW(), :total_amount)');
        try {
            $insertRefund->execute(['order_id' => $order_id, 'total_amount' => $totalAmount]);
        } catch (PDOException $pe) {
            if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Exception $_) { /* ignore */ } }
            throw new Exception('Failed to insert refund record: ' . $pe->getMessage());
        }
        $refund_id = (int)$pdo->lastInsertId();

        // If lastInsertId() returns 0 (table missing AUTO_INCREMENT or driver quirk), try to find the refund row by order_id
        if ($refund_id <= 0) {
            $found = $pdo->prepare('SELECT refund_id FROM refund WHERE order_id = :order_id LIMIT 1');
            $found->execute(['order_id' => $order_id]);
            $row = $found->fetch();
            if ($row && !empty($row['refund_id'])) {
                $refund_id = (int)$row['refund_id'];
            } else {
                throw new Exception('Failed to create refund record (no insert id). Ensure `refund.refund_id` is AUTO_INCREMENT and migration 002_create_refund_tables.sql has been applied.');
            }
        }

        $insertRefundDetail = $pdo->prepare('INSERT INTO refund_detail (refund_id, medicine_id, quantity, price) VALUES (:refund_id, :medicine_id, :quantity, :price)');

        foreach ($order['items'] as $item) {
            try {
                // Use the recorded price when inserting refund detail so refunds reflect
                // the price paid at the time of the original order.
                $rprice = isset($item['recorded_price']) ? $item['recorded_price'] : (isset($item['unit_price']) ? $item['unit_price'] : (isset($item['price']) ? $item['price'] : 0));
                $insertRefundDetail->execute([
                    'refund_id' => $refund_id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'price' => $rprice,
                ]);
                $refundDetailId = (int)$pdo->lastInsertId();
            } catch (PDOException $pe) {
                if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Exception $_) { /* ignore */ } }
                throw new Exception('Failed to insert refund detail: ' . $pe->getMessage());
            }

            // Restore the exact lots used by the sale. Fall back to the old
            // aggregate behavior for historical orders created before migration.
            $restoredByLot = restoreOrderDetailLots(
                $pdo,
                (int)$item['order_detail_id'],
                'REFUND',
                'REFUND_DETAIL',
                $refundDetailId
            );
            if (!$restoredByLot) {
                $restoredHistorical = restoreHistoricalStockToOpeningLot(
                    $pdo,
                    (int)$item['medicine_id'],
                    (int)$item['quantity'],
                    'REFUND',
                    'REFUND_DETAIL',
                    $refundDetailId
                );
                if (!$restoredHistorical) {
                    $stmt2 = $pdo->prepare("UPDATE medicine SET stock = stock + :qty, status = CASE WHEN (stock + :qty2) > 0 THEN 'Available' ELSE status END WHERE medicine_id = :id");
                    $stmt2->execute(['qty' => $item['quantity'], 'qty2' => $item['quantity'], 'id' => $item['medicine_id']]);
                }
            }

            $stmt3 = $pdo->prepare('SELECT stock FROM medicine WHERE medicine_id = :id LIMIT 1');
            $stmt3->execute(['id' => $item['medicine_id']]);
            $stockRow = $stmt3->fetch();
            if ($stockRow) {
                maybeCreateLowStockAlert($pdo, $item['medicine_id'], (int)$stockRow['stock']);
            }
        }

        // Create a point transaction for refund (points deducted)
        $points = (int) floor($totalAmount / 100);
        if ($points > 0) {
            addPointTransaction($pdo, $order['customer_id'], -$points, 'USE');
        }

        $pdo->commit();
        return $refund_id;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Exception $_) { /* ignore rollback errors */ } }
        throw $e;
    }
}
