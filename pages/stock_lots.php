<?php
$pageTitle = 'Medicine Lot Details';
$pageSub = 'Lot-level quantities, expiry dates, suppliers, and notes.';
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';
$pdo = getPDO();

$medicineId = isset($_GET['medicine_id']) && is_numeric($_GET['medicine_id'])
    ? (int)$_GET['medicine_id']
    : 0;
$medicine = null;
$lots = [];
$error = null;

try {
    if ($medicineId <= 0) throw new Exception('Invalid medicine ID.');
    if (!lotStockEnabled($pdo)) throw new Exception('Import database_migration_lot_stock.sql before using lot-based stock.');

    $medicineStmt = $pdo->prepare('SELECT medicine_id, medicine_name FROM medicine WHERE medicine_id = :id LIMIT 1');
    $medicineStmt->execute(['id' => $medicineId]);
    $medicine = $medicineStmt->fetch();
    if (!$medicine) throw new Exception('Medicine not found.');

    $lotStmt = $pdo->prepare(
        "SELECT ml.lot_id, ml.lot_number, ml.expiry_date,
                ml.received_quantity, ml.remaining_quantity, ml.status,
                ml.created_at, sr.note, sr.received_at, s.supplier_name
         FROM medicine_lot ml
         LEFT JOIN stock_receipt sr ON sr.receipt_id = ml.receipt_id
         LEFT JOIN supplier s ON s.supplier_id = sr.supplier_id
         WHERE ml.medicine_id = :id
         ORDER BY (ml.expiry_date IS NULL) ASC, ml.expiry_date ASC, ml.lot_id ASC"
    );
    $lotStmt->execute(['id' => $medicineId]);
    $lots = $lotStmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="page-title mb-1">Lot Details</h3>
            <?php if ($medicine): ?>
                <div class="text-muted small">
                    #<?php echo (int)$medicine['medicine_id']; ?> — <?php echo htmlspecialchars($medicine['medicine_name']); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="/Final_Project/pages/stock.php" class="btn btn-outline-secondary btn-sm">Back to Stock</a>
            <?php if ($medicine): ?>
                <a href="/Final_Project/pages/new_stock.php?medicine_id=<?php echo (int)$medicine['medicine_id']; ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Add Lot
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="card card-compact shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Lot Number</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Remaining</th>
                                <th>Expiry Date</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lots as $lot):
                            $remaining = (int)$lot['remaining_quantity'];
                            $expiry = $lot['expiry_date'];
                            $today = date('Y-m-d');

                            if ($remaining <= 0 || $lot['status'] === 'DEPLETED') {
                                $statusLabel = 'Depleted';
                                $statusClass = 'bg-secondary';
                            } elseif ($lot['status'] === 'RECALLED') {
                                $statusLabel = 'Recalled';
                                $statusClass = 'bg-dark';
                            } elseif (!$expiry) {
                                $statusLabel = 'Expiry unknown';
                                $statusClass = 'bg-secondary';
                            } elseif ($expiry < $today || $lot['status'] === 'EXPIRED') {
                                $statusLabel = 'Expired';
                                $statusClass = 'bg-danger';
                            } elseif ($expiry === $today) {
                                $statusLabel = 'Expires today';
                                $statusClass = 'bg-warning text-dark';
                            } else {
                                $statusLabel = 'Active';
                                $statusClass = 'bg-success';
                            }
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($lot['lot_number']); ?></div>
                                    <div class="text-muted small">Lot ID #<?php echo (int)$lot['lot_id']; ?></div>
                                </td>
                                <td class="text-end"><?php echo number_format((int)$lot['received_quantity']); ?></td>
                                <td class="text-end fw-semibold"><?php echo number_format($remaining); ?></td>
                                <td><?php echo $expiry ? htmlspecialchars($expiry) : '<span class="text-muted">Unknown</span>'; ?></td>
                                <td><?php echo $lot['supplier_name'] ? htmlspecialchars($lot['supplier_name']) : '<span class="text-muted">—</span>'; ?></td>
                                <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                <td><?php echo $lot['note'] ? htmlspecialchars($lot['note']) : '<span class="text-muted">—</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lots)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No lots found for this medicine</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
