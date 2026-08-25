<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    json_ok([]);
}

json_ok(get_students_by_class($mysqli, $sclass));
