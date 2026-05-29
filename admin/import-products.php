<?php
// ============================================================
//  Import Products — master-of-truth catalog upload
//  Phases 4 + 5 + 7 of the catalog redesign
// ============================================================
$pageTitle = "Import Products";
require_once __DIR__ . '/includes/auth.php';
require_permission_for_current_admin_page();

require_once __DIR__ . '/includes/db.php';       // $conn (MySQLi) + Env loaded
require_once __DIR__ . '/../vendor/autoload.php'; // Composer PSR-4

use App\Core\Database;
use App\Core\FileCache;
use App\Services\ProductImportService;

const VARIANT_UNIT_TYPES = ['size', 'weight', 'piece', 'custom'];

// ============================================================
//  Helpers
// ============================================================

function importSlugify(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    return $slug !== '' ? $slug : 'item';
}

function processRow(
    \PDO $pdo,
    string $categoryName,
    string $productName,
    string $description,
    array $variants,
    string &$action = ''
): int|false {
    if ($categoryName === '' || $productName === '' || $variants === []) {
        return false;
    }

    $categoryStmt = $pdo->prepare(
        'SELECT id FROM categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND parent_id IS NULL AND deleted_at IS NULL LIMIT 1'
    );
    $categoryStmt->execute([$categoryName]);
    $categoryId = (int)($categoryStmt->fetchColumn() ?: 0);
    if ($categoryId <= 0) {
        $baseSlug = importSlugify($categoryName);
        $slug = $baseSlug;
        $suffix = 1;
        $slugStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
        $slugStmt->execute([$slug]);
        while ($slugStmt->fetchColumn()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
            $slugStmt->execute([$slug]);
        }

        $insertCategory = $pdo->prepare('INSERT INTO categories (name, slug, parent_id, is_active) VALUES (?, ?, NULL, 1)');
        $insertCategory->execute([$categoryName, $slug]);
        $categoryId = (int)$pdo->lastInsertId();
    }

    $productStmt = $pdo->prepare('SELECT id, sku FROM products WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND collection_category_id = ? LIMIT 1');
    $productStmt->execute([$productName, $categoryId]);
    $existing = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    usort($variants, static function (array $a, array $b): int {
        return ((int)($b['is_default'] ?? 0)) <=> ((int)($a['is_default'] ?? 0));
    });
    $hasDefault = false;
    foreach ($variants as $variant) {
        if ((int)($variant['is_default'] ?? 0) === 1) {
            $hasDefault = true;
            break;
        }
    }
    if (!$hasDefault && isset($variants[0])) {
        $variants[0]['is_default'] = 1;
    }
    $basePrice = round((float)($variants[0]['price'] ?? 0), 2);
    if ($basePrice <= 0) {
        return false;
    }

    $shortDescription = mb_substr($description, 0, 250);
    if ($existing) {
        $action = 'update';
        $updateStmt = $pdo->prepare(
            'UPDATE products SET
                short_description = :short_description,
                description = :description,
                long_description = :long_description,
                base_price = :base_price,
                starting_price = :starting_price,
                availability_status = :availability_status,
                deleted_at = NULL,
                updated_at = NOW()
             WHERE id = :id'
        );
        $updateStmt->execute([
            'short_description' => $shortDescription,
            'description' => $description,
            'long_description' => $description,
            'base_price' => $basePrice,
            'starting_price' => $basePrice,
            'availability_status' => 'in_stock',
            'id' => (int)$existing['id'],
        ]);
        $productId = (int)$existing['id'];
    } else {
        $action = 'insert';
        $baseSlug = importSlugify($productName);
        $slug = $baseSlug;
        $suffix = 1;
        $slugStmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
        $slugStmt->execute([$slug]);
        while ($slugStmt->fetchColumn()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
            $slugStmt->execute([$slug]);
        }

        $sku = 'SKU-' . strtoupper(substr(uniqid('', true), -8));
        $insertStmt = $pdo->prepare(
            'INSERT INTO products (
                name, slug, sku, collection_category_id,
                short_description, description, long_description,
                base_price, starting_price, availability_status,
                is_veg, dietary_tag, topper_enabled, note_enabled
            ) VALUES (
                :name, :slug, :sku, :collection_category_id,
                :short_description, :description, :long_description,
                :base_price, :starting_price, :availability_status,
                1, "regular", 1, 1
            )'
        );
        $insertStmt->execute([
            'name' => $productName,
            'slug' => $slug,
            'sku' => $sku,
            'collection_category_id' => $categoryId,
            'short_description' => $shortDescription,
            'description' => $description,
            'long_description' => $description,
            'base_price' => $basePrice,
            'starting_price' => $basePrice,
            'availability_status' => 'in_stock',
        ]);
        $productId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);

    $variantInsert = $pdo->prepare(
        'INSERT INTO product_variants (
            product_id, variant_label, variant_name, weight_or_size, unit_type,
            price, stock_quantity, sku, sku_suffix, is_default, is_active
         ) VALUES (
            :product_id, :variant_label, :variant_name, :weight_or_size, :unit_type,
            :price, 100, :sku, :sku_suffix, :is_default, 1
         )'
    );

    foreach ($variants as $index => $variant) {
        $variantName = trim((string)($variant['variant_name'] ?? ''));
        $price = round((float)($variant['price'] ?? 0), 2);
        if ($variantName === '' || $price <= 0) {
            continue;
        }

        $unitType = strtolower(trim((string)($variant['unit_type'] ?? 'custom')));
        if (!in_array($unitType, VARIANT_UNIT_TYPES, true)) {
            $unitType = 'custom';
        }
        $sku = trim((string)($variant['sku'] ?? ''));

        $variantInsert->execute([
            'product_id' => $productId,
            'variant_label' => $variantName,
            'variant_name' => $variantName,
            'weight_or_size' => $variantName,
            'unit_type' => $unitType,
            'price' => $price,
            'sku' => $sku !== '' ? $sku : null,
            'sku_suffix' => $sku !== '' ? $sku : null,
            'is_default' => (int)($variant['is_default'] ?? ($index === 0 ? 1 : 0)) === 1 ? 1 : 0,
        ]);
    }

    return $productId;
}

