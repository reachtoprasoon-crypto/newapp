<?php
// Change password while logged in. Ports change-password-flow.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff accounts can change their password.', 403);
}

$oldPassword = $_POST['oldPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';

if ($oldPassword === '' || $newPassword === '') {
    json_error('Missing required fields.');
}

$teacher = db_fetch_one($mysqli, "SELECT tpass FROM teachers WHERE tid = ?", 'i', [$user['tid']]);

if ($teacher === null) {
    json_error('Teacher not found.');
}

if ($teacher['tpass'] !== $oldPassword) {
    json_error('The old password you entered is incorrect.');
}

db_execute($mysqli, "UPDATE teachers SET tpass = ? WHERE tid = ?", 'si', [$newPassword, $user['tid']]);

json_ok(['success' => true]);
