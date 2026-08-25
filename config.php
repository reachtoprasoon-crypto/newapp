<?php
// Database connection + session bootstrap, shared by every page and api/*.php endpoint.
// Mirrors the connection convention used by sibling apps (mascon, online, aptitude): mysqli, root/root.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'vsecadlu_reportcard202667');

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$mysqli) {
    http_response_code(500);
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($mysqli, 'utf8');
