<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_staff_role_ajax([10]);

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$exid = isset($_GET['exid']) ? (int) $_GET['exid'] : 0;
if ($sclass === '' || !$termid || !$report || !$exid) {
    json_error('sclass, termid, report and exid are required.');
}

json_ok(get_term_schedule_for_form($mysqli, $sclass, $termid, $report, $exid));
