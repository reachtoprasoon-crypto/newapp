<?php
// Public (no login) — backs the /collect.php?form_id=X kiosk page.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/data_collection.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    json_error('id is required.');
}

$form = get_data_collection_form_by_id($mysqli, $id);
if ($form === null) {
    json_error('This form is either inactive or does not exist.', 404);
}
json_ok($form);
