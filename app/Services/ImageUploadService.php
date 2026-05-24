<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ImageUploadService
 *
 * Centralised, security-hardened image upload handler.
 * Used by products, categories, branding, and any future upload context.
 *
 * Usage example:
 *   $result = ImageUploadService::upload(
 *       $_FILES['image'],
 *       __DIR__ . '/../../client/assets/images/product',
 *       '/client/assets/images/product/',
 *       ['context' => 'product', 'admin_id' => $adminId]
 *   );
 *   if (!$result['ok']) { echo $result['error']; }
 */
class ImageUploadService
{
    /** Allowed raster MIME types (SVG handled separately). */
    private const RASTER_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Extensions corresponding to allowed raster MIME types. */
    private const RASTER_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Default max size for raster images: 10 MB. */
    private const DEFAULT_MAX_BYTES = 10 * 1024 * 1024;

    /** Default max size for SVG logos: 512 KB. */
    private const SVG_MAX_BYTES = 512 * 1024;

    /**
     * Upload an image file to a destination directory.
     *
     * @param  array  $file              Single entry from $_FILES (e.g. $_FILES['image'])
     * @param  string $destDirAbsolute   Absolute filesystem path to the destination directory
     * @param  string $webRelativePrefix Web URL prefix for the stored file (e.g. '/client/assets/images/product/')
     * @param  array  $opts {
     *   @type int    max_bytes   Maximum allowed file size in bytes (default: 10 MB raster / 512 KB SVG)
     *   @type bool   allow_svg   Allow SVG upload with automatic sanitization (default: false)
     *   @type string context     Short identifier written to the upload log (e.g. 'product', 'branding')
     *   @type int    admin_id    Admin user ID written to the upload log (default: 0)
     *   @type string base_name   Custom base filename without extension (default: auto-generated timestamp + random hex)
     * }
     * @return array{ok: bool, relative_url: string, absolute_path: string, error: string}
     */
    public static function upload(array $file, string $destDirAbsolute, string $webRelativePrefix, array $opts = []): array
    {
        $context  = (string)($opts['context']  ?? 'upload');
        $adminId  = (int)($opts['admin_id']    ?? 0);
        $allowSvg = (bool)($opts['allow_svg']  ?? false);

        $failure = static function (string $reason) use ($context, $adminId): array {
            self::log('FAIL', $context, $adminId, '', 0, $reason);
            return ['ok' => false, 'relative_url' => '', 'absolute_path' => '', 'error' => $reason];
        };

        // ── 1. PHP upload error check ─────────────────────────────────────────
        $phpError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($phpError !== UPLOAD_ERR_OK) {
            return $failure(self::phpUploadErrorMessage($phpError));
        }

        $tmpName  = (string)($file['tmp_name'] ?? '');
        $origName = basename((string)($file['name'] ?? ''));
        $fileSize = (int)($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return $failure('No temporary file available for upload.');
        }

        // ── 2. Security: block PHP and script filenames (double-extension attacks) ──
        if (preg_match('/\.(php\d?|phtml|phar|pl|py|rb|cgi|sh|exe|bat|cmd)(\.|$)/i', $origName)) {
            return $failure('File type not allowed.');
        }

        // ── 3. Extension extraction ───────────────────────────────────────────
        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));

        // ── 4. MIME detection via finfo (more reliable than extension alone) ──
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $detected = finfo_file($fi, $tmpName);
                finfo_close($fi);
                if ($detected !== false) {
                    $mime = (string)$detected;
                }
            }
        }

        if ($mime === '') {
            // Fallback: derive MIME from extension when finfo is unavailable.
            $mimeMap = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
                'svg'  => 'image/svg+xml',
            ];
            $mime = $mimeMap[$ext] ?? '';
        }

        // Block files whose actual content is PHP/HTML, regardless of extension.
        if (in_array($mime, ['application/x-php', 'text/x-php', 'application/php', 'text/html'], true)) {
            return $failure('File type not allowed.');
        }

        $isSvg = ($mime === 'image/svg+xml' || $ext === 'svg');

        // ── 5. Validate type against allow-list ───────────────────────────────
        if ($isSvg) {
            if (!$allowSvg) {
                return $failure('SVG files are not allowed for this upload type.');
            }
        } elseif (!in_array($mime, self::RASTER_MIMES, true) || !in_array($ext, self::RASTER_EXTS, true)) {
            return $failure('Allowed formats: JPG, PNG, WebP, GIF' . ($allowSvg ? ', SVG' : '') . '.');
        }

        // ── 6. File size limit ────────────────────────────────────────────────
        $maxBytes = isset($opts['max_bytes'])
            ? (int)$opts['max_bytes']
            : ($isSvg ? self::SVG_MAX_BYTES : self::DEFAULT_MAX_BYTES);

        if ($fileSize > $maxBytes) {
            $mb = round($maxBytes / (1024 * 1024), 1);
            return $failure('File exceeds the ' . $mb . ' MB limit.');
        }

        // ── 7. Prepare destination directory ─────────────────────────────────
        if (!is_dir($destDirAbsolute)) {
            mkdir($destDirAbsolute, 0777, true);
        }
        @chmod($destDirAbsolute, 0777);

        if (!is_writable($destDirAbsolute)) {
            return $failure('Upload directory is not writable. Check server storage permissions.');
        }

        // ── 8. Generate unique base filename ─────────────────────────────────
        $customBase = isset($opts['base_name'])
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$opts['base_name'])
            : '';
        $baseName = $customBase !== '' ? $customBase : (time() . '_' . bin2hex(random_bytes(4)));

        $webRelativePrefix = rtrim($webRelativePrefix, '/') . '/';

        // ── 9. SVG path: sanitize and save ────────────────────────────────────
        if ($isSvg) {
            $finalName    = $baseName . '.svg';
            $absolutePath = $destDirAbsolute . DIRECTORY_SEPARATOR . $finalName;

            $svgContent = file_get_contents($tmpName);
            if ($svgContent === false) {
                return $failure('Could not read uploaded SVG file.');
            }

            $svgContent = self::sanitizeSvg($svgContent);
            if (file_put_contents($absolutePath, $svgContent) === false) {
                return $failure('Could not write SVG file to disk. Check server storage permissions.');
            }

            $relativeUrl = $webRelativePrefix . $finalName;
            self::log('OK', $context, $adminId, $origName . '→' . $finalName, $fileSize, 'svg');
            return ['ok' => true, 'relative_url' => $relativeUrl, 'absolute_path' => $absolutePath, 'error' => ''];
        }

        // ── 10. Raster path: resize+WebP conversion, fall back to original ext ──
        self::loadImageHelpers();

        $webpName = $baseName . '.webp';
        $webpPath = $destDirAbsolute . DIRECTORY_SEPARATOR . $webpName;

        // Prefer resize_and_convert_to_webp (caps at 1200 px) over bare convert.
        $converted = function_exists('resize_and_convert_to_webp')
            ? resize_and_convert_to_webp($tmpName, $webpPath)
            : (function_exists('convert_to_webp') && convert_to_webp($tmpName, $webpPath));

        if ($converted) {
            $relativeUrl = $webRelativePrefix . $webpName;
            self::log('OK', $context, $adminId, $origName . '→' . $webpName, $fileSize, 'webp');
            return ['ok' => true, 'relative_url' => $relativeUrl, 'absolute_path' => $webpPath, 'error' => ''];
        }

        // WebP conversion unavailable or failed — keep original extension.
        $finalName    = $baseName . '.' . $ext;
        $absolutePath = $destDirAbsolute . DIRECTORY_SEPARATOR . $finalName;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return $failure('Could not move uploaded file to destination. Check server storage permissions.');
        }

        $relativeUrl = $webRelativePrefix . $finalName;
        self::log('OK', $context, $adminId, $origName . '→' . $finalName, $fileSize, 'fallback');
        return ['ok' => true, 'relative_url' => $relativeUrl, 'absolute_path' => $absolutePath, 'error' => ''];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Sanitize an SVG by stripping <script> elements and on* event attributes.
     * Uses DOMDocument for precision; falls back to regex when the extension is absent.
     */
    private static function sanitizeSvg(string $svg): string
    {
        if (!class_exists('DOMDocument')) {
            // Best-effort regex fallback when the DOM extension is absent.
            $svg = (string)(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg);
            $svg = (string)(preg_replace('/\bon\w+\s*=/i', 'data-removed=', $svg) ?? $svg);
            return $svg;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadXML($svg);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($doc);

        // Remove all <script> elements.
        foreach ($xpath->query('//script') ?: [] as $node) {
            /** @var \DOMNode $node */
            $node->parentNode?->removeChild($node);
        }

        // Remove on* event-handler attributes from every element.
        foreach ($xpath->query('//@*') ?: [] as $attr) {
            /** @var \DOMAttr $attr */
            if (str_starts_with(strtolower($attr->localName), 'on')) {
                $attr->ownerElement?->removeAttributeNode($attr);
            }
        }

        $result = $doc->saveXML();
        return $result !== false ? $result : $svg;
    }

    /**
     * Require admin/includes/image_helpers.php (provides convert_to_webp()) once.
     */
    private static function loadImageHelpers(): void
    {
        if (function_exists('convert_to_webp')) {
            return;
        }

        $helpers = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'admin'
            . DIRECTORY_SEPARATOR . 'includes'
            . DIRECTORY_SEPARATOR . 'image_helpers.php';

        if (is_file($helpers)) {
            require_once $helpers;
        }
    }

    /**
     * Write a structured entry to storage/logs/upload.log.
     */
    private static function log(
        string $status,
        string $context,
        int    $adminId,
        string $file,
        int    $bytes,
        string $note
    ): void {
        $logDir = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . 'upload.log';
        $ts      = date('Y-m-d H:i:s');
        $line    = "[{$ts}] {$status} context={$context} file={$file} bytes={$bytes} admin={$adminId}"
                 . ($note !== '' ? " note={$note}" : '')
                 . PHP_EOL;

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Human-readable message for PHP file upload error codes.
     */
    private static function phpUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the server upload size limit.',
            UPLOAD_ERR_PARTIAL                        => 'Upload was interrupted. Please retry.',
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE                     => 'Server storage is not writable.',
            UPLOAD_ERR_EXTENSION                      => 'Upload blocked by a server extension.',
            UPLOAD_ERR_NO_FILE                        => 'No file selected.',
            default                                   => 'Upload failed (PHP error code ' . $code . ').',
        };
    }
}
