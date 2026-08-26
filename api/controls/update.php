<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/controls.php';

require_staff_role_ajax([10]);
$user = current_user();

$conid = (int) ($_POST['conid'] ?? 0);
if (!$conid) {
    json_error('conid is required.');
}

$fields = [];
if (isset($_POST['allowed'])) {
    $fields['allowed'] = $_POST['allowed'] === '1' || $_POST['allowed'] === 'true';
}
if (isset($_POST['cval']) && $_POST['cval'] !== '') {
    $fields['cval'] = (int) $_POST['cval'];
}
if (isset($_POST['cdata'])) {
    $fields['cdata'] = $_POST['cdata'];
}

if (count($fields) === 0) {
    json_error('At least one of allowed/cval/cdata is required.');
}

$result = update_control($mysqli, $conid, $fields, $user['tname']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
