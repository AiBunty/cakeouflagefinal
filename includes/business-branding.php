<?php
declare(strict_types=1);

use App\Services\BusinessBrandingService;

if (!function_exists('cakeouflage_branding_assets')) {
    /**
     * @param array<string, mixed> $siteConfig
     * @return array<string, string>
     */
    function cakeouflage_branding_assets(array $siteConfig): array
    {
        return BusinessBrandingService::build($siteConfig);
    }
}
