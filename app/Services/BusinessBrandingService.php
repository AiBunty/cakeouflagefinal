<?php
declare(strict_types=1);

namespace App\Services;

final class BusinessBrandingService
{
    public const DEFAULT_NAVBAR_LOGO = '/client/assets/images/mainlogo.svg';
    public const DEFAULT_FOOTER_LOGO = '/client/assets/images/whitelogo.png';

    /**
     * @param array<string, mixed> $siteConfig
     * @return array<string, string>
     */
    public static function build(array $siteConfig): array
    {
        $businessLogo = self::sanitizePath((string)($siteConfig['business_logo'] ?? ''));
        $navbarLogo = self::pick(
            [
                (string)($siteConfig['navbar_logo_url'] ?? ''),
                $businessLogo,
                self::DEFAULT_NAVBAR_LOGO,
            ],
            self::DEFAULT_NAVBAR_LOGO
        );

        $footerLogo = self::pick(
            [
                (string)($siteConfig['footer_logo_url'] ?? ''),
                $businessLogo,
                self::DEFAULT_FOOTER_LOGO,
            ],
            self::DEFAULT_FOOTER_LOGO
        );

        return [
            'navbar_logo_url' => $navbarLogo,
            'footer_logo_url' => $footerLogo,
            'business_logo' => self::pick([
                $businessLogo,
                $navbarLogo,
                self::DEFAULT_NAVBAR_LOGO,
            ], self::DEFAULT_NAVBAR_LOGO),
            'favicon_url' => self::pick([
                (string)($siteConfig['favicon_url'] ?? ''),
                $navbarLogo,
                self::DEFAULT_NAVBAR_LOGO,
            ], self::DEFAULT_NAVBAR_LOGO),
            'navbar_logo_fallback' => self::DEFAULT_NAVBAR_LOGO,
            'footer_logo_fallback' => self::DEFAULT_FOOTER_LOGO,
            'navbar_logo_onerror' => self::onError(self::DEFAULT_NAVBAR_LOGO),
            'footer_logo_onerror' => self::onError(self::DEFAULT_FOOTER_LOGO),
        ];
    }

    /**
     * @param list<string> $candidates
     */
    private static function pick(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            $normalized = self::sanitizePath($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $fallback;
    }

    private static function sanitizePath(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $lower = strtolower($trimmed);
        if (strpos($lower, 'javascript:') === 0) {
            return '';
        }

        if (strpos($trimmed, 'http://') === 0 || strpos($trimmed, 'https://') === 0 || strpos($trimmed, '//') === 0) {
            return $trimmed;
        }

        if ($trimmed[0] === '/') {
            return $trimmed;
        }

        return '/' . ltrim($trimmed, '/');
    }

    private static function onError(string $fallbackPath): string
    {
        return "this.onerror=null;this.src='" . addslashes($fallbackPath) . "';";
    }
}
