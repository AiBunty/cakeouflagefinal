<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\BusinessBrandingService;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $page, array $data = []): void
    {
        $title = (string)($data['title'] ?? 'Cakeouflage');
        $layout = (string)($data['layout'] ?? 'public');
        $breadcrumbs = $data['breadcrumbs'] ?? [];
        $brand = [
            'name' => Env::get('BAKERY_NAME', 'Cakeouflage'),
            'tagline' => Env::get('BAKERY_TAGLINE', 'We bake sweet wonderful happy memories'),
        ];
        $csrfToken = Csrf::token();
        $siteConfig = [
            'navLinks' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Shop', 'href' => '/shop'],
                ['label' => 'Course', 'href' => '/course'],
                ['label' => 'B2B', 'href' => '/b2b'],
                ['label' => 'Contact', 'href' => '/contact'],
            ],
            'footerLinks' => [
                ['label' => 'Privacy Policy', 'href' => 'http://localhost/Cakeouflage-E-commerce/privacy-policy']
              //  ['label' => 'Terms', 'href' => '/terms'],
             //   ['label' => 'Shipping', 'href' => '/shipping-info'],
              //  ['label' => 'FAQ', 'href' => '/faq'],
            ],
            'contact' => [
                'phone' => Env::get('SUPPORT_PHONE', '+91 96735 65935'),
                'email' => Env::get('SUPPORT_EMAIL', 'cakeouflage@gmail.com'),
                'city' => Env::get('BAKERY_CITY', 'Nashik'),
                'whatsapp' => Env::get('SUPPORT_PHONE', '+91 96735 65935'),
                'website' => Env::get('BAKERY_WEBSITE', ''),
                'business_hours' => Env::get('BUSINESS_HOURS', ''),
                'map_embed_url' => Env::get('CONTACT_MAP_EMBED_URL', ''),
            ],
            'business' => [
                'address_line1' => '',
                'address_line2' => '',
                'address' => '',
                'state' => Env::get('BAKERY_STATE', 'Maharashtra'),
                'postal_code' => '',
                'gst_number' => '',
                'pan_number' => '',
                'store_food_mode' => 'veg_only',
                'currency_code' => Env::get('CURRENCY_CODE', 'INR'),
                'currency_symbol' => Env::get('CURRENCY_SYMBOL', 'Rs'),
            ],
            'currency_symbol' => Env::get('CURRENCY_SYMBOL', 'Rs'),
            'currency_code' => Env::get('CURRENCY_CODE', 'INR'),
            'navbar_logo_url' => '/client/assets/images/mainlogo.svg',
            'footer_logo_url' => '/client/assets/images/whitelogo.png',
            'business_logo' => '/client/assets/images/mainlogo.svg',
        ];

        // Merge DB-driven business settings when available.
        try {
            $pdo = Database::getConnection();
            $keys = [
                'business_name',
                'business_address_line1',
                'business_address_line2',
                'business_city',
                'business_state',
                'business_postal_code',
                'business_phone',
                'business_email',
                'business_gst_number',
                'business_pan_number',
                'business_address',
                'business_logo',
                'store_food_mode',
                'currency_code',
                'currency_symbol',
                'navbar_logo_url',
                'footer_logo_url',
                'business_website',
                'support_whatsapp',
                'business_hours',
                'contact_map_embed_url',
            ];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
            $stmt->execute($keys);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($rows as $row) {
                $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
            }

            if (($settings['business_name'] ?? '') !== '') {
                $brand['name'] = $settings['business_name'];
            }
            if (($settings['business_phone'] ?? '') !== '') {
                $siteConfig['contact']['phone'] = $settings['business_phone'];
            }
            if (($settings['business_email'] ?? '') !== '') {
                $siteConfig['contact']['email'] = $settings['business_email'];
            }
            if (($settings['business_city'] ?? '') !== '') {
                $siteConfig['contact']['city'] = $settings['business_city'];
            }
            if (($settings['support_whatsapp'] ?? '') !== '') {
                $siteConfig['contact']['whatsapp'] = $settings['support_whatsapp'];
            }
            if (($settings['business_website'] ?? '') !== '') {
                $siteConfig['contact']['website'] = $settings['business_website'];
            }
            if (($settings['business_hours'] ?? '') !== '') {
                $siteConfig['contact']['business_hours'] = $settings['business_hours'];
            }
            if (($settings['contact_map_embed_url'] ?? '') !== '') {
                $siteConfig['contact']['map_embed_url'] = $settings['contact_map_embed_url'];
            }

            $siteConfig['business']['address_line1'] = $settings['business_address_line1'] ?? '';
            $siteConfig['business']['address_line2'] = $settings['business_address_line2'] ?? '';
            $siteConfig['business']['address'] = $settings['business_address'] ?? '';
            $siteConfig['business']['state'] = ($settings['business_state'] ?? '') !== ''
                ? $settings['business_state']
                : $siteConfig['business']['state'];
            $siteConfig['business']['postal_code'] = $settings['business_postal_code'] ?? '';
            $siteConfig['business']['gst_number'] = $settings['business_gst_number'] ?? '';
            $siteConfig['business']['pan_number'] = $settings['business_pan_number'] ?? '';
            $siteConfig['business']['store_food_mode'] = getDietaryMode($pdo);
            $siteConfig['business']['currency_code'] = ($settings['currency_code'] ?? '') !== ''
                ? strtoupper((string)$settings['currency_code'])
                : $siteConfig['business']['currency_code'];
            $siteConfig['business']['currency_symbol'] = ($settings['currency_symbol'] ?? '') !== ''
                ? (string)$settings['currency_symbol']
                : $siteConfig['business']['currency_symbol'];
            $siteConfig['currency_symbol'] = $siteConfig['business']['currency_symbol'];
            $siteConfig['currency_code'] = $siteConfig['business']['currency_code'];

            if (($settings['business_logo'] ?? '') !== '') {
                $siteConfig['business_logo'] = $settings['business_logo'];
            }

            if (($settings['navbar_logo_url'] ?? '') !== '') {
                $siteConfig['navbar_logo_url'] = $settings['navbar_logo_url'];
            }
            if (($settings['footer_logo_url'] ?? '') !== '') {
                $siteConfig['footer_logo_url'] = $settings['footer_logo_url'];
            }
        } catch (\Throwable $e) {
            // Keep env defaults when DB settings are unavailable.
        }

        $branding = BusinessBrandingService::build($siteConfig);
        $siteConfig['branding'] = $branding;
        $siteConfig['navbar_logo_url'] = $branding['navbar_logo_url'];
        $siteConfig['footer_logo_url'] = $branding['footer_logo_url'];
        $siteConfig['business_logo'] = $branding['business_logo'];

        // Inject DB-driven navigation tree for header/mobile-menu partials
        try {
            $navTree = \App\Services\CategoryService::getNavTree();
        } catch (\Throwable $e) {
            $navTree = [];
            // DB unavailable locally — menu will be empty until connected to StackCP
        }

        extract($data, EXTR_SKIP);

        include __DIR__ . '/../Views/partials/head.php';

        if ($layout === 'public') {
            include __DIR__ . '/../Views/partials/header.php';
            include __DIR__ . '/../Views/partials/mobile-menu.php';
            include __DIR__ . '/../Views/partials/breadcrumb.php';
        } elseif ($layout === 'admin') {
            include __DIR__ . '/../Views/partials/admin-shell-start.php';
            include __DIR__ . '/../Views/partials/admin-sidebar.php';
            include __DIR__ . '/../Views/partials/admin-topbar.php';
        } elseif ($layout === 'admin-auth') {
            include __DIR__ . '/../Views/partials/admin-auth-start.php';
        }

        $viewPath = __DIR__ . '/../Views/pages/' . $page . '.php';
        if (is_file($viewPath)) {
            include $viewPath;
        } else {
            include __DIR__ . '/../Views/errors/404.php';
        }

        if ($layout === 'public') {
            include __DIR__ . '/../Views/partials/footer.php';
        } elseif ($layout === 'admin') {
            include __DIR__ . '/../Views/partials/admin-shell-end.php';
        } elseif ($layout === 'admin-auth') {
            include __DIR__ . '/../Views/partials/admin-auth-end.php';
        }

        include __DIR__ . '/../Views/partials/scripts.php';
    }
}
