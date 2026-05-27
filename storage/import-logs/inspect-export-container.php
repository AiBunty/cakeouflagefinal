<?php
require '/var/www/html/vendor/autoload.php';
$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('/var/www/html/storage/import-logs/dietary-export-before.xlsx')->getActiveSheet();
$headers = [];
for ($c = 1; $c <= 6; $c++) {
    $headers[] = (string)$sheet->getCell([$c, 1])->getValue();
}
echo implode('|', $headers), PHP_EOL;
