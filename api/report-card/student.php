<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/report_card.php';

require_login_ajax();

$sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if (!$sid || !$termid || !$report) {
    json_error('sid, termid and report are required.');
}

$student = db_fetch_one($mysqli, "SELECT sclass FROM students WHERE sid = ?", 'i', [$sid]);
if ($student === null) {
    json_error('Student not found.');
}

// Students may only ever view their own report card (used by the student dashboard); staff go through require_class_access_ajax.
$user = current_user();
if ($user['type'] === 'student') {
    if ((int) $user['sid'] !== $sid) {
        json_error('You do not have permission to view this.', 403);
    }
} else {
    require_class_access_ajax($mysqli, $student['sclass']);
}

json_ok(get_student_report_card_data($mysqli, $sid, $termid, $report));
