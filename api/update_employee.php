<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Lightweight request logging for debugging update issues
@mkdir(__DIR__ . '/../storage', 0777, true);
file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee REQUEST_METHOD=".($_SERVER['REQUEST_METHOD']??'')." IP=".($_SERVER['REMOTE_ADDR']??'')." RAW=".substr($raw,0,1000)."\n", FILE_APPEND);
file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee PARSED=".var_export($data, true)."\n", FILE_APPEND);
// If JSON decode failed, accept URL-encoded form POST as fallback
if (!$data || !is_array($data)) {
    $data = $_POST;
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee FALLBACK_TO_POST=".var_export($data, true)."\n", FILE_APPEND);
}

// Temporary debug mode: if ?debug=1 present, return parsed data and stop
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee DEBUG_ECHO\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode(['debug' => true, 'parsed' => $data]);
    exit;
}

$employee_id = isset($data['employee_id']) ? intval($data['employee_id']) : 0;
$employee_name = isset($data['employee_name']) ? trim($data['employee_name']) : '';
$position = isset($data['position']) ? trim($data['position']) : '';
$salary = isset($data['salary']) ? $data['salary'] : 0;
$phone = isset($data['phone']) ? trim($data['phone']) : null;
$email = isset($data['email']) ? trim($data['email']) : null;
$address = isset($data['address']) ? trim($data['address']) : null;
$status = isset($data['status']) ? trim($data['status']) : null;

if ($employee_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid employee ID']);
    exit;
}

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
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee EXECUTE_UPDATE with params=".json_encode([$employee_id,$employee_name,$position,$salary,$phone,$email])."\n", FILE_APPEND);
    // include status if provided
    if ($status !== null) {
        try {
            $stmt = $pdo->prepare('UPDATE employee SET employee_name = :employee_name, position = :position, salary = :salary, phone = :phone, email = :email, address = :address, status = :status WHERE employee_id = :employee_id');
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':employee_name' => $employee_name,
                ':position' => $position,
                ':salary' => $salary,
                ':phone' => $phone,
                ':email' => $email,
                ':address' => $address,
                ':status' => $status
            ]);
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'unknown column') !== false || stripos($e->getMessage(), "1054") !== false) {
                $pdo->exec("ALTER TABLE employee ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
                $stmt = $pdo->prepare('UPDATE employee SET employee_name = :employee_name, position = :position, salary = :salary, phone = :phone, email = :email, address = :address, status = :status WHERE employee_id = :employee_id');
                $stmt->execute([
                    ':employee_id' => $employee_id,
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
    } else {
        $stmt = $pdo->prepare('UPDATE employee SET employee_name = :employee_name, position = :position, salary = :salary, phone = :phone, email = :email, address = :address WHERE employee_id = :employee_id');
        $stmt->execute([
            ':employee_id' => $employee_id,
            ':employee_name' => $employee_name,
            ':position' => $position,
            ':salary' => $salary,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address
        ]);
    }
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee EXECUTE_UPDATE_OK affected=".$stmt->rowCount()."\n", FILE_APPEND);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Employee updated successfully'
    ]);
} catch (PDOException $e) {
    @mkdir(__DIR__ . '/../storage', 0777, true);
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    @mkdir(__DIR__ . '/../storage', 0777, true);
    file_put_contents(__DIR__ . '/../storage/update.log', "[".date('c')."] update_employee Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
