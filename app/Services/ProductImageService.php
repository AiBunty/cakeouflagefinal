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
        $slug = strtolower(trim((string)$categorySlug));
        $map = self::categoryPlaceholderMap();
        if ($slug !== '' && isset($map[$slug]) && self::isReadableWebPath($map[$slug])) {
            return $map[$slug];
        }
        return self::globalPlaceholder();
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

        return self::normalizePath($candidate);
    }

    return self::placeholderForCategory($categorySlug);
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
