<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(__DIR__ . '/dietary-export-before.xlsx')->getActiveSheet();
$headers = [];
for ($c = 1; $c <= 6; $c++) {
    $headers[] = (string)$sheet->getCell([$c, 1])->getValue();
}
echo implode('|', $headers), PHP_EOL;
