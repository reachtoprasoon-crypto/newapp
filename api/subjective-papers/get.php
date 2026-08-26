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

$spid = (int) ($_GET['spid'] ?? 0);
if (!$spid) {
    json_error('spid is required.');
}

$paper = get_subjective_paper($mysqli, $spid);
if ($paper === null) {
    json_error('Paper not found.', 404);
}
if (!is_subjective_privileged($user) && $paper['tid'] !== (int) $user['tid']) {
    json_error('You do not have permission to view this paper.', 403);
}

json_ok($paper);
