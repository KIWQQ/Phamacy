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

$medicine_id = isset($data['medicine_id']) ? intval($data['medicine_id']) : 0;

if ($medicine_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid medicine ID']);
    exit;
}

try {
    // Soft-delete: update status instead of removing row
    $stmt = $pdo->prepare("UPDATE medicine SET status = 'Discontinued' WHERE medicine_id = :medicine_id");
    $stmt->execute([':medicine_id' => $medicine_id]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Medicine marked as cancelled (soft-deleted)'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
