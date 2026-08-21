<?php
$pageTitle = 'Order Details';
$pageSub = '';
ob_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order = null;
$items = [];
$refund = null;
$error = null;

if ($orderId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT o.order_id, o.order_date, o.status, o.payment_method, c.customer_name, e.employee_name
             FROM orders o
             LEFT JOIN customer c ON c.customer_id = o.customer_id
             LEFT JOIN employee e ON e.employee_id = o.employee_id
             WHERE o.order_id = :order_id'
        );
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $itemStmt = $pdo->prepare(
                'SELECT od.quantity, COALESCE(m.price, od.price) AS unit_price, od.price AS recorded_price, m.medicine_name, od.medicine_id
                 FROM order_detail od
                 LEFT JOIN medicine m ON m.medicine_id = od.medicine_id
                 WHERE od.order_id = :order_id'
            );
            $itemStmt->execute([':order_id' => $orderId]);
            $items = $itemStmt->fetchAll();
            if (empty($items)) {
                $error = 'No products found for this order. Please check order_detail and medicine data.';
            } else {
                $missing = array_filter($items, fn($it) => empty($it['medicine_name']) || empty($it['medicine_id']));
                if (!empty($missing)) {
                    error_log('order_view warning: missing medicine reference in order_detail for order ' . $orderId . ' items: ' . json_encode($missing));
                }
            }

            // Calculate expected total using medicine prices from DB
            $totalStmt = $pdo->prepare('SELECT COALESCE(SUM(od.quantity * COALESCE(m.price, od.price)), 0) AS calc_total, COALESCE(SUM(od.quantity * od.price), 0) AS recorded_total FROM order_detail od LEFT JOIN medicine m ON m.medicine_id = od.medicine_id WHERE od.order_id = :order_id');
            $totalStmt->execute(['order_id' => $orderId]);
            $calcTotalRow = $totalStmt->fetch();
            $calcTotal = $calcTotalRow ? (float)$calcTotalRow['calc_total'] : 0.0;
            $recordedTotal = $calcTotalRow ? (float)$calcTotalRow['recorded_total'] : 0.0;

            $refundStmt = $pdo->prepare('SELECT refund_id, refund_date, total_amount FROM refund WHERE order_id = :order_id LIMIT 1');
            $refundStmt->execute(['order_id' => $orderId]);
            $refund = $refundStmt->fetch();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Order Details</h3>
            <?php if ($order): ?>
                <small class="text-muted">Order #<?php echo htmlspecialchars($order['order_id']); ?> • <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="/pages/order_list.php" class="btn btn-outline-secondary btn-sm">Back to Orders</a>
            <?php if ($order && !$refund): ?>
                <button type="button" class="btn btn-outline-danger btn-sm" id="refundOrderBtn">Refund</button>
            <?php elseif ($refund): ?>
                <span class="badge bg-refund align-self-center">Refunded</span>
            <?php endif; ?>
            <?php if ($order): ?>
                <a href="/pages/order_list.php?search=<?php echo urlencode($order['order_id']); ?>" class="btn btn-outline-primary btn-sm">View in list</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">Error loading order: <?php echo htmlspecialchars($error); ?></div>
        <?php if (!empty($detailRows)): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">Diagnostics: order_detail rows for order <?php echo $orderId; ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead><tr><th>order_detail_id</th><th>medicine_id</th><th>quantity</th><th>price</th></tr></thead>
                            <tbody>
                            <?php foreach ($detailRows as $dr): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dr['order_detail_id']); ?></td>
                                    <td><?php echo htmlspecialchars($dr['medicine_id']); ?></td>
                                    <td><?php echo htmlspecialchars($dr['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($dr['price'],2)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small">Suggested local SQL to inspect related products:</p>
                    <pre class="small">SELECT * FROM order_detail WHERE order_id = <?php echo (int)$orderId; ?>;
SELECT * FROM medicine WHERE medicine_id IN (<?php echo implode(',', array_map(fn($r) => (int)$r['medicine_id'], $detailRows)); ?>);</pre>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif (!$order): ?>
        <div class="alert alert-warning">Order not found.</div>
    <?php else: ?>
        <div class="card card-compact shadow-sm mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <h6>Customer</h6>
                        <p class="mb-1"><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6>Served By</h6>
                        <p class="mb-1"><?php echo htmlspecialchars($order['employee_name'] ?? 'Unknown'); ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6>Order Date</h6>
                        <p class="mb-1"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></p>
                    </div>
                    <div class="col-md-3">
                        <h6>Status</h6>
                        <p class="mb-1">
                            <?php if (!empty($refund)): ?>
                                <span class="badge bg-refund">Refunded</span>
                            <?php else: ?>
                                <?php 
                                $status = isset($order['status']) ? strtolower($order['status']) : '';
                                $statusBadge = 'secondary';
                                $statusLabel = $status;
                                
                                if ($status === 'completed') {
                                    $statusBadge = 'success';
                                    $statusLabel = 'Completed';
                                } elseif ($status === 'pending') {
                                    $statusBadge = 'warning';
                                    $statusLabel = 'Pending';
                                } elseif ($status === 'cancelled') {
                                    $statusBadge = 'danger';
                                    $statusLabel = 'Cancelled';
                                } elseif ($status === 'available') {
                                    $statusBadge = 'info';
                                    $statusLabel = 'Available';
                                }
                                ?>
                                <span class="badge bg-<?php echo $statusBadge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <h6>Payment Method</h6>
                            <p class="mb-1">
                                <?php
                                    $pm = $order['payment_method'] ?? null;
                                    $labels = [
                                        'cash' => 'Cash',
                                        'credit_card' => 'Credit Card',
                                        'bank_transfer' => 'Bank Transfer',
                                        'ewallet' => 'E-Wallet'
                                    ];
                                    if ($pm && isset($labels[$pm])) echo htmlspecialchars($labels[$pm]);
                                    elseif ($pm) echo htmlspecialchars($pm);
                                    else echo '-';
                                ?>
                            </p>
                        </div>
                        <div class="col-md-4"></div>
                    </div>
                

                
            </div>
        </div>

        <div class="card card-compact shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover table-bordered align-middle">
                        <thead class="table-light"><tr><th>Product</th><th class="text-end" style="width:120px;">Unit Price</th><th class="text-end" style="width:120px;">Qty</th><th class="text-end" style="width:120px;">Line Total</th></tr></thead>
                        <tbody>
                        <?php
                        $grandTotal = 0;
                        if (empty($items)) {
                            echo '<tr><td colspan="4" class="text-center text-danger">No products found for this order. Please check order details.</td></tr>';
                        }
                        foreach ($items as $item):
                            $unitPrice = floatval($item['unit_price'] ?? $item['price'] ?? 0);
                            $line = $unitPrice * intval($item['quantity']);
                            $grandTotal += $line;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['medicine_name'] ?? '(deleted)'); ?></td>
                                <td class="text-end">฿ <?php echo number_format($unitPrice, 2); ?></td>
                                <td class="text-end"><?php echo intval($item['quantity']); ?></td>
                                <td class="text-end">฿ <?php echo number_format($line, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th class="text-end">฿ <?php echo number_format($grandTotal, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Refund Confirmation Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Refund Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to refund this order? This will restore inventory and deduct customer points.</p>
        <p class="text-danger small">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmRefundBtn">Refund</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
    const orderId = <?php echo json_encode($orderId); ?>;
    const refundBtn = document.getElementById('refundOrderBtn');
    const confirmBtn = document.getElementById('confirmRefundBtn');
    const refundModalEl = document.getElementById('refundModal');
    const refundModal = refundModalEl ? new bootstrap.Modal(refundModalEl) : null;

    // show modal when refund button clicked
    if (refundBtn && refundModal) {
        refundBtn.addEventListener('click', () => refundModal.show());
    }

    // inline feedback element inside modal
    const modalBody = refundModalEl ? refundModalEl.querySelector('.modal-body') : null;
    function showModalMessage(html) {
        if (!modalBody) return alert(html.replace(/<[^>]+>/g, ''));
        let msgEl = modalBody.querySelector('.refund-message');
        if (!msgEl) {
            msgEl = document.createElement('div');
            msgEl.className = 'refund-message mt-2';
            modalBody.appendChild(msgEl);
        }
        msgEl.innerHTML = html;
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async () => {
            if (!orderId) return;
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Refunding...';
            showModalMessage('');

            try {
                console.log('refund: sending request for order', orderId);
                const res = await fetch('/api/refund_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId })
                });

                const text = await res.text();
                console.log('refund: raw response text', text);

                let data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }

                if (res.ok && data && data.success) {
                    console.log('refund: success', data);
                    if (refundModal) refundModal.hide();
                    window.location.reload();
                    return;
                }

                // show detailed error inline (prefer JSON error, else raw text)
                let errMsg = 'HTTP ' + res.status;
                if (data && data.error) errMsg = data.error;
                else if (text) errMsg = text;

                showModalMessage('<div class="alert alert-danger small mb-0">Failed: ' + errMsg.replace(/</g,'&lt;') + '</div>' + (data && data.fix_sql ? '<pre class="small mt-2">' + data.fix_sql + '</pre>' : ''));
                console.error('refund failed', res.status, data || text);
            } catch (err) {
                showModalMessage('<div class="alert alert-danger small mb-0">Error: ' + (err.message || err) + '</div>');
                console.error('refund exception', err);
            } finally {
                // ensure button is re-enabled unless we reloaded
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Refund';
            }
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
