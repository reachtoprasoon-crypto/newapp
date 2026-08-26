<?php
// MCQ "Question Papers": a header row (question_papers) plus its questions,
// each with up to 5 images (question + 4 options) stored as base64 data
// URLs, matching the students.photo convention. Ports get-question-papers-
// flow.ts / upsert-question-paper-flow.ts (same file) / delete.
//
// Ownership is tightened vs. the source: deleteQuestionPaperFlow there takes
// no tid at all and lets any teacher delete any paper (only the UI hides the
// button) — here it's enforced in the WHERE clause, same idiom as
// student_notes.php. Privileged roles (Admin/Principal, ttype 10/6, matching
// the source's own isPrivileged set for this feature) may edit/delete any
// paper, exactly as the source's UI already implies.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paper_shorthand.php';
require_once __DIR__ . '/../vendor/autoload.php';

function is_mcq_privileged($user) {
    return $user !== null && $user['type'] === 'staff' && in_array((int) $user['ttype'], [10, 6], true);
}

// controls row conid=14 ("Create_Questions") gates MCQ authoring for
// non-privileged staff, matching the source's `controls.find(c => c.conid
// === 14)` check.
function is_mcq_creation_allowed($mysqli) {
    $row = db_fetch_one($mysqli, "SELECT allowed FROM controls WHERE conid = 14");
    return $row !== null && (int) $row['allowed'] === 1;
}

function get_question_papers($mysqli, $tid, $isPrivileged) {
    if ($isPrivileged) {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT qp.qpid, qp.tid, t.tname, qp.sclass, qp.subid, s.subname, s.subshort, qp.title, qp.created_at,
                    (SELECT COUNT(*) FROM questions q WHERE q.qpid = qp.qpid) AS question_count
             FROM question_papers qp
             JOIN teachers t ON qp.tid = t.tid
             JOIN subjects s ON qp.subid = s.subid
             ORDER BY qp.created_at DESC"
        );
    } else {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT qp.qpid, qp.tid, t.tname, qp.sclass, qp.subid, s.subname, s.subshort, qp.title, qp.created_at,
                    (SELECT COUNT(*) FROM questions q WHERE q.qpid = qp.qpid) AS question_count
             FROM question_papers qp
             JOIN teachers t ON qp.tid = t.tid
             JOIN subjects s ON qp.subid = s.subid
             WHERE qp.tid = ?
             ORDER BY qp.created_at DESC",
            'i',
            [$tid]
        );
    }
    foreach ($rows as &$r) {
        $r['qpid'] = (int) $r['qpid'];
        $r['tid'] = (int) $r['tid'];
        $r['subid'] = (int) $r['subid'];
        $r['question_count'] = (int) $r['question_count'];
    }
    return $rows;
}

function get_question_paper($mysqli, $qpid) {
    $paper = db_fetch_one(
        $mysqli,
        "SELECT qp.qpid, qp.tid, t.tname, qp.sclass, qp.subid, s.subname, s.subshort, qp.title, qp.created_at
         FROM question_papers qp
         JOIN teachers t ON qp.tid = t.tid
         JOIN subjects s ON qp.subid = s.subid
         WHERE qp.qpid = ?",
        'i',
        [$qpid]
    );
    if ($paper === null) {
        return null;
    }
    $paper['qpid'] = (int) $paper['qpid'];
    $paper['tid'] = (int) $paper['tid'];
    $paper['subid'] = (int) $paper['subid'];
    $paper['questions'] = db_fetch_all($mysqli, "SELECT * FROM questions WHERE qpid = ? ORDER BY qid", 'i', [$qpid]);
    foreach ($paper['questions'] as &$q) {
        $q['qid'] = (int) $q['qid'];
        $q['qpid'] = (int) $q['qpid'];
    }
    return $paper;
}

