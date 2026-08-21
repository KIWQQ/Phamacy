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

$employee_id = isset($data['employee_id']) ? intval($data['employee_id']) : 0;

if ($employee_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid employee ID']);
    exit;
}

try {
    // Try to mark employee as Inactive instead of deleting
    try {
        $stmt = $pdo->prepare("UPDATE employee SET status = 'Inactive' WHERE employee_id = :employee_id");
        $stmt->execute([':employee_id' => $employee_id]);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully']);
        exit;
    } catch (PDOException $e) {
        // If status column missing, attempt to add it then retry
        if (stripos($e->getMessage(), 'unknown column') !== false || stripos($e->getMessage(), "1054") !== false) {
            try {
                $pdo->exec("ALTER TABLE employee ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
                $stmt = $pdo->prepare("UPDATE employee SET status = 'Inactive' WHERE employee_id = :employee_id");
                $stmt->execute([':employee_id' => $employee_id]);
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully (status column created)']);
                exit;
            } catch (Exception $_e) {
                // fall through to hard delete as last resort
            }
        }
        // fallback to hard delete if update/alter failed
        $stmt = $pdo->prepare('DELETE FROM employee WHERE employee_id = :employee_id');
        $stmt->execute([':employee_id' => $employee_id]);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Employee deleted']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
