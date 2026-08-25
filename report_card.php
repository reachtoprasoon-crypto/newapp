<?php
// Server-rendered, print-friendly report card (class-wide or single student).
// True server-generated PDF (Dompdf) is deferred to a later phase; for now
// this relies on the browser's own print-to-PDF, per the Phase 4 plan.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/permissions.php';
require_once __DIR__ . '/lib/report_card.php';

require_login_page();
$user = current_user();

$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$sclass = trim($_GET['sclass'] ?? '');
$sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;

if (!$termid || !$report || (!$sclass && !$sid)) {
    http_response_code(400);
    die('termid, report, and either sclass or sid are required.');
}

if ($sid) {
    $studentRow = db_fetch_one($mysqli, "SELECT sclass FROM students WHERE sid = ?", 'i', [$sid]);
    if ($studentRow === null) {
        die('Student not found.');
    }
    if ($user['type'] === 'student') {
        if ((int) $user['sid'] !== $sid) {
            http_response_code(403);
            die('Forbidden.');
        }
    } else {
        require_class_access_page($mysqli, $studentRow['sclass']);
    }
    $data = get_student_report_card_data($mysqli, $sid, $termid, $report);
    $sclass = $studentRow['sclass'];
} else {
    if ($user['type'] === 'student') {
        http_response_code(403);
        die('Forbidden.');
    }
    require_class_access_page($mysqli, $sclass);
    $data = get_report_card_data($mysqli, $sclass, $termid, $report);
}

$termRow = db_fetch_one($mysqli, "SELECT termname FROM terms WHERE termid = ?", 'i', [$termid]);
$termName = $termRow ? $termRow['termname'] : "Term $termid";

$header = $data['header'];
$studentData = $data['studentData'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Report Card - Class <?= htmlspecialchars($sclass) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { padding: 1.5rem; font-size: 0.85rem; }
  .rc-header { text-align: center; margin-bottom: 1.5rem; }
  table.rc-table { border-collapse: collapse; width: 100%; }
  table.rc-table th, table.rc-table td { border: 1px solid #666; padding: 4px 6px; text-align: center; }
  table.rc-table thead th { background: #f0f0f0; }
  .no-print { margin-bottom: 1rem; }
  @media print {
    .no-print { display: none; }
    body { padding: 0; }
  }
</style>
</head>
<body>

<div class="no-print d-flex gap-2">
  <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
  <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
</div>

<div class="rc-header">
  <h5 class="fw-bold mb-0">Dr. Virendra Swarup Education Centre, Avadhpuri</h5>
  <div>Report Card &mdash; Class <?= htmlspecialchars($sclass) ?> &mdash; <?= htmlspecialchars($termName) ?> (Report <?= (int) $report ?>)</div>
</div>

<?php if (empty($header) || empty($studentData)): ?>
  <p class="text-center text-muted">No schedule or marks data available for this selection.</p>
<?php else: ?>
<div class="table-responsive">
<table class="rc-table">
  <thead>
    <tr>
      <th rowspan="2">Roll</th>
      <th rowspan="2">Name</th>
      <?php foreach ($header as $h): ?>
        <th colspan="<?= (int) $h['colSpan'] ?>"><?= htmlspecialchars($h['label']) ?></th>
      <?php endforeach; ?>
    </tr>
    <tr>
      <?php foreach ($header as $h): foreach ($h['subHeaders'] as $sh): ?>
        <th><?= htmlspecialchars($sh['label']) ?><br><small>(<?= (int) $sh['maxm'] ?>)</small></th>
      <?php endforeach; endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($studentData as $row): ?>
      <tr>
        <td><?= (int) $row['roll'] ?></td>
        <td class="text-start"><?= htmlspecialchars($row['sname']) ?></td>
        <?php foreach ($header as $h): foreach ($h['subHeaders'] as $sh): ?>
          <td><?= $row[$sh['key']] === null ? '-' : htmlspecialchars($row[$sh['key']]) ?></td>
        <?php endforeach; endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

</body>
</html>
