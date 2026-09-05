<?php
// Server-rendered, print-friendly FINAL report card for one student —
// combines marks across every term, HIC comparisons, promotion status, and
// the persisted final rank/percentage. Distinct from report_card.php (which
// is a single term/report). True server PDF generation is still deferred.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/permissions.php';
require_once __DIR__ . '/lib/final_results.php';
require_once __DIR__ . '/lib/dates.php';

require_login_page();
$user = current_user();

$sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if (!$sid || !$termid || !$report) {
    http_response_code(400);
    die('sid, termid and report are required.');
}

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

$data = get_final_report_card_data($mysqli, $studentRow['sclass'], $termid, $report);
$student = null;
foreach ($data['students'] as $s) {
    if ($s['sid'] === $sid) {
        $student = $s;
        break;
    }
}
if ($student === null) {
    die('No data found for this student.');
}

// Group schedule (all-term exam breakdown) by subject, in orderedSubjects order.
$scheduleBySubject = [];
foreach ($data['schedule'] as $row) {
    $scheduleBySubject[(int) $row['subid']][] = $row;
}
$studentMarksByTermschid = [];
foreach ($student['marks'] as $m) {
    $studentMarksByTermschid[(int) $m['termschid']] = (int) $m['marks'];
}

$grandTotal = 0;
$grandMax = 0;
foreach ($scheduleBySubject as $exams) {
    foreach ($exams as $e) {
        $grandMax += (int) $e['maxm'];
        $grandTotal += $studentMarksByTermschid[(int) $e['termschid']] ?? 0;
    }
}
$percentage = $grandMax > 0 ? round(($grandTotal / $grandMax) * 100, 2) : 0;

$finalTotal = $student['snapshot']['total'];
$promotionStatus = $finalTotal['status'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Final Report Card - <?= htmlspecialchars($student['sname']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { padding: 1.5rem; font-size: 0.85rem; }
  .rc-header { text-align: center; margin-bottom: 1rem; }
  table.rc-table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; }
  table.rc-table th, table.rc-table td { border: 1px solid #666; padding: 4px 6px; text-align: center; }
  table.rc-table thead th { background: #f0f0f0; }
  .student-info div { margin-bottom: 2px; }
  .no-print { margin-bottom: 1rem; }
  @media print { .no-print { display: none; } body { padding: 0; } }
</style>
</head>
<body>

<div class="no-print d-flex gap-2">
  <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / Save as PDF</button>
  <button class="btn btn-outline-secondary btn-sm" onclick="window.close()">Close</button>
</div>

<div class="rc-header">
  <h5 class="fw-bold mb-0">Dr. Virendra Swarup Education Centre, Avadhpuri</h5>
  <div>Final Report Card</div>
</div>

<div class="row student-info mb-3">
  <div class="col-md-6">
    <div><strong>Name:</strong> <?= htmlspecialchars($student['sname']) ?></div>
    <div><strong>Scholar No.:</strong> <?= (int) $student['schno'] ?> &nbsp; <strong>Roll No.:</strong> <?= (int) $student['roll'] ?></div>
    <div><strong>Class:</strong> <?= htmlspecialchars($student['sclass']) ?> &nbsp; <strong>House:</strong> <?= htmlspecialchars($student['house'] ?? '-') ?></div>
  </div>
  <div class="col-md-6">
    <div><strong>Father's Name:</strong> <?= htmlspecialchars($student['pname'] ?? '') ?></div>
    <div><strong>Mother's Name:</strong> <?= htmlspecialchars($student['mname'] ?? '') ?></div>
    <div><strong>Date of Birth:</strong> <?= htmlspecialchars($student['dob'] ?? '') ?></div>
  </div>
</div>

<div class="table-responsive">
<table class="rc-table">
  <thead>
    <tr><th>Subject</th><th>Exam</th><th>Max Marks</th><th>Marks Obtained</th><th>Highest in Class</th></tr>
  </thead>
  <tbody>
    <?php foreach ($data['orderedSubjects'] as $sub): $subid = (int) $sub['subid']; ?>
      <?php foreach (($scheduleBySubject[$subid] ?? []) as $i => $exam): ?>
        <tr>
          <?php if ($i === 0): ?><td rowspan="<?= count($scheduleBySubject[$subid]) ?>"><?= htmlspecialchars($sub['subname']) ?></td><?php endif; ?>
          <td><?= htmlspecialchars($exam['termname']) ?> - <?= htmlspecialchars($exam['examshort']) ?></td>
          <td><?= (int) $exam['maxm'] ?></td>
          <td><?= isset($studentMarksByTermschid[(int) $exam['termschid']]) ? $studentMarksByTermschid[(int) $exam['termschid']] : '-' ?></td>
          <?php if ($i === 0): ?><td rowspan="<?= count($scheduleBySubject[$subid]) ?>"><?= isset($data['hics']->{$subid}) ? $data['hics']->{$subid} : '-' ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <tr class="fw-bold">
      <td colspan="2">Grand Total</td>
      <td><?= $grandMax ?></td>
      <td><?= $grandTotal ?> (<?= $percentage ?>%)</td>
      <td><?= $data['grandThic'] ?: '-' ?></td>
    </tr>
  </tbody>
</table>
</div>

<div class="row mb-3">
  <div class="col-md-6">
    <?php if ($finalTotal && $finalTotal['total_marks'] !== null): ?>
      <div><strong>Final Total Marks:</strong> <?= htmlspecialchars($finalTotal['total_marks'] ?? '-') ?></div>
      <div><strong>Final Percentage:</strong> <?= htmlspecialchars($finalTotal['percentage'] ?? '-') ?>%</div>
      <div><strong>Final Rank:</strong> <?= htmlspecialchars($finalTotal['rank'] ?? 'N/A') ?></div>
    <?php else: ?>
      <div class="text-muted">Final results have not been generated for this class yet (use "Final Roster" to generate them).</div>
    <?php endif; ?>
  </div>
  <div class="col-md-6">
    <div><strong>Promotion Status:</strong> <?= htmlspecialchars($promotionStatus ?? 'Pending') ?></div>
    <?php if (!empty($student['snapshot']['attendance'])): $a = $student['snapshot']['attendance']; ?>
      <div><strong>Attendance:</strong> <?= (int) $a['attendance'] ?> / <?= (int) $a['totalattendance'] ?></div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($student['snapshot']['grades'])): ?>
<div class="mb-3">
  <strong>Co-scholastic Grades:</strong>
  <ul>
    <?php foreach ($student['snapshot']['grades'] as $g): ?>
      <li><?= htmlspecialchars($g['subname']) ?>: <?= htmlspecialchars($g['grade']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="mt-4 text-end small">
  School reopens on: <strong><?= htmlspecialchars($data['reopenText']) ?></strong>
</div>

</body>
</html>
