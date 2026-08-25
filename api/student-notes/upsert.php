<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/student_notes.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can add student notes.', 403);
}

$noteId = isset($_POST['note_id']) && $_POST['note_id'] !== '' ? (int) $_POST['note_id'] : null;
$sid = isset($_POST['sid']) ? (int) $_POST['sid'] : 0;
$noteContent = trim($_POST['note_content'] ?? '');

if (!$sid || $noteContent === '') {
    json_error('sid and note_content are required.');
}

$result = upsert_student_note($mysqli, $noteId, $sid, (int) $user['tid'], $noteContent);
json_ok($result);
