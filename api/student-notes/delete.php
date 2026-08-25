<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/student_notes.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can delete student notes.', 403);
}

$noteId = isset($_POST['note_id']) ? (int) $_POST['note_id'] : 0;
if (!$noteId) {
    json_error('note_id is required.');
}

$result = delete_student_note($mysqli, $noteId, (int) $user['tid']);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
