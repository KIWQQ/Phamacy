<?php
$pageTitle = 'New Order';
$pageSub = '';
// compute JS file version early so echoes below won't warn
$oc_ver = @filemtime(__DIR__ . '/../assets/js/order_calculation.js') ?: time();
ob_start();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">New Order</h3>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <label class="form-label">Customer</label>
                    <input id="customer-search" class="form-control mb-2" placeholder="Search customer...">
                    <div id="customer-suggestions" class="list-group product-suggestion mb-2"></div>
                        <input type="hidden" id="customer-id">
                        <?php if (isset($_GET['order_id']) && ctype_digit($_GET['order_id'])): ?>
                            <input type="hidden" id="order-id" value="<?php echo htmlspecialchars($_GET['order_id']); ?>">
                        <?php endif; ?>
                    <div id="selected-customer" class="small text-muted"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <label class="form-label">Product</label>
                    <div class="input-group mb-2">
                        <input id="product-search" class="form-control" placeholder="Search product...">
                        <button type="button" id="add-row" class="btn btn-outline-primary">Add Row</button>
                    </div>
                    <div id="product-suggestions" class="list-group product-suggestion"></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <label class="form-label">Employee</label>
                    <?php
                    require_once __DIR__ . '/../functions/employee_functions.php';
                    require_once __DIR__ . '/../config/db.php';
                    $pdo = getPDO();
                    $emps = getAllEmployees($pdo);
                    ?>
                    <select id="employee-id" class="form-select form-select-sm">
                        <option value="">-- Select employee (optional) --</option>
                        <?php foreach ($emps as $e): ?>
                            <option value="<?php echo htmlspecialchars($e['employee_id']); ?>"><?php echo htmlspecialchars($e['employee_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-compact shadow-sm mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover table-bordered align-middle" id="items-table">
                    <thead class="table-light"><tr><th>Product</th><th style="width:120px;">Price</th><th style="width:120px;">Qty</th><th style="width:140px;">Line</th><th style="width:80px;"></th></tr></thead>
                    <tbody id="items-body"></tbody>
                </table>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <div id="order-msg"></div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2 d-flex gap-2 align-items-center">
                        <label class="small mb-0 me-2">Payment</label>
                        <select id="payment-method" class="form-select form-select-sm" style="width:200px;">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Transfer</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between"><div class="text-muted">Subtotal</div><div id="subtotal">0.00</div></div>
                    <div class="d-flex justify-content-between"><div class="text-muted">Total</div><div id="total" class="total-box">฿ 0.00</div></div>
                    <div class="mt-3 d-flex gap-2 justify-content-end">
                        <button id="clear-items" class="btn btn-outline-secondary">Clear</button>
                        <button id="save-order" class="btn btn-success"><i class="bi bi-save me-1"></i>Save Order</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Allergy confirmation modal (used by order_calculation.js) -->
<div class="modal fade" id="allergyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Allergy Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="allergyModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" id="allergyCancelBtn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="allergyConfirmBtn" class="btn btn-danger">Add anyway</button>
            </div>
        </div>
    </div>
</div>

<script defer src="/assets/js/order_calculation.js?v=<?php echo $oc_ver; ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', async function(){
    const orderIdEl = document.getElementById('order-id');
    if (!orderIdEl || !orderIdEl.value) return;
    const id = orderIdEl.value;
    try {
        const res = await fetch('/api/get_order.php?id=' + encodeURIComponent(id));
        if (!res.ok) throw new Error('Order not found');
        const data = await res.json();
        // set customer
        document.getElementById('customer-id').value = data.customer_id;
        document.getElementById('selected-customer').textContent = (data.customer_name || '') + ' (ID: ' + data.customer_id + ')';
        // set employee if present
        if (data.employee_id) {
            const emp = document.getElementById('employee-id'); if (emp) emp.value = data.employee_id;
        }
        // set payment method
        if (data.payment_method) {
            const pm = document.getElementById('payment-method'); if (pm) pm.value = data.payment_method;
        }
        // populate items
        const tbody = document.getElementById('items-body'); if (!tbody) return;
        tbody.innerHTML = '';
        for (const it of (data.items || [])){
            const tr = document.createElement('tr');
            tr.dataset.id = it.medicine_id;
            const priceVal = (parseFloat(it.unit_price || it.recorded_price || it.price || 0)).toFixed(2);
            tr.innerHTML = `
                <td><input class="form-control form-control-sm product-name" value="${(it.medicine_name||'').replace(/"/g,'&quot;')}" readonly></td>
                <td><div class="d-flex justify-content-end align-items-center" style="gap:8px;"><span class="price-display">${priceVal}</span><input type="hidden" class="price" value="${priceVal}"></div></td>
                <td><input type="number" min="1" value="${parseInt(it.quantity||1)}" class="form-control form-control-sm qty" style="width:100px;"></td>
                <td class="text-end line">${priceVal}</td>
                <td><button type="button" class="btn btn-sm btn-danger rm">×</button></td>
            `;
            tbody.appendChild(tr);
            tr.querySelector('.qty').addEventListener('input', function(){ const price=parseFloat(tr.querySelector('.price')?.value||0); tr.querySelector('.line').textContent = (price * (parseInt(this.value)||0)).toFixed(2); try{ if (typeof window.recalc === 'function') window.recalc(); if (typeof window.markAllergyRows === 'function') window.markAllergyRows(); }catch(e){} });
            tr.querySelector('.rm').addEventListener('click', ()=>{ tr.remove(); });
        }
        // trigger recalculation & allergy marking after a short delay to ensure order_calculation hooks are ready
        setTimeout(()=>{ try { if (typeof window.recalc === 'function') window.recalc(); } catch(e){ } if (typeof document.getElementById('items-body') !== 'undefined') { const ev = new Event('input'); document.querySelectorAll('#items-body .qty').forEach(q=>q.dispatchEvent(ev)); } }, 250);
        // change heading
        const h = document.querySelector('h3.mb-0'); if (h) h.textContent = 'Edit Order';
    } catch (err){ console.error('Failed to load order for edit', err); alert('Failed to load order: ' + err.message); }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
        </div>

