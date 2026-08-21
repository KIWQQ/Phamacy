<?php
$pageTitle = 'Refunds';
ob_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

$error = null;
$refunds = [];

try {
    // Get refunds and precompute refund detail totals (fallback when total_amount is 0)
    $sql = 'SELECT r.refund_id, r.order_id, r.refund_date, r.total_amount, COALESCE(rd_sum.computed_total,0) AS computed_total, o.customer_id, c.customer_name
            FROM refund r
            LEFT JOIN (
                SELECT refund_id, SUM(quantity * price) AS computed_total FROM refund_detail GROUP BY refund_id
            ) rd_sum ON rd_sum.refund_id = r.refund_id
            LEFT JOIN orders o ON o.order_id = r.order_id
            LEFT JOIN customer c ON c.customer_id = o.customer_id
            ORDER BY r.refund_date DESC
            LIMIT 200';
    $stmt = $pdo->query($sql);
    $refunds = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

$refundCount = count($refunds);
$refundTotal = array_reduce($refunds, function($carry, $item) {
    $stored = floatval($item['total_amount']);
    $computed = floatval($item['computed_total'] ?? 0);
    return $carry + ($stored > 0 ? $stored : $computed);
}, 0.0);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Refund History</h3>
            <small class="text-muted">ดูประวัติและค้นหา refund ได้อย่างเป็นระเบียบ</small>
        </div>
        <div>
            <a href="/pages/order_list.php" class="btn btn-outline-secondary btn-sm">Back to Orders</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning">Unable to load refunds: <?php echo htmlspecialchars($error); ?>. Run the refund migration (migrations/002_create_refund_tables.sql).</div>
    <?php endif; ?>

    <?php if (!empty($refunds)): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Refund History</h6>
                    <div class="input-group" style="max-width: 420px;">
                        <input type="text" id="refundHistorySearch" class="form-control" placeholder="Order ID, Customer, Refund ID...">
                        <span class="input-group-text">Search</span>
                        
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body table-responsive">
                <table id="refundTable" class="table table-sm table-striped table-hover align-middle">
                    <thead class="table-light"><tr><th>#</th><th>Order</th><th>Customer</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($refunds as $r): ?>
                        <tr>
                            <td><?php echo intval($r['refund_id']); ?></td>
                            <td><a href="/pages/order_view.php?order_id=<?php echo intval($r['order_id']); ?>"><?php echo intval($r['order_id']); ?></a></td>
                            <td><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($r['refund_date']))); ?></td>
                            <td class="text-end">฿ <?php echo number_format($r['total_amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <?php if (!$error): ?>
            <div class="alert alert-info">No refunds found.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function(){
    const refundHistorySearch = document.getElementById('refundHistorySearch');
    const refundTableRows = document.querySelectorAll('#refundTable tbody tr');

    if (refundHistorySearch) {
        refundHistorySearch.addEventListener('input', (e) => {
            const keyword = e.target.value.trim().toLowerCase();
            refundTableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = keyword === '' || text.includes(keyword) ? '' : 'none';
            });
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
