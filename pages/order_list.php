<?php
$pageTitle = 'Orders';
$pageSub = '';
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="page-title mb-0">Orders</h3>
        <a href="/pages/order_form.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Order
        </a>
    </div>

    <?php
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();

    $order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
    $customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';
    // unified query input (search bar)
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    // only populate order_id/customer from q if they were not provided explicitly
    if ($q !== '' && $order_id === '' && $customer === '') {
      if (ctype_digit($q)) {
        $order_id = $q;
      } else {
        $customer = $q;
      }
    }
    // support combined sort+dir in a single select (format: key:dir)
    $sortdir = isset($_GET['sortdir']) ? trim($_GET['sortdir']) : 'order_date:desc';
    if (strpos($sortdir, ':') !== false) {
      list($sort, $dirPart) = explode(':', $sortdir, 2);
      $dir = (strtolower($dirPart) === 'asc') ? 'ASC' : 'DESC';
    } else {
      $sort = $sortdir;
      $dir = 'DESC';
    }
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    try {
      $st = $pdo->prepare('SELECT DISTINCT status FROM orders ORDER BY status');
      $st->execute();
      $order_statuses = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $_) { $order_statuses = []; }
    // removed min/max total filters
    ?>

    <!-- Search Form -->
    <div class="card card-compact shadow-sm mb-3">
        <div class="card-body p-2">
            <form method="GET" class="row g-2 align-items-center w-100">
              <div class="col-12 col-md-8">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search orders by ID or customer name..." value="<?php echo htmlspecialchars($q); ?>">
              </div>
                <div class="col-6 col-md-2">
                  <select name="sortdir" class="form-select form-select-sm" onchange="this.form.submit();">
                  <option value="order_date:desc" <?php if(($sort.':'.strtolower($dir))==='order_date:desc') echo 'selected'; ?>>Date ↓</option>
                  <option value="order_date:asc" <?php if(($sort.':'.strtolower($dir))==='order_date:asc') echo 'selected'; ?>>Date ↑</option>
                  <option value="order_id:desc" <?php if(($sort.':'.strtolower($dir))==='order_id:desc') echo 'selected'; ?>>ID ↓</option>
                  <option value="order_id:asc" <?php if(($sort.':'.strtolower($dir))==='order_id:asc') echo 'selected'; ?>>ID ↑</option>
                  <option value="customer_name:desc" <?php if(($sort.':'.strtolower($dir))==='customer_name:desc') echo 'selected'; ?>>Customer ↓</option>
                  <option value="customer_name:asc" <?php if(($sort.':'.strtolower($dir))==='customer_name:asc') echo 'selected'; ?>>Customer ↑</option>
                  <option value="total:desc" <?php if(($sort.':'.strtolower($dir))==='total:desc') echo 'selected'; ?>>Total ↓</option>
                  <option value="total:asc" <?php if(($sort.':'.strtolower($dir))==='total:asc') echo 'selected'; ?>>Total ↑</option>
                  <option value="refunded:desc" <?php if(($sort.':'.strtolower($dir))==='refunded:desc') echo 'selected'; ?>>Status ↓</option>
                  <option value="refunded:asc" <?php if(($sort.':'.strtolower($dir))==='refunded:asc') echo 'selected'; ?>>Status ↑</option>
                </select>
              </div>

              <!-- grouped actions so everything fits in one row -->
              <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                  <i class="fas fa-search"></i> Search
                </button>
                <a href="?" class="btn btn-secondary btn-sm w-100">Clear</a>
              </div>
            </form>
        </div>
    </div>

    <?php
    $params = [];
    $where = '';
    if ($order_id !== '') {
      $where = "WHERE o.order_id = :order_id";
      $params['order_id'] = $order_id;
    }
    if ($customer !== '') {
      if ($where === '') {
        $where = "WHERE c.customer_name LIKE :customer_name";
      } else {
        $where .= " AND c.customer_name LIKE :customer_name";
      }
      $params['customer_name'] = "%{$customer}%";
    }
    if (!empty($status_filter)) {
      if ($where === '') {
        $where = "WHERE o.status = :status_filter";
      } else {
        $where .= " AND o.status = :status_filter";
      }
      $params['status_filter'] = $status_filter;
    }

    // Build ORDER BY safely: map allowed sort keys to SQL expressions
    $allowed = [
      'order_id' => 'o.order_id',
      'order_date' => 'o.order_date',
      'customer_name' => 'c.customer_name',
      'status' => 'o.status',
      'refunded' => 'refunded',
      'total' => 'total'
    ];
    $orderBy = isset($allowed[$sort]) ? $allowed[$sort] : 'o.order_date';
    // ensure status sorting uses a stable expression
    if ($sort === 'status') {
      $orderBy = "COALESCE(o.status, '')";
    }

    $sql = "SELECT o.order_id, o.order_date, c.customer_name, o.status,
        COALESCE(SUM(od.quantity * COALESCE(m.price, od.price)),0) AS total,
        COALESCE(SUM(od.quantity * od.price),0) AS recorded_total,
        IF(MAX(r.refund_id) IS NULL, 0, 1) AS refunded
        FROM orders o
        JOIN customer c ON c.customer_id = o.customer_id
        LEFT JOIN order_detail od ON od.order_id = o.order_id
        LEFT JOIN medicine m ON m.medicine_id = od.medicine_id
        LEFT JOIN refund r ON r.order_id = o.order_id
        {$where}
        GROUP BY o.order_id, o.order_date, c.customer_name, o.status
        ORDER BY {$orderBy} {$dir}
        LIMIT 200";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
    } catch (Exception $e) {
        $orders = [];
        $errorMsg = $e->getMessage();
    }
    ?>

    <div class="card card-compact shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover table-bordered align-middle">
                    <thead class="table-light"><tr><th style="width:90px">ID</th><th>Date</th><th>Customer</th><th class="text-center" style="width:120px">Status</th><th class="text-end">Total</th><th style="width:120px">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo htmlspecialchars($o['order_id']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($o['order_date']))); ?></td>
                            <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                            <td class="text-center">
                              <?php if (!empty($o['refunded'])): ?>
                                <span class="badge bg-refund">Refunded</span>
                              <?php else: ?>
                                <?php if (isset($o['status']) && $o['status'] === 'Discontinued'): ?>
                                  <span class="badge bg-danger">Discontinued</span>
                                <?php else: ?>
                                  <span class="badge bg-success">Completed</span>
                                <?php endif; ?>
                              <?php endif; ?>
                            </td>
                            <td class="text-end">฿ <?php echo number_format($o['total'],2); ?></td>
                            <td class="text-end">
                              <div class="d-flex gap-2 justify-content-end">
                                <a href="/pages/order_view.php?order_id=<?php echo $o['order_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <?php if (empty($o['refunded'])): ?>
                                  <a href="/pages/order_form.php?order_id=<?php echo $o['order_id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                  <button type="button" class="btn btn-sm btn-outline-warning refund-order-btn" data-id="<?php echo $o['order_id']; ?>">Refund</button>
                                <?php endif; ?>
                              </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($errorMsg)): ?>
                        <tr><td colspan="6"><div class="alert alert-danger mb-0">Search error: <?php echo htmlspecialchars($errorMsg); ?></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- (Cancel action removed; status is shown instead) -->

