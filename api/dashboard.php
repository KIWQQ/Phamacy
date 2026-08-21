<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/customer_functions.php';
require_once __DIR__ . '/../functions/order_functions.php';
require_once __DIR__ . '/../functions/product_functions.php';

header('Content-Type: application/json');

$pdo = getPDO();
try {
    // optional start/end for range queries
    $start = isset($_GET['start']) && $_GET['start'] !== '' ? trim($_GET['start']) : null;
    $end = isset($_GET['end']) && $_GET['end'] !== '' ? trim($_GET['end']) : null;
    $view = isset($_GET['view']) ? $_GET['view'] : 'top_products';

    // Total Sales KPI defaults to last 30 days unless a range is provided
    if ($start && $end) {
        // normalize dates
        $s = date('Y-m-d', strtotime($start));
        $e = date('Y-m-d', strtotime($end));
        $stmtTot = $pdo->prepare("SELECT COALESCE(SUM(od.quantity * od.price),0) AS total FROM orders o JOIN order_detail od ON od.order_id = o.order_id WHERE DATE(o.order_date) BETWEEN :s AND :e");
        $stmtTot->execute(['s' => $s, 'e' => $e]);
        $rowTot = $stmtTot->fetch();
        $allSales = $rowTot ? (float)$rowTot['total'] : 0.0;
    } else {
        $allSales = getTotalSales($pdo, 30); // last 30 days total
    }

    $data = ['total_sales' => (float)$allSales, 'items' => [], 'alerts' => [], 'top_customer' => null, 'top_product' => null];

    // Pull usable (non-expired) stock after the lot migration.
    $alertSource = lotStockEnabled($pdo) ? 'v_medicine_stock_summary' : 'medicine';
    $alertStock = lotStockEnabled($pdo) ? 'm.usable_stock' : 'm.stock';
    $alertSql = "SELECT m.medicine_id, m.medicine_name, {$alertStock} AS current_stock,
        COALESCE((SELECT l2.reorder_level FROM low_stock_alert l2 WHERE l2.medicine_id = m.medicine_id ORDER BY l2.created_at DESC LIMIT 1), 20) AS reorder_level,
        (SELECT l2.alert_message FROM low_stock_alert l2 WHERE l2.medicine_id = m.medicine_id ORDER BY l2.created_at DESC LIMIT 1) AS alert_message,
        (SELECT l2.created_at FROM low_stock_alert l2 WHERE l2.medicine_id = m.medicine_id ORDER BY l2.created_at DESC LIMIT 1) AS created_at
        FROM {$alertSource} m
        WHERE {$alertStock} <= COALESCE((SELECT l2.reorder_level FROM low_stock_alert l2 WHERE l2.medicine_id = m.medicine_id ORDER BY l2.created_at DESC LIMIT 1), 20)
        ORDER BY created_at DESC
        LIMIT 6";
    $alertStmt = $pdo->prepare($alertSql);
    $alertStmt->execute();
    $alerts = $alertStmt->fetchAll();
    foreach ($alerts as $a) {
        $msg = $a['alert_message'] ?: ('Low stock: ' . $a['medicine_name']);
        $data['alerts'][] = ['medicine_name' => $a['medicine_name'], 'message' => $msg, 'current_stock' => (int)$a['current_stock'], 'reorder_level' => (int)$a['reorder_level'], 'created_at' => $a['created_at']];
    }

    if ($view === 'sales_month') {
        // Last 6 months sales by month
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS period, COALESCE(SUM(od.quantity * od.price),0) AS total
            FROM orders o
            JOIN order_detail od ON od.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY period
            ORDER BY period ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $data['items'][] = ['label' => $r['period'], 'value' => (float)$r['total']];
        }
    } elseif ($view === 'sales_day') {
        // Use function for last 14 days
        $rows = getSalesByDay($pdo, 14);
        foreach ($rows as $r) {
            $data['items'][] = ['label' => $r['date'], 'value' => (float)$r['total']];
        }
    } elseif ($view === 'sales_year') {
        // Last 5 years sales by year
        $stmt = $pdo->prepare("SELECT YEAR(o.order_date) AS period, COALESCE(SUM(od.quantity * od.price),0) AS total
            FROM orders o
            JOIN order_detail od ON od.order_id = o.order_id
            WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
            GROUP BY period
            ORDER BY period ASC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $data['items'][] = ['label' => $r['period'], 'value' => (float)$r['total']];
        }
    } elseif ($view === 'sales_customer') {
        // Use function for top customers
        $rows = getTopCustomers($pdo, 10);
        foreach ($rows as $r) {
            $data['items'][] = ['label' => $r['customer_name'], 'value' => (float)$r['total']];
        }
    } else {
        // Use function for top products
        // top_products default
        if ($view === 'sales_range' && $start && $end) {
            // aggregate by date range: fallback to top products in range
            $s = date('Y-m-d', strtotime($start));
            $e = date('Y-m-d', strtotime($end));
            $stmt = $pdo->prepare("SELECT m.medicine_name AS label, COALESCE(SUM(od.quantity),0) AS value FROM order_detail od JOIN orders o ON o.order_id = od.order_id LEFT JOIN medicine m ON m.medicine_id = od.medicine_id WHERE DATE(o.order_date) BETWEEN :s AND :e GROUP BY od.medicine_id ORDER BY value DESC LIMIT 50");
            $stmt->execute(['s' => $s, 'e' => $e]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) $data['items'][] = ['label' => $r['label'], 'value' => (float)$r['value']];
        } else {
            $rows = getTopProducts($pdo, 10);
            foreach ($rows as $r) {
                $data['items'][] = ['label' => $r['medicine_name'], 'value' => (float)$r['sold']];
            }
        }
    }

    // Use function for stock levels
    if ($view === 'stock_levels') {
        $data['items'] = [];
        $rows = getStockLevels($pdo, 20);
        foreach ($rows as $r) {
            $data['items'][] = ['label' => $r['medicine_name'], 'value' => (int)$r['stock'], 'reorder_level' => (int)$r['reorder_level']];
        }
    }

    // Use functions for top customer and product; honor optional range
    if ($start && $end) {
        $s = date('Y-m-d', strtotime($start));
        $e = date('Y-m-d', strtotime($end));
        // top customer in range
        $stmt = $pdo->prepare("SELECT c.customer_name, COALESCE(SUM(od.quantity * od.price),0) AS total FROM orders o JOIN order_detail od ON od.order_id = o.order_id JOIN customer c ON c.customer_id = o.customer_id WHERE DATE(o.order_date) BETWEEN :s AND :e GROUP BY o.customer_id ORDER BY total DESC LIMIT 1");
        $stmt->execute(['s' => $s, 'e' => $e]);
        $topCust = $stmt->fetch();
        if ($topCust) $data['top_customer'] = ['name' => $topCust['customer_name'], 'value' => (float)$topCust['total']];

        // top product in range
        $stmt2 = $pdo->prepare("SELECT m.medicine_name, COALESCE(SUM(od.quantity),0) AS sold FROM order_detail od JOIN orders o ON o.order_id = od.order_id LEFT JOIN medicine m ON m.medicine_id = od.medicine_id WHERE DATE(o.order_date) BETWEEN :s AND :e GROUP BY od.medicine_id ORDER BY sold DESC LIMIT 1");
        $stmt2->execute(['s' => $s, 'e' => $e]);
        $topProd = $stmt2->fetch();
        if ($topProd) $data['top_product'] = ['name' => $topProd['medicine_name'], 'value' => (float)$topProd['sold']];
    } else {
        $topCust = getTopCustomerSingle($pdo);
        if ($topCust) {
            $data['top_customer'] = ['name' => $topCust['customer_name'], 'value' => (float)$topCust['total']];
        }
        $topProd = getTopProductSingle($pdo);
        if ($topProd) {
            $data['top_product'] = ['name' => $topProd['medicine_name'], 'value' => (float)$topProd['sold']];
        }
    }

    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
