<?php
// Recomputes HIC (highest-in-class, per subject and term total) for a
// class/term/report from the current marks data. Self-contained: builds the
// students+marks+grandTotal input from get_report_card_data() rather than
// requiring the client to submit it (the source's setHicData signature takes
// that shape as input; here it's an internal recompute-and-persist action).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/report_card.php';
require_once __DIR__ . '/../../lib/final_results.php';

require_staff_role_ajax([10, 5]);

$sclass = trim($_POST['sclass'] ?? '');
$termid = isset($_POST['termid']) ? (int) $_POST['termid'] : 0;
$report = isset($_POST['report']) ? (int) $_POST['report'] : 0;
if ($sclass === '' || !$termid || !$report) {
    json_error('sclass, termid and report are required.');
}

require_class_access_ajax($mysqli, $sclass);

$reportCardData = get_report_card_data($mysqli, $sclass, $termid, $report);
$students = array_map(function ($row) {
    return ['grandTotal' => $row['grandTotal'], 'marks' => $row['marks']];
}, $reportCardData['studentData']);

$result = set_hic_data($mysqli, $sclass, $termid, $report, $students);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
