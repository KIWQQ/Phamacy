<?php
$pageTitle = 'Employees';
$pageSub = '';
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="page-title mb-0">Employees</h3>
        <a href="/Final_Project/pages/new_employee.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i>New Employee
        </a>
    </div>

    <?php
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    // combined sort+dir select (format: key:dir)
    $sortdir = isset($_GET['sortdir']) ? trim($_GET['sortdir']) : 'employee_name:asc';
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
        $where = "WHERE (employee_name LIKE :search_name OR position LIKE :search_position OR CAST(employee_id AS CHAR) LIKE :search_id)";
        $params['search_name'] = "%{$search}%";
        $params['search_position'] = "%{$search}%";
        $params['search_id'] = "%{$search}%";
    }

        // map allowed sort keys to SQL expressions
        $allowed = [
            'employee_id' => 'employee.employee_id',
            'employee_name' => 'employee.employee_name',
            'position' => 'employee.position',
            'salary' => 'employee.salary'
        ];
        $orderBy = isset($allowed[$sort]) ? $allowed[$sort] : 'employee.employee_name';

        $sql = "SELECT employee_id, employee_name, position, salary, phone, email, address, COALESCE(status, 'Active') AS status
            FROM employee
            {$where}
            ORDER BY {$orderBy} {$dir}
            LIMIT 500";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $employees = $stmt->fetchAll();
    } catch (Exception $e) {
        $employees = [];
        $errorMsg = $e->getMessage();
    }
    ?>

    <!-- Search Form -->
    <div class="card card-compact shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-12 col-md-6">
                    <input type="search" name="search" class="form-control form-control-sm" 
                           placeholder="Search by Employee ID, Name, or Position..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-6 col-md-2">
                    
                    <select name="sortdir" class="form-select form-select-sm" onchange="this.form.submit();">
                        <option value="employee_name:desc" <?php if(($sort.':'.strtolower($dir))==='employee_name:desc') echo 'selected'; ?>>Name ↓</option>
                        <option value="employee_name:asc" <?php if(($sort.':'.strtolower($dir))==='employee_name:asc') echo 'selected'; ?>>Name ↑</option>
                        <option value="employee_id:desc" <?php if(($sort.':'.strtolower($dir))==='employee_id:desc') echo 'selected'; ?>>ID ↓</option>
                        <option value="employee_id:asc" <?php if(($sort.':'.strtolower($dir))==='employee_id:asc') echo 'selected'; ?>>ID ↑</option>
                        <option value="position:desc" <?php if(($sort.':'.strtolower($dir))==='position:desc') echo 'selected'; ?>>Position ↓</option>
                        <option value="position:asc" <?php if(($sort.':'.strtolower($dir))==='position:asc') echo 'selected'; ?>>Position ↑</option>
                        
                        <option value="salary:desc" <?php if(($sort.':'.strtolower($dir))==='salary:desc') echo 'selected'; ?>>Salary ↓</option>
                        <option value="salary:asc" <?php if(($sort.':'.strtolower($dir))==='salary:asc') echo 'selected'; ?>>Salary ↑</option>
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
                    <thead class="table-light"><tr><th style="width:90px">ID</th><th>Name</th><th>Position</th><th>Status</th><th>Phone</th><th>Email</th><th>Address</th><th class="text-end">Salary</th><th style="width:100px">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($employees as $e): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo htmlspecialchars($e['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($e['employee_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($e['position']); ?></td>
                                                        <td>
                                                            <?php $est = isset($e['status']) ? $e['status'] : 'Active'; ?>
                                                            <?php if (strtolower($est) === 'inactive'): ?>
                                                                <span class="badge bg-secondary">Inactive</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Active</span>
                                                            <?php endif; ?>
                                                        </td>
                            <td><?php echo htmlspecialchars($e['phone'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($e['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($e['address'] ?? ''); ?></td>
                            <td class="text-end">฿ <?php echo number_format($e['salary'], 2); ?></td>
                            <td class="text-end">
                              <div class="d-flex gap-2 justify-content-end">
                                <a href="/Final_Project/pages/new_employee.php?employee_id=<?php echo $e['employee_id']; ?>" class="btn btn-outline-primary btn-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm delete-btn" data-id="<?php echo $e['employee_id']; ?>" data-name="<?php echo htmlspecialchars($e['employee_name']); ?>" title="Deactivate">
                                    <i class="bi bi-person-dash"></i>
                                </button>
                              </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No employees found</td></tr>
                    <?php endif; ?>
                    <?php if (!empty($errorMsg)): ?>
                        <tr><td colspan="8"><div class="alert alert-danger mb-0">Search error: <?php echo htmlspecialchars($errorMsg); ?></div></td></tr>
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
                <h5 class="modal-title">Deactivate Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate <strong id="deleteName"></strong>?</p>
                <p class="text-muted small">This will deactivate the employee; account remains in the system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Deactivate</button>
            </div>
        </div>
    </div>
</div>

<script>
let deleteId = null;

// Light submit listener: do not intercept. This only logs the form submit for debugging
document.addEventListener('submit', function(e) {
    let form = e.target;
    if (form && form.tagName !== 'FORM') form = form.closest('form');
    if (!form || !form.classList.contains('edit-form')) return;
    // allow normal POST submission; only log values for debug
    try {
        const nameEl = form.querySelector('input[name="employee_name"], input[name="customer_name"]');
        const idHidden = form.querySelector('input[type="hidden"][name$="_id"]');
        console.debug('Submitting form (normal POST)', { formDataset: form.dataset, id: idHidden ? idHidden.value : null, name: nameEl ? nameEl.value : null });
    } catch (err) {
        console.debug('Form submit debug error', err);
    }
    // do not call e.preventDefault() — allow browser to submit the form normally
});

document.addEventListener('DOMContentLoaded', function() {
    // delete handling
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            deleteId = this.dataset.id;
            const nameEl = document.getElementById('deleteName');
            if (nameEl) nameEl.textContent = this.dataset.name;
            const modalEl = document.getElementById('deleteModal');
            if (modalEl) new bootstrap.Modal(modalEl).show();
        });
    });

    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async function() {
            if (!deleteId) return;
            this.disabled = true; this.textContent = 'Deactivating...';
            try {
                const response = await fetch('/Final_Project/api/delete_employee.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ employee_id: parseInt(deleteId) })
                });
                if (response.ok) location.reload();
                else { const result = await response.json(); alert('Failed to deactivate employee: ' + (result.error || 'Unknown error')); this.disabled = false; this.textContent = 'Deactivate'; }
            } catch (err) { alert('Error: ' + err.message); this.disabled = false; this.textContent = 'Deactivate'; console.error('Deactivate error:', err); }
        });
    }

    // Save button triggers submit
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
