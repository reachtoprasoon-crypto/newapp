<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../../lib/final_results.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login_page();
set_time_limit(300);
$user = current_user();
if ($user['type'] !== 'staff') {
    http_response_code(403);
    die('Forbidden.');
}

$sclass = trim($_GET['sclass'] ?? '');
if ($sclass === '') {
    http_response_code(400);
    die('sclass is required.');
}

require_class_access_page($mysqli, $sclass);

$data = get_promotion_data($mysqli, $sclass);
if (empty($data)) {
    http_response_code(404);
    die('No students found for this class.');
}

$headers = ['Roll No', 'Sch No', 'Student Name', 'Official Status'];
$cols = ['A', 'B', 'C', 'D'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Official Promotion List');

foreach ($headers as $i => $h) {
    $sheet->setCellValue($cols[$i] . '1', $h);
}
$row = 2;
foreach ($data as $s) {
    $sheet->setCellValue('A' . $row, $s['roll']);
    $sheet->setCellValue('B' . $row, $s['schno']);
    $sheet->setCellValue('C' . $row, $s['sname']);
    $sheet->setCellValue('D' . $row, $s['status'] ?: 'PENDING');
    $row++;
}
$lastRow = $row - 1;

$fullRange = 'A1:D' . $lastRow;
$sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle($fullRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C1:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle($fullRange)->getFont()->setName('Calibri')->setSize(11);
$sheet->getStyle('A1:D1')->getFont()->setBold(true);
$sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');

$widths = ['A' => 10, 'B' => 15, 'C' => 35, 'D' => 25];
foreach ($widths as $c => $w) {
    $sheet->getColumnDimension($c)->setWidth($w);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Promotion_Records_Class_' . $sclass . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
