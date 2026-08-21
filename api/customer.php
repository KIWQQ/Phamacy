<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$mysqli = getMySQLi();
$action = isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : 'get';

function send_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function get_request_data() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return array_merge($_REQUEST, $data);
    }
    return $_REQUEST;
}

function validate_customer_fields($data) {
    $name = isset($data['customer_name']) ? trim($data['customer_name']) : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $address = isset($data['address']) ? trim($data['address']) : null;
    if ($name === '') {
        send_response(['error' => 'Customer name is required'], 400);
    }
    if ($phone === '') {
        send_response(['error' => 'Phone number is required'], 400);
    }
    return [$name, $phone, $address];
}

function sync_customer_allergies($mysqli, $customer_id, $allergy_raw) {
    // Accept comma-separated allergy names (or null/empty to clear)
    if ($customer_id <= 0) return;
    // normalize input to array of trimmed non-empty names
    $names = [];
    $ids = [];
    if (is_array($allergy_raw)) {
        // detect numeric IDs array (medicine_id values)
        $all = array_values($allergy_raw);
        $all_is_numeric = count($all) > 0 && array_reduce($all, fn($carry,$v)=> $carry && (is_numeric($v) || $v===null), true);
        if ($all_is_numeric) {
            $ids = array_map('intval', $all);
        } else {
            $names = array_map('trim', $allergy_raw);
        }
    } elseif (is_string($allergy_raw)) {
        $parts = array_map('trim', explode(',', $allergy_raw));
        $names = array_filter($parts, fn($s) => $s !== '');
    }

    // remove existing mappings
    $stmt = $mysqli->prepare('DELETE FROM customer_allergy WHERE customer_id = ?');
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();

    if (empty($names) && empty($ids)) return;

    // for each name, ensure allergy exists and create mapping
    // we expect customer_allergy to reference medicines (medicine_id)
    $findStmt = $mysqli->prepare('SELECT medicine_id FROM medicine WHERE medicine_name = ? LIMIT 1');
    $findStmtLike = $mysqli->prepare('SELECT medicine_id FROM medicine WHERE medicine_name LIKE ? LIMIT 1');
    $insMap = $mysqli->prepare('INSERT INTO customer_allergy (customer_id, medicine_id) VALUES (?, ?)');
    // if numeric ids provided, insert them directly
    if (!empty($ids)) {
        foreach ($ids as $mid) {
            if (!$mid) continue;
            $midi = (int)$mid;
            $insMap->bind_param('ii', $customer_id, $midi);
            $insMap->execute();
        }
        return;
    }
    foreach ($names as $nm) {
        if ($nm === '') continue;
        // find medicine by name (case-insensitive)
        $findStmt->bind_param('s', $nm);
        $findStmt->execute();
        $res = $findStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && isset($row['medicine_id'])) {
            $mid = (int)$row['medicine_id'];
            $insMap->bind_param('ii', $customer_id, $mid);
            $insMap->execute();
            continue;
        }
        // try a fuzzy match if exact name not found
        $likeParam = '%' . $nm . '%';
        $findStmtLike->bind_param('s', $likeParam);
        $findStmtLike->execute();
        $res2 = $findStmtLike->get_result();
        $row2 = $res2 ? $res2->fetch_assoc() : null;
        if ($row2 && isset($row2['medicine_id'])) {
            $mid = (int)$row2['medicine_id'];
            $insMap->bind_param('ii', $customer_id, $mid);
            $insMap->execute();
        }
    }
}