<!-- Refund Order Confirmation Modal -->
<div class="modal fade" id="refundOrderModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Refund Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to refund order <strong id="refundOrderId"></strong>?</p>
        <p class="text-danger small">This will restore stock and deduct customer points. This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmRefundOrderBtn">Refund</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Note: Cancel action removed; status badge is shown instead.

  // Refund button handlers (inline confirm)
  document.querySelectorAll('.refund-order-btn').forEach(btn => {
    btn.addEventListener('click', async function(){
      const id = this.dataset.id; if (!id) return; if (!confirm('Refund order #' + id + '? This will restore stock and deduct points.')) return;
      const btnEl = this; btnEl.disabled = true; btnEl.textContent = 'Refunding...';
        try {
        const res = await fetch('/api/refund_order.php', { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify({ order_id: parseInt(id) }) });
        const text = await res.text();
        let data = null;
        try { data = text ? JSON.parse(text) : null; } catch(e) { data = null; }
        console.log('refund_order response', res.status, data || text);
        if (res.ok && data && data.success) {
          const tr = btnEl.closest('tr');
            if (tr) {
            const statusCell = tr.querySelector('td:nth-child(4)');
            if (statusCell) {
              statusCell.innerHTML = '';
              const badge = document.createElement('span'); badge.className='badge bg-refund'; badge.textContent='Refunded';
              statusCell.appendChild(badge);
            }
            // remove refund button from actions cell
            btnEl.remove();
          }
        } else {
          const errMsg = data && data.error ? data.error : (text || 'Unknown error');
          alert('Failed to refund order: ' + errMsg);
          btnEl.disabled=false; btnEl.textContent='Refund';
        }
      } catch(err){ alert('Error: '+err.message); btnEl.disabled=false; btnEl.textContent='Refund'; console.error(err); }
    });
  });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';

