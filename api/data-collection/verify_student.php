<?php
// Public (no login) — identity verification step of the /collect.php kiosk.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/students.php';

$schno = isset($_POST['schno']) ? (int) $_POST['schno'] : 0;
$dob = trim($_POST['dob'] ?? '');
if (!$schno || $dob === '') {
    json_error('Scholar number and date of birth are required.');
}

$result = verify_student_credentials($mysqli, $schno, $dob);
if (!$result['isValid']) {
    json_error($result['error']);
}
json_ok($result['student']);
