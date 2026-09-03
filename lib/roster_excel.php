<?php
// Styled Excel exports for the class roster and final roster — both share a
// 2-row merged subject-group header, alternating row shading, and red/bold/
// underline highlighting for failing marks (<40%) or students failing 3+
// subjects. Ports class-roster.tsx::handleDownloadRoster and
// final-roster.tsx::handleExport.

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

function rx_col($col) {
    return Coordinate::stringFromColumnIndex($col);
}

function rx_apply_style($sheet, $ref, $opts) {
    $style = $sheet->getStyle($ref);
    $font = $style->getFont();
    $font->setBold(!empty($opts['bold']));
    if (!empty($opts['color'])) {
        $font->getColor()->setARGB($opts['color']);
    }
    if (!empty($opts['underline'])) {
        $font->setUnderline(true);
    }
    $align = $style->getAlignment();
    $align->setHorizontal($opts['halign'] ?? Alignment::HORIZONTAL_CENTER);
    $align->setVertical(Alignment::VERTICAL_CENTER);
    if (!empty($opts['wrap'])) {
        $align->setWrapText(true);
    }
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
    if (!empty($opts['fill'])) {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($opts['fill']);
    }
}

const RX_ALT_FILL = 'FFD6EEEE';
const RX_HEADER_FILL = 'FFD9E1F2';
const RX_FAIL_COLOR = 'FFFF0000';

function rx_page_setup($sheet) {
    $pageSetup = $sheet->getPageSetup();
    $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
    $pageSetup->setFitToPage(true);
    $pageSetup->setFitToWidth(1);
    $pageSetup->setFitToHeight(0);
    $sheet->setShowGridlines(false);
    $sheet->setPrintGridlines(false);
    $margins = $sheet->getPageMargins();
    $margins->setLeft(0.25)->setRight(0.25)->setTop(0.75)->setBottom(0.75)->setHeader(0.3)->setFooter(0.3);
}

