<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can create data-collection forms.', 403);
}

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

if ($id) {
    // Ownership check (admins may edit any form).
    $existing = db_fetch_one($mysqli, "SELECT tid FROM data_collection_forms WHERE id = ?", 'i', [$id]);
    if ($existing === null) {
        json_error('Form not found.', 404);
    }
    if ((int) $user['ttype'] !== 10 && (int) $existing['tid'] !== (int) $user['tid']) {
        json_error('You do not have permission to edit this form.', 403);
    }
}

$result = upsert_data_collection_form($mysqli, $id, (int) $user['tid'], $title, $description, $fields, $isActive);
json_ok($result);
