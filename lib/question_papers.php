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

// Self-healing ALTER, same idiom as controls.php's get_all_controls(): no
// migrations mechanism exists in this project, so schema additions run
// defensively on every relevant request and no-op once applied.
function ensure_questions_marks_column($mysqli) {
    try {
        mysqli_query($mysqli, "ALTER TABLE questions ADD COLUMN marks DECIMAL(4,2) NOT NULL DEFAULT 1.00");
    } catch (\Throwable $e) {
        // already exists
    }
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
    ensure_questions_marks_column($mysqli);
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
    ensure_questions_marks_column($mysqli);
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
            $marks = is_numeric($q['marks'] ?? null) ? (float) $q['marks'] : 1.00;
            db_execute(
                $mysqli,
                "INSERT INTO questions (qpid, question_text, question_image, option_a, option_a_image, option_b, option_b_image, option_c, option_c_image, option_d, option_d_image, correct_option, marks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'isssssssssssd',
                [
                    $qpid,
                    $q['question_text'], $q['question_image'] ?: null,
                    $q['option_a'], $q['option_a_image'] ?: null,
                    $q['option_b'], $q['option_b_image'] ?: null,
                    $q['option_c'], $q['option_c_image'] ?: null,
                    $q['option_d'], $q['option_d_image'] ?: null,
                    $q['correct_option'],
                    $marks,
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

// Picks a file extension from a "data:image/xxx;base64,..." URL's MIME
// subtype; falls back to png for anything unrecognized/missing.
function question_paper_image_ext($dataUrl) {
    if (preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,#', $dataUrl, $m)) {
        $subtype = strtolower($m[1]);
        return $subtype === 'jpeg' ? 'jpg' : $subtype;
    }
    return 'png';
}

// Builds a temp zip of every question/option image, laid out as
// picques/<sclass>/<subshort>/Question_<n>[_A.._D].<ext>. Caller streams and
// unlink()s the returned path.
function build_question_paper_zip_path($paper) {
    $folder = 'picques/' . preg_replace('/\s+/', '_', $paper['sclass']) . '/' . preg_replace('/\s+/', '_', $paper['subshort']);
    $tmpFile = tempnam(sys_get_temp_dir(), 'qp_zip_');
    // tempnam() already creates an empty file; ZipArchive::CREATE needs the
    // path to not exist yet (more portable across libzip versions than
    // relying on ::OVERWRITE, which some shared-hosting PHP builds mishandle
    // on a pre-existing 0-byte file — silently, with no error, hence the
    // check below).
    unlink($tmpFile);
    $zip = new \ZipArchive();
    $result = $zip->open($tmpFile, \ZipArchive::CREATE);
    if ($result !== true) {
        throw new \RuntimeException('Could not create zip archive (ZipArchive::open error code ' . $result . ').');
    }

    $entryCount = 0;
    foreach ($paper['questions'] as $index => $q) {
        $n = $index + 1;
        if (!empty($q['question_image'])) {
            $bytes = paper_decode_base64_image($q['question_image']);
            if ($bytes !== null) {
                $zip->addFromString("{$folder}/Question_{$n}." . question_paper_image_ext($q['question_image']), $bytes);
                $entryCount++;
            }
        }
        foreach (['a', 'b', 'c', 'd'] as $opt) {
            $field = 'option_' . $opt . '_image';
            if (!empty($q[$field])) {
                $bytes = paper_decode_base64_image($q[$field]);
                if ($bytes !== null) {
                    $zip->addFromString("{$folder}/Question_{$n}_" . strtoupper($opt) . '.' . question_paper_image_ext($q[$field]), $bytes);
                    $entryCount++;
                }
            }
        }
    }

    // libzip silently declines to write anything to disk for a zero-entry
    // archive (close() still reports success) — surface that as a clear
    // error instead of streaming a phantom empty file.
    if ($entryCount === 0) {
        $zip->close();
        throw new \RuntimeException('This paper has no question or option images to export.');
    }

    if (!$zip->close()) {
        throw new \RuntimeException('Could not finalize zip archive.');
    }
    return $tmpFile;
}

// Builds the "Positional" answers CSV rows: 2 sets per paper, each question
// rotated across a 5-page cycle (set 1 walks Page_1..Page_5; set 2 starts one
// page earlier, Page_5, Page_1..Page_4 — matches the legacy export format).
// The 4 option-letter columns are always the fixed A,B,C,D order since this
// app's printed/exported paper never shuffles option display order — the
// real answer key is each question's correct_option.
function build_question_paper_answers_csv_rows($paper) {
    $folder = 'picques/' . preg_replace('/\s+/', '_', $paper['sclass']) . '/' . preg_replace('/\s+/', '_', $paper['subshort']);
    $rows = [];
    foreach ([1, 2] as $set) {
        foreach ($paper['questions'] as $index => $q) {
            $n = $index + 1;
            $page = (($n - $set) % 5 + 5) % 5 + 1;
            $rows[] = [
                "<img src='./{$folder}/Question_{$n}.png'>",
                'A', 'B', 'C', 'D',
                'Page_' . $page,
                (float) ($q['marks'] ?? 1.00),
                $paper['sclass'],
                $set,
                $paper['subname'],
                1,
                0,
            ];
        }
    }
    return $rows;
}

function paper_stream_zip_file($tmpFile, $filename) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
}

function paper_stream_csv_rows($rows, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
}
