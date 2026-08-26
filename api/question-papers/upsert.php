<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/respond.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/question_papers.php';
require_once __DIR__ . '/../../lib/term_schedule.php';

require_login_ajax();
$user = current_user();
if ($user['type'] !== 'staff') {
    json_error('Forbidden', 403);
}

$tid = (int) $user['tid'];
$isPrivileged = is_mcq_privileged($user);
$qpid = (int) ($_POST['qpid'] ?? 0) ?: null;
$sclass = trim($_POST['sclass'] ?? '');
$subid = (int) ($_POST['subid'] ?? 0);
$title = trim($_POST['title'] ?? '');
$questions = json_decode($_POST['questions'] ?? '[]', true);

if ($sclass === '' || !$subid || $title === '' || !is_array($questions) || count($questions) === 0) {
    json_error('Class, subject, title and at least one question are required.');
}

if (!$qpid && !$isPrivileged && !is_mcq_creation_allowed($mysqli)) {
    json_error('MCQ paper creation is currently disabled.', 403);
}

if (!$isPrivileged) {
    $allowedSubjects = array_column(get_teacher_subjects_for_class($mysqli, $tid, $sclass), 'subid');
    if (!in_array($subid, $allowedSubjects, true)) {
        json_error('You are not assigned to teach this subject for this class.', 403);
    }
}

$cleanQuestions = [];
foreach ($questions as $q) {
    $correct = strtoupper(trim($q['correct_option'] ?? 'A'));
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        $correct = 'A';
    }
    $cleanQuestions[] = [
        'question_text' => trim($q['question_text'] ?? ''),
        'question_image' => $q['question_image'] ?? '',
        'option_a' => trim($q['option_a'] ?? ''),
        'option_a_image' => $q['option_a_image'] ?? '',
        'option_b' => trim($q['option_b'] ?? ''),
        'option_b_image' => $q['option_b_image'] ?? '',
        'option_c' => trim($q['option_c'] ?? ''),
        'option_c_image' => $q['option_c_image'] ?? '',
        'option_d' => trim($q['option_d'] ?? ''),
        'option_d_image' => $q['option_d_image'] ?? '',
        'correct_option' => $correct,
    ];
}

$result = upsert_question_paper($mysqli, $qpid, $tid, $isPrivileged, [
    'sclass' => $sclass,
    'subid' => $subid,
    'title' => $title,
    'questions' => $cleanQuestions,
]);

if (!$result['success']) {
    json_error($result['error']);
}
json_ok($result);
