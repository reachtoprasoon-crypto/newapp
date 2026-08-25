<?php
// Public (no login) — backs the /collect.php?form_id=X kiosk page.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/data_collection.php';

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
$sid = isset($_GET['sid']) ? (int) $_GET['sid'] : 0;
if (!$formId || !$sid) {
    json_error('form_id and sid are required.');
}

json_ok(check_student_response_exists($mysqli, $formId, $sid));
