<?php
// functions/product_functions.php
require_once __DIR__ . '/../config/db.php';

function lotStockEnabled($pdo)
{
    static $enabled = null;
    if ($enabled !== null) return $enabled;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicine_lot'");
    $stmt->execute();
    $enabled = ((int)$stmt->fetchColumn() > 0);
    return $enabled;
}

function productStockSource($pdo)
{
    return lotStockEnabled($pdo) ? 'v_medicine_stock_summary' : 'medicine';
}

function getAllProducts($pdo, $limit = 50)
{
    $source = productStockSource($pdo);
    $stockColumn = lotStockEnabled($pdo) ? 'usable_stock AS stock' : 'stock';
    $sql = "SELECT medicine_id, medicine_name, price, {$stockColumn} FROM {$source} ORDER BY medicine_name LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function searchProducts($pdo, $q)
{
    $source = productStockSource($pdo);
    $stockColumn = lotStockEnabled($pdo) ? 'usable_stock AS stock' : 'stock';
    $sql = "SELECT medicine_id, medicine_name, price, {$stockColumn} FROM {$source} WHERE medicine_name LIKE :q OR CAST(medicine_id AS CHAR) LIKE :q_id LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['q' => "%$q%", 'q_id' => "%$q%"]);
    return $stmt->fetchAll();
}

function getProductById($pdo, $id)
{
    if (lotStockEnabled($pdo)) {
        $sql = "SELECT medicine_id, medicine_name, type_id, price, usable_stock AS stock, expired_stock, nearest_expiry_date, status FROM v_medicine_stock_summary WHERE medicine_id = :id";
    } else {
        $sql = "SELECT medicine_id, medicine_name, type_id, price, stock, status FROM medicine WHERE medicine_id = :id";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function getProductsByType($pdo)
{
    $sql = "SELECT type_id, type_name FROM medicine_type ORDER BY type_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getStockLevels($pdo, $limit = 20)
{
    $stockSource = lotStockEnabled($pdo) ? 'v_medicine_stock_summary' : 'medicine';
    $stockExpression = lotStockEnabled($pdo) ? 'm.usable_stock' : 'm.stock';
    $sql = "SELECT m.medicine_id, m.medicine_name, {$stockExpression} AS stock,
            COALESCE((SELECT l2.reorder_level FROM low_stock_alert l2 WHERE l2.medicine_id = m.medicine_id ORDER BY l2.created_at DESC LIMIT 1), 20) AS reorder_level
            FROM {$stockSource} m
            ORDER BY stock ASC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function reduceProductStock($pdo, $medicine_id, $qty)
{
    // Use distinct parameter names because PDO native prepares (EMULATE_PREPARES=false)
    // do not support reusing the same named parameter more than once.
    $sql = "UPDATE medicine SET stock = stock - :qty, status = CASE WHEN (stock - :qty2) <= 0 THEN 'Not available' ELSE 'Available' END WHERE medicine_id = :id AND stock >= :min_qty";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['qty' => $qty, 'qty2' => $qty, 'id' => $medicine_id, 'min_qty' => $qty]);
    return $stmt->rowCount() > 0;
}

function validTypeExists($pdo, $type_id)
{
    $sql = "SELECT type_id FROM medicine_type WHERE type_id = :type_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['type_id' => $type_id]);
    return $stmt->fetch() !== false;
}

