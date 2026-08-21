<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/order_functions.php';
header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
$items = isset($data['items']) ? $data['items'] : [];
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'cash';
$employee_id = isset($data['employee_id']) ? (int)$data['employee_id'] : null;

try {
    if ($customer_id <= 0) throw new Exception('Invalid customer');

    // normalize items into expected format for createOrder()
    if (!is_array($items)) {
        // single item object
        $items = [$items];
    }

    $normalized = [];
    foreach ($items as $idx => $it) {
        if (is_object($it)) $it = (array)$it;
        if (!is_array($it)) throw new Exception("Invalid item format at index {$idx}");

        // accept common keys and normalize to medicine_id & quantity
        $rawProduct = null;
        if (isset($it['medicine_id'])) $rawProduct = $it['medicine_id'];
        elseif (isset($it['product_id'])) $rawProduct = $it['product_id'];
        elseif (isset($it['id'])) $rawProduct = $it['id'];

        $rawQty = null;
        if (isset($it['quantity'])) $rawQty = $it['quantity'];
        elseif (isset($it['qty'])) $rawQty = $it['qty'];
        elseif (isset($it['amount'])) $rawQty = $it['amount'];

        if (is_string($rawProduct)) $rawProduct = trim($rawProduct);
        if (is_string($rawQty)) $rawQty = trim($rawQty);

        $product_id = filter_var($rawProduct, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($rawQty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($product_id === false || $product_id === null) throw new Exception("Invalid item data at index {$idx}: missing or invalid product_id");
        if ($quantity === false || $quantity === null) throw new Exception("Invalid item data at index {$idx}: missing or invalid quantity");

        // price may be provided by client but we'll ignore/override with server price in createOrder()
        $normalized[] = ['medicine_id' => (int)$product_id, 'quantity' => (int)$quantity];
    }

    // call shared logic (pass employee_id if provided)
    if ($employee_id && $employee_id > 0) {
        $result = createOrder($pdo, $customer_id, $normalized, $payment_method, $employee_id);
    } else {
        $result = createOrder($pdo, $customer_id, $normalized, $payment_method);
    }
    $response = ['success' => true, 'order_id' => (int)$result['order_id']];
    if (!empty($result['warnings'])) {
        $response['warnings'] = $result['warnings'];
    }
    echo json_encode($response);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $out = ['error' => $e->getMessage()];
    // include trace for debugging, but don't expose internal details in production
    if (function_exists('debug_backtrace')) $out['trace'] = $e->getTraceAsString();
    error_log("save_order error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\nPAYLOAD: " . $raw);
    echo json_encode($out);
}
