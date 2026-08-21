<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$pdo = getPDO();

try {
    if ($q === '') {
        // return a short product list for empty queries
        $all = getAllProducts($pdo, 50);
        $out = array_map(function($r){ return ['medicine_id'=>$r['medicine_id'],'medicine_name'=>$r['medicine_name'],'price'=>$r['price']]; }, $all);
        @file_put_contents(__DIR__ . '/../storage/search_product.log', json_encode(['time'=>date('c'),'q'=>'EMPTY','count'=>count($out)]) . "\n", FILE_APPEND);
        echo json_encode($out);
        exit;
    }
    $rows = searchProducts($pdo, $q);
    @file_put_contents(__DIR__ . '/../storage/search_product.log', json_encode(['time'=>date('c'),'q'=>$q,'count'=>count($rows)]) . "\n", FILE_APPEND);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/../storage/search_product.log', json_encode(['time'=>date('c'),'q'=>$q,'error'=>$e->getMessage()]) . "\n", FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
}
