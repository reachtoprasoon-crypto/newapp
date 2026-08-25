<?php
// Ports src/lib/term-report-card-excel.ts::generateTermExcel — one worksheet
// per student, precisely laid out (merged cells, borders, watermark image,
// senior vs. junior subject-grid format) rather than the plain roster table
// report_card.php renders. Uses PhpSpreadsheet (ExcelJS's closest PHP
// equivalent) via Composer.

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;

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

function rc_style($sheet, $range, $opts) {
    $style = $sheet->getStyle($range);
    if (isset($opts['bold']) || isset($opts['color']) || isset($opts['size']) || isset($opts['italic'])) {
        $font = $style->getFont();
        if (isset($opts['bold'])) $font->setBold($opts['bold']);
        if (isset($opts['italic'])) $font->setItalic($opts['italic']);
        if (isset($opts['color'])) $font->getColor()->setARGB($opts['color']);
        if (isset($opts['size'])) $font->setSize($opts['size']);
    }
    if (isset($opts['halign']) || isset($opts['valign']) || isset($opts['wrap']) || isset($opts['indent'])) {
        $align = $style->getAlignment();
        if (isset($opts['halign'])) $align->setHorizontal($opts['halign']);
        if (isset($opts['valign'])) $align->setVertical($opts['valign']);
        if (isset($opts['wrap'])) $align->setWrapText($opts['wrap']);
        if (isset($opts['indent'])) $align->setIndent($opts['indent']);
    }
    if (!empty($opts['border'])) {
        rc_apply_border($style->getBorders(), $opts['border']);
    }
}

