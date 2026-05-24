<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use Exception;

/**
 * ProductImportService — Master-of-truth catalog import with snapshot versioning.
 *
 * FLOW (full import):
 *   1. beginImportRun()         → creates product_import_runs row, returns $runId
 *   2. snapshotCurrentCatalog() → snapshots ALL live products + variants BEFORE mutation
 *   3. [caller runs processRow() for each Excel row, collects $upsertedIds]
 *   4. softDeleteMissingProducts($upsertedIds) → archives products NOT in new file
 *   5. completeImportRun()      → marks run success with counts
 *   6. cleanupOldVersions(5)    → auto-purges oldest beyond 5 retained
 *
 * RESTORE FLOW:
 *   1. snapshotCurrentCatalog() → save current catalog as a new run (so restore is reversible)
 *   2. restoreImportVersion($targetRunId) → loads pre-import snapshot JSON, re-applies it
 *   3. softDeleteMissingProducts($restoredIds) → archives products not in the restored catalog
 *
 * DB TABLES (actual schema):
 *   product_import_runs:
 *     id, mode, status, source_file, created_count, updated_count,
 *     deleted_count, failed_count, total_rows, restored_from_run_id,
 *     metadata_json, created_by_admin_id, created_at
 *
 *   product_import_snapshots:
 *     id, run_id (UNIQUE), snapshot_json LONGTEXT, created_at
 *
 *   product_snapshots:
 *     id, run_id, product_id, sku, product_data JSON, operation,
 *     sequence_number, has_variants, variant_count, created_at, deleted_at
 *
 *   product_variant_snapshots:
 *     id, snapshot_id, run_id, product_id, variant_id, variant_sku,
 *     variant_data JSON, variant_option_values, variant_price, variant_stock,
 *     sequence_number, created_at
 *
 * ARCHITECTURE NOTES:
 *   • All restore operations are wrapped in a PDO transaction for atomicity.
 *   • Race-condition guard: beginImportRun() refuses if another run is 'pending'.
 *   • MAX_RETAINED_VERSIONS = 5; older runs (and their snapshots) are CASCADE-deleted.
 */
class ProductImportService
{
    private PDO $pdo;
    public const MAX_RETAINED_VERSIONS = 5;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // Import run lifecycle
    // -------------------------------------------------------------------------

    /**
     * Open a new import run record.
     * Throws if another run is currently in-flight (status = 'pending').
     *
     * @return int The new run ID
     */
    public function beginImportRun(string $sourceFile = '', ?int $adminId = null): int
    {
        // Race-condition guard: block concurrent imports
        $inFlight = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM product_import_runs WHERE status = 'pending'"
        )->fetchColumn();

