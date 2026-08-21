<?php
$pageTitle = 'Dashboard';
$pageSub = 'Overview of recent sales, top products, customers, and stock alerts.';
ob_start();
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

// Minimal server-side work; charts fetch data from API endpoints.
?>

<!-- Tailwind (CDN) -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Chart.js datalabels plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

<style>
  :root{ --primary:#0f766e; --accent:#10b981; --neutral:#475569 }
  .card { background: #fff; border-radius:12px; box-shadow:0 8px 24px rgba(15,23,42,0.06); min-height:140px; }
  .card-title{ font-weight:600; color:var(--neutral); }
  .card-sub{ font-size:.875rem; color:#6b7280 }
  .slide-up{ transform:translateY(12px); opacity:0; transition:all .45s ease; }
  .slide-up.show{ transform:none; opacity:1 }
  .no-data{ display:flex; align-items:center; justify-content:center; height:100%; color:#9ca3af; font-weight:600 }
</style>

<div class="max-w-7xl mx-auto py-8 px-4">
  <!-- Top section: Low-stock (left) and Date Range (right) in two-column layout -->
  <div class="mb-6 grid grid-cols-1 md:grid-cols-2 items-center gap-6">
    <!-- Left: Reduced-width low-stock card (always visible) -->
    <div id="low-stock-alert-top">
      <div class="card p-4 bg-red-50 border border-red-200" style="max-width:560px;">
        <div class="flex items-center justify-between gap-4">
          <div class="text-left" style="flex:1 1 auto">
            <div class="font-bold text-red-800 text-lg" id="low-stock-title-top">Low stock</div>
            <div class="text-sm text-red-700 mt-1" id="low-stock-list-top"></div>
          </div>
          <div style="flex:0 0 auto">
            <a href="/pages/stock.php" class="inline-block bg-red-600 text-white px-3 py-1 rounded text-sm">View All</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Date Range filter, aligned to the right column -->
    <div class="flex justify-end">
      <div class="card p-4">
        <div class="flex items-center gap-6">
          <!-- From Date Field -->
          <div class="card-title">Date Range</div>
          <div class="flex flex-col gap-1.5">
            
            <label for="kpi-start" class="text-sm font-medium text-gray-700">From</label>
            <input type="date" id="kpi-start" class="border border-gray-300 rounded px-3 py-2 bg-white text-sm h-10 w-32">
          </div>

          <!-- To Date Field -->
          <div class="flex flex-col gap-1.5">
            <label for="kpi-end" class="text-sm font-medium text-gray-700">To</label>
            <input type="date" id="kpi-end" class="border border-gray-300 rounded px-3 py-2 bg-white text-sm h-10 w-32">
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-2">
            <button id="applyKpiRange" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm h-10 font-medium transition">Apply</button>
            <button id="clearKpiRange" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm h-10 font-medium transition">Clear</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- KPI row -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    <div class="card p-5 slide-up">
      <div class="card-title">Total Sales</div>
      <div class="card-sub">Gross sales</div>
      <div class="mt-3 text-2xl font-bold text-emerald-600">฿ <span id="kpi-total-sales">0.00</span></div>
      <div class="text-xs text-gray-400 mt-1">Updated: <span id="kpi-updated"></span></div>
    </div>

  <!-- old low-stock alert removed (moved to top) -->

    <div class="card p-5 slide-up">
      <div class="card-title">Top Customer</div>
      <div class="card-sub">Highest spending customer</div>
      <div class="mt-3 text-lg font-semibold" id="kpi-top-customer">—</div>
      <div class="text-xs text-gray-400 mt-1" id="kpi-top-customer-val"></div>
    </div>

    <div class="card p-5 slide-up">
      <div class="card-title">Top Product</div>
      <div class="card-sub">Most sold item</div>
      <div class="mt-3 text-lg font-semibold" id="kpi-top-product">—</div>
      <div class="text-xs text-gray-400 mt-1" id="kpi-top-product-val"></div>
    </div>
  </div>

  <!-- Charts grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Combined Top Entities -->
    <div class="card p-4 slide-up">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
          <div>
            <div id="top-entities-title" class="card-title">Top Entities</div>

          </div>
          <select id="top-entity-select" class="border border-gray-300 rounded px-3 py-1 bg-white text-sm">
            <option value="products" selected>Top Products</option>
            <option value="customers">Top Customers</option>
          </select>
        </div>
      <div style="height:360px"><canvas id="chart-top-combined"></canvas></div>
    </div>

    <!-- Sales trend with period selector -->
    <div class="card p-4 slide-up">
      <div class="mb-3 flex items-center justify-between gap-3">
        <div>
          <div class="card-title">Sales Trend</div>

        </div>
        <select id="sales-period-select" class="border border-gray-300 rounded px-3 py-1 bg-white text-sm">
          <option value="sales_day">Last 14 days</option>
          <option value="sales_month" selected>Last 6 months</option>
          <option value="sales_year">Last 5 years</option>
          <option value="sales_range">Custom range</option>
        </select>
        <div id="custom-date-range" style="display:none; margin-top:8px;">
          <div class="flex flex-col items-stretch gap-2">
            <div class="flex items-center gap-2">
              <label class="sr-only" for="startDate">Start date</label>
              <input type="date" id="startDate" class="border border-gray-300 rounded px-3 py-1 bg-white text-sm w-full">
            </div>
            <div class="flex items-center gap-2">
              <label class="sr-only" for="endDate">End date</label>
              <input type="date" id="endDate" class="border border-gray-300 rounded px-3 py-1 bg-white text-sm w-full">
            </div>
            <div class="flex justify-end">
              <button id="applyRangeBtn" class="bg-emerald-600 text-white px-4 py-1 rounded text-sm">Apply</button>
            </div>
          </div>
        </div>
      </div>
      <div style="height:320px"><canvas id="chart-sales-trend"></canvas></div>
    </div>

    <!-- Stock Levels removed per request -->
  </div>
</div>

<script>
// animate cards
document.addEventListener('DOMContentLoaded', ()=>{
  requestAnimationFrame(()=>{ document.querySelectorAll('.slide-up').forEach((el,i)=>setTimeout(()=>el.classList.add('show'), 80*i)); });
});

function registerDataLabels(){
  if (!window.Chart || !window.ChartDataLabels) return;
  try {
    // Safe check for plugin presence; different Chart.js versions expose registry differently
    const has = Chart.registry && Chart.registry.plugins && typeof Chart.registry.plugins.get === 'function'
      ? !!Chart.registry.plugins.get('datalabels')
      : (typeof Chart.registry.getPlugin === 'function' ? !!Chart.registry.getPlugin('datalabels') : false);
    if (!has) Chart.register(window.ChartDataLabels);
  } catch (e) {
    try { Chart.register(window.ChartDataLabels); } catch (_) { /* ignore */ }
  }
}

function makeColors(n, highlightIndex){
  const primary = '#10b981'; const neutral = '#60a5fa'; const arr=[];
  for(let i=0;i<n;i++){ arr.push(i===highlightIndex? primary : neutral); }
  return arr;
}

function showCountBadge(container, count){
  const prev = container.querySelector('.dp-count-badge'); if(prev) prev.remove();
  const b = document.createElement('div'); b.className = 'dp-count-badge text-xs';
  b.style.position='absolute'; b.style.top='10px'; b.style.right='12px'; b.style.background='rgba(15,23,42,0.04)'; b.style.padding='6px 8px'; b.style.borderRadius='999px'; b.style.color='#374151'; b.textContent = count+' items';
  container.style.position = container.style.position || 'relative'; container.appendChild(b);
}

async function fetchView(view, start=null, end=null){
  let url = '/api/dashboard.php?view=' + encodeURIComponent(view);
  if (start && end) url += '&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
  const res = await fetch(url);
  if(!res.ok) throw new Error('Failed: '+view);
  return res.json();
}

// Combined top chart (products/customers)
async function renderTopCombined(view, start=null, end=null){
  const query = view === 'customers' ? 'sales_customer' : 'top_products';
  const data = await fetchView(query, start, end);
  console.log('dashboard: top_combined', view, data);
  const items = (data.items || []).slice();
  items.sort((a,b)=>b.value - a.value);
  const labels = items.map(i=>i.label);
  const values = items.map(i=>i.value||0);

  registerDataLabels();
  const ctx = document.getElementById('chart-top-combined').getContext('2d');
  const wrap = ctx.canvas.parentElement;
  showCountBadge(wrap, items.length);
  const prev = wrap.querySelector('.no-data'); if(prev) prev.remove(); ctx.canvas.style.display = '';
  if (values.length === 0) {
    if(window._topCombined) window._topCombined.destroy();
    ctx.canvas.style.display = 'none';
    const el = document.createElement('div'); el.className='no-data'; el.textContent='No data'; wrap.appendChild(el); return;
  }

  if (window._topCombined) window._topCombined.destroy();

  window._topCombined = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets:[{ data: values, backgroundColor: makeColors(labels.length, 0), borderRadius: 6 }]},
    options: {
      indexAxis: view === 'products' ? 'y' : 'x',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
        datalabels: { anchor: 'end', align: 'end', color: '#063047', font: { weight: '600' }, formatter: Math.round }
      },
      scales: { x: { ticks: { color: '#475569' } }, y: { ticks: { color: '#475569' } } }
    }
  });
}

