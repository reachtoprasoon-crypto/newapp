<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/daily_attendance.php';

require_staff_role_ajax([10, 5]);

$startDate = trim($_POST['startDate'] ?? '');
$endDate = trim($_POST['endDate'] ?? '');
$sclasses = json_decode($_POST['sclasses'] ?? '[]', true);
$description = trim($_POST['description'] ?? '');
$isHoliday = ($_POST['isHoliday'] ?? '1') === '1';

if ($startDate === '' || $endDate === '' || !is_array($sclasses) || empty($sclasses)) {
    json_error('startDate, endDate and sclasses are required.');
}

try {
    json_ok(set_holiday_range($mysqli, $startDate, $endDate, $sclasses, $description, $isHoliday));
} catch (Exception $e) {
    json_error('Database operation failed: ' . $e->getMessage());
}
