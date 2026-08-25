<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/reporting.php';
require_once __DIR__ . '/../../lib/roster_excel.php';

require_login_page();
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    die('Forbidden.');
}

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if ($sclass === '' || !$termid || !$report) {
    http_response_code(400);
    die('sclass, termid and report are required.');
}

require_class_access_page($mysqli, $sclass);

$roster = get_class_roster_data($mysqli, $sclass, $termid, $report);
if ($roster === null) {
    http_response_code(404);
    die('No marks data found for this class and term.');
}

$spreadsheet = generate_class_roster_excel($roster, $sclass);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Roster_' . $sclass . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
