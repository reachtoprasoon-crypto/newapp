<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/subjective_papers.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Forbidden', 403);
}

$spid = (int) ($_POST['spid'] ?? 0);
if (!$spid) {
    json_error('spid is required.');
}

$result = delete_subjective_paper($mysqli, $spid, (int) $user['tid']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
