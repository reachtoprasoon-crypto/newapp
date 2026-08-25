<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/reference.php';

require_login_ajax();

$tid = isset($_GET['tid']) ? (int) $_GET['tid'] : null;
if (!$tid) {
    json_error('tid is required.');
}

json_ok(get_teacher_classes($mysqli, $tid));