try {
    if ($action === 'create' || $action === 'update' || $action === 'delete') {
        $data = get_request_data();
    }

    if ($action === 'create') {
        list($name, $phone, $address) = validate_customer_fields($data);
        $status = isset($data['status']) ? $data['status'] : 'Active';
        // attempt to insert status column; create column if missing
        $insertSql = 'INSERT INTO customer (customer_name, phone, address, status) VALUES (?, ?, ?, ?)';
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
            // try creating column then prepare again
            $mysqli->query("ALTER TABLE customer ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
            $stmt = $mysqli->prepare($insertSql);
        }
        $stmt->bind_param('ssss', $name, $phone, $address, $status);
        if (!$stmt->execute()) {
            send_response(['error' => 'Insert failed: ' . $stmt->error], 500);
        }
        $cid = $mysqli->insert_id;
        // sync allergies if provided (accept comma-separated string in `allergy`)
        $allergy_raw = isset($data['allergy']) ? $data['allergy'] : null;
        sync_customer_allergies($mysqli, $cid, $allergy_raw);
        send_response(['success' => true, 'message' => 'Customer added successfully', 'customer_id' => $cid], 201);
    }

    if ($action === 'update') {
        $customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
        if ($customer_id <= 0) {
            send_response(['error' => 'Invalid customer ID'], 400);
        }
        // If caller only intends to update status, allow a status-only update without requiring name/phone
        $status = isset($data['status']) ? $data['status'] : null;
        $hasName = isset($data['customer_name']) && trim($data['customer_name']) !== '';
        $hasPhone = isset($data['phone']) && trim($data['phone']) !== '';

        if ($status !== null && !$hasName && !$hasPhone && !isset($data['address'])) {
            // perform status-only update (soft-activate/soft-deactivate)
            $upd = $mysqli->prepare('UPDATE customer SET status = ? WHERE customer_id = ?');
            if (!$upd) {
                // add status column if missing then retry
                $mysqli->query("ALTER TABLE customer ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
                $upd = $mysqli->prepare('UPDATE customer SET status = ? WHERE customer_id = ?');
            }
            $upd->bind_param('si', $status, $customer_id);
            if (!$upd->execute()) {
                send_response(['error' => 'Update failed: ' . $upd->error], 500);
            }
            send_response(['success' => true, 'message' => 'Customer status updated successfully']);
        }

        // Full update path (name/phone required)
        list($name, $phone, $address) = validate_customer_fields($data);
        if ($status !== null) {
            // attempt to update including status, create column if missing
            $upd = $mysqli->prepare('UPDATE customer SET customer_name = ?, phone = ?, address = ?, status = ? WHERE customer_id = ?');
            if (!$upd) {
                $mysqli->query("ALTER TABLE customer ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
                $upd = $mysqli->prepare('UPDATE customer SET customer_name = ?, phone = ?, address = ?, status = ? WHERE customer_id = ?');
            }
            $upd->bind_param('ssssi', $name, $phone, $address, $status, $customer_id);
            $stmt = $upd;
        } else {
            $stmt = $mysqli->prepare('UPDATE customer SET customer_name = ?, phone = ?, address = ? WHERE customer_id = ?');
            $stmt->bind_param('sssi', $name, $phone, $address, $customer_id);
        }
        if (!$stmt->execute()) {
            send_response(['error' => 'Update failed: ' . $stmt->error], 500);
        }
        // sync allergies if provided (accept comma-separated string in `allergy`)
        $allergy_raw = isset($data['allergy']) ? $data['allergy'] : null;
        sync_customer_allergies($mysqli, $customer_id, $allergy_raw);
        send_response(['success' => true, 'message' => 'Customer updated successfully']);
    }

    if ($action === 'delete') {
        $customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
        if ($customer_id <= 0) {
            send_response(['error' => 'Invalid customer ID'], 400);
        }

        // Soft-deactivate customer: set status = 'Inactive'
        $upd = $mysqli->prepare('UPDATE customer SET status = ? WHERE customer_id = ?');
        if (!$upd) {
            // add status column if missing then retry
            $mysqli->query("ALTER TABLE customer ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'");
            $upd = $mysqli->prepare('UPDATE customer SET status = ? WHERE customer_id = ?');
        }
        $inactive = 'Inactive';
        $upd->bind_param('si', $inactive, $customer_id);
        if (!$upd->execute()) {
            send_response(['error' => 'Failed to deactivate customer: ' . $upd->error], 500);
        }
        send_response(['success' => true, 'message' => 'Customer deactivated successfully']);
    }

    // GET logic: get all customer list or single customer if id provided
    if ($action === 'get') {
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        if ($customer_id > 0) {
            $stmt = $mysqli->prepare("SELECT c.customer_id, c.customer_name, c.phone, c.address, GROUP_CONCAT(m.medicine_name SEPARATOR ', ') AS allergy FROM customer c LEFT JOIN customer_allergy ca ON ca.customer_id = c.customer_id LEFT JOIN medicine m ON m.medicine_id = ca.medicine_id WHERE c.customer_id = ? GROUP BY c.customer_id LIMIT 1");
            $stmt->bind_param('i', $customer_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                send_response(['error' => 'Customer not found'], 404);
            }
            send_response(['customer' => $row]);
        }
        $sql = "SELECT c.customer_id, c.customer_name, c.phone, c.address, GROUP_CONCAT(m.medicine_name SEPARATOR ', ') AS allergy FROM customer c LEFT JOIN customer_allergy ca ON ca.customer_id = c.customer_id LEFT JOIN medicine m ON m.medicine_id = ca.medicine_id GROUP BY c.customer_id ORDER BY c.customer_id DESC";
        $result = $mysqli->query($sql);
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        send_response(['customers' => $customers]);
    }

    send_response(['error' => 'Invalid action'], 400);
} catch (Exception $e) {
    if ($mysqli->errno) {
        $mysqli->rollback();
    }
    send_response(['error' => 'Server error: ' . $e->getMessage()], 500);
}
