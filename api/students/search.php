<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_login_ajax();

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    json_ok([]);
}

json_ok(search_students($mysqli, $query));
