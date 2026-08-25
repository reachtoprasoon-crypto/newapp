<?php
// Bulk photo upload: file names must be the student's Scholar Number
// (e.g. "1234.jpg"), matching the source app's bulk-photo-upload.tsx
// convention. Real multipart upload here (vs. the source's client-side
// base64 round-trip); each file is read and stored as a base64 data URI,
// same on-disk format the rest of the app already expects in students.photo.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/students.php';

require_staff_role_ajax([10, 5]);

if (empty($_FILES['photos']) || !is_array($_FILES['photos']['tmp_name'])) {
    json_error('No files uploaded.');
}

$updates = [];
$unmatched = 0;
$count = count($_FILES['photos']['tmp_name']);

for ($i = 0; $i < $count; $i++) {
    if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }
    $originalName = $_FILES['photos']['name'][$i];
    $schnoStr = pathinfo($originalName, PATHINFO_FILENAME);

    if (!ctype_digit($schnoStr)) {
        $unmatched++;
        continue;
    }

    $tmpPath = $_FILES['photos']['tmp_name'][$i];
    $mimeType = mime_content_type($tmpPath) ?: 'image/jpeg';
    $base64 = base64_encode(file_get_contents($tmpPath));

    $updates[] = [
        'schno' => (int) $schnoStr,
        'photo' => 'data:' . $mimeType . ';base64,' . $base64,
    ];
}

if (empty($updates)) {
    json_error('No valid photos found. File names must be the Scholar Number, e.g. 1234.jpg.');
}

$result = bulk_update_student_photos($mysqli, $updates);
if (!$result['success']) {
    json_error($result['error']);
}
$result['unmatched'] = $unmatched;
json_ok($result);
