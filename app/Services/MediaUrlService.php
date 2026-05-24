<?php
declare(strict_types=1);

namespace App\Services;

final class MediaUrlService
{
    public static function resolve(?string $path, string $variant = 'optimized', ?string $categorySlug = null): string
    {
        $raw = trim((string)$path);
        if ($raw === '') {
            return ProductImageService::placeholderForCategory($categorySlug);
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        // Normalize through existing product service to preserve legacy path compatibility.
        $normalized = ProductImageService::resolve($raw, $categorySlug);

        if ($variant === 'original' || $variant === '') {
            return $normalized;
        }

        $derived = self::variantPath($normalized, $variant);
        if ($derived !== '' && self::isReadableWebPath($derived)) {
            return $derived;
        }

        if (self::isReadableWebPath($normalized)) {
            return $normalized;
        }

        return ProductImageService::placeholderForCategory($categorySlug);
    }

    private static function variantPath(string $normalized, string $variant): string
    {
        // Unified pipeline originals are stored under /public/uploads/originals/{bucket}/{base}.{ext}
        if (!str_starts_with($normalized, '/public/uploads/originals/')) {
            return '';
        }

        $parts = pathinfo($normalized);
        $dir = (string)($parts['dirname'] ?? '');
        $filename = (string)($parts['filename'] ?? '');
        if ($dir === '' || $filename === '') {
            return '';
        }

        $bucket = trim(str_replace('/public/uploads/originals/', '', $dir), '/');
        if ($bucket === '') {
            return '';
        }

        if ($variant === 'optimized' || $variant === 'webp') {
            return '/public/uploads/optimized/' . $bucket . '/' . $filename . '.webp';
        }

        // thumbnail/mobile/grid/detail/retina variants are generated into thumbnails dir.
        return '/public/uploads/thumbnails/' . $bucket . '/' . $filename . '_' . $variant . '.webp';
    }

    private static function isReadableWebPath(string $path): bool
    {
        $root = dirname(__DIR__, 2);
        $absolute = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        return is_file($absolute) && is_readable($absolute);
    }
}
