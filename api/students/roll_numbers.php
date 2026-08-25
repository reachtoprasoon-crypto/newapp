<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_staff_role_ajax([10, 5]);

$students = json_decode($_POST['students'] ?? '[]', true);
if (!is_array($students)) {
    json_error('Invalid payload.');
}

foreach ($students as $s) {
    if (!isset($s['sid'], $s['roll'])) {
        json_error('Each row must include sid and roll.');
    }
}

$result = update_student_roll_numbers($mysqli, $students);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
