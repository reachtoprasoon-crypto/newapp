<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/communications.php';

require_login_ajax();
$user = current_user();

$filters = [];
$mine = ($_GET['mine'] ?? '') === '1';
$sclass = trim($_GET['sclass'] ?? '');

if ($mine) {
    if ($user['type'] !== 'staff') {
        json_error('Only staff have sent communications.', 403);
    }
    $filters['tid'] = (int) $user['tid'];
} elseif ($sclass !== '') {
    // A student may only browse their own class; staff go through the usual class-access check.
    if ($user['type'] === 'student') {
        if ($sclass !== $user['sclass']) {
            json_error('You do not have access to this class.', 403);
        }
    } else {
        require_class_access_ajax($mysqli, $sclass);
    }
    $filters['sclass'] = $sclass;
} else {
    json_error('Pass either mine=1 or sclass.');
}

json_ok(get_communications($mysqli, $filters));
