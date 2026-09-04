<?php
// Exports one data-collection form's responses as a flat Excel sheet —
// Sch No/Roll/Name/Class, one column per form field, then Submitted At.
// Ports data-collection-management.tsx::handleExportExcel (client-side
// SheetJS there; done server-side here to match every other export in
// this app).

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/data_collection.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_staff_role_page([10]);
set_time_limit(300);

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
if (!$formId) {
    http_response_code(400);
    die('form_id is required.');
}

$form = db_fetch_one($mysqli, "SELECT title, fields_json FROM data_collection_forms WHERE id = ?", 'i', [$formId]);
if ($form === null) {
    http_response_code(404);
    die('Form not found.');
}

$fields = json_decode($form['fields_json'], true) ?: [];
$responses = get_data_collection_responses($mysqli, $formId);

$headers = ['Sch No', 'Roll', 'Name', 'Class'];
foreach ($fields as $f) {
    $headers[] = $f['label'];
}
$headers[] = 'Submitted At';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Responses');

$col = 1;
foreach ($headers as $h) {
    $sheet->setCellValue([$col, 1], $h);
    $col++;
}
$lastCol = count($headers);
$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);

$row = 2;
foreach ($responses as $r) {
    $col = 1;
    $sheet->setCellValue([$col++, $row], (int) $r['schno']);
    $sheet->setCellValue([$col++, $row], (int) $r['roll']);
    $sheet->setCellValue([$col++, $row], $r['sname']);
    $sheet->setCellValue([$col++, $row], $r['sclass']);
    foreach ($fields as $f) {
        $val = $r['responses'][$f['label']] ?? '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $sheet->setCellValue([$col++, $row], $val);
    }
    $submittedAt = $r['submitted_at'] ?? '';
    $sheet->setCellValue([$col++, $row], $submittedAt !== '' ? date('d-M-Y h:i A', strtotime($submittedAt)) : '');
    $row++;
}
$lastRow = $row - 1;

if ($lastRow >= 1) {
    $sheet->getStyle("A1:{$lastColLetter}1")->getFont()->setBold(true);
    $sheet->getStyle("A1:{$lastColLetter}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $sheet->getStyle("A1:{$lastColLetter}" . max($lastRow, 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    foreach (range(1, $lastCol) as $c) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

$safeTitle = preg_replace('/\s+/', '_', trim($form['title']));
$filename = "DataCollection_{$safeTitle}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
