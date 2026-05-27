<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class ProductImageService
{
    /**
     * Branded default images — shown for any product without an uploaded image.
     * Physical files live at:  public/assets/defaults/
     * Run __generate_default_webp.php once after placing the PNG to create the WebP.
     */
    public const DEFAULT_IMAGE_WEBP = '/public/assets/defaults/default-product-image.webp';
    public const DEFAULT_IMAGE_PNG  = '/public/assets/defaults/default-product-image.png';

    /**
     * Ultimate fallback — the branded default WebP.
     * Category-specific SVGs are returned first when they exist on disk.
     */
    private const GLOBAL_PLACEHOLDER = self::DEFAULT_IMAGE_WEBP;

    /** @var array<string, bool> */
    private static $existsCache = [];
    private static bool $defaultLoaded = false;
    private static string $defaultPlaceholder = self::GLOBAL_PLACEHOLDER;

    /**
     * Build a clean PDP/API gallery list from product + image rows.
     *
     * Rules:
     * - Prefer valid uploaded images from product_images.
     * - Deduplicate by final resolved URL.
     * - If none, use featured_image only when it is a valid uploaded image.
     * - If still none, return exactly one business/default placeholder image.
     *
     * @param array<string,mixed> $product
     * @param array<int,array<string,mixed>> $imageRows
     * @return array<int,array<string,mixed>>
     */
    public static function getProductGalleryImages(array $product, array $imageRows = [], ?string $categorySlug = null, int $maxImages = 2): array
    {
        $category = trim((string)($categorySlug ?? ($product['category_slug'] ?? '')));
        $max = min(max($maxImages, 1), 8);

        $final = [];
        $seen = [];

        foreach ($imageRows as $row) {
            $raw = trim((string)($row['image_url'] ?? ''));
            $uploadedUrl = self::resolveUploadedOnly($raw);
            if ($uploadedUrl === null) {
                continue;
            }

            $dedupeKey = strtolower($uploadedUrl);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $final[] = [
                'image_url' => $uploadedUrl,
                'alt_text' => trim((string)($row['alt_text'] ?? '')),
            ];

            if (count($final) >= $max) {
                break;
            }
        }

        if (empty($final)) {
            $featuredRaw = trim((string)($product['featured_image'] ?? ''));
            $featuredUploaded = self::resolveUploadedOnly($featuredRaw);
            if ($featuredUploaded !== null) {
                $final[] = [
                    'image_url' => $featuredUploaded,
                    'alt_text' => trim((string)($product['name'] ?? '')),
                ];
            }
        }

        if (empty($final)) {
            $final[] = [
                'image_url' => self::placeholderForCategory($category),
                'alt_text' => trim((string)($product['name'] ?? '')),
            ];
        }

        return $final;
    }

    /** @return array<string, string> */
    private static function categoryPlaceholderMap(): array
    {
        return [
            'classic-cakes' => '/client/assets/images/placeholders/classic-cakes.svg',
            'cheesecakes' => '/client/assets/images/placeholders/cheesecakes.svg',
            'dessert-cakes' => '/client/assets/images/placeholders/dessert-cakes.svg',
            'tart-cakes' => '/client/assets/images/placeholders/tart-cakes.svg',
            'tea-cakes-travel-cakes' => '/client/assets/images/placeholders/travel-cakes.svg',
            'baby-shower-cakes' => '/client/assets/images/placeholders/baby-shower.svg',
            'birthday-cakes' => '/client/assets/images/placeholders/birthday-cakes.svg',
            'anniversary-cakes' => '/client/assets/images/placeholders/anniversary-cakes.svg',
            'engagement-wedding-cakes' => '/client/assets/images/placeholders/wedding-cakes.svg',
            'brownies' => '/client/assets/images/placeholders/brownies.svg',
            'cookies' => '/client/assets/images/placeholders/cookies.svg',
            'chocolates' => '/client/assets/images/placeholders/chocolates.svg',
            'mini-tarts' => '/client/assets/images/placeholders/mini-tarts.svg',
            'dessert-tubs' => '/client/assets/images/placeholders/dessert-tubs.svg',
            'gifting' => '/client/assets/images/placeholders/gifting.svg',
            'gift-hampers' => '/client/assets/images/placeholders/gifting.svg',
            'platters' => '/client/assets/images/placeholders/platters.svg',
            'courses' => '/client/assets/images/placeholders/courses.svg',
            'events' => '/client/assets/images/placeholders/events.svg',
            'webinars' => '/client/assets/images/placeholders/events.svg',
        ];
    }

    public static function placeholderForCategory(?string $categorySlug): string
    {
        // If the admin has configured a custom global product image, prefer it over category SVGs.
        $global = self::globalPlaceholder();
        if ($global !== self::GLOBAL_PLACEHOLDER) {
            return $global;
        }

        // Fall back to category-specific SVG when no custom global image is configured.
        $slug = strtolower(trim((string)$categorySlug));
        $map = self::categoryPlaceholderMap();
        if ($slug !== '' && isset($map[$slug]) && self::isReadableWebPath($map[$slug])) {
            return $map[$slug];
        }

        return $global;
    }

public static function resolve(?string $path, ?string $categorySlug = null): string
{
    $candidate = trim((string)$path);

    if ($candidate !== '') {
        // External URL — return as-is
        if (self::isExternalUrl($candidate)) {
            return $candidate;
        }

        if (self::isKnownDefaultPlaceholderPath($candidate)) {
            return self::globalPlaceholder();
        }

        $normalized = self::normalizePath($candidate);

        // For local web paths, ensure we do not emit broken URLs that trigger 404s in the browser.
        if (self::isLocalWebPath($normalized)) {
            if (self::isReadableWebPath($normalized)) {
                return $normalized;
            }

            $recovered = self::recoverMissingLocalPath($normalized);
            if ($recovered !== null) {
                return $recovered;
            }

            return self::placeholderForCategory($categorySlug);
        }

        return $normalized;
    }

    return self::placeholderForCategory($categorySlug);
}

    private static function isLocalWebPath(string $path): bool
    {
        return !self::isExternalUrl($path) && strpos($path, '/') === 0;
    }

    private static function recoverMissingLocalPath(string $missingPath): ?string
    {
        $filename = basename($missingPath);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $candidates = [
            '/uploads/products/' . $filename,
            '/public/uploads/' . $filename,
        ];

        foreach ($candidates as $candidate) {
            if (self::isReadableWebPath($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

/**
 * Normalizes legacy and current path formats to a canonical web-root-relative path.
 * Handles: legacy /Cakeouflage-E-commerce/ prefix, /assets/images/products/ paths,
 * bare /assets/ paths, old /uploads/ paths, and relative filenames.
 */
private static function normalizePath(string $raw): string
{
    // Strip legacy /Cakeouflage-E-commerce/ prefix
    $path = preg_replace('#^/?Cakeouflage-E-commerce/#i', '/', $raw) ?? $raw;

    // Ensure leading slash; bare filenames → product image directory
    if ($path === '' || $path[0] !== '/') {
        return '/client/assets/images/product/' . ltrim($path, '/');
    }

    // Legacy: /assets/images/products/ or /assets/images/product/ → canonical product path
    if (strpos($path, '/assets/images/products/') === 0 || strpos($path, '/assets/images/product/') === 0) {
        return '/client/assets/images/product/' . basename($path);
    }

    // Legacy default images under /assets/defaults should map to served /public/assets/defaults.
    if (strpos($path, '/assets/defaults/') === 0) {
        return '/public' . $path;
    }

    // Legacy: /assets/ that is NOT the defaults dir → prefix with /client/
    if (strpos($path, '/assets/') === 0) {
        return '/client' . $path;
    }

    // Legacy: old /uploads/ paths → /public/uploads/
    if (strpos($path, '/uploads/') === 0) {
        return '/public' . $path;
    }

    return self::normalizeWebPath($path);
}

    private static function isKnownDefaultPlaceholderPath(string $path): bool
    {
        $normalized = self::normalizePath($path);
        $known = [
            '/public/assets/defaults/default-product-image.webp',
            '/assets/defaults/default-product-image.webp',
            '/public/assets/defaults/default-product-image.png',
            '/assets/defaults/default-product-image.png',
        ];

        return in_array($normalized, $known, true);
    }

    private static function resolveUploadedOnly(string $raw): ?string
    {
        $candidate = trim($raw);
        if ($candidate === '') {
            return null;
        }

        if (self::isExternalUrl($candidate)) {
            return $candidate;
        }

        if (self::isKnownDefaultPlaceholderPath($candidate)) {
            return null;
        }

        $normalized = self::normalizePath($candidate);
        if (self::isKnownDefaultPlaceholderPath($normalized)) {
            return null;
        }

        if (!self::isLocalWebPath($normalized)) {
            return null;
        }

        if (self::isReadableWebPath($normalized)) {
            return $normalized;
        }

        $recovered = self::recoverMissingLocalPath($normalized);
        if ($recovered !== null && !self::isKnownDefaultPlaceholderPath($recovered)) {
            return $recovered;
        }

        return null;
    }

    private static function globalPlaceholder(): string
    {
        if (self::$defaultLoaded) {
            return self::$defaultPlaceholder;
        }

        self::$defaultLoaded = true;
        self::$defaultPlaceholder = self::GLOBAL_PLACEHOLDER;

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute([':key' => 'default_product_image_url']);
            $configured = trim((string)($stmt->fetchColumn() ?: ''));
            if ($configured === '') {
                return self::$defaultPlaceholder;
            }

            if (self::isExternalUrl($configured)) {
                self::$defaultPlaceholder = $configured;
                return self::$defaultPlaceholder;
            }

            $normalized = self::normalizePath($configured);
            if (self::isReadableWebPath($normalized)) {
                self::$defaultPlaceholder = $normalized;
            }
        } catch (Throwable $e) {
            // Keep static fallback when settings table is unavailable.
        }

        return self::$defaultPlaceholder;
    }

    private static function isExternalUrl(string $path): bool
    {
        $lower = strtolower($path);
        return strpos($lower, 'https://') === 0 || strpos($lower, 'http://') === 0;
    }

    private static function normalizeWebPath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private static function isReadableWebPath(string $path): bool
    {
        $normalized = self::normalizeWebPath($path);
        if (isset(self::$existsCache[$normalized])) {
            return self::$existsCache[$normalized];
        }

        $fsPath = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        $ok = is_file($fsPath) && is_readable($fsPath);
        self::$existsCache[$normalized] = $ok;
        return $ok;
    }
}
