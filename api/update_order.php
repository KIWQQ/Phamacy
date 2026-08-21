<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/order_functions.php';
header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit; }

$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$items = isset($data['items']) ? $data['items'] : [];
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : null;
$employee_id = isset($data['employee_id']) ? (int)$data['employee_id'] : null;

if ($order_id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid order_id']); exit; }
try {
    $existing = getOrderById($pdo, $order_id);
    if (!$existing) { http_response_code(404); echo json_encode(['error' => 'Order not found']); exit; }

    // compute old total from recorded prices
    $old_total = 0;
    foreach ($existing['items'] as $it) {
        $price = isset($it['recorded_price']) ? $it['recorded_price'] : (isset($it['unit_price']) ? $it['unit_price'] : 0);
        $old_total += floatval($price) * intval($it['quantity']);
    }

    // Normalize incoming items
    if (!is_array($items)) $items = [$items];
    $normalized = [];
    foreach ($items as $idx => $it) {
        if (is_object($it)) $it = (array)$it;
        $rawProduct = $it['medicine_id'] ?? ($it['product_id'] ?? ($it['id'] ?? null));
        $rawQty = $it['quantity'] ?? ($it['qty'] ?? ($it['amount'] ?? null));
        $product_id = filter_var($rawProduct, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $quantity = filter_var($rawQty, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($product_id === false || $product_id === null) throw new Exception('Invalid product id in items');
        if ($quantity === false || $quantity === null) throw new Exception('Invalid quantity in items');
        $normalized[] = ['medicine_id' => (int)$product_id, 'quantity' => (int)$quantity];
    }

    $pdo->beginTransaction();
    // Restore the exact lots used by the old order before replacing its items.
    foreach ($existing['items'] as $it) {
        $mid = (int)$it['medicine_id']; $qty = (int)$it['quantity'];
        $restoredByLot = restoreOrderDetailLots($pdo, (int)$it['order_detail_id'], 'ADJUST_IN', 'ORDER_UPDATE', $order_id);
        if (!$restoredByLot) {
            $restoredHistorical = restoreHistoricalStockToOpeningLot($pdo, $mid, $qty, 'ADJUST_IN', 'ORDER_UPDATE', $order_id);
            if (!$restoredHistorical) {
                $stmt = $pdo->prepare('UPDATE medicine SET stock = stock + :qty WHERE medicine_id = :id');
                $stmt->execute(['qty' => $qty, 'id' => $mid]);
            }
        }
    }

    // Remove old order details
    $stmtDel = $pdo->prepare('DELETE FROM order_detail WHERE order_id = :oid');
    $stmtDel->execute(['oid' => $order_id]);

    // Insert new details, verify stock then reduce
    $insert = $pdo->prepare('INSERT INTO order_detail (order_id, medicine_id, quantity, price) VALUES (:order_id, :medicine_id, :quantity, :price)');
    $new_total = 0;
    foreach ($normalized as $it) {
        $mid = (int)$it['medicine_id']; $qty = (int)$it['quantity'];
        $prod = getProductById($pdo, $mid);
        if (!$prod) throw new Exception('Product not found: ' . $mid);
        if (isset($prod['status']) && strcasecmp(trim($prod['status']), 'Discontinued') === 0) throw new Exception('Product is discontinued: ' . ($prod['medicine_name'] ?? $mid));
        // getProductById returns usable, non-expired lot stock after migration.
        $avail = (int)$prod['stock'];
        if ($avail < $qty) throw new Exception('Insufficient stock for: ' . ($prod['medicine_name'] ?? $mid));
        $unitPrice = $prod['price'];
        $insert->execute(['order_id' => $order_id, 'medicine_id' => $mid, 'quantity' => $qty, 'price' => $unitPrice]);
        // Lot migration uses the order_detail trigger to allocate FEFO and
        // reduce stock. Keep the legacy fallback only for the old schema.
        if (!lotStockEnabled($pdo) && !triggerExists($pdo, 'after_order_detail_insert')) {
            if (!reduceProductStock($pdo, $mid, $qty)) {
                throw new Exception('Insufficient stock while updating: ' . ($prod['medicine_name'] ?? $mid));
            }
        }
        $new_total += floatval($unitPrice) * intval($qty);
    }

    // update orders metadata
    $updFields = [];
    $params = ['order_id' => $order_id];
    if ($payment_method !== null) { $updFields[] = 'payment_method = :pm'; $params['pm'] = $payment_method; }
    if ($employee_id && $employee_id > 0) { $updFields[] = 'employee_id = :eid'; $params['eid'] = $employee_id; }
    if (!empty($updFields)) {
        $sql = 'UPDATE orders SET ' . implode(', ', $updFields) . ' WHERE order_id = :order_id';
        $stmtUpd = $pdo->prepare($sql);
        $stmtUpd->execute($params);
    }

    // adjust customer points difference
    $cid = (int)$existing['customer_id'];
    $old_points = (int) floor($old_total / 100);
    $new_points = (int) floor($new_total / 100);
    $diff = $new_points - $old_points;
    if ($diff !== 0) {
        addPointTransaction($pdo, $cid, $diff, ($diff > 0) ? 'EARN' : 'USE');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $order_id]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
