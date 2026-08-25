<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/ht_wt.php';

require_login_ajax();

$sclass = trim($_POST['sclass'] ?? '');
$termid = isset($_POST['termid']) ? (int) $_POST['termid'] : 0;
$report = isset($_POST['report']) ? (int) $_POST['report'] : 0;
$students = json_decode($_POST['students'] ?? '[]', true);
if ($sclass === '' || !$termid || !$report || !is_array($students)) {
    json_error('sclass, termid, report and students are required.');
}

require_class_access_ajax($mysqli, $sclass);

$htWtData = [];
foreach ($students as $s) {
    if (!isset($s['sid'])) {
        json_error('Each row must include sid.');
    }
    $htWtData[] = [
        'sid' => (int) $s['sid'],
        'ht' => isset($s['ht']) && $s['ht'] !== '' ? (int) $s['ht'] : 0,
        'wt' => isset($s['wt']) && $s['wt'] !== '' ? (int) $s['wt'] : 0,
    ];
}

$result = upsert_ht_wt($mysqli, $sclass, $termid, $report, $htWtData);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
