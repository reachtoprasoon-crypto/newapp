<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_staff_role_ajax([10]);

$sourceSclass = trim($_POST['sourceSclass'] ?? '');
$sourceTermid = isset($_POST['sourceTermid']) ? (int) $_POST['sourceTermid'] : 0;
$sourceReport = isset($_POST['sourceReport']) ? (int) $_POST['sourceReport'] : 0;
$targetSclasses = json_decode($_POST['targetSclasses'] ?? '[]', true);

if ($sourceSclass === '' || !$sourceTermid || !$sourceReport || !is_array($targetSclasses)) {
    json_error('sourceSclass, sourceTermid, sourceReport and targetSclasses are required.');
}

$result = copy_term_schedule($mysqli, $sourceSclass, $sourceTermid, $sourceReport, $targetSclasses);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
