<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_staff_role_ajax([10]);

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if (!$id) {
    json_error('id is required.');
}

if (db_fetch_one($mysqli, "SELECT id FROM data_collection_forms WHERE id = ?", 'i', [$id]) === null) {
    json_error('Form not found.', 404);
}

json_ok(delete_data_collection_form($mysqli, $id));