// $roster: output of get_class_roster_data(). Returns a Spreadsheet.
function generate_class_roster_excel($roster, $selectedClass) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Class Roster');
    rx_page_setup($sheet);

    $col = 1;
    $sheet->setCellValue(rx_col($col) . '1', 'Roll');
    $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '2');
    rx_apply_style($sheet, rx_col($col) . '1:' . rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL]);
    $sheet->getColumnDimension(rx_col($col))->setWidth(8);
    $col++;

    $sheet->setCellValue(rx_col($col) . '1', 'Name');
    $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '2');
    rx_apply_style($sheet, rx_col($col) . '1:' . rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL, 'halign' => Alignment::HORIZONTAL_LEFT]);
    $sheet->getColumnDimension(rx_col($col))->setWidth(30);
    $col++;

    // Subject-group headers: a subject with >1 mark_ subheader keeps its Total
    // column; a single-exam subject drops the redundant total_ column, matching source.
    $subHeaderCols = []; // list of ['key'=>, 'label'=>, 'maxm'=>, 'col'=>]
    foreach ($roster['header'] as $mainHeader) {
        $markCount = count(array_filter($mainHeader['subHeaders'], fn($sh) => str_starts_with($sh['key'], 'mark_')));
        $finalSubHeaders = $markCount > 1
            ? $mainHeader['subHeaders']
            : array_values(array_filter($mainHeader['subHeaders'], fn($sh) => !str_starts_with($sh['key'], 'total_')));

        $startCol = $col;
        $label = $mainHeader['subshort'] ?? $mainHeader['label'];
        $sheet->setCellValue(rx_col($startCol) . '1', $label);
        if (count($finalSubHeaders) > 1) {
            $sheet->mergeCells(rx_col($startCol) . '1:' . rx_col($startCol + count($finalSubHeaders) - 1) . '1');
        }
        rx_apply_style($sheet, rx_col($startCol) . '1:' . rx_col($startCol + max(0, count($finalSubHeaders) - 1)) . '1', ['bold' => true, 'fill' => RX_HEADER_FILL]);

        foreach ($finalSubHeaders as $sh) {
            $sheet->setCellValue(rx_col($col) . '2', $sh['label']);
            rx_apply_style($sheet, rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL]);
            $sheet->getColumnDimension(rx_col($col))->setWidth(10);
            $subHeaderCols[] = ['key' => $sh['key'], 'maxm' => $sh['maxm'] ?? null, 'col' => $col];
            $col++;
        }
    }

    $gradeCols = [];
    foreach ($roster['gradeSubjects'] as $gs) {
        $label = $gs['subshort'] ?: $gs['subname'];
        $sheet->setCellValue(rx_col($col) . '1', $label);
        $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '2');
        rx_apply_style($sheet, rx_col($col) . '1:' . rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL]);
        $sheet->getColumnDimension(rx_col($col))->setWidth(15);
        $gradeCols[] = ['subid' => $gs['subid'], 'col' => $col];
        $col++;
    }

    foreach (['Attendance', 'Comment ID'] as $label) {
        $sheet->setCellValue(rx_col($col) . '1', $label);
        $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '2');
        rx_apply_style($sheet, rx_col($col) . '1:' . rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL]);
        $sheet->getColumnDimension(rx_col($col))->setWidth(15);
        $col++;
    }
    $attendanceCol = $col - 2;
    $commentCol = $col - 1;

    $row = 3;
    foreach ($roster['studentData'] as $studentIndex => $student) {
        $isAlt = $studentIndex % 2 !== 0;

        $failingSubjectsCount = 0;
        foreach ($roster['header'] as $mainHeader) {
            if (empty($mainHeader['subid']) || $mainHeader['label'] === 'Grand Total' || $mainHeader['label'] === 'Percentage') {
                continue;
            }
            $totalHeader = null;
            foreach ($mainHeader['subHeaders'] as $sh) {
                if ($sh['key'] === 'total_' . $mainHeader['subid']) { $totalHeader = $sh; break; }
            }
            if ($totalHeader && !empty($totalHeader['maxm'])) {
                $score = $student['total_' . $mainHeader['subid']] ?? null;
                $hasMarks = false;
                foreach ($student['marks'] as $m) {
                    if ($m['subid'] === $mainHeader['subid'] && $m['marksObtained'] !== null) { $hasMarks = true; break; }
                }
                if ($hasMarks && $score !== null && $score < ($totalHeader['maxm'] * 0.4)) {
                    $failingSubjectsCount++;
                }
            }
        }
        $isFailingStudent = $failingSubjectsCount >= 3;
        $grandTotalFailing = is_numeric($student['percentage'] ?? null) && $student['percentage'] < 40;

        $sheet->setCellValue('A' . $row, $student['roll']);
        rx_apply_style($sheet, 'A' . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);

        $sheet->setCellValue('B' . $row, $student['sname']);
        rx_apply_style($sheet, 'B' . $row, ['halign' => Alignment::HORIZONTAL_LEFT, 'fill' => $isAlt ? RX_ALT_FILL : null, 'bold' => $isFailingStudent, 'color' => $isFailingStudent ? RX_FAIL_COLOR : null, 'underline' => $isFailingStudent]);

        foreach ($subHeaderCols as $shc) {
            $value = $student[$shc['key']] ?? 'N/A';
            $isTotal = str_starts_with($shc['key'], 'total_') || $shc['key'] === 'grandTotal';
            $isPercentage = $shc['key'] === 'percentage';
            $isRank = $shc['key'] === 'rank';

            $isFailingMark = false;
            if ($shc['key'] === 'grandTotal' || $shc['key'] === 'percentage') {
                $isFailingMark = $grandTotalFailing;
            } elseif (!$isRank && is_numeric($value) && !empty($shc['maxm'])) {
                $isFailingMark = $value < ($shc['maxm'] * 0.4);
            }

            $ref = rx_col($shc['col']) . $row;
            $sheet->setCellValue($ref, $value);
            rx_apply_style($sheet, $ref, [
                'fill' => $isAlt ? RX_ALT_FILL : null,
                'bold' => $isTotal || $isPercentage || $isRank || $isFailingMark,
                'color' => $isFailingMark ? RX_FAIL_COLOR : null,
                'underline' => $isFailingMark,
            ]);
        }

        foreach ($gradeCols as $gc) {
            $ref = rx_col($gc['col']) . $row;
            $sheet->setCellValue($ref, $roster['studentGrades'][$student['sid']][$gc['subid']] ?? 'N/A');
            rx_apply_style($sheet, $ref, ['fill' => $isAlt ? RX_ALT_FILL : null]);
        }

        $attInfo = $roster['studentAttendance'][$student['sid']] ?? null;
        $attText = $attInfo ? (($attInfo['attendance'] ?? 'N/A') . '/' . ($attInfo['totalattendance'] ?? 'N/A')) : 'N/A';
        $sheet->setCellValue(rx_col($attendanceCol) . $row, $attText);
        rx_apply_style($sheet, rx_col($attendanceCol) . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);

        $sheet->setCellValue(rx_col($commentCol) . $row, $attInfo['comid'] ?? 'N/A');
        rx_apply_style($sheet, rx_col($commentCol) . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);

        $row++;
    }

    return $spreadsheet;
}

