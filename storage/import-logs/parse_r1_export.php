<?php
require __DIR__ . '/../../vendor/autoload.php';

$file = __DIR__ . '/r1-export-20260527185630.xlsx';
$name = 'R1 Matrix Product 20260527185630';

$sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
$max = $sheet->getHighestDataRow();
$found = '';

for ($r = 2; $r <= $max; $r++) {
    $productName = trim((string)$sheet->getCell('A' . $r)->getValue());
    if ($productName === $name) {
        $found = trim((string)$sheet->getCell('B' . $r)->getValue());
        break;
    }
}

echo 'EXPORT_MATCH_DESC=' . ($found !== '' ? $found : '<NOT_FOUND>') . PHP_EOL;
