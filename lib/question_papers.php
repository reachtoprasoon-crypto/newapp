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

// GD-rendered question images: every question becomes exactly one
// Question_<n>.png (question text + all 4 options stacked, any attached
// images composited in), matching the legacy picques/ single-image-per-
// question convention — regardless of whether the teacher attached any
// image at all. GD (bundled with virtually every PHP install, including
// shared hosting) can only draw plain text, so math shorthand ($...$) and
// **bold**/*italic* markup are rendered as their literal source characters,
// not typeset — a deliberate fidelity tradeoff so this works without a
// headless browser, which shared cPanel hosts essentially never allow
// installing. DejaVu Sans is bundled in assets/fonts/ since production
// can't be assumed to have any TrueType font installed system-wide.

define('QP_IMG_WIDTH', 900);
define('QP_IMG_PADDING', 30);
define('QP_IMG_FONT_SIZE', 15);
define('QP_IMG_LINE_HEIGHT', 22);
define('QP_IMG_MAX_EMBED_WIDTH', 300);
define('QP_IMG_MAX_EMBED_HEIGHT', 220);
define('QP_IMG_MAX_OPTION_EMBED_WIDTH', 180);
define('QP_IMG_MAX_OPTION_EMBED_HEIGHT', 130);

function question_paper_font_path() {
    return __DIR__ . '/../assets/fonts/DejaVuSans.ttf';
}

// Wraps $text (which may itself contain literal newlines) to fit $maxWidth
// at $fontSize, using the bundled font for measurement. Returns a list of
// plain-text lines.
function question_paper_wrap_text($text, $fontSize, $maxWidth) {
    $font = question_paper_font_path();
    $allLines = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $paragraph) {
        $words = preg_split('/\s+/', trim($paragraph));
        $current = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $test = $current === '' ? $word : $current . ' ' . $word;
            $box = imagettfbbox($fontSize, 0, $font, $test);
            $width = abs($box[4] - $box[0]);
            if ($width > $maxWidth && $current !== '') {
                $allLines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }
        $allLines[] = $current;
    }
    return $allLines;
}

// Decodes+scales a base64 data-URL image to fit within maxWidth x maxHeight
// (preserving aspect ratio, never upscaling). Returns a GD resource the
// caller must imagedestroy(), or null if the data URL is missing/invalid.
function question_paper_load_embedded_image($dataUrl, $maxWidth, $maxHeight) {
    if (empty($dataUrl)) {
        return null;
    }
    $bytes = paper_decode_base64_image($dataUrl);
    if ($bytes === null) {
        return null;
    }
    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        return null;
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min($maxWidth / $w, $maxHeight / $h, 1);
    $newW = max(1, (int) round($w * $scale));
    $newH = max(1, (int) round($h * $scale));
    if ($newW === $w && $newH === $h) {
        return $src;
    }
    $resized = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefill($resized, 0, 0, $white);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);
    return $resized;
}

// Builds the block-by-block layout for one question (text lines + loaded
// embedded images, each with its rendered height already known), so the
// canvas can be sized exactly before any drawing happens.
function question_paper_build_question_layout($q, $questionNumber) {
    $contentWidth = QP_IMG_WIDTH - 2 * QP_IMG_PADDING;
    $blocks = [];
    $height = QP_IMG_PADDING;

    $qLines = question_paper_wrap_text('Q' . $questionNumber . '. ' . ($q['question_text'] ?? ''), QP_IMG_FONT_SIZE, $contentWidth);
    $blocks[] = ['type' => 'text', 'lines' => $qLines, 'indent' => 0];
    $height += count($qLines) * QP_IMG_LINE_HEIGHT;

    $qImg = question_paper_load_embedded_image($q['question_image'] ?? '', QP_IMG_MAX_EMBED_WIDTH, QP_IMG_MAX_EMBED_HEIGHT);
    if ($qImg !== null) {
        $blocks[] = ['type' => 'image', 'gd' => $qImg, 'indent' => 20];
        $height += imagesy($qImg) + 10;
    }
    $height += 8;

    foreach (['a', 'b', 'c', 'd'] as $opt) {
        $optLines = question_paper_wrap_text('(' . strtoupper($opt) . ') ' . ($q['option_' . $opt] ?? ''), QP_IMG_FONT_SIZE, $contentWidth - 20);
        $blocks[] = ['type' => 'text', 'lines' => $optLines, 'indent' => 20];
        $height += count($optLines) * QP_IMG_LINE_HEIGHT;

        $optImg = question_paper_load_embedded_image($q['option_' . $opt . '_image'] ?? '', QP_IMG_MAX_OPTION_EMBED_WIDTH, QP_IMG_MAX_OPTION_EMBED_HEIGHT);
        if ($optImg !== null) {
            $blocks[] = ['type' => 'image', 'gd' => $optImg, 'indent' => 40];
            $height += imagesy($optImg) + 8;
        }
    }

    $height += QP_IMG_PADDING;
    return ['blocks' => $blocks, 'height' => $height];
}

// Renders one question (+ its 4 options) to a PNG, returned as raw bytes.
function question_paper_render_question_png($q, $questionNumber) {
    $layout = question_paper_build_question_layout($q, $questionNumber);
    $canvas = imagecreatetruecolor(QP_IMG_WIDTH, max($layout['height'], 100));
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $black = imagecolorallocate($canvas, 20, 20, 20);
    imagefill($canvas, 0, 0, $white);

    $font = question_paper_font_path();
    $y = QP_IMG_PADDING;
    foreach ($layout['blocks'] as $block) {
        if ($block['type'] === 'text') {
            foreach ($block['lines'] as $line) {
                $y += QP_IMG_LINE_HEIGHT;
                imagettftext($canvas, QP_IMG_FONT_SIZE, 0, QP_IMG_PADDING + $block['indent'], $y, $black, $font, $line);
            }
        } else {
            $img = $block['gd'];
            imagecopy($canvas, $img, QP_IMG_PADDING + $block['indent'], $y, 0, 0, imagesx($img), imagesy($img));
            $y += imagesy($img) + 10;
            imagedestroy($img);
        }
    }

    ob_start();
    imagepng($canvas);
    $bytes = ob_get_clean();
    imagedestroy($canvas);
    return $bytes;
}

// Builds a temp zip of one rendered PNG per question, laid out as
// picques/<sclass>/<subshort>/Question_<n>.png. Caller streams and
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

    foreach ($paper['questions'] as $index => $q) {
        $n = $index + 1;
        $png = question_paper_render_question_png($q, $n);
        $zip->addFromString("{$folder}/Question_{$n}.png", $png);
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