// $roster: output of get_final_roster_data(). Returns a Spreadsheet.
function generate_final_roster_excel($roster, $selectedClass) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Final Roster');

    $fixedHeaders = ['Sch No', 'Roll', 'Name'];
    foreach ($fixedHeaders as $i => $label) {
        $c = $i + 1;
        $sheet->setCellValue(rx_col($c) . '1', $label);
        $sheet->mergeCells(rx_col($c) . '1:' . rx_col($c) . '2');
        rx_apply_style($sheet, rx_col($c) . '1:' . rx_col($c) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);
    }
    $sheet->getColumnDimension('A')->setWidth(12);
    $sheet->getColumnDimension('B')->setWidth(8);
    $sheet->getColumnDimension('C')->setWidth(30);

    $col = 4;
    $subHeaderCols = [];
    foreach ($roster['header'] as $mainHeader) {
        $startCol = $col;
        $sheet->setCellValue(rx_col($startCol) . '1', $mainHeader['label']);
        if (count($mainHeader['subHeaders']) > 1) {
            $sheet->mergeCells(rx_col($startCol) . '1:' . rx_col($startCol + count($mainHeader['subHeaders']) - 1) . '1');
        }
        rx_apply_style($sheet, rx_col($startCol) . '1:' . rx_col($startCol + max(0, count($mainHeader['subHeaders']) - 1)) . '1', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);

        foreach ($mainHeader['subHeaders'] as $sh) {
            $sheet->setCellValue(rx_col($col) . '2', $sh['label']);
            rx_apply_style($sheet, rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);
            $sheet->getColumnDimension(rx_col($col))->setWidth(12);
            $subHeaderCols[] = ['key' => $sh['key'], 'maxm' => $sh['maxm'] ?? 100, 'label' => $sh['label'], 'col' => $col];
            $col++;
        }
    }

    $gradeCols = [];
    foreach ($roster['gradeSubjects'] as $gs) {
        $sheet->setCellValue(rx_col($col) . '1', $gs['subshort'] ?: $gs['subname']);
        $sheet->setCellValue(rx_col($col) . '2', 'Grade');
        $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '1');
        rx_apply_style($sheet, rx_col($col) . '1', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);
        rx_apply_style($sheet, rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);
        $sheet->getColumnDimension(rx_col($col))->setWidth(12);
        $gradeCols[] = ['subid' => $gs['subid'], 'col' => $col];
        $col++;
    }

    foreach (['Attendance', 'cmid'] as $label) {
        $sheet->setCellValue(rx_col($col) . '1', $label);
        $sheet->mergeCells(rx_col($col) . '1:' . rx_col($col) . '2');
        rx_apply_style($sheet, rx_col($col) . '1:' . rx_col($col) . '2', ['bold' => true, 'fill' => RX_HEADER_FILL, 'wrap' => true]);
        $sheet->getColumnDimension(rx_col($col))->setWidth(12);
        $col++;
    }
    $attendanceCol = $col - 2;
    $cmidCol = $col - 1;

    $row = 3;
    foreach ($roster['studentData'] as $rowIndex => $student) {
        $isAlt = $rowIndex % 2 !== 0;

        $sheet->setCellValue('A' . $row, $student['schno']);
        rx_apply_style($sheet, 'A' . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);
        $sheet->setCellValue('B' . $row, $student['roll']);
        rx_apply_style($sheet, 'B' . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);
        $sheet->setCellValue('C' . $row, $student['sname']);
        rx_apply_style($sheet, 'C' . $row, ['halign' => Alignment::HORIZONTAL_LEFT, 'fill' => $isAlt ? RX_ALT_FILL : null]);

        foreach ($subHeaderCols as $shc) {
            $val = $student[$shc['key']] ?? null;
            $max = $shc['maxm'] ?: 100;
            $isNumeric = is_numeric($val);

            $isFailing = false;
            if ($shc['key'] === 'grandTotal' || $shc['key'] === 'percentage') {
                $isFailing = is_numeric($student['percentage'] ?? null) && $student['percentage'] < 40;
            } elseif ($shc['key'] === 'rank') {
                $isFailing = false;
            } else {
                $isFailing = $isNumeric && $max > 0 && $val < ($max * 0.4);
            }

            $isCumulative = in_array($shc['label'], ['Total', 'Avg', '%', 'Rk'], true);

            $ref = rx_col($shc['col']) . $row;
            $sheet->setCellValue($ref, $val ?? 'N/A');
            rx_apply_style($sheet, $ref, [
                'fill' => $isAlt ? RX_ALT_FILL : null,
                'bold' => $isCumulative || $isFailing,
                'color' => $isFailing ? RX_FAIL_COLOR : null,
                'underline' => $isFailing,
            ]);
        }

        foreach ($gradeCols as $gc) {
            $ref = rx_col($gc['col']) . $row;
            $sheet->setCellValue($ref, $student['grade_' . $gc['subid']] ?? 'N/A');
            rx_apply_style($sheet, $ref, ['fill' => $isAlt ? RX_ALT_FILL : null]);
        }

        $sheet->setCellValue(rx_col($attendanceCol) . $row, $student['attendance'] ?? 'N/A');
        rx_apply_style($sheet, rx_col($attendanceCol) . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);
        $sheet->setCellValue(rx_col($cmidCol) . $row, $student['cmid'] ?? 'N/A');
        rx_apply_style($sheet, rx_col($cmidCol) . $row, ['fill' => $isAlt ? RX_ALT_FILL : null]);

        $row++;
    }

    return $spreadsheet;
}
