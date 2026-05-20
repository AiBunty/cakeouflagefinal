<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Shared helper for all Excel (.xlsx) operations.
 */
class ExcelService
{
    // -----------------------------------------------------------------------
    // Streaming helpers
    // -----------------------------------------------------------------------

    /**
     * Stream a Spreadsheet object to the browser as an xlsx download.
     * Must be called before any output has been sent.
     */
    public static function streamXlsx(Spreadsheet $spreadsheet, string $filename): void
    {
        // Sanitise filename
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        exit;
    }

    /**
     * Quick convenience export: headers + rows → xlsx download.
     *
     * @param string[]   $headers   Column header labels (row 1)
     * @param array[]    $rows      Data rows (array of arrays, values in same order as headers)
     * @param string     $filename  Download filename (.xlsx appended if missing)
     * @param int[]      $colWidths Optional per-column widths (1-indexed, e.g. [1 => 20, 2 => 30])
     */
    public static function export(
        array $headers,
        array $rows,
        string $filename,
        array $colWidths = []
    ): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- Header row ---
        foreach ($headers as $colIdx => $label) {
            $col = $colIdx + 1;
            $sheet->setCellValueByColumnAndRow($col, 1, $label);
        }

        // Bold + background on header
        $lastCol = count($headers);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $headerRange = 'A1:' . $lastColLetter . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');

        // --- Data rows ---
        foreach ($rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            foreach (array_values($row) as $colIdx => $value) {
                $sheet->setCellValueByColumnAndRow($colIdx + 1, $rowNum, $value);
            }
        }

