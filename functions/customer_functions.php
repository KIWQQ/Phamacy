<?php
// functions/customer_functions.php
require_once __DIR__ . '/../config/db.php';

function getAllCustomers($pdo)
{
        $sql = "SELECT c.customer_id, c.customer_name, c.phone, c.address, GROUP_CONCAT(m.medicine_name SEPARATOR ', ') AS allergy
            FROM customer c
            LEFT JOIN customer_allergy ca ON ca.customer_id = c.customer_id
            LEFT JOIN medicine m ON m.medicine_id = ca.medicine_id
            GROUP BY c.customer_id
            ORDER BY c.customer_id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCustomerById($pdo, $customer_id)
{
        $sql = "SELECT c.customer_id, c.customer_name, c.phone, c.address, GROUP_CONCAT(m.medicine_name SEPARATOR ', ') AS allergy
            FROM customer c
            LEFT JOIN customer_allergy ca ON ca.customer_id = c.customer_id
            LEFT JOIN medicine m ON m.medicine_id = ca.medicine_id
            WHERE c.customer_id = :id
            GROUP BY c.customer_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customer_id]);
    return $stmt->fetch();
}

function getCustomerName($pdo, $customer_id)
{
    $sql = "SELECT customer_name FROM customer WHERE customer_id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $customer_id]);
    $row = $stmt->fetch();
    return $row ? $row['customer_name'] : null;
}

function searchCustomers($pdo, $q)
{
    // allow searching by name, phone (with/without formatting or leading zero) or id
    $q_raw = trim($q);
    $q_like = "%{$q_raw}%";
    // digits-only version for phone matching
    $q_digits = preg_replace('/\D+/', '', $q_raw);
    $q_digits_like = "%{$q_digits}%";
    // also try without a leading zero (user may type with or without it)
    $q_digits_nozero = $q_digits;
    if (strlen($q_digits_nozero) > 0 && $q_digits_nozero[0] === '0') {
        $q_digits_nozero = ltrim($q_digits_nozero, '0');
    }
    $q_digits_nozero_like = "%{$q_digits_nozero}%";

        $sql = "SELECT customer_id, customer_name, phone FROM customer
            WHERE customer_name LIKE :q1
               OR phone LIKE :q2
               OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', '') LIKE :qd
               OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', '') LIKE :qd2
               OR CAST(customer_id AS CHAR) LIKE :q3
            LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
        'q1' => $q_like,
        'q2' => $q_like,
        'q3' => $q_like,
        'qd' => $q_digits_like,
        'qd2' => $q_digits_nozero_like
        ]);
    return $stmt->fetchAll();
}

function getTopCustomers($pdo, $limit = 10)
{
    $sql = "SELECT c.customer_id, c.customer_name, COALESCE(SUM(od.quantity * od.price), 0) AS total
            FROM orders o
            JOIN customer c ON c.customer_id = o.customer_id
            JOIN order_detail od ON od.order_id = o.order_id
            GROUP BY c.customer_id
            ORDER BY total DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTopCustomerSingle($pdo)
{
    $sql = "SELECT c.customer_name, COALESCE(SUM(od.quantity * od.price), 0) AS total
            FROM orders o
            JOIN customer c ON c.customer_id = o.customer_id
            JOIN order_detail od ON od.order_id = o.order_id
            GROUP BY c.customer_id
            ORDER BY total DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch();
}

function countCustomers($pdo)
{
    $sql = "SELECT COUNT(*) AS cnt FROM customer";
    $stmt = $pdo->query($sql);
    return (int)$stmt->fetchColumn();
}
