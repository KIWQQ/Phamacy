/* ============================================ PHARMACY POS - APPLICATION LOGIC ============================================ */

// Mock Data
const mockData = {
    customers: [
        { id: 1, name: 'นาย สมชาย ใจดี', phone: '0812345678', address: 'กรุงเทพฯ', allergies: [1, 2], points: 150 },
        { id: 2, name: 'นาง สมหญิง รักษา', phone: '0823456789', address: 'ชลบุรี', allergies: [3, 4], points: 200 },
        { id: 3, name: 'เด็กชาย ทศพล สวัสดี', phone: '0834567890', address: 'สมุทรปราการ', allergies: [5], points: 50 },
        { id: 4, name: 'นาย กรณ์ ธรรมชาติ', phone: '0845678901', address: 'ธนบุรี', allergies: [], points: 300 },
    ],
    medicines: [
        { id: 1, name: 'ยาแก้แพ้ Cetirizine', type: 1, price: 45, stock: 5, status: 'Available' },
        { id: 2, name: 'เพนิซิลิน 500mg', type: 2, price: 120, stock: 3, status: 'Available' },
        { id: 3, name: 'เอมพิซิลิน 250mg', type: 2, price: 85, stock: 12, status: 'Available' },
        { id: 4, name: 'ยาซัลฟา Sulfamethoxazole', type: 2, price: 60, stock: 25, status: 'Available' },
        { id: 5, name: 'ไซโลฟ๑กซิน Ciprofloxacin', type: 2, price: 150, stock: 8, status: 'Available' },
        { id: 6, name: 'ยาแก้ปวด Ibuprofen', type: 3, price: 35, stock: 40, status: 'Available' },
        { id: 7, name: 'ยาลดไข้ Paracetamol', type: 3, price: 25, stock: 15, status: 'Available' },
        { id: 8, name: 'วิตามิน C 1000mg', type: 4, price: 55, stock: 50, status: 'Available' },
    ],
    medicineTypes: [
        { id: 1, name: 'Allergy Medicine' },
        { id: 2, name: 'Antibiotic' },
        { id: 3, name: 'Pain Relief' },
        { id: 4, name: 'Vitamin' },
    ],
    orders: [
        { id: 101, customerId: 1, customerName: 'นาย สมชาย ใจดี', items: [{ medicineId: 1, quantity: 2, price: 45 }], total: 90, payment: 'cash', date: '2024-01-15' },
        { id: 102, customerId: 4, customerName: 'นาย กรณ์ ธรรมชาติ', items: [{ medicineId: 8, quantity: 1, price: 55 }], total: 55, payment: 'bank_transfer', date: '2024-01-14' },
    ],
    employees: [
        { id: 1, name: 'นางสาว พชร พลการ', position: 'Pharmacist', phone: '0861234567', email: 'phachom@mail.com', salary: 25000, address: 'บ้านแสน', status: 'Active' },
        { id: 2, name: 'นาย สิทธิเกียรติ พิทักษ์', position: 'Assistant', phone: '0872345678', email: 'sitthigiat@mail.com', salary: 15000, address: 'เพชรบุรี', status: 'Active' },
    ],
};

