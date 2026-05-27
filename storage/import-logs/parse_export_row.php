<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php parse_export_row.php <xlsxPath> <productName>\n");
    exit(2);
}

$xlsxPath = (string)$argv[1];
$productName = trim((string)$argv[2]);

if ($xlsxPath === '' || $productName === '') {
    fwrite(STDERR, "Invalid arguments\n");
    exit(2);
}

$sheet = IOFactory::load($xlsxPath)->getActiveSheet();
$maxRow = (int)$sheet->getHighestDataRow();

$found = [
    'description' => '',
    'variant_name' => '',
    'unit_type' => '',
    'is_default' => '',
];

for ($row = 2; $row <= $maxRow; $row++) {
    $rowProduct = trim((string)$sheet->getCell('A' . $row)->getValue());
    if ($rowProduct !== $productName) {
        continue;
    }

    $found['description'] = trim((string)$sheet->getCell('B' . $row)->getValue());
    $found['variant_name'] = trim((string)$sheet->getCell('D' . $row)->getValue());
    $found['unit_type'] = trim((string)$sheet->getCell('F' . $row)->getValue());
    $found['is_default'] = trim((string)$sheet->getCell('H' . $row)->getValue());
    break;
}

echo json_encode($found, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
