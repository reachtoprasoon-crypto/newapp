<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();

$isClassTeacher = ($_GET['isClassTeacher'] ?? '') === '1';
json_ok(get_class_subjects_for_grading($mysqli, $isClassTeacher));
