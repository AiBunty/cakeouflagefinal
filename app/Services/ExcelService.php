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
    /** @var array<int, string> */
    private const DEFAULT_MATRIX_SIZES = ['Per Pcs', '0.5 kg', '1 kg', '1.5 kg', '2 kg', '2.5 kg', '3 kg', '3.5 kg', '4 kg'];

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
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->setCellValue($cell, $label);
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
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . (string)$rowNum;
                $sheet->setCellValue($cell, $value);
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
        $sizeLabels = self::resolveActiveSizeLabels($pdo);

        // ---- Sheet 2: Lists (hidden, data source for dropdowns) ----
        $listsSheet = new Worksheet($spreadsheet, 'Lists');
        $spreadsheet->addSheet($listsSheet);

        $categoryStmt = $pdo->query(
            "SELECT name FROM categories WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name"
        );
        $categoryNames = $categoryStmt ? $categoryStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($categoryNames as $i => $name) {
            $cell = 'A' . (string)($i + 1);
            $listsSheet->setCellValue($cell, (string)$name);
            $listsSheet->setCellValue('B' . (string)($i + 1), (string)$name);
        }

        // Hide the Lists sheet
        $listsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // ---- Sheet 1: Import ----
        $importSheet = $spreadsheet->getSheet(0);
        $importSheet->setTitle('Import');
        $spreadsheet->setActiveSheetIndex(0);

        $headers = [
            'Product Name',
            'Category',
            'SubCategory',
            'Description',
            'Food Type',
            'Dietary Tag',
            "Chef's Special",
            'Enable Topper Selection',
            'Enable Note on Cake',
        ];
        foreach ($sizeLabels as $sizeLabel) {
            $headers[] = $sizeLabel;
        }

        $examples = [
            [
                'Belgian Velvet Truffle',
                'Cakes',
                'Classic Cakes',
                'Rich dark chocolate truffle cake',
                'veg',
                'eggless',
                'Yes',
                'Yes',
                'Yes',
            ],
            [
                'Raspberry Cocoa Bloom',
                'Cakes',
                'Birthday Cakes',
                'Light cocoa sponge with raspberry cream',
                'veg',
                'regular',
                'No',
                'Yes',
                'Yes',
            ],
        ];

        foreach ($examples as $rowIndex => $row) {
            foreach ($sizeLabels as $sizeLabel) {
                $examples[$rowIndex][] = match (strtolower(trim($sizeLabel))) {
                    'per pcs' => $rowIndex === 0 ? '120' : '',
                    '0.5 kg' => $rowIndex === 0 ? '550' : '620',
                    '1 kg' => $rowIndex === 0 ? '950' : '1120',
                    '1.5 kg' => $rowIndex === 0 ? '1320' : '1540',
                    '2 kg' => $rowIndex === 0 ? '1690' : '2020',
                    default => '',
                };
            }
        }

        // Write headers
        foreach ($headers as $colIdx => $label) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . '1';
            $importSheet->setCellValue($cell, $label);
        }

        // Style headers
        $lastHeaderColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $importSheet->getStyle('A1:' . $lastHeaderColumnLetter . '1')->applyFromArray([
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
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1) . (string)$rowNum;
                $importSheet->setCellValue($cell, $value);
            }
        }

        // Column widths
        $widths = [28, 22, 22, 42, 14, 14, 16, 24, 20];
        for ($i = 0; $i < count($sizeLabels); $i++) {
            $widths[] = 12;
        }
        foreach ($widths as $i => $width) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $importSheet->getColumnDimension($letter)->setWidth($width);
        }

        // Data validation on Category + SubCategory from hidden list sheet.
        if (!empty($categoryNames)) {
            $categoryCount = count($categoryNames);
            $categoryFormula = 'Lists!$A$1:$A$' . $categoryCount;
            $subCategoryFormula = 'Lists!$B$1:$B$' . $categoryCount;
            for ($row = 2; $row <= 1000; $row++) {
                $validation = $importSheet->getCell('B' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Invalid category');
                $validation->setError('Please choose a valid category from dropdown.');
                $validation->setFormula1($categoryFormula);

                $subValidation = $importSheet->getCell('C' . $row)->getDataValidation();
                $subValidation->setType(DataValidation::TYPE_LIST);
                $subValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $subValidation->setAllowBlank(true);
                $subValidation->setShowDropDown(true);
                $subValidation->setShowErrorMessage(true);
                $subValidation->setErrorTitle('Invalid subcategory');
                $subValidation->setError('Please choose a valid subcategory from dropdown.');
                $subValidation->setFormula1($subCategoryFormula);
            }
        }

        // Comment on Food Type column.
        $foodTypeComment = $importSheet->getComment('E1');
        $foodTypeComment->getText()->createTextRun(
            "Allowed values:\n" .
            "  veg\n" .
            "  nonveg"
        );
        $foodTypeComment->setWidth('140pt');
        $foodTypeComment->setHeight('65pt');

        // Comment on Dietary Tag column.
        $dietaryTypeComment = $importSheet->getComment('F1');
        $dietaryTypeComment->getText()->createTextRun(
            "Allowed values:\n" .
            "  regular\n" .
            "  eggless\n" .
            "  vegan\n" .
            "  sugar_free"
        );
        $dietaryTypeComment->setWidth('160pt');
        $dietaryTypeComment->setHeight('85pt');

        // Dropdown validation on Food Type: veg/nonveg
        for ($row = 2; $row <= 1000; $row++) {
            $validation = $importSheet->getCell('E' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid food type');
            $validation->setError('Enter veg or nonveg.');
            $validation->setFormula1('"veg,nonveg"');
        }

        // Dropdown validation on Dietary Tag.
        for ($row = 2; $row <= 1000; $row++) {
            $validation = $importSheet->getCell('F' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid dietary tag');
            $validation->setError('Enter one of regular, eggless, vegan, sugar_free.');
            $validation->setFormula1('"regular,eggless,vegan,sugar_free"');
        }

        // Toggle dropdowns (Yes/No).
        foreach (['G', 'H', 'I'] as $colLetter) {
            for ($row = 2; $row <= 1000; $row++) {
                $validation = $importSheet->getCell($colLetter . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Invalid toggle value');
                $validation->setError('Enter Yes or No.');
                $validation->setFormula1('"Yes,No"');
            }
        }

        return $spreadsheet;
    }

    /** @return array<int, string> */
    private static function resolveActiveSizeLabels(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query('SELECT label FROM product_size_master WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
            $labels = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $labels = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), is_array($labels) ? $labels : []), static fn(string $v): bool => $v !== ''));
            if (count($labels) > 0) {
                return $labels;
            }
        } catch (\Throwable $e) {
            // Fallback to defaults when table does not exist yet.
        }

        return self::DEFAULT_MATRIX_SIZES;
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
