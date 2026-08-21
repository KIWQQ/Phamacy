<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/order_functions.php';
header('Content-Type: application/json');

$pdo = getPDO();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
// buffer any unexpected output so we can return valid JSON even if warnings/notices are emitted
ob_start();

// If the refund table exists but `refund_id` is not AUTO_INCREMENT, try to fix it here
try {
    $check = $pdo->query("SHOW TABLES LIKE 'refund'");
    $exists = (bool) ($check && $check->fetch());
    if ($exists) {
        $col = $pdo->query("SHOW COLUMNS FROM `refund` LIKE 'refund_id'")->fetch();
        if ($col && stripos($col['Extra'] ?? '', 'auto_increment') === false) {
            $maxRow = $pdo->query('SELECT COALESCE(MAX(refund_id), 0) AS maxid FROM `refund`')->fetch();
            $next = (int)$maxRow['maxid'] + 1;
            try {
                $pdo->exec("ALTER TABLE `refund` MODIFY `refund_id` INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$next}");
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Refund table exists but `refund_id` is not AUTO_INCREMENT and automatic ALTER failed: ' . $e->getMessage(),
                    'fix_sql' => "ALTER TABLE `refund` MODIFY `refund_id` INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$next};",
                ]);
                exit;
            }
        }
    }
} catch (Exception $e) {
    // ignore here; refundOrder() will handle and return informative errors
}

if (!$data || !isset($data['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

$order_id = intval($data['order_id']);
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order_id']);
    exit;
}

try {
    $refund_id = refundOrder($pdo, $order_id);
    $buf = ob_get_clean(); if ($buf) error_log('refund_order buffered output before success: ' . $buf);
    echo json_encode(['success' => true, 'refund_id' => (int)$refund_id]);
} catch (Exception $e) {
    // If the refund table doesn't exist, attempt to create it from the migration and retry once
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'refund'");
        $exists = (bool)$check->fetch();
    } catch (Exception $inner) {
        $exists = false;
    }

    if (!$exists) {
        $mig = __DIR__ . '/../migrations/002_create_refund_tables.sql';
        if (file_exists($mig)) {
            try {
                $sql = file_get_contents($mig);
                // remove block comments /* ... */
                $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
                // remove -- style comments
                $lines = preg_split("/\r\n|\n|\r/", $sql);
                $cleanLines = [];
                foreach ($lines as $line) {
                    $line = preg_replace('/^\s*--.*$/', '', $line);
                    if (trim($line) !== '') $cleanLines[] = $line;
                }
                $cleanSql = implode("\n", $cleanLines);
                $stmts = array_filter(array_map('trim', explode(';', $cleanSql)));
                foreach ($stmts as $s) {
                    if ($s === '') continue;
                    $pdo->exec($s);
                }
                // retry refund once
                $refund_id = refundOrder($pdo, $order_id);
                $buf = ob_get_clean(); if ($buf) error_log('refund_order buffered output after migration: ' . $buf);
                echo json_encode(['success' => true, 'refund_id' => (int)$refund_id, 'note' => 'Created refund tables from migration and retried.']);
                exit;
            } catch (Exception $migErr) {
                error_log('Failed to apply refund migration: ' . $migErr->getMessage());
            }
        }
    }

    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    $out = ['error' => $e->getMessage()];
    if (function_exists('debug_backtrace')) $out['trace'] = $e->getTraceAsString();
    // capture any buffered output (PHP warnings/notices) and include in logs and optional debug field
    $buffer = ob_get_clean();
    if ($buffer) {
        error_log("refund_order unexpected output: " . $buffer);
        // include raw output when debug requested via ?debug=1 or payload debug
        $wantDebug = (isset($_GET['debug']) && $_GET['debug'] == '1') || (!empty($data['debug']));
        if ($wantDebug) $out['raw_output'] = $buffer;
    }
    error_log("refund_order error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\nPAYLOAD: " . $raw);
    echo json_encode($out);
}
