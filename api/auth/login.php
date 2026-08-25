<?php
// Ports actions.ts::verifyCredentialsAction: try teacher credentials first,
// fall back to treating (username, password) as (schno, dob) for a student
// login — exact same two-step logic as the source app's unified login form.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/dates.php';
require_once __DIR__ . '/../../lib/controls.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    json_error('Invalid form data.');
}

// --- 1. Try teacher login (plaintext password compare, preserved from source) ---
$teacher = db_fetch_one(
    $mysqli,
    "SELECT tid, tname, tpass, ttype, sclass FROM teachers WHERE tuser = ?",
    's',
    [$username]
);

if ($teacher !== null && $teacher['tpass'] !== null && $password === $teacher['tpass']) {
    $ttype = (int) $teacher['ttype'];

    if ($ttype !== 10 && !is_staff_login_allowed($mysqli)) {
        json_error('Staff login is currently disabled by the administrator.');
    }

    $_SESSION['auth'] = [
        'type' => 'staff',
        'tid' => (int) $teacher['tid'],
        'tname' => $teacher['tname'],
        'ttype' => $ttype,
        'sclass' => $teacher['sclass'],
    ];

    json_ok([
        'isValid' => true,
        'tid' => (int) $teacher['tid'],
        'teacherName' => $teacher['tname'],
        'ttype' => $ttype,
        'sclass' => $teacher['sclass'],
    ]);
}

// --- 2. Fall back to student login: username=schno, password=dob (dd-MM-yyyy) ---
$schno = (int) $username;
$student = db_fetch_one(
    $mysqli,
    "SELECT sid, schno, sname, sclass, dob, photo FROM students WHERE schno = ?",
    'i',
    [$schno]
);

if ($student === null) {
    json_error('Invalid username or password.');
}

if (!is_student_login_allowed($mysqli)) {
    json_error('Student login is currently disabled by the administrator.');
}

$formattedDob = normalize_dob_display($student['dob']);

if ($formattedDob !== $password) {
    json_error('Invalid Scholar Number or Date of Birth.');
}

$_SESSION['auth'] = [
    'type' => 'student',
    'sid' => (int) $student['sid'],
    'schno' => (int) $student['schno'],
    'sname' => $student['sname'],
    'sclass' => $student['sclass'],
    'photo' => $student['photo'],
];

json_ok([
    'isValid' => true,
    'ttype' => 0,
    'teacherName' => $student['sname'],
    'student' => [
        'sid' => (int) $student['sid'],
        'schno' => (int) $student['schno'],
        'sname' => $student['sname'],
        'sclass' => $student['sclass'],
        'photo' => $student['photo'],
    ],
]);
