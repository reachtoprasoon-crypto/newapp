<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/teachers.php';

require_staff_role_ajax([10]);

$tid = isset($_POST['tid']) ? (int) $_POST['tid'] : 0;
$sclass = trim($_POST['sclass'] ?? '');

if (!$tid || $sclass === '') {
    json_error('tid and sclass are required.');
}

$result = assign_class_teacher($mysqli, $tid, $sclass);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
