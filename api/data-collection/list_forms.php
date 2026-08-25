<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can view data-collection forms.', 403);
}

// Admins see everyone's forms; everyone else sees only their own.
$tid = ((int) $user['ttype'] === 10) ? null : (int) $user['tid'];
json_ok(get_data_collection_forms($mysqli, $tid));
