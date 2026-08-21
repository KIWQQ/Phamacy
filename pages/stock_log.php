<?php
$pageTitle = 'Lot Stock Log';
$pageSub = 'Every stock movement is traceable to a medicine lot and source.';
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';
$pdo = getPDO();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$movementType = isset($_GET['movement_type']) ? trim($_GET['movement_type']) : '';
$rows = [];
$error = null;
$movementTypes = ['OPENING_BALANCE','STOCK_IN','SALE','REFUND','ADJUST_IN','ADJUST_OUT','EXPIRED','RECALL'];

try {
    if (!lotStockEnabled($pdo)) throw new Exception('Import database_migration_lot_stock.sql before using lot stock logs.');

    $clauses = [];
    $params = [];
    if ($search !== '') {
        $clauses[] = '(m.medicine_name LIKE :search_name OR ml.lot_number LIKE :search_lot OR CAST(m.medicine_id AS CHAR) LIKE :search_id)';
        $params = [
            'search_name' => "%{$search}%",
            'search_lot' => "%{$search}%",
            'search_id' => "%{$search}%",
        ];
    }
    if ($movementType !== '' && in_array($movementType, $movementTypes, true)) {
        $clauses[] = 'sm.movement_type = :movement_type';
        $params['movement_type'] = $movementType;
    }
    $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

    $sql = "SELECT sm.movement_id, sm.movement_type, sm.quantity_change,
                   sm.note, sm.created_at,
                   ml.lot_number, ml.expiry_date,
                   m.medicine_id, m.medicine_name,
                   s.supplier_name
            FROM stock_movement sm
            JOIN medicine_lot ml ON ml.lot_id = sm.lot_id
            JOIN medicine m ON m.medicine_id = ml.medicine_id
            LEFT JOIN stock_receipt sr ON sr.receipt_id = ml.receipt_id
            LEFT JOIN supplier s ON s.supplier_id = sr.supplier_id
            {$where}
            ORDER BY sm.created_at DESC, sm.movement_id DESC
            LIMIT 1000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="page-title mb-1">Lot Stock Log</h3>
            <div class="text-muted small">Positive quantity means stock in; negative quantity means stock out.</div>
        </div>
        <a href="/pages/new_stock.php" class="btn btn-success btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Stock Lot</a>
    </div>

    <?php if ($error): ?><div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card card-compact shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-12 col-md-6">
                    <input type="search" name="search" class="form-control form-control-sm" placeholder="Medicine or lot number" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <select name="movement_type" class="form-select form-select-sm">
                        <option value="">All movement types</option>
                        <?php foreach ($movementTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $movementType === $type ? 'selected' : ''; ?>><?php echo str_replace('_', ' ', $type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Search</button>
                    <a href="/pages/stock_log.php" class="btn btn-secondary btn-sm w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-compact shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th><th>Medicine / Lot</th><th>Expiry</th><th>Type</th>
                            <th class="text-end">Change</th><th>Supplier</th><th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int)$row['movement_id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['medicine_name']); ?>
                                <div class="small text-muted">#<?php echo (int)$row['medicine_id']; ?> · Lot <?php echo htmlspecialchars($row['lot_number']); ?></div>
                            </td>
                            <td><?php echo $row['expiry_date'] ? htmlspecialchars($row['expiry_date']) : '<span class="text-muted">Unknown</span>'; ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars(str_replace('_', ' ', $row['movement_type'])); ?></span></td>
                            <td class="text-end fw-bold <?php echo (int)$row['quantity_change'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo (int)$row['quantity_change'] > 0 ? '+' : ''; ?><?php echo number_format((int)$row['quantity_change']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['supplier_name'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$error && empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">No stock movement found</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
