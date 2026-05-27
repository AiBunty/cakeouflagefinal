<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Services\MailService;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthRateLimitService;
use App\Services\AuthManager;
use App\Services\ByocQuoteExpiryService;
use App\Services\OrderAutomationService;
use App\Services\PasswordResetService;
use App\Services\ProductImageService;
use App\Services\UnifiedMediaService;
use App\Services\SlotService;
use App\Services\PhoneNormalizerService;
use PDO;
use Throwable;
//session_start();
final class ApiController
{
    private const DEFAULT_PAGE_SIZE = 24;
    private static $couponSchemaEnsured = false;
    private static $topperSchemaEnsured = false;
    private static $bankAlertSchemaEnsured = false;
    private static $byocQuoteSchemaEnsured = false;
    private static $orderLifecycleSchemaEnsured = false;
    private static $productsColumnMap = null;

    private static function currentSchema(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query('SELECT DATABASE()');
            return (string)($stmt ? $stmt->fetchColumn() : '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $schema = self::currentSchema($pdo);
        if ($schema === '') {
            return false;
        }

        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table_name AND column_name = :column_name');
        $check->bindValue(':schema', $schema);
        $check->bindValue(':table_name', $tableName);
        $check->bindValue(':column_name', $columnName);
        $check->execute();

        return (int)($check->fetchColumn() ?: 0) > 0;
    }
    /** @return array<string, bool> */
    private static function productsColumnMap(PDO $pdo): array
    {
        if (is_array(self::$productsColumnMap)) {
            return self::$productsColumnMap;
        }

        try {
            $stmt = $pdo->query('SELECT DATABASE()');
            $schema = (string)($stmt ? $stmt->fetchColumn() : '');
            if ($schema === '') {
                self::$productsColumnMap = [];
                return self::$productsColumnMap;
            }

            $check = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND table_name = "products"');
            $check->bindValue(':schema', $schema);
            $check->execute();
            $map = [];
            foreach ($check->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
                $map[(string)$columnName] = true;
            }
            self::$productsColumnMap = $map;
        } catch (Throwable $e) {
            self::$productsColumnMap = [];
        }

        return self::$productsColumnMap;
    }

    /** Get PDO or send 503 JSON and return null. */
    private static function db(): ?\PDO
    {
        try {
            return Database::getConnection();
        } catch (\Throwable $e) {
            if (!self::isDevelopmentMode()) {
                Response::json(['success' => false, 'message' => 'Database unavailable. Deploy to StackCP to connect.'], 503);
            }
            return null;
        }
    }

    private static function isDevelopmentMode(): bool
    {
        return Env::get('APP_ENV', 'production') === 'development';
    }

    /** @return array<int, array<string, mixed>> */
    private static function developmentCategories(): array
    {
        return [
            ['id' => 1, 'name' => 'Cakes', 'slug' => 'cakes', 'parent_id' => null, 'category_type' => null, 'product_count' => 16],
            ['id' => 2, 'name' => 'Classic Cakes', 'slug' => 'classic-cakes', 'parent_id' => 1, 'category_type' => null, 'product_count' => 8],
            ['id' => 3, 'name' => 'Cheesecakes', 'slug' => 'cheesecakes', 'parent_id' => 1, 'category_type' => null, 'product_count' => 5],
            ['id' => 4, 'name' => 'Gifting', 'slug' => 'gifting', 'parent_id' => null, 'category_type' => null, 'product_count' => 10],
            ['id' => 5, 'name' => 'Hampers', 'slug' => 'hampers', 'parent_id' => 4, 'category_type' => null, 'product_count' => 6],
            ['id' => 6, 'name' => 'Corporate Gifting', 'slug' => 'corporate-gifting', 'parent_id' => 4, 'category_type' => null, 'product_count' => 4],
            ['id' => 7, 'name' => 'Desserts', 'slug' => 'desserts', 'parent_id' => null, 'category_type' => null, 'product_count' => 9],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function developmentProducts(): array
    {
        return [
            ['id' => 101, 'name' => 'Buttercrisp Cookie Jar 01', 'slug' => 'buttercrisp-cookie-jar-01', 'short_description' => 'Crunchy artisan cookies in a gift-ready jar.', 'starting_price' => 499, 'dietary_tag' => 'regular', 'category_name' => 'Gifting', 'category_slug' => 'gifting', 'default_variant_id' => 1001, 'default_variant_label' => 'Jar Pack', 'image' => ProductImageService::placeholderForCategory('gifting')],
            ['id' => 102, 'name' => 'Classic Truffle Cake', 'slug' => 'classic-truffle-cake', 'short_description' => 'Rich chocolate truffle cake for all occasions.', 'starting_price' => 899, 'dietary_tag' => 'eggless', 'category_name' => 'Cakes', 'category_slug' => 'cakes', 'default_variant_id' => 1002, 'default_variant_label' => '1 Kg', 'image' => ProductImageService::placeholderForCategory('classic-cakes')],
            ['id' => 103, 'name' => 'Blueberry Cheesecake', 'slug' => 'blueberry-cheesecake', 'short_description' => 'Creamy cheesecake topped with blueberry compote.', 'starting_price' => 1099, 'dietary_tag' => 'regular', 'category_name' => 'Cakes', 'category_slug' => 'cheesecakes', 'default_variant_id' => 1003, 'default_variant_label' => '1 Kg', 'image' => ProductImageService::placeholderForCategory('cheesecakes')],
            ['id' => 104, 'name' => 'Mini Dessert Box', 'slug' => 'mini-dessert-box', 'short_description' => 'Assorted mini desserts for parties and gifting.', 'starting_price' => 699, 'dietary_tag' => 'eggless', 'category_name' => 'Desserts', 'category_slug' => 'desserts', 'default_variant_id' => 1004, 'default_variant_label' => 'Box of 12', 'image' => ProductImageService::placeholderForCategory('desserts')],
        ];
    }

    private function otpRecentlyRequested(PDO $pdo, string $email): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM otp_verifications WHERE email = :email AND created_at > NOW() - INTERVAL 60 SECOND');
        $stmt->execute(['email' => $email]);
        return (int)($stmt->fetchColumn() ?: 0) > 0;
    }

    public function health(): void
    {
        Response::json([
            'success' => true,
            'status' => 'ok',
            'service' => 'api',
            'timestamp' => gmdate('c'),
        ]);
    }

    public function healthDb(): void
    {
        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        try {
            $dbNameStmt = $pdo->query('SELECT DATABASE()');
            $dbName = (string)($dbNameStmt ? $dbNameStmt->fetchColumn() : '');

            $pingStmt = $pdo->query('SELECT 1');
            $pingOk = (int)($pingStmt ? $pingStmt->fetchColumn() : 0) === 1;

            Response::json([
                'success' => true,
                'status' => $pingOk ? 'ok' : 'degraded',
                'service' => 'db',
                'database' => $dbName,
                'timestamp' => gmdate('c'),
            ], $pingOk ? 200 : 503);
        } catch (Throwable $e) {
            Response::json([
                'success' => false,
                'status' => 'down',
                'service' => 'db',
                'message' => 'Database health check failed',
            ], 503);
        }
    }

    public function banners(): void
    {
        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $placement = trim((string)($_GET['placement'] ?? ''));
        if ($placement === '') {
            Response::json([
                'success' => false,
                'message' => 'Placement required',
            ], 422);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM banners WHERE placement = :placement AND is_active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute(['placement' => $placement]);

        Response::json([
            'success' => true,
            'data' => $stmt->fetchAll(),
        ]);
    }

    public function siteTopOffer(): void
    {
        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        try {
            ob_start();
            include __DIR__ . '/../Views/partials/top-offer-banner.php';
            $html = (string) ob_get_clean();

            Response::json([
                'success' => true,
                'data' => [
                    'html' => $html,
                ],
            ]);
        } catch (Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'Unable to load site offer banner',
            ], 500);
        }
    }

    public function products(): void
    {
        $pdo = self::db();
        if (!$pdo) {
            if (self::isDevelopmentMode()) {
                $items = self::developmentProducts();
                Response::json([
                    'success' => true,
                    'message' => 'ok (development fallback)',
                    'data' => [
                        'items' => $items,
                        'count' => count($items),
                        'total' => count($items),
                        'page' => 1,
                        'limit' => self::DEFAULT_PAGE_SIZE,
                    ],
                ]);
            }
            return;
        }
        $params = [];
        $productsColumns = self::productsColumnMap($pdo);
        $hasIsVegColumn = isset($productsColumns['is_veg']);
        $hasIsBestsellerColumn = isset($productsColumns['is_bestseller']);
        $hasIsChefSpecialColumn = isset($productsColumns['is_chef_special']);
        $hasReviewCountColumn = isset($productsColumns['review_count']);
        $effectivePriceSql = 'COALESCE(NULLIF(p.starting_price, 0), pv_min.min_price, p.base_price, 0)';
        $where = [
            'p.deleted_at IS NULL',
            "p.availability_status <> 'draft'",
        ];

        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(
                p.name LIKE :search_name
                OR p.short_description LIKE :search_short_description
                OR c.name LIKE :search_category_name
                OR COALESCE(p.flavour_notes, "") LIKE :search_flavour_notes
                OR COALESCE(p.occasion_tag, "") LIKE :search_occasion_tag
            )';
            $searchLike = '%' . $search . '%';
            $params['search_name'] = $searchLike;
            $params['search_short_description'] = $searchLike;
            $params['search_category_name'] = $searchLike;
            $params['search_flavour_notes'] = $searchLike;
            $params['search_occasion_tag'] = $searchLike;
        }

        $category = trim((string)($_GET['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'c.slug = :category';
            $params['category'] = $category;
        }

        $foodMode = getDietaryMode($pdo);

        $dietary = trim((string)($_GET['dietary'] ?? ''));
        if ($dietary !== '') {
            $allowedDietary = ['regular', 'eggless', 'vegan', 'sugar_free'];
            $dietaryValues = array_values(array_filter(array_map(static function (string $value): string {
                return trim($value);
            }, explode(',', $dietary)), static function (string $value): bool {
                return $value !== '';
            }));

            $dietaryValues = array_values(array_unique(array_values(array_filter($dietaryValues, static function (string $value) use ($allowedDietary): bool {
                return in_array($value, $allowedDietary, true);
            }))));

            if (count($dietaryValues) === 1) {
                $where[] = 'p.dietary_tag = :dietary';
                $params['dietary'] = $dietaryValues[0];
            } elseif (count($dietaryValues) > 1) {
                $dietaryPlaceholders = [];
                foreach ($dietaryValues as $index => $value) {
                    $key = 'dietary_' . $index;
                    $dietaryPlaceholders[] = ':' . $key;
                    $params[$key] = $value;
                }
                $where[] = 'p.dietary_tag IN (' . implode(', ', $dietaryPlaceholders) . ')';
            }
        }

        $isVegParam = $_GET['is_veg'] ?? '';
        if ($hasIsVegColumn && $foodMode === 'veg_only') {
            $where[] = 'p.is_veg = :is_veg_forced';
            $params['is_veg_forced'] = 1;
        } elseif ($hasIsVegColumn && $isVegParam !== '' && in_array((string)$isVegParam, ['0', '1'], true)) {
            $where[] = 'p.is_veg = :is_veg';
            $params['is_veg'] = (int)$isVegParam;
        }

        $availability = trim((string)($_GET['availability'] ?? ''));
        if ($availability !== '') {
            $where[] = 'p.availability_status = :availability';
            $params['availability'] = $availability;
        }

        $minPrice = filter_var($_GET['min_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($minPrice !== false && $minPrice !== null) {
            $where[] = $effectivePriceSql . ' >= :min_price';
            $params['min_price'] = $minPrice;
        }

        $maxPrice = filter_var($_GET['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($maxPrice !== false && $maxPrice !== null) {
            $where[] = $effectivePriceSql . ' <= :max_price';
            $params['max_price'] = $maxPrice;
        }

        $priceBucket = trim((string)($_GET['price_bucket'] ?? ''));
        if ($priceBucket !== '') {
            if ($priceBucket === 'under_500') {
                $where[] = $effectivePriceSql . ' < :bucket_max_500';
                $params['bucket_max_500'] = 500;
            } elseif ($priceBucket === '500_1000') {
                $where[] = $effectivePriceSql . ' >= :bucket_min_500 AND ' . $effectivePriceSql . ' <= :bucket_max_1000';
                $params['bucket_min_500'] = 500;
                $params['bucket_max_1000'] = 1000;
            } elseif ($priceBucket === '1000_2000') {
                $where[] = $effectivePriceSql . ' > :bucket_min_1000 AND ' . $effectivePriceSql . ' <= :bucket_max_2000';
                $params['bucket_min_1000'] = 1000;
                $params['bucket_max_2000'] = 2000;
            } elseif ($priceBucket === 'above_2000') {
                $where[] = $effectivePriceSql . ' > :bucket_min_2000';
                $params['bucket_min_2000'] = 2000;
            }
        }

        $sort = (string)($_GET['sort'] ?? 'latest');
        switch ($sort) {
            case 'price_asc':
                $sortSql = $effectivePriceSql . ' ASC';
                break;
            case 'price_desc':
                $sortSql = $effectivePriceSql . ' DESC';
                break;
            case 'popular':
                $popularSortParts = [];
                if ($hasIsBestsellerColumn) {
                    $popularSortParts[] = 'p.is_bestseller DESC';
                }
                if ($hasReviewCountColumn) {
                    $popularSortParts[] = 'p.review_count DESC';
                }
                $sortSql = !empty($popularSortParts) ? implode(', ', $popularSortParts) : 'p.created_at DESC';
                break;
            default:
                $sortSql = 'p.created_at DESC';
                break;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? self::DEFAULT_PAGE_SIZE);
        $limit = min(max($limit, 1), 60);
        $offset = ($page - 1) * $limit;

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM products p
            JOIN categories c ON c.id = p.collection_category_id
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            WHERE {$whereSql}
        ";
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $isVegSelectSql = $hasIsVegColumn ? 'p.is_veg' : 'NULL AS is_veg';
        $isBestsellerSelectSql = $hasIsBestsellerColumn ? 'p.is_bestseller' : '0 AS is_bestseller';
        $isChefSpecialSelectSql = $hasIsChefSpecialColumn ? 'p.is_chef_special' : '0 AS is_chef_special';

        $sql = "
            SELECT
                p.id,
                p.name,
                p.slug,
                p.short_description,
                {$effectivePriceSql} AS starting_price,
                p.featured_image,
                pi_hover.image_url AS hover_image_raw,
                p.availability_status,
                p.dietary_tag,
            {$isVegSelectSql},
                {$isBestsellerSelectSql},
                {$isChefSpecialSelectSql},
                c.name AS category_name,
                c.slug AS category_slug,
                v.id AS default_variant_id,
                v.variant_label AS default_variant_label
            FROM products p
            JOIN categories c ON c.id = p.collection_category_id
            LEFT JOIN (
                SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                FROM product_variants
                WHERE is_active = 1 AND price > 0
                GROUP BY product_id
            ) pv_min ON pv_min.product_id = p.id
            LEFT JOIN product_variants v ON v.product_id = p.id AND v.is_default = 1
            LEFT JOIN product_images pi_hover
              ON pi_hover.product_id = p.id
              AND pi_hover.id = (
                SELECT id FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC LIMIT 1
              )
            WHERE {$whereSql}
            ORDER BY {$sortSql}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['image'] = ProductImageService::resolve((string)($row['featured_image'] ?? ''), (string)($row['category_slug'] ?? ''));
            $row['hover_image'] = $row['hover_image_raw'] !== null
                ? ProductImageService::resolve((string)$row['hover_image_raw'], (string)($row['category_slug'] ?? ''))
                : null;
            unset($row['hover_image_raw']);
        }
        unset($row);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'items' => $rows,
                'count' => count($rows),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    public function search(): void
    {
        $query = trim((string)($_GET['q'] ?? ''));
        $limit = (int)($_GET['limit'] ?? 8);
        $limit = min(max($limit, 1), 12);

        try {
            try {
                $pdo = Database::getConnection();
            } catch (Throwable $dbError) {
                error_log('[ApiController::search][db] ' . $dbError->getMessage());
                Response::json([
                    'success' => false,
                    'products' => [],
                    'categories' => [],
                    'message' => 'Search temporarily unavailable',
                    'data' => [
                        'query' => $query,
                        'products' => [],
                        'categories' => [],
                    ],
                ], 200);
                exit;
            }

            if (strlen($query) < 2) {
                Response::json([
                    'success' => true,
                    'message' => 'ok',
                    'data' => [
                        'query' => $query,
                        'products' => [],
                        'categories' => [],
                    ],
                ]);
                return;
            }

            $productsColumns = self::productsColumnMap($pdo);
            $searchLike = '%' . $query . '%';
            $searchConditions = [
                'p.name LIKE :search_name',
                "COALESCE(p.short_description, '') LIKE :search_short_description",
                "COALESCE(p.flavour_notes, '') LIKE :search_flavour_notes",
                "COALESCE(p.occasion_tag, '') LIKE :search_occasion_tag",
                "COALESCE(c.name, '') LIKE :search_category_name",
            ];
            $searchParams = [
                'search_name' => $searchLike,
                'search_short_description' => $searchLike,
                'search_flavour_notes' => $searchLike,
                'search_occasion_tag' => $searchLike,
                'search_category_name' => $searchLike,
            ];

            foreach (['sku', 'tags', 'keywords', 'customisation_note'] as $columnName) {
                if (isset($productsColumns[$columnName])) {
                    $paramKey = 'search_' . $columnName;
                    $searchConditions[] = "COALESCE(p." . $columnName . ", '') LIKE :" . $paramKey;
                    $searchParams[$paramKey] = $searchLike;
                }
            }

            $effectivePriceSql = 'COALESCE(NULLIF(p.starting_price, 0), pv_min.min_price, p.base_price, 0)';
            $productSql = '
                SELECT
                    p.id,
                    p.name,
                    p.slug,
                    p.featured_image,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    ' . $effectivePriceSql . ' AS starting_price
                FROM products p
                JOIN categories c ON c.id = p.collection_category_id
                LEFT JOIN (
                    SELECT product_id, MIN(COALESCE(discount_price, price)) AS min_price
                    FROM product_variants
                    WHERE is_active = 1 AND price > 0
                    GROUP BY product_id
                ) pv_min ON pv_min.product_id = p.id
                WHERE p.deleted_at IS NULL
                  AND p.availability_status <> \'draft\'
                  AND (' . implode(' OR ', $searchConditions) . ')
                ORDER BY
                  CASE WHEN p.name LIKE :prefix_search THEN 0 ELSE 1 END,
                  p.created_at DESC
                LIMIT :limit
            ';

            $productStmt = $pdo->prepare($productSql);
            foreach ($searchParams as $paramKey => $paramValue) {
                $productStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
            }
            $productStmt->bindValue(':prefix_search', $query . '%', PDO::PARAM_STR);
            $productStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $productStmt->execute();
            $productRows = $productStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($productRows as &$row) {
                $row['image'] = ProductImageService::resolve((string)($row['featured_image'] ?? ''), (string)($row['category_slug'] ?? ''));
                $row['price'] = (float)($row['starting_price'] ?? 0);
                $row['url'] = '/product/' . rawurlencode((string)($row['slug'] ?? ''));
                unset($row['featured_image'], $row['starting_price']);
            }
            unset($row);

            $categoryStmt = $pdo->prepare(
                'SELECT id, name, slug
                 FROM categories
                 WHERE is_active = 1
                   AND deleted_at IS NULL
                                     AND (name LIKE :search_name OR slug LIKE :search_slug)
                 ORDER BY CASE WHEN name LIKE :prefix_search THEN 0 ELSE 1 END, name ASC
                 LIMIT 4'
            );
                        $categoryStmt->bindValue(':search_name', $searchLike, PDO::PARAM_STR);
                        $categoryStmt->bindValue(':search_slug', $searchLike, PDO::PARAM_STR);
            $categoryStmt->bindValue(':prefix_search', $query . '%', PDO::PARAM_STR);
            $categoryStmt->execute();
            $categoryRows = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($categoryRows as &$categoryRow) {
                $categoryRow['url'] = '/category/' . rawurlencode((string)($categoryRow['slug'] ?? ''));
            }
            unset($categoryRow);

            Response::json([
                'success' => true,
                'message' => 'ok',
                'data' => [
                    'query' => $query,
                    'products' => $productRows,
                    'categories' => $categoryRows,
                ],
            ]);
        } catch (Throwable $error) {
            error_log('[ApiController::search] ' . $error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
            Response::json([
                'success' => false,
                'products' => [],
                'categories' => [],
                'message' => 'Search temporarily unavailable',
                'data' => [
                    'query' => $query,
                    'products' => [],
                    'categories' => [],
                ],
            ], 200);
            exit;
        }
    }

    public function product(string $slug): void
    {
        $pdo = self::db(); if (!$pdo) return;

        $stmt = $pdo->prepare(
            "
            SELECT
                p.*,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            JOIN categories c ON c.id = p.collection_category_id
            WHERE p.slug = :slug
              AND p.deleted_at IS NULL
              AND p.availability_status <> 'draft'
            LIMIT 1
            "
        );
        $stmt->execute(['slug' => $slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            Response::json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
            return;
        }

        $variantStmt = $pdo->prepare(
            'SELECT id, variant_label, variant_name, weight_or_size, unit_type, sku, flavor, price, discount_price, stock_quantity, is_default
             FROM product_variants
             WHERE product_id = :product_id AND is_active = 1 AND price > 0
             ORDER BY price ASC'
        );
        $variantStmt->execute(['product_id' => $product['id']]);
        $variants = $variantStmt->fetchAll(PDO::FETCH_ASSOC);

        $imageStmt = $pdo->prepare(
            'SELECT id, image_url, alt_text, sort_order
             FROM product_images
             WHERE product_id = :product_id
             ORDER BY sort_order ASC'
        );
        $imageStmt->execute(['product_id' => $product['id']]);
        $imageRows = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
        $images = ProductImageService::getProductGalleryImages(
            $product,
            $imageRows,
            (string)($product['category_slug'] ?? ''),
            2
        );

        $product['featured_image'] = (string)($images[0]['image_url'] ?? ProductImageService::resolve((string)($product['featured_image'] ?? ''), (string)($product['category_slug'] ?? '')));

        $relatedStmt = $pdo->prepare(
            'SELECT id, name, slug, starting_price, featured_image
             FROM products
             WHERE collection_category_id = :category_id
               AND id <> :product_id
               AND deleted_at IS NULL
               AND availability_status <> "draft"
             ORDER BY is_bestseller DESC, created_at DESC
             LIMIT 4'
        );
        $relatedStmt->execute([
            'category_id' => $product['collection_category_id'],
            'product_id' => $product['id'],
        ]);

        $relatedRows = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($relatedRows as &$related) {
            $related['featured_image'] = ProductImageService::resolve((string)($related['featured_image'] ?? ''), (string)($product['category_slug'] ?? ''));
        }
        unset($related);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'product' => $product,
                'variants' => $variants,
                'images' => $images,
                'related_products' => $relatedRows,
            ],
        ]);
    }

    public function categories(): void
    {
        $pdo = self::db();
        if (!$pdo) {
            if (self::isDevelopmentMode()) {
                Response::json([
                    'success' => true,
                    'message' => 'ok (development fallback)',
                    'data' => [
                        'items' => self::developmentCategories(),
                    ],
                ]);
            }
            return;
        }
                $stmt = $pdo->query(
                        "SELECT
                                c.id,
                                c.name,
                                c.slug,
                                c.parent_id,
                                NULL AS category_type,
                                (
                                        SELECT COUNT(DISTINCT p.id)
                                        FROM products p
                                        WHERE p.deleted_at IS NULL
                                            AND p.availability_status <> 'draft'
                                            AND (
                                                p.collection_category_id = c.id
                                                OR p.subcategory_id = c.id
                                                OR p.child_category_id = c.id
                                            )
                                ) AS product_count
                         FROM categories c
                         WHERE c.is_active = 1 AND c.deleted_at IS NULL
                         ORDER BY
                                CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END,
                                COALESCE(c.parent_id, c.id),
                                c.sort_order ASC,
                                c.name ASC"
                );

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [],
            ],
        ]);
    }

