<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$sql = "
    SELECT
        p.id,
        p.name AS product_name,
        COALESCE(NULLIF(p.description, ''), p.long_description, p.short_description, '') AS description,
        COALESCE(parent_cat.name, '') AS category_name,
        COALESCE(NULLIF(pv.variant_name, ''), pv.variant_label, pv.weight_or_size, '') AS variant_name,
        ROUND(pv.price, 2) AS price,
        COALESCE(NULLIF(pv.unit_type, ''), 'custom') AS unit_type,
        COALESCE(NULLIF(pv.sku, ''), pv.sku_suffix, '') AS variant_sku,
        pv.is_default
    FROM products p
    LEFT JOIN categories parent_cat ON parent_cat.id = p.collection_category_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1 AND pv.price > 0
    WHERE p.deleted_at IS NULL
    ORDER BY category_name ASC, product_name ASC, pv.is_default DESC, pv.id ASC
";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = ['Product Name', 'Description', 'Category', 'Variant Name', 'Price', 'Unit Type', 'Variant SKU', 'Default Variant (1/0)'];
foreach ($headers as $colIdx => $label) {
    $sheet->setCellValue([$colIdx + 1, 1], $label);
}

$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '80001F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->freezePane('A2');

$colWidths = [32, 42, 22, 20, 10, 14, 22, 18];
foreach ($colWidths as $idx => $width) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
    $sheet->getColumnDimension($letter)->setWidth($width);
}

$rowNum = 2;
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue([1, $rowNum], $row['product_name']);
        $sheet->setCellValue([2, $rowNum], $row['description']);
        $sheet->setCellValue([3, $rowNum], $row['category_name']);
        $sheet->setCellValue([4, $rowNum], $row['variant_name'] !== '' ? $row['variant_name'] : 'Base');
        $sheet->setCellValue([5, $rowNum], (float)($row['price'] ?? 0));
        $sheet->setCellValue([6, $rowNum], $row['unit_type']);
        $sheet->setCellValue([7, $rowNum], $row['variant_sku']);
        $sheet->setCellValue([8, $rowNum], (int)($row['is_default'] ?? 0));
        $rowNum++;
    }
}

$filename = 'products-export-' . date('Ymd-His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;
