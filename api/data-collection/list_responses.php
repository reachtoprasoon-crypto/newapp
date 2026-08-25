<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can view responses.', 403);
}

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
if (!$formId) {
    json_error('form_id is required.');
}

$form = db_fetch_one($mysqli, "SELECT tid FROM data_collection_forms WHERE id = ?", 'i', [$formId]);
if ($form === null) {
    json_error('Form not found.', 404);
}
if ((int) $user['ttype'] !== 10 && (int) $form['tid'] !== (int) $user['tid']) {
    json_error('You do not have permission to view these responses.', 403);
}

json_ok(get_data_collection_responses($mysqli, $formId));
