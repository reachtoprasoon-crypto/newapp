<?php
// Exports the SAME data get_final_roster_data() already computed for the
// on-screen roster (does not recompute — call /api/final-results/roster.php
// first if the on-screen data may be stale).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';
require_once __DIR__ . '/../../lib/roster_excel.php';

require_login_page();
set_time_limit(300);
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    die('Forbidden.');
}

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    http_response_code(400);
    die('sclass is required.');
}

require_class_access_page($mysqli, $sclass);

$roster = get_final_roster_data($mysqli, $sclass);
if (empty($roster['studentData'])) {
    http_response_code(404);
    die('No final roster data available for this class.');
}

$spreadsheet = generate_final_roster_excel($roster, $sclass);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Final_Roster_Class_' . $sclass . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
