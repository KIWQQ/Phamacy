<?php
$pageTitle = 'New Medicine';
$pageSub = '';
ob_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/product_functions.php';

$pdo = getPDO();

$isEdit = false;
$medicineId = null;
$medicineName = '';
$typeId = '';
$price = '';
$stock = 0;

// If an ID is provided, load the existing medicine for editing
if (isset($_GET['medicine_id']) && is_numeric($_GET['medicine_id'])) {
    $medicineId = intval($_GET['medicine_id']);
    if ($medicineId > 0) {
        try {
            $existing = getProductById($pdo, $medicineId);
            if ($existing) {
                $isEdit = true;
                $medicineName = $existing['medicine_name'];
                $typeId = $existing['type_id'];
                $price = $existing['price'];
                $stock = $existing['stock'];
                $pageTitle = 'Edit Medicine';
            }
        } catch (Exception $e) {
            // Fall back to creation mode if we can't load the record
        }
    }
}

// Get medicine types for dropdown
try {
    $types = getProductsByType($pdo);
} catch (Exception $e) {
    $types = [];
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><?php echo $isEdit ? 'Edit Medicine' : 'Add New Medicine'; ?></h3>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="medicineForm">
                        <?php if ($isEdit): ?>
                            <input type="hidden" id="medicine_id" value="<?php echo htmlspecialchars($medicineId); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="medicine_name" class="form-label">Medicine Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="medicine_name" name="medicine_name" required placeholder="Enter medicine name" value="<?php echo htmlspecialchars($medicineName); ?>">
                            <div class="invalid-feedback">Medicine name is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="type_id" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_id" name="type_id" required>
                                <option value="">-- Select Type --</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['type_id']); ?>" <?php echo ($typeId == $type['type_id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Type is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Price (THB) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="price" name="price" required placeholder="Enter price" step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>">
                            <div class="invalid-feedback">Price is required and must be a valid number</div>
                        </div>

                        <div class="alert alert-info">
                            <strong>Usable stock: <?php echo number_format((int)$stock); ?></strong><br>
                            Stock cannot be edited here because every quantity must belong to a lot with an expiry date.
                            <?php if ($isEdit): ?><a href="/pages/new_stock.php?medicine_id=<?php echo (int)$medicineId; ?>" class="alert-link">Add a stock lot</a>.<?php endif; ?>
                        </div>

                        <div id="message" class="mb-3"></div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="/pages/Product_list.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="bi bi-save me-1"></i><?php echo $isEdit ? 'Update Medicine' : 'Save Medicine'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
const medicineId = <?php echo $isEdit ? intval($medicineId) : 'null'; ?>;

document.getElementById('medicineForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (isEdit ? 'Updating...' : 'Saving...');

    const data = {
        medicine_name: document.getElementById('medicine_name').value,
        type_id: parseInt(document.getElementById('type_id').value),
        price: parseFloat(document.getElementById('price').value)
    };

    if (isEdit && medicineId) {
        data.medicine_id = medicineId;
    }

    try {
        const response = await fetch(isEdit ? '/api/update_medicine.php' : '/api/save_medicine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        const messageDiv = document.getElementById('message');

        if (response.ok) {
            messageDiv.innerHTML = `<div class="alert alert-success">Medicine ${isEdit ? 'updated' : 'added'} successfully! Redirecting...</div>`;
            setTimeout(() => {
                window.location.href = isEdit
                    ? '/pages/Product_list.php'
                    : '/pages/new_stock.php?medicine_id=' + encodeURIComponent(result.medicine_id);
            }, 1500);
        } else {
            messageDiv.innerHTML = `<div class="alert alert-danger">${result.error || 'Failed to save medicine'}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Medicine' : 'Save Medicine');
        }
    } catch (error) {
        document.getElementById('message').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Medicine' : 'Save Medicine');
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
