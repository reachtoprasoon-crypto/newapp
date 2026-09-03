<?php
// Streams the "Positional" answers CSV for an MCQ paper (2 sets, page
// rotation, per-question marks). Page-context guard, privileged-only —
// see zip.php.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/question_papers.php';

require_login_page();
$user = current_user();
if (!is_mcq_privileged($user)) {
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

$rows = build_question_paper_answers_csv_rows($paper);
$filename = 'Answers_' . preg_replace('/\s+/', '_', $paper['subshort']) . '_Positional.csv';
paper_stream_csv_rows($rows, $filename);