        if ($inFlight > 0) {
            throw new Exception(
                'Another catalog import is already in progress. Please wait a moment and try again.'
            );
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO product_import_runs
                (mode, status, source_file, created_by_admin_id, created_at)
            VALUES
                ('commit', 'pending', ?, ?, NOW())
        ");
        $stmt->execute([$sourceFile ?: null, $adminId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Open a restore-mode run record (pre-snapshots current catalog before overwriting).
     * The $restoringFromRunId records which version is being restored.
     *
     * @return int The new run ID
     */
    public function beginRestoreRun(int $restoringFromRunId, ?int $adminId = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_import_runs
                (mode, status, restored_from_run_id, created_by_admin_id, created_at)
            VALUES
                ('restore', 'pending', ?, ?, NOW())
        ");
        $stmt->execute([$restoringFromRunId, $adminId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Mark a run as successfully completed with import statistics.
     */
    public function completeImportRun(
        int $runId,
        int $created,
        int $updated,
        int $deleted,
        int $total,
        int $failed = 0
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE product_import_runs
            SET status        = 'success',
                created_count = ?,
                updated_count = ?,
                deleted_count = ?,
                failed_count  = ?,
                total_rows    = ?
            WHERE id = ?
        ");
        $stmt->execute([$created, $updated, $deleted, $failed, $total, $runId]);
    }

    /**
     * Mark a run as failed with an error message.
     */
    public function failImportRun(int $runId, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE product_import_runs
            SET status = 'failed',
                metadata_json = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode(['error' => $error]), $runId]);
    }

    // -------------------------------------------------------------------------
    // Snapshot current catalog (MUST be called before any import mutations)
    // -------------------------------------------------------------------------

    /**
     * Snapshot every live product (and its active variants) for this run.
     *
     * Writes to:
     *   • product_import_snapshots — full catalog JSON for quick restore
     *   • product_snapshots        — per-product audit entries
     *   • product_variant_snapshots — per-variant audit entries
     *
     * @return int Number of products snapshotted
     */
    public function snapshotCurrentCatalog(int $runId): int
    {
        // Fetch all live products
        $products = $this->pdo->query("
            SELECT p.*,
                   cc.name AS _category_name,
                   sc.name AS _subcategory_name
            FROM products p
            LEFT JOIN categories cc ON cc.id = p.collection_category_id
            LEFT JOIN categories sc ON sc.id = p.subcategory_id
            WHERE p.deleted_at IS NULL
        ")->fetchAll(PDO::FETCH_ASSOC);

        $snapInsert = $this->pdo->prepare("
            INSERT INTO product_snapshots
                (run_id, product_id, sku, product_data, operation,
                 sequence_number, has_variants, variant_count, created_at)
            VALUES (?, ?, ?, ?, 'update', ?, ?, ?, NOW())
        ");

        $varSnapInsert = $this->pdo->prepare("
            INSERT INTO product_variant_snapshots
                (snapshot_id, run_id, product_id, variant_id, variant_sku,
                 variant_data, variant_price, variant_stock, sequence_number, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $varFetch = $this->pdo->prepare(
            "SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1"
        );

        $catalogItems = [];
        $seq = 0;

        foreach ($products as $product) {
            $seq++;
            $productId = (int)$product['id'];

            $varFetch->execute([$productId]);
            $variants = $varFetch->fetchAll(PDO::FETCH_ASSOC);

            // Build catalog item (includes variants, excludes joined helper cols)
            $item = $product;
            $item['_variants'] = $variants;
            $catalogItems[] = $item;

            // Per-product audit snapshot (strip helper cols from JSON)
            $productJson = $product;
            unset($productJson['_category_name'], $productJson['_subcategory_name']);

            $snapInsert->execute([
                $runId, $productId, $product['sku'],
                json_encode($productJson), $seq,
                !empty($variants) ? 1 : 0, count($variants),
            ]);
            $snapId = (int)$this->pdo->lastInsertId();

            // Per-variant audit snapshot
            foreach ($variants as $v) {
                $varSnapInsert->execute([
                    $snapId, $runId, $productId, (int)$v['id'],
                    $v['variant_sku'] ?? null,
                    json_encode($v),
                    (float)($v['price'] ?? 0),
                    (int)($v['stock_quantity'] ?? 0),
                    $seq,
                ]);
            }
        }

        // Full-catalog JSON snapshot (used by restoreImportVersion)
        $fullSnap = $this->pdo->prepare("
            INSERT INTO product_import_snapshots (run_id, snapshot_json, created_at)
            VALUES (?, ?, NOW())
            AS new
            ON DUPLICATE KEY UPDATE snapshot_json = new.snapshot_json
        ");
        $fullSnap->execute([$runId, json_encode($catalogItems)]);

        return count($products);
    }

    // -------------------------------------------------------------------------
    // Master-of-truth: archive products absent from the new import
    // -------------------------------------------------------------------------

    /**
     * Soft-delete every live product whose ID is NOT in $upsertedProductIds.
     * This enforces "master of truth" — only products in the uploaded file stay live.
     *
     * @param  int[] $upsertedProductIds  IDs that were inserted or updated during import
     * @return int Number of products archived
     */
    public function softDeleteMissingProducts(array $upsertedProductIds): int
    {
        if (empty($upsertedProductIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($upsertedProductIds), '?'));
        $stmt = $this->pdo->prepare("
            UPDATE products
            SET deleted_at = NOW()
            WHERE id NOT IN ($placeholders)
              AND deleted_at IS NULL
        ");
        $stmt->execute($upsertedProductIds);
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Restore a previous catalog version
    // -------------------------------------------------------------------------

    /**
     * Restore the catalog to the state captured by the snapshot for $targetRunId.
     *
     * The snapshot stores the catalog as it was BEFORE that run's import ran,
     * so restoring it effectively rolls the catalog back to the previous state.
     *
     * Wrapped in a transaction — safe to call without data loss on failure.
     *
     * @return array{restored: int, archived: int}
     */
    public function restoreImportVersion(int $targetRunId): array
    {
        $snapRow = $this->pdo->prepare(
            "SELECT snapshot_json FROM product_import_snapshots WHERE run_id = ? LIMIT 1"
        );
        $snapRow->execute([$targetRunId]);
        $row = $snapRow->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['snapshot_json'])) {
            throw new Exception("No catalog snapshot found for run ID {$targetRunId}.");
        }

        $catalogItems = json_decode($row['snapshot_json'], true);
        if (!is_array($catalogItems)) {
            throw new Exception("Snapshot data is corrupted for run ID {$targetRunId}.");
        }

        $this->pdo->beginTransaction();
        $restoredIds = [];

        try {
            $updateProd = $this->pdo->prepare("
                UPDATE products SET
                    name                   = ?,
                    slug                   = ?,
                    sku                    = ?,
                    collection_category_id = ?,
                    subcategory_id         = ?,
                    dietary_tag            = ?,
                    availability_status    = ?,
                    starting_price         = ?,
                    base_price             = ?,
                    is_chef_special        = ?,
                    is_veg                 = ?,
                    deleted_at             = NULL,
                    updated_at             = NOW()
                WHERE id = ?
            ");

            $updateVar = $this->pdo->prepare("
                UPDATE product_variants SET
                    variant_label  = ?,
                    weight_or_size = ?,
                    price          = ?,
                    is_default     = ?,
                    is_active      = 1,
                    stock_quantity = ?
                WHERE id = ?
            ");

            $insertVar = $this->pdo->prepare("
                INSERT INTO product_variants
                    (id, product_id, variant_label, weight_or_size,
                     price, is_default, is_active, stock_quantity, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");

            $checkProd = $this->pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
            $checkVar  = $this->pdo->prepare("SELECT id FROM product_variants WHERE id = ? LIMIT 1");

            foreach ($catalogItems as $item) {
                $variants  = $item['_variants'] ?? [];
                $productId = (int)$item['id'];

                $checkProd->execute([$productId]);
                if ($checkProd->fetchColumn()) {
                    $updateProd->execute([
                        $item['name'],
                        $item['slug'],
                        $item['sku'],
                        $item['collection_category_id'],
                        $item['subcategory_id'],
                        $item['dietary_tag'],
                        $item['availability_status'] ?? 'in_stock',
                        $item['starting_price'],
                        $item['base_price'],
                        $item['is_chef_special'],
                        $item['is_veg'],
                        $productId,
                    ]);
                }
                // If product was hard-deleted (beyond retention), we skip gracefully.

                $restoredIds[] = $productId;

                foreach ($variants as $v) {
                    $varId = (int)$v['id'];
                    $checkVar->execute([$varId]);
                    if ($checkVar->fetchColumn()) {
                        $updateVar->execute([
                            $v['variant_label'],
                            $v['weight_or_size'],
                            (float)$v['price'],
                            (int)$v['is_default'],
                            (int)($v['stock_quantity'] ?? 10),
                            $varId,
                        ]);
                    } else {
                        // Re-insert if somehow purged
                        $insertVar->execute([
                            $varId, $productId,
                            $v['variant_label'], $v['weight_or_size'],
                            (float)$v['price'], (int)$v['is_default'],
                            (int)($v['stock_quantity'] ?? 10),
                        ]);
                    }
                }
            }

            // Archive everything currently live that wasn't in the restored snapshot
            $archived = $this->softDeleteMissingProducts($restoredIds);

            $this->pdo->commit();

            return ['restored' => count($restoredIds), 'archived' => $archived];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Version retention — keep only the N most recent completed runs
    // -------------------------------------------------------------------------

    /**
     * Delete runs (and their CASCADE-linked snapshots) beyond the retention limit.
     *
     * @return int Number of run records deleted
     */
    public function cleanupOldVersions(int $keepCount = self::MAX_RETAINED_VERSIONS): int
    {
        // Find IDs of runs to purge (everything after the Nth most recent)
        $stmt = $this->pdo->prepare("
            SELECT id FROM product_import_runs
            WHERE status IN ('success', 'partial', 'failed')
            ORDER BY id DESC
            LIMIT 99999 OFFSET ?
        ");
        $stmt->execute([$keepCount]);
        $oldIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($oldIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($oldIds), '?'));
        $del = $this->pdo->prepare(
            "DELETE FROM product_import_runs WHERE id IN ($placeholders)"
        );
        $del->execute($oldIds);
        return $del->rowCount();
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function listRecentImports(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                mode,
                status,
                source_file,
                created_count,
                updated_count,
                deleted_count,
                failed_count,
                total_rows,
                restored_from_run_id,
                created_by_admin_id,
                created_at
            FROM product_import_runs
            ORDER BY id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Count of currently live (non-deleted) products. */
    public function getCurrentLiveCount(): int
    {
        return (int)$this->pdo->query(
            "SELECT COUNT(*) FROM products WHERE deleted_at IS NULL"
        )->fetchColumn();
    }

    /** True if a full-catalog snapshot exists for this run. */
    public function hasSnapshot(int $runId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM product_import_snapshots WHERE run_id = ?"
        );
        $stmt->execute([$runId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

