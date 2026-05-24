<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\View;
use App\Services\ByocQuoteExpiryService;
use App\Services\CategoryService;

final class WebController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /cart\n";
        echo "Disallow: /checkout\n";
        echo "Disallow: /account\n";
        echo "Disallow: /orders\n";
        echo "Sitemap: " . $this->absoluteUrl('/sitemap_index.xml') . "\n";
        echo "Sitemap: " . $this->absoluteUrl('/sitemap.xml') . "\n";
    }

    public function sitemapIndex(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo "  <sitemap>\n";
        echo '    <loc>' . $this->xml($this->absoluteUrl('/sitemap.xml')) . "</loc>\n";
        echo '    <lastmod>' . gmdate('c') . "</lastmod>\n";
        echo "  </sitemap>\n";
        echo "</sitemapindex>\n";
    }

    public function sitemap(): void
    {
        $urls = [
            ['path' => '/', 'priority' => '1.0'],
            ['path' => '/shop', 'priority' => '0.9'],
            ['path' => '/about', 'priority' => '0.7'],
            ['path' => '/course', 'priority' => '0.7'],
            ['path' => '/events', 'priority' => '0.7'],
            ['path' => '/b2b', 'priority' => '0.6'],
            ['path' => '/contact', 'priority' => '0.6'],
            ['path' => '/faq', 'priority' => '0.5'],
            ['path' => '/privacy-policy', 'priority' => '0.3'],
            ['path' => '/terms', 'priority' => '0.3'],
            ['path' => '/shipping-info', 'priority' => '0.3'],
            ['path' => '/custom-cake-inquiry', 'priority' => '0.8'],
        ];

        try {
            $db = Database::getInstance();

            foreach ($db->fetchAll("SELECT slug, updated_at FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id ASC") as $row) {
                $urls[] = ['path' => '/category/' . $this->urlSegment((string) $row['slug']), 'lastmod' => $row['updated_at'], 'priority' => '0.8'];
            }

            foreach ($db->fetchAll("SELECT slug, updated_at FROM products WHERE availability_status <> 'draft' ORDER BY id ASC") as $row) {
                $urls[] = ['path' => '/product/' . $this->urlSegment((string) $row['slug']), 'lastmod' => $row['updated_at'], 'priority' => '0.8'];
            }

            foreach ($db->fetchAll("SELECT slug, updated_at FROM courses WHERE is_active = 1 ORDER BY id ASC") as $row) {
                $urls[] = ['path' => '/course/' . $this->urlSegment((string) $row['slug']), 'lastmod' => $row['updated_at'], 'priority' => '0.7'];
            }

            foreach ($db->fetchAll("SELECT slug, updated_at FROM events WHERE is_published = 1 AND event_status <> 'draft' ORDER BY id ASC") as $row) {
                $urls[] = ['path' => '/events/' . $this->urlSegment((string) $row['slug']), 'lastmod' => $row['updated_at'], 'priority' => '0.7'];
            }
        } catch (\Throwable $e) {
            // Keep the sitemap alive even if the database is temporarily unavailable.
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            echo "  <url>\n";
            echo '    <loc>' . $this->xml($this->absoluteUrl($url['path'])) . "</loc>\n";
            if (!empty($url['lastmod'])) {
                echo '    <lastmod>' . $this->xml($this->formatLastModified($url['lastmod'])) . "</lastmod>\n";
            }
            echo '    <priority>' . $url['priority'] . "</priority>\n";
            echo "  </url>\n";
        }

        echo "</urlset>\n";
    }

    public function home(): void
    {
        View::render('home', ['title' => 'Cakeouflage | Premium Bakery']);
    }

    private function absoluteUrl(string $path): string
    {
        $configured = trim((string) Env::get('APP_URL', ''));
        if ($configured !== '') {
            $baseUrl = rtrim($configured, '/');
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
        } else {
            $baseUrl = rtrim((string) Env::get('APP_BASE_URL', 'http://localhost'), '/');
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function formatLastModified(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? gmdate('c') : gmdate('c', $timestamp);
    }

    private function urlSegment(string $value): string
    {
        return rawurlencode($value);
    }

    public function shop(): void
    {
        header('Location: /category', true, 301);
        exit;
    }

    public function course(): void
    {
        View::render('course', ['title' => 'Cake Making Course', 'breadcrumbs' => [['label' => 'Course']]]);
    }

    public function events(): void
    {
        View::render('events', ['title' => 'Webinars & Events', 'breadcrumbs' => [['label' => 'Events']]]);
    }

    public function b2b(): void
    {
        View::render('b2b', ['title' => 'Corporate & Bulk Orders', 'breadcrumbs' => [['label' => 'B2B']]]);
    }

    public function b2bLogin(): void
    {
        if ($this->hasB2bSession()) {
            header('Location: /b2b/dashboard');
            return;
        }

        View::render('b2b-login', ['title' => 'B2B Login', 'breadcrumbs' => [['label' => 'B2B Login']]]);
    }

    public function b2bRegister(): void
    {
        if ($this->hasB2bSession()) {
            header('Location: /b2b/dashboard');
            return;
        }

        View::render('b2b-register', ['title' => 'B2B Registration', 'breadcrumbs' => [['label' => 'B2B Registration']]]);
    }

    public function b2bDashboard(): void
    {
        if (!$this->hasB2bSession()) {
            header('Location: /b2b/login');
            return;
        }

        View::render('b2b-dashboard', ['title' => 'B2B Dashboard', 'breadcrumbs' => [['label' => 'B2B Dashboard']]]);
    }

    public function contact(): void
    {
        View::render('contact', ['title' => 'Contact', 'breadcrumbs' => [['label' => 'Contact']]]);
    }

    public function customCakeInquiry(): void
    {
        View::render('custom-cake-inquiry', [
            'title' => 'Custom Cake Inquiry',
            'breadcrumbs' => [['label' => 'Custom Cake Inquiry']],
        ]);
    }

    public function customCakeQuoteAccept(string $token): void
    {
        $token = trim($token);
        $quote = null;
        $inquiryMeta = [];
        $isExpired = false;

        if ($token !== '') {
            try {
                $pdo = Database::getConnection();
                (new ByocQuoteExpiryService())->expireDueQuotes($pdo);
                $sql = 'SELECT
                            bql.token,
                            bql.expires_at AS link_expires_at,
                            bql.used_at,
                            bq.id AS byoc_quote_id,
                            bq.quote_number,
                            bq.quote_subject,
                            bq.quote_message,
                            bq.quote_amount,
                            bq.currency,
                            bq.status AS quote_status,
                            bq.accepted_at,
                            bq.order_id,
                            bq.expires_at AS quote_expires_at,
                            i.id AS inquiry_id,
                            i.name,
                            i.email,
                            i.phone,
                            i.message
                        FROM byoc_quote_links bql
                        INNER JOIN byoc_quotes bq ON bq.id = bql.byoc_quote_id
                        INNER JOIN inquiries i ON i.id = bq.inquiry_id
                        WHERE bql.token = :token
                        LIMIT 1';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['token' => $token]);
                $quote = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

                if ($quote) {
                    $meta = json_decode((string)($quote['message'] ?? ''), true);
                    if (is_array($meta)) {
                        $inquiryMeta = $meta;
                    }

                    $expiryTs = strtotime((string)($quote['link_expires_at'] ?? ''));
                    $isExpired = $expiryTs !== false && $expiryTs < time();
                }
            } catch (\Throwable $e) {
                $quote = null;
            }
        }

        View::render('byoc-quote-accept', [
            'title' => 'Accept Custom Cake Quote',
            'breadcrumbs' => [['label' => 'Accept Quote']],
            'token' => $token,
            'quote' => $quote,
            'inquiryMeta' => $inquiryMeta,
            'isExpired' => $isExpired,
        ]);
    }

    public function byocOrderConfirmation(string $orderNumber): void
    {
        $orderNumber = preg_replace('/[^A-Z0-9\-]/', '', strtoupper(trim($orderNumber)));
        $order = null;
        $token = trim((string)($_GET['t'] ?? ''));

        if ($orderNumber !== '' && $token !== '') {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare(
                    'SELECT o.order_number, o.customer_name, o.customer_email, o.customer_phone,
                            o.grand_total, o.advance_amount, o.payment_status, o.order_status,
                            o.delivery_street, o.delivery_postal_code, o.scheduled_slot_label,
                            o.created_at, o.admin_note
                     FROM orders o
                     INNER JOIN byoc_quotes bq ON bq.order_id = o.id
                     INNER JOIN byoc_quote_links bql ON bql.byoc_quote_id = bq.id
                     WHERE o.order_number = :order_number
                       AND bql.token = :token
                     LIMIT 1'
                );
                $stmt->execute(['order_number' => $orderNumber, 'token' => $token]);
                $order = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable $e) {
                $order = null;
            }
        }

        View::render('order-confirmation', [
            'title' => 'Order Confirmed',
            'breadcrumbs' => [['label' => 'Order Confirmation']],
            'order' => $order,
            'orderNumber' => $orderNumber,
        ]);
    }

    public function login(): void
    {
        View::render('login', ['title' => 'Login', 'breadcrumbs' => [['label' => 'Login']]]);
    }

    public function forgotPassword(): void
    {
        View::render('forgot-password', [
            'title' => 'Forgot Password',
            'breadcrumbs' => [['label' => 'Forgot Password']],
            'pageTitle' => 'Forgot Password',
        ]);
    }

    public function resetPassword(): void
    {
        View::render('reset-password', [
            'title' => 'Reset Password',
            'breadcrumbs' => [['label' => 'Reset Password']],
            'pageTitle' => 'Reset Password',
        ]);
    }

    public function register(): void
    {
        View::render('register', ['title' => 'Register', 'breadcrumbs' => [['label' => 'Register']]]);
    }

    public function faq(): void
    {
        View::render('faq', ['title' => 'FAQ', 'breadcrumbs' => [['label' => 'FAQ']]]);
    }

    public function privacy(): void
    {
        View::render('privacy', ['title' => 'Privacy Policy', 'breadcrumbs' => [['label' => 'Privacy Policy']]]);
    }

    public function terms(): void
    {
        View::render('terms', ['title' => 'Terms', 'breadcrumbs' => [['label' => 'Terms']]]);
    }

    public function shipping(): void
    {
        View::render('shipping', ['title' => 'Shipping & Delivery', 'breadcrumbs' => [['label' => 'Shipping']]]);
    }

    public function categories(): void
    {
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {
            View::render('category', [
                'title'         => 'All Products | Cakeouflage',
                'category'      => ['id' => null, 'name' => 'All Products', 'slug' => '', 'description' => 'Browse our full handcrafted collection of cakes, desserts and gifting hampers.', 'banner_image' => ''],
                'children'      => [],
                'products'      => [],
                'totalProducts' => 0,
                'breadcrumbs'   => [['label' => 'Home', 'url' => '/'], ['label' => 'All Products']],
                'currentPage'   => 1,
                'perPage'       => 24,
                'dbOffline'     => true,
            ]);
            return;
        }

        $columnRows = $db->fetchAll('SHOW COLUMNS FROM products');
        $productsColumnMap = [];
        foreach ($columnRows as $columnRow) {
            $field = strtolower((string)($columnRow['Field'] ?? ''));
            if ($field !== '') {
                $productsColumnMap[$field] = true;
            }
        }
        $hasIsVeg         = isset($productsColumnMap['is_veg']);
        $hasChefSpecial   = isset($productsColumnMap['is_chef_special']);
        $hasTopperEnabled = isset($productsColumnMap['topper_enabled']);
        $hasNoteEnabled   = isset($productsColumnMap['note_enabled']);

        $effectivePriceSql = 'COALESCE(NULLIF(p.starting_price, 0), pv_min.min_price, p.base_price, 0)';

        $whereSql = [
            'p.deleted_at IS NULL',
            "p.availability_status <> 'draft'",
        ];
        $params = [];

        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $whereSql[] = '(p.name LIKE ? OR p.short_description LIKE ? OR COALESCE(p.flavour_notes, "") LIKE ? OR COALESCE(p.occasion_tag, "") LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        $dietaryRaw = $_GET['dietary'] ?? [];
        $dietaryValues = is_array($dietaryRaw) ? $dietaryRaw : explode(',', (string)$dietaryRaw);
        $allowedDietary = ['regular', 'eggless', 'vegan', 'sugar_free'];
        $dietaryValues = array_values(array_unique(array_values(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $dietaryValues), static function (string $value) use ($allowedDietary): bool {
            return in_array($value, $allowedDietary, true);
        }))));
        if (!empty($dietaryValues)) {
            $dietaryPlaceholders = implode(',', array_fill(0, count($dietaryValues), '?'));
            $whereSql[] = "p.dietary_tag IN ($dietaryPlaceholders)";
            $params     = array_merge($params, $dietaryValues);
        }

        $isVegParam = (string)($_GET['is_veg'] ?? '');
        if ($hasIsVeg && in_array($isVegParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_veg = ?';
            $params[]   = (int)$isVegParam;
        }

        $isBestsellerParam = (string)($_GET['is_bestseller'] ?? '');
        if (in_array($isBestsellerParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_bestseller = ?';
            $params[]   = (int)$isBestsellerParam;
        }

        $isChefSpecialParam = (string)($_GET['is_chef_special'] ?? '');
        if ($hasChefSpecial && in_array($isChefSpecialParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_chef_special = ?';
            $params[]   = (int)$isChefSpecialParam;
        }

        if ((string)($_GET['customizable'] ?? '') === '1') {
            $whereSql[] = 'COALESCE(TRIM(p.customisation_note), "") <> ""';
        }

        if ($hasTopperEnabled && (string)($_GET['topper_enabled'] ?? '') === '1') {
            $whereSql[] = 'p.topper_enabled = 1';
        }

        if ($hasNoteEnabled && (string)($_GET['note_enabled'] ?? '') === '1') {
            $whereSql[] = 'p.note_enabled = 1';
        }

        if ((string)($_GET['same_day'] ?? '') === '1') {
            $whereSql[] = 'p.lead_time_hours <= 24';
        }

        if ((string)($_GET['express'] ?? '') === '1') {
            $whereSql[] = 'p.lead_time_hours <= 8';
        }

        $maxPrice = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($maxPrice !== false && $maxPrice !== null) {
            $whereSql[] = $effectivePriceSql . ' <= ?';
            $params[]   = (float)$maxPrice;
        }

        $priceBucketRaw = trim((string)($_GET['price_bucket'] ?? ''));
        $priceBucketMap = [
            'under-500'  => 'under_500',
            'under-1000' => '500_1000',
            '1000-2000'  => '1000_2000',
            'above-2000' => 'above_2000',
            'under_500'  => 'under_500',
            '500_1000'   => '500_1000',
            '1000_2000'  => '1000_2000',
            'above_2000' => 'above_2000',
        ];
        $priceBucket = $priceBucketMap[$priceBucketRaw] ?? '';
        if ($priceBucket === 'under_500') {
            $whereSql[] = $effectivePriceSql . ' < ?';
            $params[]   = 500;
        } elseif ($priceBucket === '500_1000') {
            $whereSql[] = $effectivePriceSql . ' <= ?';
            $params[]   = 1000;
        } elseif ($priceBucket === '1000_2000') {
            $whereSql[] = $effectivePriceSql . ' > ? AND ' . $effectivePriceSql . ' <= ?';
            $params[]   = 1000;
            $params[]   = 2000;
        } elseif ($priceBucket === 'above_2000') {
            $whereSql[] = $effectivePriceSql . ' > ?';
            $params[]   = 2000;
        }

        $sortParam = trim((string)($_GET['sort'] ?? 'latest'));
        if ($sortParam === 'newest') {
            $sortParam = 'latest';
        }
        $sortMap = [
            'latest'     => 'p.created_at DESC',
            'price_asc'  => $effectivePriceSql . ' ASC',
            'price_desc' => $effectivePriceSql . ' DESC',
            'popular'    => 'p.is_bestseller DESC, p.review_count DESC, p.created_at DESC',
        ];
        $sortSql = $sortMap[$sortParam] ?? $sortMap['latest'];

        $whereClause = implode(' AND ', $whereSql);

        $countSql = "
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            WHERE $whereClause
        ";
        $totalProducts = (int)$db->fetchScalar($countSql, $params);

        $perPage = 24;
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $isVegSelectSql = $hasIsVeg ? 'p.is_veg' : '1 AS is_veg';

        $productSql = "
            SELECT
                p.id,
                p.name,
                p.slug,
                p.short_description,
                p.featured_image,
                p.dietary_tag,
                p.is_featured,
                p.is_bestseller,
                $isVegSelectSql,
                $effectivePriceSql AS min_price,
                (
                    SELECT pi.image_url
                    FROM product_images pi
                    WHERE pi.product_id = p.id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS thumb
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            WHERE $whereClause
            ORDER BY $sortSql
            LIMIT $perPage OFFSET $offset
        ";
        $products = $db->fetchAll($productSql, $params);

        View::render('category', [
            'title'         => 'All Products | Cakeouflage',
            'metaDesc'      => 'Browse our full collection of handcrafted cakes, desserts, and gifting hampers.',
            'category'      => ['id' => null, 'name' => 'All Products', 'slug' => '', 'description' => 'Browse our full handcrafted collection of cakes, desserts and gifting hampers.', 'banner_image' => ''],
            'children'      => [],
            'products'      => $products,
            'totalProducts' => $totalProducts,
            'breadcrumbs'   => [['label' => 'Home', 'url' => '/'], ['label' => 'All Products']],
            'currentPage'   => $page,
            'perPage'       => $perPage,
        ]);
    }

    public function category(string $slug): void
    {
        $categoryId = null;
        if (preg_match('/^(.*)-(\d+)$/', $slug, $matches)) {
            $slug = (string)$matches[1];
            $categoryId = (int)$matches[2];
        }

        $slug = strtolower(trim($slug));
        if ($slug === '') {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Not Found']);
            return;
        }

        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {
            View::render('category', [
                'title'         => 'Category | Cakeouflage',
                'category'      => ['id' => 0, 'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'description' => '', 'banner_image' => ''],
                'children'      => [],
                'products'      => [],
                'totalProducts' => 0,
                'breadcrumbs'   => [['label' => 'Home', 'url' => '/'], ['label' => 'Categories', 'url' => '/category'], ['label' => ucwords(str_replace('-', ' ', $slug))]],
                'currentPage'   => 1,
                'perPage'       => 24,
                'dbOffline'     => true,
            ]);
            return;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $cat = $db->fetchOne('SELECT * FROM categories WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$categoryId]);
        } else {
            $cat = $db->fetchOne('SELECT * FROM categories WHERE slug = ? AND deleted_at IS NULL LIMIT 1', [$slug]);
        }

        if (!$cat || (int)($cat['is_active'] ?? 1) !== 1) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Not Found']);
            return;
        }

        $children = CategoryService::getChildren((int)$cat['id']);
        $descIds = array_merge([(int)$cat['id']], CategoryService::getDescendantIds((int)$cat['id']));
        $descIds = array_values(array_unique(array_filter(array_map('intval', $descIds), static function (int $id): bool {
            return $id > 0;
        })));

        if (empty($descIds)) {
            $descIds = [(int)$cat['id']];
        }

        $columnRows = $db->fetchAll('SHOW COLUMNS FROM products');
        $productsColumnMap = [];
        foreach ($columnRows as $columnRow) {
            $field = strtolower((string)($columnRow['Field'] ?? ''));
            if ($field !== '') {
                $productsColumnMap[$field] = true;
            }
        }
        $hasIsVeg = isset($productsColumnMap['is_veg']);
        $hasChefSpecial = isset($productsColumnMap['is_chef_special']);
        $hasTopperEnabled = isset($productsColumnMap['topper_enabled']);
        $hasNoteEnabled = isset($productsColumnMap['note_enabled']);

        $effectivePriceSql = 'COALESCE(NULLIF(p.starting_price, 0), pv_min.min_price, p.base_price, 0)';
        $idPlaceholders = implode(',', array_fill(0, count($descIds), '?'));

        $whereSql = [
            'p.deleted_at IS NULL',
            "p.availability_status <> 'draft'",
            "(p.collection_category_id IN ($idPlaceholders) OR p.subcategory_id IN ($idPlaceholders) OR p.child_category_id IN ($idPlaceholders))",
        ];
        $params = array_merge($descIds, $descIds, $descIds);

        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $whereSql[] = '(p.name LIKE ? OR p.short_description LIKE ? OR COALESCE(p.flavour_notes, "") LIKE ? OR COALESCE(p.occasion_tag, "") LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        $dietaryRaw = $_GET['dietary'] ?? [];
        $dietaryValues = is_array($dietaryRaw) ? $dietaryRaw : explode(',', (string)$dietaryRaw);
        $allowedDietary = ['regular', 'eggless', 'vegan', 'sugar_free'];
        $dietaryValues = array_values(array_unique(array_values(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, $dietaryValues), static function (string $value) use ($allowedDietary): bool {
            return in_array($value, $allowedDietary, true);
        }))));
        if (!empty($dietaryValues)) {
            $dietaryPlaceholders = implode(',', array_fill(0, count($dietaryValues), '?'));
            $whereSql[] = "p.dietary_tag IN ($dietaryPlaceholders)";
            $params = array_merge($params, $dietaryValues);
        }

        $isVegParam = (string)($_GET['is_veg'] ?? '');
        if ($hasIsVeg && in_array($isVegParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_veg = ?';
            $params[] = (int)$isVegParam;
        }

        $isBestsellerParam = (string)($_GET['is_bestseller'] ?? '');
        if (in_array($isBestsellerParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_bestseller = ?';
            $params[] = (int)$isBestsellerParam;
        }

        $isChefSpecialParam = (string)($_GET['is_chef_special'] ?? '');
        if ($hasChefSpecial && in_array($isChefSpecialParam, ['0', '1'], true)) {
            $whereSql[] = 'p.is_chef_special = ?';
            $params[] = (int)$isChefSpecialParam;
        }

        if ((string)($_GET['customizable'] ?? '') === '1') {
            $whereSql[] = 'COALESCE(TRIM(p.customisation_note), "") <> ""';
        }

        if ($hasTopperEnabled && (string)($_GET['topper_enabled'] ?? '') === '1') {
            $whereSql[] = 'p.topper_enabled = 1';
        }

        if ($hasNoteEnabled && (string)($_GET['note_enabled'] ?? '') === '1') {
            $whereSql[] = 'p.note_enabled = 1';
        }

        if ((string)($_GET['same_day'] ?? '') === '1') {
            $whereSql[] = 'p.lead_time_hours <= 24';
        }

        if ((string)($_GET['express'] ?? '') === '1') {
            $whereSql[] = 'p.lead_time_hours <= 8';
        }

        $maxPrice = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($maxPrice !== false && $maxPrice !== null) {
            $whereSql[] = $effectivePriceSql . ' <= ?';
            $params[] = (float)$maxPrice;
        }

        $priceBucketRaw = trim((string)($_GET['price_bucket'] ?? ''));
        $priceBucketMap = [
            'under-500' => 'under_500',
            'under-1000' => '500_1000',
            '1000-2000' => '1000_2000',
            'above-2000' => 'above_2000',
            'under_500' => 'under_500',
            '500_1000' => '500_1000',
            '1000_2000' => '1000_2000',
            'above_2000' => 'above_2000',
        ];
        $priceBucket = $priceBucketMap[$priceBucketRaw] ?? '';
        if ($priceBucket === 'under_500') {
            $whereSql[] = $effectivePriceSql . ' < ?';
            $params[] = 500;
        } elseif ($priceBucket === '500_1000') {
            $whereSql[] = $effectivePriceSql . ' <= ?';
            $params[] = 1000;
        } elseif ($priceBucket === '1000_2000') {
            $whereSql[] = $effectivePriceSql . ' > ? AND ' . $effectivePriceSql . ' <= ?';
            $params[] = 1000;
            $params[] = 2000;
        } elseif ($priceBucket === 'above_2000') {
            $whereSql[] = $effectivePriceSql . ' > ?';
            $params[] = 2000;
        }

        $sortParam = trim((string)($_GET['sort'] ?? 'latest'));
        if ($sortParam === 'newest') {
            $sortParam = 'latest';
        }
        $sortMap = [
            'latest' => 'p.created_at DESC',
            'price_asc' => $effectivePriceSql . ' ASC',
            'price_desc' => $effectivePriceSql . ' DESC',
            'popular' => 'p.is_bestseller DESC, p.review_count DESC, p.created_at DESC',
        ];
        $sortSql = $sortMap[$sortParam] ?? $sortMap['latest'];

        $whereClause = implode(' AND ', $whereSql);

        $countSql = "
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            WHERE $whereClause
        ";
        $totalProducts = (int)$db->fetchScalar($countSql, $params);

        $perPage = 24;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $isVegSelectSql = $hasIsVeg ? 'p.is_veg' : '1 AS is_veg';

        $productSql = "
            SELECT
                p.id,
                p.name,
                p.slug,
                p.short_description,
                p.featured_image,
                p.dietary_tag,
                p.is_featured,
                p.is_bestseller,
                $isVegSelectSql,
                $effectivePriceSql AS min_price,
                (
                    SELECT pi.image_url
                    FROM product_images pi
                    WHERE pi.product_id = p.id
                    ORDER BY pi.sort_order ASC, pi.id ASC
                    LIMIT 1
                ) AS thumb
            FROM products p
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            WHERE $whereClause
            ORDER BY $sortSql
            LIMIT $perPage OFFSET $offset
        ";
        $products = $db->fetchAll($productSql, $params);

        $breadcrumbs = CategoryService::getBreadcrumb((string)($cat['slug'] ?? $slug));
        if (empty($breadcrumbs)) {
            $breadcrumbs = [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'Categories', 'url' => '/category'],
                ['label' => (string)($cat['name'] ?? ucwords(str_replace('-', ' ', $slug)))],
            ];
        }

        View::render('category', [
            'title' => ((string)($cat['seo_title'] ?? '') !== '' ? (string)$cat['seo_title'] : (string)$cat['name']) . ' | Cakeouflage',
            'metaDesc' => (string)($cat['seo_description'] ?? ''),
            'category' => $cat,
            'children' => $children,
            'products' => $products,
            'totalProducts' => $totalProducts,
            'breadcrumbs' => $breadcrumbs,
            'currentPage' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function product(string $slug): void
    {
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {
            View::render('product', [
                'title'       => 'Product | Cakeouflage',
                'product'     => null,
                'variants'    => [],
                'images'      => [],
                'breadcrumbs' => [['label' => 'Shop', 'href' => '/shop']],
                'related'     => [],
                'dbOffline'   => true,
            ]);
            return;
        }

        $product = $db->fetchOne("
            SELECT p.*,
                   c.name    AS category_name,
                   c.slug    AS category_slug,
                   pc.name   AS parent_category_name,
                   pc.slug   AS parent_category_slug,
                   gp.name   AS grandparent_category_name,
                   gp.slug   AS grandparent_category_slug
            FROM products p
          LEFT JOIN categories c ON c.id = p.subcategory_id
            LEFT JOIN categories pc  ON pc.id = c.parent_id
            LEFT JOIN categories gp  ON gp.id = pc.parent_id
                        WHERE p.slug = ?
                            AND p.deleted_at IS NULL
                            AND p.availability_status != 'draft'
            LIMIT 1
        ", [$slug]);

        if (!$product) {
            $fallbackSlug = $this->findNearestProductSlug($db, $slug);
            if ($fallbackSlug !== null) {
                header('Location: /product/' . rawurlencode($fallbackSlug), true, 301);
                return;
            }

            http_response_code(404);
            View::render('errors/404', ['title' => 'Not Found']);
            return;
        }

        $variants = $db->fetchAll(
            "SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 AND price > 0 ORDER BY is_default DESC, price",
            [(int)$product['id']]
        );

        $images = $db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order",
            [(int)$product['id']]
        );

        // Build breadcrumbs: Home → Grandparent → Parent → Category → Product
        $breadcrumbs = [['label' => 'Home', 'url' => '/']];
        if ($product['grandparent_category_slug']) {
            $breadcrumbs[] = ['label' => $product['grandparent_category_name'], 'url' => CategoryService::categoryUrl($product['grandparent_category_slug'])];
        }
        if ($product['parent_category_slug']) {
            $breadcrumbs[] = ['label' => $product['parent_category_name'], 'url' => CategoryService::categoryUrl($product['parent_category_slug'])];
        }
      if (!empty($product['category_slug'])) {
            $breadcrumbs[] = ['label' => $product['category_name'], 'url' => CategoryService::categoryUrl($product['category_slug'])];
        }
        $breadcrumbs[] = ['label' => $product['name']];

        // Related products (same category, excluding self)
     $related = $db->fetchAll("
    SELECT p.*,
           MIN(pv.price) AS min_price,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order LIMIT 1) AS thumb
    FROM products p
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    WHERE p.subcategory_id = ?
        AND p.id != ?
        AND p.deleted_at IS NULL
        AND p.availability_status != 'draft'
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 6
", [(int)$product['subcategory_id'], (int)$product['id']]);

        // Load business phone from settings for WhatsApp enquiry link
        $bizPhoneRow  = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'business_phone' LIMIT 1");
        $rawPhone     = $bizPhoneRow ? preg_replace('/\D/', '', (string)$bizPhoneRow['setting_value']) : '';
        if ($rawPhone === '') { $rawPhone = '919673565935'; }
        elseif (strlen($rawPhone) === 10) { $rawPhone = '91' . $rawPhone; }

        View::render('product', [
            'title'        => ($product['seo_title'] ?: $product['name']) . ' | Cakeouflage',
            'metaDesc'     => $product['seo_description'] ?? $product['short_description'] ?? '',
            'product'      => $product,
            'variants'     => $variants,
            'images'       => $images,
            'related'      => $related,
            'breadcrumbs'  => $breadcrumbs,
            'businessPhone'=> $rawPhone,
        ]);
    }

    private function findNearestProductSlug($db, string $requestedSlug): ?string
    {
        $requested = strtolower(trim($requestedSlug));
        if ($requested === '') {
            return null;
        }

        $requestedCompact = preg_replace('/[^a-z0-9]/', '', $requested) ?: '';
        $tokens = preg_split('/[-_\s]+/', $requested) ?: [];
        $tokens = array_values(array_filter(array_map(static function ($token): string {
            $clean = strtolower(trim((string)$token));
            return strlen($clean) >= 3 ? $clean : '';
        }, $tokens)));

        $rows = $db->fetchAll(
            "SELECT slug, name FROM products WHERE deleted_at IS NULL AND availability_status != 'draft' ORDER BY id DESC LIMIT 500"
        );

        $bestSlug = null;
        $bestScore = 0;

        foreach ($rows as $row) {
            $candidateSlug = strtolower((string)($row['slug'] ?? ''));
            if ($candidateSlug === '') {
                continue;
            }

            $candidateName = strtolower((string)($row['name'] ?? ''));
            $candidateCompact = preg_replace('/[^a-z0-9]/', '', $candidateSlug . ' ' . $candidateName) ?: '';

            if ($requestedCompact !== '' && $candidateCompact === $requestedCompact) {
                return (string)$row['slug'];
            }

            $score = 0;
            foreach ($tokens as $token) {
                if (strpos($candidateSlug, $token) !== false || strpos($candidateName, $token) !== false) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSlug = (string)$row['slug'];
            }
        }

        return $bestScore >= 2 ? $bestSlug : null;
    }

    public function cart(): void
    {
        View::render('cart', [
            'title' => 'Cart',
            'breadcrumbs' => [['label' => 'Cart']],
        ]);
    }

    public function checkout(): void
    {
        $currentUser = null;
        $lastAddress = null;
        if (!empty($_SESSION['user_id'])) {
            try {
                $pdo = Database::getConnection();
                if ($pdo) {
                    $uStmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
                    $uStmt->execute(['id' => (int)$_SESSION['user_id']]);
                    $currentUser = $uStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                    if ($currentUser) {
                        // Try saved addresses first
                        $addrStmt = $pdo->prepare('SELECT street, postal_code, maps_link FROM user_addresses WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
                        $addrStmt->execute(['uid' => (int)$_SESSION['user_id']]);
                        $lastAddress = $addrStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                        if (!$lastAddress) {
                            // Fallback: last delivery order
                            $aStmt = $pdo->prepare('SELECT delivery_street AS street, delivery_postal_code AS postal_code, delivery_maps_link AS maps_link FROM orders WHERE user_id = :uid AND delivery_street IS NOT NULL AND delivery_street != \'\' ORDER BY created_at DESC LIMIT 1');
                            $aStmt->execute(['uid' => (int)$_SESSION['user_id']]);
                            $lastAddress = $aStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal — proceed without pre-fill
            }
        }
        $allowPartialPayment = true;
        $screenshotRequired = true;
        try {
            $settingPdo = Database::getConnection();
            if ($settingPdo) {
                $settingStmt = $settingPdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
                $settingStmt->execute(['key' => 'allow_partial_payment']);
                $settingRow = $settingStmt->fetch(\PDO::FETCH_ASSOC);
                if ($settingRow && isset($settingRow['setting_value'])) {
                    $allowPartialPayment = (string)$settingRow['setting_value'] !== '0';
                }
                $settingStmt->execute(['key' => 'payment_screenshot_required']);
                $settingRow = $settingStmt->fetch(\PDO::FETCH_ASSOC);
                if ($settingRow && isset($settingRow['setting_value'])) {
                    $screenshotRequired = (string)$settingRow['setting_value'] !== '0';
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — default to enabled
        }
        View::render('checkout', [
            'title' => 'Checkout',
            'breadcrumbs' => [['label' => 'Checkout']],
            'currentUser' => $currentUser,
            'lastAddress' => $lastAddress,
            'allowPartialPayment' => $allowPartialPayment,
            'screenshotRequired' => $screenshotRequired,
        ]);
    }

    public function account(): void
    {
        View::render('account', [
            'title' => 'My Account',
            'breadcrumbs' => [['label' => 'My Account']],
        ]);
    }

    public function orders(): void
    {
        View::render('orders', [
            'title' => 'Order History',
            'breadcrumbs' => [['label' => 'Order History']],
        ]);
    }

    public function wishlist(): void
    {
        View::render('wishlist', [
            'title' => 'Wishlist',
            'breadcrumbs' => [['label' => 'Wishlist']],
        ]);
    }

    public function about(): void
    {
        View::render('about', [
            'title' => 'About Us',
            'breadcrumbs' => [['label' => 'About Us']],
        ]);
    }

    public function courseDetail(string $slug): void
    {
        View::render('course-detail', [
            'title' => 'Course Detail',
            'breadcrumbs' => [
                ['label' => 'Course', 'href' => '/course'],
                ['label' => 'Detail'],
            ],
            'courseSlug' => $slug,
        ]);
    }

    public function eventDetail(string $slug): void
    {
        View::render('event-detail', [
            'title' => 'Event Detail',
            'breadcrumbs' => [
                ['label' => 'Events', 'href' => '/events'],
                ['label' => 'Detail'],
            ],
            'eventSlug' => $slug,
        ]);
    }

    public function adminLogin(): void
    {
        header('Location: /admin/login.php');
        return;
    }

    public function adminDashboard(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-dashboard', [
            'title' => 'Admin Dashboard',
            'layout' => 'admin',
            'adminTitle' => 'Dashboard',
            'adminSubtitle' => 'Retail and B2B operations overview',
        ]);
    }

    public function adminProducts(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-products', [
            'title' => 'Product Management',
            'layout' => 'admin',
            'adminTitle' => 'Products',
            'adminSubtitle' => 'Create and manage catalog products and pricing variants',
        ]);
    }

    public function adminCategories(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-categories', [
            'title' => 'Category Management',
            'layout' => 'admin',
            'adminTitle' => 'Categories',
            'adminSubtitle' => 'Manage parent collections and subcategories',
        ]);
    }

    public function adminCourses(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-courses', [
            'title' => 'Course Management',
            'layout' => 'admin',
            'adminTitle' => 'Courses',
            'adminSubtitle' => 'Manage workshops, course pages, and enrollment-ready batches',
        ]);
    }

    public function adminEvents(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-events', [
            'title' => 'Event Management',
            'layout' => 'admin',
            'adminTitle' => 'Events & Webinars',
            'adminSubtitle' => 'Create and publish webinars and live events for frontend registration',
        ]);
    }

    public function adminBulkImport(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-bulk-import', [
            'title' => 'Bulk Import',
            'layout' => 'admin',
            'adminTitle' => 'Bulk Product Import',
            'adminSubtitle' => 'Upload CSV, validate rows, and inspect failed logs',
        ]);
    }

    public function adminMedia(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-media', [
            'title' => 'Media Manager',
            'layout' => 'admin',
            'adminTitle' => 'Media Manager',
            'adminSubtitle' => 'Upload, compress, and organize product images',
        ]);
    }

    public function adminOrders(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-orders', [
            'title' => 'Order Management',
            'layout' => 'admin',
            'adminTitle' => 'Orders',
            'adminSubtitle' => 'Track retail and custom fulfilment updates',
        ]);
    }

    public function adminFinanceDashboard(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-finance-dashboard', [
            'title' => 'Finance Dashboard',
            'layout' => 'admin',
            'adminTitle' => 'Finance Dashboard',
            'adminSubtitle' => 'Receivables, overdue invoices, and payment verification overview',
        ]);
    }

    public function adminInvoices(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-invoices', [
            'title' => 'Invoice Management',
            'layout' => 'admin',
            'adminTitle' => 'Invoices',
            'adminSubtitle' => 'Track invoice status, payment proofs, and manual settlements',
        ]);
    }

    public function adminComms(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-communications', [
            'title' => 'Communication Settings',
            'layout' => 'admin',
            'adminTitle' => 'Communication Hub',
            'adminSubtitle' => 'SMTP, WhatsApp, templates, logs, and resend workflow',
        ]);
    }

    public function adminWhatsAppMeta(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-whatsapp-meta', [
            'title' => 'Meta Integration',
            'layout' => 'admin',
            'adminTitle' => 'Meta WhatsApp Integration',
            'adminSubtitle' => 'WABA connection, status sync, and approval dashboard',
        ]);
    }

    public function adminWhatsAppTemplates(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-whatsapp-templates', [
            'title' => 'WhatsApp Template Management',
            'layout' => 'admin',
            'adminTitle' => 'WhatsApp Templates',
            'adminSubtitle' => 'Draft builder, preview, approval, and test send',
        ]);
    }

    public function adminWhatsAppMappings(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-whatsapp-mappings', [
            'title' => 'WhatsApp Template Mapping',
            'layout' => 'admin',
            'adminTitle' => 'Template Mapping',
            'adminSubtitle' => 'Map approved templates to live business events',
        ]);
    }

    public function adminWhatsAppLogs(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-whatsapp-logs', [
            'title' => 'WhatsApp Logs & Monitoring',
            'layout' => 'admin',
            'adminTitle' => 'WhatsApp Logs',
            'adminSubtitle' => 'Sync logs, approval logs, send logs, failed queue, and usage report',
        ]);
    }

    public function adminAutomation(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-automation', [
            'title' => 'Automation Rules',
            'layout' => 'admin',
            'adminTitle' => 'Automation & Reminders',
            'adminSubtitle' => 'Manage trigger rules, reminders, and queue monitor',
        ]);
    }

    public function adminBirthdays(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-birthdays', [
            'title' => 'Upcoming Birthdays',
            'layout' => 'admin',
            'adminTitle' => 'Birthday Pipeline',
            'adminSubtitle' => 'Upcoming DOB reminders and campaign follow-up list',
        ]);
    }

    public function adminCustomers(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-customers', [
            'title' => 'Customers',
            'layout' => 'admin',
            'adminTitle' => 'Customers & CRM',
            'adminSubtitle' => 'Profiles, tags, lifecycle markers, and birthday intelligence',
        ]);
    }

    public function adminB2bAccounts(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-b2b-accounts', [
            'title' => 'B2B Accounts',
            'layout' => 'admin',
            'adminTitle' => 'B2B Accounts',
            'adminSubtitle' => 'Approvals, pricing tiers, and account health',
        ]);
    }

    public function adminB2bQuotes(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-b2b-quotes', [
            'title' => 'B2B Quotes',
            'layout' => 'admin',
            'adminTitle' => 'B2B Quotes',
            'adminSubtitle' => 'Track quote workflows and convert approved quotes to orders',
        ]);
    }

    public function adminB2bOrders(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-b2b-orders', [
            'title' => 'B2B Orders',
            'layout' => 'admin',
            'adminTitle' => 'B2B Orders',
            'adminSubtitle' => 'Bulk fulfilment, settlement, and account-level delivery planning',
        ]);
    }

    public function adminContent(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-content', [
            'title' => 'Content Pages',
            'layout' => 'admin',
            'adminTitle' => 'Content Management',
            'adminSubtitle' => 'Edit static pages, SEO copy, and policy content',
        ]);
    }

    public function adminBanners(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-banners', [
            'title' => 'Banners',
            'layout' => 'admin',
            'adminTitle' => 'Banner Management',
            'adminSubtitle' => 'Homepage, course, and B2B banner placements',
        ]);
    }

    public function adminReports(): void
    {
        if (!$this->hasAdminSession()) {
            header('Location: /admin/login');
            return;
        }

        View::render('admin-reports', [
            'title' => 'Reports',
            'layout' => 'admin',
            'adminTitle' => 'Reports & Monitoring',
            'adminSubtitle' => 'Retail, B2B, finance, and communication performance summary',
        ]);
    }

    private function hasAdminSession(): bool
    {
        return (int)($_SESSION['admin_id'] ?? 0) > 0 || (int)($_SESSION['admin'] ?? 0) > 0;
    }

    private function hasB2bSession(): bool
    {
        return (int)($_SESSION['user_id'] ?? 0) > 0 && (string)($_SESSION['user_role'] ?? '') === 'b2b_user';
    }

    public function placeholder(): void
    {
        $title = 'Page';
        View::render('placeholder', [
            'title' => $title,
            'breadcrumbs' => [['label' => $title]],
            'pageTitle' => $title,
            'pagePath' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }

    private function placeholderPage(string $title): void
    {
        View::render('placeholder', [
            'title' => $title,
            'breadcrumbs' => [['label' => $title]],
            'pageTitle' => $title,
            'pagePath' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
    }
}
