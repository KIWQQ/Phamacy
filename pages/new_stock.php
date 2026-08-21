<?php
$pageTitle = 'Add Stock by Lot';
$pageSub = 'Receive medicine with lot number, expiry date, supplier, and quantity.';
ob_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

$medicineId = isset($_GET['medicine_id']) && is_numeric($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : null;
$medicines = [];
$suppliers = [];
$schemaReady = true;
$loadError = null;

try {
    $medicines = $pdo->query("SELECT medicine_id, medicine_name FROM medicine WHERE status <> 'Discontinued' ORDER BY medicine_name")->fetchAll();
    $check = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicine_lot'");
    $schemaReady = ((int)$check->fetchColumn() > 0);
    if ($schemaReady) {
        $suppliers = $pdo->query("SELECT supplier_name FROM supplier ORDER BY supplier_name")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $loadError = $e->getMessage();
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Add Stock by Lot</h3>
            <div class="text-muted small">Every stock receipt must identify its lot and expiry date.</div>
        </div>
        <a href="/pages/stock.php" class="btn btn-outline-secondary btn-sm">Back to Stock</a>
    </div>

    <?php if (!$schemaReady): ?>
        <div class="alert alert-warning">Lot-stock database is not ready. Import <strong>database_migration_lot_stock.sql</strong> before using this page.</div>
    <?php endif; ?>
    <?php if ($loadError): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($loadError); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form id="stockForm" class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="medicine_id" class="form-label">Medicine <span class="text-danger">*</span></label>
                    <select id="medicine_id" class="form-select" required>
                        <option value="">-- Select Medicine --</option>
                        <?php foreach ($medicines as $m): ?>
                            <option value="<?php echo (int)$m['medicine_id']; ?>" <?php echo ($medicineId === (int)$m['medicine_id']) ? 'selected' : ''; ?>>
                                #<?php echo (int)$m['medicine_id']; ?> — <?php echo htmlspecialchars($m['medicine_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label for="lot_number" class="form-label">Lot Number <span class="text-danger">*</span></label>
                    <input type="text" id="lot_number" class="form-control" maxlength="100" required placeholder="e.g. LOT-PARA-2026-001">
                </div>

                <div class="col-12 col-md-6">
                    <label for="quantity" class="form-label">Quantity Received <span class="text-danger">*</span></label>
                    <input type="number" id="quantity" class="form-control" min="1" step="1" required>
                </div>

                <div class="col-12 col-md-6">
                    <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                    <input type="date" id="expiry_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label for="note" class="form-label">Note</label>
                    <input type="text" id="note" class="form-control" maxlength="500" placeholder="Optional note">
                </div>

                <div class="col-12 col-md-6">
                    <label for="supplier_name" class="form-label">Supplier <span class="text-danger">*</span></label>
                    <input type="text" id="supplier_name" class="form-control" list="supplierOptions" maxlength="150" required placeholder="Supplier name">
                    <datalist id="supplierOptions">
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo htmlspecialchars($supplier); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-12" id="message"></div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                    <button type="submit" class="btn btn-success" id="submitBtn" <?php echo !$schemaReady ? 'disabled' : ''; ?>>
                        <i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('stockForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const expiryDate = document.getElementById('expiry_date').value;
    const message = document.getElementById('message');

    const payload = {
        medicine_id: parseInt(document.getElementById('medicine_id').value, 10),
        lot_number: document.getElementById('lot_number').value.trim(),
        quantity: parseInt(document.getElementById('quantity').value, 10),
        supplier_name: document.getElementById('supplier_name').value.trim(),
        expiry_date: expiryDate,
        note: document.getElementById('note').value.trim()
    };

    const button = document.getElementById('submitBtn');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Receiving...';
    message.innerHTML = '';

    try {
        const response = await fetch('/api/save_stock.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Failed to receive stock');

        message.innerHTML = `<div class="alert alert-success mb-0">Lot <strong>${escapeHtml(payload.lot_number)}</strong> received successfully. Usable stock is now <strong>${data.new_stock}</strong>.</div>`;
        setTimeout(() => window.location.href = '/pages/stock.php', 1200);
    } catch (error) {
        message.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(error.message) + '</div>';
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock';
    }
});

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
