<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_staff_role_ajax([10]);
$user = current_user();

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$fields = json_decode($_POST['fields'] ?? '[]', true);
$isActive = ($_POST['is_active'] ?? '1') === '1';

if ($title === '' || !is_array($fields) || empty($fields)) {
    json_error('title and at least one field are required.');
}
foreach ($fields as $f) {
    if (empty($f['label']) || empty($f['type'])) {
        json_error('Each field needs a label and type.');
    }
}

if ($id && db_fetch_one($mysqli, "SELECT id FROM data_collection_forms WHERE id = ?", 'i', [$id]) === null) {
    json_error('Form not found.', 404);
}

$result = upsert_data_collection_form($mysqli, $id, (int) $user['tid'], $title, $description, $fields, $isActive);
json_ok($result);
