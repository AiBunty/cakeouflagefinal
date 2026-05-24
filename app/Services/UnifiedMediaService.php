<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * UnifiedMediaService
 *
 * Single upload ingress for all image modules.
 * Upload request path does only:
 * 1) validate + store original
 * 2) enqueue background optimization
 * 3) return immediately with original path
 */
final class UnifiedMediaService
{
    private const DEFAULT_MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<string,string> */
    private const ALLOWED_MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    /** @var array<string,string> */
    private const MODULE_BUCKET_MAP = [
        'product' => 'products',
        'product_image_2' => 'products',
        'category' => 'categories',
        'branding' => 'branding',
        'email_branding' => 'branding',
        'navbar_logo' => 'branding',
        'footer_logo' => 'branding',
        'banner' => 'banners',
        'media_center' => 'media-center',
        'byoc' => 'byoc',
        'avatar' => 'avatars',
        'crm' => 'media-center',
        'gallery' => 'media-center',
        'coupon_banner' => 'banners',
        'blog' => 'media-center',
        'template_editor' => 'media-center',
    ];

    /**
     * @param array<string,mixed> $file
     * @param array<string,mixed> $opts
     * @return array{ok:bool, relative_url:string, optimized_url:string, queue_id:int, error:string, absolute_path:string}
     */
    public static function upload(array $file, array $opts = []): array
    {
        $module = strtolower(trim((string)($opts['module'] ?? 'media_center')));
        $entityType = trim((string)($opts['entity_type'] ?? $module));
        $entityId = (int)($opts['entity_id'] ?? 0);
        $adminId = (int)($opts['admin_id'] ?? 0);
        $maxBytes = max(1024, (int)($opts['max_bytes'] ?? self::DEFAULT_MAX_BYTES));
        $allowSvg = (bool)($opts['allow_svg'] ?? true);
        $replacePaths = isset($opts['replace_paths']) && is_array($opts['replace_paths']) ? $opts['replace_paths'] : [];

        $failure = static function (string $error): array {
            self::log('FAIL', [
                'error' => $error,
            ]);
            return [
                'ok' => false,
                'relative_url' => '',
                'optimized_url' => '',
                'queue_id' => 0,
                'error' => $error,
                'absolute_path' => '',
            ];
        };

        $phpError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($phpError !== UPLOAD_ERR_OK) {
            return $failure(self::uploadErrorMessage($phpError));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $origName = basename((string)($file['name'] ?? 'file'));
        $size = (int)($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return $failure('No temporary file available for upload.');
        }

        if ($size <= 0) {
            return $failure('Uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            return $failure('File exceeds upload limit.');
        }

        // Block executable/double-extension payloads.
        if (preg_match('/\.(php\d?|phtml|phar|pl|py|rb|cgi|sh|exe|bat|cmd)(\.|$)/i', $origName)) {
            return $failure('File type not allowed.');
        }

        $detectedMime = self::detectMime($tmpName);
        if ($detectedMime === '' || !isset(self::ALLOWED_MIME_TO_EXT[$detectedMime])) {
            return $failure('Allowed formats: JPG, PNG, WebP, SVG, GIF.');
        }

        if ($detectedMime === 'image/svg+xml' && !$allowSvg) {
            return $failure('SVG files are not allowed for this upload type.');
        }

        $bucket = self::bucketForModule($module);
        $sanitizedStem = self::sanitizeStem((string)pathinfo($origName, PATHINFO_FILENAME));
        $token = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $base = ($sanitizedStem !== '' ? $sanitizedStem : 'media') . '_' . $token;
        $ext = self::ALLOWED_MIME_TO_EXT[$detectedMime];

        $root = self::projectRoot();
        $originalRelDir = '/public/uploads/originals/' . $bucket;
        $optimizedRelDir = '/public/uploads/optimized/' . $bucket;
        $thumbRelDir = '/public/uploads/thumbnails/' . $bucket;

        $originalDirAbs = $root . $originalRelDir;
        $optimizedDirAbs = $root . $optimizedRelDir;
        $thumbDirAbs = $root . $thumbRelDir;

        self::ensureDir($originalDirAbs);
        self::ensureDir($optimizedDirAbs);
        self::ensureDir($thumbDirAbs);

        if (!is_dir($originalDirAbs) || !is_writable($originalDirAbs)) {
            self::log('FAIL', [
                'error' => 'Upload destination is not writable',
                'dir' => $originalDirAbs,
                'exists' => is_dir($originalDirAbs) ? '1' : '0',
                'writable' => is_writable($originalDirAbs) ? '1' : '0',
            ]);
            return $failure('Upload directory is not writable.');
        }

        $originalRelPath = $originalRelDir . '/' . $base . '.' . $ext;
        $originalAbsPath = $root . str_replace('/', DIRECTORY_SEPARATOR, $originalRelPath);

        if ($detectedMime === 'image/svg+xml') {
            $svg = file_get_contents($tmpName);
            if ($svg === false) {
                return $failure('Could not read uploaded SVG.');
            }
            $svg = self::sanitizeSvg($svg);
            if (file_put_contents($originalAbsPath, $svg) === false) {
                return $failure('Could not write uploaded SVG to storage.');
            }
        } else {
            if (!move_uploaded_file($tmpName, $originalAbsPath)) {
                $last = error_get_last();
                self::log('FAIL', [
                    'error' => 'move_uploaded_file failed',
                    'tmp' => $tmpName,
                    'tmp_readable' => is_readable($tmpName) ? '1' : '0',
                    'dest' => $originalAbsPath,
                    'dest_dir' => (string)dirname($originalAbsPath),
                    'dest_dir_writable' => is_writable(dirname($originalAbsPath)) ? '1' : '0',
                    'php_last_error' => is_array($last) ? (string)($last['message'] ?? '') : '',
                ]);
                return $failure('Could not move uploaded file to storage.');
            }
        }

        $optimizedRelPath = $optimizedRelDir . '/' . $base . '.webp';

        // Best-effort cleanup of old media paths after a replacement upload.
        if (!empty($replacePaths)) {
            self::cleanupPaths($replacePaths);
        }

        $queueId = self::enqueueProcessing(
            $module,
            $entityType,
            $entityId,
            $originalRelPath,
            $optimizedRelPath,
            $adminId
        );

        self::log('OK', [
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => (string)$entityId,
            'mime' => $detectedMime,
            'bytes' => (string)$size,
            'original' => $originalRelPath,
            'optimized' => $optimizedRelPath,
            'queue_id' => (string)$queueId,
        ]);

        return [
            'ok' => true,
            'relative_url' => $originalRelPath,
            'optimized_url' => $optimizedRelPath,
            'queue_id' => $queueId,
            'error' => '',
            'absolute_path' => $originalAbsPath,
        ];
    }

    private static function bucketForModule(string $module): string
    {
        return self::MODULE_BUCKET_MAP[$module] ?? 'media-center';
    }

    private static function detectMime(string $tmpName): string
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $detected = finfo_file($fi, $tmpName);
                finfo_close($fi);
                if (is_string($detected) && $detected !== '') {
                    return strtolower(trim($detected));
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($tmpName);
            if (is_string($detected) && $detected !== '') {
                return strtolower(trim($detected));
            }
        }

        return '';
    }

    private static function sanitizeStem(string $stem): string
    {
        $stem = preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $stem) ?? '';
        $stem = trim($stem, '-_ ');
        return strtolower($stem);
    }

