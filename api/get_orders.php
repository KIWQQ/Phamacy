<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/order_functions.php';
header('Content-Type: application/json');

$pdo = getPDO();
try {
    $rows = listOrders($pdo, 200);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