// ============================================================
//  Action: RESTORE VERSION
// ============================================================
$importResult  = null;
$restoreResult = null;
$importError   = null;
$importSkipLog = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_version') {
    $targetRunId = (int)($_POST['run_id'] ?? 0);

    if ($targetRunId <= 0) {
        $importError = 'Invalid version ID for restore.';
    } else {
        try {
            $importService = new ProductImportService();

            if (!$importService->hasSnapshot($targetRunId)) {
                throw new \Exception("No catalog snapshot found for version #{$targetRunId}. Cannot restore.");
            }

            // Snapshot current state first so the restore itself is reversible
            $preRestoreRunId = $importService->beginRestoreRun($targetRunId, $_SESSION['admin_id'] ?? null);
            $importService->snapshotCurrentCatalog($preRestoreRunId);

            $result = $importService->restoreImportVersion($targetRunId);

            $importService->completeImportRun(
                $preRestoreRunId,
                0, $result['restored'], $result['archived'],
                $result['restored'] + $result['archived']
            );
            $importService->cleanupOldVersions(5);
            FileCache::clearAll();

            $restoreResult = [
                'restored_from_run_id' => $targetRunId,
                'restored'             => $result['restored'],
                'archived'             => $result['archived'],
            ];
        } catch (\Throwable $e) {
            $importError = 'Restore failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ============================================================
//  Action: UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {

    if (empty($_FILES['file']['tmp_name'])) {
        $importError = 'No file was uploaded. Please select a .csv or .xlsx file.';

    } else {
        $originalName = basename($_FILES['file']['name'] ?? 'import.xlsx');
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        // Save backup copy
        $backupDir = __DIR__ . '/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $backupName = date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $backupPath = $backupDir . $backupName;
        move_uploaded_file($_FILES['file']['tmp_name'], $backupPath);

        // Parse rows
        $dataRows = [];
        try {
            if ($extension === 'xlsx') {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($backupPath);
                $allRows     = $spreadsheet->getActiveSheet()->toArray(null, false, false, false);
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                $dataRows = array_slice($allRows, 1);
            } else {
                $fh = fopen($backupPath, 'r');
                if ($fh) {
                    fgetcsv($fh); // skip header
                    while (($csvRow = fgetcsv($fh)) !== false) {
                        $dataRows[] = $csvRow;
                    }
                    fclose($fh);
                }
            }
        } catch (\Throwable $e) {
            $importError = 'Could not read file: ' . htmlspecialchars($e->getMessage());
        }

        if (!$importError && empty($dataRows)) {
            $importError = 'The uploaded file contains no data rows (only a header, or it is empty).';
        }

        if (!$importError) {
            try {
                $pdo           = Database::getConnection();
                $importService = new ProductImportService();

                // Begin run + snapshot BEFORE any mutations
                $runId = $importService->beginImportRun($backupName, $_SESSION['admin_id'] ?? null);
                $importService->snapshotCurrentCatalog($runId);

                $upsertedIds = [];
                $inserted    = 0;
                $updated     = 0;
                $failed      = 0;

                $groupedProducts = [];
                foreach ($dataRows as $rowIdx => $row) {
                    if (empty(array_filter(array_map('strval', $row)))) {
                        continue;
                    }

                    $productName = trim((string)($row[0] ?? ''));
                    $description = trim((string)($row[1] ?? ''));
                    $categoryName = trim((string)($row[2] ?? ''));
                    $variantName = trim((string)($row[3] ?? ''));
                    $price = (float)trim((string)($row[4] ?? ''));
                    $unitType = strtolower(trim((string)($row[5] ?? 'custom')));
                    $sku = trim((string)($row[6] ?? ''));
                    $isDefault = (trim((string)($row[7] ?? '')) === '1') ? 1 : 0;

                    if ($productName === '' || $categoryName === '' || $variantName === '' || $price <= 0) {
                        $failed++;
                        $importSkipLog[] = 'Row ' . ($rowIdx + 2) . ': skipped (required columns: Product Name, Category, Variant Name, Price)';
                        continue;
                    }

                    if (!in_array($unitType, VARIANT_UNIT_TYPES, true)) {
                        $unitType = 'custom';
                    }

                    $groupKey = strtolower($categoryName . '|' . $productName);
                    if (!isset($groupedProducts[$groupKey])) {
                        $groupedProducts[$groupKey] = [
                            'category_name' => $categoryName,
                            'product_name' => $productName,
                            'description' => $description,
                            'variants' => [],
                        ];
                    }

                    if ($description !== '' && $groupedProducts[$groupKey]['description'] === '') {
                        $groupedProducts[$groupKey]['description'] = $description;
                    }

                    $groupedProducts[$groupKey]['variants'][] = [
                        'variant_name' => $variantName,
                        'price' => round($price, 2),
                        'unit_type' => $unitType,
                        'sku' => $sku,
                        'is_default' => $isDefault,
                    ];
                }

                foreach ($groupedProducts as $group) {
                    $action = '';
                    $productId = processRow(
                        $pdo,
                        (string)$group['category_name'],
                        (string)$group['product_name'],
                        (string)$group['description'],
                        (array)$group['variants'],
                        $action
                    );

                    if ($productId !== false) {
                        $upsertedIds[] = $productId;
                        if ($action === 'insert') {
                            $inserted++;
                        } else {
                            $updated++;
                        }
                    } else {
                        $failed++;
                        $importSkipLog[] = htmlspecialchars((string)$group['product_name']) . ' — skipped (invalid grouped product payload)';
                    }
                }

                // Master-of-truth: archive products NOT in this upload
                $deleted = $importService->softDeleteMissingProducts(array_unique($upsertedIds));

                $importService->completeImportRun($runId, $inserted, $updated, $deleted, count($groupedProducts), $failed);
                $importService->cleanupOldVersions(5);
                FileCache::clearAll();

                $importResult = [
                    'run_id'   => $runId,
                    'file'     => $backupName,
                    'inserted' => $inserted,
                    'updated'  => $updated,
                    'deleted'  => $deleted,
                    'failed'   => $failed,
                    'total'    => count($groupedProducts),
                ];

            } catch (\Throwable $e) {
                if (isset($runId)) {
                    (new ProductImportService())->failImportRun($runId, $e->getMessage());
                }
                $importError = 'Import failed: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Load version history for sidebar
$recentRuns = [];
try {
    $importService = $importService ?? new ProductImportService();
    $recentRuns    = $importService->listRecentImports(5);
} catch (\Throwable $e) {
    // silently degrade
}

require_once __DIR__ . '/layout.php';
?>
<style>
.imp-page { max-width: 1200px; }

.imp-grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 22px;
    margin-bottom: 24px;
}
@media (max-width: 980px) { .imp-grid { grid-template-columns: 1fr; } }

.imp-card {
    background: #fff;
    border: 1px solid rgba(128,0,31,.1);
    border-radius: 18px;
    padding: 24px 26px;
    box-shadow: 0 10px 32px rgba(128,0,31,.07);
}
.imp-card__title {
    margin: 0 0 4px;
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 1.35rem;
    color: #80001F;
}
.imp-card__sub { margin: 0 0 20px; color: #805564; font-size: 0.83rem; }

/* Instructions accordion */
.imp-accordion {
    margin-bottom: 24px;
    border: 1px solid rgba(128,0,31,.12);
    border-radius: 14px;
    overflow: hidden;
    background: #fffafb;
}
.imp-accordion summary {
    cursor: pointer;
    padding: 14px 18px;
    font-weight: 600;
    font-size: 0.86rem;
    color: #80001F;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
}
.imp-accordion summary::-webkit-details-marker { display: none; }
.imp-accordion summary::before { content: '▶'; font-size: .7rem; transition: transform .2s; }
.imp-accordion[open] summary::before { transform: rotate(90deg); }
.imp-accordion__body {
    padding: 0 18px 16px;
    font-size: 0.82rem;
    color: #4a2030;
    line-height: 1.7;
}
.imp-accordion__body p { margin: 0 0 10px; }
.imp-accordion__body strong { color: #80001F; }
.imp-warn {
    background: #fff8e1;
    border: 1px solid #f0cc40;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.81rem;
    color: #5a4400;
    margin: 10px 0;
}
.imp-col-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
.imp-col-table th { background: #80001F; color: #fff; font-size: .75rem; padding: 6px 10px; text-align: left; }
.imp-col-table td { font-size: .77rem; padding: 5px 10px; border-bottom: 1px solid rgba(128,0,31,.08); }
.imp-col-table tr:nth-child(even) td { background: #fff4f7; }

/* Upload form */
.imp-form { display: grid; gap: 16px; }
.file-drop {
    display: block;
    border: 2px dashed rgba(128,0,31,.26);
    background: linear-gradient(160deg,#fff9fb,#fff3f6);
    border-radius: 14px;
    padding: 20px;
}
.file-drop__title { display: block; font-weight: 600; font-size: .85rem; color: #6c2d3f; margin-bottom: 8px; }
.file-drop__hint  { display: block; font-size: .75rem; color: #9a6f7f; margin-top: 6px; }
.file-input {
    width: 100%;
    border: 1px solid rgba(128,0,31,.18);
    background: #fff;
    color: #432530;
    border-radius: 10px;
    font-size: 0.83rem;
    padding: 8px;
}
.imp-confirm {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #fff4f6;
    border: 1px solid rgba(128,0,31,.14);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 0.82rem;
    color: #5a2030;
}
.imp-confirm input[type=checkbox] { width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px; accent-color: #80001F; }

.imp-actions { display: flex; flex-wrap: wrap; gap: 10px; }
.imp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    border-radius: 10px;
    padding: 0 18px;
    text-decoration: none;
    font-size: 0.83rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: transform .18s, box-shadow .18s, background .18s, opacity .18s;
}
.imp-btn--primary {
    color: #fff;
    background: linear-gradient(135deg,#80001F,#a1002a);
    box-shadow: 0 8px 20px rgba(128,0,31,.28);
}
.imp-btn--primary:hover    { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(128,0,31,.35); }
.imp-btn--primary:disabled { opacity: .45; cursor: not-allowed; transform: none; }
.imp-btn--secondary {
    color: #80001F;
    background: #fff;
    border: 1px solid rgba(128,0,31,.22);
}
.imp-btn--secondary:hover { background: #fff4f8; transform: translateY(-1px); }
.imp-btn--danger {
    color: #fff;
    background: linear-gradient(135deg,#7a1f1f,#9e2828);
    box-shadow: 0 6px 16px rgba(120,20,20,.22);
    font-size: 0.76rem;
    min-height: 34px;
    padding: 0 14px;
}
.imp-btn--danger:hover { transform: translateY(-1px); }

/* Version history table */
.ver-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
.ver-table th { background: #80001F; color: #fff; padding: 7px 10px; text-align: left; font-size: .74rem; }
.ver-table td { padding: 8px 10px; border-bottom: 1px solid rgba(128,0,31,.08); color: #3f1d28; vertical-align: middle; }
.ver-table tr:nth-child(even) td { background: #fff7f9; }
.ver-table tr:hover td { background: #fff0f4; }
.ver-badge { display: inline-block; border-radius: 6px; padding: 2px 8px; font-size: .7rem; font-weight: 600; }
.ver-badge--success { background: #d6f3e3; color: #1a6640; }
.ver-badge--failed  { background: #ffe0e0; color: #8a1818; }
.ver-badge--pending { background: #fff0c8; color: #6b4800; }
.ver-badge--restore { background: #e0eeff; color: #1a3a6b; }
.ver-empty { margin: 0; color: #987281; font-size: .82rem; padding: 14px 0; text-align: center; }

.preview-box {
    display: none;
    margin-top: 12px;
    border: 1px solid rgba(128,0,31,.16);
    border-radius: 12px;
    background: #fff8fb;
    padding: 12px;
}
.preview-box.is-open { display: block; }
.preview-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.preview-item {
    border: 1px solid rgba(128,0,31,.12);
    border-radius: 10px;
    padding: 8px;
    background: #fff;
}
.preview-item__label { font-size: .7rem; color: #7a5567; }
.preview-item__num { font-size: 1.2rem; color: #80001F; font-weight: 700; }

.size-master-list { margin-top: 12px; display: grid; gap: 8px; }
.size-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 8px;
    align-items: center;
    border: 1px solid rgba(128,0,31,.12);
    border-radius: 10px;
    padding: 8px 10px;
    background: #fff;
}
.size-row.is-inactive { opacity: .55; }
.size-label-chip { font-size: .8rem; font-weight: 600; color: #4b1e2d; }
.size-row .imp-btn { min-height: 30px; padding: 0 10px; font-size: .72rem; }

/* Results */
.imp-result {
    border-radius: 18px;
    border: 1px solid rgba(128,0,31,.1);
    padding: 24px 26px;
    background: #fff;
    box-shadow: 0 10px 32px rgba(128,0,31,.07);
    margin-bottom: 24px;
}
.imp-result__title {
    margin: 0 0 16px;
    font-family: 'DM Serif Display', Georgia, serif;
    font-size: 1.2rem;
    color: #80001F;
}
.imp-stats { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.imp-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    padding: 14px 18px;
    border-radius: 12px;
    border: 1px solid rgba(128,0,31,.1);
    background: linear-gradient(160deg,#fffdfd,#fff7fa);
}
.imp-stat__num { font-family: 'DM Serif Display', Georgia, serif; font-size: 1.8rem; color: #80001F; line-height: 1; }
.imp-stat__label { font-size: .72rem; color: #7a5567; margin-top: 4px; text-align: center; }
.imp-stat--warn .imp-stat__num  { color: #b87000; }
.imp-stat--alert .imp-stat__num { color: #8a1818; }

.msg { margin: 0 0 12px; border-radius: 10px; padding: 11px 14px; font-size: .83rem; font-weight: 500; }
.msg--success { border: 1px solid #c8ead6; background: #effcf4; color: #1a6c3f; }
.msg--error   { border: 1px solid #f0c3c3; background: #fff3f3; color: #922f2f; }
.msg--info    { border: 1px solid #c0d8f4; background: #f0f6ff; color: #1a3d6c; }

.skip-log { max-height: 140px; overflow-y: auto; background: #fff8f8; border: 1px solid #f0c3c3; border-radius: 10px; padding: 10px 12px; font-size: .76rem; color: #7a2020; margin-top: 8px; }
.skip-log li { margin: 2px 0; }

/* Modals */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(30,8,14,.55);
    z-index: 9000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.is-open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; padding: 28px 30px; max-width: 420px; width: 90%; box-shadow: 0 24px 60px rgba(128,0,31,.22); }
.modal-title { margin: 0 0 10px; font-family: 'DM Serif Display', Georgia, serif; font-size: 1.3rem; color: #80001F; }
.modal-body  { font-size: .85rem; color: #4a2030; line-height: 1.7; margin-bottom: 20px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }

@media (max-width: 560px) {
    .imp-card { padding: 16px; }
    .imp-stats { gap: 8px; }
    .imp-stat  { min-width: 80px; padding: 10px 12px; }
}
</style>

<?php if ($importResult): ?>
<div class="imp-result">
    <h2 class="imp-result__title">Import Complete — Run #<?= (int)$importResult['run_id'] ?></h2>
    <div class="imp-stats">
        <div class="imp-stat">
            <span class="imp-stat__num"><?= $importResult['inserted'] ?></span>
            <span class="imp-stat__label">Added</span>
        </div>
        <div class="imp-stat">
            <span class="imp-stat__num"><?= $importResult['updated'] ?></span>
            <span class="imp-stat__label">Updated</span>
        </div>
        <div class="imp-stat <?= $importResult['deleted'] > 0 ? 'imp-stat--warn' : '' ?>">
            <span class="imp-stat__num"><?= $importResult['deleted'] ?></span>
            <span class="imp-stat__label">Archived</span>
        </div>
        <?php if ($importResult['failed'] > 0): ?>
        <div class="imp-stat imp-stat--alert">
            <span class="imp-stat__num"><?= $importResult['failed'] ?></span>
            <span class="imp-stat__label">Skipped</span>
        </div>
        <?php endif; ?>
        <div class="imp-stat">
            <span class="imp-stat__num"><?= $importResult['total'] ?></span>
            <span class="imp-stat__label">Total Rows</span>
        </div>
    </div>
    <p class="msg msg--success">
        Catalog updated. <?= $importResult['deleted'] ?> product(s) not in your file have been archived.
        Version #<?= (int)$importResult['run_id'] ?> saved — restore it anytime from the history panel.
    </p>
    <?php if (!empty($importSkipLog)): ?>
    <details>
        <summary style="cursor:pointer;font-size:.82rem;color:#80001F;font-weight:600;">
            Show <?= count($importSkipLog) ?> skipped row(s)
        </summary>
        <ul class="skip-log">
            <?php foreach ($importSkipLog as $msg): echo '<li>' . $msg . '</li>'; endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($restoreResult): ?>
<div class="imp-result">
    <h2 class="imp-result__title">Catalog Restored to Version #<?= (int)$restoreResult['restored_from_run_id'] ?></h2>
    <div class="imp-stats">
        <div class="imp-stat">
            <span class="imp-stat__num"><?= $restoreResult['restored'] ?></span>
            <span class="imp-stat__label">Restored</span>
        </div>
        <div class="imp-stat imp-stat--warn">
            <span class="imp-stat__num"><?= $restoreResult['archived'] ?></span>
            <span class="imp-stat__label">Archived</span>
        </div>
    </div>
    <p class="msg msg--info">
        The catalog has been rolled back. The previous state was saved automatically so you can undo this if needed.
    </p>
</div>
<?php endif; ?>

<?php if ($importError): ?>
<p class="msg msg--error"><?= $importError ?></p>
<?php endif; ?>

<div class="imp-page">

    <details class="imp-accordion">
        <summary>How Master Import Works &amp; Column Guide</summary>
        <div class="imp-accordion__body">
            <p>
                <strong>This is a master-of-truth import.</strong>
                When you upload a new Excel file, it becomes the definitive product catalog.
                Products in your file are added or updated. Products <strong>NOT</strong> in your file
                are automatically <strong>archived</strong> (hidden from customers, not deleted).
            </p>
            <div class="imp-warn">
                ⚠️ Before uploading, verify your Excel contains <em>all</em> products you want live.
                A missing row = that product gets archived from the storefront.
            </div>
            <p><strong>Column layout (matrix contract):</strong></p>
            <table class="imp-col-table">
                <thead><tr><th>Col</th><th>Name</th><th>Example</th></tr></thead>
                <tbody>
                    <tr><td>A</td><td>Product Name</td><td>Dark Truffle Cake</td></tr>
                    <tr><td>B</td><td>Category</td><td>Cakes</td></tr>
                    <tr><td>C</td><td>SubCategory</td><td>Classic Cakes</td></tr>
                    <tr><td>D</td><td>Description</td><td>Rich chocolate ganache cake</td></tr>
                    <tr><td>E</td><td>Food Type</td><td>veg</td></tr>
                    <tr><td>F</td><td>Dietary Tag</td><td>eggless</td></tr>
                    <tr><td>G</td><td>Chef's Special</td><td>Yes/No</td></tr>
                    <tr><td>H</td><td>Enable Topper Selection</td><td>Yes/No</td></tr>
                    <tr><td>I</td><td>Enable Note on Cake</td><td>Yes/No</td></tr>
                    <tr><td>J onward</td><td>Dynamic size columns</td><td>Per Pcs, 0.5 kg, 1 kg, ...</td></tr>
                </tbody>
            </table>
            <p>
                One row equals one product. Fill prices in active size columns; blank cell means variant disabled.
                A preview summary is generated before commit. The last 5 imports are versioned and restorable.
            </p>
        </div>
    </details>

    <div class="imp-grid">

        <section class="imp-card">
            <h2 class="imp-card__title">Upload New Product Catalog</h2>
            <p class="imp-card__sub">Upload a .csv or .xlsx file. The file becomes the new master catalog — products missing from the file are archived.</p>

            <form method="POST" enctype="multipart/form-data" class="imp-form" id="uploadForm">
                <label class="file-drop">
                    <span class="file-drop__title">Select CSV or Excel file</span>
                    <input type="file" name="file" accept=".csv,.xlsx" required class="file-input" id="fileInput">
                    <span class="file-drop__hint">.csv and .xlsx supported · matrix format with dynamic size columns</span>
                </label>

                <div class="preview-box" id="previewBox">
                    <div class="preview-grid" id="previewGrid"></div>
                </div>

                <label class="imp-confirm">
                    <input type="checkbox" id="confirmCheck" required>
                    <span>I understand this will <strong>replace the live catalog</strong>. Products not in my file will be archived from the storefront.</span>
                </label>

                <div class="imp-actions">
                    <button type="button" class="imp-btn imp-btn--primary" id="uploadTrigger" disabled>
                        ⬆ Import Catalog
                    </button>
                    <a href="download_products.php" class="imp-btn imp-btn--secondary">⬇ Download Current Catalog</a>
                </div>

                <button type="submit" name="upload" id="realSubmit" style="display:none"></button>
            </form>
        </section>

        <section class="imp-card">
            <h3 class="imp-card__title">Version History</h3>
            <p class="imp-card__sub">Last 5 imports. Restore any version to roll back the catalog.</p>

            <?php if (empty($recentRuns)): ?>
            <p class="ver-empty">No import versions yet. Upload a file to create the first version.</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="ver-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File / Mode</th>
                        <th title="Added">+</th>
                        <th title="Updated">~</th>
                        <th title="Archived">−</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentRuns as $run):
                    $badgeClass = match($run['status']) {
                        'success' => 'ver-badge--success',
                        'failed'  => 'ver-badge--failed',
                        'pending' => 'ver-badge--pending',
                        default   => 'ver-badge--restore',
                    };
                    $isRestore  = ($run['mode'] === 'restore');
                    $runLabel   = $isRestore
                        ? 'restore ← #' . (int)($run['restored_from_run_id'] ?? 0)
                        : htmlspecialchars(basename($run['source_file'] ?? '(file)'));
                    $canRestore = ($run['status'] === 'success' && !$isRestore);
                ?>
                <tr>
                    <td><strong>#<?= (int)$run['id'] ?></strong></td>
                    <td>
                        <span class="ver-badge <?= $badgeClass ?>"><?= htmlspecialchars($run['status']) ?></span>
                        <br><span style="font-size:.71rem;color:#805564;word-break:break-all"><?= $runLabel ?></span>
                    </td>
                    <td style="color:#1a6640;font-weight:600"><?= (int)($run['created_count'] ?? 0) ?></td>
                    <td style="color:#1a3d6c;font-weight:600"><?= (int)($run['updated_count'] ?? 0) ?></td>
                    <td style="color:#8a4a00;font-weight:600"><?= (int)($run['deleted_count'] ?? 0) ?></td>
                    <td style="white-space:nowrap;font-size:.74rem"><?= date('d M y H:i', strtotime($run['created_at'])) ?></td>
                    <td>
                        <?php if ($canRestore): ?>
                        <button type="button"
                                class="imp-btn imp-btn--danger"
                                onclick="openRestoreModal(<?= (int)$run['id'] ?>, '<?= date('d M Y', strtotime($run['created_at'])) ?>')">
                            Restore
                        </button>
                        <?php else: ?>
                        <span style="font-size:.72rem;color:#aaa">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="imp-card">
            <h3 class="imp-card__title">Dynamic Size Master</h3>
            <p class="imp-card__sub">Manage labels used for matrix import/export headers. Reorder and toggle active state.</p>

            <form class="imp-form" id="sizeCreateForm" onsubmit="return createSizeLabel(event)">
                <label class="file-drop">
                    <span class="file-drop__title">Add New Size Label</span>
                    <input type="text" id="newSizeLabel" class="file-input" placeholder="Example: 4.5 kg" required>
                </label>
                <div class="imp-actions">
                    <button type="submit" class="imp-btn imp-btn--primary">Add Size</button>
                    <button type="button" class="imp-btn imp-btn--secondary" onclick="loadSizeMaster()">Refresh</button>
                </div>
            </form>

            <div class="size-master-list" id="sizeMasterList"></div>
        </section>

    </div>
</div>

</div><!-- .main -->
</div><!-- .dashboard -->
</body>
</html>

<!-- Restore modal -->
<div class="modal-overlay" id="restoreModal" role="dialog" aria-modal="true">
    <div class="modal-box">
        <h2 class="modal-title">Restore Catalog Version</h2>
        <div class="modal-body">
            Are you sure you want to restore version <strong id="modalRunId"></strong>
            from <strong id="modalRunDate"></strong>?
            <br><br>
            The current catalog will be replaced. A snapshot of the current state will be saved
            automatically so you can undo this restore.
        </div>
        <div class="modal-actions">
            <button class="imp-btn imp-btn--secondary" onclick="closeRestoreModal()">Cancel</button>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="restore_version">
                <input type="hidden" name="run_id" id="modalRunIdInput" value="">
                <button type="submit" class="imp-btn imp-btn--danger">Yes, Restore</button>
            </form>
        </div>
    </div>
</div>

<!-- Upload confirm modal -->
<div class="modal-overlay" id="uploadModal" role="dialog" aria-modal="true">
    <div class="modal-box">
        <h2 class="modal-title">Confirm Catalog Upload</h2>
        <div class="modal-body">
            You are about to replace the live product catalog.
            Products <strong>not present</strong> in your uploaded file will be
            <strong>archived</strong> from the storefront.
            The current catalog will be versioned automatically.
            <br><br>
            Continue?
        </div>
        <div class="modal-actions">
            <button class="imp-btn imp-btn--secondary" onclick="closeUploadModal()">Cancel</button>
            <button class="imp-btn imp-btn--primary"   onclick="doUpload()">Yes, Import Now</button>
        </div>
    </div>
</div>

<script>
const fileInput     = document.getElementById('fileInput');
const confirmCheck  = document.getElementById('confirmCheck');
const uploadTrigger = document.getElementById('uploadTrigger');
const previewBox = document.getElementById('previewBox');
const previewGrid = document.getElementById('previewGrid');
const sizeMasterList = document.getElementById('sizeMasterList');

let sizeMasterItems = [];

function updateUploadBtn() {
    uploadTrigger.disabled = !(fileInput.files.length > 0 && confirmCheck.checked);
}
fileInput.addEventListener('change', updateUploadBtn);
confirmCheck.addEventListener('change', updateUploadBtn);

uploadTrigger.addEventListener('click', function () {
    openUploadPreviewAndConfirm();
});
function closeUploadModal() { document.getElementById('uploadModal').classList.remove('is-open'); }
function doUpload() {
    document.getElementById('uploadModal').classList.remove('is-open');
    document.getElementById('realSubmit').click();
}

async function openUploadPreviewAndConfirm() {
    if (!(fileInput.files.length > 0 && confirmCheck.checked)) {
        return;
    }
    try {
        const form = new FormData();
        form.append('file', fileInput.files[0]);
        const response = await fetch('/api/admin/import/products/preview', {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        });
        const payload = await response.json();
        const summary = payload && payload.data ? payload.data.preview_summary : null;
        if (summary) {
            renderPreviewSummary(summary);
        }
    } catch (e) {
        console.error('Preview failed', e);
    }

    document.getElementById('uploadModal').classList.add('is-open');
}

function renderPreviewSummary(summary) {
    if (!previewBox || !previewGrid) {
        return;
    }
    const items = [
        ['New Products', Number(summary.new_products || 0)],
        ['Updated', Number(summary.updated_products || 0)],
        ['Invalid Rows', Number(summary.invalid_rows || 0)],
        ['New Variants', Number(summary.new_variants || 0)],
        ['Removed Variants', Number(summary.removed_variants || 0)],
        ['Will Archive', Number(summary.archived_products || 0)],
    ];
    previewGrid.innerHTML = items.map(function (item) {
        return '<div class="preview-item"><div class="preview-item__label">' + item[0] + '</div><div class="preview-item__num">' + item[1] + '</div></div>';
    }).join('');
    previewBox.classList.add('is-open');
}

async function apiJson(path, options) {
    const response = await fetch(path, Object.assign({
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }
    }, options || {}));
    const payload = await response.json();
    if (!response.ok || !payload || payload.success === false) {
        throw new Error((payload && payload.message) ? payload.message : 'Request failed');
    }
    return payload;
}

async function loadSizeMaster() {
    try {
        const payload = await apiJson('/api/admin/product-size-master', { method: 'GET', headers: {} });
        sizeMasterItems = (payload.data && payload.data.items) ? payload.data.items : [];
        renderSizeMaster();
    } catch (error) {
        sizeMasterList.innerHTML = '<p class="ver-empty">Failed to load size master: ' + error.message + '</p>';
    }
}

function renderSizeMaster() {
    if (!sizeMasterItems || sizeMasterItems.length === 0) {
        sizeMasterList.innerHTML = '<p class="ver-empty">No size labels yet.</p>';
        return;
    }

    sizeMasterList.innerHTML = sizeMasterItems.map(function (item, index) {
        const inactiveClass = Number(item.is_active || 0) === 1 ? '' : ' is-inactive';
        return '<div class="size-row' + inactiveClass + '">' +
            '<span class="size-label-chip">' + escapeHtml(String(item.label || '')) + '</span>' +
            '<button type="button" class="imp-btn imp-btn--secondary" onclick="moveSize(' + index + ', -1)">Up</button>' +
            '<button type="button" class="imp-btn imp-btn--secondary" onclick="moveSize(' + index + ', 1)">Down</button>' +
            '<button type="button" class="imp-btn ' + (Number(item.is_active || 0) === 1 ? 'imp-btn--danger' : 'imp-btn--primary') + '" onclick="toggleSize(' + Number(item.id) + ', ' + (Number(item.is_active || 0) === 1 ? 0 : 1) + ')">' +
            (Number(item.is_active || 0) === 1 ? 'Disable' : 'Enable') +
            '</button>' +
            '</div>';
    }).join('');
}

function moveSize(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= sizeMasterItems.length) {
        return;
    }
    const temp = sizeMasterItems[index];
    sizeMasterItems[index] = sizeMasterItems[target];
    sizeMasterItems[target] = temp;
    renderSizeMaster();
    persistSizeOrder();
}

async function persistSizeOrder() {
    try {
        const ids = sizeMasterItems.map(function (item) { return Number(item.id); });
        await apiJson('/api/admin/product-size-master/reorder', {
            method: 'POST',
            body: JSON.stringify({ ids: ids })
        });
    } catch (error) {
        alert('Failed to reorder size labels: ' + error.message);
        loadSizeMaster();
    }
}

async function toggleSize(id, isActive) {
    try {
        await apiJson('/api/admin/product-size-master/' + id, {
            method: 'PATCH',
            body: JSON.stringify({ is_active: isActive })
        });
        loadSizeMaster();
    } catch (error) {
        alert('Failed to update size label: ' + error.message);
    }
}

async function createSizeLabel(event) {
    event.preventDefault();
    const input = document.getElementById('newSizeLabel');
    const label = String(input.value || '').trim();
    if (!label) {
        return false;
    }
    try {
        await apiJson('/api/admin/product-size-master', {
            method: 'POST',
            body: JSON.stringify({ label: label })
        });
        input.value = '';
        loadSizeMaster();
    } catch (error) {
        alert('Failed to add size label: ' + error.message);
    }
    return false;
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openRestoreModal(runId, runDate) {
    document.getElementById('modalRunId').textContent   = '#' + runId;
    document.getElementById('modalRunDate').textContent = runDate;
    document.getElementById('modalRunIdInput').value    = runId;
    document.getElementById('restoreModal').classList.add('is-open');
}
function closeRestoreModal() { document.getElementById('restoreModal').classList.remove('is-open'); }

document.querySelectorAll('.modal-overlay').forEach(function (o) {
    o.addEventListener('click', function (e) { if (e.target === o) o.classList.remove('is-open'); });
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay').forEach(function (m) { m.classList.remove('is-open'); });
    }
});

loadSizeMaster();
</script>