    private static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        @chmod($path, 0777);
    }

    /**
     * @param array<int|string,mixed> $paths
     */
    private static function cleanupPaths(array $paths): void
    {
        $root = self::projectRoot();
        foreach ($paths as $path) {
            $value = trim((string)$path);
            if ($value === '') {
                continue;
            }
            if (!preg_match('#^/public/uploads/#', $value)) {
                continue;
            }
            if (strpos($value, '..') !== false || strpos($value, "\0") !== false) {
                continue;
            }
            $absolute = $root . str_replace('/', DIRECTORY_SEPARATOR, $value);
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    private static function enqueueProcessing(
        string $module,
        string $entityType,
        int $entityId,
        string $originalPath,
        string $optimizedPath,
        int $adminId
    ): int {
        if (!class_exists(Database::class)) {
            $databasePath = dirname(__DIR__, 2) . '/app/Core/Database.php';
            if (is_file($databasePath)) {
                require_once $databasePath;
            }
        }

        if (!class_exists(Database::class)) {
            self::log('WARN', [
                'message' => 'Queue enqueue skipped because Database class is unavailable',
                'original' => $originalPath,
            ]);
            return 0;
        }

        try {
            $pdo = Database::getConnection();
        } catch (Throwable $e) {
            self::log('WARN', [
                'message' => 'Queue enqueue skipped due to DB error',
                'error' => $e->getMessage(),
                'original' => $originalPath,
            ]);
            return 0;
        }

        $queueRecordId = 0;
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'media_processing_queue'");
            if ($tableExists instanceof \PDOStatement && $tableExists->fetchColumn()) {
                $stmt = $pdo->prepare(
                    'INSERT INTO media_processing_queue (module_name, entity_type, entity_id, original_path, optimized_path, processing_status, attempts, created_at)
                     VALUES (:module_name, :entity_type, :entity_id, :original_path, :optimized_path, "pending", 0, NOW())'
                );
                $stmt->execute([
                    'module_name' => $module,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'original_path' => $originalPath,
                    'optimized_path' => $optimizedPath,
                ]);
                $queueRecordId = (int)$pdo->lastInsertId();
            }

            $jobStmt = $pdo->prepare(
                'INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts, created_at, updated_at)
                 VALUES ("media_image_optimize", :payload_json, "queued", NOW(), 0, NOW(), NOW())'
            );

            $jobStmt->execute([
                'payload_json' => json_encode([
                    'media_queue_id' => $queueRecordId,
                    'module_name' => $module,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'original_path' => $originalPath,
                    'optimized_path' => $optimizedPath,
                    'admin_id' => $adminId,
                ], JSON_UNESCAPED_SLASHES),
            ]);

            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            self::log('WARN', [
                'message' => 'Queue enqueue failed',
                'error' => $e->getMessage(),
                'original' => $originalPath,
            ]);
            return 0;
        }
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Uploaded file is too large.';
            case UPLOAD_ERR_PARTIAL:
                return 'File upload was interrupted. Please retry.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server temporary directory is missing.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server failed to write uploaded file.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload blocked by server extension.';
            default:
                return 'Upload failed.';
        }
    }

    private static function sanitizeSvg(string $svg): string
    {
        if (!class_exists('DOMDocument')) {
            $svg = (string)(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg);
            $svg = (string)(preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? $svg);
            return $svg;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($svg);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//script') ?: [] as $node) {
            /** @var \DOMNode $node */
            $node->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('//@*') ?: [] as $attr) {
            /** @var \DOMAttr $attr */
            if (str_starts_with(strtolower($attr->localName), 'on')) {
                $attr->ownerElement?->removeAttributeNode($attr);
            }
        }

        $result = $doc->saveXML();
        return $result !== false ? $result : $svg;
    }

    /** @param array<string,string> $context */
    private static function log(string $level, array $context): void
    {
        $root = self::projectRoot();
        $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . DIRECTORY_SEPARATOR . 'media.log';

        $pairs = [];
        foreach ($context as $key => $value) {
            $pairs[] = $key . '=' . str_replace(["\r", "\n"], ' ', $value);
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $level . ' ' . implode(' ', $pairs) . PHP_EOL;
        $written = @file_put_contents($logFile, $line, FILE_APPEND);
        if ($written === false) {
            error_log('[UnifiedMediaService] log write failed path=' . $logFile . ' line=' . trim($line));
        }
    }
}
