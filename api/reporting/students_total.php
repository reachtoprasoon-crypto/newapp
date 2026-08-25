<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/reporting.php';

require_staff_role_ajax([10, 5]);

json_ok(get_all_students_total($mysqli));
