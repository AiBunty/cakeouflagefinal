<?php

declare(strict_types=1);

use App\Core\Database;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require APP_ROOT . '/app/bootstrap.php';

final class MediaMigrateOptimizeCommand
{
    private PDO $pdo;
    private bool $dryRun;
    private int $limit;
    private int $adminId;

    private array $stats = [
        'scanned' => 0,
        'queued' => 0,
        'already_optimized' => 0,
        'missing_files' => 0,
        'skipped_non_images' => 0,
        'copied_to_unified' => 0,
        'copy_failures' => 0,
    ];

    private array $tableSpecs = [
        ['table' => 'products', 'id' => 'id', 'columns' => ['featured_image', 'hover_image', 'image_2', 'image_3', 'image_4'], 'module' => 'products', 'entity_type' => 'product'],
        ['table' => 'categories', 'id' => 'id', 'columns' => ['image'], 'module' => 'categories', 'entity_type' => 'category'],
        ['table' => 'banners', 'id' => 'id', 'columns' => ['image_url'], 'module' => 'banners', 'entity_type' => 'banner'],
        ['table' => 'brand_settings', 'id' => 'id', 'columns' => ['logo_url', 'favicon_url'], 'module' => 'branding', 'entity_type' => 'brand_setting'],
        ['table' => 'orders', 'id' => 'id', 'columns' => ['payment_proof_url'], 'module' => 'byoc', 'entity_type' => 'payment_proof'],
        ['table' => 'inquiries', 'id' => 'id', 'columns' => ['reference_file'], 'module' => 'byoc', 'entity_type' => 'reference_file'],
    ];

    public function __construct(array $argv)
    {
        $this->dryRun = in_array('--dry-run', $argv, true);
        $this->limit = $this->readIntOption($argv, '--limit=', 2000);
        $this->adminId = $this->readIntOption($argv, '--admin-id=', 0);
        $this->pdo = Database::getConnection();
    }

    public function run(): int
    {
        $this->printHeader();

        if (!$this->tableExists('media_processing_queue') || !$this->tableExists('queue_jobs')) {
            fwrite(STDERR, "Missing required tables: media_processing_queue or queue_jobs\n");
            return 1;
        }

        $candidates = $this->collectCandidates();
        $candidates = array_slice($candidates, 0, $this->limit);

        foreach ($candidates as $candidate) {
            $this->processCandidate($candidate);
        }

        $this->printSummary();
        return 0;
    }

    private function collectCandidates(): array
    {
        $results = [];

        foreach ($this->tableSpecs as $spec) {
            if (!$this->tableExists((string)$spec['table'])) {
                continue;
            }

            $idCol = (string)$spec['id'];
            foreach ((array)$spec['columns'] as $column) {
                if (!$this->columnExists((string)$spec['table'], (string)$column)) {
                    continue;
                }

                $sql = sprintf(
                    'SELECT %s AS entity_id, %s AS media_path FROM %s WHERE %s IS NOT NULL AND TRIM(%s) <> ""',
                    $idCol,
                    $column,
                    $spec['table'],
                    $column,
                    $column
                );

                $stmt = $this->pdo->query($sql);
                if (!$stmt instanceof PDOStatement) {
                    continue;
                }

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $mediaPath = trim((string)($row['media_path'] ?? ''));
                    if ($mediaPath === '') {
                        continue;
                    }

                    $results[] = [
                        'module' => (string)$spec['module'],
                        'entity_type' => (string)$spec['entity_type'],
                        'entity_id' => (int)($row['entity_id'] ?? 0),
                        'source' => $mediaPath,
                    ];
                }
            }
        }

        // Also sweep unified originals to catch files that exist but are not represented in DB references.
        $originalsDir = APP_ROOT . '/public/uploads/originals';
        if (is_dir($originalsDir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($originalsDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $relative = str_replace(APP_ROOT, '', $fileInfo->getPathname());
                $relative = str_replace('\\', '/', $relative);
                $results[] = [
                    'module' => 'migrated',
                    'entity_type' => 'filesystem',
                    'entity_id' => 0,
                    'source' => $relative,
                ];
            }
        }

        return $results;
    }

    private function processCandidate(array $candidate): void
    {
        $this->stats['scanned']++;

        $source = trim((string)($candidate['source'] ?? ''));
        if (!$this->isImagePath($source)) {
            $this->stats['skipped_non_images']++;
            return;
        }

        $sourceAbs = $this->resolveAbsolutePath($source);
        if ($sourceAbs === null || !is_file($sourceAbs)) {
            $this->stats['missing_files']++;
            return;
        }

        $sourceUnified = $this->toUnifiedOriginalPath($source, $sourceAbs);
        if ($sourceUnified === null) {
            $this->stats['copy_failures']++;
            return;
        }

        [$originalPath, $optimizedPath] = $sourceUnified;
        $optimizedAbs = $this->resolveAbsolutePath($optimizedPath);

        if ($optimizedAbs !== null && is_file($optimizedAbs)) {
            $this->stats['already_optimized']++;
            return;
        }

        if ($this->isAlreadyQueued($originalPath, $optimizedPath)) {
            return;
        }

        if ($this->dryRun) {
            $this->stats['queued']++;
            return;
        }

        $this->enqueueJob(
            (string)$candidate['module'],
            (string)$candidate['entity_type'],
            (int)$candidate['entity_id'],
            $originalPath,
            $optimizedPath
        );
        $this->stats['queued']++;
    }

