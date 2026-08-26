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

$qpid = (int) ($_GET['qpid'] ?? 0);
if (!$qpid) {
    json_error('qpid is required.');
}

$paper = get_question_paper($mysqli, $qpid);
if ($paper === null) {
    json_error('Paper not found.', 404);
}
if (!is_mcq_privileged($user) && $paper['tid'] !== (int) $user['tid']) {
    json_error('You do not have permission to view this paper.', 403);
}

json_ok($paper);
