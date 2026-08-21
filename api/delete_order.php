<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Instead of deleting rows, mark the order as cancelled so history is preserved
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Discontinued' WHERE order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully'
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