    private function isAlreadyQueued(string $originalPath, string $optimizedPath): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM media_processing_queue
             WHERE original_path = :original_path AND optimized_path = :optimized_path
               AND processing_status IN ("pending", "processing")
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['original_path' => $originalPath, 'optimized_path' => $optimizedPath]);
        return (bool)$stmt->fetchColumn();
    }

    private function enqueueJob(string $module, string $entityType, int $entityId, string $originalPath, string $optimizedPath): void
    {
        $queueStmt = $this->pdo->prepare(
            'INSERT INTO media_processing_queue (module_name, entity_type, entity_id, original_path, optimized_path, processing_status, attempts, created_at)
             VALUES (:module_name, :entity_type, :entity_id, :original_path, :optimized_path, "pending", 0, NOW())'
        );
        $queueStmt->execute([
            'module_name' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'original_path' => $originalPath,
            'optimized_path' => $optimizedPath,
        ]);
        $queueId = (int)$this->pdo->lastInsertId();

        $payload = [
            'media_queue_id' => $queueId,
            'module_name' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'original_path' => $originalPath,
            'optimized_path' => $optimizedPath,
            'admin_id' => $this->adminId,
        ];

        $jobStmt = $this->pdo->prepare(
            'INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts, created_at, updated_at)
             VALUES ("media_image_optimize", :payload_json, "queued", NOW(), 0, NOW(), NOW())'
        );
        $jobStmt->execute(['payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES)]);
    }

    private function toUnifiedOriginalPath(string $source, string $sourceAbs): ?array
    {
        $normalized = str_replace('\\', '/', $source);

        if (str_starts_with($normalized, '/public/uploads/originals/')) {
            $relativeWithoutPrefix = substr($normalized, strlen('/public/uploads/originals/'));
            if ($relativeWithoutPrefix === false || $relativeWithoutPrefix === '') {
                return null;
            }
            $optimized = '/public/uploads/optimized/' . preg_replace('/\.[^.]+$/', '.webp', $relativeWithoutPrefix);
            return [$normalized, $optimized];
        }

        $ext = strtolower((string)pathinfo($sourceAbs, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        $baseName = strtolower((string)pathinfo($sourceAbs, PATHINFO_FILENAME));
        $safeBase = preg_replace('/[^a-z0-9_-]+/', '-', $baseName) ?: 'image';
        $hash = substr(sha1($sourceAbs . '|' . filesize($sourceAbs)), 0, 12);
        $targetRelative = '/public/uploads/originals/migrated/' . $safeBase . '-' . $hash . '.' . $ext;
        $targetAbs = APP_ROOT . $targetRelative;

        if (!is_file($targetAbs)) {
            $targetDir = dirname($targetAbs);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return null;
            }

            if ($this->dryRun) {
                $this->stats['copied_to_unified']++;
            } else {
                if (!copy($sourceAbs, $targetAbs)) {
                    return null;
                }
                $this->stats['copied_to_unified']++;
            }
        }

        $optimized = '/public/uploads/optimized/migrated/' . $safeBase . '-' . $hash . '.webp';
        return [$targetRelative, $optimized];
    }

    private function resolveAbsolutePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);
        if (str_starts_with($normalized, '/public/')) {
            return APP_ROOT . $normalized;
        }
        if (str_starts_with($normalized, '/uploads/')) {
            return APP_ROOT . '/public' . $normalized;
        }
        if (str_starts_with($normalized, 'uploads/')) {
            return APP_ROOT . '/public/' . $normalized;
        }
        if (str_starts_with($normalized, 'public/')) {
            return APP_ROOT . '/' . $normalized;
        }
        if (str_starts_with($normalized, '/client/')) {
            return APP_ROOT . $normalized;
        }
        if (str_starts_with($normalized, 'client/')) {
            return APP_ROOT . '/' . $normalized;
        }

        return APP_ROOT . '/' . ltrim($normalized, '/');
    }

    private function isImagePath(string $path): bool
    {
        $extension = strtolower((string)pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'bmp'], true);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute(['table_name' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
        );
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function readIntOption(array $argv, string $prefix, int $default): int
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, $prefix)) {
                $value = (int)substr($arg, strlen($prefix));
                return $value > 0 ? $value : $default;
            }
        }
        return $default;
    }

    private function printHeader(): void
    {
        $mode = $this->dryRun ? 'dry-run' : 'execute';
        fwrite(STDOUT, "media:migrate-optimize ({$mode})\n");
        fwrite(STDOUT, "limit={$this->limit} admin-id={$this->adminId}\n");
    }

    private function printSummary(): void
    {
        fwrite(STDOUT, json_encode([
            'success' => true,
            'dry_run' => $this->dryRun,
            'stats' => $this->stats,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}

$command = new MediaMigrateOptimizeCommand($argv);
$exitCode = $command->run();
exit($exitCode);
