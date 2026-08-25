<?php
// Public (no login) — backs the /collect.php?form_id=X kiosk page.
// Re-verifies schno+dob at submit time rather than trusting a client-held
// sid from an earlier verify step (see lib/students.php::verify_student_credentials).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/students.php';
require_once __DIR__ . '/../../lib/data_collection.php';

$formId = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
$schno = isset($_POST['schno']) ? (int) $_POST['schno'] : 0;
$dob = trim($_POST['dob'] ?? '');
$responses = json_decode($_POST['responses'] ?? '{}', true);

if (!$formId || !$schno || $dob === '' || !is_array($responses)) {
    json_error('form_id, schno, dob and responses are required.');
}

$verification = verify_student_credentials($mysqli, $schno, $dob);
if (!$verification['isValid']) {
    json_error($verification['error']);
}
$sid = $verification['student']['sid'];

$form = get_data_collection_form_by_id($mysqli, $formId);
if ($form === null) {
    json_error('This form is either inactive or does not exist.', 404);
}

if (check_student_response_exists($mysqli, $formId, $sid)) {
    json_error('You have already submitted a response for this form.');
}

foreach ($form['fields'] as $f) {
    if (!empty($f['required']) && empty($responses[$f['label']])) {
        json_error($f['label'] . ' is mandatory.');
    }
}

json_ok(submit_data_collection_response($mysqli, $formId, $sid, $responses));
