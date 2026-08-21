<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$allowed = [7, 30, 90, 365];
if (!in_array($days, $allowed, true)) {
    $days = 30;
}

$host = '127.0.0.1';
$db   = 'pharmacy';
$user = 'root';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$stmt = $mysqli->prepare("SELECT DATE(order_date) AS date, COALESCE(SUM(total_amount),0) AS total
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(order_date)
    ORDER BY date ASC");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed']);
    exit;
}

$stmt->bind_param('i', $days);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$values = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['date'];
    $values[] = (float)$row['total'];
}

$stmt->close();
$mysqli->close();

echo json_encode(['labels' => $labels, 'values' => $values]);
