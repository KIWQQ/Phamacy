<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside id="appSidebar" class="sidebar d-flex flex-column py-4">
  <div class="px-3 mb-4 d-flex align-items-center gap-2 brand-wrap">
    <div class="brand-icon"><i class="bi bi-bandaid"></i></div>
    <div>
      <div class="brand-title">Pharmacy POS</div>
      <div class="brand-sub">Clinic Dashboard</div>
    </div>
  </div>

  <nav class="flex-grow-1 px-2">
    <a href="/pages/dashboard.php" class="nav-link d-flex align-items-center <?php echo $current==='dashboard.php' ? 'active':''; ?>"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    
    <div class="nav-divider my-3"></div>
    <div class="nav-label small text-muted px-2 mb-2">Orders</div>
    <a href="/pages/order_list.php" class="nav-link d-flex align-items-center <?php echo $current==='order_list.php' ? 'active':''; ?>"><i class="bi bi-receipt me-2"></i> Orders</a>
    <a href="/pages/refund_list.php" class="nav-link d-flex align-items-center <?php echo $current==='refund_list.php' ? 'active':''; ?>"><i class="bi bi-arrow-counterclockwise me-2"></i> Refunds</a>
    
    <div class="nav-divider my-3"></div>
    <div class="nav-label small text-muted px-2 mb-2">Products</div>
    <a href="/pages/Product_list.php" class="nav-link d-flex align-items-center <?php echo (strtolower($current)==='product_list.php' || $current==='Product_list.php') ? 'active':''; ?>"><i class="bi bi-boxes me-2"></i> Medicines</a>
    <a href="/pages/stock.php" class="nav-link d-flex align-items-center <?php echo in_array($current, ['stock.php', 'stock_lots.php', 'new_stock.php'], true) ? 'active':''; ?>"><i class="bi bi-stack me-2"></i> Stock</a>
      <a href="/pages/stock_log.php" class="nav-link d-flex align-items-center <?php echo $current==='stock_log.php' ? 'active':''; ?>"><i class="bi bi-journal-text me-2"></i> Stock Log</a>
    
    <div class="nav-divider my-3"></div>
    <div class="nav-label small text-muted px-2 mb-2">Master Data</div>
    <a href="/pages/customer_list.php" class="nav-link d-flex align-items-center <?php echo $current==='customer_list.php' ? 'active':''; ?>"><i class="bi bi-people me-2"></i> Customers</a>
    <a href="/pages/employee_list.php" class="nav-link d-flex align-items-center <?php echo $current==='employee_list.php' ? 'active':''; ?>"><i class="bi bi-person-lines-fill me-2"></i> Employees</a>
  </nav>

  <div class="px-3 mt-3">
    <?php if (!empty($_SESSION['user'])): ?>
      <a href="/pages/logout.php" class="btn btn-outline-secondary btn-sm w-100">Logout (<?php echo htmlspecialchars($_SESSION['user']['username']); ?>)</a>
    <?php endif; ?>
  </div>

</aside>