// $sides: array subset of ['top','bottom','left','right'] or 'all'
function rc_apply_border($borders, $sides) {
    if ($sides === 'all') {
        $sides = ['top', 'bottom', 'left', 'right'];
    }
    foreach ($sides as $side) {
        $getter = 'get' . ucfirst($side);
        $borders->$getter()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(RC_DATA_COLOR);
    }
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

    preg_match('/\d+/', $selectedClass, $classNumMatch);
    $classNum = $classNumMatch ? (int) $classNumMatch[0] : 0;
    $useSeniorFormat = $classNum >= 9 && $classNum <= 12;

    $spreadsheet = new Spreadsheet();
    $spreadsheet->removeSheetByIndex(0);
    $sheetIndex = 0;

    foreach ($students as $student) {
        $sid = (int) $student['sid'];
        $studentDetails = null;
        foreach ($classStudents as $cs) {
            if ((int) $cs['sid'] === $sid) { $studentDetails = $cs; break; }
        }
        $studentAttendance = null;
        foreach ($attendanceData as $a) {
            if ((int) $a['sid'] === $sid) { $studentAttendance = $a; break; }
        }
        $studentComment = null;
        if ($studentAttendance && $studentAttendance['comid'] !== null) {
            foreach ($comments as $c) {
                if ((int) $c['comid'] === (int) $studentAttendance['comid']) { $studentComment = $c; break; }
            }
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

        for ($i = 1; $i <= $gridEndCol; $i++) {
            $width = ($i === 1) ? 2.0 : (($i === 22) ? 1.0 : 5.0);
            $sheet->getColumnDimension(rc_col_letter($i))->setWidth($width);
        }
        for ($i = 1; $i <= 35; $i++) {
            $sheet->getRowDimension($i)->setRowHeight($i === 7 ? 5.0 : 21.0);
        }

        rc_merge_set($sheet, 1, 2, $gridEndCol, $headerConfig['includeSchool'] ? 'DR. VIRENDRA SWARUP EDUCATION CENTRE' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 24, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);
        rc_merge_set($sheet, 2, 2, $gridEndCol, $headerConfig['includeBranch'] ? 'AVADHPURI, KANPUR' : '', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 20, 'halign' => Alignment::HORIZONTAL_CENTER, 'valign' => Alignment::VERTICAL_CENTER]);

        if ($watermarkData && !empty($headerConfig['includeWatermark'])) {
            try {
                $gdImage = @imagecreatefromstring($watermarkData);
                if ($gdImage !== false) {
                    $drawing = new MemoryDrawing();
                    $drawing->setImageResource($gdImage);
                    $drawing->setRenderingFunction(MemoryDrawing::RENDERING_DEFAULT);
                    $drawing->setMimeType(MemoryDrawing::MIMETYPE_DEFAULT);
                    $drawing->setCoordinates('G6');
                    $drawing->setWidth($watermarkSize);
                    $drawing->setHeight($watermarkSize);
                    $drawing->setWorksheet($sheet);
                }
            } catch (Throwable $e) {
                // Non-fatal, matches source's swallowed try/catch.
            }
        }

        $headerGrid = [
            ['r' => 3, 'items' => [[2, 5, true, 'SCHOLAR NO.'], [6, 21, true, 'NAME'], [22, 26, true, 'CLASS']]],
            ['r' => 4, 'items' => [[2, 5, false, $student['schno'] ?? ''], [6, 21, false, $student['sname']], [22, 26, false, rc_roman_numeral($studentDetails['sclass'] ?? '')]]],
            ['r' => 5, 'items' => [[2, 5, true, 'TERM/YEAR'], [6, 9, true, 'ATTENDANCE'], [10, 13, true, 'D.O.B.'], [14, 17, true, 'HOUSE'], [18, 21, true, 'WEIGHT'], [22, 26, true, 'HEIGHT']]],
            ['r' => 6, 'items' => [
                [2, 5, false, $customTermLabel ?: ($selectedTermName . ' ' . date('Y'))],
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

        rc_merge_set($sheet, 8, 2, 6, 'SUBJECTS', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

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

        $assessmentCols = [];
        if ($useSeniorFormat) {
            $mmCol = $setupMetricHeader('MM', 7, 5);
            $assessmentCols[] = $setupMetricHeader('EXAM', 12, 5);
            $assessmentCols[] = $setupMetricHeader('HIC', 17, 5);
        } elseif ($showTwo) {
            $mmCol = $setupMetricHeader('MM', 7, 3);
            $assessmentCols[] = $setupMetricHeader($examLabels[0], 10, 3);
            $assessmentCols[] = $setupMetricHeader($examLabels[1], 13, 3);
            $assessmentCols[] = $setupMetricHeader('TOTAL', 16, 3);
            $assessmentCols[] = $setupMetricHeader('HIC', 19, 3);
        } else {
            $mmCol = $setupMetricHeader('MM', 7, 3);
            $labelStr = $examLabels[0] ?? 'EXAM';
            $assessmentCols[] = $setupMetricHeader($labelStr, 10, 6);
            $assessmentCols[] = $setupMetricHeader('HIC', 16, 6);
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

            $sheet->mergeCells(rc_range($rIdx, 2, 6));
            $sheet->mergeCells(rc_range($rIdx, $mmCol['start'], $mmCol['start'] + $mmCol['span'] - 1));
            foreach ($assessmentCols as $col) {
                $sheet->mergeCells(rc_range($rIdx, $col['start'], $col['start'] + $col['span'] - 1));
            }

            $scRef = rc_col_letter(2) . $rIdx;
            $mmRef = rc_col_letter($mmCol['start']) . $rIdx;
            rc_style($sheet, $scRef, ['halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1, 'border' => $borderSides]);
            rc_style($sheet, $mmRef, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);

            if ($sub) {
                $subid = $sub['subid'];
                $sheet->setCellValue($scRef, $sub['label']);
                rc_style($sheet, $scRef, ['color' => RC_THEME_COLOR, 'size' => 12]);

                if ($useSeniorFormat) {
                    $sMax = 0;
                    foreach ($sub['subHeaders'] as $sh) if (str_starts_with($sh['key'], 'mark_')) $sMax += ($sh['maxm'] ?? 0);
                    $sObt = $student['total_' . $subid] ?? 0;

                    $sheet->setCellValue($mmRef, $sMax ?: '');
                    $exRef = rc_col_letter(12) . $rIdx;
                    $sheet->setCellValue($exRef, $sObt ?: '');
                    rc_style($sheet, $exRef, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                    $hiRef = rc_col_letter(17) . $rIdx;
                    $sheet->setCellValue($hiRef, $subjectHics->{$subid} ?? '');
                    rc_style($sheet, $hiRef, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);

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
                            rc_style($sheet, $dataRef, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                        }
                    }
                    $sheet->setCellValue($mmRef, $sMax ?: '');

                    if ($showTwo) {
                        $tC = $findCol('TOTAL');
                        if ($tC) {
                            $ref = rc_col_letter($tC['start']) . $rIdx;
                            $sheet->setCellValue($ref, $sObt ?: '');
                            rc_style($sheet, $ref, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                        }
                    }

                    $hC = $findCol('HIC');
                    if ($hC) {
                        $ref = rc_col_letter($hC['start']) . $rIdx;
                        $sheet->setCellValue($ref, $subjectHics->{$subid} ?? '');
                        rc_style($sheet, $ref, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                    }
                    $gMax += $sMax;
                    $gObt += $sObt;
                }
            } else {
                $sheet->setCellValue($scRef, '');
                $sheet->setCellValue($mmRef, '');
                foreach ($assessmentCols as $col) {
                    $ref = rc_col_letter($col['start']) . $rIdx;
                    rc_style($sheet, $ref, ['halign' => Alignment::HORIZONTAL_CENTER, 'border' => $borderSides]);
                }
            }
        }

        $tR = 21;
        rc_merge_set($sheet, $tR, 2, 6, 'TOTAL', ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_RIGHT, 'border' => 'all']);
        rc_merge_set($sheet, $tR, $mmCol['start'], $mmCol['start'] + $mmCol['span'] - 1, $gMax, ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);

        if ($useSeniorFormat) {
            rc_merge_set($sheet, $tR, 12, 16, $gObt, ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
            rc_merge_set($sheet, $tR, 17, 21, $hicData['termHic'] ?? '', ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
        } else {
            foreach ($examLabels as $lab) {
                $col = $findCol($lab);
                if ($col) {
                    rc_merge_set($sheet, $tR, $col['start'], $col['start'] + $col['span'] - 1, $colTotals[$lab], ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
                }
            }
            if ($showTwo) {
                $tC = $findCol('TOTAL');
                if ($tC) {
                    rc_merge_set($sheet, $tR, $tC['start'], $tC['start'] + $tC['span'] - 1, $gObt, ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
                }
            }
            $hC = $findCol('HIC');
            if ($hC) {
                rc_merge_set($sheet, $tR, $hC['start'], $hC['start'] + $hC['span'] - 1, $hicData['termHic'] ?? '', ['bold' => true, 'size' => 12, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => 'all']);
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
        $sheet->mergeCells(rc_col_letter($sS) . '11:' . rc_col_letter($sE) . '13');
        $legendRef = rc_col_letter($sS) . '11';
        $sheet->setCellValue($legendRef, "A - 80% and above\nB - 60 - 79%\nC - 40 - 59%\nD - Below 40%");
        rc_style($sheet, $legendRef, ['color' => RC_THEME_COLOR, 'size' => 10, 'wrap' => true, 'indent' => 1, 'border' => 'all']);

        $setupSidebarHeader(15, 'GRADE SUBJECTS');
        for ($i = 0; $i < 6; $i++) {
            $r = 16 + $i;
            $g = $studentGradesList[$i] ?? null;
            $isLastRow = $i === 5;
            $sheet->mergeCells(rc_range($r, $sS, $sE - 1));
            $nameRef = rc_col_letter($sS) . $r;
            $valRef = rc_col_letter($sE) . $r;
            if ($g) {
                $sheet->setCellValue($nameRef, $g['subname']);
                $sheet->setCellValue($valRef, $g['grade']);
            }
            rc_style($sheet, $nameRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_LEFT, 'indent' => 1, 'border' => $isLastRow ? ['left', 'bottom'] : ['left']]);
            rc_style($sheet, $valRef, ['size' => 11, 'halign' => Alignment::HORIZONTAL_CENTER, 'border' => $isLastRow ? ['right', 'bottom'] : ['right']]);
        }

        if ($studentComment && !empty(trim($studentComment['comment'] ?? '')) && trim($studentComment['comment']) !== '_') {
            $cR = 24;
            $sheet->setCellValue('B' . $cR, 'COMMENT:');
            rc_style($sheet, 'B' . $cR, ['bold' => true, 'color' => RC_THEME_COLOR, 'size' => 12, 'halign' => Alignment::HORIZONTAL_LEFT]);
            $sheet->mergeCells('E' . $cR . ':' . rc_col_letter($gridEndCol) . ($cR + 2));
            $sheet->setCellValue('E' . $cR, $studentComment['comment']);
            rc_style($sheet, 'E' . $cR, ['size' => 12, 'wrap' => true, 'valign' => Alignment::VERTICAL_TOP]);
        }

        $sigR = 30;
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
