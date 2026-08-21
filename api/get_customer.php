<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/customer_functions.php';

header('Content-Type: application/json');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = getPDO();

    try {
        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid id']);
            exit;
        }
        $row = getCustomerById($pdo, $id);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Customer not found']);
            exit;
        }
        // fetch medicines mapped as allergies (if any)
        try {
            $aStmt = $pdo->prepare("SELECT m.medicine_name FROM medicine m JOIN customer_allergy ca ON ca.medicine_id = m.medicine_id WHERE ca.customer_id = :id ORDER BY m.medicine_name");
            $aStmt->execute(['id' => $id]);
            $als = $aStmt->fetchAll(PDO::FETCH_COLUMN);
            if ($als && count($als) > 0) {
                $row['allergy'] = implode(', ', $als);
            }
        } catch (Exception $e) {
            // ignore; keep whatever getCustomerById returned
        }
        echo json_encode($row);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
