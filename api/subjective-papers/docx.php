<?php
// Streams a subjective paper as a .docx. Page-context guard (plain GET,
// navigated to directly) rather than an AJAX JSON endpoint, matching
// api/tc/docx.php's convention.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/subjective_papers.php';

require_login_page();
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    die('Forbidden');
}

$spid = (int) ($_GET['spid'] ?? 0);
if (!$spid) {
    http_response_code(400);
    die('spid is required.');
}

$paper = get_subjective_paper($mysqli, $spid);
if ($paper === null) {
    http_response_code(404);
    die('Paper not found.');
}
if (!is_subjective_privileged($user) && $paper['tid'] !== (int) $user['tid']) {
    http_response_code(403);
    die('You do not have permission to download this paper.');
}

[$phpWord, $mathMap] = generate_subjective_paper_docx($paper);
$filename = 'Subjective_' . preg_replace('/\s+/', '_', $paper['subname']) . '_' . preg_replace('/\s+/', '_', $paper['sclass']) . '.docx';
paper_stream_docx($phpWord, $mathMap, $filename);
