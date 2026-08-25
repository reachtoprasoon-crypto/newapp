<?php
// Step 1 of "Forgot Password": look up whether a username exists and whether
// resets are currently allowed. Ports get-teacher-dob-flow.ts, but omits
// echoing the real DOB back to the client (the UI never displays it — it asks
// the user to enter their own DOB as verification — so returning it was an
// unused PII leak in the source, dropped here without changing behavior).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/controls.php';

$username = trim($_POST['username'] ?? '');
if ($username === '') {
    json_error('Username is required.');
}

if (!is_password_reset_allowed($mysqli)) {
    json_ok(['found' => false, 'resetAllowed' => false, 'error' => 'Password reset is disabled by the administrator.']);
}

$teacher = db_fetch_one($mysqli, "SELECT tid, tname FROM teachers WHERE tuser = ?", 's', [$username]);

if ($teacher === null) {
    json_ok(['found' => false, 'resetAllowed' => true, 'error' => 'Username not found.']);
}

json_ok([
    'found' => true,
    'resetAllowed' => true,
    'tid' => (int) $teacher['tid'],
    'tname' => $teacher['tname'],
]);
