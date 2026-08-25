<?php
// Issues a Transfer Certificate: archives the student's snapshot into
// `tcissued` and permanently removes them from `students`. Irreversible.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/tc.php';

require_staff_role_ajax([10]);

$required = [
    'sid', 'tcr_no', 'sl_no', 'admitted_on', 'admitted_class', 'prev_school',
    'left_on', 'character_cert', 'studying_class', 'board_stream',
    'year_from', 'year_to', 'dob_words', 'promotion_status', 'issue_date',
];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        json_error("Missing required field: $field");
    }
}

$input = [
    'sid' => (int) $_POST['sid'],
    'tcr_no' => trim($_POST['tcr_no']),
    'sl_no' => (int) $_POST['sl_no'],
    'admitted_on' => trim($_POST['admitted_on']),
    'admitted_class' => trim($_POST['admitted_class']),
    'prev_school' => trim($_POST['prev_school']),
    'left_on' => trim($_POST['left_on']),
    'character_cert' => trim($_POST['character_cert']),
    'studying_class' => trim($_POST['studying_class']),
    'board_stream' => trim($_POST['board_stream']),
    'year_from' => trim($_POST['year_from']),
    'year_to' => trim($_POST['year_to']),
    'dob_words' => trim($_POST['dob_words']),
    'promotion_status' => trim($_POST['promotion_status']),
    'issue_date' => trim($_POST['issue_date']),
];

$user = current_user();
$result = issue_tc($mysqli, $input, $user['tname']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result['tc']);
