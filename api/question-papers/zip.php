<?php
// Streams a zip of every question/option image in an MCQ paper. Page-context
// guard (plain GET, navigated to directly), same convention as docx.php.
// Privileged-only (Admin/Principal): this is a bulk print/scan export, not
// something a subject teacher needs for their own paper.

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

$tmpFile = build_question_paper_zip_path($paper);
$filename = 'MCQ_' . preg_replace('/\s+/', '_', $paper['subname']) . '_' . preg_replace('/\s+/', '_', $paper['title']) . '_Images.zip';
paper_stream_zip_file($tmpFile, $filename);
