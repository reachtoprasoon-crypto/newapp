<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/teachers.php';

require_staff_role_ajax([10]);

$tname = trim($_POST['tname'] ?? '');
$tuser = trim($_POST['tuser'] ?? '');
$tpass = $_POST['tpass'] ?? '';
$dob = trim($_POST['dob'] ?? ''); // 'dd/MM/yyyy', optional

if ($tname === '' || $tuser === '' || $tpass === '') {
    json_error('Name, username and password are required.');
}

$result = add_teacher($mysqli, $tname, $tuser, $tpass, $dob);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
