<?php
$pageTitle = 'Stock Management';
$pageSub = 'Usable stock is calculated from active, non-expired medicine lots.';
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';
$pdo = getPDO();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$medicines = [];
$schemaReady = false;
$error = null;

try {
    $schemaReady = lotStockEnabled($pdo);
    if (!$schemaReady) throw new Exception('Import database_migration_lot_stock.sql before using lot-based stock.');

    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE (v.medicine_name LIKE :name OR CAST(v.medicine_id AS CHAR) LIKE :id OR EXISTS (SELECT 1 FROM medicine_lot x WHERE x.medicine_id = v.medicine_id AND x.lot_number LIKE :lot))';
        $params = ['name' => "%{$q}%", 'id' => "%{$q}%", 'lot' => "%{$q}%"];
    }

    $sql = "SELECT v.medicine_id, v.medicine_name, v.usable_stock, v.expired_stock,
                   v.nearest_expiry_date,
                   COALESCE(SUM(CASE WHEN ml.status = 'ACTIVE' AND ml.remaining_quantity > 0
                                         AND (ml.expiry_date IS NULL OR ml.expiry_date >= CURRENT_DATE())
                                    THEN 1 ELSE 0 END), 0) AS active_lots
            FROM v_medicine_stock_summary v
            LEFT JOIN medicine_lot ml ON ml.medicine_id = v.medicine_id
            {$where}
            GROUP BY v.medicine_id, v.medicine_name, v.usable_stock, v.expired_stock, v.nearest_expiry_date
            ORDER BY v.usable_stock ASC, v.nearest_expiry_date ASC, v.medicine_name ASC
            LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="page-title mb-1">Medicine Stock</h3>
            <div class="text-muted small">FEFO: the lot with the nearest expiry date is sold first.</div>
        </div>
        <a href="/Final_Project/pages/new_stock.php" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>Add Stock Lot
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card card-compact shadow-sm">
        <div class="card-body">
            <form method="get" class="mb-3 row g-2">
                <div class="col-12 col-md-9">
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Search by medicine ID, name, or lot number" value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm w-100" type="submit">Search</button>
                    <a class="btn btn-secondary btn-sm w-100" href="/Final_Project/pages/stock.php">Clear</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px">ID</th>
                            <th>Medicine</th>
                            <th class="text-end">Usable</th>
                            <th class="text-end">Expired</th>
                            <th class="text-center">Active Lots</th>
                            <th>Nearest Expiry</th>
                            <th style="width:210px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($medicines as $medicine):
                        $usable = (int)$medicine['usable_stock'];
                        $expired = (int)$medicine['expired_stock'];
                        $nearest = $medicine['nearest_expiry_date'];
                        $daysLeft = $nearest ? (int)floor((strtotime($nearest) - strtotime(date('Y-m-d'))) / 86400) : null;
                    ?>
                        <tr>
                            <td class="fw-bold">#<?php echo (int)$medicine['medicine_id']; ?></td>
                            <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                            <td class="text-end">
                                <span class="badge <?php echo $usable <= 5 ? 'bg-danger' : ($usable <= 20 ? 'bg-warning text-dark' : 'bg-success'); ?>"><?php echo number_format($usable); ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($expired > 0): ?><span class="badge bg-danger"><?php echo number_format($expired); ?></span><?php else: ?><span class="text-muted">0</span><?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo (int)$medicine['active_lots']; ?></td>
                            <td>
                                <?php if ($nearest): ?>
                                    <?php echo htmlspecialchars($nearest); ?>
                                    <?php if ($daysLeft !== null && $daysLeft < 0): ?>
                                        <span class="badge bg-danger ms-1">Expired</span>
                                    <?php elseif ($daysLeft === 0): ?>
                                        <span class="badge bg-warning text-dark ms-1">Expires today</span>
                                    <?php elseif ($daysLeft !== null && $daysLeft <= 90): ?>
                                        <span class="badge bg-warning text-dark ms-1"><?php echo $daysLeft; ?> days</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No dated lot</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="/Final_Project/pages/stock_lots.php?medicine_id=<?php echo (int)$medicine['medicine_id']; ?>">View Lots</a>
                                <a class="btn btn-sm btn-outline-primary" href="/Final_Project/pages/new_stock.php?medicine_id=<?php echo (int)$medicine['medicine_id']; ?>">Add Lot</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$error && empty($medicines)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No medicine stock found</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
