<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/controls.php';

require_staff_role_ajax([10]);
json_ok(get_toggleable_controls($mysqli));
