<?php
$pageTitle = 'New Employee';
$pageSub = '';

// Support editing an existing employee (via ?employee_id=...)
$editing = false;
$employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;
$employee = null;
if ($employeeId) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../functions/employee_functions.php';
    $pdo = getPDO();
    $employee = getEmployeeById($pdo, $employeeId);
    if ($employee) {
        $editing = true;
        $pageTitle = 'Edit Employee';
    }
}

ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><?= $editing ? 'Edit Employee' : 'Add New Employee'; ?></h3>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="employeeForm">
                        <?php if ($editing && isset($employee['employee_id'])): ?>
                            <input type="hidden" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="employee_name" class="form-label">Employee Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="employee_name" name="employee_name" required placeholder="Enter employee name" value="<?php echo htmlspecialchars($employee['employee_name'] ?? ''); ?>">
                            <div class="invalid-feedback">Employee name is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="">-- Select Position --</option>
                                <option value="Pharmacist" <?php echo (isset($employee['position']) && $employee['position'] === 'Pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                                <option value="Cashier" <?php echo (isset($employee['position']) && $employee['position'] === 'Cashier') ? 'selected' : ''; ?>>Cashier</option>
                                <option value="Assistant" <?php echo (isset($employee['position']) && $employee['position'] === 'Assistant') ? 'selected' : ''; ?>>Assistant</option>
                                <option value="Manager" <?php echo (isset($employee['position']) && $employee['position'] === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                <option value="Other" <?php echo (isset($employee['position']) && $employee['position'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">Position is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="salary" class="form-label">Salary (THB) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="salary" name="salary" required placeholder="Enter salary" step="0.01" min="0" value="<?php echo htmlspecialchars($employee['salary'] ?? ''); ?>">
                            <div class="invalid-feedback">Salary is required and must be a valid number</div>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select form-select-sm">
                                <option value="Active" <?php echo (isset($employee['status']) && $employee['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (isset($employee['status']) && $employee['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div id="message" class="mb-3"></div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="/Final_Project/pages/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="bi bi-save me-1"></i><?php echo $editing ? 'Update Employee' : 'Save Employee'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('employeeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const isEdit = <?php echo $editing ? 'true' : 'false'; ?>;
    const endpoint = isEdit ? '/Final_Project/api/update_employee.php' : '/Final_Project/api/save_employee.php';

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    const data = {
        employee_name: document.getElementById('employee_name').value,
        position: document.getElementById('position').value,
        salary: parseFloat(document.getElementById('salary').value)
    };

    // optional contact fields
    data.phone = document.getElementById('phone') ? document.getElementById('phone').value : '';
    data.email = document.getElementById('email') ? document.getElementById('email').value : '';
    data.address = document.getElementById('address') ? document.getElementById('address').value : '';
    // include status
    data.status = document.getElementById('status') ? document.getElementById('status').value : 'Active';

    if (isEdit) {
        const idEl = document.getElementById('employee_id');
        if (idEl && idEl.value) data.employee_id = parseInt(idEl.value, 10);
    }

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        const messageDiv = document.getElementById('message');

        if (response.ok) {
            const actionText = isEdit ? 'updated' : 'added';
            messageDiv.innerHTML = `<div class="alert alert-success">Employee ${actionText} successfully! Redirecting...</div>`;
            setTimeout(() => {
                window.location.href = '/Final_Project/pages/dashboard.php';
            }, 2000);
        } else {
            messageDiv.innerHTML = `<div class="alert alert-danger">${result.error || 'Failed to save employee'}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Employee' : 'Save Employee');
        }
    } catch (error) {
        document.getElementById('message').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Employee' : 'Save Employee');
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
