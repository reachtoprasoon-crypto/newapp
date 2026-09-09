<?php
// Ports src/lib/term-report-card-excel.ts::generateTermExcel — one worksheet
// per student, precisely laid out (merged cells, borders, watermark image,
// senior vs. junior subject-grid format) rather than the plain roster table
// report_card.php renders. Uses PhpSpreadsheet (ExcelJS's closest PHP
// equivalent) via Composer.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/report_card.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Helper\Dimension as CssDimension;

const RC_THEME_COLOR = 'FF095889';
const RC_DATA_COLOR = 'FF000000';

function rc_col_letter($col) {
    return Coordinate::stringFromColumnIndex($col);
}

function rc_range($row, $startCol, $endCol) {
    return rc_col_letter($startCol) . $row . ':' . rc_col_letter($endCol) . $row;
}

function rc_merge_set($sheet, $row, $startCol, $endCol, $value, $opts = []) {
    $range = rc_range($row, $startCol, $endCol);
    if ($startCol !== $endCol) {
        $sheet->mergeCells($range);
    }
    $cellRef = rc_col_letter($startCol) . $row;
    $sheet->setCellValue($cellRef, $value);
    rc_style($sheet, $range, $opts);
    return $cellRef;
}

// Builds one combined style array and applies it in a single call. Each
// individual setter on a getStyle($range)->getFont()/getAlignment()/
// getBorders() supervisor internally re-triggers a full applyFromArray()
// pass over the whole range — so the previous one-setter-call-per-property
// version did up to 9 full range-mutation passes per rc_style() call (this
// runs 60+ times per student sheet). Batching into one applyFromArray() cuts
// that to a single pass; see PhpSpreadsheet's Style::applyFromArray() docs
// ("Best Practices for Performance").
function rc_style($sheet, $range, $opts) {
    $style = [];

    $font = [];
    if (isset($opts['bold'])) $font['bold'] = $opts['bold'];
    if (isset($opts['italic'])) $font['italic'] = $opts['italic'];
    if (isset($opts['color'])) $font['color'] = ['argb' => $opts['color']];
    if (isset($opts['size'])) $font['size'] = $opts['size'];
    if ($font) $style['font'] = $font;

    $align = [];
    if (isset($opts['halign'])) $align['horizontal'] = $opts['halign'];
    if (isset($opts['valign'])) $align['vertical'] = $opts['valign'];
    if (isset($opts['wrap'])) $align['wrapText'] = $opts['wrap'];
    if (isset($opts['indent'])) $align['indent'] = $opts['indent'];
    if ($align) $style['alignment'] = $align;

    if (!empty($opts['border'])) {
        $style['borders'] = rc_border_array($opts['border']);
    }

    if ($style) {
        $sheet->getStyle($range)->applyFromArray($style);
    }
}

// $sides: array subset of ['top','bottom','left','right'], 'all' (every side
// of every cell in the range — a full grid), or 'outline' (a single border
// around the range's true outer perimeter only, regardless of how many rows/
// cells it spans or how they're merged). 'allBorders'/'outline' are handled
// by PhpSpreadsheet's own "advanced borders" logic in Style::applyFromArray()
// (expands to the exact same per-side application the old getOutline()/
// per-side-getter code did, but as part of the single combined call above).
function rc_border_array($sides) {
    $border = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => RC_DATA_COLOR]];
    if ($sides === 'outline') {
        return ['outline' => $border];
    }
    if ($sides === 'all') {
        return ['allBorders' => $border];
    }
    $borders = [];
    foreach ($sides as $side) {
        $borders[$side] = $border;
    }
    return $borders;
}

function rc_roman_numeral($numStr) {
    if (!$numStr) return '';
    $classStr = strtoupper(trim($numStr));
    if (!preg_match('/^(\d+)([A-Z])$/', $classStr, $m)) {
        return $numStr;
    }
    $num = (int) $m[1];
    $section = $m[2];
    $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
    $result = '';
    foreach ($map as $key => $val) {
        while ($num >= $val) {
            $result .= $key;
            $num -= $val;
        }
    }
    return $result . '-' . $section;
}

/**
 * $input keys: header, students, gradeSubjects, studentGrades (map sid=>[{subname,grade}]),
 * classStudents, attendanceData, hicData ({subjectHics, termHic}), comments,
 * selectedClass, selectedTermName, customTermLabel, watermarkBase64, watermarkSize,
 * headerConfig ({includeSchool, includeBranch, includeWatermark, includeSignatures})
 * Returns the Spreadsheet object; caller decides how to write/stream it.
 */
