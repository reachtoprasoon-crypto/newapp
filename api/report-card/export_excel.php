<?php
// Generates and streams a per-student Excel workbook (one worksheet per
// student) for a class/term/report, matching term-report-card-excel.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/students.php';
require_once __DIR__ . '/../../lib/attendance.php';
require_once __DIR__ . '/../../lib/grades.php';
require_once __DIR__ . '/../../lib/term_schedule.php';
require_once __DIR__ . '/../../lib/report_card.php';
require_once __DIR__ . '/../../lib/final_results.php';
require_once __DIR__ . '/../../lib/controls.php';
require_once __DIR__ . '/../../lib/report_card_excel.php';

require_login_page();
set_time_limit(300);

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$studentIds = isset($_GET['sids']) ? array_map('intval', explode(',', $_GET['sids'])) : [];
$customTermLabel = trim($_GET['label'] ?? '');
$includeSchool = ($_GET['includeSchool'] ?? '1') === '1';
$includeBranch = ($_GET['includeBranch'] ?? '1') === '1';
$includeWatermark = ($_GET['includeWatermark'] ?? '1') === '1';
$includeSignatures = ($_GET['includeSignatures'] ?? '1') === '1';

if ($sclass === '' || !$termid || !$report || empty($studentIds)) {
    http_response_code(400);
    die('sclass, termid, report and at least one student are required.');
}

require_class_access_page($mysqli, $sclass);

$termRow = db_fetch_one($mysqli, "SELECT termname FROM terms WHERE termid = ?", 'i', [$termid]);
$termName = $termRow ? $termRow['termname'] : "Term $termid";

$reportData = get_report_card_data($mysqli, $sclass, $termid, $report);
if (empty($reportData['studentData'])) {
    http_response_code(404);
    die('No academic records found for this selection.');
}

$targetStudentData = array_values(array_filter($reportData['studentData'], fn($s) => in_array($s['sid'], $studentIds, true)));
if (empty($targetStudentData)) {
    http_response_code(404);
    die('None of the selected students have data for this selection.');
}

$attendanceData = get_attendance_for_class($mysqli, $sclass, $termid, $report);
$hicData = get_hic_data($mysqli, $sclass, $termid, $report);
$classStudents = get_students_by_class($mysqli, $sclass);
$gradeSubjects = get_scheduled_graded_subjects_for_class($mysqli, $sclass);
$comments = db_fetch_all($mysqli, "SELECT comid, comment FROM comments");

$studentGradesMap = [];
foreach ($studentIds as $sid) {
    $studentGradesMap[$sid] = [];
}
foreach ($gradeSubjects as $gs) {
    $gradesForSubject = get_grades_for_subject($mysqli, $sclass, $gs['subid'], $termid, $report);
    foreach ($gradesForSubject as $gr) {
        if (isset($studentGradesMap[$gr['sid']]) && !empty($gr['grade'])) {
            $studentGradesMap[$gr['sid']][] = ['subname' => $gs['subname'], 'grade' => $gr['grade']];
        }
    }
}

$controls = get_all_controls($mysqli);
$watermarkControl = null;
foreach ($controls as $c) {
    if ($c['control'] === 'Report Watermark') { $watermarkControl = $c; break; }
}
$watermarkBase64 = $watermarkControl['cdata'] ?? null;
$watermarkSize = $watermarkControl ? (int) $watermarkControl['cval'] : 350;

$spreadsheet = generate_term_report_card_excel([
    'header' => $reportData['header'],
    'students' => $targetStudentData,
    'gradeSubjects' => $gradeSubjects,
    'studentGrades' => $studentGradesMap,
    'classStudents' => $classStudents,
    'attendanceData' => $attendanceData,
    'hicData' => $hicData,
    'comments' => $comments,
    'selectedClass' => $sclass,
    'selectedTermName' => $termName,
    'customTermLabel' => $customTermLabel ?: null,
    'watermarkBase64' => $watermarkBase64,
    'watermarkSize' => $watermarkSize,
    'headerConfig' => [
        'includeSchool' => $includeSchool,
        'includeBranch' => $includeBranch,
        'includeWatermark' => $includeWatermark,
        'includeSignatures' => $includeSignatures,
    ],
]);

$filename = "Term_Reports_{$sclass}_Report{$report}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
