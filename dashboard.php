<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/reference.php';
require_once __DIR__ . '/lib/controls.php';
require_once __DIR__ . '/lib/nav.php';
require_once __DIR__ . '/lib/theme.php';

require_login_page();
$user = current_user();

if ($user['type'] === 'student') {
    header('Location: /newapp/student_dashboard.php');
    exit;
}

$ttype = (int) $user['ttype'];

// Pre-fetch reference data once per page load, mirroring the source app's
// Promise.all in src/app/page.tsx::fetchDashboardData.
$classes = get_all_classes($mysqli);
$subjects = get_all_subjects($mysqli);
$teachers = get_all_teachers($mysqli);
$allTeacherDetails = search_teachers($mysqli, '');
$controls = get_all_controls($mysqli);
$houses = get_all_houses($mysqli);
$terms = get_all_terms($mysqli);
$exams = get_all_exams($mysqli);
$teacherClasses = get_teacher_classes($mysqli, $user['tid']);
$comments = get_all_comments($mysqli);

// Role label + nav tabs. Extend $navTabs as later phases add modules —
// unbuilt slugs render a "coming soon" placeholder via partials/load.php.
$roleLabels = [10 => 'Administrator', 6 => 'Principal', 5 => 'Office', 1 => 'Class Teacher'];
$roleLabel = $roleLabels[$ttype] ?? 'Teacher';

$navTabs = get_nav_tabs_for_role($ttype);
$defaultThemeCval = 0;
foreach ($controls as $c) {
    if ($c['control'] === 'Default Theme') {
        $defaultThemeCval = (int) $c['cval'];
        break;
    }
}
$themeMode = $defaultThemeCval === 1 ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $themeMode ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Dr. Virendra Swarup Education Centre, Avadhpuri</title>
<?php include __DIR__ . '/partials/_assets_head.php'; ?>
</head>
<body>

<div id="loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>

<div class="app-header">
  <div class="d-flex align-items-center gap-3">
    <img src="/newapp/assets/images/logo.gif" width="40" height="40" class="rounded-circle" alt="School Logo">
    <div>
      <div class="school-name">Dr. Virendra Swarup Education Centre, Avadhpuri</div>
      <div class="welcome-line">Welcome, <strong><?= htmlspecialchars($user['tname']) ?></strong> &middot; <?= htmlspecialchars($roleLabel) ?></div>
    </div>
  </div>
  <a href="/newapp/logout.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i>Logout</a>
</div>

<div class="app-shell">
  <nav class="navbar navbar-expand-lg navbar-light app-nav px-3 pt-3">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardNavCollapse" aria-controls="dashboardNavCollapse" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span> <span class="align-middle">Menu</span>
    </button>
    <div class="collapse navbar-collapse" id="dashboardNavCollapse">
      <ul class="nav nav-tabs flex-column flex-lg-row w-100" id="dashboardTabs">
        <?php foreach ($navTabs as $i => $tab): ?>
          <li class="nav-item">
            <a class="nav-link <?= $i === 0 ? 'active' : '' ?>" href="#" data-slug="<?= htmlspecialchars($tab['slug']) ?>">
              <i class="fa-solid <?= htmlspecialchars($tab['icon']) ?> me-1"></i><?= htmlspecialchars($tab['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
  <div class="p-3" id="tabContent">
    <div class="tab-pane-loading">Loading...</div>
  </div>
</div>

<?php include __DIR__ . '/partials/_assets_scripts.php'; ?>
<script>
  window.APP_DATA = {
    ttype: <?= json_encode($ttype) ?>,
    tid: <?= json_encode((int) $user['tid']) ?>,
    sclass: <?= json_encode($user['sclass']) ?>,
    classes: <?= json_encode($classes) ?>,
    subjects: <?= json_encode($subjects) ?>,
    teachers: <?= json_encode($teachers) ?>,
    allTeacherDetails: <?= json_encode($allTeacherDetails) ?>,
    controls: <?= json_encode($controls) ?>,
    houses: <?= json_encode($houses) ?>,
    terms: <?= json_encode($terms) ?>,
    exams: <?= json_encode($exams) ?>,
    teacherClasses: <?= json_encode($teacherClasses) ?>,
    comments: <?= json_encode($comments) ?>,
  };
</script>
<script src="/newapp/assets/js/dashboard.js"></script>
</body>
</html>
