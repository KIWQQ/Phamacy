<?php
$pageTitle = 'Customers';
$pageSub = '';
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="page-title mb-0">Customers</h3>
        <a href="/pages/new_customer.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Customer
        </a>
    </div>

    <?php
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    // combined sort+dir select (format: key:dir)
    $sortdir = isset($_GET['sortdir']) ? trim($_GET['sortdir']) : 'customer_name:asc';
    if (strpos($sortdir, ':') !== false) {
        list($sort, $dirPart) = explode(':', $sortdir, 2);
        $dir = (strtolower($dirPart) === 'asc') ? 'ASC' : 'DESC';
    } else {
        $sort = $sortdir;
        $dir = 'ASC';
    }
    $params = [];
    $where = '';
    if ($search !== '') {
        $where = "WHERE (c.customer_name LIKE :search_name OR c.phone LIKE :search_phone OR CAST(c.customer_id AS CHAR) LIKE :search_id)";
        $params['search_name'] = "%{$search}%";
        $params['search_phone'] = "%{$search}%";
        $params['search_id'] = "%{$search}%";
    }

        // map allowed sort keys to SQL expressions (use alias c)
        $allowed = [
            'customer_id' => 'c.customer_id',
            'customer_name' => 'c.customer_name',
            'points' => '(SELECT COALESCE(SUM(points),0) FROM point_transaction pt WHERE pt.customer_id = c.customer_id)'
        ];
        $orderBy = isset($allowed[$sort]) ? $allowed[$sort] : 'c.customer_name';

        // Use GROUP_CONCAT to load relational allergies (customer_allergy -> allergy)
        $sql = "SELECT c.customer_id, c.customer_name, c.phone, c.address, c.status, GROUP_CONCAT(m.medicine_name SEPARATOR ', ') AS allergy,
            (SELECT COALESCE(SUM(points),0) FROM point_transaction pt WHERE pt.customer_id = c.customer_id) AS points
            FROM customer c
            LEFT JOIN customer_allergy ca ON ca.customer_id = c.customer_id
            LEFT JOIN medicine m ON m.medicine_id = ca.medicine_id
            {$where}
            GROUP BY c.customer_id
            ORDER BY {$orderBy} {$dir}
            LIMIT 500";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    } catch (Exception $e) {
        $customers = [];
        $errorMsg = $e->getMessage();
    }
    ?>

    <!-- Search Form -->
    <div class="card card-compact shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-6">
                    <input type="search" name="search" class="form-control form-control-sm" 
                           placeholder="Search by Customer ID, Name, or Phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <select name="sortdir" class="form-select form-select-sm" onchange="this.form.submit();">
                        <option value="customer_name:asc" <?php if(($sort.':'.strtolower($dir))==='customer_name:asc') echo 'selected'; ?>>Name ↑</option>
                        <option value="customer_name:desc" <?php if(($sort.':'.strtolower($dir))==='customer_name:desc') echo 'selected'; ?>>Name ↓</option>
                        <option value="customer_id:asc" <?php if(($sort.':'.strtolower($dir))==='customer_id:asc') echo 'selected'; ?>>ID ↑</option>
                        <option value="customer_id:desc" <?php if(($sort.':'.strtolower($dir))==='customer_id:desc') echo 'selected'; ?>>ID ↓</option>
                        <option value="points:desc" <?php if(($sort.':'.strtolower($dir))==='points:desc') echo 'selected'; ?>>Points ↓</option>
                        <option value="points:asc" <?php if(($sort.':'.strtolower($dir))==='points:asc') echo 'selected'; ?>>Points ↑</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="col-12 col-md-2">
                    <a href="?" class="btn btn-secondary btn-sm w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-compact shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover table-bordered align-middle">
                    <thead class="table-light"><tr><th style="width:90px">ID</th><th>Name</th><th>Phone</th><th>Status</th><th>Allergy</th><th>Address</th><th style="width:120px" class="text-end">Points</th><th style="width:140px" class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo htmlspecialchars($c['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['phone']); ?></td>
                            <td>
                                <?php $st = isset($c['status']) ? $c['status'] : 'Active'; ?>
                                <?php if (strtolower($st) === 'inactive'): ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($c['allergy'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($c['address'] ?? ''); ?></td>
                            <td class="text-end fw-bold"><?php echo intval($c['points']); ?></td>
                                                        <td class="text-end">
                                                            <div class="d-flex gap-2 justify-content-end">
                                                                <a href="/pages/customer_points.php?customer_id=<?php echo $c['customer_id']; ?>" class="btn btn-outline-info btn-sm" title="View Points">
                                                                        <i class="bi bi-coin"></i>
                                                                </a>
                                                                <a href="/pages/new_customer.php?customer_id=<?php echo $c['customer_id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                                                        <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <?php $btnClass = (strtolower($st) === 'inactive') ? 'btn-outline-secondary' : 'btn-outline-danger'; ?>
                                                                <button type="button" class="btn <?php echo $btnClass; ?> btn-sm toggle-status-btn" data-id="<?php echo $c['customer_id']; ?>" data-status="<?php echo htmlspecialchars($st); ?>" title="<?php echo (strtolower($st) === 'inactive') ? 'Activate' : 'Deactivate'; ?>">
                                                                    <i class="<?php echo (strtolower($st) === 'inactive') ? 'bi bi-person-check' : 'bi bi-person-dash'; ?>"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No customers found</td></tr>
                    <?php endif; ?>
                    <?php if (!empty($errorMsg)): ?>
                        <tr><td colspan="7"><div class="alert alert-danger mb-0">Search error: <?php echo htmlspecialchars($errorMsg); ?></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Deactivate Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deactivate Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate <strong id="deleteName"></strong>?</p>
                <p class="text-muted small">This will deactivate the customer; data remains in the system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Deactivate</button>
            </div>
        </div>
    </div>
</div>

<script>
// Light submit listener: allow normal POST submission while logging for debug
document.addEventListener('submit', function(e) {
    let form = e.target;
    if (form && form.tagName !== 'FORM') form = form.closest('form');
    if (!form || !form.classList.contains('edit-form')) return;
    try {
        const nameEl = form.querySelector('input[name="customer_name"], input[name="employee_name"]');
        const idHidden = form.querySelector('input[type="hidden"][name$="_id"]');
        console.debug('Submitting form (normal POST)', { formDataset: form.dataset, id: idHidden ? idHidden.value : null, name: nameEl ? nameEl.value : null });
    } catch (err) {
        console.debug('Form submit debug error', err);
    }
    // allow browser to perform the normal POST
});

document.addEventListener('DOMContentLoaded', function() {
    console.debug('customer_list: DOMContentLoaded');

    // Handle toggle-status button clicks
    const toggleBtns = document.querySelectorAll('.toggle-status-btn');
    console.debug('customer_list: found toggle buttons', toggleBtns.length);
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = parseInt(this.dataset.id);
            const current = (this.dataset.status || 'Active').toString().toLowerCase();
            const newStatus = current === 'inactive' ? 'Active' : 'Inactive';
            const btnEl = this;
            btnEl.disabled = true;
            try {
                const res = await fetch('/api/customer.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: id, status: newStatus })
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => null);
                    alert('Failed to update status: ' + (body && body.error ? body.error : res.statusText));
                    return;
                }
                // Update badge cell (4th column)
                const tr = btnEl.closest('tr');
                const statusCell = tr ? tr.querySelector('td:nth-child(4)') : null;
                if (statusCell) {
                    statusCell.innerHTML = newStatus.toLowerCase() === 'inactive' ? '<span class="badge bg-secondary">Inactive</span>' : '<span class="badge bg-success">Active</span>';
                }
                // Update button appearance and data-status/title/icon
                btnEl.dataset.status = newStatus;
                if (newStatus.toLowerCase() === 'inactive') {
                    btnEl.className = 'btn btn-outline-secondary btn-sm toggle-status-btn';
                    btnEl.title = 'Activate';
                    btnEl.innerHTML = '<i class="bi bi-person-check"></i>';
                } else {
                    btnEl.className = 'btn btn-outline-danger btn-sm toggle-status-btn';
                    btnEl.title = 'Deactivate';
                    btnEl.innerHTML = '<i class="bi bi-person-dash"></i>';
                }
            } catch (err) {
                alert('Error: ' + err.message);
                console.error('Toggle status error:', err);
            } finally {
                btnEl.disabled = false;
            }
        });
    });

    // Ensure save button triggers submit
    document.querySelectorAll('.save-edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const f = this.closest('form');
            if (f) { if (typeof f.requestSubmit === 'function') f.requestSubmit(); else f.submit(); }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
