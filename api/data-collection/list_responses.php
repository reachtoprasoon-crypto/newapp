<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_staff_role_ajax([10]);

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
if (!$formId) {
    json_error('form_id is required.');
}

if (db_fetch_one($mysqli, "SELECT id FROM data_collection_forms WHERE id = ?", 'i', [$formId]) === null) {
    json_error('Form not found.', 404);
}

json_ok(get_data_collection_responses($mysqli, $formId));
