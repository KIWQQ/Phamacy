<?php
// functions/employee_functions.php
require_once __DIR__ . '/../config/db.php';

function getAllEmployees($pdo)
{
    $sql = "SELECT employee_id, employee_name, position, salary, phone, email, address FROM employee ORDER BY employee_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getEmployeeById($pdo, $employee_id)
{
    $sql = "SELECT employee_id, employee_name, position, salary, phone, email, address FROM employee WHERE employee_id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $employee_id]);
    return $stmt->fetch();
}

function countEmployees($pdo)
{
    $sql = "SELECT COUNT(*) AS cnt FROM employee";
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
}
