<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/marks.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
$subid = isset($_GET['subid']) ? (int) $_GET['subid'] : 0;
if ($sclass === '' || !$subid) {
    json_error('sclass and subid are required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(get_view_fed_marks_summary($mysqli, $sclass, $subid));
