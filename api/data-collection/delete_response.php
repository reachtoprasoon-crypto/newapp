<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can delete responses.', 403);
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$id) {
    json_error('id is required.');
}

$response = db_fetch_one(
    $mysqli,
    "SELECT f.tid FROM data_collection_responses r JOIN data_collection_forms f ON r.form_id = f.id WHERE r.id = ?",
    'i',
    [$id]
);
if ($response === null) {
    json_error('Response not found.', 404);
}
if ((int) $user['ttype'] !== 10 && (int) $response['tid'] !== (int) $user['tid']) {
    json_error('You do not have permission to delete this response.', 403);
}

json_ok(delete_data_collection_response($mysqli, $id));
