<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$medicineId = isset($payload['medicine_id']) ? (int)$payload['medicine_id'] : 0;
$quantity = isset($payload['quantity']) ? (int)$payload['quantity'] : 0;
$lotNumber = isset($payload['lot_number']) ? trim($payload['lot_number']) : '';
$expiryDate = isset($payload['expiry_date']) ? trim($payload['expiry_date']) : '';
$supplierName = isset($payload['supplier_name']) ? trim($payload['supplier_name']) : '';
$note = isset($payload['note']) ? trim($payload['note']) : null;

$errors = [];
if ($medicineId <= 0) $errors[] = 'Please select a medicine.';
if ($quantity <= 0) $errors[] = 'Quantity must be greater than zero.';
if ($lotNumber === '') $errors[] = 'Lot number is required.';
if ($expiryDate === '' || !DateTime::createFromFormat('Y-m-d', $expiryDate)) $errors[] = 'A valid expiry date is required.';
if ($expiryDate !== '' && $expiryDate < date('Y-m-d')) $errors[] = 'Cannot receive an already expired lot.';
if ($supplierName === '') $errors[] = 'Supplier name is required.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

try {
    if (!lotStockEnabled($pdo)) {
        throw new Exception('Lot-stock database migration has not been imported. Import database_migration_lot_stock.sql first.');
    }

    $medicineStmt = $pdo->prepare("SELECT medicine_id FROM medicine WHERE medicine_id = :id AND status <> 'Discontinued' LIMIT 1");
    $medicineStmt->execute(['id' => $medicineId]);
    if (!$medicineStmt->fetchColumn()) throw new Exception('Medicine not found or discontinued.');

    $pdo->beginTransaction();

    $supplierStmt = $pdo->prepare('SELECT supplier_id FROM supplier WHERE supplier_name = :name LIMIT 1');
    $supplierStmt->execute(['name' => $supplierName]);
    $supplierId = (int)$supplierStmt->fetchColumn();
    if ($supplierId <= 0) {
        $insertSupplier = $pdo->prepare('INSERT INTO supplier (supplier_name) VALUES (:name)');
        $insertSupplier->execute(['name' => $supplierName]);
        $supplierId = (int)$pdo->lastInsertId();
    }

    $receiptStmt = $pdo->prepare('INSERT INTO stock_receipt (supplier_id, received_at, note) VALUES (:supplier_id, NOW(), :note)');
    $receiptStmt->execute([
        'supplier_id' => $supplierId,
        'note' => $note ?: null,
    ]);
    $receiptId = (int)$pdo->lastInsertId();

    $lotStmt = $pdo->prepare("INSERT INTO medicine_lot (medicine_id, receipt_id, lot_number, expiry_date, received_quantity, remaining_quantity, status) VALUES (:medicine_id, :receipt_id, :lot_number, :expiry_date, :received_quantity, :remaining_quantity, 'ACTIVE')");
    $lotStmt->execute([
        'medicine_id' => $medicineId,
        'receipt_id' => $receiptId,
        'lot_number' => $lotNumber,
        'expiry_date' => $expiryDate,
        'received_quantity' => $quantity,
        'remaining_quantity' => $quantity,
    ]);
    $lotId = (int)$pdo->lastInsertId();

    $statusStmt = $pdo->prepare("UPDATE medicine SET status = 'Available' WHERE medicine_id = :id AND status <> 'Discontinued'");
    $statusStmt->execute(['id' => $medicineId]);

    $pdo->commit();

    $summaryStmt = $pdo->prepare('SELECT usable_stock, expired_stock, nearest_expiry_date FROM v_medicine_stock_summary WHERE medicine_id = :id');
    $summaryStmt->execute(['id' => $medicineId]);
    $summary = $summaryStmt->fetch() ?: [];

    http_response_code(201);
    echo json_encode([
        'ok' => true,
        'receipt_id' => $receiptId,
        'lot_id' => $lotId,
        'medicine_id' => $medicineId,
        'new_stock' => isset($summary['usable_stock']) ? (int)$summary['usable_stock'] : null,
        'nearest_expiry_date' => $summary['nearest_expiry_date'] ?? null,
    ]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $message = $e->getCode() === '23000'
        ? 'This lot number already exists for the selected medicine.'
        : 'Database error: ' . $e->getMessage();
    http_response_code(400);
    echo json_encode(['error' => $message]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
