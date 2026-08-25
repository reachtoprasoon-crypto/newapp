<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/reference.php';

require_login_ajax();

$query = trim($_GET['q'] ?? '');
json_ok(search_teachers($mysqli, $query));
