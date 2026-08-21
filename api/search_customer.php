<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/customer_functions.php';

header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$pdo = getPDO();

try {
    if ($q === '') {
        // return a short recent customer list when empty (for select dropdown)
        $all = getAllCustomers($pdo);
        $short = array_slice($all, 0, 50);
        $out = array_map(function($r){ return ['customer_id'=>$r['customer_id'],'customer_name'=>$r['customer_name'],'phone'=>$r['phone'] ?? '']; }, $short);
        @file_put_contents(__DIR__ . '/../storage/search_customer.log', json_encode(['time'=>date('c'),'q'=>'EMPTY','count'=>count($out)]) . "\n", FILE_APPEND);
        echo json_encode($out);
        exit;
    }
    $rows = searchCustomers($pdo, $q);
    // log query and number of results for debugging
    $log = [ 'time' => date('c'), 'q' => $q, 'count' => count($rows) ];
    @file_put_contents(__DIR__ . '/../storage/search_customer.log', json_encode($log) . "\n", FILE_APPEND);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/../storage/search_customer.log', "[".date('c')."] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
}
