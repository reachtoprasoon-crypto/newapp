<?php
// Step 2 of "Forgot Password": verify the submitted DOB against teachers.dob
// and, if it matches, set the new plaintext password. Ports reset-password-flow.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/respond.php';

$username = trim($_POST['username'] ?? '');
$dob = trim($_POST['dob'] ?? ''); // expected 'dd/MM/yyyy', matching DATE_FORMAT(dob,'%d/%m/%Y') in source
$newPassword = $_POST['newPassword'] ?? '';

if ($username === '' || $dob === '' || $newPassword === '') {
    json_error('Missing required fields.');
}

$teacher = db_fetch_one(
    $mysqli,
    "SELECT DATE_FORMAT(dob, '%d/%m/%Y') as dob FROM teachers WHERE tuser = ?",
    's',
    [$username]
);

if ($teacher === null) {
    json_error('Username not found.');
}

if ($teacher['dob'] !== $dob) {
    json_error('The date of birth is incorrect.');
}

db_execute($mysqli, "UPDATE teachers SET tpass = ? WHERE tuser = ?", 'ss', [$newPassword, $username]);

json_ok(['success' => true]);
