<?php
// Ports aptitude-management.tsx::exportLogsheet — single-sheet styled export
// (header fill, total-column shading, borders, column widths), via PhpSpreadsheet.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/aptitude.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login_page();
set_time_limit(300);
if ((int) current_user()['ttype'] !== 10) {
    http_response_code(403);
    die('Forbidden.');
}

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    http_response_code(400);
    die('sclass is required.');
}

$data = get_aptitude_logsheet_data($mysqli, $sclass);
if (empty($data)) {
    http_response_code(404);
    die('No data available to generate logsheet.');
}

$headers = ['Roll No', 'Sch No', 'Student Name', 'Aptitude Marks', 'Mathematics Avg (%)', 'Computer App Avg (%)', 'Grand Total'];
$cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Aptitude Logsheet');

foreach ($headers as $i => $h) {
    $sheet->setCellValue($cols[$i] . '1', $h);
}
$rowNum = 2;
foreach ($data as $r) {
    $sheet->setCellValue('A' . $rowNum, $r['roll']);
    $sheet->setCellValue('B' . $rowNum, $r['schno']);
    $sheet->setCellValue('C' . $rowNum, $r['sname']);
    $sheet->setCellValue('D' . $rowNum, $r['aptitude'] ?? 'N/A');
    $sheet->setCellValue('E' . $rowNum, $r['mathsAvg'] ?? 'N/A');
    $sheet->setCellValue('F' . $rowNum, $r['compAvg'] ?? 'N/A');
    $sheet->setCellValue('G' . $rowNum, $r['total'] ?? 'N/A');
    $rowNum++;
}
$lastRow = $rowNum - 1;

$fullRange = 'A1:G' . $lastRow;
$sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($fullRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C1:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle($fullRange)->getFont()->setName('Calibri')->setSize(11);

$sheet->getStyle('A1:G1')->getFont()->setBold(true);
$sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');

$sheet->getStyle('G1:G' . $lastRow)->getFont()->setBold(true);
$sheet->getStyle('G2:G' . $lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');

$widths = [10, 15, 35, 15, 20, 20, 15];
foreach ($widths as $i => $w) {
    $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
}

$filename = 'Aptitude_Logsheet_' . $sclass . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
