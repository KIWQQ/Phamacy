<?php
$pageTitle = 'Customer Points';
$pageSub = '';
ob_start();
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="page-title mb-0">Customer Points</h3>
    <a href="/pages/customer_list.php" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>

  <?php
  require_once __DIR__ . '/../config/db.php';
  require_once __DIR__ . '/../functions/customer_functions.php';
  
  $pdo = getPDO();

  $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
  if (!$customer_id) {
    echo '<div class="alert alert-danger">Missing customer_id</div>';
    exit;
  }

  try {
    $cust_name = getCustomerName($pdo, $customer_id);
    if (!$cust_name) throw new Exception('Customer not found');

    // Try to select with created_at; if the column doesn't exist (older schema), fall back
    try {
      $stmt2 = $pdo->prepare('SELECT point_id, points, type, created_at FROM point_transaction WHERE customer_id = :id ORDER BY created_at DESC');
      $stmt2->execute(['id' => $customer_id]);
      $rows = $stmt2->fetchAll();
    } catch (PDOException $pe) {
      // If created_at column missing, retry without it and add a null created_at in PHP
      $rows = [];
      if (strpos($pe->getMessage(), 'Unknown column') !== false || $pe->getCode() === '42S22') {
        $stmt2 = $pdo->prepare('SELECT point_id, points, type FROM point_transaction WHERE customer_id = :id ORDER BY point_id DESC');
        $stmt2->execute(['id' => $customer_id]);
        $tmp = $stmt2->fetchAll();
        foreach ($tmp as $tr) {
          $tr['created_at'] = null;
          $rows[] = $tr;
        }
      } else {
        throw $pe;
      }
    }

    $total = 0;
    foreach ($rows as $r) { $total += intval($r['points']); }
  } catch (Exception $e) {
    $rows = [];
    $error = $e->getMessage();
  }
  ?>

  <div class="card card-compact shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0"><?php echo htmlspecialchars($cust_name); ?></h5>
        <div class="small text-muted">Customer ID: <?php echo $customer_id; ?></div>
      </div>
      <div class="text-end">
        <div class="h4 mb-0"><?php echo intval($total); ?> pts</div>
        <div class="small text-muted">Balance</div>
      </div>
    </div>
  </div>

  <div class="card card-compact shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light"><tr><th style="width:90px">ID</th><th>Type</th><th class="text-end">Points</th><th style="width:180px">Date</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>#<?php echo $r['point_id']; ?></td>
                <td><?php echo htmlspecialchars($r['type']); ?></td>
                <td class="text-end"><?php echo intval($r['points']); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No point transactions</td></tr>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
              <tr><td colspan="4"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
