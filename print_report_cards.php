<?php
// Direct-print HTML variant of the per-student report card (one page per
// student, auto-triggers the browser print dialog). Ports the handlePrint()
// path in generate-report-cards.tsx — a simpler, non-Excel sibling of
// api/report-card/export_excel.php built from the same underlying data.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/permissions.php';
require_once __DIR__ . '/lib/students.php';
require_once __DIR__ . '/lib/attendance.php';
require_once __DIR__ . '/lib/term_schedule.php';
require_once __DIR__ . '/lib/report_card.php';
require_once __DIR__ . '/lib/final_results.php';

require_login_page();

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$studentIds = isset($_GET['sids']) ? array_map('intval', explode(',', $_GET['sids'])) : [];
$customTermLabel = trim($_GET['label'] ?? '');
$includeSchool = ($_GET['includeSchool'] ?? '1') === '1';
$includeBranch = ($_GET['includeBranch'] ?? '1') === '1';

if ($sclass === '' || !$termid || !$report || empty($studentIds)) {
    http_response_code(400);
    die('sclass, termid, report and at least one student are required.');
}

require_class_access_page($mysqli, $sclass);

$termRow = db_fetch_one($mysqli, "SELECT termname FROM terms WHERE termid = ?", 'i', [$termid]);
$termName = $termRow ? $termRow['termname'] : "Term $termid";

$reportData = get_report_card_data($mysqli, $sclass, $termid, $report);
$targetStudentData = array_values(array_filter($reportData['studentData'], fn($s) => in_array($s['sid'], $studentIds, true)));

$attendanceData = get_attendance_for_class($mysqli, $sclass, $termid, $report);
$hicData = get_hic_data($mysqli, $sclass, $termid, $report);
$classStudents = get_students_by_class($mysqli, $sclass);
$gradeSubjects = get_scheduled_graded_subjects_for_class($mysqli, $sclass);
$comments = db_fetch_all($mysqli, "SELECT comid, comment FROM comments");

