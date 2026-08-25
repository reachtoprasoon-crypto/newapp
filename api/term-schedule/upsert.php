<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_staff_role_ajax([10]);

$sclass = trim($_POST['sclass'] ?? '');
$termid = isset($_POST['termid']) ? (int) $_POST['termid'] : 0;
$report = isset($_POST['report']) ? (int) $_POST['report'] : 0;
$exid = isset($_POST['exid']) ? (int) $_POST['exid'] : 0;
$schedules = json_decode($_POST['schedules'] ?? '[]', true);
if ($sclass === '' || !$termid || !$report || !$exid || !is_array($schedules)) {
    json_error('sclass, termid, report, exid and schedules are required.');
}

foreach ($schedules as $s) {
    if (!isset($s['subid'], $s['maxm'])) {
        json_error('Each row must include subid and maxm.');
    }
}

$result = upsert_term_schedule($mysqli, $sclass, $termid, $report, $exid, $schedules);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
