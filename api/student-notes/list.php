<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/student_notes.php';

require_login_ajax();
if (current_user()['type'] !== 'staff') {
    json_error('Only staff can view student notes.', 403);
}

$sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
if (!$sid) {
    json_error('sid is required.');
}

json_ok(get_student_notes($mysqli, $sid));
