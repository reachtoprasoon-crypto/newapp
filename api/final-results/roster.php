<?php
// Also persists (overwrites finalhic/finalhictotal/finaltotal for the class)
// as a side effect, matching get-final-roster-data-flow.ts.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';

require_login_ajax();

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    json_error('sclass is required.');
}

require_class_access_ajax($mysqli, $sclass);

try {
    json_ok(get_final_roster_data($mysqli, $sclass));
} catch (Exception $e) {
    json_error('Database operation failed: ' . $e->getMessage());
}
