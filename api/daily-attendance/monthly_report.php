<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/daily_attendance.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
$month = isset($_GET['month']) ? (int) $_GET['month'] : 0;
$year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
if ($sclass === '' || !$month || !$year) {
    json_error('sclass, month and year are required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(get_monthly_attendance_report($mysqli, $sclass, $month, $year));