// $input: sclass, subid, title, questions[] (question_text, question_image,
// option_a..d, option_a..d_image, correct_option). $qpid null -> insert.
function upsert_question_paper($mysqli, $qpid, $tid, $isPrivileged, $input) {
    mysqli_begin_transaction($mysqli);
    try {
        if ($qpid) {
            if ($isPrivileged) {
                $result = db_execute(
                    $mysqli,
                    "UPDATE question_papers SET sclass = ?, subid = ?, title = ? WHERE qpid = ?",
                    'sisi',
                    [$input['sclass'], $input['subid'], $input['title'], $qpid]
                );
            } else {
                $result = db_execute(
                    $mysqli,
                    "UPDATE question_papers SET sclass = ?, subid = ?, title = ? WHERE qpid = ? AND tid = ?",
                    'sisii',
                    [$input['sclass'], $input['subid'], $input['title'], $qpid, $tid]
                );
            }
            $ownsIt = $isPrivileged || db_fetch_one($mysqli, "SELECT qpid FROM question_papers WHERE qpid = ? AND tid = ?", 'ii', [$qpid, $tid]) !== null;
            if (!$ownsIt) {
                mysqli_rollback($mysqli);
                return ['success' => false, 'error' => 'Access denied. Only the creator of this paper can modify it.'];
            }
            db_execute($mysqli, "DELETE FROM questions WHERE qpid = ?", 'i', [$qpid]);
        } else {
            $result = db_execute(
                $mysqli,
                "INSERT INTO question_papers (tid, sclass, subid, title) VALUES (?, ?, ?, ?)",
                'isis',
                [$tid, $input['sclass'], $input['subid'], $input['title']]
            );
            $qpid = $result['insert_id'];
        }

        foreach ($input['questions'] as $q) {
            db_execute(
                $mysqli,
                "INSERT INTO questions (qpid, question_text, question_image, option_a, option_a_image, option_b, option_b_image, option_c, option_c_image, option_d, option_d_image, correct_option)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'isssssssssss',
                [
                    $qpid,
                    $q['question_text'], $q['question_image'] ?: null,
                    $q['option_a'], $q['option_a_image'] ?: null,
                    $q['option_b'], $q['option_b_image'] ?: null,
                    $q['option_c'], $q['option_c_image'] ?: null,
                    $q['option_d'], $q['option_d_image'] ?: null,
                    $q['correct_option'],
                ]
            );
        }

        mysqli_commit($mysqli);
        return ['success' => true, 'qpid' => $qpid];
    } catch (\Throwable $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function delete_question_paper($mysqli, $qpid, $tid, $isPrivileged) {
    if ($isPrivileged) {
        $result = db_execute($mysqli, "DELETE FROM question_papers WHERE qpid = ?", 'i', [$qpid]);
    } else {
        $result = db_execute($mysqli, "DELETE FROM question_papers WHERE qpid = ? AND tid = ?", 'ii', [$qpid, $tid]);
    }
    if ($result['affected'] === 0) {
        return ['success' => false, 'error' => 'Paper not found or you do not have permission to delete it.'];
    }
    return ['success' => true];
}

// Returns [PhpWord instance, mathMap] — see paper_stream_docx().
function generate_question_paper_docx($paper) {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $mathMap = [];

    $section->addText($paper['title'], ['bold' => true, 'size' => 14], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $section->addText(
        "Subject: {$paper['subname']} | Class: {$paper['sclass']}",
        ['size' => 11],
        ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
    );
    $section->addTextBreak();

    foreach ($paper['questions'] as $index => $q) {
        $run = $section->addTextRun();
        $run->addText('Q' . ($index + 1) . '. ', ['bold' => true, 'size' => 12]);
        paper_write_runs($run, paper_shorthand_to_runs($q['question_text'] ?? ''), $mathMap);

        if (!empty($q['question_image'])) {
            paper_add_base64_image($section, $q['question_image'], 150, 100, \PhpOffice\PhpWord\SimpleType\Jc::START);
        }

        foreach (['a', 'b', 'c', 'd'] as $opt) {
            $optRun = $section->addTextRun(['indentation' => ['left' => 720]]);
            $optRun->addText('(' . strtoupper($opt) . ') ', ['size' => 12]);
            paper_write_runs($optRun, paper_shorthand_to_runs($q['option_' . $opt] ?? ''), $mathMap);
            if (!empty($q['option_' . $opt . '_image'])) {
                $bytes = paper_decode_base64_image($q['option_' . $opt . '_image']);
                if ($bytes !== null) {
                    $optRun->addText('  ');
                    $optRun->addImage($bytes, ['width' => 120, 'height' => 80]);
                }
            }
        }
        $section->addTextBreak();
    }

    return [$phpWord, $mathMap];
}
