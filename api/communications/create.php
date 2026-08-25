<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/communications.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Only staff can post communications.', 403);
}

$sclasses = json_decode($_POST['sclasses'] ?? '[]', true);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$attachmentName = trim($_POST['attachment_name'] ?? '');
$attachmentFile = $_POST['attachment_file'] ?? '';
$commType = $_POST['comm_type'] ?? '';

if ($title === '' || empty($sclasses) || !in_array($commType, comm_valid_types(), true)) {
    json_error('Title, at least one recipient class, and a valid type are required.');
}

$result = create_communication($mysqli, (int) $user['tid'], $sclasses, $title, $content, $attachmentName, $attachmentFile, $commType);
if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
