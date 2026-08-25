<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/controls.php';
require_once __DIR__ . '/../../lib/attendance.php';

require_login_ajax();
$user = current_user();

$sclass = trim($_POST['sclass'] ?? '');
$termid = isset($_POST['termid']) ? (int) $_POST['termid'] : 0;
$report = isset($_POST['report']) ? (int) $_POST['report'] : 0;
$totalAttendance = isset($_POST['totalAttendance']) ? (int) $_POST['totalAttendance'] : 0;
$students = json_decode($_POST['students'] ?? '[]', true);
if ($sclass === '' || !$termid || !$report || !is_array($students)) {
    json_error('sclass, termid, report and students are required.');
}

require_class_access_ajax($mysqli, $sclass);

$controls = get_all_controls($mysqli);
if (!is_feeding_allowed_for_class($controls, (int) $user['ttype'], $sclass)) {
    json_error('Attendance feeding is currently disabled for this class by the administrator.', 403);
}

$attendanceData = [];
foreach ($students as $s) {
    if (!isset($s['sid'])) {
        json_error('Each row must include sid.');
    }
    $attendanceData[] = [
        'sid' => (int) $s['sid'],
        'attendance' => ($s['attendance'] === '' || $s['attendance'] === null) ? null : (int) $s['attendance'],
        'totalattendance' => $totalAttendance,
        'comid' => ($s['comid'] === '' || $s['comid'] === null) ? null : (int) $s['comid'],
    ];
}

$result = upsert_attendance($mysqli, $sclass, $termid, $report, $attendanceData);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
