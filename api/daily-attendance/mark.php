<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/daily_attendance.php';

require_login_ajax();

$sid = isset($_POST['sid']) ? (int) $_POST['sid'] : 0;
$isPresent = ($_POST['isPresent'] ?? '') === '1';
$date = trim($_POST['date'] ?? '') ?: null;
if (!$sid) {
    json_error('sid is required.');
}

$student = db_fetch_one($mysqli, "SELECT sclass FROM students WHERE sid = ?", 'i', [$sid]);
if ($student === null) {
    json_error('Student not found.');
}
require_class_access_ajax($mysqli, $student['sclass']);

json_ok(mark_student_attendance($mysqli, $sid, $isPresent, $date));
