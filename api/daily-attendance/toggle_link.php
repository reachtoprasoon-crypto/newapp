<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/daily_attendance.php';

require_login_ajax();

$sclass = trim($_POST['sclass'] ?? '');
$isActive = ($_POST['isActive'] ?? '') === '1';
if ($sclass === '') {
    json_error('sclass is required.');
}

require_class_access_ajax($mysqli, $sclass);

json_ok(toggle_attendance_link($mysqli, $sclass, $isActive));
