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

$employee_name = isset($data['employee_name']) ? trim($data['employee_name']) : '';
$position = isset($data['position']) ? trim($data['position']) : '';
$salary = isset($data['salary']) ? $data['salary'] : 0;
$phone = isset($data['phone']) ? trim($data['phone']) : null;
$email = isset($data['email']) ? trim($data['email']) : null;
$address = isset($data['address']) ? trim($data['address']) : null;
$status = isset($data['status']) ? trim($data['status']) : 'Active';

if (empty($employee_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Employee name is required']);
    exit;
}

if (empty($position)) {
    http_response_code(400);
    echo json_encode(['error' => 'Position is required']);
    exit;
}

$salary = is_numeric($salary) ? floatval($salary) : 0;

if ($salary < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Salary must be a positive number']);
    exit;
}

try {
    try {
        $stmt = $pdo->prepare('INSERT INTO employee (employee_name, position, salary, phone, email, address, status) VALUES (:employee_name, :position, :salary, :phone, :email, :address, :status)');
        $stmt->execute([
            ':employee_name' => $employee_name,
            ':position' => $position,
            ':salary' => $salary,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':status' => $status
        ]);
    } catch (PDOException $e) {
        // if status column missing, create it and retry
        if (stripos($e->getMessage(), 'unknown column') !== false || stripos($e->getMessage(), "1054") !== false) {
            $pdo->exec("ALTER TABLE employee ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
            $stmt = $pdo->prepare('INSERT INTO employee (employee_name, position, salary, phone, email, address, status) VALUES (:employee_name, :position, :salary, :phone, :email, :address, :status)');
            $stmt->execute([
                ':employee_name' => $employee_name,
                ':position' => $position,
                ':salary' => $salary,
                ':phone' => $phone,
                ':email' => $email,
                ':address' => $address,
                ':status' => $status
            ]);
        } else throw $e;
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Employee added successfully',
        'employee_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
