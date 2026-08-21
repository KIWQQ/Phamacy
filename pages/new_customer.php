<?php
$pageTitle = 'New Customer';
$pageSub = '';

// Support editing an existing customer (via ?customer_id=...)
$requireDb = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../functions/customer_functions.php';
$editing = false;
$customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;
$customer = null;
if ($customerId) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../functions/customer_functions.php';
    $pdo = getPDO();
    $customer = getCustomerById($pdo, $customerId);
    if ($customer) {
        $editing = true;
        $pageTitle = 'Edit Customer';
    }
}

ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><?= $editing ? 'Edit Customer' : 'Add New Customer'; ?></h3>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form id="customerForm">
                        <?php if ($editing && isset($customer['customer_id'])): ?>
                            <input type="hidden" id="customer_id" name="customer_id" value="<?php echo htmlspecialchars($customer['customer_id']); ?>">
                        <?php endif; ?>
                        <div class="mb-3" style="position:relative;">
                            <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required placeholder="Enter customer name" value="<?php echo htmlspecialchars($customer['customer_name'] ?? ''); ?>">
                            <div class="invalid-feedback">Customer name is required</div>
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" required placeholder="Enter phone number" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            <div class="invalid-feedback">Phone number is required</div>
                        </div>

                        <div class="mb-3" style="position:relative;">
                            <label for="allergy_input" class="form-label">Allergies</label>
                            <?php
                                // preload selected allergies (id + name) for editing
                                $pdo = getPDO();
                                $selectedAllergyRows = [];
                                if ($editing && isset($customer['customer_id'])) {
                                    $stmt = $pdo->prepare('SELECT m.medicine_id, m.medicine_name FROM customer_allergy ca JOIN medicine m ON m.medicine_id = ca.medicine_id WHERE ca.customer_id = ?');
                                    $stmt->execute([$customer['customer_id']]);
                                    $selectedAllergyRows = $stmt->fetchAll();
                                }
                            ?>
                            <input id="allergy_input" class="form-control mb-1" placeholder="Type to search medicines...">
                            <div id="allergy-suggestions" class="list-group product-suggestion" style="display:none; position:absolute; z-index:1050; width:100%;"></div>
                            <div id="allergy-selected" class="mt-2">
                                <?php foreach ($selectedAllergyRows as $sar): ?>
                                    <span class="badge bg-secondary me-1 mb-1 allergy-chip" data-id="<?php echo htmlspecialchars($sar['medicine_id']); ?>"><?php echo htmlspecialchars($sar['medicine_name']); ?> <button type="button" class="btn-close btn-close-white btn-sm ms-1 remove-allergy" aria-label="Remove"></button></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">Select medicines the customer is allergic to.</div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Enter address"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select form-select-sm">
                                <option value="Active" <?php echo (isset($customer['status']) && $customer['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (isset($customer['status']) && $customer['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div id="message" class="mb-3"></div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="/Final_Project/pages/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success" id="submitBtn">
                                <i class="bi bi-save me-1"></i><?php echo $editing ? 'Update Customer' : 'Save Customer'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('customerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const form = e.target;
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    const isEdit = <?php echo $editing ? 'true' : 'false'; ?>;
    const endpoint = isEdit ? '/Final_Project/api/update_customer.php' : '/Final_Project/api/save_customer.php';

    const data = {
        customer_name: document.getElementById('customer_name').value,
        phone: document.getElementById('phone').value
    };

    // optional fields
    // collect selected allergy medicine IDs (array)
    const selectedChips = document.querySelectorAll('#allergy-selected .allergy-chip');
    data.allergy = Array.from(selectedChips).map(ch => parseInt(ch.dataset.id, 10));
    data.address = document.getElementById('address') ? document.getElementById('address').value : '';
    // include status
    data.status = document.getElementById('status') ? document.getElementById('status').value : 'Active';

    if (isEdit) {
        const idEl = document.getElementById('customer_id');
        if (idEl && idEl.value) data.customer_id = parseInt(idEl.value, 10);
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
            messageDiv.innerHTML = `<div class="alert alert-success">Customer ${actionText} successfully! Redirecting...</div>`;
            setTimeout(() => {
                window.location.href = '/Final_Project/pages/dashboard.php';
            }, 2000);
        } else {
            messageDiv.innerHTML = `<div class="alert alert-danger">${result.error || 'Failed to save customer'}</div>`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Customer' : 'Save Customer');
        }
    } catch (error) {
        document.getElementById('message').innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-save me-1"></i>' + (isEdit ? 'Update Customer' : 'Save Customer');
    }
});
</script>

<script>
// Allergy autocomplete and selected-chips for New Customer
(function(){
    const input = document.getElementById('allergy_input');
    const box = document.getElementById('allergy-suggestions');
    const selectedContainer = document.getElementById('allergy-selected');
    if (!input || !box || !selectedContainer) return;
    let controller = null;
    let debounce = null;

    async function fetchSuggestions(q){
        if (!q || q.length < 1) return [];
        if (controller) controller.abort();
        controller = new AbortController();
        try {
            const res = await fetch('/Final_Project/api/search_product.php?q=' + encodeURIComponent(q), {signal: controller.signal});
            if (!res.ok) return [];
            const rows = await res.json();
            return Array.isArray(rows) ? rows : [];
        } catch (e) { return []; }
    }

    function renderList(items){
        box.innerHTML = '';
        if (!items.length) { box.style.display='none'; return; }
        items.slice(0,10).forEach(it=>{
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = it.medicine_name;
            btn.dataset.id = it.medicine_id;
            btn.dataset.name = it.medicine_name;
            btn.addEventListener('click', ()=>{ addSelected(it.medicine_id, it.medicine_name); box.style.display='none'; input.value=''; input.focus(); });
            box.appendChild(btn);
        });
        box.style.display = 'block';
    }

    function addSelected(id, name){
        // prevent duplicate
        if (selectedContainer.querySelector('[data-id="'+id+'"]')) return;
        const span = document.createElement('span');
        span.className = 'badge bg-secondary me-1 mb-1 allergy-chip';
        span.dataset.id = id;
        span.innerHTML = document.createTextNode(name).textContent + ' ';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-close btn-close-white btn-sm ms-1 remove-allergy';
        btn.setAttribute('aria-label','Remove');
        btn.addEventListener('click', ()=> span.remove());
        span.appendChild(btn);
        selectedContainer.appendChild(span);
    }

    input.addEventListener('input', ()=>{
        clearTimeout(debounce);
        debounce = setTimeout(async ()=>{
            const q = input.value.trim(); if (!q) { box.style.display='none'; return; }
            const rows = await fetchSuggestions(q);
            renderList(rows);
        }, 200);
    });

    input.addEventListener('keydown', (ev)=>{
        const items = box.querySelectorAll('.list-group-item');
        if (ev.key === 'ArrowDown' && items.length){ ev.preventDefault(); items[0].focus(); }
        if (ev.key === 'Enter'){ ev.preventDefault(); if (items.length) items[0].click(); }
        if (ev.key === 'Escape'){ box.style.display='none'; }
    });

    document.addEventListener('click', (e)=>{ if (!box.contains(e.target) && e.target !== input) box.style.display='none'; });

    // wire preloaded remove buttons
    document.querySelectorAll('#allergy-selected .remove-allergy').forEach(btn=> btn.addEventListener('click', (ev)=> ev.target.closest('.allergy-chip')?.remove()));
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
