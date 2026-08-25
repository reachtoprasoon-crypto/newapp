<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/grades.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
$subid = isset($_GET['subid']) ? (int) $_GET['subid'] : 0;
$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
if ($sclass === '' || !$subid || !$termid || !$report) {
    json_error('sclass, subid, termid and report are required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(get_grades_for_subject($mysqli, $sclass, $subid, $termid, $report));
