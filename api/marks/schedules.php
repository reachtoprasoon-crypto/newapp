<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();

$filters = [
    'sclass' => trim($_GET['sclass'] ?? ''),
    'subid' => isset($_GET['subid']) ? (int) $_GET['subid'] : null,
    'termid' => isset($_GET['termid']) ? (int) $_GET['termid'] : null,
    'report' => isset($_GET['report']) ? (int) $_GET['report'] : null,
];

json_ok(get_term_schedules($mysqli, $filters));
