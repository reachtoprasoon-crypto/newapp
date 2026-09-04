<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/data_collection.php';

require_staff_role_ajax([10]);

json_ok(get_data_collection_forms($mysqli, null));
