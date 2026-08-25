<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can delete data-collection forms.', 403);
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$id) {
    json_error('id is required.');
}

$existing = db_fetch_one($mysqli, "SELECT tid FROM data_collection_forms WHERE id = ?", 'i', [$id]);
if ($existing === null) {
    json_error('Form not found.', 404);
}
if ((int) $user['ttype'] !== 10 && (int) $existing['tid'] !== (int) $user['tid']) {
    json_error('You do not have permission to delete this form.', 403);
}

json_ok(delete_data_collection_form($mysqli, $id));
