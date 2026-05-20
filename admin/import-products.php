<?php
// ============================================================
//  Import Products — master-of-truth catalog upload
//  Phases 4 + 5 + 7 of the catalog redesign
// ============================================================
$pageTitle = "Import Products";
include "includes/auth.php";
require_permission_for_current_admin_page();

require 'includes/db.php';                        // $conn (MySQLi) + Env loaded
require_once __DIR__ . '/../vendor/autoload.php'; // Composer PSR-4

use App\Core\Database;
use App\Core\FileCache;
use App\Services\ProductImportService;

// ── Weight keys (order must match export/download exactly) ────────────────
const WEIGHT_KEYS = ['per_piece', '0.5lb', '1lb', '1.5lb', '2lb', '2.5lb', '3lb', '3.5lb', '4lb', '4.5lb', '5lb'];

// ── Dietary label map: human-readable (from Excel) → DB enum ─────────────
const DIETARY_IMPORT_MAP = [
    'Regular'    => 'regular',
    'Eggless'    => 'eggless',
    'Vegan'      => 'vegan',
    'Sugar Free' => 'sugar_free',
    'Healthy'    => 'healthy',
    'regular'    => 'regular',
    'eggless'    => 'eggless',
    'vegan'      => 'vegan',
    'sugar_free' => 'sugar_free',
    'sugar free' => 'sugar_free',
    'healthy'    => 'healthy',
];

const ALLOWED_DIETARY = ['regular', 'eggless', 'vegan', 'sugar_free', 'healthy'];

// ============================================================
//  Helpers
// ============================================================

/**
 * Normalise a weight/size label coming from the spreadsheet.
 * Passes through 'per_piece' unchanged; lowercases and strips spaces otherwise.
 */
function normalizeWeight(string $weight): string
{
    $w = strtolower(trim($weight));
    if ($w === 'per_piece' || $w === 'per piece') {
        return 'per_piece';
    }
    $w = str_replace(' ', '', $w);
    $w = str_replace('gm', 'g', $w);
    return $w;
}

/**
 * Upsert one product row using PDO prepared statements (no SQL injection risk).
 *
 * @param  array<string,float>  $variantPrices  weight_key => price (only non-empty, > 0 entries)
 * @param  string               &$action        set to 'insert' or 'update' by reference
 * @return int|false  product_id on success, false on skip or hard error
 */
