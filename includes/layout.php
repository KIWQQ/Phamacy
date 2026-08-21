<?php
if (!isset($pageTitle)) $pageTitle = '';
if (!isset($content)) $content = '';




?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle ? $pageTitle . ' — Pharmacy POS' : 'Pharmacy POS'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="/assets/style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main">
      <div class="container-fluid">
        <?php include __DIR__ . '/navbar.php'; ?>
        <?php echo $content; ?>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
