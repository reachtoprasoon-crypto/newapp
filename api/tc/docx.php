<?php
// Streams the Transfer Certificate for an already-issued record as a .docx.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tc.php';

require_staff_role_page([10]);

$tcid = isset($_GET['tcid']) ? (int) $_GET['tcid'] : 0;
if (!$tcid) {
    http_response_code(400);
    die('tcid is required.');
}

$tc = get_issued_tc_by_id($mysqli, $tcid);
if ($tc === null) {
    http_response_code(404);
    die('TC record not found.');
}

$phpWord = generate_tc_docx($tc);

$filename = "TC_{$tc['schno']}_" . preg_replace('/\s+/', '_', trim($tc['sname'])) . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
