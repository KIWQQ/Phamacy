// assets/js/order_calculation.js
// Rewritten: enforce readonly name/price, server allergy check, send only ids+qty
console.log('order_calculation.js loaded');
(function(){
    const el = id => document.getElementById(id);
    let custTimer = null, prodTimer = null;
    let currentCustomerAllergies = [];
    let allergyDetected = false;

    async function fetchJson(url){
        try { const r = await fetch(url); if (!r.ok) throw new Error(r.statusText); return await r.json(); } catch(e){ console.error('fetchJson', url, e); return null; }
    }

    async function searchCustomers(q){
        const url = `/api/search_customer.php?q=${encodeURIComponent(q)}`;
        try {
            console.debug('searchCustomers request', url);
            const r = await fetch(url);
            const txt = await r.text();
            try {
                const json = JSON.parse(txt);
                console.debug('searchCustomers response', json);
                return json || [];
            } catch (e) {
                console.error('searchCustomers: failed to parse JSON', txt, e);
                return [];
            }
        } catch (e) {
            console.error('searchCustomers fetch failed', e);
            return [];
        }
    }
    async function searchProducts(q){ return await fetchJson(`/api/search_product.php?q=${encodeURIComponent(q)}`) || []; }

    function formatCurrency(v){ return Number(v||0).toFixed(2); }

    function enforceReadonlyOnExistingRows(){
        const rows = document.querySelectorAll('#items-body tr');
        rows.forEach(r=>{
            const nameEl = r.querySelector('.product-name');
            if (nameEl) {
                nameEl.readOnly = true;
                nameEl.addEventListener('keydown', e=>e.preventDefault());
            }
            const priceInput = r.querySelector('.price');
            const priceDisplay = r.querySelector('.price-display');
            if (priceInput) {
                // hide editable input if present, ensure there's a visible price span
                try { priceInput.type = 'hidden'; priceInput.disabled = true; } catch(e){}
                if (!priceDisplay) {
                    const span = document.createElement('span');
                    span.className = 'price-display';
                    span.textContent = formatCurrency(parseFloat(priceInput.value)||0);
                    // try to find a cell to append to (second td)
                    const tds = r.querySelectorAll('td');
                    if (tds && tds[1]) tds[1].appendChild(span);
                }
            }
            // do not lock dataset.id here — keep ability to auto-resolve server-rendered rows
        });
    }

    function recalc(){
        let subtotal = 0;
        document.querySelectorAll('#items-body tr').forEach(r=>{
            const price = parseFloat(r.querySelector('.price')?.value) || 0;
            const qty = parseInt(r.querySelector('.qty')?.value) || 0;
            const line = price * qty;
            const lineEl = r.querySelector('.line'); if (lineEl) lineEl.textContent = formatCurrency(line);
            subtotal += line;
        });
        if (el('subtotal')) el('subtotal').textContent = formatCurrency(subtotal);
        if (el('total')) el('total').textContent = formatCurrency(subtotal);
    }

    async function confirmAllergy(text){
        // modal fallback to confirm
        const modalEl = document.getElementById('allergyModal');
        const textEl = document.getElementById('allergyModalText');
        const okBtn = document.getElementById('allergyConfirmBtn');
        const cancelBtn = document.getElementById('allergyCancelBtn');
        if (!modalEl || !textEl || !okBtn || !cancelBtn || typeof bootstrap === 'undefined') {
            return window.confirm(String(text));
        }
        return new Promise(resolve=>{
            textEl.textContent = text;
            const modal = new bootstrap.Modal(modalEl);
            function onOk(){ cleanup(); resolve(true); }
            function onCancel(){ cleanup(); resolve(false); }
            function cleanup(){ okBtn.removeEventListener('click', onOk); cancelBtn.removeEventListener('click', onCancel); modal.hide(); }
            okBtn.addEventListener('click', onOk); cancelBtn.addEventListener('click', onCancel);
            modal.show();
        });
    }

    function markAllergyRows(){
        allergyDetected = false;
        document.querySelectorAll('#items-body tr').forEach(r=>{
            const pname = (r.querySelector('.product-name')?.value || '').toLowerCase();
            const matched = currentCustomerAllergies.some(a=> a && (a === pname || pname.includes(a)));
            if (matched) { r.classList.add('table-danger'); allergyDetected = true; } else r.classList.remove('table-danger');
        });
        const msg = el('order-msg');
        if (allergyDetected) {
            if (msg) msg.innerHTML = `<div class="alert alert-warning p-2"><strong>Allergy warning:</strong> Customer may be allergic to selected items.</div>`;
        }
    }

    async function addItem(prod){
        if (!prod || !prod.id) { alert('Please choose a product from suggestions'); return; }
        const tbody = el('items-body'); if (!tbody) return;
        const tr = document.createElement('tr');
        tr.dataset.id = prod.id;
        const priceVal = formatCurrency(prod.price || 0);
        tr.innerHTML = `
            <td><input class="form-control form-control-sm product-name" value="${(prod.name||'').replace(/"/g,'&quot;')}" readonly></td>
            <td><div class="d-flex justify-content-end align-items-center" style="gap:8px;"><span class="price-display">${priceVal}</span><input type="hidden" class="price" value="${priceVal}"></div></td>
            <td><input type="number" min="1" value="1" class="form-control form-control-sm qty" style="width:100px;"></td>
            <td class="text-end line">${priceVal}</td>
            <td><button type="button" class="btn btn-sm btn-danger rm">×</button></td>
        `;
        tbody.appendChild(tr);
        // hook events
        tr.querySelector('.qty').addEventListener('input', recalc);
        tr.querySelector('.rm').addEventListener('click', ()=>{ tr.remove(); recalc(); markAllergyRows(); });
        // enforce readonly immediately
        enforceReadonlyOnExistingRows();
        recalc();
        markAllergyRows();
    }

    async function saveOrder(){
        const msg = el('order-msg'); if (msg) msg.textContent = '';
        const customer_id = parseInt(el('customer-id')?.value) || 0; if (!customer_id) { if (msg) msg.innerHTML = '<div class="alert alert-warning p-2">Select a customer first</div>'; return; }
        const rows = Array.from(document.querySelectorAll('#items-body tr'));
        if (rows.length === 0) { if (msg) msg.innerHTML = '<div class="alert alert-warning p-2">Add at least one product</div>'; return; }
        const items = [];
        for (const r of rows){
            let mid = parseInt(r.dataset.id) || null;
            const qty = parseInt(r.querySelector('.qty')?.value) || 0;
            // Try to auto-resolve rows that don't have an id (server-rendered or manual)
            if ((!mid || mid <= 0)){
                const name = (r.querySelector('.product-name')?.value || '').trim();
                if (name) {
                    const matches = await searchProducts(name);
                    if (matches && matches.length > 0) {
                        const match = matches.find(m => m.medicine_name.toLowerCase() === name.toLowerCase()) || matches[0];
                        mid = parseInt(match.medicine_id);
                        // assign dataset and update hidden price/display
                        try { r.dataset.id = mid; } catch(e){}
                        const ph = r.querySelector('.price'); if (ph) ph.value = formatCurrency(match.price || 0);
                        const pd = r.querySelector('.price-display'); if (pd) pd.textContent = formatCurrency(match.price || 0);
                        const lineEl = r.querySelector('.line'); if (lineEl) lineEl.textContent = formatCurrency((parseFloat(match.price)||0) * (qty || 1));
                    }
                }
            }
            if (!mid || mid <= 0) { if (msg) msg.innerHTML = `<div class="alert alert-warning p-2">Invalid product row detected</div>`; return; }
            if (qty <= 0) { if (msg) msg.innerHTML = `<div class="alert alert-warning p-2">Quantity must be at least 1</div>`; return; }
            items.push({ medicine_id: mid, quantity: qty });
        }
        // client-side allergy summary: show warning but do not block or ask on Save
        if (currentCustomerAllergies.length > 0) {
            const matched = [];
            rows.forEach((r, idx)=>{
                const name = (r.querySelector('.product-name')?.value || '').trim();
                const lname = name.toLowerCase();
                currentCustomerAllergies.forEach(a=>{ if (a && (a === lname || lname.includes(a))) matched.push({row: idx+1, name, allergy:a}); });
            });
            if (matched.length > 0){
                const htmlLines = matched.map(m=>`Row ${m.row}: ${m.name} (allergy: ${m.allergy})`).map(l=>`<div>${l}</div>`).join('');
                if (msg) msg.innerHTML = `<div class="alert alert-warning p-2"><strong>Allergy notice:</strong>${htmlLines}</div>`;
                // do NOT prompt the user on save; proceed to submit
            }
        }

        const payload = { customer_id, items };
        const pm = el('payment-method'); if (pm) payload.payment_method = pm.value;
        const emp = el('employee-id'); if (emp && emp.value) payload.employee_id = parseInt(emp.value) || null;
        try{
            // If order-id present, call update endpoint instead of create
            const orderIdEl = el('order-id');
            const endpoint = (orderIdEl && orderIdEl.value) ? '/api/update_order.php' : '/api/save_order.php';
            if (orderIdEl && orderIdEl.value) payload.order_id = parseInt(orderIdEl.value);
            const res = await fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
            const text = await res.text();
            let data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }

            if (res.ok && data && data.success) {
                const url = `/pages/order_view.php?order_id=${encodeURIComponent(data.order_id)}`;
                if (data.warnings && data.warnings.length) {
                    if (msg) msg.innerHTML = `<div class="alert alert-warning p-2"><strong>Warnings:</strong>${data.warnings.map(w=>`<div>${w}</div>`).join('')}</div>`;
                    setTimeout(()=> window.location.href = url, 2000);
                } else {
                    window.location.href = url;
                }
            } else {
                // Prefer structured error, else show raw text (may contain HTML/PHP error)
                let errMsg = 'Save failed';
                if (data && data.error) errMsg = data.error;
                else if (text) errMsg = text;
                if (msg) msg.innerHTML = `<div class="alert alert-danger p-2">${errMsg.replace(/</g,'&lt;')}</div>`;
            }
        } catch(e){ if (msg) msg.innerHTML = `<div class="alert alert-danger p-2">${e.message}</div>`; }
    }

    function wireSuggestionLists(){
        // customer suggestions — trigger only on phone digits and show names
        const cinput = el('customer-search'); const csug = el('customer-suggestions');
        if (cinput){
            cinput.addEventListener('input', ()=>{
                clearTimeout(custTimer); custTimer = setTimeout(async ()=>{
                    const raw = cinput.value || '';
                    const digits = raw.replace(/\D+/g,'');
                    if (!digits || digits.length < 3) { if (csug) { csug.innerHTML=''; csug.style.display='none'; } return; }
                    const items = await searchCustomers(digits);
                    if (!csug) return;
                    // filter results by phone digits
                    const filtered = (items || []).filter(i=>{ const p = (i.phone||'').replace(/\D+/g,''); return p.includes(digits); });
                    csug.innerHTML = filtered.length ? filtered.map(i=>`<button type="button" class="list-group-item list-group-item-action" data-id="${i.customer_id}" data-name="${i.customer_name}" data-phone="${i.phone||''}">${i.customer_name} (${i.phone||''})</button>`).join('') : '<div class="list-group-item">No customers found</div>';
                    csug.style.display = filtered.length ? 'block' : 'none';
                    csug.querySelectorAll('button').forEach(b=> b.addEventListener('click', async ()=>{
                        el('customer-id').value = b.dataset.id;
                        el('selected-customer').textContent = b.dataset.name + ' (' + b.getAttribute('data-phone') + ')';
                        csug.innerHTML = '';
                        cinput.value = '';
                        const c = await fetchJson(`/api/get_customer.php?id=${encodeURIComponent(b.dataset.id)}`) || {};
                        currentCustomerAllergies = (c.allergy||'').split(',').map(s=>s.trim().toLowerCase()).filter(Boolean);
                        if (currentCustomerAllergies.length) el('selected-customer').textContent += ' — Allergy: ' + (c.allergy||'');
                        markAllergyRows();
                    }));
                }, 250);
            });

            cinput.addEventListener('keydown', (ev)=>{ if (ev.key === 'Escape' && csug) csug.innerHTML = ''; });
            document.addEventListener('click', (e)=>{ if (csug && !csug.contains(e.target) && e.target !== cinput) csug.innerHTML = ''; });
        }

        // product suggestions
        const pinput = el('product-search'); const psug = el('product-suggestions');
        if (pinput){
            pinput.addEventListener('input', ()=>{
                clearTimeout(prodTimer); prodTimer = setTimeout(async ()=>{
                    const q = pinput.value.trim(); if (!q) { if (psug) psug.innerHTML=''; return; }
                    const items = await searchProducts(q);
                    if (!psug) return;
                    psug.innerHTML = items.map(i=>`<button type="button" class="list-group-item list-group-item-action" data-id="${i.medicine_id}" data-name="${i.medicine_name}" data-price="${i.price}">${i.medicine_name} — ฿ ${formatCurrency(i.price)}</button>`).join('');
                    psug.querySelectorAll('button').forEach(b=> b.addEventListener('click', async ()=>{
                        const prod = { id: b.dataset.id, name: b.dataset.name, price: parseFloat(b.dataset.price||0) };
                        // server allergy check if customer selected
                        const cid = parseInt(el('customer-id')?.value) || 0;
                        if (cid > 0) {
                            try {
                                const chk = await fetchJson(`/api/check_customer_allergy.php?customer_id=${encodeURIComponent(cid)}&medicine_id=${encodeURIComponent(prod.id)}`);
                                if (chk && chk.allergic) {
                                    const ok = await confirmAllergy(`Customer is allergic to "${prod.name}". Add anyway?`);
                                    if (!ok) { psug.innerHTML=''; pinput.value=''; return; }
                                }
                            } catch(e){ console.error('allergy check fail', e); }
                        }
                        // client fallback
                        if (currentCustomerAllergies.length){
                            const lower = (prod.name||'').toLowerCase();
                            const matches = currentCustomerAllergies.filter(a=> a && (a === lower || lower.includes(a)) );
                            if (matches.length){ const ok = await confirmAllergy(`Customer may be allergic to "${prod.name}". Add anyway?`); if (!ok) { psug.innerHTML=''; pinput.value=''; return; } }
                        }
                        await addItem(prod);
                        psug.innerHTML=''; pinput.value='';
                    }));
                }, 250);
            });
        }
    }

    function attachButtons(){
        el('add-row')?.addEventListener('click', ()=> el('product-search')?.focus());
        el('save-order')?.addEventListener('click', saveOrder);
        el('clear-items')?.addEventListener('click', ()=>{ if (el('items-body')) { el('items-body').innerHTML=''; recalc(); markAllergyRows(); } });
    }

    function init(){
        console.log('order_calculation.init');
        wireSuggestionLists();
        attachButtons();
        enforceReadonlyOnExistingRows();
        recalc();
        markAllergyRows();
    }

    // expose helpers for embedding/edit flows
    window.recalc = recalc;
    window.markAllergyRows = markAllergyRows;
    window.addItemToOrder = addItem;

    window.addEventListener('DOMContentLoaded', init);
})();
