<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/question_papers.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Forbidden', 403);
}

$qpid = (int) ($_POST['qpid'] ?? 0);
if (!$qpid) {
    json_error('qpid is required.');
}

$result = delete_question_paper($mysqli, $qpid, (int) $user['tid'], is_mcq_privileged($user));
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
