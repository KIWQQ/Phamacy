<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';

header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$medicine_name = isset($data['medicine_name']) ? trim($data['medicine_name']) : '';
$type_id = isset($data['type_id']) ? intval($data['type_id']) : 0;
$price = isset($data['price']) ? $data['price'] : 0;

if (empty($medicine_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Medicine name is required']);
    exit;
}

if ($type_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid medicine type is required']);
    exit;
}

$price = is_numeric($price) ? floatval($price) : 0;

if ($price < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Price must be a positive number']);
    exit;
}

try {
    // Use function to verify type exists
    if (!validTypeExists($pdo, $type_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid medicine type']);
        exit;
    }

    // New medicines start at zero. Stock must be received through a dated lot.
    $stmt = $pdo->prepare('INSERT INTO medicine (medicine_name, type_id, price, stock, status) VALUES (:medicine_name, :type_id, :price, 0, \'Not available\')');
    $stmt->execute([
        ':medicine_name' => $medicine_name,
        ':type_id' => $type_id,
        ':price' => $price
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Medicine added successfully',
        'medicine_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