function processRow(
    \PDO   $pdo,
    string $categoryName,
    string $subcategoryName,
    string $productName,
    array  $variantPrices,
    int    $isChefSpecial,
    string $dietaryTag,
    int    $isVeg,
    string &$action = ''
): int|false {

    if ($categoryName === '' || $subcategoryName === '' || $productName === '') {
        return false;
    }

    // ── 1. Upsert parent category ────────────────────────────────────────
    $catStmt = $pdo->prepare("
        SELECT id FROM categories
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
          AND parent_id IS NULL
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $catStmt->execute([$categoryName]);
    $categoryId = $catStmt->fetchColumn();

    if (!$categoryId) {
        $catSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($categoryName)), '-') ?: 'category';
        $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, NULL)")
            ->execute([$categoryName, $catSlug]);
        $categoryId = (int)$pdo->lastInsertId();
    } else {
        $categoryId = (int)$categoryId;
    }

    // ── 2. Upsert subcategory ────────────────────────────────────────────
    $subStmt = $pdo->prepare("
        SELECT id FROM categories
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))
          AND parent_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $subStmt->execute([$subcategoryName, $categoryId]);
    $subcategoryId = $subStmt->fetchColumn();

    if (!$subcategoryId) {
        $subSlug = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($subcategoryName)), '-') ?: 'subcategory';
        $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)")
            ->execute([$subcategoryName, $subSlug, $categoryId]);
        $subcategoryId = (int)$pdo->lastInsertId();
    } else {
        $subcategoryId = (int)$subcategoryId;
    }

    // ── 3. Upsert product ────────────────────────────────────────────────
    $prodStmt = $pdo->prepare("
        SELECT id FROM products
        WHERE LOWER(name) = LOWER(?)
          AND subcategory_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $prodStmt->execute([$productName, $subcategoryId]);
    $productId = $prodStmt->fetchColumn();

    if ($productId) {
        $action    = 'update';
        $productId = (int)$productId;
        $basePrice = !empty($variantPrices) ? (float)array_values($variantPrices)[0] : 0.0;

        $pdo->prepare("
            UPDATE products SET
                collection_category_id = ?,
                is_chef_special        = ?,
                dietary_tag            = ?,
                is_veg                 = ?,
                base_price             = ?,
                starting_price         = ?,
                updated_at             = NOW()
            WHERE id = ?
        ")->execute([$categoryId, $isChefSpecial, $dietaryTag, $isVeg, $basePrice, $basePrice, $productId]);

    } else {
        $action    = 'insert';
        $basePrice = !empty($variantPrices) ? (float)array_values($variantPrices)[0] : 0.0;

        // Unique slug
        $baseSlug  = trim(preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($productName)), '-') ?: 'product';
        $slug      = $baseSlug;
        $slugCheck = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
        $slugCheck->execute([$slug]);
        $n = 1;
        while ($slugCheck->fetchColumn()) {
            $slug = $baseSlug . '-' . $n++;
            $slugCheck->execute([$slug]);
        }

        $sku = 'SKU-' . strtoupper(substr(uniqid(), -8));

        $pdo->prepare("
            INSERT INTO products
                (name, slug, sku, subcategory_id, collection_category_id,
                 base_price, starting_price, availability_status,
                 is_chef_special, dietary_tag, is_veg, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'in_stock', ?, ?, ?, NOW())
        ")->execute([
            $productName, $slug, $sku, $subcategoryId, $categoryId,
            $basePrice, $basePrice,
            $isChefSpecial, $dietaryTag, $isVeg,
        ]);

        $productId = (int)$pdo->lastInsertId();
    }

    // ── 4. Upsert variants (only populated weight columns) ───────────────
    $varCheck = $pdo->prepare("
        SELECT id FROM product_variants
        WHERE product_id = ? AND weight_or_size = ?
        LIMIT 1
    ");
    $defCheck = $pdo->prepare(
        "SELECT id FROM product_variants WHERE product_id = ? AND is_default = 1 LIMIT 1"
    );

    foreach ($variantPrices as $weightKey => $price) {
        $normalised = normalizeWeight((string)$weightKey);
        $price      = round((float)$price, 2);

        $varCheck->execute([$productId, $normalised]);
        $existingVarId = $varCheck->fetchColumn();

        if ($existingVarId) {
            $pdo->prepare("
                UPDATE product_variants SET price = ?, is_active = 1, stock_quantity = 10
                WHERE id = ?
            ")->execute([$price, (int)$existingVarId]);
        } else {
            $defCheck->execute([$productId]);
            $isDefault = $defCheck->fetchColumn() ? 0 : 1;

            $pdo->prepare("
                INSERT INTO product_variants
                    (product_id, variant_label, weight_or_size, price, is_default, is_active, stock_quantity, created_at)
                VALUES (?, ?, ?, ?, ?, 1, 10, NOW())
            ")->execute([$productId, $normalised, $normalised, $price, $isDefault]);
        }
    }

    // Deactivate variants for weight keys absent from the new file
    $notInList = implode(',', array_fill(0, count(WEIGHT_KEYS), '?'));
    $pdo->prepare("
        UPDATE product_variants
        SET is_active = 0
        WHERE product_id = ?
          AND weight_or_size NOT IN ($notInList)
    ")->execute(array_merge([$productId], WEIGHT_KEYS));

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

                foreach ($dataRows as $rowIdx => $row) {
                    if (empty(array_filter(array_map('strval', $row)))) {
                        continue;
                    }

                    $categoryName    = trim((string)($row[0] ?? ''));
                    $subcategoryName = trim((string)($row[1] ?? ''));
                    $productName     = trim((string)($row[2] ?? ''));

                    // Columns 3–13: one per weight key
                    $variantPrices = [];
                    foreach (WEIGHT_KEYS as $i => $wKey) {
                        $raw = trim((string)($row[3 + $i] ?? ''));
                        if ($raw !== '' && (float)$raw > 0) {
                            $variantPrices[$wKey] = (float)$raw;
                        }
                    }

                    $isChefSpecial = (trim((string)($row[14] ?? '')) === '1') ? 1 : 0;

                    $rawDietary = trim((string)($row[15] ?? ''));
                    $dietaryTag = DIETARY_IMPORT_MAP[$rawDietary] ?? 'regular';
                    if (!in_array($dietaryTag, ALLOWED_DIETARY, true)) {
                        $dietaryTag = 'regular';
                    }

                    $isVeg = (trim((string)($row[16] ?? '')) === '0') ? 0 : 1;

                    $action    = '';
                    $productId = processRow(
                        $pdo,
                        $categoryName, $subcategoryName, $productName,
                        $variantPrices,
                        $isChefSpecial, $dietaryTag, $isVeg,
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
                        $importSkipLog[] = 'Row ' . ($rowIdx + 2) . ': '
                            . htmlspecialchars("$categoryName / $subcategoryName / $productName")
                            . ' — skipped (missing required fields)';
                    }
                }

                // Master-of-truth: archive products NOT in this upload
                $deleted = $importService->softDeleteMissingProducts(array_unique($upsertedIds));

                $importService->completeImportRun($runId, $inserted, $updated, $deleted, count($dataRows), $failed);
                $importService->cleanupOldVersions(5);
                FileCache::clearAll();

                $importResult = [
                    'run_id'   => $runId,
                    'file'     => $backupName,
                    'inserted' => $inserted,
                    'updated'  => $updated,
                    'deleted'  => $deleted,
                    'failed'   => $failed,
                    'total'    => count($dataRows),
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

include 'layout.php';
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
            <p><strong>Column layout (17 columns):</strong></p>
            <table class="imp-col-table">
                <thead><tr><th>Col</th><th>Name</th><th>Example</th></tr></thead>
                <tbody>
                    <tr><td>A</td><td>Category</td><td>Cakes</td></tr>
                    <tr><td>B</td><td>Subcategory</td><td>Chocolate Cakes</td></tr>
                    <tr><td>C</td><td>Product Name</td><td>Dark Truffle Cake</td></tr>
                    <tr><td>D</td><td>Per Piece</td><td>450</td></tr>
                    <tr><td>E</td><td>0.5lb</td><td>350</td></tr>
                    <tr><td>F</td><td>1lb</td><td>650</td></tr>
                    <tr><td>G</td><td>1.5lb</td><td>900</td></tr>
                    <tr><td>H</td><td>2lb</td><td>1200</td></tr>
                    <tr><td>I</td><td>2.5lb</td><td></td></tr>
                    <tr><td>J</td><td>3lb</td><td>1800</td></tr>
                    <tr><td>K</td><td>3.5lb</td><td></td></tr>
                    <tr><td>L</td><td>4lb</td><td>2400</td></tr>
                    <tr><td>M</td><td>4.5lb</td><td></td></tr>
                    <tr><td>N</td><td>5lb</td><td>3000</td></tr>
                    <tr><td>O</td><td>Chef's Special (0/1)</td><td>1</td></tr>
                    <tr><td>P</td><td>Dietary Type</td><td>Eggless</td></tr>
                    <tr><td>Q</td><td>Veg (1=Yes / 0=No)</td><td>1</td></tr>
                </tbody>
            </table>
            <p>
                Leave any weight price cell <strong>blank</strong> for sizes you don't offer —
                only filled cells create a variant. Dietary values: Regular, Eggless, Vegan, Sugar Free, Healthy.
                The last 5 imports are versioned — restore any version from the history panel.
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
                    <span class="file-drop__hint">.csv and .xlsx supported · 17-column format</span>
                </label>

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

function updateUploadBtn() {
    uploadTrigger.disabled = !(fileInput.files.length > 0 && confirmCheck.checked);
}
fileInput.addEventListener('change', updateUploadBtn);
confirmCheck.addEventListener('change', updateUploadBtn);

uploadTrigger.addEventListener('click', function () {
    document.getElementById('uploadModal').classList.add('is-open');
});
function closeUploadModal() { document.getElementById('uploadModal').classList.remove('is-open'); }
function doUpload() {
    document.getElementById('uploadModal').classList.remove('is-open');
    document.getElementById('realSubmit').click();
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
</script>