// Sales lines: supports day/month/year
function defaultLineOptions(){ return { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false }, tooltip:{ mode:'index', intersect:false }, datalabels:{ display:true, align:'top', anchor:'end', color:'#065f46', formatter: v => Math.round(v), font:{size:10} } }, scales:{ x:{ ticks:{ color:'#475569' } }, y:{ ticks:{ color:'#475569' } } } } }

async function renderSalesTrend(view){
  // If view is an object with explicit items, use it directly
  const labelsBy = {
    sales_day: 'Last 14 days',
    sales_month: 'Last 6 months',
    sales_year: 'Last 5 years',
    sales_range: 'Custom range'
  };
  let items = [];
  if (Array.isArray(view)) {
    items = view.slice();
  } else {
    const data = await fetchView(view);
    items = (data && data.items) ? data.items : [];
  }
  console.log('dashboard: sales_trend', view, items);
  const rawLabels = items.map(i=>i.label);
  const labels = rawLabels.map(l => formatDateForDisplay(l, typeof view === 'string' ? view : 'sales_range'));
  const values = items.map(i=>i.value||0);
  registerDataLabels();
  const ctx = document.getElementById('chart-sales-trend').getContext('2d');
  const wrap = ctx.canvas.parentElement;
  const prev = wrap.querySelector('.no-data'); if(prev) prev.remove(); ctx.canvas.style.display='';
  showCountBadge(wrap, items.length);

  if (values.length === 0) {
    if (window._salesTrend) window._salesTrend.destroy();
    ctx.canvas.style.display = 'none';
    const el = document.createElement('div'); el.className = 'no-data'; el.textContent = 'No data'; wrap.appendChild(el); return;
  }

  if (window._salesTrend) window._salesTrend.destroy();
  window._salesTrend = new Chart(ctx, {
    type:'line',
    data:{ labels, datasets:[{ label:labelsBy[typeof view === 'string' ? view : 'sales_range'] || 'Sales', data:values, borderColor:'rgba(16,185,129,1)', backgroundColor:'rgba(16,185,129,0.12)', tension:0.25, fill:true, pointRadius:3 }]},
    options: defaultLineOptions()
  });
}

