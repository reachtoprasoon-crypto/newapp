<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_staff_role_ajax([10]);

$sid = isset($_POST['sid']) ? (int) $_POST['sid'] : 0;
if (!$sid) {
    json_error('sid is required.');
}

$result = delete_student($mysqli, $sid);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
