<?php
// Database connection + session bootstrap, shared by every page and api/*.php endpoint.
// Connection values live in .env (git-ignored, one per environment, never
// committed) — this file itself carries no secrets and is safe to keep
// identical across local and production. The Database admin tab
// (lib/db_config.php) edits .env directly, never this file.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function load_env_file($path) {
    $env = [];
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $env[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    return $env;
}

$env = load_env_file(__DIR__ . '/.env');

define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_USER', $env['DB_USER'] ?? 'root');
define('DB_PASS', $env['DB_PASS'] ?? 'root');
define('DB_NAME', $env['DB_NAME'] ?? 'vsecadlu_reportcard202667');

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$mysqli) {
    http_response_code(500);
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($mysqli, 'utf8');
