<?php
// Generates and streams a per-student Excel workbook (one worksheet per
// student) for FINAL (all-terms-combined) report cards, matching
// final-report-card-excel.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';
require_once __DIR__ . '/../../lib/controls.php';
require_once __DIR__ . '/../../lib/report_card_excel.php';

require_login_page();
set_time_limit(300);

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$studentIds = isset($_GET['sids']) ? array_map('intval', explode(',', $_GET['sids'])) : [];
$includeSchool = ($_GET['includeSchool'] ?? '1') === '1';
$includeBranch = ($_GET['includeBranch'] ?? '1') === '1';
$includeWatermark = ($_GET['includeWatermark'] ?? '1') === '1';
$includeSignatures = ($_GET['includeSignatures'] ?? '1') === '1';

if ($sclass === '' || !$termid || !$report || empty($studentIds)) {
    http_response_code(400);
    die('sclass, termid, report and at least one student are required.');
}

require_class_access_page($mysqli, $sclass);

$data = get_final_report_card_data($mysqli, $sclass, $termid, $report);
if (empty($data['students'])) {
    http_response_code(404);
    die('No academic records found for this class.');
}

$targetStudentData = array_values(array_filter($data['students'], fn($s) => in_array($s['sid'], $studentIds, true)));
if (empty($targetStudentData)) {
    http_response_code(404);
    die('None of the selected students have data for this selection.');
}

$comments = db_fetch_all($mysqli, "SELECT comid, comment FROM comments");

$controls = get_all_controls($mysqli);
$watermarkControl = null;
foreach ($controls as $c) {
    if ($c['control'] === 'Report Watermark') { $watermarkControl = $c; break; }
}
$watermarkBase64 = $watermarkControl['cdata'] ?? null;
$watermarkSize = $watermarkControl ? (int) $watermarkControl['cval'] : 350;

$spreadsheet = generate_final_report_card_excel([
    'students' => $targetStudentData,
    'schedule' => $data['schedule'],
    'orderedSubjects' => $data['orderedSubjects'],
    'hics' => $data['hics'],
    'grandThic' => $data['grandThic'],
    'reopenText' => $data['reopenText'],
    'comments' => $comments,
    'watermarkBase64' => $watermarkBase64,
    'watermarkSize' => $watermarkSize,
    'headerConfig' => [
        'includeSchool' => $includeSchool,
        'includeBranch' => $includeBranch,
        'includeWatermark' => $includeWatermark,
        'includeSignatures' => $includeSignatures,
    ],
]);

$filename = "Final_Reports_{$sclass}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
