<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Services\MailService;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthRateLimitService;
use App\Services\ByocQuoteExpiryService;
use App\Services\OrderAutomationService;
use App\Services\PasswordResetService;
use App\Services\ProductImageService;
use PDO;
use Throwable;
//session_start();
final class ApiController
{
    private const DEFAULT_PAGE_SIZE = 24;
    private static $couponSchemaEnsured = false;
    private static $bankAlertSchemaEnsured = false;
    private static $byocQuoteSchemaEnsured = false;

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
            ['id' => 1, 'name' => 'Cakes', 'slug' => 'cakes', 'category_type' => null],
            ['id' => 4, 'name' => 'Gifting', 'slug' => 'gifting', 'category_type' => null],
            ['id' => 7, 'name' => 'Desserts', 'slug' => 'desserts', 'category_type' => null],
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

    public function health(): void
    {
        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'app' => Env::get('BAKERY_NAME', 'Cakeouflage'),
                'timestamp' => date(DATE_ATOM),
            ],
        ]);
    }

    public function healthDb(): void
    {
        try {
            $pdo = Database::getConnection();
            $pdo->query('SELECT 1');
            $databaseName = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: Env::get('DB_NAME', 'unknown'));

            Response::json([
                'success' => true,
                'message' => 'database connected',
                'data' => [
                    'connected' => true,
                    'database' => $databaseName,
                    'timestamp' => date(DATE_ATOM),
                ],
            ]);
            return;
        } catch (Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'database connection failed',
                'data' => [
                    'connected' => false,
                    'timestamp' => date(DATE_ATOM),
                ],
            ], 503);
            return;
        }
    }

public function banners(): void
{
    $pdo = self::db();
    if (!$pdo) return;

    $placement = $_GET['placement'] ?? '';

    if (!$placement) {
        Response::json([
            'success' => false,
            'message' => 'Placement required'
        ], 422);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM banners 
        WHERE placement = :placement 
        AND is_active = 1
        ORDER BY sort_order ASC
    ");

    $stmt->execute([
        'placement' => $placement
    ]);

    $data = $stmt->fetchAll();

    Response::json([
        'success' => true,
        'data' => $data
    ]);
}
public function bannerUpdate($id): void
{
    $pdo = self::db();
    if (!$pdo) return;

    // 🔥 JSON read
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        Response::json([
            "success" => false,
            "message" => "Invalid input"
        ], 400);
        return;
    }

    // 🔥 only required fields
    $title = $input['title'] ?? null;
    $image = $input['image_url'] ?? null;

    if (!$title && !$image) {
        Response::json([
            "success" => false,
            "message" => "Nothing to update"
        ], 400);
        return;
    }

    $sql = "UPDATE banners SET 
                title = COALESCE(:title, title),
                image_url = COALESCE(:image, image_url),
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'title' => $title,
        'image' => $image,
        'id' => $id
    ]);

    Response::json([
        "success" => true,
        "message" => "Banner updated"
    ]);
}

