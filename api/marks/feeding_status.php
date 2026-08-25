<?php
// Admin/office reporting view — status across all classes, not scoped to one class.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/marks.php';

require_staff_role_ajax([10, 5]);

$termid = isset($_GET['termid']) ? (int) $_GET['termid'] : 0;
$report = isset($_GET['report']) ? (int) $_GET['report'] : 0;
$sclass = trim($_GET['sclass'] ?? 'all');
if (!$termid || !$report) {
    json_error('termid and report are required.');
}

json_ok(get_marks_feeding_status($mysqli, $termid, $report, $sclass));
