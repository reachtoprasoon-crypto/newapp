<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_staff_role_ajax([10, 5]);

$required = ['schno', 'roll', 'sname', 'dob', 'sclass', 'hid', 'ht', 'wt'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        json_error("Missing required field: $field");
    }
}

$input = [
    'schno' => (int) $_POST['schno'],
    'roll' => (int) $_POST['roll'],
    'sname' => trim($_POST['sname']),
    'pname' => trim($_POST['pname'] ?? ''),
    'mname' => trim($_POST['mname'] ?? ''),
    'dob' => trim($_POST['dob']),
    'sclass' => trim($_POST['sclass']),
    'branch' => trim($_POST['branch'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'hid' => (int) $_POST['hid'],
    'ht' => (int) $_POST['ht'],
    'wt' => (int) $_POST['wt'],
    'photo' => $_POST['photo'] ?? '',
];

$result = add_student($mysqli, $input);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