public function siteTopOffer(): void
{
    $pdo = self::db();
    if (!$pdo) return;

    try {
        $stmt = $pdo->query("\n            SELECT\n                b.title,\n                b.subtitle,\n                b.cta_label,\n                b.cta_url,\n                b.ends_at,\n                b.linked_coupon_id,\n                c.code AS coupon_code,\n                c.is_active AS coupon_is_active,\n                c.is_deleted AS coupon_is_deleted,\n                c.starts_at AS coupon_starts_at,\n                c.ends_at AS coupon_ends_at\n            FROM banners b\n            LEFT JOIN coupons c ON c.id = b.linked_coupon_id\n            WHERE b.placement = 'site_top_offer'\n              AND b.is_active = 1\n              AND (b.starts_at IS NULL OR b.starts_at <= NOW())\n              AND (b.ends_at IS NULL OR b.ends_at >= NOW())\n            ORDER BY b.id DESC\n            LIMIT 1\n        ");
        $banner = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        $isVisible = false;
        if (is_array($banner)) {
            $linkedCouponId = isset($banner['linked_coupon_id']) ? (int) $banner['linked_coupon_id'] : 0;
            if ($linkedCouponId > 0) {
                $couponActive = (int)($banner['coupon_is_active'] ?? 0) === 1;
                $couponDeleted = (int)($banner['coupon_is_deleted'] ?? 0) === 1;
                $couponCode = trim((string)($banner['coupon_code'] ?? ''));
                $couponStarts = trim((string)($banner['coupon_starts_at'] ?? ''));
                $couponEnds = trim((string)($banner['coupon_ends_at'] ?? ''));
                $couponStartsTs = $couponStarts !== '' ? strtotime($couponStarts) : false;
                $couponEndsTs = $couponEnds !== '' ? strtotime($couponEnds) : false;
                $nowTs = time();
                // FIXED: Handle NULL start/end times for open-ended coupons
                $couponWindowValid = true;
                if ($couponStarts !== '') {
                    $couponWindowValid = $couponWindowValid && ($nowTs >= $couponStartsTs);
                }
                if ($couponEnds !== '') {
                    $couponWindowValid = $couponWindowValid && ($nowTs <= $couponEndsTs);
                }
                $isVisible = $couponActive && !$couponDeleted && $couponWindowValid && $couponCode !== '';
            }
        }

        if (!$isVisible || !is_array($banner)) {
            Response::json([
                'success' => true,
                'data' => null,
            ]);
            return;
        }

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
        $effectivePriceSql = 'COALESCE(NULLIF(p.starting_price, 0), pv_min.min_price, p.base_price, 0)';
        $where = [
            'p.deleted_at IS NULL',
            "p.availability_status <> 'draft'",
        ];

        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.short_description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $category = trim((string)($_GET['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'c.slug = :category';
            $params['category'] = $category;
        }

        $dietary = trim((string)($_GET['dietary'] ?? ''));
        if ($dietary !== '') {
            $where[] = 'p.dietary_tag = :dietary';
            $params['dietary'] = $dietary;
        }

        $isVegParam = $_GET['is_veg'] ?? '';
        if ($isVegParam !== '' && in_array((string)$isVegParam, ['0', '1'], true)) {
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

        $sort = (string)($_GET['sort'] ?? 'latest');
        switch ($sort) {
            case 'price_asc':
                $sortSql = $effectivePriceSql . ' ASC';
                break;
            case 'price_desc':
                $sortSql = $effectivePriceSql . ' DESC';
                break;
            case 'popular':
                $sortSql = 'p.is_bestseller DESC, p.review_count DESC';
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
                p.is_veg,
                p.is_bestseller,
                p.is_chef_special,
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
            'SELECT id, variant_label, weight_or_size, flavor, price, discount_price, stock_quantity, is_default
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
        $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as &$image) {
            $image['image_url'] = ProductImageService::resolve((string)($image['image_url'] ?? ''), (string)($product['category_slug'] ?? ''));
        }
        unset($image);

        $product['featured_image'] = ProductImageService::resolve((string)($product['featured_image'] ?? ''), (string)($product['category_slug'] ?? ''));

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
            "SELECT id, name, slug, NULL AS category_type
             FROM categories
             WHERE parent_id IS NULL AND is_active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, name ASC"
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
        $input = $this->readJsonInput();
        $name = trim((string)($input['full_name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($name === '' || $email === '' || $phone === '' || strlen($password) < 8) {
            Response::json(['success' => false, 'message' => 'Invalid registration input'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetch()) {
            Response::json(['success' => false, 'message' => 'Email already registered'], 409);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (:full_name, :email, :phone, :password_hash, "customer")'
        );
        $stmt->execute([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $hash,
        ]);

        $_SESSION['user_id'] = (int)$pdo->lastInsertId();
        $_SESSION['user_role'] = 'customer';

        Response::json(['success' => true, 'message' => 'Registration successful']);
    }

    public function authLogin(): void
    {
        $input = $this->readJsonInput();
        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::json(['success' => false, 'message' => 'Email and password are required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $rateLimiter = new AuthRateLimitService();
        $bucketKey = $this->buildRateLimitBucket('customer-login', $email);
        if ($rateLimiter->isBlocked($pdo, 'customer_login', $bucketKey)) {
            Response::json(['success' => false, 'message' => 'Too many login attempts. Please try again later.'], 429);
            return;
        }

        $stmt = $pdo->prepare('SELECT id, full_name, email, role, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $rateLimiter->hit($pdo, 'customer_login', $bucketKey, 6, 20);
            Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
            return;
        }

        $rateLimiter->clear($pdo, 'customer_login', $bucketKey);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = (string)$user['role'];
        $_SESSION['otp_verified'] = true;

        $updateStmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $updateStmt->execute(['id' => $user['id']]);

        Response::json([
            'success' => true,
            'message' => 'Login successful',
            'data' => ['user' => [
                'id' => (int)$user['id'],
                'full_name' => (string)$user['full_name'],
                'email' => (string)$user['email'],
                'role' => (string)$user['role'],
            ]],
        ]);
    }

    public function authForgotPassword(): void
    {
        $input = $this->readJsonInput();
        $email = trim((string)($input['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Valid email is required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $rateLimiter = new AuthRateLimitService();
        $bucketKey = $this->buildRateLimitBucket('forgot-password', $email);
        if ($rateLimiter->isBlocked($pdo, 'forgot_password', $bucketKey)) {
            Response::json(['success' => false, 'message' => 'Too many reset attempts. Please try again later.'], 429);
            return;
        }

        $stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $rateLimiter->hit($pdo, 'forgot_password', $bucketKey, 5, 30);
            Response::json(['success' => true, 'message' => 'If the account exists, a reset link has been queued']);
            return;
        }

        $service = new PasswordResetService();
        $token = $service->createToken($pdo, (int)$user['id'], (string)$user['email']);
        $resetUrl = rtrim((string)(Env::get('APP_URL', 'http://localhost:8000') ?? 'http://localhost:8000'), '/') . '/reset-password?email=' . rawurlencode($email) . '&token=' . rawurlencode($token['token']);

        $logStmt = $pdo->prepare('INSERT INTO communication_logs (user_id, channel, event_key, recipient, status, payload_json) VALUES (:user_id, "email", "password_reset", :recipient, "queued", :payload_json)');
        $logStmt->execute([
            'user_id' => (int)$user['id'],
            'recipient' => (string)$user['email'],
            'payload_json' => json_encode([
                'customer_name' => (string)$user['full_name'],
                'reset_url' => $resetUrl,
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $logId = (int)$pdo->lastInsertId();

        $queueStmt = $pdo->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:communication_log_id, "email", :payload_json)');
        $queueStmt->execute([
            'communication_log_id' => $logId,
            'payload_json' => json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES),
        ]);

        $jobStmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", :payload_json, "queued", NOW(), 0)');
        $jobStmt->execute([
            'payload_json' => json_encode(['log_id' => $logId, 'channel' => 'email', 'event_key' => 'password_reset', 'recipient' => (string)$user['email']], JSON_UNESCAPED_SLASHES),
        ]);

        $rateLimiter->clear($pdo, 'forgot_password', $bucketKey);
        Response::json(['success' => true, 'message' => 'If the account exists, a reset link has been queued']);
    }

    public function authResetPassword(): void
    {
        $input = $this->readJsonInput();
        $email = trim((string)($input['email'] ?? ''));
        $token = trim((string)($input['token'] ?? ''));
        $password = (string)($input['password'] ?? '');
        if ($email === '' || $token === '' || strlen($password) < 8) {
            Response::json(['success' => false, 'message' => 'email, token, and password are required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $rateLimiter = new AuthRateLimitService();
        $bucketKey = $this->buildRateLimitBucket('reset-password', $email);
        if ($rateLimiter->isBlocked($pdo, 'reset_password', $bucketKey)) {
            Response::json(['success' => false, 'message' => 'Too many reset attempts. Please try again later.'], 429);
            return;
        }

        $service = new PasswordResetService();
        $ok = $service->consume($pdo, $email, $token, $password);
        if (!$ok) {
            $rateLimiter->hit($pdo, 'reset_password', $bucketKey, 5, 30);
            Response::json(['success' => false, 'message' => 'Invalid or expired reset token'], 422);
            return;
        }

        $rateLimiter->clear($pdo, 'reset_password', $bucketKey);
        Response::json(['success' => true, 'message' => 'Password reset successful']);
    }

    public function authMe(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::json(['success' => false, 'message' => 'Not authenticated'], 401);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, full_name, email, phone, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['user' => $user]]);
    }

    public function authLogout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();

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

        // 🔥 CHECK existing
        $existingStmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND variant_id = :variant_id LIMIT 1');
        $existingStmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId,
            'variant_id' => $variantId,
        ]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = (int)$existing['quantity'] + $quantity;

            $updateStmt = $pdo->prepare('
                UPDATE cart_items 
                SET quantity = :quantity, unit_price = :unit_price, line_total = :line_total 
                WHERE id = :id
            ');

            $updateStmt->execute([
                'quantity' => $newQty,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $newQty,
                'id' => $existing['id'],
            ]);

        } else {

            $insertStmt = $pdo->prepare('
                INSERT INTO cart_items 
                (cart_id, product_id, variant_id, quantity, unit_price, line_total) 
                VALUES 
                (:cart_id, :product_id, :variant_id, :quantity, :unit_price, :line_total)
            ');

            $insertStmt->execute([
                'cart_id' => $cartId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
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

        $stmt = $pdo->prepare('SELECT id, unit_price FROM cart_items WHERE id = :id AND cart_id = :cart_id LIMIT 1');
        $stmt->execute(['id' => (int)$itemId, 'cart_id' => $cartId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            Response::json(['success' => false, 'message' => 'Cart item not found'], 404);
            return;
        }

        $updateStmt = $pdo->prepare('UPDATE cart_items SET quantity = :quantity, line_total = :line_total, updated_at = NOW() WHERE id = :id');
        $updateStmt->execute([
            'quantity' => $quantity,
            'line_total' => round((float)$item['unit_price'] * $quantity, 2),
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
        $mode = (string)($_GET['mode'] ?? 'delivery');
        $sameDay = (int)($_GET['same_day'] ?? 0);

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
            'SELECT id, slot_label, start_time, end_time, fulfilment_mode, is_same_day_allowed
             FROM delivery_time_slots
             WHERE is_active = 1
               AND (fulfilment_mode = :mode OR fulfilment_mode = "both")
               AND (:same_day = 0 OR is_same_day_allowed = 1)
             ORDER BY sort_order ASC'
        );
        $stmt->execute(['mode' => $mode, 'same_day' => $sameDay]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function checkoutPreview(): void
    {
        $pdo = self::db(); if (!$pdo) return;
        $cartId = $this->getOrCreateCartId($pdo);
        $input = $this->readJsonInput();

        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        $postalCode = trim((string)($input['postal_code'] ?? ''));

        $this->maybeAutoApplyBestPublicCoupon($pdo, $cartId);
        $cart = $this->buildCartResponse($pdo, $cartId);
        if ((int)$cart['item_count'] <= 0) {
            Response::json(['success' => false, 'message' => 'Cart is empty'], 422);
            return;
        }

        $deliveryFee = 0.0;
        $distanceKm = null;

        if ($fulfilmentMode === 'delivery' || $fulfilmentMode === 'custom_delivery') {
            if ($postalCode === '') {
                Response::json(['success' => false, 'message' => 'postal_code is required for delivery'], 422);
                return;
            }

            $pinStmt = $pdo->prepare('SELECT * FROM delivery_pincodes WHERE postal_code = :postal_code LIMIT 1');
            $pinStmt->execute(['postal_code' => $postalCode]);
            $pin = $pinStmt->fetch(PDO::FETCH_ASSOC);
            if (!$pin || (int)$pin['is_serviceable'] !== 1) {
                Response::json(['success' => false, 'message' => 'Delivery unavailable for this pincode'], 422);
                return;
            }

            $distanceKm = (float)$pin['approx_distance_km'];
            $slabStmt = $pdo->prepare('SELECT * FROM delivery_distance_slabs WHERE :distance >= min_km AND :distance <= max_km LIMIT 1');
            $slabStmt->execute(['distance' => $distanceKm]);
            $slab = $slabStmt->fetch(PDO::FETCH_ASSOC);
            if (!$slab || (int)$slab['is_available'] !== 1) {
                Response::json(['success' => false, 'message' => 'Delivery outside service radius unless manually approved'], 422);
                return;
            }

            $deliveryFee = (float)$slab['delivery_fee'];
        }

        $grandTotal = round((float)$cart['subtotal'] - (float)$cart['discount_total'] + $deliveryFee, 2);
        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'cart' => $cart,
                'fulfilment_mode' => $fulfilmentMode,
                'delivery_fee' => $deliveryFee,
                'distance_km' => $distanceKm,
                'grand_total' => $grandTotal,
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
    Response::json([
        'success' => false,
        'message' => 'Cart not found'
    ], 422);
    return;
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
        $customerPhone = preg_replace('/\D/', '', $customerPhone);

if (strpos($customerPhone, '91') !== 0) {
    $customerPhone = '91' . $customerPhone;
}

$customerPhone = $customerPhone;
        $fulfilmentMode = (string)($input['fulfilment_mode'] ?? 'delivery');
        $paymentMethod = (string)($input['payment_method'] ?? 'upi_manual');
        if ($paymentMethod !== 'upi_manual') {
            Response::json([
                'success' => false,
                'message' => 'Online checkout requires UPI payment only',
            ], 422);
            return;
        }
        $postalCode      = trim((string)($input['delivery_pincode'] ?? $input['postal_code'] ?? ''));
        $deliveryStreet  = trim((string)($input['delivery_street'] ?? ''));
        $deliveryMapsLink = trim((string)($input['delivery_maps_link'] ?? ''));
        $deliveryDate = trim((string)($input['delivery_date'] ?? ''));
        $slotId = (int)($input['slot_id'] ?? 0);
        $paymentType = trim((string)($input['payment_type'] ?? 'full'));
        if (!in_array($paymentType, ['full', 'advance_50'], true)) { $paymentType = 'full'; }

        if ($paymentType === 'advance_50') {
            $allowPartial = $this->getSettingValue($pdo, 'allow_partial_payment');
            if ($allowPartial === '0') {
                Response::json(['success' => false, 'message' => 'Partial payment is not available at this time.'], 400);
                return;
            }
        }

        $paymentStatus = $paymentType === 'advance_50' ? 'partial' : 'pending';

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

            $scheduledSlot = $deliveryDate . ' ' . $startTime;
            $scheduledSlotLabel = trim((string)($slotRow['slot_label'] ?? ''));
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
            $advanceAmount = $paymentType === 'advance_50' ? round($grandTotal * 0.5, 2) : null;

$orderStmt = $pdo->prepare('
INSERT INTO orders (
    order_number, user_id, customer_name, customer_email, customer_phone,
    fulfilment_mode, order_status, payment_status, payment_method,
    scheduled_slot, scheduled_slot_label,
    delivery_postal_code, delivery_street, delivery_maps_link, delivery_distance_km, delivery_fee,
    subtotal, discount_total, tax_total, grand_total, advance_amount
) VALUES (
    :order_number, :user_id, :customer_name, :customer_email, :customer_phone,
    :fulfilment_mode, "pending", :payment_status, :payment_method,
    :scheduled_slot, :scheduled_slot_label,
    :postal_code, :delivery_street, :delivery_maps_link, :distance_km, :delivery_fee,
    :subtotal, :discount_total, 0, :grand_total, :advance_amount
)');

$orderStmt->execute([
    'order_number' => $orderNumber,
    'user_id' => $userId > 0 ? $userId : null,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'fulfilment_mode' => $fulfilmentMode,
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

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id, product_id, variant_id, product_name_snapshot, variant_snapshot,
                    unit_price, quantity, line_total, customisation_note
                ) VALUES (
                    :order_id, :product_id, :variant_id, :product_name_snapshot, :variant_snapshot,
                    :unit_price, :quantity, :line_total, :customisation_note
                )'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name_snapshot' => $item['product_name'],
                    'variant_snapshot' => $item['variant_label'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                    'customisation_note' => null,
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
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
           
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
                o.grand_total,
                o.created_at,
                COUNT(oi.id) AS item_count
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :user_id
             GROUP BY o.id
             ORDER BY o.created_at DESC'
        );
        $stmt->execute(['user_id' => (int)$user['id']]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
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
            ],
        ]);
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
             )
             ON DUPLICATE KEY UPDATE
                customer_user_id = VALUES(customer_user_id),
                order_id = VALUES(order_id),
                status = VALUES(status),
                match_confidence = VALUES(match_confidence),
                alert_message = VALUES(alert_message),
                raw_payload_json = VALUES(raw_payload_json),
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
                 )
                 ON DUPLICATE KEY UPDATE
                    parsed_amount = COALESCE(VALUES(parsed_amount), parsed_amount),
                    bank_sender = COALESCE(VALUES(bank_sender), bank_sender),
                    email_subject = COALESCE(VALUES(email_subject), email_subject),
                    alert_message = COALESCE(VALUES(alert_message), alert_message),
                    event_time = COALESCE(VALUES(event_time), event_time),
                    status = CASE
                        WHEN status = "confirmed" THEN status
                        ELSE VALUES(status)
                    END,
                    match_confidence = CASE
                        WHEN match_confidence = "strong" THEN match_confidence
                        ELSE VALUES(match_confidence)
                    END,
                    customer_user_id = COALESCE(VALUES(customer_user_id), customer_user_id),
                    order_id = COALESCE(VALUES(order_id), order_id),
                    raw_payload_json = VALUES(raw_payload_json),
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
    $budgetRange = trim((string)($input['budget_range'] ?? ''));
    $dietPreference = trim((string)($input['diet_preference'] ?? ''));
    $designBrief = trim((string)($input['design_breif_notes'] ?? ''));
    $privacyConsent = (string)($input['privacy_consent'] ?? '') === '1';

    if ($name === '' || $email === '' || $phone === '' || $eventInformation === '' || $eventDate === '' || $servings === '' || $budgetRange === '' || $dietPreference === '' || $designBrief === '') {
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

    $allowedDietPreference = ['Veg', 'Non Veg', 'Jain'];
    if (!in_array($dietPreference, $allowedDietPreference, true)) {
        Response::json(['success' => false, 'message' => 'Invalid diet preference selected'], 422);
        return;
    }

    // ✅ IMAGE UPLOAD LOGIC START
    $referenceImagePath = null;

    if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === 0) {

        $uploadDir = __DIR__ . '/../../public/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmp = $_FILES['reference_file']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['reference_file']['name']);

        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmp, $targetPath)) {
            $referenceImagePath = '/uploads/' . $fileName;
        }
    }
    // ✅ IMAGE UPLOAD LOGIC END

    $encodedMessage = json_encode([
        'phone_country_code' => $phoneCountryCode,
        'event_information' => $eventInformation,
        'event_date' => $eventDate,
        'number_of_servings_guests' => $servings,
        'budget_range' => $budgetRange,
        'diet_preference' => $dietPreference,
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
        . ' | Servings: ' . $servings
        . ' | Budget: ' . $budgetRange
        . ' | Diet: ' . $dietPreference;
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
        'budget_range' => $budgetRange,
        'diet_preference' => $dietPreference,
        'design_brief_notes' => $safeDesignBrief,
        'quote_description' => $quoteDescription,
        'reference_file' => (string)$referenceImagePath,
        'contact.name' => $name,
        'contact.first_name' => $firstName,
        'contact.mobile' => $phone,
        'contact.phone' => $phone,
        'contact.email' => $email,
        'contact.orderid' => 'INQ-' . $inquiryId,
        'contact.amount' => $budgetRange,
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
            'budget_range' => $budgetRange,
            'diet_preference' => $dietPreference,
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
            $paymentType = trim((string)($input['payment_type'] ?? 'advance_50'));
            if (!in_array($paymentType, ['full', 'advance_50'], true)) {
                $paymentType = 'advance_50';
            }
            if ($deliveryStreet === '') {
                $pdo->rollBack();
                Response::json(['success' => false, 'message' => 'Delivery street address is required.'], 422);
                return;
            }

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
                    byoc_quote_id
                ) VALUES (
                    :order_number,
                    NULL,
                    :customer_name,
                    :customer_email,
                    :customer_phone,
                    "custom_delivery",
                    "pending",
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
                    0,
                    0,
                    :grand_total,
                    :advance_amount,
                    :admin_note,
                    "byoc_quote",
                    :byoc_quote_id
                )'
            );
            $advanceAmount = $paymentType === 'full' ? $quoteAmount : round($quoteAmount * 0.5, 2);
            $paymentStatus = $paymentType === 'full' ? 'pending' : 'partial';
            $insertOrder->execute([
                'order_number' => $orderNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'scheduled_slot' => !empty($meta['event_date']) ? $meta['event_date'] . ' 10:00:00' : null,
                'scheduled_slot_label' => !empty($meta['event_date']) ? 'Event Date: ' . $meta['event_date'] : null,
                'delivery_postal_code' => $deliveryPincode ?: null,
                'delivery_street' => $deliveryStreet,
                'delivery_maps_link' => $deliveryMapsLink ?: null,
                'subtotal' => $quoteAmount,
                'grand_total' => $quoteAmount,
                'advance_amount' => $advanceAmount,
                'payment_status' => $paymentStatus,
                'admin_note' => 'BYOC quote accepted via secure link. Quote #' . (string)($quote['quote_number'] ?? ''),
                'byoc_quote_id' => (int)$quote['quote_id'],
            ]);

            $orderId = (int)$pdo->lastInsertId();

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
                $remainingBalance = max(0, $quoteAmount - $advanceAmount);
                $deliveryAddress = trim($deliveryStreet . ($deliveryPincode !== '' ? ', ' . $deliveryPincode : ''));
                $emailContext = [
                    'order_number'     => $orderNumber,
                    'customer_name'    => $customerName,
                    'customer_email'   => $customerEmail,
                    'customer_phone'   => $customerPhone,
                    'grand_total'      => number_format($quoteAmount, 2),
                    'advance_amount'   => number_format($advanceAmount, 2),
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
        if ($userId <= 0 || $role !== 'customer') {
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
        $stmt = $pdo->prepare(
            'SELECT
                ci.id,
                ci.product_id,
                ci.variant_id,
                ci.quantity,
                ci.unit_price,
                ci.line_total,
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
$deliveryFee = 0; // 
//$deliveryFee = 60; // TODO: make dynamic based on pincode (future)
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
        $postalCode = trim((string)($input['postal_code'] ?? ''));
        $cart = $this->buildCartResponse($pdo, $cartId);

        if ((int)$cart['item_count'] <= 0) {
            return ['ok' => false, 'message' => 'Cart is empty'];
        }

        $deliveryFee = 0.0;
        $distanceKm = null;

        if ($fulfilmentMode === 'delivery' || $fulfilmentMode === 'custom_delivery') {
            if ($postalCode === '') {
                return ['ok' => false, 'message' => 'postal_code is required for delivery'];
            }

            $pinStmt = $pdo->prepare('SELECT * FROM delivery_pincodes WHERE postal_code = :postal_code LIMIT 1');
            $pinStmt->execute(['postal_code' => $postalCode]);
            $pin = $pinStmt->fetch(PDO::FETCH_ASSOC);
            if (!$pin || (int)$pin['is_serviceable'] !== 1) {
                return ['ok' => false, 'message' => 'Delivery unavailable for this pincode'];
            }

           $distanceKm = (float)$pin['approx_distance_km'];

$slabStmt = $pdo->prepare('
SELECT * FROM delivery_distance_slabs 
WHERE :distance >= min_km AND :distance <= max_km
LIMIT 1
');

$slabStmt->execute([
    'distance1' => $distanceKm,
    'distance2' => $distanceKm
]);

$slab = $slabStmt->fetch(PDO::FETCH_ASSOC);

if (!$slab || (int)$slab['is_available'] !== 1) {
    return ['ok' => false, 'message' => 'Delivery outside service radius unless manually approved'];
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

    $otp = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    $pdo->prepare('DELETE FROM otp_verifications WHERE email = :email')
        ->execute(['email' => $email]);

    $stmt = $pdo->prepare(
        'INSERT INTO otp_verifications (email, otp, expires_at)
         VALUES (:email, :otp, :expires_at)'
    );
    $stmt->execute([
        'email' => $email,
        'otp' => $otp,
        'expires_at' => $expiresAt,
    ]);

    try {
        MailService::sendOtp($email, $otp, $customerName);
    } catch (\Throwable $e) {
        error_log('OTP send failed for ' . $email . ': ' . $e->getMessage());
        Response::json([
            'success' => false,
            'message' => 'Unable to send OTP email right now. Please try again shortly.',
        ], 500);
        return;
    }

    Response::json([
        'success' => true,
        'message' => 'OTP sent to email',
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

    if ($email === '' || $otp === '' || strlen($otp) !== 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::json([
            "success" => false,
            "message" => "Valid email and 6-digit OTP required"
        ], 422);
        return;
    }

    $pdo = self::db();
    if (!$pdo) return;

    $stmt = $pdo->prepare("
        SELECT * FROM otp_verifications 
        WHERE email = :email 
        AND otp = :otp 
        AND expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email,
        'otp' => $otp
    ]);

    $otpRow = $stmt->fetch();

    if (!$otpRow) {
        Response::json([
            "success" => false,
            "message" => "Invalid or expired OTP"
        ], 401);
        return;
    }

    // ✅ user create/get
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = (int)$user['id'];

        if ($name !== '' || $phone !== '') {
            $updateParts = [];
            $params = ['id' => $userId];

            if ($name !== '') {
                $updateParts[] = 'full_name = :name';
                $params['name'] = $name;
            }
            if ($phone !== '') {
                $updateParts[] = 'phone = :phone';
                $params['phone'] = $phone;
            }

            if ($updateParts !== []) {
                $pdo->prepare('UPDATE users SET ' . implode(', ', $updateParts) . ' WHERE id = :id')
                    ->execute($params);
            }
        }
    } else {
        $generatedPasswordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (:full_name, :email, :phone, :password_hash, "customer")'
        );
        $stmt->execute([
            'full_name' => $name !== '' ? $name : 'Guest User',
            'email' => $email,
            'phone' => $phone !== '' ? $phone : '0000000000',
            'password_hash' => $generatedPasswordHash,
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    // ✅ session
    $_SESSION['user_id'] = $userId;

    $_SESSION['user_role'] = 'customer';
    $_SESSION['otp_verified'] = true;

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute(['id' => $userId]);

    // attach cart
    $sessionId = session_id();
    //$pdo->prepare("UPDATE carts SET user_id = :user_id WHERE session_id = :session_id")
   // attach cart (FIXED VERSION 🔥)


// 1. guest cart शोध
// 🔥 attach + merge cart properly

$sessionId = session_id();

// ✅ guest cart
$stmt = $pdo->prepare("
    SELECT id FROM carts 
    WHERE session_id = :session_id 
    LIMIT 1
");
$stmt->execute(['session_id' => $sessionId]);
$guestCartId = (int)($stmt->fetchColumn() ?: 0);

// ✅ user cart
$stmt = $pdo->prepare("
    SELECT id FROM carts 
    WHERE user_id = :user_id 
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$userCartId = (int)($stmt->fetchColumn() ?: 0);

if ($guestCartId > 0) {

    if ($userCartId > 0) {
        // 🔥 MERGE ITEMS (MAIN FIX)
        $pdo->prepare("
            UPDATE cart_items 
            SET cart_id = :user_cart_id 
            WHERE cart_id = :guest_cart_id
        ")->execute([
            'user_cart_id' => $userCartId,
            'guest_cart_id' => $guestCartId
        ]);
    } else {
        // 🔥 convert guest cart → user cart
        $pdo->prepare("
            UPDATE carts 
            SET user_id = :user_id 
            WHERE id = :guest_cart_id
        ")->execute([
            'user_id' => $userId,
            'guest_cart_id' => $guestCartId
        ]);
    }
}


    // delete OTP
    $pdo->prepare("DELETE FROM otp_verifications WHERE email = :email")
        ->execute(['email' => $email]);

    Response::json([
        "success" => true,
        "message" => "Email OTP verified"
    ]);
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

