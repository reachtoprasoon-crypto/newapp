<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/communications.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can delete communications.', 403);
}

$commid = isset($_POST['commid']) ? (int) $_POST['commid'] : 0;
if (!$commid) {
    json_error('commid is required.');
}

$result = delete_communication($mysqli, $commid, (int) $user['tid']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
