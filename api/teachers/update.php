<?php
// Partial update: admin editing another teacher can send tname/tuser/tpass/phone/dob;
// a teacher editing their own profile would only send a subset (e.g. phone/dob) —
// same partial-update semantics as update-teacher-details-flow.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/teachers.php';

require_login_ajax();
$user = current_user();

$tid = isset($_POST['tid']) ? (int) $_POST['tid'] : 0;
if (!$tid) {
    json_error('tid is required.');
}

$isSelf = $user['type'] === 'staff' && (int) $user['tid'] === $tid;
$isAdmin = $user['type'] === 'staff' && (int) $user['ttype'] === 10;
if (!$isAdmin && !$isSelf) {
    json_error('You do not have permission to perform this action.', 403);
}

$fields = [];
foreach (['tname', 'tuser', 'tpass', 'phone', 'dob'] as $key) {
    if (isset($_POST[$key])) {
        $fields[$key] = trim($_POST[$key]);
    }
}

// Non-admins editing themselves may only change their own password/phone/dob,
// not their name or username (matches the admin-only fields in teacher-management.tsx).
if (!$isAdmin) {
    unset($fields['tname'], $fields['tuser']);
}

$result = update_teacher_details($mysqli, $tid, $fields);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
