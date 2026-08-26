<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/subjective_papers.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Forbidden', 403);
}

$tid = (int) $user['tid'];
$spid = (int) ($_POST['spid'] ?? 0) ?: null;

if (!$spid && is_subjective_privileged($user)) {
    json_error('Admin/Office/Principal cannot author subjective papers — only the class subject teacher can.', 403);
}

$sclass = trim($_POST['sclass'] ?? '');
$subid = (int) ($_POST['subid'] ?? 0);
$title = trim($_POST['title'] ?? '');
$instruction = trim($_POST['instruction'] ?? '');
$maxMarks = (int) ($_POST['max_marks'] ?? 0);
$timeDuration = trim($_POST['time_duration'] ?? '');
$elements = json_decode($_POST['elements'] ?? '[]', true);

if ($sclass === '' || !$subid || $title === '' || $timeDuration === '' || !is_array($elements) || count($elements) === 0) {
    json_error('Class, subject, title, time duration and at least one paper element are required.');
}

$allowedSubjects = array_column(get_teacher_subjects_for_class($mysqli, $tid, $sclass), 'subid');
if (!in_array($subid, $allowedSubjects, true)) {
    json_error('You are not assigned to teach this subject for this class.', 403);
}

$cleanElements = [];
foreach ($elements as $el) {
    $type = $el['type'] ?? '';
    if ($type === 'Part' || $type === 'Section') {
        $cleanElements[] = [
            'type' => $type,
            'text' => trim($el['text'] ?? ''),
            'instruction' => trim($el['instruction'] ?? ''),
        ];
    } elseif ($type === 'Question') {
        $q = $el['question'] ?? [];
        $subparts = [];
        foreach (($q['subparts'] ?? []) as $sp) {
            $subparts[] = ['text' => trim($sp['text'] ?? ''), 'marks' => (float) ($sp['marks'] ?? 0)];
        }
        $cleanElements[] = [
            'type' => 'Question',
            'question' => [
                'qno' => (int) ($q['qno'] ?? 0),
                'text' => trim($q['text'] ?? ''),
                'image' => $q['image'] ?? '',
                'marks' => (float) ($q['marks'] ?? 0),
                'subparts' => $subparts,
            ],
        ];
    }
}

$result = upsert_subjective_paper($mysqli, $spid, $tid, [
    'sclass' => $sclass,
    'subid' => $subid,
    'title' => $title,
    'instruction' => $instruction,
    'max_marks' => $maxMarks,
    'time_duration' => $timeDuration,
    'elements' => $cleanElements,
]);

if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
