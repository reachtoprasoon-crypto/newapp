<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/reporting.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can view the class roster.', 403);
}

$sclass = trim($_GET['sclass'] ?? '');
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if ($sclass === '' || !$termid || !$report) {
    json_error('sclass, termid and report are required.');
}

require_class_access_ajax($mysqli, $sclass);

$roster = get_class_roster_data($mysqli, $sclass, $termid, $report);
if ($roster === null) {
    json_error('No marks data found for this class and term.', 404);
}
json_ok($roster);