// Attempt to fetch a custom range from backend; fallback to client-side filter
async function fetchAndRenderRange(start, end){
  // try backend first
  try{
    const url = '/api/dashboard.php?view=sales_range&start='+encodeURIComponent(start)+"&end="+encodeURIComponent(end);
    const res = await fetch(url);
    if (res.ok){
      const data = await res.json();
      if (data && Array.isArray(data.items)){
        await renderSalesTrend(data.items);
        return;
      }
    }
  }catch(e){ console.warn('Backend range fetch failed', e); }

  // fallback: fetch a broad dataset and filter by label dates (best-effort)
  try{
    const fallback = await fetchView('sales_year');
    const items = (fallback && fallback.items) ? fallback.items.slice() : [];
    const s = Date.parse(start);
    const e = Date.parse(end);
    const filtered = items.filter(it => {
      const d = Date.parse(it.label);
      if (isNaN(d)) return false;
      return d >= s && d <= e;
    });
    await renderSalesTrend(filtered);
  }catch(e){ console.warn('Fallback range render failed', e); }
}

// Stock Levels feature removed — chart renderer deleted

// KPIs hydrate (accept optional start/end in yyyy-mm-dd)
async function hydrateKPIs(start=null, end=null){
  try{
    let url = '/api/dashboard.php?view=sales_month';
    if (start && end) {
      url = '/api/dashboard.php?view=sales_range&start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
    }
    const res = await fetch(url);
    const data = await res.json();
    document.getElementById('kpi-total-sales').textContent = (data.total_sales||0).toFixed(2);
    document.getElementById('kpi-updated').textContent = new Date().toLocaleString('en-GB');
    if(data.top_customer){ document.getElementById('kpi-top-customer').textContent = data.top_customer.name||'—'; document.getElementById('kpi-top-customer-val').textContent = '฿ '+(data.top_customer.value||0).toFixed(2); }
    if(data.top_product){ document.getElementById('kpi-top-product').textContent = data.top_product.name||'—'; document.getElementById('kpi-top-product-val').textContent = (data.top_product.value||0)+' sold'; }
    // Render low-stock alerts (if any) - show top 5, short labels
    try {
      const alertContainerTop = document.getElementById('low-stock-alert-top');
      const listElTop = document.getElementById('low-stock-list-top');
      const titleElTop = document.getElementById('low-stock-title-top');
      if (data.alerts && data.alerts.length > 0) {
        titleElTop.textContent = `Low stock — ${data.alerts.length}`;
        const firstFive = data.alerts.slice(0,5);
        listElTop.innerHTML = firstFive.map(a => `<div class="py-1"><strong class="text-red-800">${escapeHtml(a.medicine_name)}</strong>: <span class="text-red-700">${a.current_stock}</span> left</div>`).join('');
      } else {
        titleElTop.textContent = 'Low stock — 0';
        if (listElTop) listElTop.innerHTML = '<div class="py-1 text-sm text-gray-600">No low stock</div>';
      }
    } catch (e) { console.warn('Failed to render low-stock alerts', e); }
  }catch(e){ console.warn('KPIs failed', e); }
}

