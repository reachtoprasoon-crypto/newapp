<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/aptitude.php';

require_staff_role_ajax([10]);

$marks = json_decode($_POST['marks'] ?? '[]', true);
if (!is_array($marks)) {
    json_error('marks is required.');
}
foreach ($marks as $m) {
    if (!isset($m['sid'], $m['marks'])) {
        json_error('Each row must include sid and marks.');
    }
}

json_ok(upsert_aptitude_marks($mysqli, $marks));
