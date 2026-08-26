<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db_config.php';

require_staff_role_ajax([10]);
$user = current_user();

$host = trim($_POST['host'] ?? '');
$dbUser = trim($_POST['user'] ?? '');
$password = $_POST['password'] ?? '';
$database = trim($_POST['database'] ?? '');

if ($host === '' || $dbUser === '' || $database === '') {
    json_error('Host, user, and database name are required.');
}

$result = update_db_config($mysqli, $host, $dbUser, $password, $database, $user['tname']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
