<?php
// Public endpoint (no login) — backs the /attendance.php?sclass=X kiosk page.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/daily_attendance.php';

$sclass = trim($_POST['sclass'] ?? '');
$schno = isset($_POST['schno']) ? (int) $_POST['schno'] : 0;
$dob = trim($_POST['dob'] ?? '');

if ($sclass === '' || !$schno || $dob === '') {
    json_error('Scholar number and date of birth are required.');
}

$result = self_mark_attendance($mysqli, $sclass, $schno, $dob);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
