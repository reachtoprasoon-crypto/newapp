<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/marks.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
$termschid = isset($_GET['termschid']) ? (int) $_GET['termschid'] : 0;
if ($sclass === '' || !$termschid) {
    json_error('sclass and termschid are required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(get_students_with_marks($mysqli, $sclass, $termschid));
