<?php
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require 'includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ---------------------------------------------------------------------------
// Imperial weight columns — in the exact order used for import / export
// ---------------------------------------------------------------------------
$weightLabels   = ['per_piece', '0.5lb', '1lb', '1.5lb', '2lb', '2.5lb', '3lb', '3.5lb', '4lb', '4.5lb', '5lb'];
$weightHeaders  = ['Per Piece',  '0.5lb', '1lb', '1.5lb', '2lb', '2.5lb', '3lb', '3.5lb', '4lb', '4.5lb', '5lb'];

// Human-readable dietary labels (matches import round-trip)
$dietaryExportMap = [
    'regular'    => 'Regular',
    'eggless'    => 'Eggless',
    'vegan'      => 'Vegan',
    'sugar_free' => 'Sugar Free',
    'healthy'    => 'Healthy',
];

// ---------------------------------------------------------------------------
// Fetch all live products
// ---------------------------------------------------------------------------
$productSql = "
    SELECT
        p.id,
        COALESCE(parent_cat.name, '') AS category_name,
        COALESCE(sub_cat.name,    '') AS subcategory_name,
        p.name          AS product_name,
        p.is_chef_special,
        p.dietary_tag,
        p.is_veg
    FROM products p
    LEFT JOIN categories parent_cat ON parent_cat.id = p.collection_category_id
    LEFT JOIN categories sub_cat    ON sub_cat.id    = p.subcategory_id
    WHERE p.deleted_at IS NULL
    ORDER BY category_name ASC, subcategory_name ASC, p.name ASC
";

// Fetch all active variants with a positive price
$variantSql = "
    SELECT product_id, weight_or_size, ROUND(price, 2) AS price
    FROM product_variants
    WHERE is_active = 1 AND price > 0
    ORDER BY product_id ASC
";

// Build a lookup: $variantPrices[$productId][$weightKey] = price
$variantPrices = [];
$varResult = $conn->query($variantSql);
if ($varResult) {
    while ($vRow = $varResult->fetch_assoc()) {
        $pid = (int)$vRow['product_id'];
        $key = strtolower(trim($vRow['weight_or_size']));
        $variantPrices[$pid][$key] = (float)$vRow['price'];
    }
}

// ---------------------------------------------------------------------------
// Build spreadsheet
// ---------------------------------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header row: Category, Subcategory, Product Name, [11 weight cols], Chef's Special, Dietary Type, Veg
$headers = array_merge(
    ['Category', 'Subcategory', 'Product Name'],
    $weightHeaders,
    ["Chef's Special (0/1)", 'Dietary Type', 'Veg (1=Yes/0=No)']
);
$totalCols = count($headers); // should be 17

foreach ($headers as $colIdx => $label) {
    $sheet->setCellValue([$colIdx + 1, 1], $label);
}

$lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);
$sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '80001F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->freezePane('A2');

// Column widths: cat(22), subcat(22), name(32), 11×price(9), chef(14), dietary(14), veg(14)
$colWidths = [22, 22, 32];
for ($i = 0; $i < 11; $i++) {
    $colWidths[] = 9;
}
$colWidths[] = 14;
$colWidths[] = 16;
$colWidths[] = 14;

foreach ($colWidths as $idx => $width) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
    $sheet->getColumnDimension($letter)->setWidth($width);
}

// ---------------------------------------------------------------------------
// Data rows
// ---------------------------------------------------------------------------
$rowNum  = 2;
$result  = $conn->query($productSql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pid      = (int)$row['id'];
        $pvMap    = $variantPrices[$pid] ?? [];
        $dietary  = $dietaryExportMap[$row['dietary_tag'] ?? ''] ?? 'Regular';

        $colNum = 1;
        $sheet->setCellValue([$colNum++, $rowNum], $row['category_name']);
        $sheet->setCellValue([$colNum++, $rowNum], $row['subcategory_name']);
        $sheet->setCellValue([$colNum++, $rowNum], $row['product_name']);

        // 11 weight price columns
        foreach ($weightLabels as $wKey) {
            $price = $pvMap[$wKey] ?? '';
            $sheet->setCellValue([$colNum++, $rowNum], $price !== '' ? (float)$price : '');
        }

        $sheet->setCellValue([$colNum++, $rowNum], (int)$row['is_chef_special']);
        $sheet->setCellValue([$colNum++, $rowNum], $dietary);
        $sheet->setCellValue([$colNum,   $rowNum], (int)$row['is_veg']);

        $rowNum++;
    }
}

// ---------------------------------------------------------------------------
// Output XLSX
// ---------------------------------------------------------------------------
$filename = 'products-export-' . date('Ymd-His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
exit;


$sql = "
    SELECT
        COALESCE(parent_cat.name, '') AS category_name,
        COALESCE(sub_cat.name, '') AS subcategory_name,
        p.name AS product_name,
        p.is_chef_special,
        p.dietary_tag,
        p.is_veg,
        COALESCE(
            GROUP_CONCAT(
                CONCAT(COALESCE(NULLIF(pv.variant_label, ''), pv.weight_or_size), ':', ROUND(pv.price, 2))
                ORDER BY pv.is_default DESC, pv.id ASC
                SEPARATOR ' | '
            ),
            ''
        ) AS variants
    FROM products p
    LEFT JOIN categories parent_cat ON parent_cat.id = p.collection_category_id
    LEFT JOIN categories sub_cat ON sub_cat.id = p.subcategory_id
    LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
    WHERE p.deleted_at IS NULL
    GROUP BY p.id, parent_cat.name, sub_cat.name, p.name, p.is_chef_special, p.dietary_tag, p.is_veg
    ORDER BY p.name ASC
";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header row
$headers = ['Category', 'Subcategory', 'Product Name', 'Variants (label:price | label:price)', "Chef's Special (0/1)", 'Dietary Type', 'Veg (1=Yes/0=No)'];
foreach ($headers as $colIdx => $label) {
    $sheet->setCellValue([$colIdx + 1, 1], $label);
}
$sheet->getStyle('A1:G1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->freezePane('A2');

// Column widths
foreach ([1 => 22, 2 => 22, 3 => 30, 4 => 50, 5 => 14, 6 => 14, 7 => 14] as $col => $width) {
    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $sheet->getColumnDimension($letter)->setWidth($width);
}

// Data rows
$rowNum = 2;
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue([1, $rowNum], $row['category_name']);
        $sheet->setCellValue([2, $rowNum], $row['subcategory_name']);
        $sheet->setCellValue([3, $rowNum], $row['product_name']);
        $sheet->setCellValue([4, $rowNum], $row['variants']);
        $sheet->setCellValue([5, $rowNum], (int)$row['is_chef_special']);
        $sheet->setCellValue([6, $rowNum], (string)($row['dietary_tag'] ?? 'regular'));
        $sheet->setCellValue([7, $rowNum], (int)$row['is_veg']);
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
