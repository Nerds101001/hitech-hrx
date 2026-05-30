<?php
require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileType = 'Xlsx';
$inputFileName = 'TRAVELLING FORMAT--hi tech (1).xlsx';
$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($inputFileName);
$sheet = $spreadsheet->getActiveSheet();

foreach ($sheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false);
    $rowData = [];
    foreach ($cellIterator as $cell) {
        $rowData[] = $cell->getValue();
    }
    echo implode(" | ", $rowData) . "\n";
    if ($row->getRowIndex() > 15) break;
}
