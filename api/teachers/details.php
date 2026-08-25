<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/teachers.php';

require_login_ajax();
$user = current_user();

$tid = isset($_GET['tid']) ? (int) $_GET['tid'] : 0;
if (!$tid) {
    json_error('tid is required.');
}

$isSelf = $user['type'] === 'staff' && (int) $user['tid'] === $tid;
$isAdmin = $user['type'] === 'staff' && (int) $user['ttype'] === 10;
if (!$isAdmin && !$isSelf) {
    json_error('You do not have permission to view this record.', 403);
}

$teacher = get_teacher_details($mysqli, $tid);
if ($teacher === null) {
    json_error('Teacher not found.', 404);
}
json_ok($teacher);
