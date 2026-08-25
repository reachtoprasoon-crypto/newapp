<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/reporting.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login_page();
$user = current_user();
if ($user['type'] !== 'staff' || !in_array((int) $user['ttype'], [10, 5], true)) {
    http_response_code(403);
    die('Forbidden.');
}

$search = trim($_GET['q'] ?? '');
$data = get_all_students_total($mysqli);
if ($search !== '') {
    $lower = strtolower($search);
    $data = array_values(array_filter($data, function ($s) use ($lower, $search) {
        return str_contains(strtolower($s['sname']), $lower)
            || str_contains((string) $s['schno'], $search)
            || str_contains(strtolower($s['sclass']), $lower);
    }));
}

if (empty($data)) {
    http_response_code(404);
    die('No data to export.');
}

$headers = ['Sch No', 'Roll No', 'Student Name', 'Grand Total', 'Class', 'Section'];
$cols = ['A', 'B', 'C', 'D', 'E', 'F'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Students Total');

foreach ($headers as $i => $h) {
    $sheet->setCellValue($cols[$i] . '1', $h);
}
$row = 2;
foreach ($data as $s) {
    $sheet->setCellValue('A' . $row, $s['schno']);
    $sheet->setCellValue('B' . $row, $s['roll']);
    $sheet->setCellValue('C' . $row, $s['sname']);
    $sheet->setCellValue('D' . $row, $s['total_marks'] ?? 'N/A');
    $sheet->setCellValue('E' . $row, $s['classPart']);
    $sheet->setCellValue('F' . $row, $s['sectionPart']);
    $row++;
}
$lastRow = $row - 1;

$fullRange = 'A1:F' . $lastRow;
$sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($fullRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C1:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle($fullRange)->getFont()->setName('Calibri')->setSize(11);
$sheet->getStyle('A1:F1')->getFont()->setBold(true);
$sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');

$widths = ['A' => 15, 'B' => 10, 'C' => 35, 'D' => 15, 'E' => 10, 'F' => 10];
foreach ($widths as $c => $w) {
    $sheet->getColumnDimension($c)->setWidth($w);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Students_Total_Report.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