        // --- Column widths ---
        foreach ($colWidths as $colNum => $width) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((int)$colNum);
            $sheet->getColumnDimension($letter)->setWidth((float)$width);
        }

        // Auto-size columns that have no explicit width
        if (empty($colWidths)) {
            for ($c = 1; $c <= $lastCol; $c++) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }
        }

        self::streamXlsx($spreadsheet, $filename);
    }

    // -----------------------------------------------------------------------
    // Product import template with dropdown + validation
    // -----------------------------------------------------------------------

    /**
     * Build the product bulk-import template spreadsheet.
     *
     * Sheet 1 ("Import"): Headers + 2 example rows, frozen header, category dropdown.
     * Sheet 2 ("Lists"):  Hidden sheet with category slugs for data validation.
     */
    public static function buildProductTemplate(PDO $pdo): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ---- Sheet 2: Lists (hidden, data source for dropdowns) ----
        $listsSheet = new Worksheet($spreadsheet, 'Lists');
        $spreadsheet->addSheet($listsSheet);

        $slugStmt = $pdo->query(
            "SELECT slug FROM categories WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name"
        );
        $slugs = $slugStmt ? $slugStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($slugs as $i => $slug) {
            $listsSheet->setCellValueByColumnAndRow(1, $i + 1, $slug);
        }

        // Hide the Lists sheet
        $listsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // ---- Sheet 1: Import ----
        $importSheet = $spreadsheet->getSheet(0);
        $importSheet->setTitle('Import');
        $spreadsheet->setActiveSheetIndex(0);

        $headers = [
            'product_name',
            'category_slug',
            'description',
            'price',
            'discount_price',
            'sku',
            'stock',
            'tags',
            'variant_info',
            'image_url',
            'is_veg',
        ];

        $examples = [
            [
                'Belgian Velvet Truffle',
                'classic-cakes',
                'Rich dark chocolate truffle cake',
                '1200',
                '999',
                'BVT-001',
                '50',
                'bestseller|eggless',
                '500g:500,1kg:900,2kg:1600',
                '',
                '1',
            ],
            [
                'Raspberry Cocoa Bloom',
                'birthday-cakes',
                'Light cocoa sponge with raspberry cream',
                '1400',
                '',
                'RCB-002',
                '30',
                'featured|chefs_special',
                '500g:600,1kg:1100,2kg:2000',
                '',
                '1',
            ],
        ];

        // Write headers
        foreach ($headers as $colIdx => $label) {
            $importSheet->setCellValueByColumnAndRow($colIdx + 1, 1, $label);
        }

        // Style headers
        $importSheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $importSheet->freezePane('A2');

        // Write example rows
        foreach ($examples as $rowIdx => $row) {
            $rowNum = $rowIdx + 2;
            foreach ($row as $colIdx => $value) {
                $importSheet->setCellValueByColumnAndRow($colIdx + 1, $rowNum, $value);
            }
        }

        // Column widths
        $widths = [25, 25, 40, 10, 15, 15, 10, 30, 50, 50, 10];
        foreach ($widths as $i => $width) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $importSheet->getColumnDimension($letter)->setWidth($width);
        }

        // Data validation on B2:B1000 — category slug dropdown from Lists sheet
        if (!empty($slugs)) {
            $slugCount = count($slugs);
            $listFormula = 'Lists!$A$1:$A$' . $slugCount;
            for ($row = 2; $row <= 1000; $row++) {
                $validation = $importSheet->getCell('B' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true); // Note: false = show dropdown arrow
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Invalid category');
                $validation->setError('Please choose a slug from the dropdown or type a valid slug.');
                $validation->setFormula1($listFormula);
            }
        }

        // Comment on H1 (tags column) describing valid tag values
        $tagComment = $importSheet->getComment('H1');
        $tagComment->getText()->createTextRun(
            "Valid tags (pipe-separated):\n" .
            "  featured\n" .
            "  bestseller\n" .
            "  chefs_special\n" .
            "  eggless\n" .
            "  b2b\n\n" .
            "Example: featured|eggless"
        );
        $tagComment->setWidth('180pt');
        $tagComment->setHeight('120pt');

        // Comment on I1 (variant_info) explaining format
        $variantComment = $importSheet->getComment('I1');
        $variantComment->getText()->createTextRun(
            "Format: label:price pairs, comma-separated.\n" .
            "Example: 500g:500,1kg:900,2kg:1600\n\n" .
            "Leave blank for no variants."
        );
        $variantComment->setWidth('200pt');
        $variantComment->setHeight('90pt');

        // Comment on K1 (is_veg) describing values
        $vegComment = $importSheet->getComment('K1');
        $vegComment->getText()->createTextRun(
            "1 = Veg (green dot)\n" .
            "0 = Non-Veg (red dot)\n\n" .
            "Defaults to 1 (Veg) if omitted."
        );
        $vegComment->setWidth('160pt');
        $vegComment->setHeight('75pt');

        // Dropdown validation on K2:K1000 — only allow 0 or 1
        for ($row = 2; $row <= 1000; $row++) {
            $validation = $importSheet->getCell('K' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid value');
            $validation->setError('Enter 1 for Veg or 0 for Non-Veg.');
            $validation->setFormula1('"1,0"');
        }

        return $spreadsheet;
    }

    // -----------------------------------------------------------------------
    // Reading uploaded xlsx files
    // -----------------------------------------------------------------------

    /**
     * Read an uploaded xlsx (or csv) file and return rows as arrays.
     *
     * Returns an array where:
     *   - index 0 is the header row (array of strings)
     *   - index 1..n are data rows (array of scalar values)
     *
     * This mirrors the shape of repeated fgetcsv() calls, so existing
     * import logic that does $header = $rows[0]; array_shift($rows); still works.
     *
     * @param string $tmpPath  Absolute path to the uploaded temp file
     * @return array<int, array<int, mixed>>
     */
    public static function readUploadedXlsx(string $tmpPath): array
    {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        for ($rowNum = 1; $rowNum <= $highestRow; $rowNum++) {
            $row = [];
            for ($colNum = 1; $colNum <= $highestColIdx; $colNum++) {
                $row[] = $sheet->getCellByColumnAndRow($colNum, $rowNum)->getValue();
            }
            // Skip entirely empty rows
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $rows[] = $row;
        }

        $spreadsheet->disconnectWorksheets();
        return $rows;
    }
}
