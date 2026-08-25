<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/controls.php';
require_once __DIR__ . '/../../lib/marks.php';

require_login_ajax();
$user = current_user();

$termschid = isset($_POST['termschid']) ? (int) $_POST['termschid'] : 0;
$marks = json_decode($_POST['marks'] ?? '[]', true);
if (!$termschid || !is_array($marks)) {
    json_error('termschid and marks are required.');
}

$context = db_fetch_one($mysqli, "SELECT sclass FROM termschedule WHERE termschid = ?", 'i', [$termschid]);
if ($context === null) {
    json_error('Assessment not found.');
}
$sclass = $context['sclass'];

require_class_access_ajax($mysqli, $sclass);

$controls = get_all_controls($mysqli);
if (!is_feeding_allowed_for_class($controls, (int) $user['ttype'], $sclass)) {
    json_error('Marks feeding is currently disabled for this class by the administrator.', 403);
}

foreach ($marks as $m) {
    if (!isset($m['sid'], $m['marks'])) {
        json_error('Each row must include sid and marks.');
    }
}

$result = upsert_marks($mysqli, $termschid, $marks);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