// small helper to avoid HTML injection
function escapeHtml(str){ return String(str).replace(/[&<>"]/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]); }); }

// Date parsing and display helpers: make labels friendly (dd/mm or dd/mm/yyyy)
function parseDateFromLabel(label){
  if(!label) return null;
  const s = String(label).trim();
  // Normalize slashes to dashes
  const norm = s.replace(/\//g,'-');
  if (/^\d{4}-\d{2}-\d{2}$/.test(norm)) return new Date(norm + 'T00:00:00');
  if (/^\d{4}-\d{2}$/.test(norm)){
    const [y,m] = norm.split('-'); return new Date(Number(y), Number(m)-1, 1);
  }
  const parsed = Date.parse(s);
  if(!isNaN(parsed)) return new Date(parsed);
  return null;
}

function formatDateForDisplay(label, view){
  const d = parseDateFromLabel(label);
  if(!d) return label;
  // Use day/month for daily views, dd/mm/yyyy for custom ranges, short month/year for months
  try{
    if(view === 'sales_day') return d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit' });
    if(view === 'sales_month') return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
    if(view === 'sales_year') return d.getFullYear().toString();
    // sales_range and fallback
    return d.toLocaleDateString('en-GB');
  }catch(e){ return label; }
}

async function loadAll(){
  try{
    await hydrateKPIs();
    const entitySelect = document.getElementById('top-entity-select');
    const salesSelect = document.getElementById('sales-period-select');
    const sdInput = document.getElementById('startDate');
    const edInput = document.getElementById('endDate');
    if (sdInput) sdInput.setAttribute('placeholder', 'dd/mm/yyyy');
    if (edInput) edInput.setAttribute('placeholder', 'dd/mm/yyyy');
    const entitySelected = entitySelect ? entitySelect.value : 'products';
    const salesSelected = salesSelect ? salesSelect.value : 'sales_month';
    // Set title to match initial selection
    const titleEl = document.getElementById('top-entities-title');
    if (titleEl) titleEl.textContent = (entitySelected === 'customers') ? 'Top Customer' : 'Top Product';
    await Promise.all([ renderTopCombined(entitySelected), renderSalesTrend(salesSelected) ]);

    if (entitySelect) {
      entitySelect.addEventListener('change', async (event)=>{
        const v = event.target.value;
        // Update title to match dropdown (singular)
        if (titleEl) titleEl.textContent = (v === 'customers') ? 'Top Customer' : 'Top Product';
        await renderTopCombined(v);
      });
    }
    if (salesSelect) {
      salesSelect.addEventListener('change', async (event)=>{
        const v = event.target.value;
        if (v === 'sales_range') {
          document.getElementById('custom-date-range').style.display = '';
        } else {
          document.getElementById('custom-date-range').style.display = 'none';
          await renderSalesTrend(v);
        }
      });
    }

    // custom range apply
    const applyBtn = document.getElementById('applyRangeBtn');
    if (applyBtn) {
      applyBtn.addEventListener('click', async ()=>{
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        if (!s || !e) return alert('Please select start and end dates');
        if (Date.parse(s) > Date.parse(e)) return alert('Start must be before end');
        await fetchAndRenderRange(s, e);
      });
    }

    // KPI date-range apply/clear
    const kpiApply = document.getElementById('applyKpiRange');
    const kpiClear = document.getElementById('clearKpiRange');
    const kpiStart = document.getElementById('kpi-start');
    const kpiEnd = document.getElementById('kpi-end');
    if (kpiApply) {
      kpiApply.addEventListener('click', async () => {
        const s = kpiStart.value; const e = kpiEnd.value;
        if (!s || !e) return alert('Please select start and end dates');
        if (Date.parse(s) > Date.parse(e)) return alert('Start must be before end');
        await hydrateKPIs(s, e);
        // also refresh top combined and sales trend within range
        const ent = entitySelect ? entitySelect.value : 'products';
        await Promise.all([ renderTopCombined(ent, s, e), fetchAndRenderRange(s, e) ]);
      });
    }
    if (kpiClear) {
      kpiClear.addEventListener('click', async () => {
        if (kpiStart) kpiStart.value = '';
        if (kpiEnd) kpiEnd.value = '';
        await hydrateKPIs();
        const ent = entitySelect ? entitySelect.value : 'products';
        await Promise.all([ renderTopCombined(ent), renderSalesTrend(salesSelected) ]);
      });
    }
  } catch(e){
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', loadAll);
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
