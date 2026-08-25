<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();
$user = current_user();

$tid = isset($_GET['tid']) ? (int) $_GET['tid'] : 0;
$sclass = trim($_GET['sclass'] ?? '');
if (!$tid || $sclass === '') {
    json_error('tid and sclass are required.');
}

// Staff may only look up their own subject assignments (not another teacher's).
if ($user['type'] !== 'staff' || ((int) $user['ttype'] !== 10 && (int) $user['tid'] !== $tid)) {
    json_error('You do not have permission to view this.', 403);
}

json_ok(get_teacher_subjects_for_class($mysqli, $tid, $sclass));