function prc_find($arr, $key, $val) {
    foreach ($arr as $row) {
        if ((isset($row[$key]) ? (int) $row[$key] : null) === (int) $val) return $row;
    }
    return null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Report Cards - <?= htmlspecialchars($sclass) ?></title>
<style>
  @page { size: letter landscape; margin: 0.2in; }
  body { font-family: 'Calibri', 'Arial', sans-serif; margin: 0; padding: 0; color: black; }
  .report-card { width: 100%; height: 100%; page-break-after: always; position: relative; padding: 20px; box-sizing: border-box; }
  .report-card:last-child { page-break-after: avoid; }
  .theme-text { color: #095889; font-weight: bold; }
  .header-row { text-align: center; margin-bottom: 10px; }
  .grid-table { width: 100%; border-collapse: collapse; border: 1px solid black; }
  .grid-table td { border: 1px solid black; padding: 4px; text-align: center; height: 28px; font-size: 12pt; }
  .header-info td { font-size: 13pt !important; height: 32px; font-weight: bold; }
  .label-cell { color: #095889; font-weight: bold; }
  .data-cell { color: black; font-weight: normal; }
  .grade-sidebar { width: 100%; font-size: 11pt !important; border-collapse: collapse; }
  .grade-sidebar td { height: 28px; border: 1px solid black; border-left: none; border-right: none; }
  .total-row td { font-weight: bold; font-size: 12pt; border-top: 2px solid black; }
</style>
</head>
<body>
<?php foreach ($targetStudentData as $studentData):
    $sid = $studentData['sid'];
    $details = prc_find($classStudents, 'sid', $sid);
    $attendance = prc_find($attendanceData, 'sid', $sid);
    $comment = null;
    if ($attendance && $attendance['comid'] !== null) {
        $comment = prc_find($comments, 'comid', $attendance['comid']);
    }

    $activeSubs = array_values(array_filter($reportData['header'], function ($h) use ($studentData) {
        if ($h['label'] === 'Grand Total' || $h['label'] === 'Percentage' || !isset($h['subid'])) return false;
        $shKey = null;
        foreach ($h['subHeaders'] as $sh) {
            if (str_starts_with($sh['key'], 'total_')) { $shKey = $sh['key']; break; }
        }
        return $shKey && ($studentData[$shKey] ?? null) !== null;
    }));

    $gMax = 0;
    foreach ($activeSubs as $sub) {
        foreach ($sub['subHeaders'] as $sh) {
            if (str_starts_with($sh['key'], 'mark_')) $gMax += ($sh['maxm'] ?? 0);
        }
    }
    $gObt = 0;
    foreach ($activeSubs as $sub) {
        $gObt += (float) ($studentData['total_' . $sub['subid']] ?? 0);
    }
?>
<div class="report-card">
  <div class="header-row">
    <div class="theme-text" style="font-size: 24pt;"><?= $includeSchool ? 'DR. VIRENDRA SWARUP EDUCATION CENTRE' : '' ?></div>
    <div class="theme-text" style="font-size: 20pt;"><?= $includeBranch ? 'AVADHPURI, KANPUR' : '' ?></div>
  </div>

  <table class="grid-table header-info">
    <tr>
      <td colspan="4" class="label-cell">SCHOLAR NO.</td>
      <td colspan="16" class="label-cell">NAME</td>
      <td colspan="5" class="label-cell">CLASS</td>
    </tr>
    <tr>
      <td colspan="4" class="data-cell"><?= htmlspecialchars($details['schno'] ?? '') ?></td>
      <td colspan="16" class="data-cell"><?= htmlspecialchars($details['sname'] ?? '') ?></td>
      <td colspan="5" class="data-cell"><?= htmlspecialchars($sclass) ?></td>
    </tr>
    <tr>
      <td colspan="4" class="label-cell">UNIT/TERM &amp; YEAR</td>
      <td colspan="4" class="label-cell">ATTENDANCE</td>
      <td colspan="4" class="label-cell">D.O.B.</td>
      <td colspan="4" class="label-cell">HOUSE</td>
      <td colspan="4" class="label-cell">WEIGHT</td>
      <td colspan="5" class="label-cell">HEIGHT</td>
    </tr>
    <tr>
      <td colspan="4" class="data-cell"><?= htmlspecialchars($customTermLabel ?: $termName) ?></td>
      <td colspan="4" class="data-cell"><?= (int) ($attendance['attendance'] ?? 0) ?>/<?= (int) ($attendance['totalattendance'] ?? 0) ?></td>
      <td colspan="4" class="data-cell"><?= htmlspecialchars($details['dob'] ?? '') ?></td>
      <td colspan="4" class="data-cell"><?= htmlspecialchars($details['house'] ?? 'N/A') ?></td>
      <td colspan="4" class="data-cell"><?= htmlspecialchars($details['wt'] ?? '') ?> Kg.</td>
      <td colspan="5" class="data-cell"><?= htmlspecialchars($details['ht'] ?? '') ?> Cm.</td>
    </tr>
  </table>

  <div style="margin-top: 10px; display: flex; gap: 0;">
    <div style="flex: 80%;">
      <table class="grid-table">
        <tr class="label-cell">
          <td colspan="5">SUBJECTS</td>
          <td colspan="5">MM</td>
          <td colspan="5">EXAM</td>
          <td colspan="5">HIC</td>
        </tr>
        <?php foreach ($activeSubs as $sub):
            $subid = $sub['subid'];
            $sMax = 0;
            foreach ($sub['subHeaders'] as $sh) { if (str_starts_with($sh['key'], 'mark_')) $sMax += ($sh['maxm'] ?? 0); }
            $sObt = $studentData['total_' . $subid] ?? '';
        ?>
        <tr>
          <td colspan="5" style="text-align:left; padding-left:10px;" class="theme-text"><?= htmlspecialchars($sub['label']) ?></td>
          <td colspan="5"><?= $sMax ?></td>
          <td colspan="5"><?= htmlspecialchars($sObt) ?></td>
          <td colspan="5"><?= htmlspecialchars($hicData['subjectHics']->{$subid} ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php for ($i = 0; $i < max(0, 12 - count($activeSubs)); $i++): ?>
        <tr><td colspan="5"></td><td colspan="5"></td><td colspan="5"></td><td colspan="5"></td></tr>
        <?php endfor; ?>
        <tr class="total-row">
          <td colspan="5" style="text-align:right; padding-right:10px;" class="theme-text">TOTAL</td>
          <td colspan="5"><?= $gMax ?></td>
          <td colspan="5"><?= $gObt ?></td>
          <td colspan="5"><?= htmlspecialchars($hicData['termHic'] ?? '') ?></td>
        </tr>
      </table>
    </div>
    <div style="flex: 20%; border: 1px solid black; border-left: none;">
      <table class="grade-sidebar">
        <tr class="label-cell"><td colspan="2">PERCENTAGE</td></tr>
        <tr style="height:32px;"><td colspan="2" style="font-weight:bold; border-bottom:1px solid black; text-align:center;"><?= htmlspecialchars($studentData['percentage']) ?>%</td></tr>
        <tr class="label-cell"><td colspan="2">GRADE SUBJECTS</td></tr>
        <?php foreach ($gradeSubjects as $gs): ?>
        <tr><td style="text-align:left; padding-left:5px; border-right:none;"><?= htmlspecialchars($gs['subname']) ?></td><td style="text-align:center; border-left:none;"></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div style="margin-top:10px; font-size:11pt;">
    <strong>COMMENT:</strong> <?= htmlspecialchars($comment['comment'] ?? '') ?>
  </div>
</div>
<?php endforeach; ?>

<script>
  window.onload = function () { window.print(); };
</script>
</body>
</html>