    public function courses(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT id, title, slug, short_description, mode, fee_amount, image_url
             FROM courses
             WHERE is_active = 1
             ORDER BY created_at DESC'
        );

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [],
            ],
        ]);
    }

    public function courseDetail(string $slug): void
    {
        $pdo = self::db(); if (!$pdo) return;

        $stmt = $pdo->prepare(
            'SELECT id, title, slug, short_description, description, modules, duration_text, mode, fee_amount, image_url, cta_label, cta_url
             FROM courses
             WHERE slug = :slug AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            Response::json(['success' => false, 'message' => 'Course not found'], 404);
            return;
        }

        $batchStmt = $pdo->prepare(
            'SELECT id, batch_name, starts_on, ends_on, seats_total, seats_available, fee_amount
             FROM course_batches
             WHERE course_id = :course_id AND is_active = 1
             ORDER BY starts_on ASC
             LIMIT 20'
        );
        $batchStmt->execute(['course_id' => (int)$course['id']]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'course' => $course,
                'batches' => $batchStmt->fetchAll(PDO::FETCH_ASSOC),
            ],
        ]);
    }

    public function courseBatches(string $slug): void
    {
        $pdo = self::db(); if (!$pdo) return;

        $courseStmt = $pdo->prepare('SELECT id FROM courses WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $courseStmt->execute(['slug' => $slug]);
        $courseId = (int)($courseStmt->fetchColumn() ?: 0);

        if ($courseId <= 0) {
            Response::json(['success' => false, 'message' => 'Course not found'], 404);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, batch_name, starts_on, ends_on, seats_total, seats_available, fee_amount
             FROM course_batches
             WHERE course_id = :course_id AND is_active = 1
             ORDER BY starts_on ASC'
        );
        $stmt->execute(['course_id' => $courseId]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function events(): void
    {
        $pdo = self::db(); if (!$pdo) return;

        $type = trim((string)($_GET['type'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $sql = 'SELECT
                    id, title, slug, short_description, banner_image, instructor_name,
                    starts_at, ends_at, event_type, event_category, event_status,
                    location_text, online_link, capacity, seats_available,
                    registration_cta_label
                FROM events
                WHERE is_published = 1';
        $params = [];

        if ($type !== '' && in_array($type, ['webinar', 'event'], true)) {
            $sql .= ' AND event_type = :event_type';
            $params['event_type'] = $type;
        }
        if ($status !== '') {
            $sql .= ' AND event_status = :event_status';
            $params['event_status'] = $status;
        }

        $sql .= ' ORDER BY starts_at ASC LIMIT 80';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function eventDetail(string $slug): void
    {
        $pdo = self::db(); if (!$pdo) return;

        $stmt = $pdo->prepare(
            'SELECT
                id, title, slug, short_description, full_description, banner_image,
                instructor_name, starts_at, ends_at, event_type, event_category,
                event_status, location_text, online_link, capacity, seats_available,
                registration_cta_label, is_published
             FROM events
             WHERE slug = :slug AND is_published = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            Response::json(['success' => false, 'message' => 'Event not found'], 404);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['event' => $event],
        ]);
    }

    public function authRegister(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Password-based registration is disabled. Use OTP login only.',
        ], 410);
    }

    public function authLogin(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Password-based login is disabled. Use OTP login only.',
        ], 410);
    }

    public function authForgotPassword(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Password reset is disabled. Use OTP login only.',
        ], 410);
    }

    public function authResetPassword(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Password reset is disabled. Use OTP login only.',
        ], 410);
    }

    public function authMe(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if (!AuthManager::isCustomerAuthenticated() || $userId <= 0) {
            $this->clearCustomerSessionState();
            Response::json([
                'success' => true,
                'message' => 'Guest session',
                'data' => [
                    'is_authenticated' => false,
                    'user' => null,
                ],
            ]);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->clearCustomerSessionState();
            Response::json([
                'success' => true,
                'message' => 'Guest session',
                'data' => [
                    'is_authenticated' => false,
                    'user' => null,
                ],
            ]);
            return;
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        if ($role !== 'customer') {
            $this->clearCustomerSessionState();
            Response::json([
                'success' => true,
                'message' => 'Guest session',
                'data' => [
                    'is_authenticated' => false,
                    'user' => null,
                ],
            ]);
            return;
        }

        // Keep customer session keys canonical across legacy and OTP login flows.
        $_SESSION['user_role'] = 'customer';
        $_SESSION['otp_verified'] = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = (string)($user['email'] ?? '');

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'is_authenticated' => true,
                'user' => $user,
            ],
        ]);
    }

    public function authLogout(): void
    {
        AuthManager::logoutCustomer();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        Response::json(['success' => true, 'message' => 'Logged out']);
    }

  public function cartGet(): void
{
    try {
        $pdo = self::db(); 
        if (!$pdo) return;

        $cartId = $this->getOrCreateCartId($pdo);

        $autoPublic = (string)($_GET['auto_public'] ?? '0') === '1';
        if ($autoPublic) {
            $this->maybeAutoApplyBestPublicCoupon($pdo, $cartId);
        }

        $data = $this->buildCartResponse($pdo, $cartId);

        Response::json([
            'success' => true,
            'data' => $data
        ]);

    } catch (\Throwable $e) {
        Response::json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}

    public function toppers(): void
    {
        try {
            $pdo = self::db();
            if (!$pdo) { Response::json(['success' => false, 'error' => 'DB unavailable'], 503); return; }
            $this->ensureTopperSchema($pdo);
            $stmt = $pdo->query('SELECT id, name, price, description FROM cake_toppers WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
            Response::json(['success' => true, 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }



    public function cartAddItem(): void
{
    try {

        $pdo = self::db(); 
        if (!$pdo) return;

        $cartId = $this->getOrCreateCartId($pdo);

        $input = $this->readJsonInput();

        // 🔥 fallback
        if (empty($input)) {
            $input = $_POST;
        }

        // 🔥 DEBUG (file madhe baghnyasathi)
        file_put_contents('debug.txt', print_r($input, true));

        $productId = (int)($input['product_id'] ?? 0);
        $variantId = isset($input['variant_id']) ? (int)$input['variant_id'] : 0;
        $quantity = max(1, (int)($input['quantity'] ?? 1));
        $cakeMessage = substr(trim((string)($input['cake_message'] ?? '')), 0, 200);
        $topperId    = isset($input['topper_id']) && (int)$input['topper_id'] > 0 ? (int)$input['topper_id'] : null;

        if ($productId <= 0) {
            throw new \Exception("product_id missing");
        }

        $productStmt = $pdo->prepare('SELECT id, availability_status FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $productStmt->execute(['id' => $productId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new \Exception("Product not found");
        }

        if ((string)$product['availability_status'] === 'out_of_stock') {
            throw new \Exception("Product out of stock");
        }

        // 🔥 variant fallback
        if ($variantId <= 0) {
            $defaultVariantStmt = $pdo->prepare("
                SELECT id FROM product_variants 
                WHERE product_id = :product_id 
                AND is_default = 1 
                LIMIT 1
            ");
            $defaultVariantStmt->execute(['product_id' => $productId]);
            $variantId = (int)($defaultVariantStmt->fetchColumn() ?: 0);
        }

        if ($variantId <= 0) {
            throw new \Exception("Variant not found");
        }

        $variantStmt = $pdo->prepare('SELECT id, product_id, price, discount_price FROM product_variants WHERE id = :id LIMIT 1');
        $variantStmt->execute(['id' => $variantId]);
        $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);

        if (!$variant) {
            throw new \Exception("Invalid variant");
        }

        $unitPrice = (float)($variant['discount_price'] ?? 0) > 0
            ? (float)$variant['discount_price']
            : (float)$variant['price'];

        // Validate topper and resolve price
        $topperPrice = 0.00;
        $topperNameSnapshot = null;
        if ($topperId !== null) {
            $tpStmt = $pdo->prepare('SELECT id, name, price FROM cake_toppers WHERE id = :id AND is_active = 1 LIMIT 1');
            $tpStmt->execute(['id' => $topperId]);
            $topperRow = $tpStmt->fetch(PDO::FETCH_ASSOC);
            if ($topperRow) {
                $topperPrice = (float)$topperRow['price'];
                $topperNameSnapshot = $topperRow['name'];
            } else {
                $topperId = null; // invalid/inactive topper — silently ignore
            }
        }

        // 🔥 CHECK existing
        $existingStmt = $pdo->prepare('SELECT id, quantity, topper_price FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND variant_id = :variant_id LIMIT 1');
        $existingStmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = (int)$existing['quantity'] + $quantity;
            $existingTopperPrice = (float)($existing['topper_price'] ?? 0);

            $updateStmt = $pdo->prepare('
                UPDATE cart_items 
                SET quantity = :quantity, unit_price = :unit_price, line_total = :line_total,
                    cake_message = :cake_message, topper_id = :topper_id,
                    topper_name_snapshot = :topper_name_snapshot, topper_price = :topper_price
                WHERE id = :id
            ');

            $updateStmt->execute([
                'quantity'             => $newQty,
                'unit_price'          => $unitPrice,
                'line_total'          => ($unitPrice + $topperPrice) * $newQty,
                'cake_message'        => $cakeMessage !== '' ? $cakeMessage : null,
                'topper_id'           => $topperId,
                'topper_name_snapshot'=> $topperNameSnapshot,
                'topper_price'        => $topperPrice,
                'id'                  => $existing['id'],
            ]);

        } else {

            $insertStmt = $pdo->prepare('
                INSERT INTO cart_items 
                (cart_id, product_id, variant_id, quantity, unit_price, line_total,
                 cake_message, topper_id, topper_name_snapshot, topper_price) 
                VALUES 
                (:cart_id, :product_id, :variant_id, :quantity, :unit_price, :line_total,
                 :cake_message, :topper_id, :topper_name_snapshot, :topper_price)
            ');

            $insertStmt->execute([
                'cart_id'             => $cartId,
                'product_id'          => $productId,
                'variant_id'          => $variantId,
                'quantity'            => $quantity,
                'unit_price'          => $unitPrice,
                'line_total'          => ($unitPrice + $topperPrice) * $quantity,
                'cake_message'        => $cakeMessage !== '' ? $cakeMessage : null,
                'topper_id'           => $topperId,
                'topper_name_snapshot'=> $topperNameSnapshot,
                'topper_price'        => $topperPrice,
            ]);
        }

        Response::json([
            'success' => true,
            'message' => 'Item added to cart'
        ]);

    } catch (\Throwable $e) {

        Response::json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
}
    public function cartUpdateItem(string $itemId): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $cartId = $this->getOrCreateCartId($pdo);
        $input = $this->readJsonInput();
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        $stmt = $pdo->prepare('SELECT id, unit_price, topper_price FROM cart_items WHERE id = :id AND cart_id = :cart_id LIMIT 1');
        $stmt->execute(['id' => (int)$itemId, 'cart_id' => $cartId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            Response::json(['success' => false, 'message' => 'Cart item not found'], 404);
            return;
        }

        $effectivePrice = (float)$item['unit_price'] + (float)($item['topper_price'] ?? 0);
        $updateStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity, line_total = :line_total, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([
            'quantity' => $quantity,
            'line_total' => round($effectivePrice * $quantity, 2),
            'id' => (int)$itemId,
        ]);

        Response::json(['success' => true, 'message' => 'Cart item updated', 'data' => $this->buildCartResponse($pdo, $cartId)]);
    }

    public function cartDeleteItem(string $itemId): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $cartId = $this->getOrCreateCartId($pdo);
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id');
        $stmt->execute(['id' => (int)$itemId, 'cart_id' => $cartId]);

        Response::json(['success' => true, 'message' => 'Item removed', 'data' => $this->buildCartResponse($pdo, $cartId)]);
    }

    public function cartApplyCoupon(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $this->ensureCouponSchema($pdo);
        $cartId = $this->getOrCreateCartId($pdo);
        $input = $this->readJsonInput();
        $code = strtoupper(trim((string)($input['code'] ?? '')));
        $userId = (int)($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            Response::json(['success' => false, 'message' => 'Please login before applying a coupon'], 401);
            return;
        }

        if ($code === '') {
            unset($_SESSION['applied_coupon']);
            $_SESSION['coupon_auto_opt_out'] = 1;
            Response::json(['success' => true, 'message' => 'Coupon cleared', 'data' => $this->buildCartResponse($pdo, $cartId)]);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT * FROM coupons
             WHERE UPPER(code) = :code
               AND is_active = 1
               AND is_deleted = 0
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND ends_at >= NOW()
             LIMIT 1"
        );
        $stmt->execute(['code' => $code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            Response::json(['success' => false, 'message' => 'Invalid or expired coupon'], 422);
            return;
        }

        // Verify this coupon is usable for online orders
        $applicableTo = array_map('trim', explode(',', (string)($coupon['applicable_to'] ?? 'online')));
        if (!in_array('online', $applicableTo, true)) {
            Response::json(['success' => false, 'message' => 'This coupon cannot be used for online orders'], 422);
            return;
        }

        $validationError = $this->couponValidationError($pdo, $coupon, $userId);
        if ($validationError !== null) {
            Response::json(['success' => false, 'message' => $validationError], 422);
            return;
        }

        $subtotalStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) FROM cart_items WHERE cart_id = :cart_id');
        $subtotalStmt->execute(['cart_id' => $cartId]);
        $subtotal = (float)$subtotalStmt->fetchColumn();
        $discountTotal = $this->calculateDiscountForCoupon($coupon, $subtotal);
        if ($discountTotal <= 0.0) {
            $minOrderAmount = $coupon['min_order_amount'] !== null ? (float)$coupon['min_order_amount'] : 0.0;
            if ($subtotal < $minOrderAmount) {
                Response::json(['success' => false, 'message' => 'Minimum order amount for this coupon is Rs ' . number_format($minOrderAmount, 2)], 422);
                return;
            }
            Response::json(['success' => false, 'message' => 'Coupon is not applicable to this cart'], 422);
            return;
        }

        $_SESSION['applied_coupon'] = [
            'id' => (int)$coupon['id'],
            'code' => (string)$coupon['code'],
            'auto_applied' => false,
        ];
        $_SESSION['coupon_auto_opt_out'] = 1;

        Response::json(['success' => true, 'message' => 'Coupon applied', 'data' => $this->buildCartResponse($pdo, $cartId)]);
    }

    public function fulfilmentByPincode(string $postalCode): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT * FROM delivery_pincodes WHERE postal_code = :postal_code LIMIT 1');
        $stmt->execute(['postal_code' => $postalCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::json(['success' => false, 'message' => 'Pincode not configured'], 404);
            return;
        }

     $distance = (float)$row['approx_distance_km'];

$slabStmt = $pdo->prepare('
SELECT * FROM delivery_distance_slabs 
WHERE :distance >= min_km AND :distance < max_km
LIMIT 1
');

$slabStmt->execute([
    'distance' => $distance
]);

$slab = $slabStmt->fetch(PDO::FETCH_ASSOC);

$deliveryFee = ($slab && (int)$slab['is_available'] === 1)
    ? (float)$slab['delivery_fee']
    : 0.0;

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'postal_code' => $row['postal_code'],
                'area_name' => $row['area_name'],
                'distance_km' => $distance,
                'is_serviceable' => (int)$row['is_serviceable'] === 1,
                'requires_manual_approval' => (int)$row['requires_manual_approval'] === 1,
                'delivery_fee' => $deliveryFee,
                'slab' => $slab ?: null,
            ],
        ]);
    }

    public function fulfilmentSlots(): void
    {
        // ── New slot management system ────────────────────────────────────────
        // Supports both new order_slots tables and graceful fallback.
        // Legacy delivery_time_slots table is preserved and untouched.
        // ─────────────────────────────────────────────────────────────────────

        $mode = (string)($_GET['mode'] ?? $_GET['type'] ?? 'delivery');
        $date = trim((string)($_GET['date'] ?? ''));

        // Normalise mode
        if (!in_array($mode, ['delivery', 'pickup'], true)) {
            $mode = 'delivery';
        }

        // Default date to today if omitted
        if ($date === '') {
            $date = (new \DateTimeImmutable('now'))->format('Y-m-d');
        }

        // Basic date validation
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            Response::json(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.'], 422);
            return;
        }

        // Reject dates more than 60 days out
        $maxDate = (new \DateTimeImmutable('+60 days'))->format('Y-m-d');
        if ($date > $maxDate) {
            Response::json(['success' => false, 'message' => 'Cannot book more than 60 days in advance.'], 422);
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        // Check if new slot management tables exist; fall back to legacy if not
        if (!self::tableExists($pdo, 'order_slots')) {
            // ── Legacy fallback path ──────────────────────────────────────────
            $stmt = $pdo->prepare(
                'SELECT id, slot_label, start_time, end_time, fulfilment_mode, is_same_day_allowed
                 FROM delivery_time_slots
                 WHERE is_active = 1
                   AND (fulfilment_mode = :mode OR fulfilment_mode = "both")
                 ORDER BY sort_order ASC'
            );
            $stmt->execute(['mode' => $mode]);
            Response::json([
                'success' => true,
                'data'    => [
                    'mode'  => 'slots',
                    'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                ],
            ]);
            return;
        }

        // ── New slot management path ──────────────────────────────────────────
        $svc    = new SlotService($pdo);
        $result = $svc->getAvailableSlots($mode, $date);

        Response::json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /** Check if a table exists in the current database (used for progressive migration) */
    private static function tableExists(PDO $pdo, string $tableName): bool
    {
        try {
            $schema = $pdo->query('SELECT DATABASE()')->fetchColumn();
            $stmt   = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table LIMIT 1"
            );
            $stmt->execute(['schema' => $schema, 'table' => $tableName]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function checkoutPreview(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $cartId = $this->getOrCreateCartId($pdo);
        $input = $this->readJsonInput();

        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        $postalCode = trim((string)($input['postal_code'] ?? $input['delivery_pincode'] ?? ''));
        $input['postal_code'] = $postalCode;

        $this->maybeAutoApplyBestPublicCoupon($pdo, $cartId);

        $preview = $this->buildCheckoutPreview($pdo, $cartId, $input);
        if (!($preview['ok'] ?? false)) {
            Response::json([
                'success' => false,
                'message' => (string)($preview['message'] ?? 'Checkout preview validation failed'),
                'code' => (string)($preview['code'] ?? 'checkout_preview_invalid'),
                'field' => (string)($preview['field'] ?? ''),
                'details' => $preview['details'] ?? null,
            ], 422);
            return;
        }

        $cart = $this->buildCartResponse($pdo, $cartId);
        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'cart' => $cart,
                'fulfilment_mode' => $fulfilmentMode,
                'delivery_fee' => (float)($preview['delivery_fee'] ?? 0),
                'distance_km' => $preview['distance_km'] ?? null,
                'grand_total' => (float)($preview['grand_total'] ?? 0),
            ],
        ]);
    }

    public function placeOrder(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        //$cartId = $this->getOrCreateCartId($pdo);
        $userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT id FROM carts 
    WHERE user_id = :user_id 
    LIMIT 1
");

$stmt->execute(['user_id' => $userId]);
$cartId = $stmt->fetchColumn();

if (!$cartId) {
    // Compatibility fallback: session carts may exist without user_id mapping.
    $cartId = $this->getOrCreateCartId($pdo);
}
       $input = $_POST;

// fallback (just in case JSON used)
if (empty($input)) {
    $input = $this->readJsonInput();
}

// 🔥 AFTER INPUT PARSE
if (!isset($_SESSION['user_id']) || empty($_SESSION['otp_verified'])) {
    Response::json([
        'success' => false,
        'message' => 'Please verify OTP before placing order'
    ], 401);
    return;
}

        $customerName = trim((string)($input['customer_name'] ?? ''));
        $customerEmail = trim((string)($input['customer_email'] ?? ''));
        $customerPhone = trim((string)($input['customer_phone'] ?? ''));
        $customerPhoneE164 = PhoneNormalizerService::normalize($customerPhone);
        // Keep a clean digit-prefixed fallback for backward compat (e.g. CRM lookups)
        if ($customerPhoneE164 !== '') {
            $customerPhone = $customerPhoneE164;
        } else {
            // Legacy strip-and-prefix fallback
            $customerPhone = preg_replace('/\D/', '', $customerPhone);
            if ($customerPhone !== '' && strpos($customerPhone, '91') !== 0) {
                $customerPhone = '91' . $customerPhone;
            }
            $customerPhoneE164 = null;
        }
        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        $paymentMethod = (string)($input['payment_method'] ?? 'upi_manual');
        if ($paymentMethod !== 'upi_manual') {
            Response::json([
                'success' => false,
                'message' => 'Online checkout requires UPI payment only',
            ], 422);
            return;
        }

        // ── Screenshot validation (backend enforcement) ──
        $screenshotRequiredSetting = $this->getSettingValue($pdo, 'payment_screenshot_required');
        $screenshotRequired = $screenshotRequiredSetting !== '0';
        $proofFile = $_FILES['payment_proof'] ?? null;
        $proofFileOk = !empty($proofFile) && (int)($proofFile['error'] ?? 99) === UPLOAD_ERR_OK
                       && is_uploaded_file($proofFile['tmp_name']);
        if ($screenshotRequired && $paymentMethod === 'upi_manual' && !$proofFileOk) {
            Response::json([
                'success' => false,
                'message' => 'Payment screenshot required before placing order.',
                'field'   => 'paymentProof',
            ], 422);
            return;
        }
        if ($proofFileOk) {
            // Validate MIME and size before proceeding
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $proofMime = $finfo->file($proofFile['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($proofMime, $allowedMimes, true)) {
                Response::json(['success' => false, 'message' => 'Payment screenshot must be a JPG, PNG, GIF, or WebP image.', 'field' => 'paymentProof'], 422);
                return;
            }
            if ((int)$proofFile['size'] > 5 * 1024 * 1024) {
                Response::json(['success' => false, 'message' => 'Payment screenshot must be under 5 MB.', 'field' => 'paymentProof'], 422);
                return;
            }
        }
        $postalCode      = trim((string)($input['delivery_pincode'] ?? $input['postal_code'] ?? ''));
        $deliveryStreet  = trim((string)($input['delivery_street'] ?? ''));
        $deliveryMapsLink = trim((string)($input['delivery_maps_link'] ?? ''));
        $deliveryDate = trim((string)($input['delivery_date'] ?? ''));
        $slotId = (int)($input['slot_id'] ?? 0);
        $paymentStatus = 'pending';

        $scheduledSlot = null;
        $scheduledSlotLabel = null;

        if ($fulfilmentMode === 'delivery' && $deliveryStreet === '') {
            Response::json(['success' => false, 'message' => 'Delivery street address is required'], 422);
            return;
        }

        if ($fulfilmentMode === 'delivery' && ($deliveryDate === '' || $slotId <= 0)) {
            Response::json([
                'success' => false,
                'message' => 'Delivery date and slot are required for delivery orders',
            ], 422);
            return;
        }

        if ($deliveryDate !== '' || $slotId > 0) {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $deliveryDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $deliveryDate) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid delivery date format',
                ], 422);
                return;
            }

            if ($slotId <= 0) {
                Response::json([
                    'success' => false,
                    'message' => 'Please select a valid delivery slot',
                ], 422);
                return;
            }

            // ── Slot validation: prefer new order_slots, fallback to legacy ──
            if (self::tableExists($pdo, 'order_slots')) {
                // New slot system: validate slot exists and is active
                $slotStmt = $pdo->prepare(
                    'SELECT id, slot_label, slot_name, start_time FROM order_slots
                     WHERE id = :id AND is_active = 1 LIMIT 1'
                );
                $slotStmt->execute(['id' => $slotId]);
                $slotRow = $slotStmt->fetch(PDO::FETCH_ASSOC);
                if (!$slotRow) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selected delivery slot is not available',
                    ], 422);
                    return;
                }

                $startTime = trim((string)($slotRow['start_time'] ?? '00:00:00'));
                if ($startTime === '') {
                    $startTime = '00:00:00';
                }
                $scheduledSlot      = $deliveryDate . ' ' . $startTime;
                $scheduledSlotLabel = trim((string)($slotRow['slot_label'] ?? ''));

                // Create a temporary HOLD (no capacity consumed; admin confirms after payment)
                $slotSvc = new SlotService($pdo);
                try {
                    // Validate slot still available before proceeding
                    $preHold = $slotSvc->holdSlot($slotId, $deliveryDate, 0); // orderId placeholder
                    if (!$preHold['success']) {
                        Response::json([
                            'success' => false,
                            'message' => $preHold['message'],
                        ], 409);
                        return;
                    }
                    // Use the label returned from the hold check (authoritative)
                    $scheduledSlotLabel = $preHold['slot_label'] ?: $scheduledSlotLabel;
                } catch (\RuntimeException $e) {
                    Response::json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 409);
                    return;
                }
            } else {
                // Legacy path: delivery_time_slots
                $slotStmt = $pdo->prepare('SELECT slot_label, start_time FROM delivery_time_slots WHERE id = :id AND is_active = 1 LIMIT 1');
                $slotStmt->execute(['id' => $slotId]);
                $slotRow = $slotStmt->fetch(PDO::FETCH_ASSOC);
                if (!$slotRow) {
                    Response::json([
                        'success' => false,
                        'message' => 'Selected delivery slot is not available',
                    ], 422);
                    return;
                }

                $startTime = trim((string)($slotRow['start_time'] ?? '00:00:00'));
                if ($startTime === '') {
                    $startTime = '00:00:00';
                }
                $scheduledSlot      = $deliveryDate . ' ' . $startTime;
                $scheduledSlotLabel = trim((string)($slotRow['slot_label'] ?? ''));
            }
        }

       if ($customerName === '' || $customerPhone === '') {
    Response::json([
        'success' => false,
        'message' => 'Name and phone are required'
    ], 422);
    return;
}

        $previewInput = [
            'fulfilment_mode' => $fulfilmentMode,
            'postal_code' => $postalCode,
        ];
     
        //$preview = $this->buildCheckoutPreview($pdo, $cartId, $previewInput);
     

        $cart = $this->buildCartResponse($pdo, $cartId);
        $items = $cart['items'];
        if (count($items) === 0) {
            Response::json(['success' => false, 'message' => 'Cart is empty'], 422);
            return;
        }

        $orderNumber = $this->generateOrderNumber('CKF');
        $userId = (int)($_SESSION['user_id'] ?? 0);

        try {
            $pdo->beginTransaction();

$grandTotal = max(0, (float)($cart['subtotal'] ?? 0) - (float)($cart['discount_total'] ?? 0));
            $advanceAmount = null;
            $orderStatus = 'pending_payment';

$orderStmt = $pdo->prepare('
INSERT INTO orders (
    order_number, user_id, customer_name, customer_email, customer_phone, customer_phone_e164,
    fulfilment_mode, order_status, payment_status, payment_method,
    scheduled_slot, scheduled_slot_label,
    delivery_postal_code, delivery_street, delivery_maps_link, delivery_distance_km, delivery_fee,
    subtotal, discount_total, tax_total, grand_total, advance_amount,
    order_mode, requires_kitchen_production, production_status
) VALUES (
    :order_number, :user_id, :customer_name, :customer_email, :customer_phone, :customer_phone_e164,
    :fulfilment_mode, :order_status, :payment_status, :payment_method,
    :scheduled_slot, :scheduled_slot_label,
    :postal_code, :delivery_street, :delivery_maps_link, :distance_km, :delivery_fee,
    :subtotal, :discount_total, 0, :grand_total, :advance_amount,
    "online", 1, "pending"
)');

$orderStmt->execute([
    'order_number' => $orderNumber,
    'user_id' => $userId > 0 ? $userId : null,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'customer_phone_e164' => $customerPhoneE164,
    'fulfilment_mode' => $fulfilmentMode,
    'order_status' => $orderStatus,
    'payment_method' => $paymentMethod,
    'payment_status' => $paymentStatus,
    'scheduled_slot' => $scheduledSlot,
    'scheduled_slot_label' => $scheduledSlotLabel,
    'postal_code' => $postalCode ?: null,
    'delivery_street' => $deliveryStreet ?: null,
    'delivery_maps_link' => $deliveryMapsLink ?: null,

    // ✅ NO DISTANCE/FEE CALCULATION YET
    'distance_km' => 0,
    'delivery_fee' => 0,

    'subtotal' => $cart['subtotal'] ?? 0,
    'discount_total' => $cart['discount_total'] ?? 0,
    'grand_total' => $grandTotal,
    'advance_amount' => $advanceAmount,
]);

            $orderId = (int)$pdo->lastInsertId();

            // Register official slot HOLD now that we have the real orderId
            if (!empty($slotSvc)) {
                try {
                    $holdExpiryMins = (int)($this->getSettingValue($pdo, 'hold_expiry_minutes') ?: 60);
                    $slotSvc->holdSlot($slotId, $deliveryDate, $orderId, $holdExpiryMins);
                    // Denormalize slot_id onto order for fast fulfillment queries
                    $pdo->prepare('UPDATE orders SET slot_id = :slot_id WHERE id = :id')
                        ->execute(['slot_id' => $slotId, 'id' => $orderId]);
                } catch (\Throwable $holdErr) {
                    // Non-fatal — order is placed; hold will show as missing in admin
                    error_log('[placeOrder] slot hold creation failed: ' . $holdErr->getMessage());
                }
            }

            $customisationNote = substr(trim((string)($input['customisation_note'] ?? '')), 0, 500) ?: null;

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
                    unit_price, quantity, line_total, customisation_note,
                    cake_message, topper_id, topper_name_snapshot, topper_price_snapshot
                ) VALUES (
                    :order_id, :product_id, :variant_id, :product_name_snapshot, :variant_snapshot,
                    :unit_price, :quantity, :line_total, :customisation_note,
                    :cake_message, :topper_id, :topper_name_snapshot, :topper_price_snapshot
                )'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id'             => $orderId,
                    'product_id'           => $item['product_id'],
                    'variant_id'           => $item['variant_id'],
                    'product_name_snapshot'=> $item['product_name'],
                    'variant_snapshot'     => $item['variant_label'],
                    'unit_price'           => $item['unit_price'],
                    'quantity'             => $item['quantity'],
                    'line_total'           => $item['line_total'],
                    'customisation_note'   => $customisationNote,
                    'cake_message'         => $item['cake_message'] ?? null,
                    'topper_id'            => isset($item['topper_id']) && $item['topper_id'] > 0 ? (int)$item['topper_id'] : null,
                    'topper_name_snapshot' => $item['topper_name_snapshot'] ?? null,
                    'topper_price_snapshot'=> (float)($item['topper_price'] ?? 0),
                ]);
            }

            $clearStmt = $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $clearStmt->execute(['cart_id' => $cartId]);

            $couponSession = $_SESSION['applied_coupon'] ?? null;
            if (is_array($couponSession) && isset($couponSession['id'])) {
                $this->ensureCouponSchema($pdo);
                $couponId = (int)$couponSession['id'];
                $couponLockStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id FOR UPDATE');
                $couponLockStmt->execute(['id' => $couponId]);
                $coupon = $couponLockStmt->fetch(PDO::FETCH_ASSOC);

                if ($coupon) {
                    $validationError = $this->couponValidationError($pdo, $coupon, $userId);
                    if ($validationError !== null) {
                        throw new \RuntimeException('Coupon became invalid during checkout: ' . $validationError);
                    }

                    $couponUseStmt = $pdo->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :id');
                    $couponUseStmt->execute(['id' => $couponId]);

                    $discountTotal = isset($cart['discount_total']) ? (float)$cart['discount_total'] : 0.0;
                    $redemptionStmt = $pdo->prepare('INSERT INTO coupon_redemptions (coupon_id, order_id, user_id, code_snapshot, discount_total) VALUES (:coupon_id, :order_id, :user_id, :code_snapshot, :discount_total)');
                    $redemptionStmt->execute([
                        'coupon_id' => $couponId,
                        'order_id' => $orderId,
                        'user_id' => $userId > 0 ? $userId : null,
                        'code_snapshot' => (string)$coupon['code'],
                        'discount_total' => $discountTotal,
                    ]);
                }
            }

            unset($_SESSION['applied_coupon']);

            $pdo->commit();

            // ── Save payment proof file (post-commit, non-fatal) ──
            if ($proofFileOk) {
                try {
                    $proofUrl = $this->persistPaymentProof($proofFile, (string)$orderNumber);
                    $proofStmt = $pdo->prepare('UPDATE orders SET payment_proof_url = :url, payment_proof_uploaded_at = NOW() WHERE id = :id');
                    $proofStmt->execute(['url' => $proofUrl, 'id' => $orderId]);
                } catch (\Throwable $proofErr) {
                    error_log('[ORDER_PROOF_FAIL] order_id=' . $orderId . ' error=' . $proofErr->getMessage());
                }
            }

            // Save address to user_addresses for pre-fill on next order
            if ($userId > 0 && $deliveryStreet !== '') {
                try {
                    $addrInsert = $pdo->prepare('INSERT INTO user_addresses (user_id, street, postal_code, maps_link) VALUES (:uid, :street, :postal, :maps)');
                    $addrInsert->execute([
                        'uid' => $userId,
                        'street' => $deliveryStreet,
                        'postal' => $postalCode ?: null,
                        'maps' => $deliveryMapsLink ?: null,
                    ]);
                } catch (\Throwable $addrErr) {
                    error_log('user_addresses insert failed: ' . $addrErr->getMessage());
                }
            }

            try {
                $automation = new OrderAutomationService();
                $automation->handleOrderPlaced($pdo, $orderId, 'online');
            } catch (\Throwable $automationError) {
                error_log('Order placed trigger dispatch failed: ' . $automationError->getMessage());
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ORDER_FAIL] ' . json_encode([
                'module'      => 'placeOrder',
                'error'       => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'user_id'     => $_SESSION['user_id'] ?? null,
                'cart_id'     => $cartId ?? null,
                'order_number'=> $orderNumber ?? null,
                'in_tx'       => false,
            ]));
            Response::json([
                'success' => false,
                'message' => 'Order failed. Try again.'
            ], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Order placed successfully',
            'data' => [
                'order_number' => $orderNumber,
             //   'grand_total' => $preview['grand_total'],
             'grand_total' => $cart['subtotal'] ?? 0,
            ],
        ], 201);
    }

    public function ordersList(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                o.id,
                o.order_number,
                o.order_status,
                o.payment_status,
                o.fulfilment_mode,
                o.discount_total,
                o.grand_total,
                o.created_at,
                COALESCE(item_summary.item_count, 0) AS item_count,
                COALESCE(item_summary.cake_names, "") AS cake_names,
                COALESCE(coupon_summary.coupon_info, "") AS coupon_info
             FROM orders o
             LEFT JOIN (
                SELECT
                    oi.order_id,
                    COUNT(oi.id) AS item_count,
                    GROUP_CONCAT(
                        CONCAT(oi.product_name_snapshot, " x ", oi.quantity)
                        ORDER BY oi.id ASC SEPARATOR ", "
                    ) AS cake_names
                FROM order_items oi
                GROUP BY oi.order_id
             ) item_summary ON item_summary.order_id = o.id
             LEFT JOIN (
                SELECT order_id,
                       GROUP_CONCAT(
                           CONCAT(code_snapshot, " (₹", FORMAT(discount_total, 2), ")")
                           ORDER BY id ASC SEPARATOR ", "
                       ) AS coupon_info
                FROM coupon_redemptions
                GROUP BY order_id
             ) coupon_summary ON coupon_summary.order_id = o.id
             WHERE o.user_id = :user_id
             ORDER BY o.created_at DESC'
        );
        $stmt->execute(['user_id' => (int)$user['id']]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'items' => array_map(static function (array $row): array {
                    $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? 'pending')));
                    $row['can_download_invoice'] = $paymentStatus === 'paid';
                    $row['invoice_download_url'] = '/api/orders/' . (int)($row['id'] ?? 0) . '/invoice';
                    return $row;
                }, $stmt->fetchAll(PDO::FETCH_ASSOC)),
            ],
        ]);
    }

    public function orderDetail(string $id): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }
        $this->ensureBankAlertSchema($pdo);

        $stmt = $pdo->prepare(
            'SELECT
                id,
                order_number,
              customer_name,
              customer_phone,
                order_status,
                payment_status,
                payment_method,
                fulfilment_mode,
                scheduled_slot,
                scheduled_slot_label,
                delivery_postal_code,
                delivery_distance_km,
                delivery_fee,
                subtotal,
                discount_total,
                tax_total,
                grand_total,
                created_at,
                updated_at
             FROM orders
           WHERE (id = :id OR order_number = :order_number)
AND user_id = :user_id
             LIMIT 1'
        );
  $stmt->execute([
    'id' => is_numeric($id) ? (int)$id : 0,
    'order_number' => $id,
    'user_id' => (int)$user['id'],
]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        $itemsStmt = $pdo->prepare(
            'SELECT
                oi.id,
                oi.product_id,
                oi.variant_id,
                oi.product_name_snapshot,
                oi.variant_snapshot,
                oi.unit_price,
                oi.quantity,
                oi.line_total,
                p.slug AS product_slug
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC'
        );
        $itemsStmt->execute(['order_id' => (int)$order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $alertStmt = $pdo->prepare(
            'SELECT id, parsed_utr, status, parsed_amount, confirm_note, created_at, updated_at
             FROM bank_alert_utrs
             WHERE order_id = :order_id
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $alertStmt->execute(['order_id' => (int)$order['id']]);
        $utrSubmission = $alertStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $timeline = [
            ['step' => 'created', 'timestamp' => (string)$order['created_at']],
            ['step' => (string)$order['order_status'], 'timestamp' => (string)($order['updated_at'] ?? $order['created_at'])],
        ];

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'order' => $order,
                'items' => $items,
                'timeline' => $timeline,
                'utr_submission' => $utrSubmission,
                'can_download_invoice' => strtolower(trim((string)($order['payment_status'] ?? 'pending'))) === 'paid',
                'invoice_download_url' => '/api/orders/' . (int)$order['id'] . '/invoice',
            ],
        ]);
    }

    public function orderInvoiceDownload(string $id): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                id,
                order_number,
                customer_name,
                customer_email,
                customer_phone,
                payment_status,
                payment_method,
                fulfilment_mode,
                subtotal,
                discount_total,
                tax_total,
                grand_total,
                created_at
             FROM orders
             WHERE (id = :id OR order_number = :order_number)
               AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => is_numeric($id) ? (int)$id : 0,
            'order_number' => $id,
            'user_id' => (int)$user['id'],
        ]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? 'pending')));
        if ($paymentStatus !== 'paid') {
            Response::json(['success' => false, 'message' => 'Invoice is available only after payment confirmation'], 409);
            return;
        }

        $itemsStmt = $pdo->prepare(
            'SELECT product_name_snapshot, variant_snapshot, quantity, unit_price, line_total
             FROM order_items
             WHERE order_id = :order_id
             ORDER BY id ASC'
        );
        $itemsStmt->execute(['order_id' => (int)$order['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $esc = static function ($value): string {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        };
        $money = static function ($value): string {
            return 'Rs ' . number_format((float)$value, 2);
        };

        $itemRows = '';
        foreach ($items as $item) {
            $name = $esc($item['product_name_snapshot'] ?? 'Item');
            $variant = trim((string)($item['variant_snapshot'] ?? ''));
            if ($variant !== '') {
                $name .= ' (' . $esc($variant) . ')';
            }
            $qty = (int)($item['quantity'] ?? 1);
            $unit = $money((float)($item['unit_price'] ?? 0));
            $line = $money((float)($item['line_total'] ?? 0));
            $itemRows .= '<tr>'
                . '<td style="padding:8px;border:1px solid #ddd;">' . $name . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . $qty . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' . $unit . '</td>'
                . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' . $line . '</td>'
                . '</tr>';
        }

        $invoiceNo = 'INV-' . $esc($order['order_number'] ?? 'ORDER');
        $createdAt = $esc($order['created_at'] ?? '');
        $customerName = $esc($order['customer_name'] ?? 'Customer');
        $customerPhone = $esc($order['customer_phone'] ?? '');
        $customerEmail = $esc($order['customer_email'] ?? '');
        $orderNo = $esc($order['order_number'] ?? '');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $invoiceNo . '</title></head>'
            . '<body style="font-family:Arial,sans-serif;color:#222;max-width:900px;margin:20px auto;padding:16px;">'
            . '<h1 style="margin:0 0 4px;">Cakeouflage Invoice</h1>'
            . '<p style="margin:0 0 16px;color:#666;">Invoice No: ' . $invoiceNo . ' | Date: ' . $createdAt . '</p>'
            . '<p style="margin:0 0 4px;"><strong>Order:</strong> ' . $orderNo . '</p>'
            . '<p style="margin:0 0 4px;"><strong>Customer:</strong> ' . $customerName . '</p>'
            . '<p style="margin:0 0 4px;"><strong>Phone:</strong> ' . $customerPhone . '</p>'
            . '<p style="margin:0 0 16px;"><strong>Email:</strong> ' . $customerEmail . '</p>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;">'
            . '<thead><tr>'
            . '<th style="padding:8px;border:1px solid #ddd;text-align:left;">Item</th>'
            . '<th style="padding:8px;border:1px solid #ddd;">Qty</th>'
            . '<th style="padding:8px;border:1px solid #ddd;text-align:right;">Unit Price</th>'
            . '<th style="padding:8px;border:1px solid #ddd;text-align:right;">Line Total</th>'
            . '</tr></thead><tbody>' . $itemRows . '</tbody></table>'
            . '<div style="max-width:340px;margin-left:auto;">'
            . '<p style="margin:4px 0;display:flex;justify-content:space-between;"><span>Subtotal</span><strong>' . $money((float)($order['subtotal'] ?? 0)) . '</strong></p>'
            . '<p style="margin:4px 0;display:flex;justify-content:space-between;"><span>Coupon Discount</span><strong>' . $money((float)($order['discount_total'] ?? 0)) . '</strong></p>'
            . '<p style="margin:4px 0;display:flex;justify-content:space-between;"><span>Tax</span><strong>' . $money((float)($order['tax_total'] ?? 0)) . '</strong></p>'
            . '<p style="margin:8px 0 0;display:flex;justify-content:space-between;font-size:18px;"><span>Total</span><strong>' . $money((float)($order['grand_total'] ?? 0)) . '</strong></p>'
            . '</div>'
            . '</body></html>';

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="invoice-' . preg_replace('/[^A-Za-z0-9\-_]/', '', (string)($order['order_number'] ?? 'order')) . '.html"');
        echo $html;
    }

    public function submitOrderUtr(string $id): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $this->ensureBankAlertSchema($pdo);

        $orderLookup = $pdo->prepare(
            'SELECT id, order_number, user_id, payment_status, grand_total
             FROM orders
             WHERE (id = :id OR order_number = :order_number)
               AND user_id = :user_id
             LIMIT 1'
        );
        $orderLookup->execute([
            'id' => is_numeric($id) ? (int)$id : 0,
            'order_number' => $id,
            'user_id' => (int)$user['id'],
        ]);
        $order = $orderLookup->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        if ((string)($order['payment_status'] ?? '') === 'paid') {
            Response::json(['success' => false, 'message' => 'Payment is already confirmed for this order'], 409);
            return;
        }

        $input = $this->readJsonInput();
        if ($input === [] && $_POST !== []) {
            /** @var array<string, mixed> $input */
            $input = $_POST;
        }

        $utrRaw = (string)($input['utr'] ?? '');
        $utr = $this->normalizeUtr($utrRaw);
        if ($utr === null) {
            Response::json(['success' => false, 'message' => 'Valid UTR is required'], 422);
            return;
        }

        $note = trim((string)($input['note'] ?? ''));
        $orderId = (int)$order['id'];
        $orderAmount = round((float)($order['grand_total'] ?? 0), 2);

        $existingStmt = $pdo->prepare('SELECT id, source, parsed_amount FROM bank_alert_utrs WHERE parsed_utr = :parsed_utr LIMIT 1');
        $existingStmt->execute(['parsed_utr' => $utr]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $status = 'pending';
        $confidence = 'none';
        $parsedAmount = null;

        if (is_array($existing) && (string)($existing['source'] ?? '') === 'apps_script') {
            $existingAmount = $existing['parsed_amount'] !== null ? round((float)$existing['parsed_amount'], 2) : null;
            $parsedAmount = $existingAmount;
            if ($existingAmount !== null && abs($existingAmount - $orderAmount) < 0.01) {
                $status = 'matched_auto';
                $confidence = 'strong';
            } else {
                $status = 'pending';
                $confidence = 'weak';
            }
        }

        $upsert = $pdo->prepare(
            'INSERT INTO bank_alert_utrs (
                source,
                parsed_utr,
                parsed_amount,
                customer_user_id,
                order_id,
                status,
                match_confidence,
                alert_message,
                raw_payload_json,
                created_at,
                updated_at
             ) VALUES (
                "customer_submit",
                :parsed_utr,
                :parsed_amount,
                :customer_user_id,
                :order_id,
                :status,
                :match_confidence,
                :alert_message,
                :raw_payload_json,
                NOW(),
                NOW()
                 ) AS new
             ON DUPLICATE KEY UPDATE
                     customer_user_id = new.customer_user_id,
                     order_id = new.order_id,
                     status = new.status,
                     match_confidence = new.match_confidence,
                     alert_message = new.alert_message,
                     raw_payload_json = new.raw_payload_json,
                updated_at = NOW()'
        );

        $rawPayload = [
            'submitted_via' => 'customer',
            'order_number' => (string)($order['order_number'] ?? ''),
            'note' => $note,
        ];

        $upsert->execute([
            'parsed_utr' => $utr,
            'parsed_amount' => $parsedAmount,
            'customer_user_id' => (int)$user['id'],
            'order_id' => $orderId,
            'status' => $status,
            'match_confidence' => $confidence,
            'alert_message' => $this->nullableString($note),
            'raw_payload_json' => json_encode($rawPayload, JSON_UNESCAPED_SLASHES),
        ]);

        Response::json([
            'success' => true,
            'message' => $status === 'matched_auto'
                ? 'UTR submitted and matched with bank alert. Awaiting admin confirmation.'
                : 'UTR submitted. Awaiting bank alert match or admin confirmation.',
            'data' => [
                'order_id' => $orderId,
                'order_number' => (string)($order['order_number'] ?? ''),
                'parsed_utr' => $utr,
                'status' => $status,
                'match_confidence' => $confidence,
            ],
        ]);
    }

    public function bankAlertsWebhookIngest(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $this->ensureBankAlertSchema($pdo);

        $mode = strtolower(trim($this->getSettingValue($pdo, 'upi_apps_script_mode')));
        if ($mode === '' || $mode === 'disabled') {
            Response::json(['success' => false, 'message' => 'Bank alerts integration is disabled'], 403);
            return;
        }

        $expectedSecret = trim($this->getSettingValue($pdo, 'upi_apps_script_shared_secret'));
        if ($expectedSecret === '') {
            Response::json(['success' => false, 'message' => 'Shared secret is not configured'], 503);
            return;
        }

        $input = $this->readJsonInput();
        $providedSecret = trim((string)($input['shared_secret'] ?? ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '')));
        if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            Response::json(['success' => false, 'message' => 'Invalid shared secret'], 401);
            return;
        }

        $action = strtolower(trim((string)($input['action'] ?? 'ingest')));
        if ($action === 'ping') {
            Response::json(['success' => true, 'message' => 'Webhook reachable']);
            return;
        }

        $alerts = $input['alerts'] ?? null;
        if (!is_array($alerts)) {
            $alerts = [is_array($input) ? $input : []];
        }

        $ingested = 0;
        $matched = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($alerts as $index => $alert) {
            if (!is_array($alert)) {
                $errors[] = 'Invalid alert payload at index ' . $index;
                continue;
            }

            $utr = $this->normalizeUtr((string)($alert['utr'] ?? $alert['parsed_utr'] ?? $alert['reference'] ?? ''));
            if ($utr === null) {
                $errors[] = 'Missing/invalid UTR at index ' . $index;
                continue;
            }

            $amountRaw = $alert['amount'] ?? $alert['parsed_amount'] ?? null;
            $amount = is_numeric((string)$amountRaw) ? round((float)$amountRaw, 2) : null;
            $sender = trim((string)($alert['sender'] ?? $alert['from'] ?? ''));
            $subject = trim((string)($alert['subject'] ?? ''));
            $message = trim((string)($alert['body'] ?? $alert['message'] ?? ''));
            $eventAt = trim((string)($alert['event_time'] ?? $alert['received_at'] ?? ''));
            $eventAtValue = null;
            if ($eventAt !== '') {
                $ts = strtotime($eventAt);
                if ($ts !== false) {
                    $eventAtValue = date('Y-m-d H:i:s', $ts);
                }
            }

            $status = 'pending';
            $confidence = 'none';
            $orderId = null;
            $customerUserId = null;
            $existingStmt = $pdo->prepare('SELECT id, source, order_id, customer_user_id FROM bank_alert_utrs WHERE parsed_utr = :parsed_utr LIMIT 1');
            $existingStmt->execute(['parsed_utr' => $utr]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (is_array($existing)) {
                $duplicates++;
                $existingOrderId = (int)($existing['order_id'] ?? 0);
                if ($existingOrderId > 0) {
                    $orderId = $existingOrderId;
                    $customerUserId = (int)($existing['customer_user_id'] ?? 0) ?: null;
                }
            }

            if ($orderId === null) {
                $orderNumberMatch = [];
                if (preg_match('/(CKF-\d{8}-\d{4,8})/i', $subject . ' ' . $message, $orderNumberMatch) === 1) {
                    $orderNumber = strtoupper(trim((string)$orderNumberMatch[1]));
                    $orderStmt = $pdo->prepare('SELECT id, user_id, grand_total FROM orders WHERE order_number = :order_number LIMIT 1');
                    $orderStmt->execute(['order_number' => $orderNumber]);
                    $matchedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (is_array($matchedOrder)) {
                        $orderId = (int)$matchedOrder['id'];
                        $customerUserId = (int)($matchedOrder['user_id'] ?? 0) ?: null;
                        if ($amount !== null && abs(round((float)$matchedOrder['grand_total'], 2) - $amount) < 0.01) {
                            $status = 'matched_auto';
                            $confidence = 'strong';
                        } else {
                            $status = 'matched_auto';
                            $confidence = 'weak';
                        }
                    }
                }
            } elseif ($amount !== null) {
                $orderAmountStmt = $pdo->prepare('SELECT grand_total FROM orders WHERE id = :id LIMIT 1');
                $orderAmountStmt->execute(['id' => (int)$orderId]);
                $orderAmount = $orderAmountStmt->fetchColumn();
                if ($orderAmount !== false) {
                    $status = 'matched_auto';
                    $confidence = abs(round((float)$orderAmount, 2) - $amount) < 0.01 ? 'strong' : 'weak';
                }
            }

            $upsert = $pdo->prepare(
                'INSERT INTO bank_alert_utrs (
                    source,
                    parsed_utr,
                    parsed_amount,
                    bank_sender,
                    email_subject,
                    alert_message,
                    event_time,
                    status,
                    match_confidence,
                    customer_user_id,
                    order_id,
                    raw_payload_json,
                    created_at,
                    updated_at
                 ) VALUES (
                    "apps_script",
                    :parsed_utr,
                    :parsed_amount,
                    :bank_sender,
                    :email_subject,
                    :alert_message,
                    :event_time,
                    :status,
                    :match_confidence,
                    :customer_user_id,
                    :order_id,
                    :raw_payload_json,
                    NOW(),
                    NOW()
                 ) AS new
                 ON DUPLICATE KEY UPDATE
                    parsed_amount = COALESCE(new.parsed_amount, parsed_amount),
                    bank_sender = COALESCE(new.bank_sender, bank_sender),
                    email_subject = COALESCE(new.email_subject, email_subject),
                    alert_message = COALESCE(new.alert_message, alert_message),
                    event_time = COALESCE(new.event_time, event_time),
                    status = CASE
                        WHEN status = "confirmed" THEN status
                        ELSE new.status
                    END,
                    match_confidence = CASE
                        WHEN match_confidence = "strong" THEN match_confidence
                        ELSE new.match_confidence
                    END,
                    customer_user_id = COALESCE(new.customer_user_id, customer_user_id),
                    order_id = COALESCE(new.order_id, order_id),
                    raw_payload_json = new.raw_payload_json,
                    updated_at = NOW()'
            );

            $upsert->execute([
                'parsed_utr' => $utr,
                'parsed_amount' => $amount,
                'bank_sender' => $this->nullableString($sender),
                'email_subject' => $this->nullableString($subject),
                'alert_message' => $this->nullableString($message),
                'event_time' => $eventAtValue,
                'status' => $status,
                'match_confidence' => $confidence,
                'customer_user_id' => $customerUserId,
                'order_id' => $orderId,
                'raw_payload_json' => json_encode($alert, JSON_UNESCAPED_SLASHES),
            ]);

            $ingested++;
            if ($status === 'matched_auto') {
                $matched++;
            }
        }

        Response::json([
            'success' => true,
            'message' => 'Bank alerts processed',
            'data' => [
                'received' => count($alerts),
                'ingested' => $ingested,
                'matched' => $matched,
                'duplicates' => $duplicates,
                'errors' => $errors,
            ],
        ]);
    }

    public function wishlistList(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $wishlistId = $this->getOrCreateWishlistId($pdo, (int)$user['id']);
        $stmt = $pdo->prepare(
            'SELECT
                wi.id,
                wi.product_id,
                wi.created_at,
                p.name,
                p.slug,
                p.short_description,
                p.starting_price,
                p.featured_image,
                p.availability_status
             FROM wishlist_items wi
             JOIN products p ON p.id = wi.product_id
             WHERE wi.wishlist_id = :wishlist_id
               AND p.deleted_at IS NULL
             ORDER BY wi.created_at DESC'
        );
        $stmt->execute(['wishlist_id' => $wishlistId]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function wishlistAddItem(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $input = $this->readJsonInput();
        $productId = (int)($input['product_id'] ?? 0);
        if ($productId <= 0) {
            Response::json(['success' => false, 'message' => 'product_id is required'], 422);
            return;
        }

        $existsStmt = $pdo->prepare('SELECT id FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $existsStmt->execute(['id' => $productId]);
        if (!$existsStmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Product not found'], 404);
            return;
        }

        $wishlistId = $this->getOrCreateWishlistId($pdo, (int)$user['id']);
        $stmt = $pdo->prepare(
            'INSERT INTO wishlist_items (wishlist_id, product_id)
             VALUES (:wishlist_id, :product_id)
             ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'wishlist_id' => $wishlistId,
            'product_id' => $productId,
        ]);

        Response::json(['success' => true, 'message' => 'Item saved to wishlist']);
    }

    public function wishlistDeleteItem(string $productId): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $wishlistId = $this->getOrCreateWishlistId($pdo, (int)$user['id']);
        $stmt = $pdo->prepare('DELETE FROM wishlist_items WHERE wishlist_id = :wishlist_id AND product_id = :product_id');
        $stmt->execute([
            'wishlist_id' => $wishlistId,
            'product_id' => (int)$productId,
        ]);

        Response::json(['success' => true, 'message' => 'Item removed from wishlist']);
    }

    public function accountProfileGet(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $profileStmt = $pdo->prepare('SELECT date_of_birth, anniversary_date, celebration_date FROM customer_profiles WHERE user_id = :user_id LIMIT 1');
        $profileStmt->execute(['user_id' => (int)$user['id']]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'date_of_birth' => null,
            'anniversary_date' => null,
            'celebration_date' => null,
        ];
        $profile['doa'] = $profile['anniversary_date'] ?? null;

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'full_name' => (string)$user['full_name'],
                    'email' => (string)$user['email'],
                    'phone' => (string)$user['phone'],
                ],
                'profile' => $profile,
            ],
        ]);
    }

    public function accountProfileUpdate(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $input = $this->readJsonInput();
        $fullName = trim((string)($input['full_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $dob = trim((string)($input['dob'] ?? ''));
        $doa = trim((string)($input['doa'] ?? ''));

        if ($fullName === '' || $phone === '') {
            Response::json(['success' => false, 'message' => 'full_name and phone are required'], 422);
            return;
        }

        $normalizedDob = $this->normalizeOptionalDateValue($dob);
        if ($dob !== '' && $normalizedDob === null) {
            Response::json(['success' => false, 'message' => 'dob must be in YYYY-MM-DD format'], 422);
            return;
        }

        $normalizedDoa = $this->normalizeOptionalDateValue($doa);
        if ($doa !== '' && $normalizedDoa === null) {
            Response::json(['success' => false, 'message' => 'doa must be in YYYY-MM-DD format'], 422);
            return;
        }

        $userStmt = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, updated_at = NOW() WHERE id = :id');
        $userStmt->execute([
            'full_name' => $fullName,
            'phone' => $phone,
            'id' => (int)$user['id'],
        ]);

                $profileStmt = $pdo->prepare(
                        'INSERT INTO customer_profiles (user_id, date_of_birth, anniversary_date)
                         VALUES (:user_id, :date_of_birth, :anniversary_date)
                             ON DUPLICATE KEY UPDATE date_of_birth = VALUES(date_of_birth), anniversary_date = VALUES(anniversary_date), updated_at = NOW()'
                );
        $profileStmt->execute([
            'user_id' => (int)$user['id'],
            'date_of_birth' => $normalizedDob,
            'anniversary_date' => $normalizedDoa,
        ]);

        Response::json(['success' => true, 'message' => 'Profile updated']);
    }

    public function accountAddressesList(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                id,
                label,
                recipient_name,
                phone,
                line1,
                line2,
                landmark,
                city,
                state,
                postal_code,
                is_default
             FROM user_addresses
             WHERE user_id = :user_id
             ORDER BY is_default DESC, id DESC'
        );
        $stmt->execute(['user_id' => (int)$user['id']]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function accountAddressCreate(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $input = $this->readJsonInput();
        $payload = $this->normalizeAddressInput($input);
        if ($payload === null) {
            Response::json(['success' => false, 'message' => 'recipient_name, phone, line1, city, state, and postal_code are required'], 422);
            return;
        }

        try {
           
            $pdo->beginTransaction();

            if ((int)$payload['is_default'] === 1) {
                $clearDefaultStmt = $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id');
                $clearDefaultStmt->execute(['user_id' => (int)$user['id']]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO user_addresses (
                    user_id, label, recipient_name, phone, line1, line2, landmark, city, state, postal_code, is_default
                 ) VALUES (
                    :user_id, :label, :recipient_name, :phone, :line1, :line2, :landmark, :city, :state, :postal_code, :is_default
                 )'
            );
            $stmt->execute([
                'user_id' => (int)$user['id'],
                'label' => $payload['label'],
                'recipient_name' => $payload['recipient_name'],
                'phone' => $payload['phone'],
                'line1' => $payload['line1'],
                'line2' => $payload['line2'],
                'landmark' => $payload['landmark'],
                'city' => $payload['city'],
                'state' => $payload['state'],
                'postal_code' => $payload['postal_code'],
                'is_default' => $payload['is_default'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to create address'], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Address created'], 201);
    }

    public function accountAddressUpdate(string $id): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $addressId = (int)$id;
        $existingStmt = $pdo->prepare('SELECT id FROM user_addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
        $existingStmt->execute(['id' => $addressId, 'user_id' => (int)$user['id']]);
        if (!$existingStmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Address not found'], 404);
            return;
        }

        $input = $this->readJsonInput();
        $payload = $this->normalizeAddressInput($input);
        if ($payload === null) {
            Response::json(['success' => false, 'message' => 'recipient_name, phone, line1, city, state, and postal_code are required'], 422);
            return;
        }

        try {
            $pdo->beginTransaction();

            if ((int)$payload['is_default'] === 1) {
                $clearDefaultStmt = $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :user_id');
                $clearDefaultStmt->execute(['user_id' => (int)$user['id']]);
            }

            $stmt = $pdo->prepare(
                'UPDATE user_addresses
                 SET label = :label,
                     recipient_name = :recipient_name,
                     phone = :phone,
                     line1 = :line1,
                     line2 = :line2,
                     landmark = :landmark,
                     city = :city,
                     state = :state,
                     postal_code = :postal_code,
                     is_default = :is_default,
                     updated_at = NOW()
                 WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                'label' => $payload['label'],
                'recipient_name' => $payload['recipient_name'],
                'phone' => $payload['phone'],
                'line1' => $payload['line1'],
                'line2' => $payload['line2'],
                'landmark' => $payload['landmark'],
                'city' => $payload['city'],
                'state' => $payload['state'],
                'postal_code' => $payload['postal_code'],
                'is_default' => $payload['is_default'],
                'id' => $addressId,
                'user_id' => (int)$user['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to update address'], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Address updated']);
    }

    public function accountAddressDelete(string $id): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $user = $this->getAuthenticatedCustomer($pdo);
        if (!$user) {
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM user_addresses WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'id' => (int)$id,
            'user_id' => (int)$user['id'],
        ]);

        Response::json(['success' => true, 'message' => 'Address removed']);
    }

    public function customCakeInquirySubmit(): void
{
    $pdo = self::db(); if (!$pdo) return;

    $input = $_POST;
    if (empty($input)) {
    $input = $this->readJsonInput();
}
    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phoneCountryCode = trim((string)($input['phone_country_code'] ?? '+91'));
    $phone = preg_replace('/\D+/', '', trim((string)($input['phone'] ?? ''))) ?: '';
    $eventInformation = trim((string)($input['event_information'] ?? ''));
    $eventDate = trim((string)($input['event_date'] ?? ''));
    $servings = trim((string)($input['number_of_servings_guests'] ?? ''));
    $designBrief = trim((string)($input['design_breif_notes'] ?? ''));
    $privacyConsent = (string)($input['privacy_consent'] ?? '') === '1';

    if ($name === '' || $email === '' || $phone === '' || $eventInformation === '' || $eventDate === '' || $servings === '' || $designBrief === '') {
        Response::json(['success' => false, 'message' => 'Please fill all required fields'], 422);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::json(['success' => false, 'message' => 'Please enter a valid email address'], 422);
        return;
    }

    if (!preg_match('/^\+\d{1,4}$/', $phoneCountryCode)) {
        Response::json(['success' => false, 'message' => 'Invalid country code'], 422);
        return;
    }

    if ($phoneCountryCode === '+91' && !preg_match('/^\d{10}$/', $phone)) {
        Response::json(['success' => false, 'message' => 'For India (+91), mobile number must be exactly 10 digits'], 422);
        return;
    }

    if ($phoneCountryCode !== '+91' && !preg_match('/^\d{6,15}$/', $phone)) {
        Response::json(['success' => false, 'message' => 'Please enter a valid mobile number'], 422);
        return;
    }

    if (!preg_match('/^\d+$/', $servings)) {
        Response::json(['success' => false, 'message' => 'Number of servings must be numeric'], 422);
        return;
    }

    if (!$privacyConsent) {
        Response::json(['success' => false, 'message' => 'Please accept privacy policy consent'], 422);
        return;
    }

    $allowedEventInfo = ['Birthday', 'Anniversary', 'Corporate Event', 'Engagement Ceremony', 'Family Gathering', 'Kitty Party', 'BNI Event', 'Others'];
    if (!in_array($eventInformation, $allowedEventInfo, true)) {
        Response::json(['success' => false, 'message' => 'Invalid event information selected'], 422);
        return;
    }

    // ✅ IMAGE UPLOAD LOGIC START
    $referenceImagePath = null;

    if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === 0) {
        $upload = UnifiedMediaService::upload($_FILES['reference_file'], [
            'module' => 'byoc',
            'entity_type' => 'inquiry',
            'entity_id' => 0,
            'admin_id' => 0,
            'allow_svg' => false,
            'max_bytes' => 10 * 1024 * 1024,
        ]);
        if ($upload['ok']) {
            $referenceImagePath = (string)$upload['relative_url'];
        } else {
            error_log('[BYOC_REFERENCE_UPLOAD_FAIL] ' . $upload['error']);
        }
    }
    // ✅ IMAGE UPLOAD LOGIC END

    $encodedMessage = json_encode([
        'phone_country_code' => $phoneCountryCode,
        'event_information' => $eventInformation,
        'event_date' => $eventDate,
        'number_of_servings_guests' => $servings,
        'design_breif_notes' => $designBrief,
        'privacy_consent' => $privacyConsent ? 1 : 0,
    ], JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare(
        'INSERT INTO inquiries (inquiry_type, name, email, phone, message, reference_file)
         VALUES ("custom_cake", :name, :email, :phone, :message, :reference_file)'
    );

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'message' => $encodedMessage,
        'reference_file' => $referenceImagePath // ✅ FIXED
    ]);

    $inquiryId = (int)$pdo->lastInsertId();

    $safeDesignBrief = trim($designBrief);
    $nameParts = preg_split('/\s+/', trim($name)) ?: [];
    $firstName = trim((string)($nameParts[0] ?? $name));
    if ($firstName === '') {
        $firstName = $name;
    }
    $quoteDescription = 'Event: ' . $eventInformation
        . ' | Date: ' . ($eventDate !== '' ? $eventDate : 'Not specified')
        . ' | Servings: ' . $servings;
    if ($safeDesignBrief !== '') {
        $quoteDescription .= ' | Notes: ' . $safeDesignBrief;
    }

    $inquiryContext = [
        'inquiry_id' => $inquiryId,
        'customer_name' => $name,
        'first_name' => $firstName,
        'customer_email' => $email,
        'customer_phone' => $phone,
        'phone_country_code' => $phoneCountryCode,
        'event_information' => $eventInformation,
        'event_date' => $eventDate,
        'number_of_servings_guests' => $servings,
        'design_brief_notes' => $safeDesignBrief,
        'quote_description' => $quoteDescription,
        'reference_file' => (string)$referenceImagePath,
        'contact.name' => $name,
        'contact.first_name' => $firstName,
        'contact.mobile' => $phone,
        'contact.phone' => $phone,
        'contact.email' => $email,
        'contact.orderid' => 'INQ-' . $inquiryId,
        'contact.description' => $quoteDescription,
        'contact.quote_description' => $quoteDescription,
    ];

    try {
        $automation = new OrderAutomationService();
        $crmContext = $automation->buildCustomCakeCrmContext([
            'name' => $name,
            'email' => $email,
            'phone_country_code' => $phoneCountryCode,
            'phone' => $phone,
            'event_information' => $eventInformation,
            'event_date' => $eventDate,
            'number_of_servings_guests' => $servings,
            'design_breif_notes' => $designBrief,
            'reference_file' => (string)$referenceImagePath,
        ]);
        $crmContext = array_merge($crmContext, $inquiryContext);
        $automation->queueCrmWebhookForInquiry($pdo, 'build_your_own_cake_webhook', $crmContext);
    } catch (\Throwable $automationError) {
        error_log('Custom cake webhook queue failed: ' . $automationError->getMessage());
    }

    try {
        $this->queueEmailCommunicationLog($pdo, $email, 'build_your_cake_inquiry_customer_email', $inquiryContext);

        $adminRecipients = $this->loadAdminCommunicationRecipients($pdo);
        if ($adminRecipients['to'] !== '') {
            $adminContext = $inquiryContext;
            $adminContext['admin_primary_email'] = $adminRecipients['to'];
            $adminContext['admin_cc_emails'] = implode(', ', $adminRecipients['cc']);
            $adminContext['recipient_role'] = 'admin_primary';
            $this->queueEmailCommunicationLog($pdo, $adminRecipients['to'], 'build_your_cake_inquiry_admin_email', $adminContext);
            foreach ($adminRecipients['cc'] as $ccEmail) {
                $ccContext = $adminContext;
                $ccContext['recipient_role'] = 'admin_cc';
                $this->queueEmailCommunicationLog($pdo, $ccEmail, 'build_your_cake_inquiry_admin_email', $ccContext);
            }
        }
    } catch (\Throwable $emailQueueError) {
        error_log('Custom cake inquiry email queue failed: ' . $emailQueueError->getMessage());
    }

    Response::json(['success' => true, 'message' => 'Custom cake inquiry submitted'], 201);
}

    public function customCakeQuoteAccept(string $token): void
    {
        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $token = trim($token);
        if ($token === '') {
            Response::json(['success' => false, 'message' => 'Invalid quote link.'], 422);
            return;
        }

        try {
            $this->ensureByocQuoteSchema($pdo);
            (new ByocQuoteExpiryService())->expireDueQuotes($pdo);
            $pdo->beginTransaction();

            $quoteStmt = $pdo->prepare(
                'SELECT
                    bql.id AS link_id,
                    bql.token,
                    bql.is_active,
                    bql.expires_at AS link_expires_at,
                    bql.used_at,
                    bq.id AS quote_id,
                    bq.quote_number,
                    bq.quote_subject,
                    bq.quote_message,
                    bq.quote_amount,
                    bq.currency,
                    bq.status AS quote_status,
                    bq.order_id,
                    i.id AS inquiry_id,
                    i.name,
                    i.email,
                    i.phone,
                    i.message
                 FROM byoc_quote_links bql
                 INNER JOIN byoc_quotes bq ON bq.id = bql.byoc_quote_id
                 INNER JOIN inquiries i ON i.id = bq.inquiry_id
                 WHERE bql.token = :token
                 LIMIT 1
                 FOR UPDATE'
            );
            $quoteStmt->execute(['token' => $token]);
            $quote = $quoteStmt->fetch(PDO::FETCH_ASSOC);

            if (!$quote) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Quote link not found.'], 404);
                return;
            }

            // Reject links whose parent quote is cancelled
            if ((string)($quote['quote_status'] ?? '') === 'cancelled') {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'This quote has been cancelled. Please check for a revised quote.'], 410);
                return;
            }
            if ((string)($quote['quote_status'] ?? '') === 'expired') {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'This quote link has expired.'], 410);
                return;
            }
            // Reject deactivated links (superseded by a resend / cancel)
            if (!(bool)($quote['is_active'] ?? 1)) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'This quote link is no longer valid. A revised quote may have been sent.'], 410);
                return;
            }
            $linkExpiryTs = strtotime((string)($quote['link_expires_at'] ?? ''));
            if (!empty($quote['used_at'])) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'This quote link has already been used.'], 409);
                return;
            }
            if ($linkExpiryTs !== false && $linkExpiryTs < time()) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'This quote link has expired.'], 410);
                return;
            }

            if (!empty($quote['order_id'])) {
                $pdo->rollBack();
                Response::json([
                    'success' => true,
                    'message' => 'Quote already accepted.',
                    'data' => [
                        'order_id' => (int)$quote['order_id'],
                    ],
                ]);
                return;
            }

            $meta = json_decode((string)($quote['message'] ?? ''), true);
            if (!is_array($meta)) {
                $meta = [];
            }

            $quoteAmount = (float)($quote['quote_amount'] ?? 0);
            if ($quoteAmount <= 0) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Quote amount is not valid.'], 422);
                return;
            }

            $productId = $this->resolveByocFallbackProductId($pdo);
            if ($productId <= 0) {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'No active product found for BYOC order conversion.'], 500);
                return;
            }

            // Parse address fields from form submission
            $input = $this->readJsonInput();
            if (empty($input)) {
                $input = $_POST;
            }
            $deliveryStreet   = trim((string)($input['delivery_street'] ?? ''));
            $deliveryPincode  = trim((string)($input['delivery_pincode'] ?? ''));
            $deliveryMapsLink = trim((string)($input['delivery_maps_link'] ?? ''));
            $fulfillmentType  = in_array($input['fulfillment_type'] ?? '', ['delivery', 'pickup'], true)
                ? $input['fulfillment_type'] : 'delivery';
            $slotId           = max((int)($input['slot_id'] ?? 0), 0);
            $paymentType = 'full';
            if ($fulfillmentType === 'delivery' && $deliveryStreet === '') {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Delivery street address is required.'], 422);
                return;
            }

            // Coupon validation for BYOC order
            $couponCode = strtoupper(trim((string)($input['coupon_code'] ?? '')));
            $appliedCouponId = null;
            $discountTotal = 0.0;
            if ($couponCode !== '') {
                $this->ensureCouponSchema($pdo);
                $cs = $pdo->prepare(
                    'SELECT id, discount_type, discount_value, max_discount, min_order_amount,
                            usage_limit, usage_count, starts_at, ends_at, applicable_to
                     FROM coupons WHERE UPPER(code) = :code AND is_active = 1 AND is_deleted = 0 LIMIT 1'
                );
                $cs->execute(['code' => $couponCode]);
                $couponRow = $cs->fetch(PDO::FETCH_ASSOC);
                if (!$couponRow) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'Coupon code not found or inactive.'], 422);
                    return;
                }
                $couponModules = array_map('trim', explode(',', (string)($couponRow['applicable_to'] ?? '')));
                if (!in_array('byoc', $couponModules, true)) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'This coupon cannot be used for BYOC orders.'], 422);
                    return;
                }
                $nowTs = time();
                if (!empty($couponRow['starts_at']) && strtotime((string)$couponRow['starts_at']) > $nowTs) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'Coupon is not yet active.'], 422);
                    return;
                }
                if (!empty($couponRow['ends_at']) && strtotime((string)$couponRow['ends_at']) < $nowTs) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'Coupon has expired.'], 422);
                    return;
                }
                if ($couponRow['usage_limit'] !== null && (int)$couponRow['usage_count'] >= (int)$couponRow['usage_limit']) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'Coupon usage limit has been reached.'], 422);
                    return;
                }
                $couponMinOrder = $couponRow['min_order_amount'] !== null ? (float)$couponRow['min_order_amount'] : 0.0;
                if ($quoteAmount < $couponMinOrder) {
                    $pdo->rollBack();
                    Response::json(['success' => false, 'message' => 'Minimum order amount for this coupon is ₹' . number_format($couponMinOrder, 2) . '.'], 422);
                    return;
                }
                $appliedCouponId = (int)$couponRow['id'];
                if ((string)$couponRow['discount_type'] === 'flat') {
                    $discountTotal = min((float)$couponRow['discount_value'], $quoteAmount);
                } else {
                    $discountTotal = ($quoteAmount * (float)$couponRow['discount_value']) / 100.0;
                    if ($couponRow['max_discount'] !== null) {
                        $discountTotal = min($discountTotal, (float)$couponRow['max_discount']);
                    }
                }
                $discountTotal = round($discountTotal, 2);
            }
            $grandTotal = max(round($quoteAmount - $discountTotal, 2), 0.0);

            $orderNumber = $this->generateOrderNumber('BYOC');
            $customerName = trim((string)($quote['name'] ?? 'Guest Customer'));
            $customerEmail = trim((string)($quote['email'] ?? ''));
            if ($customerEmail === '') {
                $customerEmail = 'guest-' . strtolower(bin2hex(random_bytes(4))) . '@cakeouflage.local';
            }

            $customerPhone = preg_replace('/\D+/', '', (string)($quote['phone'] ?? '')) ?: '0000000000';

            $insertOrder = $pdo->prepare(
                'INSERT INTO orders (
                    order_number,
                    user_id,
                    customer_name,
                    customer_email,
                    customer_phone,
                    fulfilment_mode,
                    order_status,
                    payment_status,
                    payment_method,
                    scheduled_slot,
                    scheduled_slot_label,
                    delivery_postal_code,
                    delivery_street,
                    delivery_maps_link,
                    delivery_distance_km,
                    delivery_fee,
                    subtotal,
                    discount_total,
                    tax_total,
                    grand_total,
                    advance_amount,
                    admin_note,
                    order_source,
                    byoc_quote_id,
                    order_mode,
                    requires_kitchen_production,
                    production_status
                ) VALUES (
                    :order_number,
                    NULL,
                    :customer_name,
                    :customer_email,
                    :customer_phone,
                    :fulfilment_mode,
                    :order_status,
                    :payment_status,
                    "upi_manual",
                    :scheduled_slot,
                    :scheduled_slot_label,
                    :delivery_postal_code,
                    :delivery_street,
                    :delivery_maps_link,
                    NULL,
                    0,
                    :subtotal,
                    :discount_total,
                    0,
                    :grand_total,
                    :advance_amount,
                    :admin_note,
                    "byoc_quote",
                    :byoc_quote_id,
                    "byoc",
                    1,
                    "pending"
                )'
            );
            $fulfilmentMode = $fulfillmentType === 'pickup' ? 'pickup' : 'custom_delivery';
            $advanceAmount = null;
            $paymentStatus = 'pending';
            $orderStatus = 'pending_payment';
            $insertOrder->execute([
                'order_number' => $orderNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'fulfilment_mode' => $fulfilmentMode,
                'order_status' => $orderStatus,
                'scheduled_slot' => !empty($meta['event_date']) ? $meta['event_date'] . ' 10:00:00' : null,
                'scheduled_slot_label' => !empty($meta['event_date']) ? 'Event Date: ' . $meta['event_date'] : null,
                'delivery_postal_code' => $deliveryPincode ?: null,
                'delivery_street' => $fulfillmentType === 'delivery' ? $deliveryStreet : null,
                'delivery_maps_link' => $deliveryMapsLink ?: null,
                'subtotal' => $quoteAmount,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'advance_amount' => $advanceAmount,
                'payment_status' => $paymentStatus,
                'admin_note' => 'BYOC quote accepted via secure link. Quote #' . (string)($quote['quote_number'] ?? ''),
                'byoc_quote_id' => (int)$quote['quote_id'],
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // Record coupon redemption if a coupon was applied
            if ($orderId > 0 && $appliedCouponId !== null) {
                $pdo->prepare(
                    'INSERT IGNORE INTO coupon_redemptions (coupon_id, order_id, user_id, code_snapshot, discount_total) VALUES (:coupon_id, :order_id, NULL, :code_snapshot, :discount_total)'
                )->execute([
                    'coupon_id' => $appliedCouponId,
                    'order_id' => $orderId,
                    'code_snapshot' => $couponCode,
                    'discount_total' => $discountTotal,
                ]);
                $pdo->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :id')
                    ->execute(['id' => $appliedCouponId]);
            }

            // Slot booking (non-fatal)
            if ($slotId > 0 && !empty($meta['event_date'])) {
                try {
                    $slotSvc = new SlotService($pdo);
                    $slotSvc->holdSlot($slotId, $meta['event_date'], $orderId, 0);
                    $slotSvc->confirmSlotReservation($orderId);
                    $pdo->prepare('UPDATE orders SET slot_id = :slot_id WHERE id = :id')
                        ->execute(['slot_id' => $slotId, 'id' => $orderId]);
                } catch (\Throwable $slotErr) {
                    error_log('[customCakeQuoteAccept] Slot booking error: ' . $slotErr->getMessage());
                }
            }

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id,
                    product_id,
                    variant_id,
                    product_name_snapshot,
                    variant_snapshot,
                    unit_price,
                    quantity,
                    line_total,
                    customisation_note
                ) VALUES (
                    :order_id,
                    :product_id,
                    NULL,
                    :product_name_snapshot,
                    NULL,
                    :unit_price,
                    1,
                    :line_total,
                    :customisation_note
                )'
            );
            $insertItem->execute([
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_name_snapshot' => (string)($quote['quote_subject'] ?: 'Build Your Own Cake Quote'),
                'unit_price' => $quoteAmount,
                'line_total' => $quoteAmount,
                'customisation_note' => (string)($quote['quote_message'] ?? ''),
            ]);

            $updateQuote = $pdo->prepare(
                'UPDATE byoc_quotes
                 SET order_id = :order_id,
                     status = "accepted",
                     accepted_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $updateQuote->execute([
                'order_id' => $orderId,
                'id' => (int)$quote['quote_id'],
            ]);

            $updateLink = $pdo->prepare('UPDATE byoc_quote_links SET used_at = NOW(), is_active = 0 WHERE id = :id');
            $updateLink->execute(['id' => (int)$quote['link_id']]);

            $inquiryUpdate = $pdo->prepare('UPDATE inquiries SET status = "in_review", updated_at = NOW() WHERE id = :id AND inquiry_type = "custom_cake"');
            $inquiryUpdate->execute(['id' => (int)$quote['inquiry_id']]);

            $pdo->commit();

            // Queue confirmation emails (after commit so IDs are stable)
            try {
                $confirmationUrl = '/order-confirmation/' . $orderNumber . '?t=' . urlencode($token);
                $currency = '₹';
                $remainingBalance = max(0, $quoteAmount);
                $deliveryAddress = trim($deliveryStreet . ($deliveryPincode !== '' ? ', ' . $deliveryPincode : ''));
                $emailContext = [
                    'order_number'     => $orderNumber,
                    'customer_name'    => $customerName,
                    'customer_email'   => $customerEmail,
                    'customer_phone'   => $customerPhone,
                    'grand_total'      => number_format($quoteAmount, 2),
                    'advance_amount'   => number_format(0, 2),
                    'remaining_balance' => number_format($remainingBalance, 2),
                    'payment_status'   => $paymentStatus,
                    'currency'         => $currency,
                    'delivery_address' => $deliveryAddress,
                    'event_date'       => $meta['event_date'] ?? '',
                    'confirmation_url' => $confirmationUrl,
                ];

                if ($customerEmail !== '') {
                    $this->queueEmailCommunicationLog($pdo, $customerEmail, 'byoc_order_confirmed_customer', $emailContext);
                }

                $adminRecipients = $this->loadAdminCommunicationRecipients($pdo);
                $adminContext = array_merge($emailContext, ['admin_primary_email' => $adminRecipients['to']]);
                $this->queueEmailCommunicationLog($pdo, $adminRecipients['to'], 'byoc_order_confirmed_admin', $adminContext);
                foreach ($adminRecipients['cc'] as $ccEmail) {
                    $this->queueEmailCommunicationLog($pdo, $ccEmail, 'byoc_order_confirmed_admin', $adminContext);
                }
            } catch (\Throwable $emailErr) {
                error_log('BYOC order confirmation email queue failed: ' . $emailErr->getMessage());
            }

            // Queue CRM trigger for BYOC order (same as online orders)
            try {
                $automation = new OrderAutomationService();
                $automation->handleOrderPlaced($pdo, $orderId, 'byoc');
            } catch (\Throwable $crmErr) {
                error_log('BYOC order CRM trigger failed: ' . $crmErr->getMessage());
            }

            Response::json([
                'success' => true,
                'message' => 'Order created from quote. Please complete payment to confirm acceptance.',
                'data' => [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'quote_id' => (int)$quote['quote_id'],
                    'confirmation_url' => '/order-confirmation/' . $orderNumber . '?t=' . urlencode($token),
                ],
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('BYOC quote accept failed: ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Could not accept quote right now. Please try again shortly.'], 500);
        }
    }

    public function courseInquirySubmit(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();

        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $workshop = trim((string)($input['workshop'] ?? ''));
        $preferredDate = trim((string)($input['preferred_date'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));

        if ($name === '' || $phone === '' || $workshop === '') {
            Response::json(['success' => false, 'message' => 'name, phone and workshop are required'], 422);
            return;
        }

        $encoded = json_encode([
            'workshop' => $workshop,
            'preferred_date' => $preferredDate,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare(
            'INSERT INTO inquiries (inquiry_type, name, email, phone, message)
             VALUES ("course", :name, :email, :phone, :message)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email !== '' ? $email : 'na@local.invalid',
            'phone' => $phone,
            'message' => $encoded,
        ]);

        Response::json(['success' => true, 'message' => 'Course enquiry submitted'], 201);
    }

    public function eventInquirySubmit(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();

        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $eventSlug = trim((string)($input['event_slug'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $attendees = max(1, (int)($input['attendees'] ?? 1));

        if ($name === '' || $email === '' || $phone === '' || $eventSlug === '') {
            Response::json(['success' => false, 'message' => 'name, email, phone and event_slug are required'], 422);
            return;
        }

        $eventStmt = $pdo->prepare('SELECT id, title, seats_available FROM events WHERE slug = :slug AND is_published = 1 LIMIT 1');
        $eventStmt->execute(['slug' => $eventSlug]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            Response::json(['success' => false, 'message' => 'Selected event not found'], 404);
            return;
        }

        $encodedMessage = json_encode([
            'event_slug' => $eventSlug,
            'event_title' => (string)$event['title'],
            'attendees' => $attendees,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        try {
            $pdo->beginTransaction();

            $insInquiry = $pdo->prepare(
                'INSERT INTO inquiries (inquiry_type, name, email, phone, message)
                 VALUES ("event", :name, :email, :phone, :message)'
            );
            $insInquiry->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'message' => $encodedMessage,
            ]);

            $insReg = $pdo->prepare(
                'INSERT INTO event_registrations (
                    event_id, participant_name, participant_email, participant_phone,
                    attendees_count, registration_status, note
                 ) VALUES (
                    :event_id, :participant_name, :participant_email, :participant_phone,
                    :attendees_count, "new", :note
                 )'
            );
            $insReg->execute([
                'event_id' => (int)$event['id'],
                'participant_name' => $name,
                'participant_email' => $email,
                'participant_phone' => $phone,
                'attendees_count' => $attendees,
                'note' => $message !== '' ? $message : null,
            ]);

            $available = (int)($event['seats_available'] ?? 0);
            if ($available > 0) {
                $updateSeats = $pdo->prepare('UPDATE events SET seats_available = GREATEST(0, seats_available - :count) WHERE id = :id');
                $updateSeats->execute([
                    'count' => $attendees,
                    'id' => (int)$event['id'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Unable to submit event registration'], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Event registration submitted'], 201);
    }

    public function b2bInquiry(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();

        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));

        if ($name === '' || $email === '' || $phone === '') {
            Response::json(['success' => false, 'message' => 'name, email and phone are required'], 422);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO inquiries (inquiry_type, name, email, phone, message)
             VALUES ("b2b_registration", :name, :email, :phone, :message)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        ]);

        Response::json(['success' => true, 'message' => 'B2B enquiry submitted'], 201);
    }

    public function b2bAuthRegister(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();

        $fullName = trim((string)($input['full_name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $companyName = trim((string)($input['company_name'] ?? ''));
        $accountType = trim((string)($input['account_type'] ?? ''));
        $companyPhone = trim((string)($input['company_phone'] ?? ''));
        $companyEmail = trim((string)($input['company_email'] ?? ''));
        $gstNumber = trim((string)($input['gst_number'] ?? ''));

        $allowedTypes = ['corporate_client', 'business_buyer', 'reseller', 'cake_shop_owner'];
        if (
            $fullName === '' ||
            $email === '' ||
            $phone === '' ||
            strlen($password) < 8 ||
            $companyName === '' ||
            !in_array($accountType, $allowedTypes, true)
        ) {
            Response::json(['success' => false, 'message' => 'Invalid B2B registration input'], 422);
            return;
        }

        if ($companyPhone === '') {
            $companyPhone = $phone;
        }
        if ($companyEmail === '') {
            $companyEmail = $email;
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Email already registered'], 409);
            return;
        }

        try {
            $pdo->beginTransaction();

            $userStmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, password_hash, role)
                 VALUES (:full_name, :email, :phone, :password_hash, "b2b_user")'
            );
            $userStmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            ]);
            $userId = (int)$pdo->lastInsertId();

            $accountStmt = $pdo->prepare(
                'INSERT INTO b2b_accounts (
                    user_id, company_name, account_type, gst_number, company_phone, company_email, approval_status, notes
                ) VALUES (
                    :user_id, :company_name, :account_type, :gst_number, :company_phone, :company_email, "pending", :notes
                )'
            );
            $accountStmt->execute([
                'user_id' => $userId,
                'company_name' => $companyName,
                'account_type' => $accountType,
                'gst_number' => $gstNumber !== '' ? $gstNumber : null,
                'company_phone' => $companyPhone,
                'company_email' => $companyEmail,
                'notes' => trim((string)($input['notes'] ?? '')),
            ]);

            $inquiryStmt = $pdo->prepare(
                'INSERT INTO inquiries (inquiry_type, name, email, phone, message)
                 VALUES ("b2b_registration", :name, :email, :phone, :message)'
            );
            $inquiryStmt->execute([
                'name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'message' => json_encode([
                    'company_name' => $companyName,
                    'account_type' => $accountType,
                    'gst_number' => $gstNumber,
                    'company_phone' => $companyPhone,
                    'company_email' => $companyEmail,
                    'notes' => trim((string)($input['notes'] ?? '')),
                ], JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to submit B2B registration'], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'B2B registration submitted. Your account will be available after approval.',
        ], 201);
    }

    public function b2bAuthLogin(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::json(['success' => false, 'message' => 'Email and password are required'], 422);
            return;
        }

        $rateLimiter = new AuthRateLimitService();
        $bucketKey = $this->buildRateLimitBucket('b2b-login', $email);
        if ($rateLimiter->isBlocked($pdo, 'b2b_login', $bucketKey)) {
            Response::json(['success' => false, 'message' => 'Too many login attempts. Please try again later.'], 429);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
                u.id,
                u.full_name,
                u.email,
                u.role,
                u.password_hash,
                b.id AS b2b_account_id,
                b.company_name,
                b.approval_status
             FROM users u
             LEFT JOIN b2b_accounts b ON b.user_id = u.id
             WHERE u.email = :email AND u.role = "b2b_user" AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $rateLimiter->hit($pdo, 'b2b_login', $bucketKey, 6, 20);
            Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
            return;
        }

        $approvalStatus = (string)($user['approval_status'] ?? 'pending');
        if ($approvalStatus !== 'approved') {
            Response::json([
                'success' => false,
                'message' => 'Your B2B account is not approved yet.',
                'data' => ['approval_status' => $approvalStatus],
            ], 403);
            return;
        }

        $rateLimiter->clear($pdo, 'b2b_login', $bucketKey);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = 'b2b_user';
        $_SESSION['b2b_account_id'] = (int)($user['b2b_account_id'] ?? 0);

        $updateStmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $updateStmt->execute(['id' => $user['id']]);

        Response::json([
            'success' => true,
            'message' => 'B2B login successful',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'full_name' => (string)$user['full_name'],
                    'email' => (string)$user['email'],
                    'role' => 'b2b_user',
                ],
                'account' => [
                    'id' => (int)($user['b2b_account_id'] ?? 0),
                    'company_name' => (string)($user['company_name'] ?? ''),
                    'approval_status' => $approvalStatus,
                ],
            ],
        ]);
    }

    public function b2bAuthMe(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $account = $this->getAuthenticatedB2bAccount($pdo);
        if (!$account) {
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'user' => [
                    'id' => (int)$account['user_id'],
                    'full_name' => (string)$account['full_name'],
                    'email' => (string)$account['email'],
                    'phone' => (string)$account['phone'],
                    'role' => 'b2b_user',
                ],
                'account' => [
                    'id' => (int)$account['id'],
                    'company_name' => (string)$account['company_name'],
                    'account_type' => (string)$account['account_type'],
                    'company_phone' => (string)$account['company_phone'],
                    'company_email' => (string)$account['company_email'],
                    'approval_status' => (string)$account['approval_status'],
                    'credit_limit' => (float)($account['credit_limit'] ?? 0),
                ],
            ],
        ]);
    }

    public function b2bAuthLogout(): void
    {
        unset($_SESSION['b2b_account_id']);
        $this->authLogout();
    }

    public function b2bDashboardSummary(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $account = $this->getAuthenticatedB2bAccount($pdo, true);
        if (!$account) {
            return;
        }

        $accountId = (int)$account['id'];
        $summary = [
            'pending_quotes' => 0,
            'accepted_quotes' => 0,
            'orders_total' => 0,
            'orders_completed' => 0,
            'current_credit_limit' => (float)($account['credit_limit'] ?? 0),
        ];

        $pendingStmt = $pdo->prepare('SELECT COUNT(*) FROM b2b_quotes WHERE b2b_account_id = :account_id AND status IN ("requested", "drafted", "sent")');
        $pendingStmt->execute(['account_id' => $accountId]);
        $summary['pending_quotes'] = (int)$pendingStmt->fetchColumn();

        $acceptedStmt = $pdo->prepare('SELECT COUNT(*) FROM b2b_quotes WHERE b2b_account_id = :account_id AND status = "accepted"');
        $acceptedStmt->execute(['account_id' => $accountId]);
        $summary['accepted_quotes'] = (int)$acceptedStmt->fetchColumn();

        $ordersStmt = $pdo->prepare('SELECT COUNT(*) FROM b2b_orders WHERE b2b_account_id = :account_id');
        $ordersStmt->execute(['account_id' => $accountId]);
        $summary['orders_total'] = (int)$ordersStmt->fetchColumn();

        $completedStmt = $pdo->prepare('SELECT COUNT(*) FROM b2b_orders WHERE b2b_account_id = :account_id AND order_status = "completed"');
        $completedStmt->execute(['account_id' => $accountId]);
        $summary['orders_completed'] = (int)$completedStmt->fetchColumn();

        Response::json(['success' => true, 'message' => 'ok', 'data' => $summary]);
    }

    public function b2bDashboardQuotes(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $account = $this->getAuthenticatedB2bAccount($pdo, true);
        if (!$account) {
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, quote_number, event_type, fulfilment_mode, scheduled_date, status, grand_total, created_at
             FROM b2b_quotes
             WHERE b2b_account_id = :account_id
             ORDER BY created_at DESC
             LIMIT 12'
        );
        $stmt->execute(['account_id' => (int)$account['id']]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function b2bDashboardQuoteRequest(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $account = $this->getAuthenticatedB2bAccount($pdo, true);
        if (!$account) {
            return;
        }

        $input = $this->readJsonInput();
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        if (count($items) === 0) {
            Response::json(['success' => false, 'message' => 'At least one item is required for quote request'], 422);
            return;
        }

        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        if (!in_array($fulfilmentMode, ['delivery', 'pickup'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid fulfilment mode'], 422);
            return;
        }

        try {
            $pdo->beginTransaction();

            $quoteNumber = $this->generateOrderNumber('B2BQ');
            $subtotal = 0.0;
            $normalizedItems = [];

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $productId = (int)($item['product_id'] ?? 0);
                $variantId = (int)($item['variant_id'] ?? 0);
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $customisationNote = trim((string)($item['customisation_note'] ?? ''));

                if ($productId <= 0) {
                    continue;
                }

                if ($variantId <= 0) {
                    $defaultStmt = $pdo->prepare('SELECT id FROM product_variants WHERE product_id = :product_id AND is_default = 1 AND is_active = 1 LIMIT 1');
                    $defaultStmt->execute(['product_id' => $productId]);
                    $variantId = (int)($defaultStmt->fetchColumn() ?: 0);
                }

                $variantStmt = $pdo->prepare(
                    'SELECT id, product_id, price, discount_price
                     FROM product_variants
                     WHERE id = :id AND is_active = 1
                     LIMIT 1'
                );
                $variantStmt->execute(['id' => $variantId]);
                $variant = $variantStmt->fetch(PDO::FETCH_ASSOC);
                if (!$variant || (int)$variant['product_id'] !== $productId) {
                    continue;
                }

                $unitPrice = (float)($variant['discount_price'] ?? 0) > 0 ? (float)$variant['discount_price'] : (float)$variant['price'];
                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $normalizedItems[] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'customisation_note' => $customisationNote !== '' ? $customisationNote : null,
                ];
            }

            if (count($normalizedItems) === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                Response::json(['success' => false, 'message' => 'No valid quote items were provided'], 422);
                return;
            }

            $quoteStmt = $pdo->prepare(
                'INSERT INTO b2b_quotes (
                    quote_number, b2b_account_id, event_type, fulfilment_mode, scheduled_date, status,
                    subtotal, discount_total, tax_total, grand_total, admin_note
                ) VALUES (
                    :quote_number, :b2b_account_id, :event_type, :fulfilment_mode, :scheduled_date, "requested",
                    :subtotal, 0, 0, :grand_total, :admin_note
                )'
            );
            $quoteStmt->execute([
                'quote_number' => $quoteNumber,
                'b2b_account_id' => (int)$account['id'],
                'event_type' => trim((string)($input['event_type'] ?? '')),
                'fulfilment_mode' => $fulfilmentMode,
                'scheduled_date' => trim((string)($input['scheduled_date'] ?? '')) !== '' ? (string)$input['scheduled_date'] : null,
                'subtotal' => round($subtotal, 2),
                'grand_total' => round($subtotal, 2),
                'admin_note' => trim((string)($input['note'] ?? '')) !== '' ? (string)$input['note'] : null,
            ]);
            $quoteId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO b2b_quote_items (quote_id, product_id, variant_id, quantity, unit_price, line_total, customisation_note)
                 VALUES (:quote_id, :product_id, :variant_id, :quantity, :unit_price, :line_total, :customisation_note)'
            );

            foreach ($normalizedItems as $item) {
                $itemStmt->execute([
                    'quote_id' => $quoteId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'customisation_note' => $item['customisation_note'],
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to create quote request'], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Quote request submitted',
            'data' => [
                'quote_number' => $quoteNumber,
            ],
        ], 201);
    }

    public function b2bQuoteRequest(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $input = $this->readJsonInput();

        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $eventType = trim((string)($input['event_type'] ?? ''));
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];

        if ($name === '' || $email === '' || $phone === '' || count($items) === 0) {
            Response::json(['success' => false, 'message' => 'Quote requires contact details and at least one item'], 422);
            return;
        }

        $message = json_encode([
            'event_type' => $eventType,
            'fulfilment_mode' => $input['fulfilment_mode'] ?? 'delivery',
            'scheduled_date' => $input['scheduled_date'] ?? null,
            'items' => $items,
        ], JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare(
            'INSERT INTO inquiries (inquiry_type, name, email, phone, message)
             VALUES ("quote_request", :name, :email, :phone, :message)'
        );
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
        ]);

        Response::json(['success' => true, 'message' => 'Quote request submitted'], 201);
    }

    public function adminDashboardSummary(): void
    {
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0) {
            Response::json(['success' => false, 'message' => 'Admin authentication required'], 401);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $revenueToday = (float)$pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE payment_status = "paid" AND DATE(created_at) = CURDATE()')->fetchColumn();
        $revenueMonth = (float)$pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE payment_status = "paid" AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())')->fetchColumn();
        $revenueYear = (float)$pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE payment_status = "paid" AND YEAR(created_at) = YEAR(CURDATE())')->fetchColumn();

        $monthlySeriesStmt = $pdo->query(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS bucket, COALESCE(SUM(grand_total),0) AS total
             FROM orders
             WHERE payment_status = "paid" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT(created_at, "%Y-%m")
             ORDER BY bucket ASC'
        );
        $monthlySeries = $monthlySeriesStmt instanceof \PDOStatement ? $monthlySeriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $yearlySeriesStmt = $pdo->query(
            'SELECT DATE_FORMAT(created_at, "%Y") AS bucket, COALESCE(SUM(grand_total),0) AS total
             FROM orders
             WHERE payment_status = "paid" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 4 YEAR)
             GROUP BY DATE_FORMAT(created_at, "%Y")
             ORDER BY bucket ASC'
        );
        $yearlySeries = $yearlySeriesStmt instanceof \PDOStatement ? $yearlySeriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $summary = [
            'orders_total' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'revenue_total' => (float)$pdo->query('SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE payment_status = "paid"')->fetchColumn(),
            'revenue_today' => $revenueToday,
            'revenue_month' => $revenueMonth,
            'revenue_year' => $revenueYear,
            'customers_total' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn(),
            'products_total' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL')->fetchColumn(),
            'b2b_accounts_total' => (int)$pdo->query('SELECT COUNT(*) FROM b2b_accounts')->fetchColumn(),
            'pending_quotes' => (int)$pdo->query('SELECT COUNT(*) FROM inquiries WHERE inquiry_type = "quote_request" AND status = "new"')->fetchColumn(),
            'monthly_series' => array_map(static function (array $row): array {
                return [
                    'label' => (string)($row['bucket'] ?? ''),
                    'value' => (float)($row['total'] ?? 0),
                ];
            }, $monthlySeries),
            'yearly_series' => array_map(static function (array $row): array {
                return [
                    'label' => (string)($row['bucket'] ?? ''),
                    'value' => (float)($row['total'] ?? 0),
                ];
            }, $yearlySeries),
        ];

        Response::json(['success' => true, 'message' => 'ok', 'data' => $summary]);
    }

    /** @return array<string, mixed> */
    private function readJsonInput(): array
    {
        return Request::json();
    }

    /** @param array<string,mixed> $context */
    private function queueEmailCommunicationLog(PDO $pdo, string $recipient, string $eventKey, array $context): void
    {
        $recipient = trim($recipient);
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $logStmt = $pdo->prepare('INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json) VALUES ("email", :event_key, :recipient, "queued", :payload_json)');
        $logStmt->execute([
            'event_key' => $eventKey,
            'recipient' => $recipient,
            'payload_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
        ]);
        $logId = (int)$pdo->lastInsertId();

        $queueStmt = $pdo->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:communication_log_id, "email", :payload_json)');
        $queueStmt->execute([
            'communication_log_id' => $logId,
            'payload_json' => json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES),
        ]);

        $jobStmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", :payload_json, "queued", NOW(), 0)');
        $jobStmt->execute([
            'payload_json' => json_encode([
                'log_id' => $logId,
                'channel' => 'email',
                'event_key' => $eventKey,
                'recipient' => $recipient,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array{to:string,cc:array<int,string>} */
    private function loadAdminCommunicationRecipients(PDO $pdo): array
    {
        $fallbackEmail = 'cakeouflage@gmail.com';
        $toEmail = '';
        $ccIdsRaw = '';

        $settingsStmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("communication_admin_to_email", "communication_admin_cc_admin_ids")');
        $settingsStmt->execute();
        $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settingsRows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            $value = trim((string)($row['setting_value'] ?? ''));
            if ($key === 'communication_admin_to_email') {
                $toEmail = $value;
            } elseif ($key === 'communication_admin_cc_admin_ids') {
                $ccIdsRaw = $value;
            }
        }

        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $toEmail = $fallbackEmail;
        }

        $ccIds = array_values(array_unique(array_filter(array_map(static function (string $value): int {
            return (int)$value;
        }, preg_split('/\s*,\s*/', $ccIdsRaw) ?: []), static function (int $id): bool {
            return $id > 0;
        })));

        if (count($ccIds) === 0) {
            return ['to' => $toEmail, 'cc' => []];
        }

        $placeholders = implode(',', array_fill(0, count($ccIds), '?'));
        $ccStmt = $pdo->prepare('SELECT email FROM admins WHERE is_active = 1 AND id IN (' . $placeholders . ') ORDER BY id ASC');
        $ccStmt->execute($ccIds);
        $ccRows = $ccStmt->fetchAll(PDO::FETCH_ASSOC);
        $ccEmails = [];
        foreach ($ccRows as $row) {
            $email = strtolower(trim((string)($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $email === strtolower($toEmail)) {
                continue;
            }
            $ccEmails[] = $email;
        }

        return ['to' => $toEmail, 'cc' => array_values(array_unique($ccEmails))];
    }

    /** @return array<string, mixed>|null */
    private function getAuthenticatedB2bAccount(PDO $pdo, bool $requireApproved = false): ?array
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = (string)($_SESSION['user_role'] ?? '');
        if ($userId <= 0 || $role !== 'b2b_user') {
            Response::json(['success' => false, 'message' => 'B2B authentication required'], 401);
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT
                b.*,
                u.full_name,
                u.email,
                u.phone,
                u.id AS user_id
             FROM b2b_accounts b
             JOIN users u ON u.id = b.user_id
             WHERE b.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            Response::json(['success' => false, 'message' => 'B2B account not found'], 404);
            return null;
        }

        if ($requireApproved && (string)$account['approval_status'] !== 'approved') {
            Response::json([
                'success' => false,
                'message' => 'B2B account is not approved.',
                'data' => ['approval_status' => (string)$account['approval_status']],
            ], 403);
            return null;
        }

        return $account;
    }

    private function buildRateLimitBucket(string $scope, string $identifier): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return strtolower($scope . '|' . trim($identifier) . '|' . $ip);
    }

    private function getOrCreateCartId(PDO $pdo): int
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $sessionId = session_id();

    // 🔥 If user logged in → ALWAYS use user cart
    if ($userId > 0) {

        // check user cart
        $stmt = $pdo->prepare("
            SELECT id FROM carts 
            WHERE user_id = :user_id 
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $userCartId = (int)($stmt->fetchColumn() ?: 0);

        if ($userCartId > 0) {
            return $userCartId;
        }

        // create user cart
        $pdo->prepare("
            INSERT INTO carts (user_id, session_id) 
            VALUES (:user_id, :session_id)
        ")->execute([
            'user_id' => $userId,
            'session_id' => $sessionId
        ]);

        return (int)$pdo->lastInsertId();
    }

    // 🔥 guest cart
    $stmt = $pdo->prepare("
        SELECT id FROM carts 
        WHERE session_id = :session_id 
        LIMIT 1
    ");
    $stmt->execute(['session_id' => $sessionId]);
    $cartId = (int)($stmt->fetchColumn() ?: 0);

    if ($cartId > 0) {
        return $cartId;
    }

    // create guest cart
    $pdo->prepare("
        INSERT INTO carts (session_id) 
        VALUES (:session_id)
    ")->execute(['session_id' => $sessionId]);

    return (int)$pdo->lastInsertId();
}

    /** @return array<string, mixed>|null */
    private function getAuthenticatedCustomer(PDO $pdo): ?array
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $role = (string)($_SESSION['user_role'] ?? '');
        $otpVerified = !empty($_SESSION['otp_verified']);
        if ($userId <= 0 || $role !== 'customer' || !$otpVerified) {
            Response::json(['success' => false, 'message' => 'Customer authentication required'], 401);
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'message' => 'User not found'], 404);
            return null;
        }

        return $user;
    }

    private function getOrCreateWishlistId(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $existing = (int)($stmt->fetchColumn() ?: 0);
        if ($existing > 0) {
            return $existing;
        }

        $insert = $pdo->prepare('INSERT INTO wishlists (user_id) VALUES (:user_id)');
        $insert->execute(['user_id' => $userId]);
        return (int)$pdo->lastInsertId();
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>|null
     */
    private function normalizeAddressInput(array $input): ?array
    {
        $recipientName = trim((string)($input['recipient_name'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $line1 = trim((string)($input['line1'] ?? ''));
        $city = trim((string)($input['city'] ?? ''));
        $state = trim((string)($input['state'] ?? ''));
        $postalCode = trim((string)($input['postal_code'] ?? ''));

        if ($recipientName === '' || $phone === '' || $line1 === '' || $city === '' || $state === '' || $postalCode === '') {
            return null;
        }

        return [
            'label' => trim((string)($input['label'] ?? '')) ?: null,
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'line1' => $line1,
            'line2' => trim((string)($input['line2'] ?? '')) ?: null,
            'landmark' => trim((string)($input['landmark'] ?? '')) ?: null,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
            'is_default' => (int)((int)($input['is_default'] ?? 0) === 1),
        ];
    }

    private function ensureCouponSchema(PDO $pdo): void
    {
        if (self::$couponSchemaEnsured) {
            return;
        }

        try {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN per_user_usage_limit INT NULL AFTER usage_limit');
        } catch (Throwable $e) {
            // no-op: column likely already exists
        }
        try {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN target_mode ENUM("all_users","specific_users") NOT NULL DEFAULT "all_users" AFTER ends_at');
        } catch (Throwable $e) {
            // no-op: column likely already exists
        }
        try {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        } catch (Throwable $e) {
            // no-op: column likely already exists
        }
        try {
            $pdo->exec('ALTER TABLE coupons ADD COLUMN deleted_at DATETIME NULL AFTER is_deleted');
        } catch (Throwable $e) {
            // no-op: column likely already exists
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS coupon_target_users (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            coupon_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_coupon_target_user (coupon_id, user_id),
            INDEX idx_coupon_target_user (user_id),
            CONSTRAINT fk_coupon_target_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            CONSTRAINT fk_coupon_target_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');

        $pdo->exec('CREATE TABLE IF NOT EXISTS coupon_redemptions (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            coupon_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            code_snapshot VARCHAR(50) NOT NULL,
            discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_coupon_order (coupon_id, order_id),
            INDEX idx_coupon_redemption_user (coupon_id, user_id),
            CONSTRAINT fk_coupon_redemption_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            CONSTRAINT fk_coupon_redemption_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_coupon_redemption_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        self::$couponSchemaEnsured = true;
    }

    /**
     * Ensure the cake_toppers table, topper/note columns on products, cart_items,
     * and order_items all exist. Safe to call even if columns already exist.
     */
    private function ensureTopperSchema(PDO $pdo): void
    {
        if (self::$topperSchemaEnsured) {
            return;
        }

        // Core toppers lookup table
        $pdo->exec('CREATE TABLE IF NOT EXISTS cake_toppers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            description VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_topper_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        // Default toppers (INSERT IGNORE respects the unique key on name)
        $pdo->exec("INSERT IGNORE INTO cake_toppers (name, price, sort_order) VALUES
            ('No Topper', 0.00, 1),
            ('Happy Birthday', 0.00, 2),
            ('Happy Anniversary', 0.00, 3),
            ('Happy Wedding', 0.00, 4),
            ('Baby Shower', 0.00, 5),
            ('Custom Message', 0.00, 6)");

        // products flags
        try {
            if (!self::columnExists($pdo, 'products', 'topper_enabled')) {
                $pdo->exec('ALTER TABLE products ADD COLUMN topper_enabled TINYINT(1) NOT NULL DEFAULT 1');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'products', 'note_enabled')) {
                $pdo->exec('ALTER TABLE products ADD COLUMN note_enabled TINYINT(1) NOT NULL DEFAULT 1');
            }
        } catch (\Throwable $e) {}

        // cart_items topper fields
        try {
            if (!self::columnExists($pdo, 'cart_items', 'cake_message')) {
                $pdo->exec('ALTER TABLE cart_items ADD COLUMN cake_message VARCHAR(200) NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'cart_items', 'topper_id')) {
                $pdo->exec('ALTER TABLE cart_items ADD COLUMN topper_id INT UNSIGNED NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'cart_items', 'topper_name_snapshot')) {
                $pdo->exec('ALTER TABLE cart_items ADD COLUMN topper_name_snapshot VARCHAR(100) NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'cart_items', 'topper_price')) {
                $pdo->exec('ALTER TABLE cart_items ADD COLUMN topper_price DECIMAL(8,2) NOT NULL DEFAULT 0.00');
            }
        } catch (\Throwable $e) {}

        // order_items topper fields
        try {
            if (!self::columnExists($pdo, 'order_items', 'cake_message')) {
                $pdo->exec('ALTER TABLE order_items ADD COLUMN cake_message VARCHAR(200) NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'order_items', 'topper_id')) {
                $pdo->exec('ALTER TABLE order_items ADD COLUMN topper_id INT UNSIGNED NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'order_items', 'topper_name_snapshot')) {
                $pdo->exec('ALTER TABLE order_items ADD COLUMN topper_name_snapshot VARCHAR(100) NULL');
            }
        } catch (\Throwable $e) {}
        try {
            if (!self::columnExists($pdo, 'order_items', 'topper_price_snapshot')) {
                $pdo->exec('ALTER TABLE order_items ADD COLUMN topper_price_snapshot DECIMAL(8,2) NOT NULL DEFAULT 0.00');
            }
        } catch (\Throwable $e) {}

        self::$topperSchemaEnsured = true;
    }

    private function ensureBankAlertSchema(PDO $pdo): void
    {
        if (self::$bankAlertSchemaEnsured) {
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS bank_alert_utrs (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            source ENUM("apps_script","customer_submit","admin_manual") NOT NULL DEFAULT "apps_script",
            parsed_utr VARCHAR(40) NOT NULL,
            parsed_amount DECIMAL(12,2) NULL,
            bank_sender VARCHAR(190) NULL,
            email_subject VARCHAR(255) NULL,
            alert_message TEXT NULL,
            event_time DATETIME NULL,
            status ENUM("pending","matched_auto","confirmed","rejected","duplicate","ignored") NOT NULL DEFAULT "pending",
            match_confidence ENUM("none","weak","strong") NOT NULL DEFAULT "none",
            customer_user_id BIGINT UNSIGNED NULL,
            order_id BIGINT UNSIGNED NULL,
            invoice_id BIGINT UNSIGNED NULL,
            payment_id BIGINT UNSIGNED NULL,
            confirm_note TEXT NULL,
            confirmed_by_admin_id BIGINT UNSIGNED NULL,
            confirmed_at DATETIME NULL,
            raw_payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bank_alert_utr (parsed_utr),
            INDEX idx_bank_alert_status (status),
            INDEX idx_bank_alert_order (order_id),
            INDEX idx_bank_alert_created (created_at),
            CONSTRAINT fk_bank_alert_user FOREIGN KEY (customer_user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_bank_alert_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            CONSTRAINT fk_bank_alert_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
            CONSTRAINT fk_bank_alert_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
            CONSTRAINT fk_bank_alert_admin FOREIGN KEY (confirmed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        self::$bankAlertSchemaEnsured = true;
    }

    private function getSettingValue(PDO $pdo, string $key): string
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : '';
    }

    private function normalizeUtr(string $value): ?string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($value)) ?? '');
        if ($normalized === '' || strlen($normalized) < 6 || strlen($normalized) > 40) {
            return null;
        }

        return $normalized;
    }

    private function normalizeOptionalDateValue(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $trimmed);
        if (!$date || $date->format('Y-m-d') !== $trimmed) {
            return null;
        }

        return $trimmed;
    }

    private function ensureOrderLifecycleSchema(PDO $pdo): void
    {
        if (self::$orderLifecycleSchemaEnsured) {
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS order_lifecycle_events (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            stage_key VARCHAR(80) NOT NULL,
            previous_stage VARCHAR(80) NULL,
            payment_status VARCHAR(40) NULL,
            actor_type ENUM("system","customer","admin") NOT NULL DEFAULT "system",
            actor_id BIGINT UNSIGNED NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_lifecycle_order (order_id, created_at),
            INDEX idx_order_lifecycle_stage (stage_key),
            CONSTRAINT fk_order_lifecycle_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        self::$orderLifecycleSchemaEnsured = true;
    }

    private function logOrderLifecycle(PDO $pdo, int $orderId, string $stageKey, ?string $previousStage, ?string $paymentStatus, string $actorType, ?int $actorId, ?string $note = null): void
    {
        $this->ensureOrderLifecycleSchema($pdo);
        $stmt = $pdo->prepare('INSERT INTO order_lifecycle_events (order_id, stage_key, previous_stage, payment_status, actor_type, actor_id, note) VALUES (:order_id, :stage_key, :previous_stage, :payment_status, :actor_type, :actor_id, :note)');
        $stmt->execute([
            'order_id' => $orderId,
            'stage_key' => $stageKey,
            'previous_stage' => $this->nullableString($previousStage),
            'payment_status' => $this->nullableString($paymentStatus),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'note' => $this->nullableString($note),
        ]);
    }

    /**
     * @return array{ok:bool,message?:string,items?:array<int,array<string,mixed>>,subtotal?:float,item_count?:int,discount_total?:float,coupon?:array<string,mixed>|null}
     */
    private function validateCheckoutCart(PDO $pdo, int $cartId, int $userId, bool $forUpdate = false): array
    {
        $this->ensureTopperSchema($pdo);

        $sql = 'SELECT
            ci.id,
            ci.product_id,
            ci.variant_id,
            ci.quantity,
            ci.unit_price,
            ci.line_total,
            ci.cake_message,
            ci.topper_id,
            ci.topper_name_snapshot,
            ci.topper_price,
            p.id AS p_id,
            p.name AS product_name,
            p.availability_status,
            p.deleted_at,
            p.stock_quantity AS product_stock_quantity,
            p.topper_enabled,
            p.note_enabled,
            pv.id AS pv_id,
            pv.product_id AS variant_product_id,
            pv.variant_label,
            pv.price AS variant_price,
            pv.discount_price AS variant_discount_price,
            pv.stock_quantity AS variant_stock_quantity,
            pv.is_active AS variant_is_active,
            ct.id AS topper_exists,
            ct.name AS topper_name,
            ct.price AS topper_current_price,
            ct.is_active AS topper_is_active
        FROM cart_items ci
        LEFT JOIN products p ON p.id = ci.product_id
        LEFT JOIN product_variants pv ON pv.id = ci.variant_id
        LEFT JOIN cake_toppers ct ON ct.id = ci.topper_id
        WHERE ci.cart_id = :cart_id
        ORDER BY ci.id ASC';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['cart_id' => $cartId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return ['ok' => false, 'message' => 'Cart is empty'];
        }

        $sanitizedItems = [];
        $subtotal = 0.0;
        $itemCount = 0;
        $errors = [];

        foreach ($rows as $row) {
            $cartItemId = (int)($row['id'] ?? 0);
            $quantity = (int)($row['quantity'] ?? 0);
            $availability = (string)($row['availability_status'] ?? '');
            $variantActive = (int)($row['variant_is_active'] ?? 0) === 1;
            $variantProductId = (int)($row['variant_product_id'] ?? 0);
            $productId = (int)($row['product_id'] ?? 0);
            $isPreorder = $availability === 'preorder';

            if ((int)($row['p_id'] ?? 0) <= 0 || (string)($row['deleted_at'] ?? '') !== '') {
                $errors[] = 'Cart item #' . $cartItemId . ' has an invalid or deleted product';
                continue;
            }

            if (!in_array($availability, ['in_stock', 'preorder'], true)) {
                $errors[] = 'Cart item #' . $cartItemId . ' product is inactive for checkout';
                continue;
            }

            if ((int)($row['pv_id'] ?? 0) <= 0 || !$variantActive || $variantProductId !== $productId) {
                $errors[] = 'Cart item #' . $cartItemId . ' has an invalid variant';
                continue;
            }

            if ($quantity <= 0) {
                $errors[] = 'Cart item #' . $cartItemId . ' has invalid quantity';
                continue;
            }

            $variantStock = (int)($row['variant_stock_quantity'] ?? 0);
            if (!$isPreorder && ($variantStock <= 0 || $quantity > $variantStock)) {
                $errors[] = 'Cart item #' . $cartItemId . ' exceeds available variant inventory';
                continue;
            }

            $productStock = (int)($row['product_stock_quantity'] ?? 0);
            if (!$isPreorder && $productStock > 0 && $quantity > $productStock) {
                $errors[] = 'Cart item #' . $cartItemId . ' exceeds available product inventory';
                continue;
            }

            $topperId = isset($row['topper_id']) ? (int)$row['topper_id'] : 0;
            $topperEnabled = (int)($row['topper_enabled'] ?? 1) === 1;
            $noteEnabled = (int)($row['note_enabled'] ?? 1) === 1;
            $cakeMessage = trim((string)($row['cake_message'] ?? ''));

            if ($cakeMessage !== '' && !$noteEnabled) {
                $errors[] = 'Cart item #' . $cartItemId . ' has a disabled cake note option';
                continue;
            }

            $canonicalTopperPrice = 0.0;
            $topperNameSnapshot = null;
            if ($topperId > 0) {
                if (!$topperEnabled) {
                    $errors[] = 'Cart item #' . $cartItemId . ' topper is not allowed for this product';
                    continue;
                }
                if ((int)($row['topper_exists'] ?? 0) <= 0 || (int)($row['topper_is_active'] ?? 0) !== 1) {
                    $errors[] = 'Cart item #' . $cartItemId . ' has an invalid topper';
                    continue;
                }
                $canonicalTopperPrice = round((float)($row['topper_current_price'] ?? 0), 2);
                $topperNameSnapshot = (string)($row['topper_name'] ?? '');
            }

            $canonicalUnitPrice = round(((float)($row['variant_discount_price'] ?? 0) > 0)
                ? (float)$row['variant_discount_price']
                : (float)($row['variant_price'] ?? 0), 2);

            $storedUnitPrice = round((float)($row['unit_price'] ?? 0), 2);
            $storedTopperPrice = round((float)($row['topper_price'] ?? 0), 2);
            $storedLineTotal = round((float)($row['line_total'] ?? 0), 2);
            $expectedLineTotal = round(($canonicalUnitPrice + $canonicalTopperPrice) * $quantity, 2);

            if (abs($storedUnitPrice - $canonicalUnitPrice) > 0.01
                || abs($storedTopperPrice - $canonicalTopperPrice) > 0.01
                || abs($storedLineTotal - $expectedLineTotal) > 0.01) {
                $errors[] = 'Cart item #' . $cartItemId . ' failed price integrity checks';
                continue;
            }

            $sanitized = [
                'cart_item_id' => $cartItemId,
                'product_id' => $productId,
                'variant_id' => (int)$row['variant_id'],
                'product_name' => (string)($row['product_name'] ?? ''),
                'variant_label' => (string)($row['variant_label'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $canonicalUnitPrice,
                'line_total' => $expectedLineTotal,
                'cake_message' => $cakeMessage !== '' ? $cakeMessage : null,
                'topper_id' => $topperId > 0 ? $topperId : null,
                'topper_name_snapshot' => $topperId > 0 ? $topperNameSnapshot : null,
                'topper_price' => $canonicalTopperPrice,
            ];

            $sanitizedItems[] = $sanitized;
            $subtotal += $expectedLineTotal;
            $itemCount += $quantity;
        }

        if ($errors) {
            return [
                'ok' => false,
                'message' => 'Checkout blocked due to invalid cart state: ' . implode('; ', array_slice($errors, 0, 3)),
            ];
        }

        $discountTotal = 0.0;
        $couponData = null;
        $couponSession = $_SESSION['applied_coupon'] ?? null;
        if (is_array($couponSession) && isset($couponSession['id'])) {
            $this->ensureCouponSchema($pdo);
            $couponStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
            $couponStmt->execute(['id' => (int)$couponSession['id']]);
            $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);

            if ($coupon) {
                $validationError = $this->couponValidationError($pdo, $coupon, $userId);
                if ($validationError === null) {
                    $discountTotal = $this->calculateDiscountForCoupon($coupon, $subtotal);
                    if ($discountTotal > 0.0) {
                        $couponData = [
                            'id' => (int)$coupon['id'],
                            'code' => (string)$coupon['code'],
                            'discount_type' => (string)$coupon['discount_type'],
                            'discount_value' => (float)$coupon['discount_value'],
                            'auto_applied' => (bool)($couponSession['auto_applied'] ?? false),
                        ];
                    }
                }
            }
        }

        return [
            'ok' => true,
            'items' => $sanitizedItems,
            'subtotal' => round($subtotal, 2),
            'item_count' => $itemCount,
            'discount_total' => round($discountTotal, 2),
            'coupon' => $couponData,
        ];
    }

    private function persistPaymentProof(array $file, string $orderNumber): string
    {
        $fileError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Payment proof upload failed');
        }

        $maxBytes = 5 * 1024 * 1024;
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new \RuntimeException('Payment proof must be an image up to 5 MB');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new \RuntimeException('Payment proof payload is invalid');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Payment proof must be JPG, PNG, or WEBP');
        }

        $upload = UnifiedMediaService::upload($file, [
            'module' => 'byoc',
            'entity_type' => 'payment_proof',
            'entity_id' => 0,
            'admin_id' => 0,
            'allow_svg' => false,
            'max_bytes' => $maxBytes,
        ]);
        if (!$upload['ok']) {
            throw new \RuntimeException('Unable to store payment proof: ' . $upload['error']);
        }

        return (string)$upload['relative_url'];
    }

    private function couponValidationError(PDO $pdo, array $coupon, int $userId): ?string
    {
        if ((int)($coupon['is_active'] ?? 0) !== 1 || (int)($coupon['is_deleted'] ?? 0) === 1) {
            return 'Coupon is inactive';
        }

        $now = time();
        if (!empty($coupon['starts_at']) && strtotime((string)$coupon['starts_at']) > $now) {
            return 'Coupon is not active yet';
        }
        if (empty($coupon['ends_at']) || strtotime((string)$coupon['ends_at']) < $now) {
            return 'Coupon has expired';
        }

        $usageLimit = $coupon['usage_limit'] !== null ? (int)$coupon['usage_limit'] : null;
        $usageCount = (int)($coupon['usage_count'] ?? 0);
        if ($usageLimit !== null && $usageCount >= $usageLimit) {
            return 'Coupon usage limit reached';
        }

        $targetMode = (string)($coupon['target_mode'] ?? 'all_users');
        if ($targetMode === 'specific_users') {
            if ($userId <= 0) {
                return 'Please login to use this coupon';
            }
            $targetStmt = $pdo->prepare('SELECT 1 FROM coupon_target_users WHERE coupon_id = :coupon_id AND user_id = :user_id LIMIT 1');
            $targetStmt->execute(['coupon_id' => (int)$coupon['id'], 'user_id' => $userId]);
            if (!$targetStmt->fetchColumn()) {
                return 'Coupon is not available for this account';
            }
        }

        $perUserLimit = $coupon['per_user_usage_limit'] !== null ? (int)$coupon['per_user_usage_limit'] : null;
        if ($perUserLimit !== null) {
            if ($userId <= 0) {
                return 'Please login to use this coupon';
            }
            $userCountStmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = :coupon_id AND user_id = :user_id');
            $userCountStmt->execute(['coupon_id' => (int)$coupon['id'], 'user_id' => $userId]);
            $userUsageCount = (int)$userCountStmt->fetchColumn();
            if ($userUsageCount >= $perUserLimit) {
                return 'Per-user coupon limit reached';
            }
        }

        return null;
    }

    private function maybeAutoApplyBestPublicCoupon(PDO $pdo, int $cartId): void
    {
        if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
            return;
        }
        if (is_array($_SESSION['applied_coupon'] ?? null)) {
            return;
        }
        if ((int)($_SESSION['coupon_auto_opt_out'] ?? 0) === 1) {
            return;
        }

        $this->ensureCouponSchema($pdo);

        $subtotalStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) FROM cart_items WHERE cart_id = :cart_id');
        $subtotalStmt->execute(['cart_id' => $cartId]);
        $subtotal = (float)$subtotalStmt->fetchColumn();
        if ($subtotal <= 0.0) {
            return;
        }

        $couponStmt = $pdo->prepare(
            "SELECT * FROM coupons
             WHERE is_active = 1
               AND is_deleted = 0
               AND target_mode = 'all_users'
               AND auto_apply = 1
               AND FIND_IN_SET('online', applicable_to)
               AND (starts_at IS NULL OR starts_at <= NOW())
             AND (ends_at IS NULL OR ends_at >= NOW())"
        );
        $couponStmt->execute();
        $coupons = $couponStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$coupons) {
            return;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        $bestCoupon = null;
        $bestDiscount = 0.0;
        foreach ($coupons as $coupon) {
            if ($this->couponValidationError($pdo, $coupon, $userId) !== null) {
                continue;
            }

            $discountTotal = $this->calculateDiscountForCoupon($coupon, $subtotal);
            if ($discountTotal <= 0.0) {
                continue;
            }

            if ($discountTotal > $bestDiscount) {
                $bestDiscount = $discountTotal;
                $bestCoupon = $coupon;
            }
        }

        if (is_array($bestCoupon)) {
            $_SESSION['applied_coupon'] = [
                'id' => (int)$bestCoupon['id'],
                'code' => (string)$bestCoupon['code'],
                'auto_applied' => true,
            ];
        }
    }

    private function calculateDiscountForCoupon(array $coupon, float $subtotal): float
    {
        $minOrder = $coupon['min_order_amount'] !== null ? (float)$coupon['min_order_amount'] : 0.0;
        if ($subtotal < $minOrder) {
            return 0.0;
        }

        if ((string)$coupon['discount_type'] === 'percentage') {
            $discountTotal = round($subtotal * ((float)$coupon['discount_value'] / 100), 2);
            if ($coupon['max_discount'] !== null) {
                $discountTotal = min($discountTotal, (float)$coupon['max_discount']);
            }
            return max(0.0, $discountTotal);
        }

        return max(0.0, min($subtotal, (float)$coupon['discount_value']));
    }

    /** @return array<string, mixed> */
    private function buildCartResponse(PDO $pdo, int $cartId): array
    {
        $this->ensureTopperSchema($pdo);

        $stmt = $pdo->prepare(
            'SELECT
                ci.id,
                ci.product_id,
                ci.variant_id,
                ci.quantity,
                ci.unit_price,
                ci.line_total,
                ci.cake_message,
                ci.topper_id,
                ci.topper_name_snapshot,
                ci.topper_price,
                p.name AS product_name,
                p.slug AS product_slug,
                p.featured_image,
                pv.variant_label
             FROM cart_items ci
             LEFT JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_variants pv ON pv.id = ci.variant_id
             WHERE ci.cart_id = :cart_id
             ORDER BY ci.created_at DESC'
        );
        $stmt->execute(['cart_id' => $cartId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subtotal = 0.0;
        $itemCount = 0;
        foreach ($items as $item) {
            $subtotal += (float)$item['line_total'];
            $itemCount += (int)$item['quantity'];
        }
    // Cart summary remains pre-fulfilment; delivery fee is computed in checkout preview.
    $deliveryFee = 0;
        $discountTotal = 0.0;
        $couponData = null;
        $couponSession = $_SESSION['applied_coupon'] ?? null;
        if (is_array($couponSession) && isset($couponSession['id'])) {
            $this->ensureCouponSchema($pdo);
            $couponStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
            $couponStmt->execute(['id' => (int)$couponSession['id']]);
            $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($coupon) {
                $validationError = $this->couponValidationError($pdo, $coupon, $userId);
                if ($validationError === null) {
                    $discountTotal = $this->calculateDiscountForCoupon($coupon, $subtotal);
                    if ($discountTotal > 0.0) {
                        $couponData = [
                            'code' => (string)$coupon['code'],
                            'discount_type' => (string)$coupon['discount_type'],
                            'discount_value' => (float)$coupon['discount_value'],
                            'auto_applied' => (bool)($couponSession['auto_applied'] ?? false),
                        ];
                    }
                } else {
                    unset($_SESSION['applied_coupon']);
                }
            } else {
                unset($_SESSION['applied_coupon']);
            }
        }

        $grandTotal = round(max(0.0, $subtotal - $discountTotal + $deliveryFee), 2);

        return [
            'cart_id' => $cartId,
            'item_count' => $itemCount,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'discount_total' => $discountTotal,
            'delivery_fee' => $deliveryFee,
            'grand_total' => $grandTotal,
            'coupon' => $couponData,
            
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function buildCheckoutPreview(PDO $pdo, int $cartId, array $input): array
    {
        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        $postalCode = trim((string)($input['postal_code'] ?? $input['delivery_pincode'] ?? ''));
        $cart = $this->buildCartResponse($pdo, $cartId);

        if ((int)$cart['item_count'] <= 0) {
            return [
                'ok' => false,
                'code' => 'cart_empty',
                'field' => 'cart',
                'message' => 'Cart is empty',
            ];
        }

        $deliveryFee = 0.0;
        $distanceKm = null;

        if ($fulfilmentMode === 'delivery' || $fulfilmentMode === 'custom_delivery') {
            if ($postalCode === '') {
                return [
                    'ok' => false,
                    'code' => 'postal_code_required',
                    'field' => 'postal_code',
                    'message' => 'postal_code is required for delivery',
                ];
            }

            $pinStmt = $pdo->prepare('SELECT * FROM delivery_pincodes WHERE postal_code = :postal_code LIMIT 1');
            $pinStmt->execute(['postal_code' => $postalCode]);
            $pin = $pinStmt->fetch(PDO::FETCH_ASSOC);
            if (!$pin || (int)$pin['is_serviceable'] !== 1) {
                return [
                    'ok' => false,
                    'code' => 'postal_code_not_serviceable',
                    'field' => 'postal_code',
                    'message' => 'Delivery unavailable for this pincode',
                    'details' => ['postal_code' => $postalCode],
                ];
            }

           $distanceKm = (float)$pin['approx_distance_km'];

$slabStmt = $pdo->prepare('
SELECT * FROM delivery_distance_slabs 
WHERE :distance_min >= min_km AND :distance_max <= max_km
LIMIT 1
');

$slabStmt->execute([
    'distance_min' => $distanceKm,
    'distance_max' => $distanceKm,
]);

$slab = $slabStmt->fetch(PDO::FETCH_ASSOC);

if (!$slab || (int)$slab['is_available'] !== 1) {
    return [
        'ok' => false,
        'code' => 'delivery_radius_exceeded',
        'field' => 'postal_code',
        'message' => 'Delivery outside service radius unless manually approved',
        'details' => ['distance_km' => $distanceKm, 'postal_code' => $postalCode],
    ];
}

$deliveryFee = (float)$slab['delivery_fee'];
        }

        return [
            'ok' => true,
            'delivery_fee' => $deliveryFee,
            'distance_km' => $distanceKm,
            'grand_total' => round((float)$cart['subtotal'] - (float)$cart['discount_total'] + $deliveryFee, 2),
        ];
    }

    private function generateOrderNumber(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('Ymd') . '-' . random_int(100000, 999999);
    }

    private function ensureByocQuoteSchema(PDO $pdo): void
    {
        if (self::$byocQuoteSchemaEnsured) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS byoc_quotes (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                inquiry_id BIGINT UNSIGNED NOT NULL,
                quote_number VARCHAR(50) NOT NULL UNIQUE,
                quote_subject VARCHAR(180) NOT NULL,
                quote_message TEXT NULL,
                quote_amount DECIMAL(10,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT "INR",
                status ENUM("sent","accepted","expired","cancelled") NOT NULL DEFAULT "sent",
                expires_at DATETIME NULL,
                accepted_at DATETIME NULL,
                order_id BIGINT UNSIGNED NULL,
                created_by_admin_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_byoc_quotes_inquiry (inquiry_id),
                INDEX idx_byoc_quotes_status (status),
                FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS byoc_quote_links (
                id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                byoc_quote_id BIGINT UNSIGNED NOT NULL,
                token VARCHAR(120) NOT NULL UNIQUE,
                expires_at DATETIME NULL,
                used_at DATETIME NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_byoc_quote_links_quote (byoc_quote_id)
            ) ENGINE=InnoDB'
        );

        $dbName = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
        if ($dbName !== '') {
            $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = "orders" AND column_name = "order_source"');
            $columnCheck->execute(['schema' => $dbName]);
            $hasOrderSource = (int)$columnCheck->fetchColumn() > 0;
            if (!$hasOrderSource) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN order_source ENUM("retail","byoc_quote") NOT NULL DEFAULT "retail" AFTER payment_method');
                $pdo->exec('CREATE INDEX idx_orders_source ON orders(order_source)');
            }

            $quoteRefCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = :schema AND table_name = "orders" AND column_name = "byoc_quote_id"');
            $quoteRefCheck->execute(['schema' => $dbName]);
            $hasByocQuoteRef = (int)$quoteRefCheck->fetchColumn() > 0;
            if (!$hasByocQuoteRef) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN byoc_quote_id BIGINT UNSIGNED NULL AFTER order_source');
                $pdo->exec('CREATE UNIQUE INDEX uq_orders_byoc_quote ON orders(byoc_quote_id)');
                $pdo->exec('ALTER TABLE orders ADD CONSTRAINT fk_orders_byoc_quote FOREIGN KEY (byoc_quote_id) REFERENCES byoc_quotes(id) ON DELETE SET NULL');
            }
        }

        self::$byocQuoteSchemaEnsured = true;
    }

    private function resolveByocFallbackProductId(PDO $pdo): int
    {
        $namedStmt = $pdo->query('SELECT id FROM products WHERE deleted_at IS NULL AND LOWER(name) LIKE "%custom%" ORDER BY id ASC LIMIT 1');
        $namedId = (int)($namedStmt ? $namedStmt->fetchColumn() : 0);
        if ($namedId > 0) {
            return $namedId;
        }

        $fallbackStmt = $pdo->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        return (int)($fallbackStmt ? $fallbackStmt->fetchColumn() : 0);
    }



 public function sendOtp(): void
{
    $input = $this->readJsonInput();
    if ($input === [] && $_POST !== []) {
        /** @var array<string, mixed> $input */
        $input = $_POST;
    }
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $customerName = trim((string)($input['name'] ?? 'Customer'));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::json([
            'success' => false,
            'message' => 'Valid email required',
        ], 422);
        return;
    }

    $pdo = self::db();
    if (!$pdo) {
        return;
    }

    $existingUserStmt = $pdo->prepare(
        'SELECT id, full_name, phone FROM users WHERE email = :email AND role = "customer" LIMIT 1'
    );
    $existingUserStmt->execute(['email' => $email]);
    $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    try {
        AuthManager::sendOtp($pdo, $email, $customerName);
    } catch (\Throwable $e) {
        $status = $e->getCode() === 429 ? 429 : 500;
        Response::json([
            'success' => false,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to send OTP email right now. Please try again shortly.',
        ], $status);
        return;
    }

    Response::json([
        'success' => true,
        'message' => 'OTP sent to email',
        'data' => [
            'existing_customer' => $existingUser !== null,
            'user' => $existingUser !== null ? [
                'id' => (int)($existingUser['id'] ?? 0),
                'full_name' => trim((string)($existingUser['full_name'] ?? '')),
                'phone' => trim((string)($existingUser['phone'] ?? '')),
            ] : null,
        ],
    ]);
}


public function verifyOtp(): void
{
    $input = $this->readJsonInput();
    if ($input === [] && $_POST !== []) {
        /** @var array<string, mixed> $input */
        $input = $_POST;
    }

    $email = strtolower(trim((string)($input['email'] ?? '')));
    $otp = preg_replace('/\D+/', '', (string)($input['otp'] ?? '')) ?? '';
    $name = trim((string)($input['name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $rememberDevice = !empty($input['remember_device'])
        && in_array(strtolower((string)$input['remember_device']), ['1', 'true', 'yes', 'on'], true);

    if ($email === '' || $otp === '' || strlen($otp) !== 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::json([
            "success" => false,
            "message" => "Valid email and 6-digit OTP required"
        ], 422);
        return;
    }

    $pdo = self::db();
    if (!$pdo) return;

    try {
        AuthManager::validateOtp($pdo, $email, $otp, 'customer');
        AuthManager::establishCustomerSession($pdo, $email, $name, $phone, $rememberDevice);
    } catch (\Throwable $e) {
        $status = ($e->getCode() === 429) ? 429 : (($e->getCode() === 401) ? 401 : 500);
        Response::json([
            'success' => false,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to verify OTP right now.',
        ], $status);
        return;
    }

    Response::json([
        "success" => true,
        "message" => "Email OTP verified",
        "data" => [
            "redirect_to" => "/account/dashboard.php",
            "remember_device" => $rememberDevice,
        ],
    ]);
}

    private function refreshSessionCookie(bool $rememberDevice): void
    {
        if (!ini_get('session.use_cookies')) {
            return;
        }

        $params = session_get_cookie_params();
        $defaultLifetime = (int)($params['lifetime'] ?? 0);
        $lifetime = $rememberDevice ? (60 * 60 * 24 * 30) : $defaultLifetime;
        $expires = $lifetime > 0 ? (time() + $lifetime) : 0;

        setcookie(session_name(), session_id(), [
            'expires' => $expires,
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => (string)($params['samesite'] ?? 'Lax'),
        ]);
    }

    /** @param array<string, mixed> $params */
    private function expireSessionCookie(array $params): void
    {
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => (string)($params['samesite'] ?? 'Lax'),
            ]);
            return;
        }

        setcookie(
            session_name(),
            '',
            time() - 42000,
            (string)($params['path'] ?? '/'),
            (string)($params['domain'] ?? ''),
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }

    private function clearCustomerSessionState(): void
    {
        AuthManager::logoutCustomer();
    }




public function mediaUpload(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
        Response::json([
            'success' => false,
            'message' => 'No file received or upload error'
        ], 400);
        return;
    }

    $file = $_FILES['file'];

    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'video/mp4'
    ];

  $mimeType = $_FILES['file']['type'] ?? '';

    if (!in_array($mimeType, $allowedTypes)) {
        Response::json([
            'success' => false,
            'message' => 'Invalid file type: ' . $mimeType
        ], 400);
        return;
    }

    if (str_starts_with($mimeType, 'image/')) {
        $upload = UnifiedMediaService::upload($file, [
            'module' => 'template_editor',
            'entity_type' => 'public_media_upload',
            'entity_id' => 0,
            'admin_id' => 0,
            'allow_svg' => true,
            'max_bytes' => 10 * 1024 * 1024,
        ]);
        if (!$upload['ok']) {
            Response::json([
                'success' => false,
                'message' => $upload['error']
            ], 500);
            return;
        }

        Response::json([
            'success' => true,
            'data' => [
                'url' => (string)$upload['relative_url'],
                'optimized_url' => (string)$upload['optimized_url'],
                'queue_id' => (int)$upload['queue_id']
            ]
        ]);
        return;
    }

    $uploadDir = __DIR__ . '/../../public/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        Response::json([
            'success' => false,
            'message' => 'Failed to move file'
        ], 500);
        return;
    }

    Response::json([
        'success' => true,
        'data' => [
            'url' => '/uploads/' . $fileName
        ]
    ]);

}
}

