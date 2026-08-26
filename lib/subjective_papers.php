<?php
// Subjective Papers: Parts/Sections/Questions authored as one ordered list,
// stored whole as JSON in subjective_papers.elements_json (kept as-is from
// the source rather than normalized into tables — see the project plan).
// Ports get-subjective-papers-flow.ts / upsert-subjective-paper-flow.ts /
// delete-subjective-paper-flow.ts.
//
// Ownership is enforced exactly as the source already does at the SQL layer
// (WHERE ... AND tid = ?) for both write paths — unlike MCQ papers, the
// source here already gets this right, so no tightening is needed. Only the
// creating teacher may ever create/edit/delete; Admin/Office/Principal
// (ttype 10/5/6) may only view all papers and export DOCX, matching
// subjective-paper-management.tsx's `!isPrivileged && <Create button>` and
// its edit/delete buttons being gated on exact tid match with no privileged
// override.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paper_shorthand.php';
require_once __DIR__ . '/../vendor/autoload.php';

function is_subjective_privileged($user) {
    return $user !== null && $user['type'] === 'staff' && in_array((int) $user['ttype'], [10, 5, 6], true);
}

function get_subjective_papers($mysqli, $tid, $isPrivileged) {
    if ($isPrivileged) {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT sp.spid, sp.tid, t.tname, sp.sclass, sp.subid, s.subname, s.subshort,
                    sp.title, sp.instruction, sp.max_marks, sp.time_duration, sp.created_at
             FROM subjective_papers sp
             JOIN teachers t ON sp.tid = t.tid
             JOIN subjects s ON sp.subid = s.subid
             ORDER BY sp.created_at DESC"
        );
    } else {
        $rows = db_fetch_all(
            $mysqli,
            "SELECT sp.spid, sp.tid, t.tname, sp.sclass, sp.subid, s.subname, s.subshort,
                    sp.title, sp.instruction, sp.max_marks, sp.time_duration, sp.created_at
             FROM subjective_papers sp
             JOIN teachers t ON sp.tid = t.tid
             JOIN subjects s ON sp.subid = s.subid
             WHERE sp.tid = ?
             ORDER BY sp.created_at DESC",
            'i',
            [$tid]
        );
    }
    foreach ($rows as &$r) {
        $r['spid'] = (int) $r['spid'];
        $r['tid'] = (int) $r['tid'];
        $r['subid'] = (int) $r['subid'];
        $r['max_marks'] = (int) $r['max_marks'];
    }
    return $rows;
}

function get_subjective_paper($mysqli, $spid) {
    $paper = db_fetch_one(
        $mysqli,
        "SELECT sp.spid, sp.tid, t.tname, sp.sclass, sp.subid, s.subname, s.subshort,
                sp.title, sp.instruction, sp.max_marks, sp.time_duration, sp.elements_json, sp.created_at
         FROM subjective_papers sp
         JOIN teachers t ON sp.tid = t.tid
         JOIN subjects s ON sp.subid = s.subid
         WHERE sp.spid = ?",
        'i',
        [$spid]
    );
    if ($paper === null) {
        return null;
    }
    $paper['spid'] = (int) $paper['spid'];
    $paper['tid'] = (int) $paper['tid'];
    $paper['subid'] = (int) $paper['subid'];
    $paper['max_marks'] = (int) $paper['max_marks'];
    $paper['elements'] = json_decode($paper['elements_json'], true) ?: [];
    unset($paper['elements_json']);
    return $paper;
}

// Distinct Part/Section labels and instruction strings this teacher has
// used before, for the authoring form's autocomplete.
function get_subjective_library($mysqli, $tid) {
    $rows = db_fetch_all($mysqli, "SELECT elements_json FROM subjective_papers WHERE tid = ?", 'i', [$tid]);
    $parts = [];
    $sections = [];
    $instructions = [];
    foreach ($rows as $row) {
        $elements = json_decode($row['elements_json'], true) ?: [];
        foreach ($elements as $el) {
            if (($el['type'] ?? '') === 'Part') {
                if (!empty($el['text'])) {
                    $parts[$el['text']] = true;
                }
                if (!empty($el['instruction'])) {
                    $instructions[$el['instruction']] = true;
                }
            } elseif (($el['type'] ?? '') === 'Section') {
                if (!empty($el['text'])) {
                    $sections[$el['text']] = true;
                }
                if (!empty($el['instruction'])) {
                    $instructions[$el['instruction']] = true;
                }
            }
        }
    }
    return [
        'parts' => array_keys($parts),
        'sections' => array_keys($sections),
        'instructions' => array_keys($instructions),
    ];
}

// $input: sclass, subid, title, instruction, max_marks, time_duration, elements[].
// $spid null -> insert.
function upsert_subjective_paper($mysqli, $spid, $tid, $input) {
    $elementsJson = json_encode($input['elements']);
    if ($spid) {
        $result = db_execute(
            $mysqli,
            "UPDATE subjective_papers SET sclass = ?, subid = ?, title = ?, instruction = ?, max_marks = ?, time_duration = ?, elements_json = ?
             WHERE spid = ? AND tid = ?",
            'sississii',
            [$input['sclass'], $input['subid'], $input['title'], $input['instruction'] ?: null, $input['max_marks'], $input['time_duration'], $elementsJson, $spid, $tid]
        );
        if ($result['affected'] === 0) {
            return ['success' => false, 'error' => 'Access denied. Only the creator of this paper can modify it.'];
        }
        return ['success' => true, 'spid' => $spid];
    }
    $result = db_execute(
        $mysqli,
        "INSERT INTO subjective_papers (tid, sclass, subid, title, instruction, max_marks, time_duration, elements_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'isississ',
        [$tid, $input['sclass'], $input['subid'], $input['title'], $input['instruction'] ?: null, $input['max_marks'], $input['time_duration'], $elementsJson]
    );
    return ['success' => true, 'spid' => $result['insert_id']];
}

