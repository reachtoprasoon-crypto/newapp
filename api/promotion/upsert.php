<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';

require_staff_role_ajax([10, 5]);
$user = current_user();

$sclass = trim($_POST['sclass'] ?? '');
$promotions = json_decode($_POST['promotions'] ?? '[]', true);
if ($sclass === '' || !is_array($promotions)) {
    json_error('sclass and promotions are required.');
}

require_class_access_ajax($mysqli, $sclass);

foreach ($promotions as $p) {
    if (!isset($p['sid'])) {
        json_error('Each row must include sid.');
    }
}

$result = upsert_promotion($mysqli, $sclass, $promotions, (int) $user['tid'], $user['tname']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