function generate_term_report_card_excel($input) {
    $header = $input['header'];
    $students = $input['students'];
    $studentGrades = $input['studentGrades'];
    $classStudents = $input['classStudents'];
    $attendanceData = $input['attendanceData'];
    $hicData = $input['hicData'];
    $comments = $input['comments'];
    $selectedClass = $input['selectedClass'];
    $selectedTermName = $input['selectedTermName'];
    $customTermLabel = $input['customTermLabel'] ?? null;
    $termid = $input['termid'] ?? null;
    $report = $input['report'] ?? null;
    $watermarkBase64 = $input['watermarkBase64'] ?? null;
    $watermarkSize = $input['watermarkSize'] ?? 350;
    $headerConfig = $input['headerConfig'];

    $gridEndCol = 26;

    $watermarkData = null;
    if ($watermarkBase64) {
        $parts = explode(',', $watermarkBase64, 2);
        $watermarkData = base64_decode($parts[1] ?? $parts[0]);
    } else {
        $logoPath = __DIR__ . '/../assets/images/logo.gif';
        if (is_file($logoPath)) {
            $watermarkData = file_get_contents($logoPath);
        }
    }

    $gdWatermarkImage = $watermarkData ? @imagecreatefromstring($watermarkData) : false;
    if ($gdWatermarkImage !== false && (imageistruecolor($gdWatermarkImage) || imagecolortransparent($gdWatermarkImage) >= 0)) {
        // Without this, the alpha channel a semi-transparent uploaded PNG carries
        // is dropped once PhpSpreadsheet clones the GD resource per sheet, so the
        // watermark always renders fully opaque regardless of the source image's
        // transparency. Matches what MemoryDrawing::fromString() does internally.
        imagesavealpha($gdWatermarkImage, true);
    }

    preg_match('/\d+/', $selectedClass, $classNumMatch);
    $classNum = $classNumMatch ? (int) $classNumMatch[0] : 0;
    $useSeniorFormat = $classNum >= 9 && $classNum <= 12;

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);
    $sheetIndex = 0;

    // Indexed once instead of linearly scanned per student below.
    $classStudentsBySid = [];
    foreach ($classStudents as $cs) {
        $classStudentsBySid[(int) $cs['sid']] = $cs;
    }
    $attendanceBySid = [];
    foreach ($attendanceData as $a) {
        $attendanceBySid[(int) $a['sid']] = $a;
    }
    $commentsByComid = [];
    foreach ($comments as $c) {
        $commentsByComid[(int) $c['comid']] = $c;
    }

    foreach ($students as $student) {
        $sid = (int) $student['sid'];
        $studentDetails = $classStudentsBySid[$sid] ?? null;
        $studentAttendance = $attendanceBySid[$sid] ?? null;
        $studentComment = null;
        if ($studentAttendance && $studentAttendance['comid'] !== null) {
            $studentComment = $commentsByComid[(int) $studentAttendance['comid']] ?? null;
        }
        $studentGradesList = $studentGrades[$sid] ?? [];

        $sheetName = preg_replace('/[\\\\\/\*\?:\[\]]/', '', substr($student['roll'] . '-' . $student['sname'], 0, 31));
        if ($sheetName === '') {
            $sheetName = 'Student' . $sid;
        }

        $sheet = $spreadsheet->createSheet($sheetIndex++);
        $sheet->setTitle($sheetName);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $margins = $sheet->getPageMargins();
        $margins->setLeft(0.2);
        $margins->setRight(0.2);
        $margins->setTop(0.3);
        $margins->setBottom(0.3);
        $margins->setHeader(0.0);
        $margins->setFooter(0.0);

        $sheet->getColumnDimension('A')->setWidth(0.54, CssDimension::UOM_INCHES);
        for ($i = 2; $i <= $gridEndCol; $i++) {
            $width = ($i === 22) ? 1.0 : 5.0;
            $sheet->getColumnDimension(rc_col_letter($i))->setWidth($width);
        }
        for ($i = 1; $i <= 35; $i++) {
            $sheet->getRowDimension($i)->setRowHeight($i === 7 ? 5.0 : 21.0);
        }

        rc_merge_set($sheet, 1, 2, $gridEndCol, $headerConfig['includeSchool'] ? 'DR. VIRENDRA SWARUP EDUCATION CENTRE' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 24, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);
        rc_merge_set($sheet, 2, 2, $gridEndCol, $headerConfig['includeBranch'] ? 'AVADHPURI, KANPUR' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 20, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);

        if ($gdWatermarkImage !== false && !empty($headerConfig['includeWatermark'])) {
            try {
                $drawing = new MemoryDrawing();
                $drawing->setImageResource($gdWatermarkImage);
                $drawing->setRenderingFunction(MemoryDrawing::RENDERING_DEFAULT);
                $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
                $drawing->setCoordinates('F3');
                $drawing->setWidth($watermarkSize);
                $drawing->setHeight($watermarkSize);
                $drawing->setWorksheet($sheet);
            } catch (Throwable $e) {
                // Non-fatal, matches source's swallowed try/catch.
            }
        }

        $headerGrid = [
            ['r' => 3, 'items' => [[2, 5, true, 'SCHOLAR NO.'], [6, 21, true, 'NAME'], [22, 26, true, 'CLASS']]],
            ['r' => 4, 'items' => [[2, 5, false, $student['schno'] ?? ''], [6, 21, false, $student['sname']], [22, 26, false, rc_roman_numeral($studentDetails['sclass'] ?? '')]]],
            ['r' => 5, 'items' => [[2, 5, true, 'TERM/YEAR'], [6, 9, true, 'ATTENDANCE'], [10, 13, true, 'D.O.B.'], [14, 17, true, 'HOUSE'], [18, 21, true, 'WEIGHT'], [22, 26, true, 'HEIGHT']]],
            ['r' => 6, 'items' => [
                [2, 5, false, $customTermLabel ?: rc_default_term_label($selectedClass, $selectedTermName, $termid, $report)],
                [6, 9, false, ($studentAttendance['attendance'] ?? 'N/A') . ' / ' . ($studentAttendance['totalattendance'] ?? 'N/A')],
                [10, 13, false, $studentDetails['dob'] ?? ''],
                [14, 17, false, $studentDetails['house'] ?? 'N/A'],
                [18, 21, false, ($studentDetails['wt'] ?? '') . ' Kg.'],
                [22, 26, false, ($studentDetails['ht'] ?? '') . ' Cm.'],
            ]],
        ];
        foreach ($headerGrid as $row) {
            foreach ($row['items'] as [$s, $e, $isLabel, $val]) {
                rc_merge_set($sheet, $row['r'], $s, $e, $val, [
                    'bold' => $isLabel, 'color' => $isLabel ? RC_THEME_COLOR : RC_DATA_COLOR, 'size' => 13,
                    'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER, 'border' => 'all',
                ]);
            }
        }

        rc_merge_set($sheet, 8, 2, 7, 'SUBJECTS', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        $activeSubjects = array_values(array_filter($header, function ($h) use ($student) {
            if ($h['label'] === 'Grand Total' || $h['label'] === 'Percentage' || !isset($h['subid'])) return false;
            foreach ($h['subHeaders'] as $sh) {
                $val = $student[$sh['key']] ?? null;
                if (is_numeric($val)) return true;
            }
            return false;
        }));

        $examLabels = [];
        foreach ($activeSubjects as $h) {
            foreach ($h['subHeaders'] as $sh) {
                if (str_starts_with($sh['key'], 'mark_') && !in_array($sh['label'], $examLabels, true)) {
                    $examLabels[] = $sh['label'];
                }
            }
        }
        $examLabels = array_slice($examLabels, 0, 2);
        usort($examLabels, fn($a, $b) => ($a === 'CA*' ? -1 : 1));
        $showTwo = count($examLabels) > 1;

        $setupMetricHeader = function ($label, $start, $span) use ($sheet) {
            rc_merge_set($sheet, 8, $start, $start + $span - 1, $label, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
            return ['start' => $start, 'span' => $span, 'label' => $label];
        };

        // MM is now H-K (8-11, 4 cols) in every format, to line up with the
        // widened B-G subject-name column. Senior's EXAM/HIC keep their old
        // start/span — MM grew by shifting its start (not its end), so
        // nothing after it needs to move. The two junior formats' MM instead
        // grew in both directions (start -1, end +2 vs their old 7-9), so
        // EXAM/TOTAL/HIC are resized (not just shifted) to still end at
        // column 21, leaving the sidebar/spacer columns untouched.
        $assessmentCols = [];
        if ($useSeniorFormat) {
            $mmCol = $setupMetricHeader('MM', 8, 4);
            $assessmentCols[] = $setupMetricHeader('EXAM', 12, 5);
            $assessmentCols[] = $setupMetricHeader('HIC', 17, 5);
        } elseif ($showTwo) {
            $mmCol = $setupMetricHeader('MM', 8, 4);
            $assessmentCols[] = $setupMetricHeader($examLabels[0], 12, 2);
            $assessmentCols[] = $setupMetricHeader($examLabels[1], 14, 2);
            $assessmentCols[] = $setupMetricHeader('TOTAL', 16, 3);
            $assessmentCols[] = $setupMetricHeader('HIC', 19, 3);
        } else {
            $mmCol = $setupMetricHeader('MM', 8, 4);
            $labelStr = $examLabels[0] ?? 'EXAM';
            $assessmentCols[] = $setupMetricHeader($labelStr, 12, 5);
            $assessmentCols[] = $setupMetricHeader('HIC', 17, 5);
        }
        $findCol = function ($label) use ($assessmentCols) {
            foreach ($assessmentCols as $c) if ($c['label'] === $label) return $c;
            return null;
        };

        $gMax = 0;
        $gObt = 0;
        $colTotals = array_fill_keys($examLabels, 0);
        $subjectHics = $hicData['subjectHics'] ?? [];

        for ($rIdx = 9; $rIdx <= 20; $rIdx++) {
            $sub = $activeSubjects[$rIdx - 9] ?? null;
            $isLastInGrid = $rIdx === 20;
            $borderSides = $isLastInGrid ? ['left', 'right', 'bottom'] : ['left', 'right'];

            $sheet->mergeCells(rc_range($rIdx, 2, 7));
            $sheet->mergeCells(rc_range($rIdx, $mmCol['start'], $mmCol['start'] + $mmCol['span'] - 1));
            foreach ($assessmentCols as $col) {
                $sheet->mergeCells(rc_range($rIdx, $col['start'], $col['start'] + $col['span'] - 1));
            }

            $scRef = rc_col_letter(2) . $rIdx;
            $mmRef = rc_col_letter($mmCol['start']) . $rIdx;
            rc_style($sheet, $scRef, ['halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1, 'border' => $borderSides]);
            rc_style($sheet, $mmRef, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);

            if ($sub) {
                $subid = $sub['subid'];
                $sheet->setCellValue($scRef, $sub['label']);
                rc_style($sheet, $scRef, ['color' => RC_THEME_COLOR, 'size' => 14]);

                if ($useSeniorFormat) {
                    $sMax = 0;
                    foreach ($sub['subHeaders'] as $sh) if (str_starts_with($sh['key'], 'mark_')) $sMax += ($sh['maxm'] ?? 0);
                    $sObt = $student['total_' . $subid] ?? 0;

                    $sheet->setCellValue($mmRef, $sMax ?: '');
                    $exRef = rc_col_letter(12) . $rIdx;
                    $sheet->setCellValue($exRef, $sObt ?: '');
                    rc_style($sheet, $exRef, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                    $hiRef = rc_col_letter(17) . $rIdx;
                    $sheet->setCellValue($hiRef, $subjectHics->{$subid} ?? '');
                    // Border must go on the full merged range (17-21), not just
                    // the anchor cell, or the merge's true right/outer edge
                    // (owned by column 21's own cell style) never gets one.
                    rc_style($sheet, $hiRef . ':' . rc_col_letter(21) . $rIdx, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);

                    $gMax += $sMax;
                    $gObt += $sObt;
                } else {
                    $sMax = 0;
                    $sObt = $student['total_' . $subid] ?? 0;

                    foreach ($examLabels as $lab) {
                        $col = $findCol($lab);
                        $sh = null;
                        foreach ($sub['subHeaders'] as $shCandidate) {
                            if ($shCandidate['label'] === $lab && str_starts_with($shCandidate['key'], 'mark_')) { $sh = $shCandidate; break; }
                        }
                        if ($col) {
                            $dataRef = rc_col_letter($col['start']) . $rIdx;
                            if ($sh) {
                                $v = $student[$sh['key']] ?? null;
                                $sheet->setCellValue($dataRef, is_numeric($v) ? $v : '');
                                $sMax += ($sh['maxm'] ?? 0);
                                if (is_numeric($v)) $colTotals[$lab] += $v;
                            }
                            rc_style($sheet, $dataRef, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                        }
                    }
                    $sheet->setCellValue($mmRef, $sMax ?: '');

                    if ($showTwo) {
                        $tC = $findCol('TOTAL');
                        if ($tC) {
                            $ref = rc_col_letter($tC['start']) . $rIdx;
                            $sheet->setCellValue($ref, $sObt ?: '');
                            rc_style($sheet, $ref, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                        }
                    }

                    $hC = $findCol('HIC');
                    if ($hC) {
                        $ref = rc_col_letter($hC['start']) . $rIdx;
                        $sheet->setCellValue($ref, $subjectHics->{$subid} ?? '');
                        // See the matching comment in the senior-format branch above.
                        $hRange = $ref . ':' . rc_col_letter($hC['start'] + $hC['span'] - 1) . $rIdx;
                        rc_style($sheet, $hRange, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                    }
                    $gMax += $sMax;
                    $gObt += $sObt;
                }
            } else {
                $sheet->setCellValue($scRef, '');
                $sheet->setCellValue($mmRef, '');
                foreach ($assessmentCols as $col) {
                    $ref = rc_col_letter($col['start']) . $rIdx;
                    if ($col['label'] === 'HIC') {
                        $ref .= ':' . rc_col_letter($col['start'] + $col['span'] - 1) . $rIdx;
                    }
                    rc_style($sheet, $ref, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                }
            }
        }

        $tR = 21;
        rc_merge_set($sheet, $tR, 2, 7, 'TOTAL', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_RIGHT, 'border' => 'all']);
        rc_merge_set($sheet, $tR, $mmCol['start'], $mmCol['start'] + $mmCol['span'] - 1, $gMax, ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        if ($useSeniorFormat) {
            rc_merge_set($sheet, $tR, 12, 16, $gObt, ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
            rc_merge_set($sheet, $tR, 17, 21, $hicData['termHic'] ?? '', ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        } else {
            foreach ($examLabels as $lab) {
                $col = $findCol($lab);
                if ($col) {
                    rc_merge_set($sheet, $tR, $col['start'], $col['start'] + $col['span'] - 1, $colTotals[$lab], ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
                }
            }
            if ($showTwo) {
                $tC = $findCol('TOTAL');
                if ($tC) {
                    rc_merge_set($sheet, $tR, $tC['start'], $tC['start'] + $tC['span'] - 1, $gObt, ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
                }
            }
            $hC = $findCol('HIC');
            if ($hC) {
                rc_merge_set($sheet, $tR, $hC['start'], $hC['start'] + $hC['span'] - 1, $hicData['termHic'] ?? '', ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
            }
        }

        $sS = 23;
        $sE = 26;
        $setupSidebarHeader = function ($r, $label) use ($sheet, $sS, $sE) {
            rc_merge_set($sheet, $r, $sS, $sE, $label, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        };

        $setupSidebarHeader(8, 'PERCENTAGE');
        $percentText = $gMax > 0 ? number_format(($gObt / $gMax) * 100, 2) . '%' : 'N/A';
        rc_merge_set($sheet, 9, $sS, $sE, $percentText, ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        $setupSidebarHeader(10, 'LEGEND');
        // A 3-row block merge (ExcelJS's mergeCells(11,sS,13,sE)).
        $legendRange = rc_col_letter($sS) . '11:' . rc_col_letter($sE) . '13';
        $sheet->mergeCells($legendRange);
        $legendRef = rc_col_letter($sS) . '11';
        $sheet->setCellValue($legendRef, "A - 80% and above\nB - 60 - 79%\nC - 40 - 59%\nD - Below 40%");
        rc_style($sheet, $legendRef, ['color' => RC_THEME_COLOR, 'size' => 10, 'halign' => Alignment::HORIZONTAL_LEFT, 'wrap' => true, 'indent' => 1]);
        // Border must be applied to the full merged range, not just the
        // top-left cell reference, or only the top-left corner renders —
        // getOutline() then draws a clean box around the range's true outer
        // edge regardless of the merge inside it.
        rc_style($sheet, $legendRange, ['border' => 'outline']);

        $setupSidebarHeader(15, 'GRADE SUBJECTS');
        for ($i = 0; $i < 6; $i++) {
            $r = 16 + $i;
            $g = $studentGradesList[$i] ?? null;
            $sheet->mergeCells(rc_range($r, $sS, $sE - 1));
            $nameRef = rc_col_letter($sS) . $r;
            $valRef = rc_col_letter($sE) . $r;
            if ($g) {
                $sheet->setCellValue($nameRef, $g['subname']);
                $sheet->setCellValue($valRef, $g['grade']);
            }
            rc_style($sheet, $nameRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1]);
            rc_style($sheet, $valRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_CENTER]);
        }
        // One outline border around the whole 6-row block (rows 16-21), same
        // reasoning as LEGEND above — replaces the previous fragile per-row
        // manual left/right/bottom-on-last-row styling.
        rc_style($sheet, rc_col_letter($sS) . '16:' . rc_col_letter($sE) . '21', ['border' => 'outline']);

        if ($studentComment && !empty(trim($studentComment['comment'] ?? '')) && trim($studentComment['comment']) !== '_') {
            $cR = 23;
            $sheet->setCellValue('B' . $cR, 'COMMENT:');
            rc_style($sheet, 'B' . $cR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_LEFT, 'valign' => Alignment::VERTICAL_TOP]);
            $sheet->mergeCells('E' . $cR . ':' . rc_col_letter($gridEndCol) . ($cR + 2));
            $sheet->setCellValue('E' . $cR, $studentComment['comment']);
            rc_style($sheet, 'E' . $cR, ['size' => 12, 'wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        }

        $sigR = 28;
        if (!empty($headerConfig['includeSignatures'])) {
            $sheet->setCellValue('B' . $sigR, 'CLASS TEACHER');
            rc_style($sheet, 'B' . $sigR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12]);
            $sheet->mergeCells(rc_col_letter($gridEndCol - 2) . $sigR . ':' . rc_col_letter($gridEndCol) . $sigR);
            $sheet->setCellValue(rc_col_letter($gridEndCol - 2) . $sigR, 'PRINCIPAL');
            rc_style($sheet, rc_col_letter($gridEndCol - 2) . $sigR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_RIGHT]);
        } else {
            $sheet->mergeCells('B' . $sigR . ':' . rc_col_letter($gridEndCol) . $sigR);
            $sheet->setCellValue('B' . $sigR, 'This is a computer generated document. No signature is required.');
            rc_style($sheet, 'B' . $sigR, ['italic' => true, 'size' => 11, 'color' => 'FF666666', 'halign' => Alignment::HORIZONTAL_CENTER]);
        }
    }

    return $spreadsheet;
}

/**
 * Ports src/lib/final-report-card-excel.ts::generateFinalExcel — same
 * per-student worksheet layout as generate_term_report_card_excel(), but
 * combines marks across every term (TERM 1 / TERM 2 / AVG columns instead
 * of a single report's exam columns) and adds a RANK/PROMOTION/REOPENS ON
 * summary row sourced from the persisted final totals.
 *
 * $input keys: students (from get_final_report_card_data, pre-filtered to
 * the selected ids), schedule, orderedSubjects, hics (stdClass keyed by
 * subid), grandThic, reopenText, comments, watermarkBase64, watermarkSize,
 * headerConfig ({includeSchool, includeBranch, includeWatermark, includeSignatures})
 */
function generate_final_report_card_excel($input) {
    $students = $input['students'];
    $schedule = $input['schedule'];
    $orderedSubjects = $input['orderedSubjects'];
    $hics = $input['hics'];
    $grandThic = $input['grandThic'];
    $reopenText = $input['reopenText'];
    $comments = $input['comments'];
    $headerConfig = $input['headerConfig'];
    $watermarkBase64 = $input['watermarkBase64'] ?? null;
    $watermarkSize = $input['watermarkSize'] ?? 350;

    $gridEndCol = 26;

    $watermarkData = null;
    if ($watermarkBase64) {
        $parts = explode(',', $watermarkBase64, 2);
        $watermarkData = base64_decode($parts[1] ?? $parts[0]);
    } else {
        $logoPath = __DIR__ . '/../assets/images/logo.gif';
        if (is_file($logoPath)) {
            $watermarkData = file_get_contents($logoPath);
        }
    }
    $gdWatermarkImage = $watermarkData ? @imagecreatefromstring($watermarkData) : false;
    if ($gdWatermarkImage !== false && (imageistruecolor($gdWatermarkImage) || imagecolortransparent($gdWatermarkImage) >= 0)) {
        // See the matching comment in generate_term_report_card_excel() above.
        imagesavealpha($gdWatermarkImage, true);
    }

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);
    $sheetIndex = 0;

    foreach ($students as $student) {
        $sheetName = preg_replace('/[\\\\\/\*\?:\[\]]/', '', substr($student['roll'] . '-' . $student['sname'], 0, 31));
        if ($sheetName === '') {
            $sheetName = 'Student' . $student['sid'];
        }

        $sheet = $spreadsheet->createSheet($sheetIndex++);
        $sheet->setTitle($sheetName);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(1);
        $margins = $sheet->getPageMargins();
        $margins->setLeft(0.2);
        $margins->setRight(0.2);
        $margins->setTop(0.3);
        $margins->setBottom(0.3);
        $margins->setHeader(0.0);
        $margins->setFooter(0.0);

        $sheet->getColumnDimension('A')->setWidth(0.54, CssDimension::UOM_INCHES);
        for ($i = 2; $i <= $gridEndCol; $i++) {
            $sheet->getColumnDimension(rc_col_letter($i))->setWidth(5.0);
        }
        // V is a thin gutter between the personal-details grid and the photo
        // block, not a normal data column — same inches-based sizing as A.
        $sheet->getColumnDimension('V')->setWidth(0.06, CssDimension::UOM_INCHES);
        for ($i = 1; $i <= 35; $i++) {
            $sheet->getRowDimension($i)->setRowHeight($i === 7 ? 5.0 : 21.0);
        }

        rc_merge_set($sheet, 1, 2, $gridEndCol, $headerConfig['includeSchool'] ? 'DR. VIRENDRA SWARUP EDUCATION CENTRE' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 24, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);
        rc_merge_set($sheet, 2, 2, $gridEndCol, $headerConfig['includeBranch'] ? 'AVADHPURI, KANPUR' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 20, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);

        if ($gdWatermarkImage !== false && !empty($headerConfig['includeWatermark'])) {
            try {
                $drawing = new MemoryDrawing();
                $drawing->setImageResource($gdWatermarkImage);
                $drawing->setRenderingFunction(MemoryDrawing::RENDERING_DEFAULT);
                $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
                $drawing->setCoordinates('F3');
                $drawing->setWidth($watermarkSize);
                $drawing->setHeight($watermarkSize);
                $drawing->setWorksheet($sheet);
            } catch (Throwable $e) {
                // Non-fatal, matches source's swallowed try/catch.
            }
        }

        // Student photo: V3:Z6 (the thin V gutter + 4 normal-width columns
        // W-Z, 4 rows tall matching the personal-details grid's height),
        // scaled to fit within that box preserving aspect ratio and centered
        // both horizontally and vertically, same reasoning as the watermark
        // above. Frame is drawn regardless of whether a photo exists.
        $sheet->mergeCells('V3:Z6');
        rc_style($sheet, 'V3:Z6', ['border' => 'outline']);
        if (!empty($student['photo'])) {
            try {
                $photoParts = explode(',', $student['photo'], 2);
                $photoBytes = isset($photoParts[1]) ? base64_decode($photoParts[1]) : false;
                $photoImage = $photoBytes !== false ? @imagecreatefromstring($photoBytes) : false;
                if ($photoImage !== false) {
                    $boxWidthPx = (0.06 * 96) + (4 * 5.0 * 7);
                    $boxHeightPx = 4 * 21.0 * 4 / 3;
                    $imgW = imagesx($photoImage);
                    $imgH = imagesy($photoImage);
                    $scale = min($boxWidthPx / $imgW, $boxHeightPx / $imgH);
                    $drawW = $imgW * $scale;
                    $drawH = $imgH * $scale;

                    $drawing = new MemoryDrawing();
                    $drawing->setImageResource($photoImage);
                    $drawing->setRenderingFunction(MemoryDrawing::RENDERING_DEFAULT);
                    $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setCoordinates('V3');
                    $drawing->setOffsetX((int) round(($boxWidthPx - $drawW) / 2));
                    $drawing->setOffsetY((int) round(($boxHeightPx - $drawH) / 2));
                    $drawing->setWidth((int) round($drawW));
                    $drawing->setHeight((int) round($drawH));
                    $drawing->setWorksheet($sheet);
                }
            } catch (Throwable $e) {
                // Non-fatal — same swallow-and-continue as the watermark.
            }
        }

        $attendance = $student['snapshot']['attendance'] ?? null;
        $headerGrid = [
            ['r' => 3, 'items' => [[2, 5, true, 'SCHOLAR NO.'], [6, 17, true, 'NAME'], [18, 21, true, 'CLASS']]],
            ['r' => 4, 'items' => [[2, 5, false, $student['schno'] ?? ''], [6, 17, false, $student['sname']], [18, 21, false, rc_roman_numeral($student['sclass'] ?? '')]]],
            ['r' => 5, 'items' => [[2, 5, true, 'TERM/YEAR'], [6, 8, true, 'ATTENDANCE'], [9, 11, true, 'D.O.B.'], [12, 14, true, 'HOUSE'], [15, 17, true, 'WEIGHT'], [18, 21, true, 'HEIGHT']]],
            ['r' => 6, 'items' => [
                [2, 5, false, 'FINAL ' . date('Y')],
                [6, 8, false, ($attendance['attendance'] ?? 'N/A') . ' / ' . ($attendance['totalattendance'] ?? 'N/A')],
                [9, 11, false, $student['dob'] ?? ''],
                [12, 14, false, $student['house'] ?? 'N/A'],
                [15, 17, false, ($student['wt'] ?? '') . ' Kg.'],
                [18, 21, false, ($student['ht'] ?? '') . ' Cm.'],
            ]],
        ];
        foreach ($headerGrid as $row) {
            foreach ($row['items'] as [$s, $e, $isLabel, $val]) {
                rc_merge_set($sheet, $row['r'], $s, $e, $val, [
                    'bold' => $isLabel, 'color' => $isLabel ? RC_THEME_COLOR : RC_DATA_COLOR, 'size' => 13,
                    'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER, 'border' => 'all',
                ]);
            }
        }

        rc_merge_set($sheet, 8, 2, 7, 'SUBJECTS', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        // MM is 2 columns (H-I); TERM 1/TERM 2/AVG/HIC keep their original
        // 3-column width and positions — only the SUBJECTS/MM boundary moves.
        $metrics = [
            ['label' => 'MM', 'start' => 8, 'span' => 1],
            ['label' => 'TERM 1', 'start' => 10, 'span' => 2],
            ['label' => 'TERM 2', 'start' => 13, 'span' => 2],
            ['label' => 'AVG', 'start' => 16, 'span' => 2],
            ['label' => 'HIC', 'start' => 19, 'span' => 2],
        ];
        foreach ($metrics as $m) {
            rc_merge_set($sheet, 8, $m['start'], $m['start'] + $m['span'], $m['label'], ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        }

        $studentMarksByTermschid = [];
        foreach ($student['marks'] as $m) {
            if ($m['marks'] !== null) {
                $studentMarksByTermschid[(int) $m['termschid']] = (int) $m['marks'];
            }
        }

        $activeSubjects = array_values(array_filter($orderedSubjects, function ($sub) use ($schedule, $studentMarksByTermschid) {
            foreach ($schedule as $sch) {
                if ((int) $sch['subid'] === (int) $sub['subid'] && isset($studentMarksByTermschid[(int) $sch['termschid']])) {
                    return true;
                }
            }
            return false;
        }));

        $getObt = function ($subid, $termid) use ($schedule, $studentMarksByTermschid) {
            $found = false;
            $obt = 0;
            foreach ($schedule as $sch) {
                if ((int) $sch['subid'] === (int) $subid && (int) $sch['termid'] === (int) $termid) {
                    $tsid = (int) $sch['termschid'];
                    if (isset($studentMarksByTermschid[$tsid])) {
                        $obt += $studentMarksByTermschid[$tsid];
                        $found = true;
                    }
                }
            }
            return $found ? $obt : null;
        };

        $mmSum = 0;
        $t1Sum = 0;
        $t2Sum = 0;
        $avgSum = 0;

        for ($rIdx = 9; $rIdx <= 20; $rIdx++) {
            $sub = $activeSubjects[$rIdx - 9] ?? null;
            $isLastInGrid = $rIdx === 20;
            $borderSides = $isLastInGrid ? ['left', 'right', 'bottom'] : ['left', 'right'];

            $sheet->mergeCells(rc_range($rIdx, 2, 7));
            foreach ([[8, 1], [10, 2], [13, 2], [16, 2], [19, 2]] as [$colStart, $span]) {
                $sheet->mergeCells(rc_range($rIdx, $colStart, $colStart + $span));
            }

            $scRef = rc_col_letter(2) . $rIdx;
            rc_style($sheet, $scRef, ['halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1, 'border' => $borderSides]);

            if ($sub) {
                $subid = (int) $sub['subid'];
                $sheet->setCellValue($scRef, $sub['subname']);
                rc_style($sheet, $scRef, ['color' => RC_THEME_COLOR, 'size' => 14]);

                $t1 = $getObt($subid, 1);
                $t2 = $getObt($subid, 2);
                $avg = (int) round((($t1 ?: 0) + ($t2 ?: 0)) / 200 * 100);
                $mmSum += 100;
                $t1Sum += $t1 ?: 0;
                $t2Sum += $t2 ?: 0;
                $avgSum += $avg;

                $hicKey = (string) $subid;
                $rowMetrics = [
                    [8, 100],
                    [10, $t1 ?? ''],
                    [13, $t2 ?? ''],
                    [16, $avg],
                    [19, $hics->$hicKey ?? ''],
                ];
                foreach ($rowMetrics as [$colStart, $val]) {
                    $ref = rc_col_letter($colStart) . $rIdx;
                    $sheet->setCellValue($ref, $val);
                    // HIC (colStart 19) needs its border on the full merged
                    // range (19-21), not just the anchor cell, or the merge's
                    // true right/outer edge never gets one.
                    $styleRef = $colStart === 19 ? ($ref . ':' . rc_col_letter($colStart + 2) . $rIdx) : $ref;
                    rc_style($sheet, $styleRef, ['size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                }
            } else {
                $sheet->setCellValue($scRef, '');
                foreach ([8, 10, 13, 16, 19] as $colStart) {
                    $ref = rc_col_letter($colStart) . $rIdx;
                    if ($colStart === 19) {
                        $ref .= ':' . rc_col_letter($colStart + 2) . $rIdx;
                    }
                    rc_style($sheet, $ref, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                }
            }
        }

        $tR = 21;
        rc_merge_set($sheet, $tR, 2, 7, 'TOTAL', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_RIGHT, 'border' => 'all']);
        $totMetrics = [[8, $mmSum, 1], [10, $t1Sum, 2], [13, $t2Sum, 2], [16, $avgSum, 2], [19, (int) round($grandThic), 2]];
        foreach ($totMetrics as [$colStart, $val, $span]) {
            rc_merge_set($sheet, $tR, $colStart, $colStart + $span, $val, ['bold' => true, 'size' => 14, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        }

        $sS = 23;
        $sE = 26;
        $setupSidebarHeader = function ($r, $label) use ($sheet, $sS, $sE) {
            rc_merge_set($sheet, $r, $sS, $sE, $label, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        };

        $setupSidebarHeader(8, 'PERCENTAGE');
        $activeCount = count($activeSubjects);
        $finalPercentText = ($mmSum > 0 && $activeCount > 0) ? number_format(($avgSum / ($activeCount * 100)) * 100, 2) . '%' : 'N/A';
        rc_merge_set($sheet, 9, $sS, $sE, $finalPercentText, ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        $setupSidebarHeader(10, 'LEGEND');
        $legendRange = rc_col_letter($sS) . '11:' . rc_col_letter($sE) . '13';
        $sheet->mergeCells($legendRange);
        $legendRef = rc_col_letter($sS) . '11';
        $sheet->setCellValue($legendRef, "A - 80% and above\nB - 60 - 79%\nC - 40 - 59%\nD - Below 40%");
        rc_style($sheet, $legendRef, ['color' => RC_THEME_COLOR, 'size' => 10, 'halign' => Alignment::HORIZONTAL_LEFT, 'wrap' => true, 'indent' => 1]);
        // See the matching comment in generate_term_report_card_excel() above.
        rc_style($sheet, $legendRange, ['border' => 'outline']);

        $setupSidebarHeader(15, 'GRADE SUBJECTS');
        $studentGradesList = $student['snapshot']['grades'] ?? [];
        for ($i = 0; $i < 6; $i++) {
            $r = 16 + $i;
            $g = $studentGradesList[$i] ?? null;
            $sheet->mergeCells(rc_range($r, $sS, $sE - 1));
            $nameRef = rc_col_letter($sS) . $r;
            $valRef = rc_col_letter($sE) . $r;
            if ($g) {
                $sheet->setCellValue($nameRef, $g['subname']);
                $sheet->setCellValue($valRef, $g['grade']);
            }
            rc_style($sheet, $nameRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1]);
            rc_style($sheet, $valRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_CENTER]);
        }
        rc_style($sheet, rc_col_letter($sS) . '16:' . rc_col_letter($sE) . '21', ['border' => 'outline']);

        $finalTotal = $student['snapshot']['total'] ?? null;
        $summaryItems = [
            ['r1' => [23, 2, 5], 'r2' => [24, 2, 5], 'label' => 'RANK', 'value' => $finalTotal['rank'] ?? 'N/A'],
            ['r1' => [23, 6, 21], 'r2' => [24, 6, 21], 'label' => 'PROMOTION', 'value' => $finalTotal['status'] ?? ''],
            ['r1' => [23, 22, 26], 'r2' => [24, 22, 26], 'label' => 'REOPENS ON', 'value' => $reopenText ?: ''],
        ];
        foreach ($summaryItems as $item) {
            rc_merge_set($sheet, $item['r1'][0], $item['r1'][1], $item['r1'][2], $item['label'], ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER, 'border' => 'all']);
            rc_merge_set($sheet, $item['r2'][0], $item['r2'][1], $item['r2'][2], $item['value'], ['size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        }

        $studentComment = null;
        $comid = $attendance['comid'] ?? null;
        if ($comid !== null) {
            foreach ($comments as $c) {
                if ((int) $c['comid'] === (int) $comid) { $studentComment = $c; break; }
            }
        }
        if ($studentComment && !empty(trim($studentComment['comment'] ?? '')) && trim($studentComment['comment']) !== '_') {
            // Ends at row 27 (cR+2) so it doesn't collide with the merged
            // CLASS TEACHER/PRINCIPAL row at 28 below.
            $cR = 25;
            $sheet->setCellValue('B' . $cR, 'COMMENT:');
            rc_style($sheet, 'B' . $cR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_LEFT, 'valign' => Alignment::VERTICAL_TOP]);
            $sheet->mergeCells('E' . $cR . ':' . rc_col_letter($gridEndCol) . ($cR + 2));
            $sheet->setCellValue('E' . $cR, $studentComment['comment']);
            rc_style($sheet, 'E' . $cR, ['size' => 11, 'wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        }

        $sigR = 28;
        if (!empty($headerConfig['includeSignatures'])) {
            $sheet->setCellValue('B' . $sigR, 'CLASS TEACHER');
            rc_style($sheet, 'B' . $sigR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12]);
            $sheet->mergeCells(rc_col_letter($gridEndCol - 2) . $sigR . ':' . rc_col_letter($gridEndCol) . $sigR);
            $sheet->setCellValue(rc_col_letter($gridEndCol - 2) . $sigR, 'PRINCIPAL');
            rc_style($sheet, rc_col_letter($gridEndCol - 2) . $sigR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_RIGHT]);
        } else {
            $sheet->mergeCells('B' . $sigR . ':' . rc_col_letter($gridEndCol) . $sigR);
            $sheet->setCellValue('B' . $sigR, 'This is a computer generated document. No signature is required.');
            rc_style($sheet, 'B' . $sigR, ['italic' => true, 'size' => 11, 'color' => 'FF666666', 'halign' => Alignment::HORIZONTAL_CENTER]);
        }
    }

    return $spreadsheet;
}
