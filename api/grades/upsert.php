<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/controls.php';
require_once __DIR__ . '/../../lib/grades.php';

require_login_ajax();
$user = current_user();

$sclass = trim($_POST['sclass'] ?? '');
$subid = isset($_POST['subid']) ? (int) $_POST['subid'] : 0;
$termid = isset($_POST['termid']) ? (int) $_POST['termid'] : 0;
$report = isset($_POST['report']) ? (int) $_POST['report'] : 0;
$grades = json_decode($_POST['grades'] ?? '[]', true);
if ($sclass === '' || !$subid || !$termid || !$report || !is_array($grades)) {
    json_error('sclass, subid, termid, report and grades are required.');
}

require_class_access_ajax($mysqli, $sclass);

$controls = get_all_controls($mysqli);
if (!is_feeding_allowed_for_class($controls, (int) $user['ttype'], $sclass)) {
    json_error('Grade feeding is currently disabled for this class by the administrator.', 403);
}

foreach ($grades as $g) {
    if (!isset($g['sid'])) {
        json_error('Each row must include sid.');
    }
}

$result = upsert_grades($mysqli, $sclass, $subid, $termid, $report, $grades);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
