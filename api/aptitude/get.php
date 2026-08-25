<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/aptitude.php';

require_staff_role_ajax([10]);

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    json_error('sclass is required.');
}

json_ok(get_aptitude_marks($mysqli, $sclass));
