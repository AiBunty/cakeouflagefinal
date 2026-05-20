<?php
declare(strict_types=1);

namespace App\Services;

final class ProductImageService
{
    private const GLOBAL_PLACEHOLDER = '/client/assets/images/placeholders/product-generic.svg';

    /** @var array<string, bool> */
    private static $existsCache = [];

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
        return self::GLOBAL_PLACEHOLDER;
    }

public static function resolve(?string $path, ?string $categorySlug = null): string
{
    $candidate = trim((string)$path);

    if ($candidate !== '') {

        // external URL
        if (self::isExternalUrl($candidate)) {
            return $candidate;
        }

        // already contains project folder → don't duplicate
        if (str_starts_with($candidate, '/Cakeouflage-E-commerce')) {
            return self::normalizeWebPath($candidate);
        }

        // normal case → add base path
        return '/Cakeouflage-E-commerce' . self::normalizeWebPath($candidate);
    }

    return self::placeholderForCategory($categorySlug);
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
