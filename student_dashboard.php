<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/reference.php';
require_once __DIR__ . '/lib/controls.php';

require_login_page();
$user = current_user();

if ($user['type'] !== 'student') {
    header('Location: /newapp/dashboard.php');
    exit;
}

$comments = get_all_comments($mysqli);
$controls = get_all_controls($mysqli);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Portal - Dr. Virendra Swarup Education Centre, Avadhpuri</title>
<?php include __DIR__ . '/partials/_assets_head.php'; ?>
</head>
<body>

<div id="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <img src="/newapp/assets/images/logo.gif" width="40" height="40" class="rounded-circle" alt="School Logo">
    <div>
      <div class="school-name">Dr. Virendra Swarup Education Centre, Avadhpuri</div>
      <div class="welcome-line">Welcome, <strong><?= htmlspecialchars($user['sname']) ?></strong> &middot; Class <?= htmlspecialchars($user['sclass']) ?></div>
    </div>
  </div>
  <a href="/newapp/logout.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
</div>

<div class="app-shell p-4">
  <div class="text-center text-muted py-5">
    <i class="fa-solid fa-hammer fa-2x mb-3"></i>
    <p>Report cards and communications are coming in a later phase of the migration.</p>
  </div>
</div>

<?php include __DIR__ . '/partials/_assets_scripts.php'; ?>
<script>
  window.APP_DATA = {
    schno: <?= json_encode((int) $user['schno']) ?>,
    sclass: <?= json_encode($user['sclass']) ?>,
    comments: <?= json_encode($comments) ?>,
    controls: <?= json_encode($controls) ?>,
  };
</script>
</body>
</html>