function delete_subjective_paper($mysqli, $spid, $tid) {
    $result = db_execute($mysqli, "DELETE FROM subjective_papers WHERE spid = ? AND tid = ?", 'ii', [$spid, $tid]);
    if ($result['affected'] === 0) {
        return ['success' => false, 'error' => 'Access denied. Only the creator of this paper can delete it.'];
    }
    return ['success' => true];
}

function subjective_roman_class($sclass) {
    if (!$sclass) {
        return '';
    }
    $classStr = strtoupper(trim($sclass));
    if (preg_match('/^(\d+)/', $classStr, $m)) {
        $num = (int) $m[1];
        if ($num === 0) {
            return '';
        }
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $result = '';
        foreach ($map as $roman => $val) {
            while ($num >= $val) {
                $result .= $roman;
                $num -= $val;
            }
        }
        return $result;
    }
    if (str_starts_with($classStr, 'LKG')) {
        return 'L.K.G';
    }
    if (str_starts_with($classStr, 'UKG')) {
        return 'U.K.G';
    }
    return explode('-', $classStr)[0];
}

// Returns [PhpWord instance, mathMap] — see paper_stream_docx().
function generate_subjective_paper_docx($paper) {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection([
        'marginTop' => 1000, 'marginBottom' => 1000, 'marginLeft' => 1000, 'marginRight' => 1000,
    ]);
    $mathMap = [];
    $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
    $right = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END];
    $romanClass = subjective_roman_class($paper['sclass']);

    $section->addText('Dr. Virendra Swarup Education Centre, Avadhpuri.', ['bold' => true, 'size' => 15], array_merge($center, ['spaceAfter' => 120]));

    $colWidth = 3400;
    $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
    $headerRows = [
        ["Class: {$romanClass}", $paper['title'], "Time: {$paper['time_duration']}"],
        ['Date: ________', "Subject: {$paper['subname']}", "Max. Marks: {$paper['max_marks']}"],
        ['Name:________________', 'Class/Section: ______________', 'Roll No: _______________'],
    ];
    foreach ($headerRows as $cells) {
        $table->addRow();
        $table->addCell($colWidth)->addText($cells[0], ['bold' => true]);
        $table->addCell($colWidth)->addText($cells[1], ['bold' => true], $center);
        $table->addCell($colWidth)->addText($cells[2], ['bold' => true], $right);
    }
    $section->addTextBreak();

    if (!empty($paper['instruction'])) {
        $section->addText($paper['instruction'], ['italic' => true, 'size' => 10], array_merge($center, ['spaceBefore' => 120, 'spaceAfter' => 120]));
        $section->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => '000000']);
    }

    foreach ($paper['elements'] as $el) {
        $type = $el['type'] ?? '';
        if ($type === 'Part') {
            $section->addText($el['text'] ?? '', ['bold' => true, 'size' => 12, 'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE], array_merge($center, ['spaceBefore' => 240]));
            if (!empty($el['instruction'])) {
                $section->addText('(' . $el['instruction'] . ')', ['italic' => true, 'size' => 10], $center);
            }
        } elseif ($type === 'Section') {
            $section->addText($el['text'] ?? '', ['bold' => true, 'size' => 11], array_merge($center, ['spaceBefore' => 180]));
            if (!empty($el['instruction'])) {
                $section->addText($el['instruction'], ['italic' => true, 'size' => 10], $center);
            }
        } elseif ($type === 'Question') {
            $q = $el['question'] ?? [];
            $subparts = $q['subparts'] ?? [];
            $hasSubparts = count($subparts) > 0;
            $marks = (float) ($q['marks'] ?? 0);

            $run = $section->addTextRun(['tabs' => [new \PhpOffice\PhpWord\Style\Tab('right', 9500)], 'spaceBefore' => 120]);
            $run->addText('Q' . ($q['qno'] ?? '') . '. ', ['bold' => true]);
            paper_write_runs($run, paper_shorthand_to_runs($q['text'] ?? ''), $mathMap);
            if (!$hasSubparts && $marks > 0) {
                $run->addText("\t");
                $run->addText('[' . rtrim(rtrim(number_format($marks, 1), '0'), '.') . ']', ['bold' => true]);
            }

            if (!empty($q['image'])) {
                paper_add_base64_image($section, $q['image'], 400, 250, \PhpOffice\PhpWord\SimpleType\Jc::CENTER);
            }

            if ($hasSubparts) {
                $perSubpart = $marks > 0 ? $marks / count($subparts) : 0;
                foreach ($subparts as $idx => $sp) {
                    $spRun = $section->addTextRun([
                        'indentation' => ['left' => 720],
                        'tabs' => [new \PhpOffice\PhpWord\Style\Tab('right', 9500)],
                        'spaceBefore' => 60,
                    ]);
                    $spRun->addText('(' . chr(97 + $idx) . ') ');
                    paper_write_runs($spRun, paper_shorthand_to_runs($sp['text'] ?? ''), $mathMap);
                    if ($marks > 0) {
                        $spRun->addText("\t");
                        $spRun->addText('[' . number_format($perSubpart, 1) . ']', ['bold' => true]);
                    }
                }
            }
        }
    }

    $footer = $section->addFooter();
    $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', null, $center);

    return [$phpWord, $mathMap];
}