// Application State & LocalStorage
const app = {
    currentPage: 'dashboard',
    selectedCustomer: null,
    editingId: null,
    
    data: {
        customers: [],
        medicines: [],
        orders: [],
        employees: [],
        stockLog: [],
        refunds: [],
    },

    init() {
        this.loadFromStorage();
        this.setupEventListeners();
        this.populateSelects();
        this.renderPage('dashboard');
        this.attachThemeToggle();
    },

    loadFromStorage() {
        const stored = localStorage.getItem('pharmacyPosData');
        if (stored) {
            this.data = JSON.parse(stored);
        } else {
            this.data = JSON.parse(JSON.stringify(mockData));
            this.saveToStorage();
        }
    },

    saveToStorage() {
        localStorage.setItem('pharmacyPosData', JSON.stringify(this.data));
    },

    setupEventListeners() {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                this.goToPage(page);
            });
        });

        document.getElementById('sidebarToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Forms
        document.getElementById('medicineForm')?.addEventListener('submit', (e) => this.saveMedicine(e));
        document.getElementById('customerForm')?.addEventListener('submit', (e) => this.saveCustomer(e));
        document.getElementById('employeeForm')?.addEventListener('submit', (e) => this.saveEmployee(e));

        // Search inputs
        document.getElementById('orderCustomerSearch')?.addEventListener('input', (e) => this.searchCustomers(e.target.value));
        document.getElementById('orderMedicineSearch')?.addEventListener('input', (e) => this.searchMedicines(e.target.value));
    },

    populateSelects() {
        const empSelect = document.getElementById('orderEmployee');
        if (empSelect) {
            empSelect.innerHTML = '<option value="">-- Select Employee --</option>';
            this.data.employees.forEach(emp => {
                empSelect.innerHTML += `<option value="${emp.id}">${emp.name}</option>`;
            });
        }

        const typeSelect = document.getElementById('medType');
        if (typeSelect) {
            typeSelect.innerHTML = '';
            this.data.medicineTypes.forEach(type => {
                typeSelect.innerHTML += `<option value="${type.id}">${type.name}</option>`;
            });
        }

        const allergyBox = document.getElementById('allergyCheckboxes');
        if (allergyBox) {
            allergyBox.innerHTML = '';
            this.data.medicines.forEach(med => {
                allergyBox.innerHTML += `<label><input type="checkbox" value="${med.id}" name="allergy"> ${med.name}</label>`;
            });
        }
    },

    // Page Navigation
    goToPage(page) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

        const pageEl = document.getElementById(page);
        if (pageEl) {
            pageEl.classList.add('active');
        }

        const navLink = document.querySelector(`[data-page="${page}"]`);
        if (navLink) navLink.classList.add('active');

        const titles = {
            dashboard: 'Dashboard',
            orders: 'Orders',
            neworder: 'New Order',
            refunds: 'Refund History',
            medicines: 'Medicines',
            newmedicine: 'Add Medicine',
            stock: 'Stock Levels',
            stocklog: 'Stock Log',
            customers: 'Customers',
            newcustomer: 'Add Customer',
            customerpoints: 'Customer Points',
            employees: 'Employees',
            newemployee: 'Add Employee',
        };

        const subs = {
            dashboard: 'Overview of your pharmacy operations',
            orders: 'Manage orders',
            neworder: 'Create a new order',
            medicines: 'Manage medicines inventory',
            stock: 'Check stock levels',
            customers: 'Manage customers',
            employees: 'Manage employees',
        };

        document.getElementById('pageTitle').textContent = titles[page] || '';
        document.getElementById('pageSub').textContent = subs[page] || '';

        this.currentPage = page;
        this.editingId = null;
        this.selectedCustomer = null;

        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('active');
        }

        this.renderPage(page);
    },

    // Render Page Content
    renderPage(page) {
        if (page === 'dashboard') this.renderDashboard();
        else if (page === 'orders') this.renderOrders();
        else if (page === 'neworder') this.renderNewOrder();
        else if (page === 'refunds') this.renderRefunds();
        else if (page === 'medicines') this.renderMedicines();
        else if (page === 'newmedicine') this.renderMedicineForm();
        else if (page === 'stock') this.renderStock();
        else if (page === 'stocklog') this.renderStockLog();
        else if (page === 'customers') this.renderCustomers();
        else if (page === 'newcustomer') this.renderCustomerForm();
        else if (page === 'customerpoints') this.renderCustomerPoints();
        else if (page === 'employees') this.renderEmployees();
        else if (page === 'newemployee') this.renderEmployeeForm();
    },

    // DASHBOARD
    renderDashboard() {
        const totalSales = this.data.orders.reduce((sum, o) => sum + o.total, 0);
        const lowStockCount = this.data.medicines.filter(m => m.stock <= 10).length;

        document.getElementById('dashTotalSales').textContent = `฿${totalSales.toFixed(2)}`;
        document.getElementById('dashTotalOrders').textContent = this.data.orders.length;
        document.getElementById('dashLowStock').textContent = lowStockCount;
        document.getElementById('dashTotalCustomers').textContent = this.data.customers.length;

        // Recent Orders
        const recentOrders = document.querySelector('#dashRecentOrders tbody');
        recentOrders.innerHTML = this.data.orders.slice(-5).reverse().map(o => `
            <tr>
                <td>#${o.id}</td>
                <td>${o.customerName}</td>
                <td>฿${o.total.toFixed(2)}</td>
                <td>${o.date}</td>
            </tr>
        `).join('');

        // Low Stock
        const lowStockTable = document.querySelector('#dashLowStockTable tbody');
        lowStockTable.innerHTML = this.data.medicines
            .filter(m => m.stock <= 10)
            .sort((a, b) => a.stock - b.stock)
            .map(m => `
                <tr>
                    <td>${m.name}</td>
                    <td style="color:#ef4444; font-weight:700;">${m.stock}</td>
                    <td>10</td>
                </tr>
            `).join('');
    },

    // ORDERS
    renderOrders() {
        const tbody = document.querySelector('#ordersTable tbody');
        tbody.innerHTML = this.data.orders.map(o => `
            <tr>
                <td>#${o.id}</td>
                <td>${o.customerName}</td>
                <td>${o.items.length}</td>
                <td>฿${o.total.toFixed(2)}</td>
                <td>${o.payment === 'cash' ? 'Cash' : 'Transfer'}</td>
                <td>${o.date}</td>
                <td><button class="btn btn-small" onclick="app.viewOrder(${o.id})">View</button></td>
            </tr>
        `).join('');
    },

    viewOrder(orderId) {
        this.showToast(`Order #${orderId} - Feature coming soon`);
    },

    // NEW ORDER
    renderNewOrder() {
        document.getElementById('orderItems').innerHTML = '';
        document.getElementById('orderSubtotal').textContent = '฿0.00';
        document.getElementById('orderTotal').textContent = '฿0.00';
        this.selectedCustomer = null;
        this.updateOrderUI();
    },

    searchCustomers(query) {
        const suggestions = document.getElementById('customerSuggestions');
        if (!query) {
            suggestions.classList.remove('active');
            return;
        }

        const results = this.data.customers.filter(c =>
            c.name.toLowerCase().includes(query.toLowerCase()) || c.phone.includes(query)
        );

        suggestions.innerHTML = results.map(c => `
            <div class="suggestion-item" onclick="app.selectCustomer(${c.id})">
                <strong>${c.name}</strong><br>
                <small>${c.phone}</small>
            </div>
        `).join('');

        suggestions.classList.toggle('active', results.length > 0);
    },

    selectCustomer(customerId) {
        this.selectedCustomer = this.data.customers.find(c => c.id === customerId);
        document.getElementById('orderCustomerSearch').value = this.selectedCustomer.name;
        document.getElementById('customerSuggestions').classList.remove('active');
        document.getElementById('selectedCustomerInfo').style.display = 'block';
        document.getElementById('selectedCustomerName').textContent = `${this.selectedCustomer.name} (${this.selectedCustomer.phone})`;

        this.updateAllergyWarning();
    },

    updateAllergyWarning() {
        const allergyBox = document.getElementById('allergyWarningBox');
        const allergyList = document.getElementById('customerAllergyList');

        if (this.selectedCustomer && this.selectedCustomer.allergies.length > 0) {
            const allergyNames = this.selectedCustomer.allergies
                .map(id => this.data.medicines.find(m => m.id === id)?.name || '')
                .filter(n => n);
            
            allergyBox.style.display = 'block';
            allergyList.innerHTML = allergyNames.map(name => `<div>🔴 ${name}</div>`).join('');
        } else {
            allergyBox.style.display = 'none';
        }

        this.checkOrderAllergies();
    },

    searchMedicines(query) {
        const suggestions = document.getElementById('medicineSuggestions');
        if (!query) {
            suggestions.classList.remove('active');
            return;
        }

        const results = this.data.medicines.filter(m =>
            m.name.toLowerCase().includes(query.toLowerCase())
        );

        suggestions.innerHTML = results.map(m => `
            <div class="suggestion-item" onclick="app.addToOrder(${m.id})">
                <strong>${m.name}</strong><br>
                <small>฿${m.price} | Stock: ${m.stock}</small>
            </div>
        `).join('');

        suggestions.classList.toggle('active', results.length > 0);
    },

    addToOrder(medicineId) {
        const medicine = this.data.medicines.find(m => m.id === medicineId);
        if (!medicine) return;

        document.getElementById('orderMedicineSearch').value = '';
        document.getElementById('medicineSuggestions').classList.remove('active');

        const itemsBody = document.getElementById('orderItems');
        const existingRow = itemsBody.querySelector(`[data-mid="${medicineId}"]`);

        if (existingRow) {
            const qtyInput = existingRow.querySelector('.qty-input');
            qtyInput.value = parseInt(qtyInput.value) + 1;
        } else {
            const row = document.createElement('tr');
            row.setAttribute('data-mid', medicineId);
            row.innerHTML = `
                <td>${medicine.name}</td>
                <td>฿${medicine.price.toFixed(2)}</td>
                <td><input type="number" class="qty-input" value="1" min="1" onchange="app.updateOrderUI()" style="width:60px; padding:6px;"></td>
                <td>฿${medicine.price.toFixed(2)}</td>
                <td><button type="button" class="btn btn-small" onclick="this.parentElement.parentElement.remove(); app.updateOrderUI();">×</button></td>
            `;
            itemsBody.appendChild(row);
        }

        this.updateOrderUI();
    },

    updateOrderUI() {
        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        let subtotal = 0;

        rows.forEach(row => {
            const medicineId = parseInt(row.getAttribute('data-mid'));
            const medicine = this.data.medicines.find(m => m.id === medicineId);
            const qty = parseInt(row.querySelector('.qty-input').value) || 1;
            const lineTotal = medicine.price * qty;
            
            row.querySelector('td:nth-child(4)').textContent = `฿${lineTotal.toFixed(2)}`;
            subtotal += lineTotal;
        });

        document.getElementById('orderSubtotal').textContent = `฿${subtotal.toFixed(2)}`;
        document.getElementById('orderTotal').textContent = `฿${subtotal.toFixed(2)}`;

        this.checkOrderAllergies();
        this.checkOrderStock();
    },

    checkOrderAllergies() {
        const allergyAlert = document.getElementById('orderAllergyAlert');
        const allergyList = document.getElementById('orderAllergyList');

        if (!this.selectedCustomer || this.selectedCustomer.allergies.length === 0) {
            allergyAlert.style.display = 'none';
            this.enableSaveButton();
            return;
        }

        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        const allergicItems = [];

        rows.forEach(row => {
            const medicineId = parseInt(row.getAttribute('data-mid'));
            if (this.selectedCustomer.allergies.includes(medicineId)) {
                const medicineName = this.data.medicines.find(m => m.id === medicineId).name;
                allergicItems.push(medicineName);
            }
        });

        if (allergicItems.length > 0) {
            allergyAlert.style.display = 'block';
            allergyList.innerHTML = allergicItems.map(name => `<div>🔴 ${name}</div>`).join('');
            this.disableSaveButton('Remove allergenic items first');
        } else {
            allergyAlert.style.display = 'none';
            this.enableSaveButton();
        }
    },

    clearAllergyItems() {
        if (!this.selectedCustomer) return;

        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        rows.forEach(row => {
            const medicineId = parseInt(row.getAttribute('data-mid'));
            if (this.selectedCustomer.allergies.includes(medicineId)) {
                row.remove();
            }
        });

        this.updateOrderUI();
        this.showToast('Allergenic items removed');
    },

    checkOrderStock() {
        const stockAlert = document.getElementById('orderStockAlert');
        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        const issues = [];

        rows.forEach(row => {
            const medicineId = parseInt(row.getAttribute('data-mid'));
            const medicine = this.data.medicines.find(m => m.id === medicineId);
            const qty = parseInt(row.querySelector('.qty-input').value) || 1;

            if (qty > medicine.stock) {
                issues.push(`${medicine.name}: Need ${qty}, Have ${medicine.stock}`);
            }
        });

        if (issues.length > 0) {
            stockAlert.style.display = 'block';
            document.getElementById('orderStockList').innerHTML = issues.map(i => `<div>🟡 ${i}</div>`).join('');
            document.getElementById('stockWhy').textContent = 'Insufficient stock in inventory';
            document.getElementById('stockNext').textContent = 'Reduce quantity, select different medicine, or remove items';
        } else {
            stockAlert.style.display = 'none';
        }
    },

    disableSaveButton(reason) {
        const btn = document.getElementById('orderSaveBtn');
        btn.disabled = true;
        btn.title = reason;
    },

    enableSaveButton() {
        const btn = document.getElementById('orderSaveBtn');
        btn.disabled = false;
        btn.title = '';
    },

    confirmClearOrder() {
        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        if (rows.length === 0) return;

        this.showConfirm('Clear Order?', 'Are you sure you want to clear all items?', () => {
            document.getElementById('orderItems').innerHTML = '';
            document.getElementById('orderSubtotal').textContent = '฿0.00';
            document.getElementById('orderTotal').textContent = '฿0.00';
            this.updateOrderUI();
            this.showToast('Order cleared');
        });
    },

    saveOrder() {
        const msg = document.getElementById('orderMessage');
        msg.innerHTML = '';

        if (!this.selectedCustomer) {
            msg.innerHTML = '<div class="alert alert-danger">Please select a customer</div>';
            return;
        }

        const rows = document.querySelectorAll('#orderItems tr[data-mid]');
        if (rows.length === 0) {
            msg.innerHTML = '<div class="alert alert-danger">Please add medicines</div>';
            return;
        }

        // Check allergies
        const allergyAlert = document.getElementById('orderAllergyAlert');
        if (allergyAlert.style.display !== 'none') {
            msg.innerHTML = '<div class="alert alert-danger">Cannot save: Customer has allergies to selected items</div>';
            return;
        }

        // Check stock
        const stockAlert = document.getElementById('orderStockAlert');
        if (stockAlert.style.display !== 'none') {
            msg.innerHTML = '<div class="alert alert-danger">Cannot save: Insufficient stock</div>';
            return;
        }

        const items = [];
        let total = 0;
        rows.forEach(row => {
            const medicineId = parseInt(row.getAttribute('data-mid'));
            const medicine = this.data.medicines.find(m => m.id === medicineId);
            const qty = parseInt(row.querySelector('.qty-input').value);
            const lineTotal = medicine.price * qty;

            items.push({ medicineId, quantity: qty, price: medicine.price });
            total += lineTotal;
            medicine.stock -= qty;
        });

        const orderId = Math.max(...this.data.orders.map(o => o.id), 0) + 1;
        const newOrder = {
            id: orderId,
            customerId: this.selectedCustomer.id,
            customerName: this.selectedCustomer.name,
            items,
            total,
            payment: document.getElementById('orderPayment').value,
            date: new Date().toISOString().split('T')[0],
        };

        this.data.orders.push(newOrder);
        this.saveToStorage();

        msg.innerHTML = `<div class="alert alert-success">✅ Order #${orderId} saved! Total: ฿${total.toFixed(2)}</div>`;
        this.showToast(`Order #${orderId} saved`);

        setTimeout(() => {
            document.getElementById('orderItems').innerHTML = '';
            document.getElementById('orderSubtotal').textContent = '฿0.00';
            document.getElementById('orderTotal').textContent = '฿0.00';
            document.getElementById('orderCustomerSearch').value = '';
            document.getElementById('selectedCustomerInfo').style.display = 'none';
            document.getElementById('orderAllergyAlert').style.display = 'none';
            document.getElementById('orderStockAlert').style.display = 'none';
            this.selectedCustomer = null;
            msg.innerHTML = '';
        }, 2000);
    },

    // MEDICINES
    renderMedicines() {
        const tbody = document.querySelector('#medicinesTable tbody');
        tbody.innerHTML = this.data.medicines.map(m => `
            <tr>
                <td>#${m.id}</td>
                <td>${m.name}</td>
                <td>${this.data.medicineTypes.find(t => t.id === m.type)?.name || ''}</td>
                <td>฿${m.price.toFixed(2)}</td>
                <td>${m.stock}</td>
                <td><span style="color:${m.stock <= 10 ? '#f59e0b' : '#10b981'};">${m.status}</span></td>
                <td>
                    <button class="btn btn-small" onclick="app.editMedicine(${m.id})">Edit</button>
                    <button class="btn btn-small danger" onclick="app.deleteMedicine(${m.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    },

    renderMedicineForm() {
        document.getElementById('medicineForm').reset();
        if (this.editingId) {
            const med = this.data.medicines.find(m => m.id === this.editingId);
            document.getElementById('medicineFormTitle').textContent = 'Edit Medicine';
            document.getElementById('medName').value = med.name;
            document.getElementById('medType').value = med.type;
            document.getElementById('medPrice').value = med.price;
            document.getElementById('medStock').value = med.stock;
            document.getElementById('medStatus').value = med.status;
        } else {
            document.getElementById('medicineFormTitle').textContent = 'Add Medicine';
        }
    },

    editMedicine(id) {
        this.editingId = id;
        this.goToPage('newmedicine');
    },

    saveMedicine(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('medName').value,
            type: parseInt(document.getElementById('medType').value),
            price: parseFloat(document.getElementById('medPrice').value),
            stock: parseInt(document.getElementById('medStock').value),
            status: document.getElementById('medStatus').value,
        };

        if (this.editingId) {
            const med = this.data.medicines.find(m => m.id === this.editingId);
            Object.assign(med, data);
        } else {
            const id = Math.max(...this.data.medicines.map(m => m.id), 0) + 1;
            this.data.medicines.push({ id, ...data });
        }

        this.saveToStorage();
        this.showToast('Medicine saved');
        this.goToPage('medicines');
    },

    deleteMedicine(id) {
        this.showConfirm('Delete Medicine?', 'Are you sure?', () => {
            this.data.medicines = this.data.medicines.filter(m => m.id !== id);
            this.saveToStorage();
            this.renderMedicines();
            this.showToast('Medicine deleted');
        });
    },

    // STOCK
    renderStock() {
        const tbody = document.querySelector('#stockTable tbody');
        tbody.innerHTML = this.data.medicines.map(m => `
            <tr>
                <td>${m.name}</td>
                <td>${m.stock}</td>
                <td>10</td>
                <td style="color:${m.stock <= 10 ? '#ef4444' : '#10b981'}; font-weight:700;">
                    ${m.stock <= 5 ? '🔴 Critical' : (m.stock <= 10 ? '🟡 Low' : '🟢 Adequate')}
                </td>
            </tr>
        `).join('');
    },

    // STOCK LOG
    renderStockLog() {
        const tbody = document.querySelector('#stockLogTable tbody');
        tbody.innerHTML = this.data.stockLog.map(log => `
            <tr>
                <td>${log.date}</td>
                <td>${log.medicine}</td>
                <td>${log.type}</td>
                <td>${log.qty}</td>
                <td>${log.reference}</td>
            </tr>
        `).join('');
    },

    // REFUNDS
    renderRefunds() {
        const tbody = document.querySelector('#refundsTable tbody');
        tbody.innerHTML = this.data.refunds.map(r => `
            <tr>
                <td>#${r.id}</td>
                <td>#${r.orderId}</td>
                <td>${r.customer}</td>
                <td>฿${r.amount.toFixed(2)}</td>
                <td>${r.date}</td>
            </tr>
        `).join('');
    },

    // CUSTOMERS
    renderCustomers() {
        const tbody = document.querySelector('#customersTable tbody');
        tbody.innerHTML = this.data.customers.map(c => `
            <tr>
                <td>#${c.id}</td>
                <td>${c.name}</td>
                <td>${c.phone}</td>
                <td>${c.address}</td>
                <td>${c.allergies.length > 0 ? c.allergies.map(id => this.data.medicines.find(m => m.id === id)?.name || '').join(', ') : '-'}</td>
                <td>${c.points}</td>
                <td>
                    <button class="btn btn-small" onclick="app.editCustomer(${c.id})">Edit</button>
                    <button class="btn btn-small danger" onclick="app.deleteCustomer(${c.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    },

    renderCustomerForm() {
        document.getElementById('customerForm').reset();
        if (this.editingId) {
            const cust = this.data.customers.find(c => c.id === this.editingId);
            document.getElementById('customerFormTitle').textContent = 'Edit Customer';
            document.getElementById('custName').value = cust.name;
            document.getElementById('custPhone').value = cust.phone;
            document.getElementById('custAddress').value = cust.address;
            
            document.querySelectorAll('#allergyCheckboxes input').forEach(cb => {
                cb.checked = cust.allergies.includes(parseInt(cb.value));
            });
        } else {
            document.getElementById('customerFormTitle').textContent = 'Add Customer';
        }
    },

    editCustomer(id) {
        this.editingId = id;
        this.goToPage('newcustomer');
    },

    saveCustomer(e) {
        e.preventDefault();
        const allergies = Array.from(document.querySelectorAll('#allergyCheckboxes input:checked')).map(cb => parseInt(cb.value));
        
        const data = {
            name: document.getElementById('custName').value,
            phone: document.getElementById('custPhone').value,
            address: document.getElementById('custAddress').value,
            allergies,
        };

        if (this.editingId) {
            const cust = this.data.customers.find(c => c.id === this.editingId);
            Object.assign(cust, data);
        } else {
            const id = Math.max(...this.data.customers.map(c => c.id), 0) + 1;
            this.data.customers.push({ id, ...data, points: 0 });
        }

        this.saveToStorage();
        this.showToast('Customer saved');
        this.goToPage('customers');
    },

    deleteCustomer(id) {
        this.showConfirm('Delete Customer?', 'Are you sure?', () => {
            this.data.customers = this.data.customers.filter(c => c.id !== id);
            this.saveToStorage();
            this.renderCustomers();
            this.showToast('Customer deleted');
        });
    },

    // CUSTOMER POINTS
    renderCustomerPoints() {
        const tbody = document.querySelector('#customerPointsTable tbody');
        tbody.innerHTML = this.data.customers.map(c => `
            <tr>
                <td>${c.name}</td>
                <td>${c.points}</td>
                <td>${new Date().toLocaleDateString()}</td>
            </tr>
        `).join('');
    },

    // EMPLOYEES
    renderEmployees() {
        const tbody = document.querySelector('#employeesTable tbody');
        tbody.innerHTML = this.data.employees.map(e => `
            <tr>
                <td>#${e.id}</td>
                <td>${e.name}</td>
                <td>${e.position}</td>
                <td>${e.phone}</td>
                <td>${e.email}</td>
                <td>฿${e.salary.toLocaleString()}</td>
                <td><span style="color:${e.status === 'Active' ? '#10b981' : '#ef4444'};">${e.status}</span></td>
                <td>
                    <button class="btn btn-small" onclick="app.editEmployee(${e.id})">Edit</button>
                    <button class="btn btn-small danger" onclick="app.deleteEmployee(${e.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    },

    renderEmployeeForm() {
        document.getElementById('employeeForm').reset();
        if (this.editingId) {
            const emp = this.data.employees.find(e => e.id === this.editingId);
            document.getElementById('employeeFormTitle').textContent = 'Edit Employee';
            document.getElementById('empName').value = emp.name;
            document.getElementById('empPosition').value = emp.position;
            document.getElementById('empPhone').value = emp.phone;
            document.getElementById('empEmail').value = emp.email;
            document.getElementById('empSalary').value = emp.salary;
            document.getElementById('empAddress').value = emp.address;
            document.getElementById('empStatus').value = emp.status;
        } else {
            document.getElementById('employeeFormTitle').textContent = 'Add Employee';
        }
    },

    editEmployee(id) {
        this.editingId = id;
        this.goToPage('newemployee');
    },

    saveEmployee(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('empName').value,
            position: document.getElementById('empPosition').value,
            phone: document.getElementById('empPhone').value,
            email: document.getElementById('empEmail').value,
            salary: parseFloat(document.getElementById('empSalary').value) || 0,
            address: document.getElementById('empAddress').value,
            status: document.getElementById('empStatus').value,
        };

        if (this.editingId) {
            const emp = this.data.employees.find(e => e.id === this.editingId);
            Object.assign(emp, data);
        } else {
            const id = Math.max(...this.data.employees.map(e => e.id), 0) + 1;
            this.data.employees.push({ id, ...data });
        }

        this.saveToStorage();
        this.showToast('Employee saved');
        this.goToPage('employees');
    },

    deleteEmployee(id) {
        this.showConfirm('Delete Employee?', 'Are you sure?', () => {
            this.data.employees = this.data.employees.filter(e => e.id !== id);
            this.saveToStorage();
            this.renderEmployees();
            this.showToast('Employee deleted');
        });
    },

    // UI Helpers
    showConfirm(title, message, onConfirm) {
        const modal = document.getElementById('confirmModal');
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalMessage').textContent = message;
        
        const confirmBtn = document.getElementById('confirmBtn');
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        document.getElementById('confirmBtn').addEventListener('click', () => {
            onConfirm();
            this.closeModal();
        });
        
        modal.classList.add('active');
    },

    closeModal() {
        document.getElementById('confirmModal').classList.remove('active');
    },

    showToast(message) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMessage').textContent = message;
        toast.classList.add('active');
        setTimeout(() => toast.classList.remove('active'), 3000);
    },

    attachThemeToggle() {
        const toggle = document.getElementById('themeToggle');
        toggle?.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            try {
                localStorage.setItem('pharmacyPosTheme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
            } catch (e) {}
        });

        try {
            if (localStorage.getItem('pharmacyPosTheme') === 'dark') {
                document.body.classList.add('dark-mode');
            }
        } catch (e) {}
    }
};

document.addEventListener('DOMContentLoaded', () => app.init());
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') app.closeModal();
});
