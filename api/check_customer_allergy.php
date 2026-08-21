<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = getPDO();
$cid = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$mid = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;

try {
    if ($cid <= 0 || $mid <= 0) {
        echo json_encode(['error' => 'Invalid parameters', 'allergic' => false]);
        exit;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM customer_allergy WHERE customer_id = :cid AND medicine_id = :mid LIMIT 1');
    $stmt->execute(['cid' => $cid, 'mid' => $mid]);
    $found = $stmt->fetchColumn();
    echo json_encode(['allergic' => (bool)$found]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'allergic' => false]);
}
