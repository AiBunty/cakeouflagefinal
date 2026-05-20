<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\View;
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
        View::render('shop', ['title' => 'Shop Cakes', 'breadcrumbs' => [['label' => 'Shop']]]);
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

    public function category(string $slug): void
    {
        // FIX: handle slug-id format
// slug-id support
$categoryId = null;

if (preg_match('/^(.*)-(\d+)$/', $slug, $matches)) {
    $slug = $matches[1];
    $categoryId = (int)$matches[2];
}
        try {
            $db = Database::getInstance();
        } catch (\Throwable $e) {
            View::render('category', [
                'title'         => 'Category | Cakeouflage',
                'category'      => ['name' => ucwords(str_replace('-', ' ', $slug)), 'description' => '', 'banner_image' => ''],
                'children'      => [],
                'products'      => [],
                'totalProducts' => 0,
                'breadcrumbs'   => [['label' => 'Shop', 'href' => '/shop'], ['label' => ucwords(str_replace('-', ' ', $slug))]],
                'currentPage'   => 1,
                'perPage'       => 24,
                'dbOffline'     => true,
            ]);
            return;
        }
        //$cat = CategoryService::getBySlug($slug);
           // FIX: clean slug and fetch directly from DB
$slug = trim(strtolower($slug));

if ($categoryId) {

    $cat = $db->fetchOne("
        SELECT * FROM categories
        WHERE id = ?
        LIMIT 1
    ", [$categoryId]);

} else {

    $cat = $db->fetchOne("
        SELECT * FROM categories
        WHERE slug = ?
        LIMIT 1
    ", [$slug]);
}

        if (!$cat) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Not Found']);
            return;
        }

        // Subcategory cards (direct children)
        $children = CategoryService::getChildren((int)$cat['id']);

        // Collect all descendant product IDs (self + children + grandchildren)
      $descIds = [(int)$cat['id']];

// include children also
foreach ($children as $child) {
    $descIds[] = (int)$child['id'];
}
        if (empty($descIds)) {
            $products = [];
            $totalProducts = 0;
        } else {
            $filterSql  = '';
            $filterArgs = [];

         // Dietary filter
if (!empty($_GET['dietary'])) {

    $allowed = ['vegan', 'eggless', 'sugar_free', 'gluten_free'];

    $tags = is_array($_GET['dietary'])
        ? $_GET['dietary']
        : explode(',', $_GET['dietary']);

    $tags = array_map('trim', $tags);
    $tags = array_intersect($tags, $allowed);

    if ($tags) {
        $placeholders = implode(',', array_fill(0, count($tags), '?'));
        $filterSql .= " AND p.dietary_tag IN ($placeholders)";
        $filterArgs = array_merge($filterArgs, $tags);
    }
}

            // Price filter  ?max_price=1500
            if (!empty($_GET['max_price']) && is_numeric($_GET['max_price'])) {
                $filterSql  .= ' AND pv_min.price <= ?';
                $filterArgs[] = (float)$_GET['max_price'];
            }

            $idPlaceholders = implode(',', array_fill(0, count($descIds), '?'));

            // Sort
            $sortMap = [
                'newest'    => 'p.created_at DESC',
              'price_asc' => 'MIN(pv.price) ASC',
              'price_desc'=> 'MIN(pv.price) DESC',
                'popular'   => 'p.id DESC',
            ];
            $sort    = $sortMap[$_GET['sort'] ?? ''] ?? 'p.id DESC';

            // Count
            $countSql = "
                SELECT COUNT(DISTINCT p.id)
                FROM products p
                LEFT JOIN (SELECT product_id, MIN(price) AS price
                           FROM product_variants GROUP BY product_id) pv_min ON pv_min.product_id = p.id
                WHERE p.subcategory_id IN ($idPlaceholders)
                                    AND p.deleted_at IS NULL
                                    AND p.availability_status != 'draft'
                $filterSql
            ";
$args = array_merge($descIds, $filterArgs);

            $totalProducts = (int)$db->fetchScalar($countSql, $args);

            // Paginate
            $perPage = 24;
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $offset  = ($page - 1) * $perPage;

            $productSql = "
                SELECT p.*,
                       MIN(pv.price)  AS min_price,
                       MAX(pv.price)  AS max_price,
                       (SELECT 
    CASE 
        WHEN pi.image_url LIKE '/assets/%' 
        THEN REPLACE(pi.image_url, '/assets/images/products/', '/uploads/products/')
        ELSE pi.image_url
    END
 FROM product_images pi 
 WHERE pi.product_id = p.id 
 ORDER BY pi.sort_order 
 LIMIT 1
) AS thumb
                FROM products p
                LEFT JOIN product_variants pv ON pv.product_id = p.id
                LEFT JOIN (SELECT product_id, MIN(price) AS price
                           FROM product_variants GROUP BY product_id) pv_min ON pv_min.product_id = p.id
                WHERE p.subcategory_id IN ($idPlaceholders)
                                    AND p.deleted_at IS NULL
                                    AND p.availability_status != 'draft'
                $filterSql
                GROUP BY p.id
                ORDER BY $sort
                LIMIT $perPage OFFSET $offset
            ";
          $productArgs = array_merge($args);

$products = $db->fetchAll($productSql, $productArgs);

        }

        $breadcrumbs = CategoryService::getBreadcrumb($slug);

        View::render('category', [
            'title'        => ($cat['seo_title'] ?: $cat['name']) . ' | Cakeouflage',
            'metaDesc'     => $cat['seo_description'] ?? '',
            'category'     => $cat,
            'children'     => $children,
            'products'     => $products ?? [],
            'totalProducts'=> $totalProducts ?? 0,
            'breadcrumbs'  => $breadcrumbs,
            'currentPage'  => $page ?? 1,
            'perPage'      => $perPage ?? 24,
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
            http_response_code(404);
            View::render('errors/404', ['title' => 'Not Found']);
            return;
        }

        $variants = $db->fetchAll(
            "SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY is_default DESC, price",
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

        View::render('product', [
            'title'      => ($product['seo_title'] ?: $product['name']) . ' | Cakeouflage',
            'metaDesc'   => $product['seo_description'] ?? $product['short_description'] ?? '',
            'product'    => $product,
            'variants'   => $variants,
            'images'     => $images,
            'related'    => $related,
            'breadcrumbs'=> $breadcrumbs,
        ]);
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
        View::render('checkout', [
            'title' => 'Checkout',
            'breadcrumbs' => [['label' => 'Checkout']],
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
        if ($this->hasAdminSession()) {
            header('Location: /admin/dashboard');
            return;
        }

        View::render('admin-login', [
            'title' => 'Admin Login',
            'layout' => 'admin-auth',
        ]);
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
