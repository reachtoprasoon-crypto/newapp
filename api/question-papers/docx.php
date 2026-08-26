<?php
// Streams an MCQ question paper as a .docx. Page-context guard (plain GET,
// navigated to directly) rather than an AJAX JSON endpoint, matching
// api/tc/docx.php's convention.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/question_papers.php';

require_login_page();
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    die('Forbidden');
}

$qpid = (int) ($_GET['qpid'] ?? 0);
if (!$qpid) {
    http_response_code(400);
    die('qpid is required.');
}

$paper = get_question_paper($mysqli, $qpid);
if ($paper === null) {
    http_response_code(404);
    die('Paper not found.');
}
if (!is_mcq_privileged($user) && $paper['tid'] !== (int) $user['tid']) {
    http_response_code(403);
    die('You do not have permission to download this paper.');
}

[$phpWord, $mathMap] = generate_question_paper_docx($paper);
$filename = 'MCQ_' . preg_replace('/\s+/', '_', $paper['subname']) . '_' . preg_replace('/\s+/', '_', $paper['title']) . '.docx';
paper_stream_docx($phpWord, $mathMap, $filename);
