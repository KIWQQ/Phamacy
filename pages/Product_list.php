<?php
$pageTitle = 'Products';
$pageSub = '';
ob_start();
?>

<div class="container py-4 main-container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="page-title mb-0">Products</h3>
    <a href="/Final_Project/pages/new_medicine.php" class="btn btn-success btn-sm">
      <i class="bi bi-plus-circle me-1"></i>New Medicine
    </a>
  </div>

  <?php
  require_once __DIR__ . '/../config/db.php';
  $pdo = getPDO();

  $search = isset($_GET['search']) ? trim($_GET['search']) : '';
  $type_id = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? intval($_GET['type_id']) : 0;
  $sortdir = isset($_GET['sortdir']) ? trim($_GET['sortdir']) : 'medicine_name:asc';
  if (strpos($sortdir, ':') !== false) {
    list($sort, $dirPart) = explode(':', $sortdir, 2);
    $dir = (strtolower($dirPart) === 'asc') ? 'ASC' : 'DESC';
  } else {
    $sort = $sortdir;
    $dir = 'ASC';
  }

  // load medicine types for filter select
  try {
    $typeStmt = $pdo->prepare('SELECT type_id, type_name FROM medicine_type ORDER BY type_name');
    $typeStmt->execute();
    $medicine_types = $typeStmt->fetchAll();
  } catch (Exception $e) {
    $medicine_types = [];
  }
  ?>

  <!-- Search Form -->




  <?php
  $params = [];
  // build where clauses from search and type filter
  $clauses = [];
  if ($search !== '') {
    if (is_numeric($search)) {
      $clauses[] = "(m.medicine_id = :search_id OR m.medicine_name LIKE :search_name)";
      $params['search_id'] = (int)$search;
      $params['search_name'] = "%{$search}%";
    } else {
      $clauses[] = "m.medicine_name LIKE :search_name";
      $params['search_name'] = "%{$search}%";
    }
  }
  if ($type_id > 0) {
    $clauses[] = "m.type_id = :type_id";
    $params['type_id'] = $type_id;
  }
  $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

  // map allowed sort keys to SQL expressions
  $allowed = [
    'medicine_name' => 'm.medicine_name',
    'medicine_id' => 'm.medicine_id',
    'price' => 'm.price',
    'status' => 'm.status',
    'type_name' => 'mt.type_name'
  ];
  $orderBy = isset($allowed[$sort]) ? $allowed[$sort] : 'm.medicine_name';

  $sql = "SELECT m.medicine_id, m.medicine_name, mt.type_name, m.price, m.status
            FROM medicine m
            JOIN medicine_type mt ON mt.type_id = m.type_id
            {$where}
            ORDER BY {$orderBy} {$dir}
            LIMIT 500";
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();
  } catch (Exception $e) {
    $medicines = [];
    $errorMsg = $e->getMessage();
  }
  ?>

  <div class="card card-compact shadow-sm">
    <div class="card-body">
      <form id="product-filter-form" method="GET" class="mb-3">
        <div class="filter-type">
          <select name="type_id" class="form-select form-select-sm" onchange="document.getElementById('product-filter-form').submit();">
            <option value="">All Types</option>
            <?php foreach ($medicine_types as $t): ?>
              <option value="<?php echo $t['type_id']; ?>" <?php echo ($type_id == $t['type_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['type_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-search">
          <input type="search" name="search" class="form-control form-control-sm" placeholder="Search by Medicine ID or Name" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-sort">
          <select name="sortdir" class="form-select form-select-sm" onchange="document.getElementById('product-filter-form').submit();">
            <option value="medicine_name:asc" <?php if(($sort.':'.strtolower($dir))==='medicine_name:asc') echo 'selected'; ?>>Name ↑</option>
            <option value="medicine_name:desc" <?php if(($sort.':'.strtolower($dir))==='medicine_name:desc') echo 'selected'; ?>>Name ↓</option>
            <option value="medicine_id:asc" <?php if(($sort.':'.strtolower($dir))==='medicine_id:asc') echo 'selected'; ?>>ID ↑</option>
            <option value="medicine_id:desc" <?php if(($sort.':'.strtolower($dir))==='medicine_id:desc') echo 'selected'; ?>>ID ↓</option>
            <option value="price:asc" <?php if(($sort.':'.strtolower($dir))==='price:asc') echo 'selected'; ?>>Price ↑</option>
            <option value="price:desc" <?php if(($sort.':'.strtolower($dir))==='price:desc') echo 'selected'; ?>>Price ↓</option>
            <option value="status:asc" <?php if(($sort.':'.strtolower($dir))==='status:asc') echo 'selected'; ?>>Status ↑</option>
            <option value="status:desc" <?php if(($sort.':'.strtolower($dir))==='status:desc') echo 'selected'; ?>>Status ↓</option>
          </select>
        </div>
        <div class="filter-actions">
          <button class="btn btn-primary btn-sm" type="submit">Search</button>
          <a class="btn btn-secondary btn-sm" href="/Final_Project/pages/Product_list.php">Clear</a>
        </div>
      </form>
    </div>
    </div>
    <br>
    <div class="card card-compact shadow-sm">
      <div class="card-body">
    <div class="table-responsive">
      <table class="table table-clean table-sm table-striped table-hover table-bordered align-middle">
        <thead class="table-light">
            <tr>
            <th style="width:90px">ID</th>
            <th>Medicine</th>
            <th>Type</th>
            <th class="text-center">Status</th>
            <th class="text-end">Price</th>
            <th style="width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($errorMsg)): ?>
            <tr>
              <td colspan="6">
                <div class="alert alert-danger mb-0">Search error: <?php echo htmlspecialchars($errorMsg); ?></div>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($medicines as $o): ?>
            <tr>
              <td class="fw-bold">#<?php echo htmlspecialchars($o['medicine_id']); ?></td>
              <td><?php echo htmlspecialchars($o['medicine_name']); ?></td>
              <td><?php echo htmlspecialchars($o['type_name']); ?></td>
              <td class="text-center">
                <?php if (isset($o['status']) && $o['status'] === 'Discontinued'): ?>
                  <span class="badge bg-danger">Discontinued</span>
                <?php elseif (isset($o['status']) && $o['status'] === 'Not available'): ?>
                  <span class="badge bg-secondary">Not available</span>
                <?php else: ?>
                  <span class="badge bg-success">Available</span>
                <?php endif; ?>
              </td>
              <td class="text-end">฿ <?php echo number_format($o['price'], 2); ?></td>
              <td class="text-end">
                <div class="table-actions">
                  <a href="/Final_Project/pages/new_medicine.php?medicine_id=<?php echo $o['medicine_id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                  <?php if (isset($o['status']) && $o['status'] === 'Discontinued'): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary toggle-status-btn" data-id="<?php echo $o['medicine_id']; ?>" data-action="restore">Undo</button>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-danger toggle-status-btn" data-id="<?php echo $o['medicine_id']; ?>" data-action="cancel">Discontinue</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<!-- Immediate toggle: Delete/Undo without modal -->
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.toggle-status-btn').forEach(btn => {
    btn.addEventListener('click', async function(){
      const id = this.dataset.id; const action = this.dataset.action;
      if (!id || !action) return;
      const btnEl = this; btnEl.disabled = true; const origText = btnEl.textContent;
      btnEl.textContent = (action === 'cancel') ? 'Discontinuing...' : 'Restoring...';
      try {
        const url = (action === 'cancel') ? '/Final_Project/api/delete_medicine.php' : '/Final_Project/api/restore_medicine.php';
        const res = await fetch(url, { method:'POST', headers:{ 'Content-Type': 'application/json' }, body: JSON.stringify({ medicine_id: parseInt(id) }) });
        const data = await res.json().catch(() => null);
        if (res.ok && data && data.success) {
          // update badge and button in the same row
          const row = btnEl.closest('tr');
          if (row) {
            const badgeCell = row.querySelector('td:nth-child(4)'); // status cell
            if (badgeCell) {
              if (action === 'cancel') badgeCell.innerHTML = '<span class="badge bg-danger">Discontinued</span>';
              else badgeCell.innerHTML = '<span class="badge bg-success">Available</span>';
            }
            // swap button appearance and action
            if (action === 'cancel') {
              btnEl.className = 'btn btn-sm btn-outline-secondary toggle-status-btn';
              btnEl.dataset.action = 'restore';
              btnEl.textContent = 'Undo';
            } else {
              btnEl.className = 'btn btn-sm btn-outline-danger toggle-status-btn';
              btnEl.dataset.action = 'cancel';
              btnEl.textContent = 'Discontinue';
            }
          }
        } else {
          alert('Failed: ' + (data && data.error ? data.error : 'Unknown error'));
          btnEl.textContent = origText;
        }
      } catch (err) {
        alert('Error: ' + err.message);
        btnEl.textContent = origText;
      } finally {
        btnEl.disabled = false;
      }
    });
  });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
