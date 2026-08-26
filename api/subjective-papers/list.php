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

$isPrivileged = is_subjective_privileged($user);
json_ok([
    'papers' => get_subjective_papers($mysqli, (int) $user['tid'], $isPrivileged),
    'isPrivileged' => $isPrivileged,
]);
