<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    json_error('sclass is required.');
}

json_ok(get_class_subjects_schedule($mysqli, $sclass));
