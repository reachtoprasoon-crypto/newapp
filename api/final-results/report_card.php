<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if ($sclass === '' || !$termid || !$report) {
    json_error('sclass, termid and report are required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(get_final_report_card_data($mysqli, $sclass, $termid, $report));
