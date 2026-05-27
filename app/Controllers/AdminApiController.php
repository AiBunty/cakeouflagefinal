<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueueWorker;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthRateLimitService;
use App\Services\AuthManager;
use App\Services\ExcelService;
use App\Services\FinancialReconciliationService;
use App\Services\MediaCapabilityService;
use App\Services\UnifiedMediaService;
use App\Services\VariableResolverService;
use App\Services\WhatsAppDispatchService;
use App\Services\WhatsAppMetaApiService;
use App\Services\WhatsAppTemplateApprovalService;
use App\Services\WhatsAppTemplateBuilderService;
use App\Services\WhatsAppTemplateRendererService;
use App\Services\WhatsAppTemplateSyncService;
use PDO;
use Throwable;

final class AdminApiController
{
    private const MAX_MEDIA_UPLOAD_BYTES = 104857600; // 100 MB

    private ?bool $mediaAssetsTableExists = null;

    private static function db(): ?\PDO
    {
        try { return Database::getConnection(); }
        catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Database unavailable.'], 503);
            return null;
        }
    }

    public function authLogin(): void
    {
        Response::json([
            'success' => false,
            'message' => 'Password-based admin login is disabled. Use OTP login only.',
        ], 410);
    }

    public function authSendOtp(): void
    {
        $input = $this->readJsonInput();
        $email = strtolower(trim((string)($input['email'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Valid admin email is required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, full_name, email FROM admins WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            Response::json(['success' => false, 'message' => 'No active admin account found for this email.'], 404);
            return;
        }

        try {
            AuthManager::sendOtp($pdo, (string)$admin['email'], (string)($admin['full_name'] ?? 'Admin'));
        } catch (\Throwable $e) {
            $status = $e->getCode() === 429 ? 429 : 500;
            Response::json([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to send OTP email right now.',
            ], $status);
            return;
        }

        Response::json(['success' => true, 'message' => 'Admin OTP sent to email']);
    }

    public function authVerifyOtp(): void
    {
        $input = $this->readJsonInput();
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $otp = preg_replace('/\D+/', '', (string)($input['otp'] ?? '')) ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($otp) !== 6) {
            Response::json(['success' => false, 'message' => 'Valid email and 6-digit OTP are required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM admins WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            Response::json(['success' => false, 'message' => 'No active admin account found for this email.'], 404);
            return;
        }

        try {
            AuthManager::validateOtp($pdo, $email, $otp, 'admin');
            AuthManager::establishAdminSession($admin);
        } catch (\Throwable $e) {
            $status = ($e->getCode() === 429) ? 429 : (($e->getCode() === 401) ? 401 : 500);
            Response::json([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to verify OTP right now.',
            ], $status);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Admin OTP verified',
            'data' => [
                'redirect_to' => '/admin/dashboard.php',
                'admin' => [
                    'id' => (int)$admin['id'],
                    'full_name' => (string)$admin['full_name'],
                    'email' => (string)$admin['email'],
                    'role' => (string)$admin['role'],
                ],
            ],
        ]);
    }

    public function authLogout(): void
    {
        AuthManager::logoutAdmin();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        Response::json(['success' => true, 'message' => 'Admin logged out']);
    }

    public function authMe(): void
    {
        if (empty($_SESSION['admin_otp_verified'])) {
            Response::json(['success' => false, 'message' => 'Admin OTP authentication required'], 401);
            return;
        }

        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            Response::json(['success' => false, 'message' => 'Admin account not found'], 404);
            return;
        }

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['admin' => $admin]]);
    }

    public function productsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 40)));

        $sql = 'SELECT p.id, p.name, p.slug, p.sku, p.short_description, p.description, p.long_description,
                   p.collection_category_id, p.starting_price, p.base_price, p.stock_quantity,
                   p.availability_status, p.is_featured, p.is_bestseller, p.is_chef_special,
                   c.name AS category_name, p.created_at
                FROM products p
                JOIN categories c ON c.id = p.collection_category_id
                WHERE p.deleted_at IS NULL';

        $params = [];
        if ($q !== '') {
            $sql .= ' AND (p.name LIKE :q_name OR p.sku LIKE :q_sku OR p.slug LIKE :q_slug)';
            $needle = '%' . $q . '%';
            $params['q_name'] = $needle;
            $params['q_sku'] = $needle;
            $params['q_slug'] = $needle;
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function productsCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $name = trim((string)($input['name'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        $sku = trim((string)($input['sku'] ?? ''));
        $categoryId = (int)($input['collection_category_id'] ?? 0);
        $description = trim((string)($input['description'] ?? ($input['long_description'] ?? ($input['short_description'] ?? ''))));
        $shortDescription = trim((string)($input['short_description'] ?? $description));
        $longDescription = trim((string)($input['long_description'] ?? $description));
        $startingPrice = (float)($input['starting_price'] ?? 0);
        $basePrice = (float)($input['base_price'] ?? $startingPrice);
        $stock = max(0, (int)($input['stock_quantity'] ?? 0));
        $availability = (string)($input['availability_status'] ?? 'in_stock');

        if ($name === '' || $slug === '' || $sku === '' || $categoryId <= 0 || $description === '' || $startingPrice <= 0) {
            Response::json(['success' => false, 'message' => 'Missing required product fields'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $dietaryType = $this->resolveDietaryType($input, $pdo);
        $dietaryTag = $this->resolveDietaryTag((string)($input['dietary_tag'] ?? 'regular'), $dietaryType);
        $isVeg = dietaryTypeToIsVeg($dietaryType);

        $categoryStmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
        $categoryStmt->execute(['id' => $categoryId]);
        if (!$categoryStmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Invalid category'], 422);
            return;
        }

        $dupeStmt = $pdo->prepare('SELECT id FROM products WHERE (slug = :slug OR sku = :sku) AND deleted_at IS NULL LIMIT 1');
        $dupeStmt->execute(['slug' => $slug, 'sku' => $sku]);
        if ($dupeStmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Slug or SKU already exists'], 409);
            return;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO products (
                    name, slug, short_description, description, long_description, sku,
                    collection_category_id, subcategory_id, occasion_tag, dietary_tag, is_veg,
                    availability_status, lead_time_hours, customisation_note,
                    delivery_eligible, pickup_eligible, featured_image,
                    starting_price, base_price, discount_price, stock_quantity,
                    is_featured, is_bestseller, is_chef_special, seo_title, seo_description,
                    is_b2b_enabled, b2b_minimum_quantity
                ) VALUES (
                    :name, :slug, :short_description, :description, :long_description, :sku,
                    :collection_category_id, :subcategory_id, :occasion_tag, :dietary_tag, :is_veg,
                    :availability_status, :lead_time_hours, :customisation_note,
                    :delivery_eligible, :pickup_eligible, :featured_image,
                    :starting_price, :base_price, :discount_price, :stock_quantity,
                    :is_featured, :is_bestseller, :is_chef_special, :seo_title, :seo_description,
                    :is_b2b_enabled, :b2b_minimum_quantity
                )'
            );

            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'short_description' => $shortDescription,
                'description' => $description,
                'long_description' => $longDescription,
                'sku' => $sku,
                'collection_category_id' => $categoryId,
                'subcategory_id' => (int)($input['subcategory_id'] ?? 0) ?: null,
                'occasion_tag' => $this->nullableString($input['occasion_tag'] ?? null),
                'dietary_tag' => $dietaryTag,
                'is_veg' => $isVeg,
                'availability_status' => $this->safeAvailability($availability),
                'lead_time_hours' => max(1, (int)($input['lead_time_hours'] ?? 24)),
                'customisation_note' => $this->nullableString($input['customisation_note'] ?? null),
                'delivery_eligible' => $this->toBinaryFlag($input['delivery_eligible'] ?? 1),
                'pickup_eligible' => $this->toBinaryFlag($input['pickup_eligible'] ?? 1),
                'featured_image' => $this->nullableString($input['featured_image'] ?? null),
                'starting_price' => round($startingPrice, 2),
                'base_price' => round($basePrice, 2),
                'discount_price' => ($input['discount_price'] ?? null) !== null ? round((float)$input['discount_price'], 2) : null,
                'stock_quantity' => $stock,
                'is_featured' => $this->toBinaryFlag($input['is_featured'] ?? 0),
                'is_bestseller' => $this->toBinaryFlag($input['is_bestseller'] ?? 0),
                'is_chef_special' => $this->toBinaryFlag($input['is_chef_special'] ?? 0),
                'seo_title' => $this->nullableString($input['seo_title'] ?? null),
                'seo_description' => $this->nullableString($input['seo_description'] ?? null),
                'is_b2b_enabled' => $this->toBinaryFlag($input['is_b2b_enabled'] ?? 0),
                'b2b_minimum_quantity' => (int)($input['b2b_minimum_quantity'] ?? 0) > 0 ? (int)$input['b2b_minimum_quantity'] : null,
            ]);

            $productId = (int)$pdo->lastInsertId();

            if ($this->tableHasColumn($pdo, 'products', 'dietary_type')) {
                $dietaryTypeStmt = $pdo->prepare('UPDATE products SET dietary_type = :dietary_type WHERE id = :id');
                $dietaryTypeStmt->execute(['dietary_type' => $dietaryType, 'id' => $productId]);
            }

            $variants = is_array($input['variants'] ?? null) ? $input['variants'] : [];
            if (count($variants) === 0) {
                $variants = [[
                    'variant_label' => '1 lb',
                    'variant_name' => '1 lb',
                    'weight_or_size' => '1 lb',
                    'unit_type' => 'size',
                    'price' => round($startingPrice, 2),
                    'stock_quantity' => $stock,
                    'is_default' => 1,
                ]];
            }
            $this->replaceProductVariants($pdo, $productId, $variants);

            $this->logAdminAction($pdo, $adminId, 'create_product', 'products', $productId, ['name' => $name, 'sku' => $sku]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to create product', 'details' => $e->getMessage()], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Product created'], 201);
    }

    public function productsUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        if ($productId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;

        $existsStmt = $pdo->prepare('SELECT id, featured_image FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $existsStmt->execute(['id' => $productId]);
        $product = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            Response::json(['success' => false, 'message' => 'Product not found'], 404);
            return;
        }

        $dietaryType = $this->resolveDietaryType($input, $pdo);

        $payload = [
            'name' => trim((string)($input['name'] ?? '')),
            'slug' => trim((string)($input['slug'] ?? '')),
            'description' => trim((string)($input['description'] ?? ($input['long_description'] ?? ($input['short_description'] ?? '')))),
            'short_description' => trim((string)($input['short_description'] ?? ($input['description'] ?? ($input['long_description'] ?? '')))),
            'long_description' => trim((string)($input['long_description'] ?? ($input['description'] ?? ($input['short_description'] ?? '')))),
            'sku' => trim((string)($input['sku'] ?? '')),
            'collection_category_id' => (int)($input['collection_category_id'] ?? 0),
            'subcategory_id' => (int)($input['subcategory_id'] ?? 0) ?: null,
            'occasion_tag' => $this->nullableString($input['occasion_tag'] ?? null),
            'dietary_tag' => $this->resolveDietaryTag((string)($input['dietary_tag'] ?? 'regular'), $dietaryType),
            'is_veg' => dietaryTypeToIsVeg($dietaryType),
            'availability_status' => $this->safeAvailability((string)($input['availability_status'] ?? 'in_stock')),
            'lead_time_hours' => max(1, (int)($input['lead_time_hours'] ?? 24)),
            'customisation_note' => $this->nullableString($input['customisation_note'] ?? null),
            'delivery_eligible' => $this->toBinaryFlag($input['delivery_eligible'] ?? 1),
            'pickup_eligible' => $this->toBinaryFlag($input['pickup_eligible'] ?? 1),
            'featured_image' => $this->nullableString($input['featured_image'] ?? null),
            'starting_price' => round((float)($input['starting_price'] ?? 0), 2),
            'base_price' => round((float)($input['base_price'] ?? 0), 2),
            'discount_price' => ($input['discount_price'] ?? null) !== null && (string)$input['discount_price'] !== '' ? round((float)$input['discount_price'], 2) : null,
            'stock_quantity' => max(0, (int)($input['stock_quantity'] ?? 0)),
            'is_featured' => $this->toBinaryFlag($input['is_featured'] ?? 0),
            'is_bestseller' => $this->toBinaryFlag($input['is_bestseller'] ?? 0),
            'is_chef_special' => $this->toBinaryFlag($input['is_chef_special'] ?? 0),
            'seo_title' => $this->nullableString($input['seo_title'] ?? null),
            'seo_description' => $this->nullableString($input['seo_description'] ?? null),
            'is_b2b_enabled' => $this->toBinaryFlag($input['is_b2b_enabled'] ?? 0),
            'b2b_minimum_quantity' => (int)($input['b2b_minimum_quantity'] ?? 0) > 0 ? (int)$input['b2b_minimum_quantity'] : null,
            'id' => $productId,
        ];

        if ($payload['name'] === '' || $payload['slug'] === '' || $payload['sku'] === '' || $payload['description'] === '' || $payload['collection_category_id'] <= 0 || $payload['starting_price'] <= 0 || $payload['base_price'] <= 0) {
            Response::json(['success' => false, 'message' => 'Missing required product fields'], 422);
            return;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE products SET
                    name = :name,
                    slug = :slug,
                    short_description = :short_description,
                    description = :description,
                    long_description = :long_description,
                    sku = :sku,
                    collection_category_id = :collection_category_id,
                    subcategory_id = :subcategory_id,
                    occasion_tag = :occasion_tag,
                    dietary_tag = :dietary_tag,
                    is_veg = :is_veg,
                    availability_status = :availability_status,
                    lead_time_hours = :lead_time_hours,
                    customisation_note = :customisation_note,
                    delivery_eligible = :delivery_eligible,
                    pickup_eligible = :pickup_eligible,
                    featured_image = :featured_image,
                    starting_price = :starting_price,
                    base_price = :base_price,
                    discount_price = :discount_price,
                    stock_quantity = :stock_quantity,
                    is_featured = :is_featured,
                    is_bestseller = :is_bestseller,
                    is_chef_special = :is_chef_special,
                    seo_title = :seo_title,
                    seo_description = :seo_description,
                    is_b2b_enabled = :is_b2b_enabled,
                    b2b_minimum_quantity = :b2b_minimum_quantity
                 WHERE id = :id'
            );
            $stmt->execute($payload);

            if ($this->tableHasColumn($pdo, 'products', 'dietary_type')) {
                $dietaryTypeStmt = $pdo->prepare('UPDATE products SET dietary_type = :dietary_type WHERE id = :id');
                $dietaryTypeStmt->execute(['dietary_type' => $dietaryType, 'id' => $productId]);
            }

            if (is_array($input['variants'] ?? null)) {
                $this->replaceProductVariants($pdo, $productId, $input['variants']);
            }

            $this->logAdminAction($pdo, $adminId, 'update_product', 'products', $productId, ['name' => $payload['name'], 'sku' => $payload['sku']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to update product', 'details' => $e->getMessage()], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Product updated']);
    }

    public function productsDelete(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE products SET deleted_at = NOW(), availability_status = "draft" WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $productId]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Product not found or already deleted'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'delete_product', 'products', $productId, []);
        Response::json(['success' => true, 'message' => 'Product archived']);
    }

    public function categoriesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT c.id, c.parent_id, "core" AS category_type, c.name, c.slug, c.description, c.is_active, c.sort_order,
                    p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.deleted_at IS NULL
             ORDER BY c.sort_order ASC, c.name ASC'
        );

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function categoriesCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $name = trim((string)($input['name'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        $categoryType = (string)($input['category_type'] ?? 'core');

        if ($name === '' || $slug === '' || !in_array($categoryType, ['core', 'occasion', 'small_bakes', 'gifting', 'course', 'b2b'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid category input'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
              'INSERT INTO categories (parent_id, name, slug, description, is_active, sort_order)
               VALUES (:parent_id, :name, :slug, :description, :is_active, :sort_order)'
        );

        try {
            $stmt->execute([
                'parent_id' => (int)($input['parent_id'] ?? 0) ?: null,
                'name' => $name,
                'slug' => $slug,
                'description' => $this->nullableString($input['description'] ?? null),
                'is_active' => $this->toBinaryFlag($input['is_active'] ?? 1),
                'sort_order' => (int)($input['sort_order'] ?? 0),
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to create category', 'details' => $e->getMessage()], 500);
            return;
        }

        $categoryId = (int)$pdo->lastInsertId();
        $this->logAdminAction($pdo, $adminId, 'create_category', 'categories', $categoryId, ['name' => $name, 'slug' => $slug]);
        Response::json(['success' => true, 'message' => 'Category created'], 201);
    }

    public function categoriesUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $categoryId = (int)$id;
        $input = $this->readJsonInput();
        $name = trim((string)($input['name'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        $categoryType = (string)($input['category_type'] ?? 'core');

        if ($categoryId <= 0 || $name === '' || $slug === '' || !in_array($categoryType, ['core', 'occasion', 'small_bakes', 'gifting', 'course', 'b2b'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid category input'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
            'UPDATE categories SET
                parent_id = :parent_id,
                name = :name,
                slug = :slug,
                description = :description,
                is_active = :is_active,
                sort_order = :sort_order
             WHERE id = :id AND deleted_at IS NULL'
        );

        try {
            $stmt->execute([
                'parent_id' => (int)($input['parent_id'] ?? 0) ?: null,
                'name' => $name,
                'slug' => $slug,
                'description' => $this->nullableString($input['description'] ?? null),
                'is_active' => $this->toBinaryFlag($input['is_active'] ?? 1),
                'sort_order' => (int)($input['sort_order'] ?? 0),
                'id' => $categoryId,
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Failed to update category', 'details' => $e->getMessage()], 500);
            return;
        }

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Category not found or no changes detected'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_category', 'categories', $categoryId, ['name' => $name, 'slug' => $slug]);
        Response::json(['success' => true, 'message' => 'Category updated']);
    }

    public function categoriesDelete(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $categoryId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE categories SET is_active = 0, deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $categoryId]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Category not found or already archived'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'delete_category', 'categories', $categoryId, []);
        Response::json(['success' => true, 'message' => 'Category archived']);
    }

    public function coursesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 60)));

        $sql = 'SELECT id, title, slug, short_description, description, modules, duration_text, mode, fee_amount, image_url, cta_label, cta_url, is_active, created_at
                FROM courses
                WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (title LIKE :q OR slug LIKE :q OR short_description LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function coursesCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $title = trim((string)($input['title'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        $shortDescription = trim((string)($input['short_description'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $durationText = trim((string)($input['duration_text'] ?? ''));
        $mode = trim((string)($input['mode'] ?? 'offline'));
        $feeAmount = round((float)($input['fee_amount'] ?? 0), 2);
        $imageUrl = trim((string)($input['image_url'] ?? ''));
        $ctaLabel = trim((string)($input['cta_label'] ?? ''));
        $ctaUrl = trim((string)($input['cta_url'] ?? ''));
        $modules = trim((string)($input['modules'] ?? ''));

        if ($title === '' || $slug === '' || $shortDescription === '' || $description === '' || $feeAmount <= 0) {
            Response::json(['success' => false, 'message' => 'Missing required course fields'], 422);
            return;
        }
        if (!in_array($mode, ['online', 'offline', 'hybrid'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid course mode'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $exists = $pdo->prepare('SELECT id FROM courses WHERE slug = :slug LIMIT 1');
        $exists->execute(['slug' => $slug]);
        if ($exists->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Course slug already exists'], 409);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO courses (
                title, slug, short_description, description, modules,
                duration_text, mode, fee_amount, image_url, cta_label, cta_url, is_active
            ) VALUES (
                :title, :slug, :short_description, :description, :modules,
                :duration_text, :mode, :fee_amount, :image_url, :cta_label, :cta_url, :is_active
            )'
        );
        $stmt->execute([
            'title' => $title,
            'slug' => $slug,
            'short_description' => $shortDescription,
            'description' => $description,
            'modules' => $modules !== '' ? $modules : null,
            'duration_text' => $durationText !== '' ? $durationText : null,
            'mode' => $mode,
            'fee_amount' => $feeAmount,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'cta_label' => $ctaLabel !== '' ? $ctaLabel : null,
            'cta_url' => $ctaUrl !== '' ? $ctaUrl : null,
            'is_active' => (int)($input['is_active'] ?? 1) === 1 ? 1 : 0,
        ]);

        $courseId = (int)$pdo->lastInsertId();
        $this->logAdminAction($pdo, $adminId, 'create_course', 'courses', $courseId, ['title' => $title]);

        Response::json(['success' => true, 'message' => 'Course created'], 201);
    }

    public function coursesUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $courseId = (int)$id;
        if ($courseId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid course id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $fields = [
            'title', 'slug', 'short_description', 'description', 'modules',
            'duration_text', 'mode', 'fee_amount', 'image_url', 'cta_label', 'cta_url', 'is_active'
        ];
        $set = [];
        $params = ['id' => $courseId];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if ($field === 'mode' && !in_array((string)$input[$field], ['online', 'offline', 'hybrid'], true)) {
                Response::json(['success' => false, 'message' => 'Invalid course mode'], 422);
                return;
            }
            if ($field === 'fee_amount') {
                $value = round((float)$input[$field], 2);
                if ($value <= 0) {
                    Response::json(['success' => false, 'message' => 'fee_amount must be greater than zero'], 422);
                    return;
                }
            } elseif ($field === 'is_active') {
                $value = (int)$input[$field] === 1 ? 1 : 0;
            } else {
                $value = trim((string)$input[$field]);
            }
            $set[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        if (count($set) === 0) {
            Response::json(['success' => false, 'message' => 'No course update payload provided'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        if (isset($params['slug'])) {
            $slugStmt = $pdo->prepare('SELECT id FROM courses WHERE slug = :slug AND id <> :id LIMIT 1');
            $slugStmt->execute(['slug' => $params['slug'], 'id' => $courseId]);
            if ($slugStmt->fetchColumn()) {
                Response::json(['success' => false, 'message' => 'Course slug already exists'], 409);
                return;
            }
        }

        $stmt = $pdo->prepare('UPDATE courses SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Course not found or unchanged'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_course', 'courses', $courseId, $params);
        Response::json(['success' => true, 'message' => 'Course updated']);
    }

    public function coursesDelete(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $courseId = (int)$id;
        if ($courseId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid course id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE courses SET is_active = 0 WHERE id = :id AND is_active = 1');
        $stmt->execute(['id' => $courseId]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Course not found or already inactive'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'archive_course', 'courses', $courseId, []);
        Response::json(['success' => true, 'message' => 'Course archived']);
    }

    public function eventsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $q = trim((string)($_GET['q'] ?? ''));
        $limit = min(120, max(10, (int)($_GET['limit'] ?? 60)));

        $sql = 'SELECT id, title, slug, short_description, event_type, event_category, event_status,
                       starts_at, ends_at, instructor_name, capacity, seats_available, is_published, created_at
                FROM events
                WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (title LIKE :q OR slug LIKE :q OR instructor_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY starts_at ASC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function eventsCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $title = trim((string)($input['title'] ?? ''));
        $slug = trim((string)($input['slug'] ?? ''));
        $shortDescription = trim((string)($input['short_description'] ?? ''));
        $fullDescription = trim((string)($input['full_description'] ?? ''));
        $bannerImage = $this->nullableString($input['banner_image'] ?? null);
        $instructorName = trim((string)($input['instructor_name'] ?? ''));
        $startsAt = trim((string)($input['starts_at'] ?? ''));
        $endsAt = $this->nullableString($input['ends_at'] ?? null);
        $eventType = (string)($input['event_type'] ?? 'event');
        $eventCategory = $this->nullableString($input['event_category'] ?? null);
        $eventStatus = (string)($input['event_status'] ?? 'scheduled');
        $locationText = $this->nullableString($input['location_text'] ?? null);
        $onlineLink = $this->nullableString($input['online_link'] ?? null);
        $capacity = max(1, (int)($input['capacity'] ?? 1));
        $seatsAvailable = max(0, (int)($input['seats_available'] ?? $capacity));
        $registrationCtaLabel = $this->nullableString($input['registration_cta_label'] ?? null);
        $isPublished = (int)($input['is_published'] ?? 1) === 1 ? 1 : 0;

        if ($title === '' || $slug === '' || $shortDescription === '' || $fullDescription === '' || $instructorName === '' || $startsAt === '') {
            Response::json(['success' => false, 'message' => 'Missing required event fields'], 422);
            return;
        }
        if (!in_array($eventType, ['webinar', 'event'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid event_type'], 422);
            return;
        }
        if (!in_array($eventStatus, ['draft', 'scheduled', 'live', 'completed', 'cancelled'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid event_status'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $exists = $pdo->prepare('SELECT id FROM events WHERE slug = :slug LIMIT 1');
        $exists->execute(['slug' => $slug]);
        if ($exists->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Event slug already exists'], 409);
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO events (
                title, slug, short_description, full_description, banner_image,
                instructor_name, starts_at, ends_at, event_type, event_category,
                event_status, location_text, online_link, capacity, seats_available,
                registration_cta_label, is_published
            ) VALUES (
                :title, :slug, :short_description, :full_description, :banner_image,
                :instructor_name, :starts_at, :ends_at, :event_type, :event_category,
                :event_status, :location_text, :online_link, :capacity, :seats_available,
                :registration_cta_label, :is_published
            )'
        );
        $stmt->execute([
            'title' => $title,
            'slug' => $slug,
            'short_description' => $shortDescription,
            'full_description' => $fullDescription,
            'banner_image' => $bannerImage,
            'instructor_name' => $instructorName,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'event_type' => $eventType,
            'event_category' => $eventCategory,
            'event_status' => $eventStatus,
            'location_text' => $locationText,
            'online_link' => $onlineLink,
            'capacity' => $capacity,
            'seats_available' => min($capacity, $seatsAvailable),
            'registration_cta_label' => $registrationCtaLabel,
            'is_published' => $isPublished,
        ]);

        $eventId = (int)$pdo->lastInsertId();
        $this->logAdminAction($pdo, $adminId, 'create_event', 'events', $eventId, ['title' => $title]);

        Response::json(['success' => true, 'message' => 'Event created'], 201);
    }

    public function eventsUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $eventId = (int)$id;
        if ($eventId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid event id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $fields = [
            'title', 'slug', 'short_description', 'full_description', 'banner_image',
            'instructor_name', 'starts_at', 'ends_at', 'event_type', 'event_category',
            'event_status', 'location_text', 'online_link', 'capacity', 'seats_available',
            'registration_cta_label', 'is_published'
        ];
        $set = [];
        $params = ['id' => $eventId];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            if ($field === 'event_type') {
                $value = (string)$input[$field];
                if (!in_array($value, ['webinar', 'event'], true)) {
                    Response::json(['success' => false, 'message' => 'Invalid event_type'], 422);
                    return;
                }
            } elseif ($field === 'event_status') {
                $value = (string)$input[$field];
                if (!in_array($value, ['draft', 'scheduled', 'live', 'completed', 'cancelled'], true)) {
                    Response::json(['success' => false, 'message' => 'Invalid event_status'], 422);
                    return;
                }
            } elseif ($field === 'capacity' || $field === 'seats_available') {
                $value = max(0, (int)$input[$field]);
            } elseif ($field === 'is_published') {
                $value = (int)$input[$field] === 1 ? 1 : 0;
            } else {
                $value = trim((string)$input[$field]);
            }

            $set[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        if (count($set) === 0) {
            Response::json(['success' => false, 'message' => 'No event update payload provided'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        if (isset($params['slug'])) {
            $slugStmt = $pdo->prepare('SELECT id FROM events WHERE slug = :slug AND id <> :id LIMIT 1');
            $slugStmt->execute(['slug' => $params['slug'], 'id' => $eventId]);
            if ($slugStmt->fetchColumn()) {
                Response::json(['success' => false, 'message' => 'Event slug already exists'], 409);
                return;
            }
        }

        if (isset($params['capacity']) && isset($params['seats_available']) && $params['seats_available'] > $params['capacity']) {
            $params['seats_available'] = $params['capacity'];
        }

        $stmt = $pdo->prepare('UPDATE events SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($params);
        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Event not found or unchanged'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_event', 'events', $eventId, $params);
        Response::json(['success' => true, 'message' => 'Event updated']);
    }

    public function eventsDelete(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $eventId = (int)$id;
        if ($eventId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid event id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE events SET is_published = 0, event_status = "draft" WHERE id = :id');
        $stmt->execute(['id' => $eventId]);
        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Event not found'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'archive_event', 'events', $eventId, []);
        Response::json(['success' => true, 'message' => 'Event archived']);
    }

    public function ordersList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $q = trim((string)($_GET['q'] ?? ''));
        $orderStatus = trim((string)($_GET['order_status'] ?? ''));
        $paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 60)));

        $sql = 'SELECT
                    o.id,
                    o.order_number,
                    o.customer_name,
                    o.customer_email,
                    o.customer_phone,
                    o.fulfilment_mode,
                    o.order_status,
                    o.payment_status,
                    o.payment_method,
                    o.scheduled_slot_label,
                    o.delivery_postal_code,
                    o.grand_total,
                    o.discount_total,
                    COALESCE(oi_summary.cake_names, "") AS cake_names,
                    COALESCE(coupon_summary.coupon_info, "") AS coupon_info,
                    COALESCE(coupon_summary.coupon_discount_total, 0) AS coupon_discount_total,
                    o.created_at
                FROM orders o
                LEFT JOIN (
                    SELECT order_id,
                           GROUP_CONCAT(
                               CONCAT(product_name_snapshot, " x ", quantity)
                               ORDER BY id ASC SEPARATOR ", "
                           ) AS cake_names
                    FROM order_items
                    GROUP BY order_id
                ) oi_summary ON oi_summary.order_id = o.id
                LEFT JOIN (
                    SELECT order_id,
                           GROUP_CONCAT(
                               CONCAT(code_snapshot, " (₹", FORMAT(discount_total, 2), ")")
                               ORDER BY id ASC SEPARATOR ", "
                           ) AS coupon_info,
                           SUM(discount_total) AS coupon_discount_total
                    FROM coupon_redemptions
                    GROUP BY order_id
                ) coupon_summary ON coupon_summary.order_id = o.id
                WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_email LIKE :q OR o.customer_phone LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($orderStatus !== '') {
            $sql .= ' AND o.order_status = :order_status';
            $params['order_status'] = $orderStatus;
        }
        if ($paymentStatus !== '') {
            $sql .= ' AND o.payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        $sql .= ' ORDER BY o.created_at DESC LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)],
        ]);
    }

    public function ordersDetail(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $orderStmt = $pdo->prepare(
            'SELECT
                id,
                order_number,
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
                delivery_distance_km,
                delivery_fee,
                subtotal,
                discount_total,
                tax_total,
                grand_total,
                admin_note,
                created_at,
                updated_at
             FROM orders
             WHERE id = :id
             LIMIT 1'
        );
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

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
                oi.customisation_note,
                p.sku AS product_sku
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC'
        );
        $itemsStmt->execute(['order_id' => $orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $timelineStmt = $pdo->prepare(
            'SELECT
                l.id,
                l.action_type,
                l.metadata_json,
                l.created_at,
                a.full_name AS admin_name
             FROM admin_action_logs l
             LEFT JOIN admins a ON a.id = l.admin_id
             WHERE l.target_type = "orders" AND l.target_id = :order_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT 100'
        );
        $timelineStmt->execute(['order_id' => $orderId]);
        $timelineRows = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);
        $timeline = [];
        foreach ($timelineRows as $row) {
            $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
            $actionType = (string)($row['action_type'] ?? '');
            $normalizedMetadata = is_array($metadata) ? $metadata : [];
            $timeline[] = [
                'id' => (int)($row['id'] ?? 0),
                'action_type' => $actionType,
                'admin_name' => (string)($row['admin_name'] ?? 'Admin'),
                'created_at' => (string)($row['created_at'] ?? ''),
                'metadata' => $normalizedMetadata,
                'badge' => $this->timelineBadgeForAction($actionType),
                'label' => $this->timelineLabelForAction($actionType),
                'message' => $this->timelineMessageForAction($actionType, $normalizedMetadata),
            ];
        }

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'order' => $order,
                'items' => $items,
                'timeline' => $timeline,
            ],
        ]);
    }

    public function ordersUpdateStatus(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order id'], 422);
            return;
        }

        $allowedOrderStatuses = ['pending_payment', 'payment_under_review', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'refund_requested', 'refunded', 'partially_refunded', 'fully_refunded', 'rejected'];
        $allowedPaymentStatuses = ['pending', 'under_review', 'paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded', 'failed', 'rejected'];

        $input = $this->readJsonInput();
        $deliveryStatus = trim((string)($input['delivery_status'] ?? ''));
        $orderStatus = trim((string)($input['order_status'] ?? ''));
        if ($orderStatus === '' && $deliveryStatus !== '') {
            $orderStatus = $deliveryStatus;
        }
        $paymentStatus = trim((string)($input['payment_status'] ?? ''));
        $scheduledSlotLabel = trim((string)($input['scheduled_slot_label'] ?? ''));
        $scheduledSlot = trim((string)($input['scheduled_slot'] ?? ''));
        $adminNote = trim((string)($input['admin_note'] ?? ''));
        $productionStatus = trim((string)($input['production_status'] ?? ''));
        $allowedProductionStatuses = ['not_required','pending','in_production','decoration_pending','ready','packed','out_for_delivery','delivered'];

        $set = [];
        $params = ['id' => $orderId];

        if ($orderStatus !== '') {
            if (!in_array($orderStatus, $allowedOrderStatuses, true)) {
                Response::json(['success' => false, 'message' => 'Invalid order status'], 422);
                return;
            }
            // order_status is handled by OrderStateManager below — not added to $set
        }

        if ($paymentStatus !== '') {
            if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
                Response::json(['success' => false, 'message' => 'Invalid payment status'], 422);
                return;
            }
            $set[] = 'payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        if ($scheduledSlotLabel !== '') {
            $set[] = 'scheduled_slot_label = :scheduled_slot_label';
            $params['scheduled_slot_label'] = $scheduledSlotLabel;
        }

        if ($scheduledSlot !== '') {
            $ts = strtotime($scheduledSlot);
            if ($ts === false) {
                Response::json(['success' => false, 'message' => 'Invalid scheduled_slot datetime'], 422);
                return;
            }
            $set[] = 'scheduled_slot = :scheduled_slot';
            $params['scheduled_slot'] = date('Y-m-d H:i:s', $ts);
        }

        if ($adminNote !== '') {
            $set[] = 'admin_note = :admin_note';
            $params['admin_note'] = $adminNote;
        }

        if ($productionStatus !== '') {
            if (!in_array($productionStatus, $allowedProductionStatuses, true)) {
                Response::json(['success' => false, 'message' => 'Invalid production status'], 422);
                return;
            }
            $set[] = 'production_status = :production_status';
            $params['production_status'] = $productionStatus;
        }

        if (count($set) === 0 && $orderStatus === '') {
            Response::json(['success' => false, 'message' => 'No update payload provided'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $existsStmt = $pdo->prepare('SELECT id, order_status, payment_status, fulfilment_mode FROM orders WHERE id = :id LIMIT 1');
        $existsStmt->execute(['id' => $orderId]);
        $existingOrder = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingOrder) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        $resolvedOrderStatus = $orderStatus;

        if ($orderStatus !== '') {
            $adminRole        = (string)($_SESSION['admin_role']        ?? '');
            $adminPermissions = (array) ($_SESSION['admin_permissions'] ?? []);
            $stateManager     = new \App\Services\OrderStateManager();
            $smResult = $stateManager->transition($pdo, $orderId, $orderStatus, $adminId, [
                'admin_role'        => $adminRole,
                'admin_permissions' => $adminPermissions,
                'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
                'reason'            => 'Admin API status update',
            ]);
            if (!$smResult['success']) {
                Response::json(['success' => false, 'message' => $smResult['message']], 422);
                return;
            }
            $resolvedOrderStatus = (string)($smResult['new_status'] ?? $orderStatus);
        }

        if ($paymentStatus !== '') {
            $currentPaymentStatus = (string)($existingOrder['payment_status'] ?? 'pending');
            if (!$this->isValidPaymentStatusTransition($currentPaymentStatus, $paymentStatus)) {
                Response::json([
                    'success' => false,
                    'message' => 'Invalid payment status transition: ' . $currentPaymentStatus . ' -> ' . $paymentStatus,
                ], 422);
                return;
            }
        }

        if (count($set) > 0) {
            $sql = 'UPDATE orders SET ' . implode(', ', $set) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // CRM ready hook — notify customer when production_status transitions to 'ready'
        // Only fires when order is in 'confirmed' state (forward transition → preparing)
        // to avoid double-notifications if the admin marks production ready on an already-notified order.
        if ($productionStatus === 'ready') {
            try {
                $currentOrderStatus = (string)($existingOrder['order_status'] ?? '');
                if ($currentOrderStatus === 'confirmed') {
                    $automation = new \App\Services\OrderAutomationService();
                    $automation->handleStatusChange($pdo, $orderId, 'preparing', $adminId);
                }
            } catch (\Throwable $crmErr) {
                error_log('[ordersUpdateStatus] CRM ready hook error: ' . $crmErr->getMessage());
            }
        }

        if ($resolvedOrderStatus !== '') {
            try {
                $automation = new \App\Services\OrderAutomationService();
                $automation->handleStatusChange($pdo, $orderId, $resolvedOrderStatus, $adminId);
            } catch (\Throwable $automationErr) {
                error_log('[ordersUpdateStatus] status automation error: ' . $automationErr->getMessage());
            }
        }

        $this->logAdminAction($pdo, $adminId, 'update_order_status', 'orders', $orderId, [
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'scheduled_slot_label' => $scheduledSlotLabel,
            'scheduled_slot' => $params['scheduled_slot'] ?? null,
            'admin_note' => $adminNote,
            'production_status' => $productionStatus,
        ]);

        Response::json(['success' => true, 'message' => 'Order updated']);
    }

    /**
     * POST /api/admin/orders/:id/confirm-payment
     * Marks payment as paid, confirms slot reservation, queues customer email + CRM.
     */
    public function ordersConfirmPayment(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) { return; }

        $input = $this->readJsonInput();

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order ID'], 422);
            return;
        }

        $pdo = self::db();
        if (!$pdo) { return; }

        // Fetch order
        $orderStmt = $pdo->prepare(
            'SELECT id, order_status, payment_status, payment_method, customer_email, customer_name,
                order_number, scheduled_slot_label, grand_total, COALESCE(refund_amount, 0) AS refund_amount
             FROM orders WHERE id = :id LIMIT 1'
        );
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        if (in_array((string)$order['payment_status'], ['paid', 'credit'], true)) {
            Response::json(['success' => true, 'message' => 'Payment already confirmed.']);
            return;
        }

        $effectivePaymentMethod = strtolower(trim((string)($input['payment_method'] ?? '')));
        if (!in_array($effectivePaymentMethod, ['cod', 'upi_manual', 'gateway', 'credit'], true)) {
            Response::json(['success' => false, 'message' => 'Please select a valid payment mode (Cash, UPI/Bank, or Credit).'], 422);
            return;
        }

        $adminRole = strtolower(trim((string)($_SESSION['admin_role'] ?? '')));
        $adminPermissions = isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
            ? array_map(static fn($v): string => (string)$v, $_SESSION['admin_permissions'])
            : [];
        $hasDiscountOverridePermission = $adminRole === 'super_admin'
            || in_array('business_settings', $adminPermissions, true)
            || in_array('order_credit', $adminPermissions, true);

        $managerOverride = !empty($input['manager_override']);
        $discountReason = trim((string)($input['discount_reason'] ?? ''));

        $pdo->beginTransaction();
        try {
            // 1. Confirm slot reservation (checks capacity inside transaction)
            $slotSvc = new \App\Services\SlotService($pdo);
            $slotResult = $slotSvc->confirmSlotReservation($orderId);
            if (!$slotResult['success'] && !empty($slotResult['waitlist'])) {
                $pdo->rollBack();
                Response::json([
                    'success' => false,
                    'message' => $slotResult['message'],
                    'waitlist' => true,
                ], 409);
                return;
            }

            // 2. Confirm financial state using shared payment confirmation service.
            $paymentService = new \App\Services\OrderPaymentConfirmationService();
            $paymentResult = $paymentService->confirmOrderPayment($pdo, $orderId, [
                'payment_method' => $effectivePaymentMethod,
                'received_amount' => array_key_exists('received_amount', $input) ? $input['received_amount'] : null,
                'discount_reason' => $discountReason,
                'manager_override' => $managerOverride,
                'has_discount_override_permission' => $hasDiscountOverridePermission,
                'admin_id' => $adminId,
                'admin_name' => (string)($_SESSION['admin_name'] ?? 'Admin'),
                'source_reference' => 'AdminApiController::ordersConfirmPayment',
                'source_event' => 'admin_api_confirm_payment',
            ]);

            if (!$paymentResult['success']) {
                $pdo->rollBack();
                Response::json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'Confirmation failed. Please try again.',
                ], (int)($paymentResult['http_status'] ?? 422));
                return;
            }

            $paymentData = is_array($paymentResult['data'] ?? null) ? $paymentResult['data'] : [];
            $discountAmount = round((float)($paymentData['discount_amount'] ?? 0), 2);
            $recognizedAmount = round((float)($paymentData['recognized_amount'] ?? 0), 2);

            $pdo->commit();

            $this->logAdminAction($pdo, $adminId, 'confirm_payment', 'orders', $orderId, [
                'slot_result' => $slotResult['message'] ?? '',
                'received_amount' => $recognizedAmount,
                'discount_amount' => $discountAmount,
                'discount_reason' => $discountReason,
                'manager_override' => $managerOverride,
                'payment_method' => $effectivePaymentMethod,
            ]);

            // 3. Queue automation (non-fatal)
            try {
                $automation = new \App\Services\OrderAutomationService();
                $automation->handleStatusChange($pdo, $orderId, 'confirmed', $adminId);
            } catch (\Throwable $e) {
                error_log('[ordersConfirmPayment] automation error: ' . $e->getMessage());
            }

            Response::json([
                'success' => true,
                'message' => ($paymentResult['message'] ?? 'Payment confirmed.') . ' Slot reserved. Customer will be notified.',
                'slot'    => $slotResult,
                'data' => [
                    'received_amount' => $recognizedAmount,
                    'discount_amount' => $discountAmount,
                    'discount_reason' => $discountReason,
                    'payment_method' => $effectivePaymentMethod,
                ],
            ]);

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[ordersConfirmPayment] order_id=' . $orderId . ' admin_id=' . $adminId . ' error=' . get_class($e) . ': ' . $e->getMessage());
            Response::json(['success' => false, 'message' => 'Confirmation failed. Please try again.'], 500);
        }
    }

    /**
     * POST /api/admin/orders/:id/reject-payment
     * Releases slot hold, marks order cancelled, queues rejection notification.
     */
    public function ordersRejectPayment(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) { return; }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order ID'], 422);
            return;
        }

        $input = (array)(json_decode(file_get_contents('php://input'), true) ?? []);
        $reason = substr(trim((string)($input['reason'] ?? '')), 0, 255);

        $pdo = self::db();
        if (!$pdo) { return; }

        $orderStmt = $pdo->prepare(
            'SELECT id, order_status, payment_status FROM orders WHERE id = :id LIMIT 1'
        );
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$order) {
            Response::json(['success' => false, 'message' => 'Order not found'], 404);
            return;
        }

        if (in_array((string)$order['order_status'], ['confirmed', 'ready', 'completed', 'delivered'], true)) {
            Response::json(['success' => false, 'message' => 'Cannot reject a confirmed/completed order.'], 409);
            return;
        }

        // Release slot hold
        $slotSvc = new \App\Services\SlotService($pdo);
        $slotSvc->releaseReservation($orderId, 'released');

        // Mark order cancelled + payment rejected
        $pdo->prepare(
            'UPDATE orders SET
                order_status = "cancelled",
                payment_status = "rejected",
                admin_note = CONCAT(COALESCE(admin_note,""), :note)
             WHERE id = :id'
        )->execute([
            'id'   => $orderId,
            'note' => $reason !== '' ? "\n[Rejected] {$reason}" : "\n[Payment rejected]",
        ]);

        $this->logAdminAction($pdo, $adminId, 'reject_payment', 'orders', $orderId, [
            'reason' => $reason,
        ]);

        // Queue rejection notification (non-fatal)
        try {
            $automation = new \App\Services\OrderAutomationService();
            $automation->handleOrderPlaced($pdo, $orderId, 'admin_reject');
        } catch (\Throwable $e) {
            error_log('[ordersRejectPayment] automation error: ' . $e->getMessage());
        }

        Response::json(['success' => true, 'message' => 'Order rejected and slot released.']);
    }

    public function ordersExportCsv(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $q = trim((string)($_GET['q'] ?? ''));
        $orderStatus = trim((string)($_GET['order_status'] ?? ''));
        $paymentStatus = trim((string)($_GET['payment_status'] ?? ''));

        $sql = 'SELECT
                    o.id,
                    o.order_number,
                    o.customer_name,
                    o.customer_email,
                    o.customer_phone,
                    COALESCE(oi_summary.cake_names, "") AS cake_names,
                    o.fulfilment_mode,
                    o.order_status,
                    o.payment_status,
                    o.payment_method,
                    o.scheduled_slot_label,
                    o.delivery_postal_code,
                    o.delivery_fee,
                    o.subtotal,
                    o.discount_total,
                    o.tax_total,
                    o.grand_total,
                    COALESCE(coupon_summary.coupon_info, "") AS coupon_info,
                    COALESCE(coupon_summary.coupon_discount_total, 0) AS coupon_discount_total,
                    o.created_at
                FROM orders o
                LEFT JOIN (
                    SELECT order_id,
                           GROUP_CONCAT(
                               CONCAT(product_name_snapshot, " x ", quantity)
                               ORDER BY id ASC SEPARATOR ", "
                           ) AS cake_names
                    FROM order_items
                    GROUP BY order_id
                ) oi_summary ON oi_summary.order_id = o.id
                LEFT JOIN (
                    SELECT order_id,
                           GROUP_CONCAT(
                               CONCAT(code_snapshot, " (₹", FORMAT(discount_total, 2), ")")
                               ORDER BY id ASC SEPARATOR ", "
                           ) AS coupon_info,
                           SUM(discount_total) AS coupon_discount_total
                    FROM coupon_redemptions
                    GROUP BY order_id
                ) coupon_summary ON coupon_summary.order_id = o.id
                WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_email LIKE :q OR o.customer_phone LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($orderStatus !== '') {
            $sql .= ' AND o.order_status = :order_status';
            $params['order_status'] = $orderStatus;
        }
        if ($paymentStatus !== '') {
            $sql .= ' AND o.payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        $sql .= ' ORDER BY o.created_at DESC LIMIT 5000';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $headers = [
            'id', 'order_number', 'customer_name', 'customer_email', 'customer_phone', 'cake_names',
            'fulfilment_mode', 'order_status', 'payment_status', 'payment_method',
            'scheduled_slot_label', 'delivery_postal_code', 'delivery_fee',
            'subtotal', 'discount_total', 'tax_total', 'grand_total', 'coupon_info', 'coupon_discount_total', 'created_at',
        ];
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ExcelService::export(
            $headers,
            $rows,
            'cakeouflage-orders-export-' . date('Ymd-His') . '.xlsx',
            [1 => 18, 2 => 22, 3 => 28, 4 => 16, 5 => 14, 6 => 14, 7 => 16, 8 => 16,
             9 => 22, 10 => 14, 11 => 12, 12 => 12, 13 => 14, 14 => 12, 15 => 14, 16 => 20]
        );
    }

    public function productMediaAttach(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        if ($productId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $path = trim((string)($input['path'] ?? ''));
        $mode = trim((string)($input['mode'] ?? 'featured'));
        $altText = $this->nullableString($input['alt_text'] ?? null);

        if ($path === '' || !str_starts_with($path, '/uploads/media/')) {
            Response::json(['success' => false, 'message' => 'Invalid media path'], 422);
            return;
        }
        if (!in_array($mode, ['featured', 'gallery'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid attach mode'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $existsStmt = $pdo->prepare('SELECT id, featured_image FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $existsStmt->execute(['id' => $productId]);
        $product = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            Response::json(['success' => false, 'message' => 'Product not found'], 404);
            return;
        }

        $absolutePath = $this->projectRoot() . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($absolutePath)) {
            Response::json(['success' => false, 'message' => 'Media file does not exist on disk'], 422);
            return;
        }

        $oldFeaturedImage = trim((string)($product['featured_image'] ?? ''));

        if ($mode === 'gallery') {
            $secondaryExistsStmt = $pdo->prepare(
                'SELECT id, image_url
                 FROM product_images
                 WHERE product_id = :product_id AND sort_order = 1
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $secondaryExistsStmt->execute(['product_id' => $productId]);
            $secondary = $secondaryExistsStmt->fetch(PDO::FETCH_ASSOC);

            if ($secondary) {
                if ((string)($secondary['image_url'] ?? '') === $path) {
                    Response::json(['success' => true, 'message' => 'Media attached to product']);
                    return;
                }

                Response::json([
                    'success' => false,
                    'message' => 'Only two images are allowed per product (featured + one secondary). Remove or replace image 2 first.'
                ], 422);
                return;
            }

            $slotCountStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = :product_id');
            $slotCountStmt->execute(['product_id' => $productId]);
            $slotCount = (int)$slotCountStmt->fetchColumn();
            if ($slotCount >= 2) {
                Response::json([
                    'success' => false,
                    'message' => 'Only two images are allowed per product (featured + one secondary).'
                ], 422);
                return;
            }
        }

        try {
            $pdo->beginTransaction();

            if ($mode === 'featured') {
                $updateProduct = $pdo->prepare('UPDATE products SET featured_image = :path WHERE id = :id');
                $updateProduct->execute(['path' => $path, 'id' => $productId]);

                if ($oldFeaturedImage !== '' && $oldFeaturedImage !== $path) {
                    $deleteOld = $pdo->prepare('DELETE FROM product_images WHERE product_id = :product_id AND image_url = :image_url');
                    $deleteOld->execute(['product_id' => $productId, 'image_url' => $oldFeaturedImage]);
                }

                $imgExists = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :product_id AND image_url = :image_url LIMIT 1');
                $imgExists->execute(['product_id' => $productId, 'image_url' => $path]);
                $imgId = (int)($imgExists->fetchColumn() ?: 0);

                if ($imgId > 0) {
                    $updateImg = $pdo->prepare('UPDATE product_images SET alt_text = :alt_text, sort_order = 0 WHERE id = :id');
                    $updateImg->execute(['alt_text' => $altText, 'id' => $imgId]);
                } else {
                    $insertImg = $pdo->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order) VALUES (:product_id, :image_url, :alt_text, 0)');
                    $insertImg->execute(['product_id' => $productId, 'image_url' => $path, 'alt_text' => $altText]);
                }
            } else {
                $imgExists = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :product_id AND image_url = :image_url LIMIT 1');
                $imgExists->execute(['product_id' => $productId, 'image_url' => $path]);
                if (!$imgExists->fetchColumn()) {
                    $insertImg = $pdo->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order) VALUES (:product_id, :image_url, :alt_text, :sort_order)');
                    $insertImg->execute(['product_id' => $productId, 'image_url' => $path, 'alt_text' => $altText, 'sort_order' => 1]);
                }
            }

            $this->enforceProductImageSlotLimit($pdo, $productId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to attach media', 'details' => $e->getMessage()], 500);
            return;
        }

        if ($mode === 'featured') {
            $this->deleteMediaFileIfUnreferenced($pdo, $oldFeaturedImage, $path);
        }

        $this->logAdminAction($pdo, $adminId, 'attach_media_product', 'products', $productId, [
            'path' => $path,
            'mode' => $mode,
        ]);

        Response::json(['success' => true, 'message' => 'Media attached to product']);
    }

    public function productMediaList(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $productId = (int)$id;
        if ($productId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
            'SELECT id, image_url, alt_text, sort_order, created_at
             FROM product_images
             WHERE product_id = :product_id
               ORDER BY sort_order ASC, id ASC
               LIMIT 2'
        );
        $stmt->execute(['product_id' => $productId]);

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function productMediaReorder(string $id, string $imageId): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        $productImageId = (int)$imageId;
        if ($productId <= 0 || $productImageId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product/image id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $direction = trim((string)($input['direction'] ?? ''));
        if (!in_array($direction, ['up', 'down'], true)) {
            Response::json(['success' => false, 'message' => 'direction must be up or down'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $currentStmt = $pdo->prepare(
            'SELECT id, sort_order
             FROM product_images
             WHERE id = :id AND product_id = :product_id
             LIMIT 1'
        );
        $currentStmt->execute(['id' => $productImageId, 'product_id' => $productId]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            Response::json(['success' => false, 'message' => 'Image not found for product'], 404);
            return;
        }

        $operator = $direction === 'up' ? '<' : '>';
        $sortDirection = $direction === 'up' ? 'DESC' : 'ASC';

        $neighborStmt = $pdo->prepare(
            'SELECT id, sort_order
             FROM product_images
             WHERE product_id = :product_id AND sort_order ' . $operator . ' :sort_order
             ORDER BY sort_order ' . $sortDirection . ', id ' . $sortDirection . '
             LIMIT 1'
        );
        $neighborStmt->execute([
            'product_id' => $productId,
            'sort_order' => (int)$current['sort_order'],
        ]);
        $neighbor = $neighborStmt->fetch(PDO::FETCH_ASSOC);

        if (!$neighbor) {
            Response::json(['success' => true, 'message' => 'No reorder needed']);
            return;
        }

        try {
            $pdo->beginTransaction();

            $setA = $pdo->prepare('UPDATE product_images SET sort_order = :sort_order WHERE id = :id');
            $setA->execute([
                'sort_order' => (int)$neighbor['sort_order'],
                'id' => (int)$current['id'],
            ]);

            $setB = $pdo->prepare('UPDATE product_images SET sort_order = :sort_order WHERE id = :id');
            $setB->execute([
                'sort_order' => (int)$current['sort_order'],
                'id' => (int)$neighbor['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to reorder gallery', 'details' => $e->getMessage()], 500);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'reorder_product_media', 'products', $productId, [
            'image_id' => $productImageId,
            'direction' => $direction,
        ]);

        Response::json(['success' => true, 'message' => 'Gallery reordered']);
    }

    public function productMediaReorderAll(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        if ($productId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $orderedIds = $input['ordered_ids'] ?? null;
        if (!is_array($orderedIds) || count($orderedIds) < 1) {
            Response::json(['success' => false, 'message' => 'ordered_ids must be a non-empty array'], 422);
            return;
        }

        $normalizedIds = [];
        foreach ($orderedIds as $rawId) {
            $imageId = (int)$rawId;
            if ($imageId > 0 && !in_array($imageId, $normalizedIds, true)) {
                $normalizedIds[] = $imageId;
            }
        }

        if (count($normalizedIds) < 1) {
            Response::json(['success' => false, 'message' => 'ordered_ids has no valid image IDs'], 422);
            return;
        }
        if (count($normalizedIds) > 2) {
            Response::json(['success' => false, 'message' => 'Only two images are allowed per product'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['product_id' => $productId]);
        $existingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingIds = array_map(static function (array $row): int {
            return (int)($row['id'] ?? 0);
        }, $existingRows);
        $existingIds = array_values(array_filter($existingIds, static function (int $value): bool {
            return $value > 0;
        }));

        if (count($existingIds) !== count($normalizedIds)) {
            Response::json(['success' => false, 'message' => 'ordered_ids count mismatch for product gallery'], 422);
            return;
        }

        sort($existingIds);
        $compareIds = $normalizedIds;
        sort($compareIds);
        if ($existingIds !== $compareIds) {
            Response::json(['success' => false, 'message' => 'ordered_ids must contain exactly product gallery image IDs'], 422);
            return;
        }

        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare('UPDATE product_images SET sort_order = :sort_order WHERE id = :id AND product_id = :product_id');
            foreach ($normalizedIds as $index => $imageId) {
                $updateStmt->execute([
                    'sort_order' => $index,
                    'id' => $imageId,
                    'product_id' => $productId,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to reorder gallery', 'details' => $e->getMessage()], 500);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'reorder_product_media_drag', 'products', $productId, [
            'ordered_ids' => $normalizedIds,
        ]);

        Response::json(['success' => true, 'message' => 'Gallery reorder saved']);
    }

    public function productMediaDelete(string $id, string $imageId): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $productId = (int)$id;
        $productImageId = (int)$imageId;
        if ($productId <= 0 || $productImageId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid product/image id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $findStmt = $pdo->prepare(
            'SELECT id, image_url
             FROM product_images
             WHERE id = :id AND product_id = :product_id
             LIMIT 1'
        );
        $findStmt->execute(['id' => $productImageId, 'product_id' => $productId]);
        $image = $findStmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            Response::json(['success' => false, 'message' => 'Image not found for product'], 404);
            return;
        }

        try {
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare('DELETE FROM product_images WHERE id = :id');
            $deleteStmt->execute(['id' => $productImageId]);

            $featuredStmt = $pdo->prepare('UPDATE products SET featured_image = NULL WHERE id = :product_id AND featured_image = :image_url');
            $featuredStmt->execute(['product_id' => $productId, 'image_url' => (string)$image['image_url']]);

            $this->normalizeProductImageOrder($pdo, $productId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to remove gallery image', 'details' => $e->getMessage()], 500);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'delete_product_media', 'products', $productId, [
            'image_id' => $productImageId,
            'image_url' => (string)$image['image_url'],
        ]);

        Response::json(['success' => true, 'message' => 'Gallery image removed']);
    }

    public function bulkTemplate(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $spreadsheet = ExcelService::buildProductTemplate($pdo);
        ExcelService::streamXlsx($spreadsheet, 'cakeouflage-product-template.xlsx');
    }

    public function productsExportCsv(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $sql = 'SELECT
                    COALESCE(collection.name, \'\') AS category,
                    COALESCE(subcategory.name, \'\') AS subcategory,
                    p.name AS product_name,
                    p.is_featured,
                    p.is_bestseller,
                    p.is_chef_special,
                    p.is_b2b_enabled,
                COALESCE(NULLIF(p.dietary_type, \'\'), IF(p.is_veg = 1, \'veg\', \'nonveg\')) AS dietary_type,
                    p.dietary_tag,
                    COALESCE(
                        GROUP_CONCAT(
                            CONCAT(
                                COALESCE(NULLIF(pv.variant_name, \'\'), NULLIF(pv.variant_label, \'\'), pv.weight_or_size),
                                \'(\', COALESCE(NULLIF(pv.unit_type, \'\'), \'custom\'), \')\',
                                \':\', FORMAT(pv.price, 2)
                            )
                            ORDER BY pv.is_default DESC, pv.id ASC
                            SEPARATOR \' | \'
                        ),
                        \'\'
                    ) AS variants
                FROM products p
                LEFT JOIN categories collection ON collection.id = p.collection_category_id
                LEFT JOIN categories subcategory ON subcategory.id = p.subcategory_id
                LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
                WHERE p.deleted_at IS NULL
                GROUP BY p.id, collection.name, subcategory.name, p.name, p.is_featured, p.is_bestseller, p.is_chef_special, p.is_b2b_enabled, p.is_veg, p.dietary_type, p.dietary_tag
                ORDER BY p.created_at DESC
                LIMIT 10000';

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            Response::json(['success' => false, 'message' => 'Unable to generate products CSV'], 500);
            return;
        }

        $headers = ['Category', 'Subcategory', 'Product Name', 'Dietary Type', 'Tags', 'Variants'];
        $exportRows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $tags = [];
            if ((int)($row['is_featured'] ?? 0)) $tags[] = 'featured';
            if ((int)($row['is_bestseller'] ?? 0)) $tags[] = 'bestseller';
            if ((int)($row['is_chef_special'] ?? 0)) $tags[] = 'chefs_special';
            if ((int)($row['is_b2b_enabled'] ?? 0)) $tags[] = 'b2b';
            if (($row['dietary_tag'] ?? 'regular') === 'eggless') $tags[] = 'eggless';
            $exportRows[] = [
                (string)($row['category'] ?? ''),
                (string)($row['subcategory'] ?? ''),
                (string)($row['product_name'] ?? ''),
                normalizeDietaryType((string)($row['dietary_type'] ?? 'veg'), $pdo),
                implode('|', $tags),
                (string)($row['variants'] ?? ''),
            ];
        }
        ExcelService::export(
            $headers,
            $exportRows,
            'cakeouflage-products-export-' . date('Ymd-His') . '.xlsx',
            [1 => 22, 2 => 22, 3 => 30, 4 => 14, 5 => 30, 6 => 50]
        );
    }

    public function bulkImportProducts(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::json(['success' => false, 'message' => 'A CSV or Excel (.xlsx) file is required under field "file"'], 422);
            return;
        }

        $file = $_FILES['file'];
        $tmpName = (string)($file['tmp_name'] ?? '');
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $name = (string)($file['name'] ?? 'upload.csv');

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            Response::json(['success' => false, 'message' => 'Upload failed'], 422);
            return;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            Response::json(['success' => false, 'message' => 'Only CSV and Excel (.xlsx) uploads are supported'], 422);
            return;
        }

        $backupDirectory = $this->ensureDirectory($this->storagePath('import-backups'));
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'upload.csv';
        $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . date('Ymd-His') . '-' . $safeName;
        if (!@copy($tmpName, $backupPath)) {
            Response::json(['success' => false, 'message' => 'Failed to create CSV backup before import'], 500);
            return;
        }

        // --- Common setup ---
        $pdo = self::db(); if (!$pdo) return;
        $dryRun = $this->toBinaryFlag($_POST['dry_run'] ?? 0) === 1;
        $strictVariants = $this->toBinaryFlag($_POST['strict_variants'] ?? 1) === 1;
        $abortOnError = $this->toBinaryFlag($_POST['abort_on_error'] ?? 0) === 1;
        $requiredVariantLabels = ['0.5 kg', '1 lb', '1.5 lb', '2 lb', '2.5 lb', '3 lb'];

        $createdCount = 0;
        $updatedCount = 0;
        $failedRows = [];
        $duplicateSkus = [];
        $lineNumber = 1;

        // --- Read header + rows (csv or xlsx) into $allImportRows ---
        if ($extension === 'xlsx') {
            try {
                $allImportRows = ExcelService::readUploadedXlsx($backupPath);
            } catch (\Throwable $xlsxReadEx) {
                Response::json(['success' => false, 'message' => 'Could not read Excel file: ' . $xlsxReadEx->getMessage()], 422);
                return;
            }
            if (count($allImportRows) === 0) {
                Response::json(['success' => false, 'message' => 'Excel file appears to be empty'], 422);
                return;
            }
            $header = array_shift($allImportRows);
            if (!is_array($header) || count($header) === 0) {
                Response::json(['success' => false, 'message' => 'Excel header row is required'], 422);
                return;
            }
        } else {
            $handle = fopen($tmpName, 'rb');
            if ($handle === false) {
                Response::json(['success' => false, 'message' => 'Cannot read uploaded file'], 422);
                return;
            }
            $header = fgetcsv($handle);
            if (!is_array($header) || count($header) === 0) {
                fclose($handle);
                Response::json(['success' => false, 'message' => 'CSV header row is required'], 422);
                return;
            }
            $allImportRows = [];
            while (($csvRow = fgetcsv($handle)) !== false) {
                $allImportRows[] = $csvRow;
            }
            fclose($handle);
        }

        $headerMap = $this->normalizeHeaderMap($header);

        // --- Process rows ---
        foreach ($allImportRows as $row) {
            $lineNumber++;
            $record = $this->mapImportRow(array_values((array)$row), $headerMap);
            $validation = $this->validateImportRecord($record, $strictVariants, $requiredVariantLabels);
            if ($validation !== null) {
                $failedRows[] = ['line' => $lineNumber, 'reason' => $validation, 'sku' => (string)($record['sku'] ?? '')];
                if ($abortOnError) {
                    break;
                }
                continue;
            }

            $parsedVariants = $this->parseImportVariants(
                (string)$record['variant_info'],
                (float)$record['price'],
                max(0, (int)$record['stock']),
                $strictVariants
            );

            if ($strictVariants && count($parsedVariants) === 0) {
                $failedRows[] = ['line' => $lineNumber, 'reason' => 'variant_info could not be parsed with strict mode', 'sku' => (string)$record['sku']];
                if ($abortOnError) {
                    break;
                }
                continue;
            }

            $categoryId = $this->findCategoryIdBySlug($pdo, (string)$record['category_slug']);
            if ($categoryId === null) {
                $failedRows[] = ['line' => $lineNumber, 'reason' => 'Unknown category_slug', 'sku' => (string)$record['sku']];
                if ($abortOnError) {
                    break;
                }
                continue;
            }

            $existingStmt = $pdo->prepare('SELECT id FROM products WHERE sku = :sku LIMIT 1');
            $existingStmt->execute(['sku' => (string)$record['sku']]);
            $existingId = (int)($existingStmt->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $duplicateSkus[] = (string)$record['sku'];
                $updatedCount++;
            } else {
                $createdCount++;
            }

            if ($dryRun) {
                continue;
            }

            $recordDietaryType = normalizeDietaryType((string)($record['dietary_type'] ?? ''), $pdo);
            if ((string)($record['dietary_type'] ?? '') === '' && in_array((int)$record['is_veg'], [0, 1], true)) {
                $recordDietaryType = normalizeDietaryType((int)$record['is_veg'] === 1 ? 'veg' : 'nonveg', $pdo);
            }
            $recordDietaryTag = $this->resolveDietaryTag(
                str_contains((string)$record['tags'], 'eggless') ? 'eggless' : 'regular',
                $recordDietaryType
            );
            $recordIsVeg = dietaryTypeToIsVeg($recordDietaryType);

            try {
                $pdo->beginTransaction();

                if ($existingId > 0) {
                    $updateStmt = $pdo->prepare(
                        'UPDATE products SET
                            name = :name,
                            slug = :slug,
                            short_description = :short_description,
                            description = :description,
                            long_description = :long_description,
                            collection_category_id = :collection_category_id,
                            starting_price = :starting_price,
                            base_price = :base_price,
                            discount_price = :discount_price,
                            stock_quantity = :stock_quantity,
                            featured_image = :featured_image,
                            is_featured = :is_featured,
                            is_bestseller = :is_bestseller,
                            is_chef_special = :is_chef_special,
                            dietary_tag = :dietary_tag,
                            is_veg = :is_veg,
                            availability_status = :availability_status,
                            deleted_at = NULL
                         WHERE id = :id'
                    );
                    $updateStmt->execute([
                        'name' => $record['product_name'],
                        'slug' => $this->slugify((string)$record['product_name']),
                        'short_description' => mb_substr((string)$record['description'], 0, 250),
                        'description' => (string)$record['description'],
                        'long_description' => (string)$record['description'],
                        'collection_category_id' => $categoryId,
                        'starting_price' => (float)$record['price'],
                        'base_price' => (float)$record['price'],
                        'discount_price' => $record['discount_price'] !== '' ? (float)$record['discount_price'] : null,
                        'stock_quantity' => max(0, (int)$record['stock']),
                        'featured_image' => $this->nullableString($record['image_url']),
                        'is_featured' => str_contains((string)$record['tags'], 'featured') ? 1 : 0,
                        'is_bestseller' => str_contains((string)$record['tags'], 'bestseller') ? 1 : 0,
                        'is_chef_special' => str_contains((string)$record['tags'], 'chefs_special') ? 1 : 0,
                        'dietary_tag' => $recordDietaryTag,
                        'is_veg' => $recordIsVeg,
                        'availability_status' => max(0, (int)$record['stock']) > 0 ? 'in_stock' : 'out_of_stock',
                        'id' => $existingId,
                    ]);
                    if ($this->tableHasColumn($pdo, 'products', 'dietary_type')) {
                        $dietaryTypeStmt = $pdo->prepare('UPDATE products SET dietary_type = :dietary_type WHERE id = :id');
                        $dietaryTypeStmt->execute(['dietary_type' => $recordDietaryType, 'id' => $existingId]);
                    }
                    $this->replaceProductVariants($pdo, $existingId, $parsedVariants);
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO products (
                            name, slug, short_description, description, long_description, sku, collection_category_id,
                            dietary_tag, is_veg, availability_status, lead_time_hours, delivery_eligible, pickup_eligible,
                            featured_image, starting_price, base_price, discount_price, stock_quantity,
                            is_featured, is_bestseller, is_chef_special, is_b2b_enabled
                        ) VALUES (
                            :name, :slug, :short_description, :description, :long_description, :sku, :collection_category_id,
                            :dietary_tag, :is_veg, :availability_status, 24, 1, 1,
                            :featured_image, :starting_price, :base_price, :discount_price, :stock_quantity,
                            :is_featured, :is_bestseller, :is_chef_special, :is_b2b_enabled
                        )'
                    );
                    $insertStmt->execute([
                        'name' => $record['product_name'],
                        'slug' => $this->slugify((string)$record['product_name']),
                        'short_description' => mb_substr((string)$record['description'], 0, 250),
                        'description' => (string)$record['description'],
                        'long_description' => (string)$record['description'],
                        'sku' => (string)$record['sku'],
                        'collection_category_id' => $categoryId,
                        'dietary_tag' => $recordDietaryTag,
                        'is_veg' => $recordIsVeg,
                        'availability_status' => max(0, (int)$record['stock']) > 0 ? 'in_stock' : 'out_of_stock',
                        'featured_image' => $this->nullableString($record['image_url']),
                        'starting_price' => (float)$record['price'],
                        'base_price' => (float)$record['price'],
                        'discount_price' => $record['discount_price'] !== '' ? (float)$record['discount_price'] : null,
                        'stock_quantity' => max(0, (int)$record['stock']),
                        'is_featured' => str_contains((string)$record['tags'], 'featured') ? 1 : 0,
                        'is_bestseller' => str_contains((string)$record['tags'], 'bestseller') ? 1 : 0,
                        'is_chef_special' => str_contains((string)$record['tags'], 'chefs_special') ? 1 : 0,
                        'is_b2b_enabled' => str_contains((string)$record['tags'], 'b2b') ? 1 : 0,
                    ]);
                    $productId = (int)$pdo->lastInsertId();
                    if ($this->tableHasColumn($pdo, 'products', 'dietary_type')) {
                        $dietaryTypeStmt = $pdo->prepare('UPDATE products SET dietary_type = :dietary_type WHERE id = :id');
                        $dietaryTypeStmt->execute(['dietary_type' => $recordDietaryType, 'id' => $productId]);
                    }
                    $this->replaceProductVariants($pdo, $productId, $parsedVariants);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $failedRows[] = ['line' => $lineNumber, 'reason' => 'DB error: ' . $e->getMessage(), 'sku' => (string)$record['sku']];
                if ($abortOnError) {
                    break;
                }
            }
        }

        $logDirectory = $this->ensureDirectory($this->storagePath('import-logs'));
        $logFile = $logDirectory . '/import-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        $logPayload = [
            'mode' => $dryRun ? 'dry_run' : 'commit',
            'strict_variants' => $strictVariants,
            'abort_on_error' => $abortOnError,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'duplicate_skus' => array_values(array_unique($duplicateSkus)),
            'failed_rows' => $failedRows,
            'generated_at' => date(DATE_ATOM),
        ];
        file_put_contents($logFile, json_encode($logPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $failedRowsCsvRelative = null;
        if (count($failedRows) > 0) {
            $failedRowsXlsxPath = preg_replace('/\.json$/', '.failed-rows.xlsx', $logFile);
            if (is_string($failedRowsXlsxPath) && $failedRowsXlsxPath !== '') {
                try {
                    $xlsxRows = [];
                    foreach ($failedRows as $fr) {
                        $xlsxRows[] = [
                            (int)($fr['line'] ?? 0),
                            (string)($fr['sku'] ?? ''),
                            (string)($fr['reason'] ?? ''),
                        ];
                    }
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    foreach (['line', 'sku', 'reason'] as $colIdx => $hdr) {
                        $sheet->setCellValueByColumnAndRow($colIdx + 1, 1, $hdr);
                    }
                    $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                    foreach ($xlsxRows as $ri => $xlsxRow) {
                        foreach ($xlsxRow as $ci => $val) {
                            $sheet->setCellValueByColumnAndRow($ci + 1, $ri + 2, $val);
                        }
                    }
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save($failedRowsXlsxPath);
                    $spreadsheet->disconnectWorksheets();
                    $failedRowsCsvRelative = basename($failedRowsXlsxPath);
                } catch (\Throwable $xlsxEx) {
                    error_log('[bulkImport] failed-rows xlsx write error: ' . $xlsxEx->getMessage());
                }
            }
        }

        $this->logAdminAction($pdo, $adminId, $dryRun ? 'bulk_import_products_dry_run' : 'bulk_import_products', 'products', null, [
            'mode' => $dryRun ? 'dry_run' : 'commit',
            'strict_variants' => $strictVariants,
            'abort_on_error' => $abortOnError,
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'failed_count' => count($failedRows),
            'log_file' => basename($logFile),
            'failed_rows_csv' => $failedRowsCsvRelative,
        ]);

        Response::json([
            'success' => true,
            'message' => $dryRun ? 'Dry run completed (no DB writes)' : 'Import completed',
            'data' => [
                'mode' => $dryRun ? 'dry_run' : 'commit',
                'strict_variants' => $strictVariants,
                'abort_on_error' => $abortOnError,
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'duplicate_skus' => array_values(array_unique($duplicateSkus)),
                'failed_count' => count($failedRows),
                'failed_rows' => $failedRows,
                'log_file' => basename($logFile),
                'failed_rows_csv' => $failedRowsCsvRelative,
                'failed_rows_csv_url' => $failedRowsCsvRelative !== null ? '/api/admin/import/logs/' . rawurlencode(basename($logFile)) . '/failed-rows' : null,
            ],
        ]);
    }

    public function bulkImportLogs(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $logDirectory = $this->ensureDirectory($this->storagePath('import-logs'));
        $files = glob($logDirectory . '/*.json');
        if (!is_array($files)) {
            $files = [];
        }

        rsort($files);
        $items = [];
        foreach (array_slice($files, 0, 20) as $filePath) {
            $contents = file_get_contents($filePath);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;
            if (!is_array($decoded)) {
                continue;
            }
            $items[] = [
                'file' => basename($filePath),
                'generated_at' => (string)($decoded['generated_at'] ?? ''),
                'mode' => (string)($decoded['mode'] ?? 'commit'),
                'strict_variants' => (bool)($decoded['strict_variants'] ?? false),
                'abort_on_error' => (bool)($decoded['abort_on_error'] ?? false),
                'created_count' => (int)($decoded['created_count'] ?? 0),
                'updated_count' => (int)($decoded['updated_count'] ?? 0),
                'failed_count' => is_array($decoded['failed_rows'] ?? null) ? count($decoded['failed_rows']) : 0,
                'duplicate_skus' => $decoded['duplicate_skus'] ?? [],
                'failed_rows_csv_url' => is_array($decoded['failed_rows'] ?? null) && count($decoded['failed_rows']) > 0
                    ? '/api/admin/import/logs/' . rawurlencode(basename($filePath)) . '/failed-rows'
                    : null,
            ];
        }

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $items]]);
    }

    public function bulkImportFailedRowsCsv(string $file): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $safeFile = basename($file);
        if (!preg_match('/^import-[a-zA-Z0-9\-]+\.json$/', $safeFile)) {
            Response::json(['success' => false, 'message' => 'Invalid log file'], 422);
            return;
        }

        $logPath = $this->storagePath('import-logs') . DIRECTORY_SEPARATOR . $safeFile;
        if (!is_file($logPath)) {
            Response::json(['success' => false, 'message' => 'Log file not found'], 404);
            return;
        }

        $contents = file_get_contents($logPath);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;
        $failedRows = is_array($decoded) && is_array($decoded['failed_rows'] ?? null) ? $decoded['failed_rows'] : [];

        $xlsxRows = [];
        foreach ($failedRows as $row) {
            $xlsxRows[] = [
                (int)($row['line'] ?? 0),
                (string)($row['sku'] ?? ''),
                (string)($row['reason'] ?? ''),
            ];
        }
        ExcelService::export(
            ['line', 'sku', 'reason'],
            $xlsxRows,
            str_replace('.json', '-failed-rows.xlsx', $safeFile)
        );
    }

    public function mediaList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $mediaDirectory = $this->ensureDirectory($this->mediaBasePath());
        $files = $this->scanMediaFiles($mediaDirectory);
        $pdo = self::db();
        if ($pdo) {
            $metaByPath = $this->fetchMediaAssetMetadataMap($pdo);
            foreach ($files as &$file) {
                $path = (string)($file['path'] ?? '');
                if ($path !== '' && isset($metaByPath[$path])) {
                    $file = array_merge($file, $metaByPath[$path]);
                }
            }
            unset($file);
        }

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => ['items' => $files],
        ]);
    }

    public function brandingUpload(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $isSuperAdmin     = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
        $adminPermissions = isset($_SESSION['admin_permissions']) && is_array($_SESSION['admin_permissions'])
            ? $_SESSION['admin_permissions']
            : [];
        if (!$isSuperAdmin && !in_array('business_settings', $adminPermissions, true)) {
            Response::json(['success' => false, 'message' => 'Permission denied'], 403);
            return;
        }

        $allowedLogoTypes = ['email_logo_url', 'navbar_logo_url', 'footer_logo_url', 'default_product_image_url'];
        $logoType = trim((string)($_POST['logo_type'] ?? ''));
        if (!in_array($logoType, $allowedLogoTypes, true)) {
            Response::json(['success' => false, 'message' => 'Invalid logo_type. Must be one of: ' . implode(', ', $allowedLogoTypes)], 422);
            return;
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::json(['success' => false, 'message' => 'No file uploaded'], 422);
            return;
        }

        $file    = $_FILES['file'];
        $error   = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($file['tmp_name'] ?? '');
        $origName = (string)($file['name'] ?? 'logo.png');
        $fileSize = (int)($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            Response::json(['success' => false, 'message' => $this->uploadErrorMessage($error)], 422);
            return;
        }

        $maxBytes = $this->effectiveUploadLimitBytes();
        if ($fileSize <= 0) {
            Response::json(['success' => false, 'message' => 'Uploaded file appears empty'], 422);
            return;
        }
        if ($maxBytes > 0 && $fileSize > $maxBytes) {
            Response::json(['success' => false, 'message' => 'File is larger than server upload limit (' . $this->formatBytes($maxBytes) . ')'], 422);
            return;
        }

        // Detect MIME — allow raster + SVG for branding
        $mime      = $this->detectMediaMimeType($tmpName);
        $extension = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        $allowedBrandingMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $allowedBrandingExts  = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if ($mime === '' || !in_array($mime, $allowedBrandingMimes, true) || !in_array($extension, $allowedBrandingExts, true)) {
            Response::json(['success' => false, 'message' => 'Allowed formats: JPG, PNG, WebP, SVG'], 422);
            return;
        }

        $uploadResult = UnifiedMediaService::upload($file, [
            'module' => 'branding',
            'entity_type' => 'settings',
            'entity_id' => $adminId,
            'admin_id' => $adminId,
            'allow_svg' => true,
            'max_bytes' => 2 * 1024 * 1024,
        ]);

        if (!$uploadResult['ok']) {
            Response::json(['success' => false, 'message' => $uploadResult['error']], 500);
            return;
        }

        $targetRelative = $uploadResult['relative_url'];

        // Persist URL to settings table and clean up old branding file
        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        // Read old value to delete the old file (only delete if it's inside /uploads/branding/)
        $stmtRead = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmtRead->execute([$logoType]);
        $oldUrl = (string)($stmtRead->fetchColumn() ?: '');
        if ($oldUrl !== '' && str_starts_with($oldUrl, '/public/uploads/branding/')) {
            $oldAbsolute = $this->projectRoot() . str_replace('/', DIRECTORY_SEPARATOR, $oldUrl);
            if (is_file($oldAbsolute)) {
                @unlink($oldAbsolute);
            }
        }

        // Upsert the new URL
        $stmtUpsert = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $stmtUpsert->execute([$logoType, $targetRelative]);

        $this->logAdminAction($pdo, $adminId, 'branding_upload', 'settings', null, [
            'logo_type' => $logoType,
            'path'      => $targetRelative,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data'    => [
                'url' => $targetRelative,
                'key' => $logoType,
            ],
        ], 201);
    }

    public function mediaUpload(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::json(['success' => false, 'message' => 'No media file uploaded'], 422);
            return;
        }

        $file = $_FILES['file'];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($file['tmp_name'] ?? '');
        $originalName = (string)($file['name'] ?? 'media.jpg');
        $fileSize = (int)($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
            Response::json(['success' => false, 'message' => $this->uploadErrorMessage($error)], 422);
            return;
        }

        $maxUploadSize = $this->effectiveUploadLimitBytes();
        if ($fileSize <= 0) {
            Response::json(['success' => false, 'message' => 'Uploaded file appears empty'], 422);
            return;
        }
        if ($maxUploadSize > 0 && $fileSize > $maxUploadSize) {
            Response::json(['success' => false, 'message' => 'File is larger than current server upload limit (' . $this->formatBytes($maxUploadSize) . ')'], 422);
            return;
        }

        $mime = $this->detectMediaMimeType($tmpName);
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $this->allowedMediaMimeMap();
        $allowedExtensions = $this->allowedMediaExtensions();
        if ($mime === '' || !isset($allowed[$mime]) || !in_array($extension, $allowedExtensions, true)) {
            Response::json(['success' => false, 'message' => 'Allowed formats: JPG, PNG, WEBP, GIF, SVG, MP4, MOV, AVI, MKV, WEBM, M4V, MPG, MPEG'], 422);
            return;
        }

        $yearMonth = date('Y/m');
        $targetDirectory = $this->ensureDirectory($this->mediaBasePath() . '/' . $yearMonth);

        $basename = $this->slugify(pathinfo($originalName, PATHINFO_FILENAME));
        if ($basename === '') {
            $basename = 'media-file';
        }
        $fileToken = $basename . '-' . date('His') . '-' . bin2hex(random_bytes(2));
        $isVideo = str_starts_with($mime, 'video/');
        $targetRelative = '/uploads/media/' . $yearMonth . '/' . $fileToken . ($isVideo ? '.' . $extension : '.webp');
        $targetAbsolute = $this->projectRoot() . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);

        $optimized = false;
        $queueId = 0;
        $sourcePath = null;
        $canonicalPath = $targetRelative;
        $publicUrl = $targetRelative;

        if ($isVideo) {
            if (!move_uploaded_file($tmpName, $targetAbsolute)) {
                Response::json(['success' => false, 'message' => 'Unable to save media file'], 500);
                return;
            }
            $sourcePath = $targetRelative;
            $canonicalPath = '/uploads/media/' . $yearMonth . '/' . $fileToken . '.mp4';
            $publicUrl = $canonicalPath;
        } else {
            $upload = UnifiedMediaService::upload($file, [
                'module' => 'media_center',
                'entity_type' => 'media_asset',
                'entity_id' => 0,
                'admin_id' => $adminId,
                'allow_svg' => true,
                'max_bytes' => $maxUploadSize,
            ]);
            if (!$upload['ok']) {
                Response::json(['success' => false, 'message' => $upload['error']], 500);
                return;
            }
            $targetRelative = (string)$upload['relative_url'];
            $targetAbsolute = (string)$upload['absolute_path'];
            $canonicalPath = (string)$upload['optimized_url'];
            $publicUrl = $targetRelative;
            $queueId = (int)$upload['queue_id'];
            $optimized = true;
        }

        $size = filesize($targetAbsolute);
        if ($size === false) {
            $size = $fileSize;
        }

        $pdo = self::db(); if (!$pdo) return;

        $mediaType = $isVideo ? 'video' : 'image';
        $conversionStatus = $isVideo ? 'queued' : ($queueId > 0 ? 'queued' : 'ready');

        if ($isVideo) {
            $this->upsertMediaAssetRecord($pdo, [
                'original_path' => $sourcePath,
                'canonical_path' => $canonicalPath,
                'original_filename' => $originalName,
                'mime_type' => $mime,
                'media_type' => $mediaType,
                'file_size' => $size,
                'conversion_status' => 'queued',
                'conversion_error' => null,
                'uploaded_by_admin_id' => $adminId,
            ]);
            $this->queueMediaTranscodeJob($pdo, $sourcePath, $canonicalPath, $adminId);
        } else {
            $this->upsertMediaAssetRecord($pdo, [
                'original_path' => $targetRelative,
                'canonical_path' => $canonicalPath,
                'original_filename' => $originalName,
                'mime_type' => 'image/webp',
                'media_type' => $mediaType,
                'file_size' => $size,
                'conversion_status' => $conversionStatus,
                'conversion_error' => null,
                'uploaded_by_admin_id' => $adminId,
            ]);
        }

        $this->logAdminAction($pdo, $adminId, 'upload_media', 'product_images', null, ['path' => $targetRelative, 'size' => $size]);

        Response::json([
            'success' => true,
            'message' => 'Media uploaded',
            'data' => [
                'path' => $targetRelative,
                'url' => $publicUrl,
                'name' => basename($targetAbsolute),
                'size' => $size,
                'mime' => $optimized ? 'image/webp' : $mime,
                'media_type' => $mediaType,
                'canonical_path' => $canonicalPath,
                'conversion_status' => $conversionStatus,
                'source_path' => $sourcePath,
                'queue_id' => $queueId,
                'media_engine' => MediaCapabilityService::detect(),
            ],
        ], 201);
    }

    public function mediaDelete(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $relativePath = (string)($input['path'] ?? '');

        if ($relativePath === '' || !$this->isValidMediaRelativePath($relativePath)) {
            Response::json(['success' => false, 'message' => 'Invalid media path'], 422);
            return;
        }

        $absolutePath = $this->resolveMediaAbsolutePath($relativePath);
        if ($absolutePath === null) {
            Response::json(['success' => false, 'message' => 'Invalid media path'], 422);
            return;
        }

        if (!is_file($absolutePath)) {
            Response::json(['success' => false, 'message' => 'Media file not found'], 404);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $references = $this->countMediaPathReferences($pdo, $relativePath);
        if ($references > 0) {
            Response::json(['success' => false, 'message' => 'Media is still referenced and cannot be deleted'], 409);
            return;
        }

        if (!@unlink($absolutePath)) {
            Response::json(['success' => false, 'message' => 'Failed to delete media file'], 500);
            return;
        }

        if ($this->mediaAssetsTableExists($pdo)) {
            $assetStmt = $pdo->prepare('SELECT original_path, canonical_path FROM media_assets WHERE original_path = :path OR canonical_path = :path LIMIT 1');
            $assetStmt->execute(['path' => $relativePath]);
            $asset = $assetStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($asset)) {
                $originalPath = (string)($asset['original_path'] ?? '');
                $canonicalPath = (string)($asset['canonical_path'] ?? '');
                if ($originalPath !== '' && $originalPath !== $relativePath) {
                    $extraAbsolute = $this->resolveMediaAbsolutePath($originalPath);
                    if ($extraAbsolute !== null && is_file($extraAbsolute) && $this->countMediaPathReferences($pdo, $originalPath) === 0) {
                        @unlink($extraAbsolute);
                    }
                }
                if ($canonicalPath !== '' && $canonicalPath !== $relativePath) {
                    $extraAbsolute = $this->resolveMediaAbsolutePath($canonicalPath);
                    if ($extraAbsolute !== null && is_file($extraAbsolute) && $this->countMediaPathReferences($pdo, $canonicalPath) === 0) {
                        @unlink($extraAbsolute);
                    }
                }
                $deleteAssetStmt = $pdo->prepare('DELETE FROM media_assets WHERE original_path = :path OR canonical_path = :path');
                $deleteAssetStmt->execute(['path' => $relativePath]);
            }
        }

        $this->logAdminAction($pdo, $adminId, 'delete_media', 'product_images', null, ['path' => $relativePath]);

        Response::json(['success' => true, 'message' => 'Media file deleted']);
    }

    public function mediaProcessingSummary(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $exists = $pdo->query("SHOW TABLES LIKE 'media_processing_queue'");
        if (!($exists instanceof \PDOStatement) || !$exists->fetchColumn()) {
            Response::json([
                'success' => true,
                'message' => 'Media processing queue not initialized',
                'data' => [
                    'counts' => ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0],
                    'storage' => ['original_bytes' => 0, 'optimized_bytes' => 0, 'savings_bytes' => 0, 'optimization_ratio' => 0.0],
                    'orphans' => ['optimized_without_queue' => 0],
                ],
            ]);
            return;
        }

        $counts = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        $countStmt = $pdo->query('SELECT processing_status, COUNT(*) AS total FROM media_processing_queue GROUP BY processing_status');
        if ($countStmt instanceof \PDOStatement) {
            while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
                $status = (string)($row['processing_status'] ?? '');
                if (isset($counts[$status])) {
                    $counts[$status] = (int)($row['total'] ?? 0);
                }
            }
        }

        $storage = [
            'original_bytes' => 0,
            'optimized_bytes' => 0,
            'savings_bytes' => 0,
            'optimization_ratio' => 0.0,
        ];

        $pairStmt = $pdo->query('SELECT original_path, optimized_path FROM media_processing_queue WHERE processing_status = "completed" ORDER BY id DESC LIMIT 5000');
        if ($pairStmt instanceof \PDOStatement) {
            while ($row = $pairStmt->fetch(PDO::FETCH_ASSOC)) {
                $originalPath = trim((string)($row['original_path'] ?? ''));
                $optimizedPath = trim((string)($row['optimized_path'] ?? ''));
                if ($originalPath === '' || $optimizedPath === '') {
                    continue;
                }

                $originalAbs = $this->resolveMediaAbsolutePath($originalPath);
                $optimizedAbs = $this->resolveMediaAbsolutePath($optimizedPath);

                if ($originalAbs === null || $optimizedAbs === null || !is_file($originalAbs) || !is_file($optimizedAbs)) {
                    continue;
                }

                $origBytes = filesize($originalAbs);
                $optBytes = filesize($optimizedAbs);
                if ($origBytes === false || $optBytes === false) {
                    continue;
                }

                $storage['original_bytes'] += (int)$origBytes;
                $storage['optimized_bytes'] += (int)$optBytes;
            }
        }

        $storage['savings_bytes'] = max(0, $storage['original_bytes'] - $storage['optimized_bytes']);
        $storage['optimization_ratio'] = $storage['original_bytes'] > 0
            ? round(($storage['savings_bytes'] / $storage['original_bytes']) * 100, 2)
            : 0.0;

        $orphans = [
            'optimized_without_queue' => $this->countOrphanOptimizedFiles($pdo),
        ];

        $capability = MediaCapabilityService::detect();

        Response::json([
            'success' => true,
            'message' => 'Media processing summary',
            'data' => [
                'counts' => $counts,
                'storage' => $storage,
                'orphans' => $orphans,
                'capability' => $capability,
            ],
        ]);
    }

    public function mediaProcessingJobs(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $exists = $pdo->query("SHOW TABLES LIKE 'media_processing_queue'");
        if (!($exists instanceof \PDOStatement) || !$exists->fetchColumn()) {
            Response::json(['success' => true, 'data' => ['items' => [], 'count' => 0]]);
            return;
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $allowed = ['pending', 'processing', 'completed', 'failed'];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = '';
        $params = [];
        if ($status !== '' && in_array($status, $allowed, true)) {
            $where = 'WHERE processing_status = :processing_status';
            $params['processing_status'] = $status;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM media_processing_queue ' . $where);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);

        $sql = 'SELECT id, module_name, entity_type, entity_id, original_path, optimized_path, processing_status, attempts, last_error, created_at, processed_at, updated_at
                FROM media_processing_queue ' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        Response::json([
            'success' => true,
            'message' => 'Media processing jobs',
            'data' => [
                'items' => $items,
                'count' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    public function financeSummary(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $todayReconciliation = (new FinancialReconciliationService())->summarizeToday();
        $invoice = $todayReconciliation['invoices'] ?? [];
        $orders = $todayReconciliation['orders'] ?? [];
        $ledger = $todayReconciliation['ledger'] ?? [];
        $variance = $todayReconciliation['variance'] ?? [];

        $metrics = [
            'total_invoices' => (int)($invoice['total_invoices'] ?? 0),
            'paid_invoices' => (int)($invoice['paid_invoices'] ?? 0),
            'unpaid_invoices' => (int)($invoice['unpaid_invoices'] ?? 0),
            'overdue_invoices' => (int)($invoice['overdue_invoices'] ?? 0),
            'part_paid_invoices' => (int)($invoice['part_paid_invoices'] ?? 0),
            'retail_receivables' => (float)($invoice['retail_receivables'] ?? 0),
            'b2b_receivables' => (float)($invoice['b2b_receivables'] ?? 0),
            'total_receivables' => (float)($invoice['total_receivables'] ?? 0),
            'cash_collections' => (float)($orders['cash_total'] ?? 0),
            'bank_collections' => (float)($orders['bank_total'] ?? 0),
            'net_collections' => (float)($orders['realized_total'] ?? 0),
            'refunded_total' => (float)($orders['refunded_total'] ?? 0),
            'ledger_cash_collections' => (float)($ledger['cash_total'] ?? 0),
            'ledger_bank_collections' => (float)($ledger['bank_total'] ?? 0),
            'ledger_net_revenue' => (float)($ledger['net_revenue'] ?? 0),
            'reconciliation_status' => (string)($todayReconciliation['status'] ?? 'attention'),
            'reconciliation_variance' => (float)($variance['absolute_sum'] ?? 0),
            'reconciliation_breakdown' => [
                'cash' => (float)($variance['cash'] ?? 0),
                'bank' => (float)($variance['bank'] ?? 0),
                'refund' => (float)($variance['refund'] ?? 0),
                'net' => (float)($variance['net'] ?? 0),
                'component_status' => $variance['component_status'] ?? [],
            ],
            'reconciliation_sources' => $todayReconciliation['source_tables'] ?? [],
            'reconciliation_window' => $todayReconciliation['window'] ?? ['from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d')],
        ];

        Response::json(['success' => true, 'message' => 'ok', 'data' => $metrics]);
    }

    public function invoicesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $customerType = trim((string)($_GET['customer_type'] ?? ''));
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 80)));

        $pdo = self::db(); if (!$pdo) return;
        $sql = 'SELECT
                    i.id,
                    i.invoice_number,
                    i.customer_type,
                    i.invoice_status,
                    i.payment_method,
                    i.grand_total,
                    i.paid_amount,
                    i.balance_due,
                    i.due_on,
                    i.issued_on,
                    i.created_at,
                    u.full_name AS retail_customer_name,
                    b.company_name AS b2b_customer_name
                FROM invoices i
                LEFT JOIN users u ON u.id = i.user_id
                LEFT JOIN b2b_accounts b ON b.id = i.b2b_account_id
                WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (i.invoice_number LIKE :q OR u.full_name LIKE :q OR b.company_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $sql .= ' AND i.invoice_status = :status';
            $params['status'] = $status;
        }
        if ($customerType !== '') {
            $sql .= ' AND i.customer_type = :customer_type';
            $params['customer_type'] = $customerType;
        }

        $sql .= ' ORDER BY i.created_at DESC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function invoiceDetail(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $invoiceId = (int)$id;
        if ($invoiceId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid invoice id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $invoiceStmt = $pdo->prepare(
            'SELECT
                i.*,
                u.full_name AS retail_customer_name,
                u.email AS retail_customer_email,
                b.company_name,
                b.company_email,
                b.company_phone
             FROM invoices i
             LEFT JOIN users u ON u.id = i.user_id
             LEFT JOIN b2b_accounts b ON b.id = i.b2b_account_id
             WHERE i.id = :id
             LIMIT 1'
        );
        $invoiceStmt->execute(['id' => $invoiceId]);
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            Response::json(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }

        $itemsStmt = $pdo->prepare('SELECT id, item_label, quantity, unit_price, line_total FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC');
        $itemsStmt->execute(['invoice_id' => $invoiceId]);

        $paymentsStmt = $pdo->prepare(
            'SELECT
                p.id,
                p.payment_method,
                p.payment_status,
                p.amount,
                p.payment_reference,
                p.note,
                p.verified_at,
                p.created_at,
                a.full_name AS verified_by
             FROM payments p
             LEFT JOIN admins a ON a.id = p.verified_by_admin_id
             WHERE p.invoice_id = :invoice_id
             ORDER BY p.created_at DESC, p.id DESC'
        );
        $paymentsStmt->execute(['invoice_id' => $invoiceId]);

        $proofStmt = $pdo->prepare(
            'SELECT
                pr.id,
                pr.payment_id,
                pr.file_url,
                pr.uploaded_by,
                pr.created_at
             FROM payment_proofs pr
             JOIN payments p ON p.id = pr.payment_id
             WHERE p.invoice_id = :invoice_id
             ORDER BY pr.created_at DESC, pr.id DESC'
        );
        $proofStmt->execute(['invoice_id' => $invoiceId]);

        $historyStmt = $pdo->prepare(
            'SELECT
                h.id,
                h.from_status,
                h.to_status,
                h.note,
                h.created_at,
                a.full_name AS changed_by
             FROM payment_status_history h
             LEFT JOIN admins a ON a.id = h.changed_by_admin_id
             WHERE h.invoice_id = :invoice_id
             ORDER BY h.created_at DESC, h.id DESC'
        );
        $historyStmt->execute(['invoice_id' => $invoiceId]);

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'invoice' => $invoice,
                'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC),
                'payments' => $paymentsStmt->fetchAll(PDO::FETCH_ASSOC),
                'proofs' => $proofStmt->fetchAll(PDO::FETCH_ASSOC),
                'history' => $historyStmt->fetchAll(PDO::FETCH_ASSOC),
            ],
        ]);
    }

    public function invoiceUpdateStatus(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $invoiceId = (int)$id;
        if ($invoiceId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid invoice id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $nextStatus = trim((string)($input['invoice_status'] ?? ''));
        $note = trim((string)($input['note'] ?? ''));
        if (!$this->isAllowedInvoiceStatus($nextStatus)) {
            Response::json(['success' => false, 'message' => 'Invalid invoice status'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, invoice_status FROM invoices WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            Response::json(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }

        $currentStatus = (string)($invoice['invoice_status'] ?? 'pending_payment');
        if (!$this->isValidInvoiceStatusTransition($currentStatus, $nextStatus)) {
            Response::json(['success' => false, 'message' => 'Invalid invoice status transition'], 422);
            return;
        }

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE invoices SET invoice_status = :status WHERE id = :id');
            $update->execute(['status' => $nextStatus, 'id' => $invoiceId]);

            $hist = $pdo->prepare('INSERT INTO payment_status_history (invoice_id, from_status, to_status, changed_by_admin_id, note) VALUES (:invoice_id, :from_status, :to_status, :admin_id, :note)');
            $hist->execute([
                'invoice_id' => $invoiceId,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'admin_id' => $adminId,
                'note' => $this->nullableString($note),
            ]);

            $this->logAdminAction($pdo, $adminId, 'update_invoice_status', 'invoices', $invoiceId, [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'note' => $note,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to update invoice status', 'details' => $e->getMessage()], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Invoice status updated']);
    }

    public function invoiceRecordPayment(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $invoiceId = (int)$id;
        if ($invoiceId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid invoice id'], 422);
            return;
        }

        $amount = (float)($_POST['amount'] ?? 0);
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'upi'));
        $paymentStatus = trim((string)($_POST['payment_status'] ?? 'submitted'));
        $reference = trim((string)($_POST['payment_reference'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $proofPath = null;

        if ($amount <= 0) {
            Response::json(['success' => false, 'message' => 'amount must be positive'], 422);
            return;
        }
        if (!in_array($paymentMethod, ['upi', 'bank_transfer', 'cash', 'pos_card', 'payment_link'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid payment method'], 422);
            return;
        }
        if (!in_array($paymentStatus, ['submitted', 'verified', 'rejected'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid payment status'], 422);
            return;
        }

        if (isset($_FILES['proof']) && is_array($_FILES['proof']) && (int)($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $proofFile = $_FILES['proof'];
            $upload = UnifiedMediaService::upload($proofFile, [
                'module' => 'byoc',
                'entity_type' => 'invoice_payment_proof',
                'entity_id' => $invoiceId,
                'admin_id' => $adminId,
                'allow_svg' => false,
                'max_bytes' => 5 * 1024 * 1024,
            ]);
            if (!$upload['ok']) {
                Response::json(['success' => false, 'message' => $upload['error']], 422);
                return;
            }
            $proofPath = (string)$upload['relative_url'];
        }

        $pdo = self::db(); if (!$pdo) return;
        $invoiceStmt = $pdo->prepare('SELECT id, invoice_status, grand_total, paid_amount FROM invoices WHERE id = :id LIMIT 1');
        $invoiceStmt->execute(['id' => $invoiceId]);
        $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) {
            Response::json(['success' => false, 'message' => 'Invoice not found'], 404);
            return;
        }

        $pdo->beginTransaction();
        try {
            $paymentInsert = $pdo->prepare(
                'INSERT INTO payments (invoice_id, payment_method, payment_status, amount, payment_reference, note, verified_by_admin_id, verified_at)
                 VALUES (:invoice_id, :payment_method, :payment_status, :amount, :payment_reference, :note, :verified_by_admin_id, :verified_at)'
            );
            $paymentInsert->execute([
                'invoice_id' => $invoiceId,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => round($amount, 2),
                'payment_reference' => $this->nullableString($reference),
                'note' => $this->nullableString($note),
                'verified_by_admin_id' => $paymentStatus === 'verified' ? $adminId : null,
                'verified_at' => $paymentStatus === 'verified' ? date('Y-m-d H:i:s') : null,
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            if ($proofPath !== null) {
                $proofInsert = $pdo->prepare('INSERT INTO payment_proofs (payment_id, file_url, uploaded_by) VALUES (:payment_id, :file_url, :uploaded_by)');
                $proofInsert->execute([
                    'payment_id' => $paymentId,
                    'file_url' => $proofPath,
                    'uploaded_by' => 'admin',
                ]);
            }

            if ($paymentStatus === 'verified') {
                $paidAmount = (float)$invoice['paid_amount'] + round($amount, 2);
                $grandTotal = (float)$invoice['grand_total'];
                $balanceDue = max(0.0, round($grandTotal - $paidAmount, 2));
                $nextStatus = $balanceDue <= 0 ? 'paid' : 'part_paid';
                $currentStatus = (string)$invoice['invoice_status'];

                $invUpdate = $pdo->prepare('UPDATE invoices SET paid_amount = :paid_amount, balance_due = :balance_due, invoice_status = :invoice_status WHERE id = :id');
                $invUpdate->execute([
                    'paid_amount' => $paidAmount,
                    'balance_due' => $balanceDue,
                    'invoice_status' => $nextStatus,
                    'id' => $invoiceId,
                ]);

                if ($currentStatus !== $nextStatus) {
                    $hist = $pdo->prepare('INSERT INTO payment_status_history (invoice_id, from_status, to_status, changed_by_admin_id, note) VALUES (:invoice_id, :from_status, :to_status, :admin_id, :note)');
                    $hist->execute([
                        'invoice_id' => $invoiceId,
                        'from_status' => $currentStatus,
                        'to_status' => $nextStatus,
                        'admin_id' => $adminId,
                        'note' => 'Auto-updated from verified payment entry',
                    ]);
                }
            } elseif ($paymentStatus === 'submitted') {
                $invStatus = (string)$invoice['invoice_status'];
                if ($invStatus === 'pending_payment' || $invStatus === 'unpaid_rejected') {
                    $up = $pdo->prepare('UPDATE invoices SET invoice_status = "payment_under_verification" WHERE id = :id');
                    $up->execute(['id' => $invoiceId]);
                }
            }

            $this->logAdminAction($pdo, $adminId, 'record_invoice_payment', 'invoices', $invoiceId, [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $reference,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to record payment', 'details' => $e->getMessage()], 500);
            return;
        }

        Response::json(['success' => true, 'message' => 'Payment entry recorded']);
    }

    public function financeAgeingReport(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT
                CASE
                  WHEN due_on IS NULL THEN "no_due_date"
                  WHEN DATEDIFF(CURDATE(), due_on) < 0 THEN "not_due"
                  WHEN DATEDIFF(CURDATE(), due_on) BETWEEN 0 AND 7 THEN "0_7"
                  WHEN DATEDIFF(CURDATE(), due_on) BETWEEN 8 AND 15 THEN "8_15"
                  WHEN DATEDIFF(CURDATE(), due_on) BETWEEN 16 AND 30 THEN "16_30"
                  ELSE "30_plus"
                END AS ageing_bucket,
                COUNT(*) AS invoice_count,
                SUM(balance_due) AS balance_due
             FROM invoices
             WHERE invoice_status IN ("pending_payment", "part_paid", "overdue", "payment_under_verification", "unpaid_rejected")
             GROUP BY ageing_bucket
             ORDER BY ageing_bucket ASC'
        );

        $items = $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $items]]);
    }

    public function bankAlertsQueueList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $this->ensureBankAlertSchema($pdo);

        $status = strtolower(trim((string)($_GET['status'] ?? 'open')));
        $limit = min(200, max(10, (int)($_GET['limit'] ?? 80)));

        $where = 'WHERE 1=1';
        $params = [];

        if ($status === 'open') {
            $where .= ' AND b.status IN ("pending", "matched_auto")';
        } elseif (in_array($status, ['pending', 'matched_auto', 'confirmed', 'rejected', 'duplicate', 'ignored'], true)) {
            $where .= ' AND b.status = :status';
            $params['status'] = $status;
        }

        $sql =
            'SELECT
                b.id,
                b.source,
                b.parsed_utr,
                b.parsed_amount,
                b.bank_sender,
                b.email_subject,
                b.alert_message,
                b.status,
                b.match_confidence,
                b.confirm_note,
                b.created_at,
                b.updated_at,
                o.id AS order_id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.grand_total AS order_amount,
                o.payment_status AS order_payment_status,
                a.full_name AS confirmed_by
             FROM bank_alert_utrs b
             LEFT JOIN orders o ON o.id = b.order_id
             LEFT JOIN admins a ON a.id = b.confirmed_by_admin_id
             ' . $where . '
             ORDER BY b.created_at DESC
             LIMIT :limit';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function bankAlertsConfirm(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $alertId = (int)$id;
        if ($alertId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid alert id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $note = trim((string)($input['note'] ?? ''));

        $pdo = self::db(); if (!$pdo) return;
        $this->ensureBankAlertSchema($pdo);

        $pdo->beginTransaction();
        try {
            $alertStmt = $pdo->prepare('SELECT * FROM bank_alert_utrs WHERE id = :id FOR UPDATE');
            $alertStmt->execute(['id' => $alertId]);
            $alert = $alertStmt->fetch(PDO::FETCH_ASSOC);
            if (!$alert) {
                throw new \RuntimeException('Alert not found');
            }

            $orderId = (int)($alert['order_id'] ?? 0);
            if ($orderId <= 0) {
                throw new \RuntimeException('Alert is not linked with any order. Match it first before confirming.');
            }

            $orderStmt = $pdo->prepare('SELECT id, order_number, order_status, payment_status, payment_method, grand_total, COALESCE(refund_amount, 0) AS refund_amount FROM orders WHERE id = :id FOR UPDATE');
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                throw new \RuntimeException('Order not found for this alert');
            }

            $currentPaymentStatus = (string)($order['payment_status'] ?? 'pending');
            if ($currentPaymentStatus !== 'paid') {
                $nextOrderStatus = (string)($order['order_status'] ?? 'pending');
                if ($nextOrderStatus === 'pending') {
                    $nextOrderStatus = 'confirmed';
                }

                $updateOrder = $pdo->prepare('UPDATE orders SET payment_status = "paid", order_status = :order_status, updated_at = NOW() WHERE id = :id');
                $updateOrder->execute([
                    'order_status' => $nextOrderStatus,
                    'id' => $orderId,
                ]);

                $recognizedAmount = max(0.0, round((float)($order['grand_total'] ?? 0) - (float)($order['refund_amount'] ?? 0), 2));
                if ($recognizedAmount > 0) {
                    $engine = new \App\Services\FinancialTransactionEngine();
                    $adminName = (string)($_SESSION['admin_name'] ?? 'Admin');

                    if ($currentPaymentStatus === 'credit') {
                        $postResult = $engine->recordBalanceSettled([
                            'order_id' => $orderId,
                            'order_number' => (string)($order['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)($order['payment_method'] ?? 'upi_manual'),
                            'source_reference' => 'AdminApiController::bankAlertsConfirm',
                            'idempotency_key' => 'bank-alert-balance-settled:' . $orderId . ':' . $alertId,
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Credit balance settled via bank alert confirmation',
                        ]);
                    } else {
                        $postResult = $engine->recordPaymentReceived([
                            'order_id' => $orderId,
                            'order_number' => (string)($order['order_number'] ?? ''),
                            'amount' => $recognizedAmount,
                            'payment_method' => (string)($order['payment_method'] ?? 'upi_manual'),
                            'payment_status' => 'paid',
                            'source_reference' => 'AdminApiController::bankAlertsConfirm',
                            'idempotency_key' => 'bank-alert-payment-received:' . $orderId . ':' . $alertId,
                            'admin_id' => $adminId,
                            'admin_name' => $adminName,
                            'narration' => 'Payment received via bank alert confirmation',
                        ]);
                    }

                    if (!$postResult['success']) {
                        error_log('[bankAlertsConfirm][fte] ' . $postResult['message']);
                    }
                }
            }

            $invoiceStmt = $pdo->prepare('SELECT id, invoice_status, grand_total, paid_amount FROM invoices WHERE order_id = :order_id LIMIT 1');
            $invoiceStmt->execute(['order_id' => $orderId]);
            $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (is_array($invoice)) {
                $invoiceId = (int)$invoice['id'];
                $amount = (float)($alert['parsed_amount'] ?? 0);
                if ($amount <= 0) {
                    $amount = round((float)$invoice['grand_total'], 2);
                }
                $amount = round($amount, 2);

                $paymentInsert = $pdo->prepare(
                    'INSERT INTO payments (invoice_id, payment_method, payment_status, amount, payment_reference, note, verified_by_admin_id, verified_at)
                     VALUES (:invoice_id, "upi", "verified", :amount, :payment_reference, :note, :verified_by_admin_id, NOW())'
                );
                $paymentInsert->execute([
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'payment_reference' => $this->nullableString((string)($alert['parsed_utr'] ?? '')),
                    'note' => $this->nullableString($note !== '' ? $note : 'Verified from bank alert queue'),
                    'verified_by_admin_id' => $adminId,
                ]);
                $paymentId = (int)$pdo->lastInsertId();

                $invoiceStatus = (string)($invoice['invoice_status'] ?? 'pending_payment');
                $invoiceGrandTotal = (float)($invoice['grand_total'] ?? 0);
                $currentPaidAmount = (float)($invoice['paid_amount'] ?? 0);
                $nextPaidAmount = min($invoiceGrandTotal, round($currentPaidAmount + $amount, 2));
                $nextBalanceDue = max(0.0, round($invoiceGrandTotal - $nextPaidAmount, 2));
                $nextInvoiceStatus = $nextBalanceDue <= 0.01 ? 'paid' : 'part_paid';
                if ($invoiceStatus !== $nextInvoiceStatus || abs($nextPaidAmount - $currentPaidAmount) > 0.009) {
                    $invoiceUpdate = $pdo->prepare('UPDATE invoices SET paid_amount = :paid_amount, balance_due = :balance_due, invoice_status = :invoice_status, updated_at = NOW() WHERE id = :id');
                    $invoiceUpdate->execute([
                        'paid_amount' => $nextPaidAmount,
                        'balance_due' => $nextBalanceDue,
                        'invoice_status' => $nextInvoiceStatus,
                        'id' => $invoiceId,
                    ]);

                    $hist = $pdo->prepare('INSERT INTO payment_status_history (invoice_id, from_status, to_status, changed_by_admin_id, note) VALUES (:invoice_id, :from_status, :to_status, :admin_id, :note)');
                    $hist->execute([
                        'invoice_id' => $invoiceId,
                        'from_status' => $invoiceStatus,
                        'to_status' => $nextInvoiceStatus,
                        'admin_id' => $adminId,
                        'note' => 'Bank alert confirmed manually',
                    ]);
                }

                try {
                    $receiptService = new \App\Services\PaymentReceiptService($pdo);
                    $receiptResult = $receiptService->issueAdvanceReceipt($orderId, [
                        'source_event' => 'bank_alert_confirmation',
                        'source_reference' => 'bank-alert-confirm:' . $alertId,
                        'payment_method' => (string)($order['payment_method'] ?? 'upi_manual'),
                        'payment_status' => (string)($order['payment_status'] ?? 'paid'),
                        'issued_by_admin_id' => $adminId,
                        'payment_id' => $paymentId,
                        'financial_transaction_id' => isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null,
                        'amount' => $amount,
                        'metadata' => [
                            'channel' => 'bank_alerts',
                            'trigger' => 'bank_alert_confirmation',
                            'alert_id' => $alertId,
                        ],
                    ]);
                    if (!$receiptResult['success'] && !in_array($receiptResult['message'], ['Receipt not allowed after full payment', 'No advance amount available for receipt', 'Payment receipt schema is not ready', 'Receipt not required when partial payment is disabled'], true)) {
                        error_log('[bankAlertsConfirm][receipt] ' . $receiptResult['message']);
                    }
                } catch (\Throwable $receiptErr) {
                    error_log('[bankAlertsConfirm][receipt] ' . $receiptErr->getMessage());
                }

                $alertUpdate = $pdo->prepare(
                    'UPDATE bank_alert_utrs
                     SET status = "confirmed",
                         confirm_note = :confirm_note,
                         confirmed_by_admin_id = :admin_id,
                         confirmed_at = NOW(),
                         invoice_id = :invoice_id,
                         payment_id = :payment_id,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $alertUpdate->execute([
                    'confirm_note' => $this->nullableString($note),
                    'admin_id' => $adminId,
                    'invoice_id' => $invoiceId,
                    'payment_id' => $paymentId,
                    'id' => $alertId,
                ]);
            } else {
                $alertUpdate = $pdo->prepare(
                    'UPDATE bank_alert_utrs
                     SET status = "confirmed",
                         confirm_note = :confirm_note,
                         confirmed_by_admin_id = :admin_id,
                         confirmed_at = NOW(),
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $alertUpdate->execute([
                    'confirm_note' => $this->nullableString($note),
                    'admin_id' => $adminId,
                    'id' => $alertId,
                ]);
            }

            $this->logAdminAction($pdo, $adminId, 'confirm_bank_alert', 'bank_alert_utrs', $alertId, [
                'order_id' => $orderId,
                'parsed_utr' => (string)($alert['parsed_utr'] ?? ''),
                'note' => $note,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $status = $e instanceof \RuntimeException ? 422 : 500;
            Response::json(['success' => false, 'message' => $e->getMessage()], $status);
            return;
        }

        Response::json(['success' => true, 'message' => 'Bank alert confirmed and payment marked paid']);
    }

    public function bankAlertsReject(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $alertId = (int)$id;
        if ($alertId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid alert id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $note = trim((string)($input['note'] ?? ''));

        $pdo = self::db(); if (!$pdo) return;
        $this->ensureBankAlertSchema($pdo);

        $stmt = $pdo->prepare(
            'UPDATE bank_alert_utrs
             SET status = "rejected",
                 confirm_note = :confirm_note,
                 confirmed_by_admin_id = :admin_id,
                 confirmed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'confirm_note' => $this->nullableString($note),
            'admin_id' => $adminId,
            'id' => $alertId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Alert not found'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'reject_bank_alert', 'bank_alert_utrs', $alertId, [
            'note' => $note,
        ]);

        Response::json(['success' => true, 'message' => 'Bank alert rejected']);
    }

    public function smtpSettingsGet(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, host, port, username, encryption, from_name, from_email, is_active, updated_at FROM smtp_settings ORDER BY id DESC LIMIT 1');
        $settings = $stmt instanceof \PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['settings' => $settings ?: new \stdClass()]]);
    }

    public function smtpSettingsUpdate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id FROM smtp_settings ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $existingId = (int)($stmt->fetchColumn() ?: 0);

        $payload = [
            'host' => $this->nullableString($input['host'] ?? null),
            'port' => (int)($input['port'] ?? 0) > 0 ? (int)$input['port'] : null,
            'username' => $this->nullableString($input['username'] ?? null),
            'password_encrypted' => $this->nullableString($input['password'] ?? null),
            'encryption' => in_array((string)($input['encryption'] ?? 'tls'), ['none', 'ssl', 'tls'], true) ? (string)$input['encryption'] : 'tls',
            'from_name' => $this->nullableString($input['from_name'] ?? null),
            'from_email' => $this->nullableString($input['from_email'] ?? null),
            'is_active' => $this->toBinaryFlag($input['is_active'] ?? 0),
            'updated_by_admin_id' => $adminId,
        ];

        if ($existingId > 0) {
            $update = $pdo->prepare('UPDATE smtp_settings SET host=:host, port=:port, username=:username, password_encrypted=:password_encrypted, encryption=:encryption, from_name=:from_name, from_email=:from_email, is_active=:is_active, updated_by_admin_id=:updated_by_admin_id WHERE id=:id');
            $payload['id'] = $existingId;
            $update->execute($payload);
        } else {
            $insert = $pdo->prepare('INSERT INTO smtp_settings (host, port, username, password_encrypted, encryption, from_name, from_email, is_active, updated_by_admin_id) VALUES (:host, :port, :username, :password_encrypted, :encryption, :from_name, :from_email, :is_active, :updated_by_admin_id)');
            $insert->execute($payload);
        }

        $this->logAdminAction($pdo, $adminId, 'update_smtp_settings', 'settings', null, ['is_active' => $payload['is_active']]);
        Response::json(['success' => true, 'message' => 'SMTP settings saved']);
    }

    public function smtpSendTest(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $to = trim((string)($input['to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'Valid recipient email is required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $logStmt = $pdo->prepare('INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json) VALUES ("email", "smtp_test", :recipient, "queued", :payload_json)');
        $logStmt->execute([
            'recipient' => $to,
            'payload_json' => json_encode(['to' => $to], JSON_UNESCAPED_SLASHES),
        ]);
        $logId = (int)$pdo->lastInsertId();

        $commQueueStmt = $pdo->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:communication_log_id, "email", :payload_json)');
        $commQueueStmt->execute([
            'communication_log_id' => $logId,
            'payload_json' => json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES),
        ]);

        $queueStmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES (:job_type, :payload_json, "queued", NOW(), 0)');
        $queueStmt->execute([
            'job_type' => 'smtp_test_email',
            'payload_json' => json_encode([
                'to' => $to,
                'subject' => 'Cakeouflage SMTP Test',
                'log_id' => $logId,
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $this->logAdminAction($pdo, $adminId, 'smtp_test_queued', 'communication_logs', null, ['to' => $to]);
        Response::json(['success' => true, 'message' => 'SMTP test message queued']);
    }

    public function whatsappSettingsGet(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, provider_name, app_id, api_base_url, phone_number_id, business_account_id, webhook_callback_url, webhook_verify_token, default_language_code, default_category, namespace_reference, is_active, updated_at FROM whatsapp_settings ORDER BY id DESC LIMIT 1');
        $settings = $stmt instanceof \PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['settings' => $settings ?: new \stdClass()]]);
    }

    public function whatsappSettingsUpdate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id FROM whatsapp_settings ORDER BY id DESC LIMIT 1');
        $stmt->execute();
        $existingId = (int)($stmt->fetchColumn() ?: 0);

        $payload = [
            'provider_name' => $this->nullableString($input['provider_name'] ?? null),
            'app_id' => $this->nullableString($input['app_id'] ?? null),
            'app_secret_encrypted' => $this->nullableString($input['app_secret'] ?? null),
            'api_base_url' => $this->nullableString($input['api_base_url'] ?? null),
            'api_key_encrypted' => $this->nullableString($input['api_key'] ?? null),
            'access_token_encrypted' => $this->nullableString($input['access_token'] ?? null),
            'phone_number_id' => $this->nullableString($input['phone_number_id'] ?? null),
            'business_account_id' => $this->nullableString($input['business_account_id'] ?? null),
            'webhook_callback_url' => $this->nullableString($input['webhook_callback_url'] ?? null),
            'webhook_verify_token' => $this->nullableString($input['webhook_verify_token'] ?? null),
            'default_language_code' => $this->nullableString($input['default_language_code'] ?? null) ?? 'en_US',
            'default_category' => in_array((string)($input['default_category'] ?? 'utility'), ['utility', 'marketing', 'authentication'], true) ? (string)$input['default_category'] : 'utility',
            'namespace_reference' => $this->nullableString($input['namespace_reference'] ?? null),
            'is_active' => $this->toBinaryFlag($input['is_active'] ?? 0),
            'updated_by_admin_id' => $adminId,
        ];

        if ($existingId > 0) {
            $update = $pdo->prepare('UPDATE whatsapp_settings SET provider_name=:provider_name, app_id=:app_id, app_secret_encrypted=:app_secret_encrypted, api_base_url=:api_base_url, api_key_encrypted=:api_key_encrypted, access_token_encrypted=:access_token_encrypted, phone_number_id=:phone_number_id, business_account_id=:business_account_id, webhook_callback_url=:webhook_callback_url, webhook_verify_token=:webhook_verify_token, default_language_code=:default_language_code, default_category=:default_category, namespace_reference=:namespace_reference, is_active=:is_active, updated_by_admin_id=:updated_by_admin_id WHERE id=:id');
            $payload['id'] = $existingId;
            $update->execute($payload);
        } else {
            $insert = $pdo->prepare('INSERT INTO whatsapp_settings (provider_name, app_id, app_secret_encrypted, api_base_url, api_key_encrypted, access_token_encrypted, phone_number_id, business_account_id, webhook_callback_url, webhook_verify_token, default_language_code, default_category, namespace_reference, is_active, updated_by_admin_id) VALUES (:provider_name, :app_id, :app_secret_encrypted, :api_base_url, :api_key_encrypted, :access_token_encrypted, :phone_number_id, :business_account_id, :webhook_callback_url, :webhook_verify_token, :default_language_code, :default_category, :namespace_reference, :is_active, :updated_by_admin_id)');
            $insert->execute($payload);
        }

        $this->logAdminAction($pdo, $adminId, 'update_whatsapp_settings', 'settings', null, ['is_active' => $payload['is_active']]);
        Response::json(['success' => true, 'message' => 'WhatsApp settings saved']);
    }

    public function whatsappSettingsTest(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $settings = $this->getWhatsAppSettings($pdo);
        $service = new WhatsAppMetaApiService($settings);

        try {
            $account = $service->testConnection();
            $templates = $service->fetchTemplates();
            $this->logAdminAction($pdo, $adminId, 'test_whatsapp_connection', 'settings', null, ['templates_found' => count($templates)]);
            Response::json([
                'success' => true,
                'message' => 'Meta WhatsApp connection successful',
                'data' => [
                    'account' => $account,
                    'template_count' => count($templates),
                ],
            ]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Meta WhatsApp connection failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplatesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $pdo = self::db(); if (!$pdo) return;
        $sql = 'SELECT t.id, t.internal_name, t.template_key, t.meta_template_name, t.category, t.language_code, t.approval_status, t.sync_status, t.mapped_event_key, t.is_active, t.updated_at, COUNT(v.id) AS version_count
                FROM whatsapp_templates t
                LEFT JOIN whatsapp_template_versions v ON v.template_id = t.id
                WHERE 1=1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND t.approval_status = :status';
            $params['status'] = $status;
        }
        $sql .= ' GROUP BY t.id ORDER BY t.updated_at DESC, t.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function whatsappTemplateDetail(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $template = $this->loadWhatsAppTemplate($pdo, $templateId);
        if ($template === null) {
            Response::json(['success' => false, 'message' => 'WhatsApp template not found'], 404);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'template' => $template,
                'variables' => $this->loadWhatsAppTemplateVariables($pdo, $templateId),
                'buttons' => $this->loadWhatsAppTemplateButtons($pdo, $templateId),
                'versions' => $this->loadWhatsAppTemplateVersions($pdo, $templateId),
                'approval_logs' => $this->loadWhatsAppTemplateApprovalLogs($pdo, $templateId),
            ],
        ]);
    }

    public function whatsappTemplateCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;
        $builder = new WhatsAppTemplateBuilderService();
        $buttons = is_array($input['buttons'] ?? null) ? $input['buttons'] : [];
        $payload = $this->normalizeWhatsAppTemplatePayload($input, $adminId, null);
        $build = $builder->build($payload, $buttons);
        if (($build['success'] ?? false) !== true) {
            Response::json(['success' => false, 'message' => 'Template validation failed', 'details' => $build['errors'] ?? []], 422);
            return;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO whatsapp_templates (internal_name, template_key, meta_template_name, waba_id, phone_number_id, category, language_code, header_type, header_text, header_media_example, body_text, footer_text, buttons_json, variables_json, approval_status, sync_status, mapped_event_key, is_active, created_by, updated_by) VALUES (:internal_name, :template_key, :meta_template_name, :waba_id, :phone_number_id, :category, :language_code, :header_type, :header_text, :header_media_example, :body_text, :footer_text, :buttons_json, :variables_json, :approval_status, :sync_status, :mapped_event_key, :is_active, :created_by, :updated_by)');
            $stmt->execute($payload);
            $templateId = (int)$pdo->lastInsertId();
            $this->syncWhatsAppTemplateChildren($pdo, $templateId, $build['variables'] ?? [], $buttons);
            $this->createWhatsAppTemplateVersion($pdo, $templateId, $adminId, 'Initial draft');
            $this->logAdminAction($pdo, $adminId, 'create_whatsapp_template', 'whatsapp_templates', $templateId, ['template_key' => $payload['template_key']]);
            $pdo->commit();
            Response::json(['success' => true, 'message' => 'WhatsApp draft template created', 'data' => ['id' => $templateId]], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to create WhatsApp template', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplateUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        if ($this->loadWhatsAppTemplate($pdo, $templateId) === null) {
            Response::json(['success' => false, 'message' => 'WhatsApp template not found'], 404);
            return;
        }

        $input = $this->readJsonInput();
        $buttons = is_array($input['buttons'] ?? null) ? $input['buttons'] : [];
        $payload = $this->normalizeWhatsAppTemplatePayload($input, $adminId, $templateId);
        $build = (new WhatsAppTemplateBuilderService())->build($payload, $buttons);
        if (($build['success'] ?? false) !== true) {
            Response::json(['success' => false, 'message' => 'Template validation failed', 'details' => $build['errors'] ?? []], 422);
            return;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE whatsapp_templates SET internal_name=:internal_name, template_key=:template_key, meta_template_name=:meta_template_name, category=:category, language_code=:language_code, header_type=:header_type, header_text=:header_text, header_media_example=:header_media_example, body_text=:body_text, footer_text=:footer_text, buttons_json=:buttons_json, variables_json=:variables_json, approval_status=:approval_status, approval_reason=:approval_reason, sync_status=:sync_status, mapped_event_key=:mapped_event_key, is_active=:is_active, updated_by=:updated_by WHERE id=:id');
            $payload['id'] = $templateId;
            $stmt->execute($payload);
            $this->syncWhatsAppTemplateChildren($pdo, $templateId, $build['variables'] ?? [], $buttons);
            $this->createWhatsAppTemplateVersion($pdo, $templateId, $adminId, trim((string)($input['change_note'] ?? 'Template updated')));
            $this->logAdminAction($pdo, $adminId, 'update_whatsapp_template', 'whatsapp_templates', $templateId, ['template_key' => $payload['template_key']]);
            $pdo->commit();
            Response::json(['success' => true, 'message' => 'WhatsApp template updated']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to update WhatsApp template', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplatesAutoGenerate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $created = [];
        $presets = $this->whatsAppDefaultTemplatePresets();
        foreach ($presets as $preset) {
            $exists = $pdo->prepare('SELECT id FROM whatsapp_templates WHERE template_key = :template_key LIMIT 1');
            $exists->execute(['template_key' => $preset['template_key']]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $builder = new WhatsAppTemplateBuilderService();
            $build = $builder->build($preset, $preset['buttons']);
            if (($build['success'] ?? false) !== true) {
                continue;
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO whatsapp_templates (internal_name, template_key, meta_template_name, category, language_code, header_type, header_text, body_text, footer_text, buttons_json, variables_json, approval_status, sync_status, mapped_event_key, is_active, created_by, updated_by) VALUES (:internal_name, :template_key, :meta_template_name, :category, :language_code, :header_type, :header_text, :body_text, :footer_text, :buttons_json, :variables_json, :approval_status, :sync_status, :mapped_event_key, :is_active, :created_by, :updated_by)');
                $stmt->execute([
                    'internal_name' => $preset['internal_name'],
                    'template_key' => $preset['template_key'],
                    'meta_template_name' => $builder->normalizeMetaTemplateName((string)$preset['meta_template_name']),
                    'category' => $preset['category'],
                    'language_code' => $preset['language_code'],
                    'header_type' => $preset['header_type'],
                    'header_text' => $preset['header_text'],
                    'body_text' => $preset['body_text'],
                    'footer_text' => $preset['footer_text'],
                    'buttons_json' => json_encode($preset['buttons'], JSON_UNESCAPED_SLASHES),
                    'variables_json' => json_encode(array_column($build['variables'], 'variable_key'), JSON_UNESCAPED_SLASHES),
                    'approval_status' => 'draft',
                    'sync_status' => 'local_only',
                    'mapped_event_key' => $preset['mapped_event_key'],
                    'is_active' => 1,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]);
                $templateId = (int)$pdo->lastInsertId();
                $this->syncWhatsAppTemplateChildren($pdo, $templateId, $build['variables'] ?? [], $preset['buttons']);
                $this->createWhatsAppTemplateVersion($pdo, $templateId, $adminId, 'Auto-generated default draft');
                $pdo->commit();
                $created[] = $preset['template_key'];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }

        $this->logAdminAction($pdo, $adminId, 'auto_generate_whatsapp_templates', 'whatsapp_templates', null, ['created' => $created]);
        Response::json(['success' => true, 'message' => 'Default WhatsApp drafts generated', 'data' => ['created' => $created]]);
    }

    public function whatsappTemplatesSync(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        try {
            $service = new WhatsAppTemplateSyncService(new WhatsAppMetaApiService($this->getWhatsAppSettings($pdo)));
            $result = $service->sync($pdo, $adminId);
            $this->logAdminAction($pdo, $adminId, 'sync_whatsapp_templates', 'whatsapp_templates', null, $result);
            Response::json(['success' => true, 'message' => 'Templates synchronized from Meta', 'data' => $result]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Template sync failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplatesBulkSubmit(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $ids = array_values(array_filter(array_map('intval', is_array($input['template_ids'] ?? null) ? $input['template_ids'] : []), static function (int $value): bool {
            return $value > 0;
        }));
        if (count($ids) === 0) {
            Response::json(['success' => false, 'message' => 'template_ids are required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $service = new WhatsAppTemplateApprovalService(new WhatsAppTemplateBuilderService(), new WhatsAppMetaApiService($this->getWhatsAppSettings($pdo)));
        $results = [];
        foreach ($ids as $templateId) {
            try {
                $results[] = ['template_id' => $templateId, 'result' => $service->submit($pdo, $templateId, $adminId), 'status' => 'submitted'];
            } catch (Throwable $e) {
                $results[] = ['template_id' => $templateId, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }

        Response::json(['success' => true, 'message' => 'Bulk submit completed', 'data' => ['items' => $results]]);
    }

    public function whatsappTemplatePreview(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $template = $this->loadWhatsAppTemplate($pdo, $templateId);
        if ($template === null) {
            Response::json(['success' => false, 'message' => 'WhatsApp template not found'], 404);
            return;
        }

        $input = $this->readJsonInput();
        $context = is_array($input['context'] ?? null) ? $input['context'] : null;
        $renderer = new WhatsAppTemplateRendererService();
        $preview = $renderer->preview($template, $this->loadWhatsAppTemplateVariables($pdo, $templateId), $this->loadWhatsAppTemplateButtons($pdo, $templateId), $context);

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['preview' => $preview]]);
    }

    public function whatsappTemplateSubmit(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        try {
            $service = new WhatsAppTemplateApprovalService(new WhatsAppTemplateBuilderService(), new WhatsAppMetaApiService($this->getWhatsAppSettings($pdo)));
            $result = $service->submit($pdo, $templateId, $adminId);
            Response::json(['success' => true, 'message' => 'Template submitted to Meta', 'data' => $result]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Template submission failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplateCloneFix(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $template = $this->loadWhatsAppTemplate($pdo, $templateId);
        if ($template === null) {
            Response::json(['success' => false, 'message' => 'WhatsApp template not found'], 404);
            return;
        }

        $buttons = $this->loadWhatsAppTemplateButtons($pdo, $templateId);
        $newKey = (string)$template['template_key'] . '_fix_' . substr(md5((string)microtime(true)), 0, 4);
        $newMetaName = (new WhatsAppTemplateBuilderService())->normalizeMetaTemplateName((string)$template['meta_template_name'] . '_fix');

        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare('INSERT INTO whatsapp_templates (internal_name, template_key, meta_template_name, meta_template_id_or_reference, waba_id, phone_number_id, category, language_code, header_type, header_text, header_media_example, body_text, footer_text, buttons_json, variables_json, approval_status, approval_reason, sync_status, mapped_event_key, is_active, created_by, updated_by) VALUES (:internal_name, :template_key, :meta_template_name, NULL, :waba_id, :phone_number_id, :category, :language_code, :header_type, :header_text, :header_media_example, :body_text, :footer_text, :buttons_json, :variables_json, "draft", NULL, "local_only", :mapped_event_key, :is_active, :created_by, :updated_by)');
            $insert->execute([
                'internal_name' => (string)$template['internal_name'] . ' Clone',
                'template_key' => $newKey,
                'meta_template_name' => $newMetaName,
                'waba_id' => $template['waba_id'],
                'phone_number_id' => $template['phone_number_id'],
                'category' => $template['category'],
                'language_code' => $template['language_code'],
                'header_type' => $template['header_type'],
                'header_text' => $template['header_text'],
                'header_media_example' => $template['header_media_example'],
                'body_text' => $template['body_text'],
                'footer_text' => $template['footer_text'],
                'buttons_json' => $template['buttons_json'],
                'variables_json' => $template['variables_json'],
                'mapped_event_key' => $template['mapped_event_key'],
                'is_active' => $template['is_active'],
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
            $newTemplateId = (int)$pdo->lastInsertId();
            $this->syncWhatsAppTemplateChildren($pdo, $newTemplateId, $this->loadWhatsAppTemplateVariables($pdo, $templateId), $buttons);
            $this->createWhatsAppTemplateVersion($pdo, $newTemplateId, $adminId, 'Cloned from rejected template');
            $pdo->commit();
            Response::json(['success' => true, 'message' => 'Template cloned for revision', 'data' => ['id' => $newTemplateId]]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Clone failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplateTestSend(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        $input = $this->readJsonInput();
        $recipient = trim((string)($input['recipient'] ?? ''));
        if ($recipient === '') {
            Response::json(['success' => false, 'message' => 'recipient is required'], 422);
            return;
        }

        $context = is_array($input['context'] ?? null) ? $input['context'] : [];
        $pdo = self::db(); if (!$pdo) return;
        try {
            $dispatch = new WhatsAppDispatchService(new WhatsAppMetaApiService($this->getWhatsAppSettings($pdo)), new VariableResolverService());
            $response = $dispatch->testSend($pdo, $templateId, $recipient, $context);

            $log = $pdo->prepare('INSERT INTO communication_logs (whatsapp_template_id, channel, event_key, recipient, status, provider_message_id, payload_json, sent_at) VALUES (:whatsapp_template_id, "whatsapp", "whatsapp_test_send", :recipient, "sent", :provider_message_id, :payload_json, NOW())');
            $log->execute([
                'whatsapp_template_id' => $templateId,
                'recipient' => $recipient,
                'provider_message_id' => (string)($response['messages'][0]['id'] ?? $response['message_id'] ?? 'meta-test'),
                'payload_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
            ]);

            $this->logAdminAction($pdo, $adminId, 'test_send_whatsapp_template', 'whatsapp_templates', $templateId, ['recipient' => $recipient]);
            Response::json(['success' => true, 'message' => 'Test message sent', 'data' => ['response' => $response]]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Test send failed', 'details' => $e->getMessage()], 500);
        }
    }

    public function whatsappTemplateVersionsList(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $templateId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $this->loadWhatsAppTemplateVersions($pdo, $templateId)]]);
    }

    public function whatsappTemplateMappingsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $mapStmt = $pdo->query('SELECT m.event_key, m.template_id, m.is_active, t.internal_name, t.meta_template_name, t.approval_status FROM whatsapp_template_mappings m JOIN whatsapp_templates t ON t.id = m.template_id ORDER BY m.event_key ASC');
        $templateStmt = $pdo->query('SELECT id, internal_name, meta_template_name, mapped_event_key, approval_status FROM whatsapp_templates WHERE approval_status = "approved" AND is_active = 1 ORDER BY internal_name ASC');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $mapStmt instanceof \PDOStatement ? $mapStmt->fetchAll(PDO::FETCH_ASSOC) : [], 'approved_templates' => $templateStmt instanceof \PDOStatement ? $templateStmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function whatsappTemplateMappingUpdate(string $eventKey): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $templateId = (int)($input['template_id'] ?? 0);
        $isActive = $this->toBinaryFlag($input['is_active'] ?? 1);

        $pdo = self::db(); if (!$pdo) return;
        if ($templateId > 0) {
            $templateStmt = $pdo->prepare('SELECT approval_status FROM whatsapp_templates WHERE id = :id LIMIT 1');
            $templateStmt->execute(['id' => $templateId]);
            $status = (string)($templateStmt->fetchColumn() ?: '');
            if ($status !== 'approved') {
                Response::json(['success' => false, 'message' => 'Only approved templates can be mapped'], 422);
                return;
            }
        }

        $stmt = $pdo->prepare('INSERT INTO whatsapp_template_mappings (event_key, template_id, is_active, updated_by) VALUES (:event_key, :template_id, :is_active, :updated_by) ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), is_active = VALUES(is_active), updated_by = VALUES(updated_by), updated_at = NOW()');
        $stmt->execute([
            'event_key' => $eventKey,
            'template_id' => $templateId,
            'is_active' => $isActive,
            'updated_by' => $adminId,
        ]);

        Response::json(['success' => true, 'message' => 'Template mapping updated']);
    }

    public function whatsappLogsOverview(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $summary = [
            'draft' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM whatsapp_templates WHERE approval_status = "draft"'),
            'submitted' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM whatsapp_templates WHERE approval_status IN ("submitted", "in_review")'),
            'approved' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM whatsapp_templates WHERE approval_status = "approved"'),
            'rejected' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM whatsapp_templates WHERE approval_status = "rejected"'),
            'failed_queue' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM communication_queue WHERE channel = "whatsapp" AND queue_status = "failed"'),
            'sent_last_30d' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM communication_logs WHERE channel = "whatsapp" AND status = "sent" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
        ];

        Response::json(['success' => true, 'message' => 'ok', 'data' => $summary]);
    }

    public function whatsappSyncLogsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, template_id, sync_direction, status, message, created_at FROM whatsapp_template_sync_logs ORDER BY created_at DESC, id DESC LIMIT 200');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function whatsappApprovalLogsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT l.id, l.template_id, t.internal_name, l.previous_status, l.new_status, l.meta_reason, l.created_at FROM whatsapp_template_approval_logs l JOIN whatsapp_templates t ON t.id = l.template_id ORDER BY l.created_at DESC, l.id DESC LIMIT 200');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function whatsappSendLogsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, whatsapp_template_id, recipient, status, provider_message_id, error_message, sent_at, created_at FROM communication_logs WHERE channel = "whatsapp" ORDER BY created_at DESC, id DESC LIMIT 200');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function whatsappFailedQueueList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, communication_log_id, queue_status, attempts, last_error, available_at, updated_at FROM communication_queue WHERE channel = "whatsapp" AND queue_status = "failed" ORDER BY updated_at DESC, id DESC LIMIT 200');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function whatsappUsageReport(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT COALESCE(t.internal_name, "Unmapped") AS template_name, COUNT(*) AS send_count, SUM(CASE WHEN l.status = "sent" THEN 1 ELSE 0 END) AS sent_count, SUM(CASE WHEN l.status = "failed" THEN 1 ELSE 0 END) AS failed_count FROM communication_logs l LEFT JOIN whatsapp_templates t ON t.id = l.whatsapp_template_id WHERE l.channel = "whatsapp" GROUP BY template_name ORDER BY send_count DESC, template_name ASC LIMIT 100');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function communicationTemplatesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $channel = trim((string)($_GET['channel'] ?? ''));
        $pdo = self::db(); if (!$pdo) return;
        $sql = 'SELECT id, channel, event_key, subject, body_template, is_active, updated_at FROM communication_templates WHERE 1=1';
        $params = [];
        if ($channel !== '') {
            $sql .= ' AND channel = :channel';
            $params['channel'] = $channel;
        }
        $sql .= ' ORDER BY channel ASC, event_key ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function communicationTemplateUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        if ($templateId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid template id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $subject = $this->nullableString($input['subject'] ?? null);
        $body = trim((string)($input['body_template'] ?? ''));
        $isActive = $this->toBinaryFlag($input['is_active'] ?? 1);
        if ($body === '') {
            Response::json(['success' => false, 'message' => 'body_template is required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $templateFetch = $pdo->prepare('SELECT id, channel, event_key FROM communication_templates WHERE id = :id LIMIT 1');
        $templateFetch->execute(['id' => $templateId]);
        $templateRow = $templateFetch->fetch(PDO::FETCH_ASSOC);
        if (!$templateRow) {
            Response::json(['success' => false, 'message' => 'Template not found'], 404);
            return;
        }

        if ((string)($templateRow['channel'] ?? '') === 'email') {
            $body = $this->stripDeveloperFooterFragments($body);
        }

        $stmt = $pdo->prepare('UPDATE communication_templates SET subject = :subject, body_template = :body_template, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'subject' => $subject,
            'body_template' => $body,
            'is_active' => $isActive,
            'id' => $templateId,
        ]);

        $this->logAdminAction($pdo, $adminId, 'update_communication_template', 'communication_templates', $templateId, [
            'is_active' => $isActive,
            'channel' => (string)($templateRow['channel'] ?? ''),
            'event_key' => (string)($templateRow['event_key'] ?? ''),
        ]);
        Response::json(['success' => true, 'message' => 'Template updated']);
    }

    public function communicationTemplateSendTest(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $templateId = (int)$id;
        if ($templateId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid template id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $recipientEmail = trim((string)($input['email'] ?? ''));
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'message' => 'A valid recipient email is required'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;

        $tplStmt = $pdo->prepare('SELECT id, channel, event_key, subject, body_template FROM communication_templates WHERE id = :id LIMIT 1');
        $tplStmt->execute(['id' => $templateId]);
        $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);
        if (!$tpl) {
            Response::json(['success' => false, 'message' => 'Template not found'], 404);
            return;
        }

        if ((string)($tpl['channel'] ?? '') !== 'email') {
            Response::json(['success' => false, 'message' => 'Test send is only supported for email templates'], 422);
            return;
        }

        // Build render context: branding + generic sample data
        $branding = \App\Services\EmailBrandingService::getEmailBranding($pdo);
        $resolver = new \App\Services\VariableResolverService($pdo);
        $sampleCtx = $resolver->sampleContext();
        $context = array_merge($branding, $sampleCtx);

        $subject = (string)($tpl['subject'] ?? 'Test Email');
        $body    = (string)($tpl['body_template'] ?? '');

        // Render placeholders
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $subject = str_replace('{{' . $key . '}}', (string)$value, $subject);
                $body    = str_replace('{{' . $key . '}}', (string)$value, $body);
            }
        }

        $previewHtml = $body;

        try {
            $smtp = \App\Services\SmtpTransportService::fromDatabase($pdo);
            $smtp->send([$recipientEmail], '[TEST] ' . $subject, strip_tags($body), $body);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'SMTP send failed: ' . $e->getMessage(),
                'preview_html' => $previewHtml,
                'subject' => $subject,
            ], 500);
            return;
        }

        // Log to communication_logs for audit trail
        $logStmt = $pdo->prepare(
            'INSERT INTO communication_logs (channel, event_key, recipient, status, sent_at, created_at) VALUES (:ch, :ek, :rec, :st, NOW(), NOW())'
        );
        $logStmt->execute([
            'ch'  => 'email',
            'ek'  => 'template_test',
            'rec' => $recipientEmail,
            'st'  => 'sent',
        ]);

        $this->logAdminAction($pdo, $adminId, 'template_test_send', 'communication_templates', $templateId, [
            'recipient' => $recipientEmail,
            'event_key' => (string)($tpl['event_key'] ?? ''),
        ]);

        Response::json([
            'success'      => true,
            'message'      => 'Test email sent to ' . $recipientEmail,
            'preview_html' => $previewHtml,
            'subject'      => $subject,
        ]);
    }

    public function communicationLogsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $limit = min(300, max(20, (int)($_GET['limit'] ?? 100)));

        $pdo = self::db(); if (!$pdo) return;
        $sql = 'SELECT id, channel, event_key, recipient, status, provider_message_id, error_message, retry_count, sent_at, created_at
                FROM communication_logs
                WHERE 1=1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function communicationRetry(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $logId = (int)$id;
        if ($logId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid log id'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('SELECT id, channel, event_key, recipient, payload_json FROM communication_logs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            Response::json(['success' => false, 'message' => 'Communication log not found'], 404);
            return;
        }

        $commQueue = $pdo->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:communication_log_id, :channel, :payload_json)');
        $commQueue->execute([
            'communication_log_id' => $logId,
            'channel' => (string)$log['channel'],
            'payload_json' => json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES),
        ]);

        $queue = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", :payload_json, "queued", NOW(), 0)');
        $queue->execute([
            'payload_json' => json_encode([
                'log_id' => $logId,
                'channel' => (string)$log['channel'],
                'event_key' => (string)$log['event_key'],
                'recipient' => (string)$log['recipient'],
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $update = $pdo->prepare('UPDATE communication_logs SET status = "queued", retry_count = retry_count + 1, error_message = NULL WHERE id = :id');
        $update->execute(['id' => $logId]);

        $this->logAdminAction($pdo, $adminId, 'retry_communication', 'communication_logs', $logId, []);
        Response::json(['success' => true, 'message' => 'Retry queued']);
    }

    public function automationRulesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT
                r.id,
                r.rule_key,
                r.channel,
                r.trigger_event,
                r.template_id,
                r.offset_days,
                r.is_active,
                t.event_key AS template_event_key
             FROM automation_rules r
             LEFT JOIN communication_templates t ON t.id = r.template_id
             ORDER BY r.rule_key ASC'
        );
        $items = $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $items]]);
    }

    public function automationRuleUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $ruleId = (int)$id;
        if ($ruleId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid rule id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $offsetDays = (int)($input['offset_days'] ?? 0);
        $templateId = (int)($input['template_id'] ?? 0);
        $isActive = $this->toBinaryFlag($input['is_active'] ?? 1);

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE automation_rules SET offset_days = :offset_days, template_id = :template_id, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'offset_days' => $offsetDays,
            'template_id' => $templateId > 0 ? $templateId : null,
            'is_active' => $isActive,
            'id' => $ruleId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Rule not found or no changes'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_automation_rule', 'automation_rules', $ruleId, ['offset_days' => $offsetDays, 'is_active' => $isActive]);
        Response::json(['success' => true, 'message' => 'Automation rule updated']);
    }

    public function remindersList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $status = trim((string)($_GET['status'] ?? ''));
        $pdo = self::db(); if (!$pdo) return;
        $sql = 'SELECT
                    r.id,
                    r.reminder_type,
                    r.title,
                    r.reminder_on,
                    r.status,
                    r.notes,
                    u.full_name AS customer_name,
                    b.company_name AS company_name
                FROM reminders r
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN b2b_accounts b ON b.id = r.b2b_account_id
                WHERE 1=1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY r.reminder_on ASC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    }

    public function reminderCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $type = trim((string)($input['reminder_type'] ?? 'follow_up'));
        $title = trim((string)($input['title'] ?? ''));
        $when = trim((string)($input['reminder_on'] ?? ''));
        if ($title === '' || strtotime($when) === false) {
            Response::json(['success' => false, 'message' => 'title and valid reminder_on are required'], 422);
            return;
        }

        if (!in_array($type, ['payment_due', 'birthday', 'follow_up', 'production'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid reminder_type'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
            'INSERT INTO reminders (user_id, b2b_account_id, reminder_type, title, reminder_on, status, notes, created_by_admin_id)
             VALUES (:user_id, :b2b_account_id, :reminder_type, :title, :reminder_on, :status, :notes, :created_by_admin_id)'
        );
        $stmt->execute([
            'user_id' => (int)($input['user_id'] ?? 0) > 0 ? (int)$input['user_id'] : null,
            'b2b_account_id' => (int)($input['b2b_account_id'] ?? 0) > 0 ? (int)$input['b2b_account_id'] : null,
            'reminder_type' => $type,
            'title' => $title,
            'reminder_on' => date('Y-m-d H:i:s', (int)strtotime($when)),
            'status' => 'pending',
            'notes' => $this->nullableString($input['notes'] ?? null),
            'created_by_admin_id' => $adminId,
        ]);

        Response::json(['success' => true, 'message' => 'Reminder created'], 201);
    }

    public function reminderUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $reminderId = (int)$id;
        if ($reminderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid reminder id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $status = trim((string)($input['status'] ?? ''));
        if (!in_array($status, ['pending', 'done', 'cancelled'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid reminder status'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE reminders SET status = :status, notes = :notes WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'notes' => $this->nullableString($input['notes'] ?? null),
            'id' => $reminderId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'Reminder not found or no changes'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_reminder', 'reminders', $reminderId, ['status' => $status]);
        Response::json(['success' => true, 'message' => 'Reminder updated']);
    }

    public function upcomingBirthdays(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $days = min(90, max(1, (int)($_GET['days'] ?? 30)));
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare(
            'SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                cp.date_of_birth,
                cp.anniversary_date,
                cp.celebration_date
             FROM customer_profiles cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.date_of_birth IS NOT NULL
             ORDER BY DATE_FORMAT(cp.date_of_birth, "%m-%d") ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new \DateTimeImmutable('today');
        $items = [];
        foreach ($rows as $row) {
            $dob = (string)($row['date_of_birth'] ?? '');
            if ($dob === '') {
                continue;
            }
            $dobDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dob);
            if (!$dobDate) {
                continue;
            }
            $next = $dobDate->setDate((int)$today->format('Y'), (int)$dobDate->format('m'), (int)$dobDate->format('d'));
            if ($next < $today) {
                $next = $next->modify('+1 year');
            }
            $delta = (int)$today->diff($next)->format('%a');
            if ($delta <= $days) {
                $row['next_birthday_on'] = $next->format('Y-m-d');
                $row['days_left'] = $delta;
                $items[] = $row;
            }
        }

        usort($items, static function (array $a, array $b): int {
            return ((int)$a['days_left']) <=> ((int)$b['days_left']);
        });
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $items]]);
    }

    public function customersList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.role,
                u.is_active,
                cp.date_of_birth,
                cp.anniversary_date,
                cp.celebration_date,
                cp.internal_note,
                COUNT(DISTINCT o.id) AS order_count,
                COUNT(DISTINCT i.id) AS invoice_count,
                COALESCE(GROUP_CONCAT(DISTINCT ct.tag_name ORDER BY ct.tag_name SEPARATOR ", "), "") AS tags
             FROM users u
             LEFT JOIN customer_profiles cp ON cp.user_id = u.id
             LEFT JOIN orders o ON o.user_id = u.id
             LEFT JOIN invoices i ON i.user_id = u.id
             LEFT JOIN customer_tag_map ctm ON ctm.user_id = u.id
             LEFT JOIN customer_tags ct ON ct.id = ctm.tag_id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT 300'
        );

        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function b2bAccountsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT
                b.id,
                b.company_name,
                b.account_type,
                b.company_email,
                b.company_phone,
                b.approval_status,
                b.credit_limit,
                b.notes,
                u.full_name AS owner_name,
                a.full_name AS assigned_admin_name,
                COUNT(DISTINCT q.id) AS quote_count,
                COUNT(DISTINCT bo.id) AS order_count
             FROM b2b_accounts b
             JOIN users u ON u.id = b.user_id
             LEFT JOIN admins a ON a.id = b.assigned_admin_id
             LEFT JOIN b2b_quotes q ON q.b2b_account_id = b.id
             LEFT JOIN b2b_orders bo ON bo.b2b_account_id = b.id
             GROUP BY b.id
             ORDER BY b.updated_at DESC, b.id DESC'
        );
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function b2bAccountUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $accountId = (int)$id;
        $input = $this->readJsonInput();
        $approvalStatus = (string)($input['approval_status'] ?? 'pending');
        $accountType = (string)($input['account_type'] ?? 'corporate_client');
        if (!in_array($approvalStatus, ['pending', 'approved', 'rejected', 'suspended'], true) || !in_array($accountType, ['corporate_client', 'business_buyer', 'reseller', 'cake_shop_owner'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid B2B account payload'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE b2b_accounts SET approval_status = :approval_status, account_type = :account_type, credit_limit = :credit_limit, notes = :notes, assigned_admin_id = :assigned_admin_id WHERE id = :id');
        $stmt->execute([
            'approval_status' => $approvalStatus,
            'account_type' => $accountType,
            'credit_limit' => ($input['credit_limit'] ?? null) !== null && (string)$input['credit_limit'] !== '' ? round((float)$input['credit_limit'], 2) : null,
            'notes' => $this->nullableString($input['notes'] ?? null),
            'assigned_admin_id' => $adminId,
            'id' => $accountId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'B2B account not found or no changes'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_b2b_account', 'b2b_accounts', $accountId, ['approval_status' => $approvalStatus]);
        Response::json(['success' => true, 'message' => 'B2B account updated']);
    }

    public function b2bQuotesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query(
            'SELECT q.id, q.quote_number, q.event_type, q.fulfilment_mode, q.scheduled_date, q.status, q.grand_total, q.admin_note, b.company_name
             FROM b2b_quotes q
             JOIN b2b_accounts b ON b.id = q.b2b_account_id
             ORDER BY q.updated_at DESC, q.id DESC'
        );
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function b2bQuoteUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $quoteId = (int)$id;
        $input = $this->readJsonInput();
        $status = (string)($input['status'] ?? 'requested');
        if (!in_array($status, ['requested', 'drafted', 'sent', 'accepted', 'rejected', 'converted_to_order'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid quote status'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE b2b_quotes SET status = :status, admin_note = :admin_note, scheduled_date = :scheduled_date WHERE id = :id');
        $stmt->execute([
            'status' => $status,
            'admin_note' => $this->nullableString($input['admin_note'] ?? null),
            'scheduled_date' => $this->nullableString($input['scheduled_date'] ?? null),
            'id' => $quoteId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'B2B quote not found or no changes'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_b2b_quote', 'b2b_quotes', $quoteId, ['status' => $status]);
        Response::json(['success' => true, 'message' => 'B2B quote updated']);
    }

    public function b2bQuoteConvertToOrder(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $quoteId = (int)$id;
        $pdo = self::db(); if (!$pdo) return;
        $quoteStmt = $pdo->prepare('SELECT * FROM b2b_quotes WHERE id = :id LIMIT 1');
        $quoteStmt->execute(['id' => $quoteId]);
        $quote = $quoteStmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) {
            Response::json(['success' => false, 'message' => 'B2B quote not found'], 404);
            return;
        }

        $existingOrderStmt = $pdo->prepare('SELECT id FROM b2b_orders WHERE source_quote_id = :source_quote_id LIMIT 1');
        $existingOrderStmt->execute(['source_quote_id' => $quoteId]);
        if ($existingOrderStmt->fetchColumn()) {
            Response::json(['success' => false, 'message' => 'Quote already converted to order'], 409);
            return;
        }

        $orderNumber = 'B2B-' . date('Ymd') . '-' . str_pad((string)$quoteId, 4, '0', STR_PAD_LEFT);
        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare('INSERT INTO b2b_orders (order_number, b2b_account_id, source_quote_id, fulfilment_mode, order_status, payment_status, subtotal, discount_total, tax_total, delivery_fee, grand_total, internal_note) VALUES (:order_number, :b2b_account_id, :source_quote_id, :fulfilment_mode, "pending", "pending", :subtotal, :discount_total, :tax_total, 0, :grand_total, :internal_note)');
            $orderStmt->execute([
                'order_number' => $orderNumber,
                'b2b_account_id' => (int)$quote['b2b_account_id'],
                'source_quote_id' => $quoteId,
                'fulfilment_mode' => (string)$quote['fulfilment_mode'],
                'subtotal' => (float)$quote['subtotal'],
                'discount_total' => (float)$quote['discount_total'],
                'tax_total' => (float)$quote['tax_total'],
                'grand_total' => (float)$quote['grand_total'],
                'internal_note' => $this->nullableString($quote['admin_note'] ?? null),
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemsStmt = $pdo->prepare('SELECT product_id, variant_id, quantity, unit_price, line_total, customisation_note FROM b2b_quote_items WHERE quote_id = :quote_id');
            $itemsStmt->execute(['quote_id' => $quoteId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $insertItem = $pdo->prepare('INSERT INTO b2b_order_items (b2b_order_id, product_id, variant_id, quantity, unit_price, line_total, customisation_note) VALUES (:b2b_order_id, :product_id, :variant_id, :quantity, :unit_price, :line_total, :customisation_note)');
            foreach ($items as $item) {
                $insertItem->execute([
                    'b2b_order_id' => $orderId,
                    'product_id' => (int)$item['product_id'],
                    'variant_id' => (int)($item['variant_id'] ?? 0) > 0 ? (int)$item['variant_id'] : null,
                    'quantity' => (int)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'line_total' => (float)$item['line_total'],
                    'customisation_note' => $this->nullableString($item['customisation_note'] ?? null),
                ]);
            }

            $updateQuote = $pdo->prepare('UPDATE b2b_quotes SET status = "converted_to_order" WHERE id = :id');
            $updateQuote->execute(['id' => $quoteId]);
            $pdo->commit();

            $this->logAdminAction($pdo, $adminId, 'convert_b2b_quote', 'b2b_quotes', $quoteId, ['order_id' => $orderId]);
            Response::json(['success' => true, 'message' => 'B2B quote converted to order', 'data' => ['order_id' => $orderId, 'order_number' => $orderNumber]]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Failed to convert quote', 'details' => $e->getMessage()], 500);
        }
    }

    public function b2bOrdersList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT o.id, o.order_number, o.fulfilment_mode, o.order_status, o.payment_status, o.grand_total, o.internal_note, o.updated_at, b.company_name FROM b2b_orders o JOIN b2b_accounts b ON b.id = o.b2b_account_id ORDER BY o.updated_at DESC, o.id DESC');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function b2bOrderUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $orderId = (int)$id;
        $input = $this->readJsonInput();
        $orderStatus = (string)($input['order_status'] ?? 'pending');
        $paymentStatus = (string)($input['payment_status'] ?? 'pending');
        if (!in_array($orderStatus, ['pending', 'confirmed', 'in_production', 'ready', 'completed', 'cancelled'], true) || !in_array($paymentStatus, ['pending', 'paid', 'part_paid', 'failed'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid B2B order payload'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE b2b_orders SET order_status = :order_status, payment_status = :payment_status, internal_note = :internal_note WHERE id = :id');
        $stmt->execute([
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'internal_note' => $this->nullableString($input['internal_note'] ?? null),
            'id' => $orderId,
        ]);

        if ($stmt->rowCount() < 1) {
            Response::json(['success' => false, 'message' => 'B2B order not found or no changes'], 404);
            return;
        }

        $this->logAdminAction($pdo, $adminId, 'update_b2b_order', 'b2b_orders', $orderId, ['order_status' => $orderStatus, 'payment_status' => $paymentStatus]);
        Response::json(['success' => true, 'message' => 'B2B order updated']);
    }

    public function bannersList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT * FROM banners ORDER BY placement ASC, sort_order ASC, id ASC');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function bannerCreate(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        $input = $this->readJsonInput();
        $placement = (string)($input['placement'] ?? 'home_hero');
        if (!in_array($placement, ['home_hero', 'home_mid', 'shop_top', 'course_top', 'b2b_top'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid banner placement'], 422);
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, placement, is_active, sort_order) VALUES (:title, :subtitle, :image_url, :cta_label, :cta_url, :placement, :is_active, :sort_order)');
        $stmt->execute([
            'title' => trim((string)($input['title'] ?? 'Untitled Banner')),
            'subtitle' => $this->nullableString($input['subtitle'] ?? null),
            'image_url' => $this->nullableString($input['image_url'] ?? null),
            'cta_label' => $this->nullableString($input['cta_label'] ?? null),
            'cta_url' => $this->nullableString($input['cta_url'] ?? null),
            'placement' => $placement,
            'is_active' => $this->toBinaryFlag($input['is_active'] ?? 1),
            'sort_order' => (int)($input['sort_order'] ?? 0),
        ]);
        Response::json(['success' => true, 'message' => 'Banner created'], 201);
    }

    public function bannerUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        $bannerId = (int)$id;
        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;
        $currentStmt = $pdo->prepare('SELECT image_url FROM banners WHERE id = :id LIMIT 1');
        $currentStmt->execute(['id' => $bannerId]);
        $currentRow = $currentStmt->fetch(PDO::FETCH_ASSOC);
        $oldImageUrl = (string)($currentRow['image_url'] ?? '');

        $placement = (string)($input['placement'] ?? 'home_hero');
        if (!in_array($placement, ['home_hero', 'home_mid', 'shop_top', 'course_top', 'b2b_top'], true)) {
            Response::json(['success' => false, 'message' => 'Invalid banner placement'], 422);
            return;
        }

        $newImageUrl = $this->nullableString($input['image_url'] ?? null);
        $stmt = $pdo->prepare('UPDATE banners SET title = :title, subtitle = :subtitle, image_url = :image_url, cta_label = :cta_label, cta_url = :cta_url, placement = :placement, is_active = :is_active, sort_order = :sort_order WHERE id = :id');
        $stmt->execute([
            'title' => trim((string)($input['title'] ?? 'Untitled Banner')),
            'subtitle' => $this->nullableString($input['subtitle'] ?? null),
            'image_url' => $newImageUrl,
            'cta_label' => $this->nullableString($input['cta_label'] ?? null),
            'cta_url' => $this->nullableString($input['cta_url'] ?? null),
            'placement' => $placement,
            'is_active' => $this->toBinaryFlag($input['is_active'] ?? 1),
            'sort_order' => (int)($input['sort_order'] ?? 0),
            'id' => $bannerId,
        ]);
        $this->deleteMediaFileIfUnreferenced($pdo, $oldImageUrl, (string)$newImageUrl);
        Response::json(['success' => true, 'message' => 'Banner updated']);
    }

    public function bannerDelete(string $id): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $existingStmt = $pdo->prepare('SELECT image_url FROM banners WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => (int)$id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        $oldImageUrl = (string)($existing['image_url'] ?? '');
        $stmt = $pdo->prepare('DELETE FROM banners WHERE id = :id');
        $stmt->execute(['id' => (int)$id]);
        $this->deleteMediaFileIfUnreferenced($pdo, $oldImageUrl, null);
        Response::json(['success' => true, 'message' => 'Banner deleted']);
    }

    public function pagesList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, title, slug, seo_title, seo_description, is_published, updated_at FROM pages ORDER BY title ASC');
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []]]);
    }

    public function pageUpdate(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }
        $pageId = (int)$id;
        $input = $this->readJsonInput();
        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->prepare('UPDATE pages SET title = :title, content = :content, seo_title = :seo_title, seo_description = :seo_description, is_published = :is_published WHERE id = :id');
        $stmt->execute([
            'title' => trim((string)($input['title'] ?? 'Untitled Page')),
            'content' => trim((string)($input['content'] ?? '')),
            'seo_title' => $this->nullableString($input['seo_title'] ?? null),
            'seo_description' => $this->nullableString($input['seo_description'] ?? null),
            'is_published' => $this->toBinaryFlag($input['is_published'] ?? 1),
            'id' => $pageId,
        ]);
        $this->logAdminAction($pdo, $adminId, 'update_page_content', 'pages', $pageId, []);
        Response::json(['success' => true, 'message' => 'Page updated']);
    }

    public function reportsSummary(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }
        $pdo = self::db(); if (!$pdo) return;
        $monthReconciliation = (new FinancialReconciliationService())->summarizeCurrentMonth();
        $monthOrders = $monthReconciliation['orders'] ?? [];
        $monthRefunds = $monthReconciliation['refunds'] ?? [];

        Response::json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'retail_orders' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM orders'),
                'b2b_orders' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM b2b_orders'),
                'pending_invoices' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM invoices WHERE invoice_status IN ("pending_payment", "part_paid", "overdue", "payment_under_verification", "unpaid_rejected")'),
                'queued_communications' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM communication_logs WHERE status = "queued"'),
                'failed_communications' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM communication_logs WHERE status = "failed"'),
                'whatsapp_approved_templates' => $this->fetchCount($pdo, 'SELECT COUNT(*) FROM whatsapp_templates WHERE approval_status = "approved"'),
                'this_month_collected' => (float)($monthOrders['realized_total'] ?? 0),
                'this_month_refunded' => (float)($monthOrders['refunded_total'] ?? 0),
                'this_month_outstanding' => (float)($monthOrders['outstanding_total'] ?? 0),
                'pending_refunds' => (int)($monthRefunds['pending_count'] ?? 0),
                'processed_refunds' => (int)($monthRefunds['processed_count'] ?? 0),
                'reconciliation_status' => (string)($monthReconciliation['status'] ?? 'attention'),
                'reconciliation_variance' => (float)(($monthReconciliation['variance']['absolute_sum'] ?? 0)),
                'reconciliation_breakdown' => [
                    'cash' => (float)($monthReconciliation['variance']['cash'] ?? 0),
                    'bank' => (float)($monthReconciliation['variance']['bank'] ?? 0),
                    'refund' => (float)($monthReconciliation['variance']['refund'] ?? 0),
                    'net' => (float)($monthReconciliation['variance']['net'] ?? 0),
                    'component_status' => $monthReconciliation['variance']['component_status'] ?? [],
                ],
                'reconciliation_sources' => $monthReconciliation['source_tables'] ?? [],
                'reconciliation_window' => $monthReconciliation['window'] ?? ['from_date' => date('Y-m-01'), 'to_date' => date('Y-m-d')],
            ],
        ]);
    }

    public function queueJobsList(): void
    {
        if ($this->requireAdminId() === null) {
            return;
        }

        $pdo = self::db(); if (!$pdo) return;
        $stmt = $pdo->query('SELECT id, job_type, status, available_at, attempts, last_error, created_at, updated_at FROM queue_jobs ORDER BY created_at DESC, id DESC LIMIT 200');
        $items = $stmt instanceof \PDOStatement ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'message' => 'ok', 'data' => ['items' => $items]]);
    }

    public function queueProcessNow(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $input = $this->readJsonInput();
        $maxJobs = (int)($input['max_jobs'] ?? 25);

        $pdo = self::db(); if (!$pdo) return;
        $result = QueueWorker::process($pdo, $maxJobs);

        $this->logAdminAction($pdo, $adminId, 'process_queue_now', 'queue_jobs', null, [
            'max_jobs' => $maxJobs,
            'processed' => (int)($result['processed'] ?? 0),
            'completed' => (int)($result['completed'] ?? 0),
            'failed' => (int)($result['failed'] ?? 0),
            'requeued' => (int)($result['requeued'] ?? 0),
        ]);

        Response::json(['success' => true, 'message' => 'Queue processing run completed', 'data' => $result]);
    }

    /** @return array<string,mixed> */
    private function readJsonInput(): array
    {
        return Request::json();
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $cacheKey = strtolower($table . '.' . $column);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
            );
            $stmt->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);
            $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            $cache[$cacheKey] = false;
        }

        return $cache[$cacheKey];
    }

    private function stripDeveloperFooterFragments(string $html): string
    {
        $original = trim($html);
        $output = $original;

        // Remove known locked footer wrappers if present in edited payload.
        $output = preg_replace('/<div[^>]*id=["\']dcore-dev-footer-lock["\'][^>]*>[\s\S]*?<\/div>/i', '', $output) ?? $output;

        // Remove raw developer credit lines to keep editor body clean; footer is enforced at send-time.
        $output = preg_replace('/<div[^>]*>[\s\S]*?dcoresystems\.com[\s\S]*?<\/div>/i', '', $output) ?? $output;
        $output = preg_replace('/Developed\s+by\s+dcoresystems\.com/i', '', $output) ?? $output;

        $cleaned = trim($output);
        if ($cleaned === '') {
            return $original;
        }
        if (strlen($original) > 0) {
            $ratio = strlen($cleaned) / strlen($original);
            if ($ratio < 0.7) {
                return $original;
            }
        }

        return $cleaned;
    }

    private function requireAdminId(): ?int
    {
        $adminId = (int)($_SESSION['admin_id'] ?? ($_SESSION['admin'] ?? 0));
        if ($adminId <= 0) {
            Response::json(['success' => false, 'message' => 'Admin authentication required'], 401);
            return null;
        }

        if (empty($_SESSION['admin_otp_verified'])) {
            Response::json(['success' => false, 'message' => 'Admin OTP authentication required'], 401);
            return null;
        }

        if (!isset($_SESSION['admin_id'])) {
            $_SESSION['admin_id'] = $adminId;
        }

        return $adminId;
    }

    /** @return array<int,string> */
    private function resolveAdminPermissions(PDO $pdo, int $adminId): array
    {
        $sessionPermissions = $_SESSION['admin_permissions'] ?? null;
        if (is_array($sessionPermissions) && count($sessionPermissions) > 0) {
            return array_values(array_unique(array_map(static fn($v): string => trim((string)$v), $sessionPermissions)));
        }

        $stmt = $pdo->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = :admin_id');
        $stmt->execute(['admin_id' => $adminId]);
        $permissions = array_map(
            static fn(array $row): string => trim((string)($row['permission_key'] ?? '')),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
        $permissions = array_values(array_filter(array_unique($permissions), static fn(string $perm): bool => $perm !== ''));
        $_SESSION['admin_permissions'] = $permissions;

        return $permissions;
    }

    private function ensureBankAlertSchema(PDO $pdo): void
    {
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
    }

    /** @param array<int, array<string, mixed>> $variants */
    private function replaceProductVariants(PDO $pdo, int $productId, array $variants): void
    {
        $deleteStmt = $pdo->prepare('DELETE FROM product_variants WHERE product_id = :product_id');
        $deleteStmt->execute(['product_id' => $productId]);

        if (count($variants) === 0) {
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO product_variants (
                product_id, variant_label, variant_name, weight_or_size, unit_type, flavor, price, discount_price,
                stock_quantity, sku_suffix, sku, is_default, is_active
             ) VALUES (
                :product_id, :variant_label, :variant_name, :weight_or_size, :unit_type, :flavor, :price, :discount_price,
                :stock_quantity, :sku_suffix, :sku, :is_default, 1
             )'
        );

        $seenVariantKeys = [];
        foreach ($variants as $index => $variant) {
            $label = trim((string)($variant['variant_label'] ?? ($variant['variant_name'] ?? '')));
            $variantName = trim((string)($variant['variant_name'] ?? $label));
            $unitType = trim((string)($variant['unit_type'] ?? 'custom'));
            $price = (float)($variant['price'] ?? 0);
            if ($label === '' || $variantName === '' || $unitType === '' || $price <= 0) {
                continue;
            }

            $uniqueKey = strtolower($variantName . '|' . $unitType);
            if (isset($seenVariantKeys[$uniqueKey])) {
                continue;
            }
            $seenVariantKeys[$uniqueKey] = true;

            $insertStmt->execute([
                'product_id' => $productId,
                'variant_label' => $label,
                'variant_name' => $variantName,
                'weight_or_size' => trim((string)($variant['weight_or_size'] ?? $label)),
                'unit_type' => $unitType,
                'flavor' => $this->nullableString($variant['flavor'] ?? null),
                'price' => round($price, 2),
                'discount_price' => ($variant['discount_price'] ?? null) !== null && (string)$variant['discount_price'] !== '' ? round((float)$variant['discount_price'], 2) : null,
                'stock_quantity' => max(0, (int)($variant['stock_quantity'] ?? 0)),
                'sku_suffix' => $this->nullableString($variant['sku_suffix'] ?? ($variant['sku'] ?? null)),
                'sku' => $this->nullableString($variant['sku'] ?? null),
                'is_default' => (int)($variant['is_default'] ?? ($index === 0 ? 1 : 0)) === 1 ? 1 : 0,
            ]);
        }
    }

    /** @return array<string, int> */
    private function normalizeHeaderMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $cell) {
            $key = strtolower(trim((string)$cell));
            $key = str_replace([' ', '-'], '_', $key);
            if ($key !== '') {
                $map[$key] = (int)$index;
            }
        }

        return $map;
    }

    /** @param array<int, string> $row
     *  @param array<string, int> $headerMap
     *  @return array<string, string>
     */
    private function mapImportRow(array $row, array $headerMap): array
    {
        $valueFor = static function (string $name) use ($row, $headerMap): string {
            if (!array_key_exists($name, $headerMap)) {
                return '';
            }
            $index = $headerMap[$name];
            return isset($row[$index]) ? trim((string)$row[$index]) : '';
        };

        return [
            'product_name' => $valueFor('product_name'),
            'category_slug' => $valueFor('category_slug'),
            'description' => $valueFor('description'),
            'price' => $valueFor('price'),
            'discount_price' => $valueFor('discount_price'),
            'sku' => $valueFor('sku'),
            'stock' => $valueFor('stock'),
            'tags' => strtolower($valueFor('tags')),
            'variant_info' => $valueFor('variant_info'),
            'image_url' => $valueFor('image_url'),
            'dietary_type' => strtolower($valueFor('dietary_type')),
            'is_veg' => $valueFor('is_veg'),
        ];
    }

    /**
     * @param array<string,string> $record
     * @param array<int,string> $requiredVariantLabels
     */
    private function validateImportRecord(array $record, bool $strictVariants, array $requiredVariantLabels): ?string
    {
        if ($record['product_name'] === '') {
            return 'product_name is required';
        }
        if ($record['category_slug'] === '') {
            return 'category_slug is required';
        }
        if ($record['sku'] === '') {
            return 'sku is required';
        }
        if (!is_numeric($record['price']) || (float)$record['price'] <= 0) {
            return 'price must be a positive number';
        }
        if ($record['stock'] !== '' && !is_numeric($record['stock'])) {
            return 'stock must be numeric';
        }
        if ($record['discount_price'] !== '' && (!is_numeric($record['discount_price']) || (float)$record['discount_price'] < 0)) {
            return 'discount_price must be numeric and non-negative';
        }
        if ($record['dietary_type'] !== '' && !in_array($record['dietary_type'], ['veg', 'nonveg'], true)) {
            return 'dietary_type must be veg or nonveg';
        }

        if ($strictVariants) {
            if ($record['variant_info'] === '') {
                return 'variant_info is required in strict mode';
            }

            $presentLabels = [];
            $chunks = array_filter(array_map('trim', explode('|', (string)$record['variant_info'])));
            foreach ($chunks as $chunk) {
                $parts = array_map('trim', explode(':', $chunk));
                if (count($parts) < 2 || $parts[0] === '' || !is_numeric($parts[1]) || (float)$parts[1] <= 0) {
                    return 'variant_info contains invalid pair. Expected label:price';
                }
                $presentLabels[] = strtolower($parts[0]);
            }

            $presentLabels = array_values(array_unique($presentLabels));
            $missing = [];
            foreach ($requiredVariantLabels as $requiredLabel) {
                if (!in_array(strtolower($requiredLabel), $presentLabels, true)) {
                    $missing[] = $requiredLabel;
                }
            }
            if (count($missing) > 0) {
                return 'variant_info missing required labels: ' . implode(', ', $missing);
            }
        }

        return null;
    }

    private function findCategoryIdBySlug(PDO $pdo, string $slug): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        return $id > 0 ? $id : null;
    }

    /** @return array<int, array<string, mixed>> */
    private function parseImportVariants(string $variantInfo, float $defaultPrice, int $stock, bool $strictVariants = false): array
    {
        $variants = [];
        $chunks = array_filter(array_map('trim', explode('|', $variantInfo)));

        foreach ($chunks as $index => $chunk) {
            $parts = array_map('trim', explode(':', $chunk));
            if (count($parts) < 2 || !is_numeric($parts[1])) {
                continue;
            }
            $label = $parts[0];
            $price = (float)$parts[1];
            if ($label === '' || $price <= 0) {
                continue;
            }

            $variants[] = [
                'variant_label' => $label,
                'variant_name' => $label,
                'weight_or_size' => $label,
                'unit_type' => 'size',
                'price' => $price,
                'stock_quantity' => $stock,
                'is_default' => $index === 0 ? 1 : 0,
            ];
        }

        if (count($variants) === 0 && !$strictVariants) {
            $variants[] = [
                'variant_label' => '1 lb',
                'variant_name' => '1 lb',
                'weight_or_size' => '1 lb',
                'unit_type' => 'size',
                'price' => $defaultPrice,
                'stock_quantity' => $stock,
                'is_default' => 1,
            ];
        }

        return $variants;
    }

    /** @param array<string, mixed> $metadata */
    private function logAdminAction(PDO $pdo, int $adminId, string $actionType, string $targetType, ?int $targetId, array $metadata): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO admin_action_logs (admin_id, action_type, target_type, target_id, metadata_json)
                 VALUES (:admin_id, :action_type, :target_type, :target_id, :metadata_json)'
            );
            $stmt->execute([
                'admin_id'      => $adminId,
                'action_type'   => $actionType,
                'target_type'   => $targetType,
                'target_id'     => $targetId,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable $e) {
            error_log('[logAdminAction] ' . $e->getMessage());
        }
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function storagePath(string $segment): string
    {
        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . trim($segment, '/\\');
    }

    /** @return array<string,mixed> */
    private function getWhatsAppSettings(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT * FROM whatsapp_settings ORDER BY id DESC LIMIT 1');
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        return is_array($row) ? $row : [];
    }

    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    private function normalizeWhatsAppTemplatePayload(array $input, int $adminId, ?int $templateId): array
    {
        $builder = new WhatsAppTemplateBuilderService();
        $templateKey = trim((string)($input['template_key'] ?? ''));
        $templateKey = $templateKey !== '' ? $templateKey : $builder->normalizeMetaTemplateName((string)($input['internal_name'] ?? ''));
        $metaTemplateName = $builder->normalizeMetaTemplateName((string)($input['meta_template_name'] ?? $templateKey));
        $variablesJson = is_array($input['variables'] ?? null) ? $input['variables'] : [];
        $buttonsJson = is_array($input['buttons'] ?? null) ? $input['buttons'] : [];

        return [
            'internal_name' => trim((string)($input['internal_name'] ?? 'Untitled WhatsApp Template')),
            'template_key' => $templateKey,
            'meta_template_name' => $metaTemplateName,
            'waba_id' => $this->nullableString($input['waba_id'] ?? null),
            'phone_number_id' => $this->nullableString($input['phone_number_id'] ?? null),
            'category' => in_array((string)($input['category'] ?? 'utility'), ['utility', 'marketing', 'authentication'], true) ? (string)$input['category'] : 'utility',
            'language_code' => $this->nullableString($input['language_code'] ?? null) ?? 'en_US',
            'header_type' => in_array((string)($input['header_type'] ?? 'none'), ['none', 'text', 'image', 'video', 'document'], true) ? (string)$input['header_type'] : 'none',
            'header_text' => $this->nullableString($input['header_text'] ?? null),
            'header_media_example' => $this->nullableString($input['header_media_example'] ?? null),
            'body_text' => trim((string)($input['body_text'] ?? '')),
            'footer_text' => $this->nullableString($input['footer_text'] ?? null),
            'buttons_json' => json_encode($buttonsJson, JSON_UNESCAPED_SLASHES),
            'variables_json' => json_encode($variablesJson, JSON_UNESCAPED_SLASHES),
            'approval_status' => in_array((string)($input['approval_status'] ?? 'draft'), ['draft', 'ready_to_submit', 'submitted', 'in_review', 'approved', 'rejected', 'paused', 'disabled', 'archived'], true) ? (string)$input['approval_status'] : 'draft',
            'approval_reason' => $this->nullableString($input['approval_reason'] ?? null),
            'sync_status' => in_array((string)($input['sync_status'] ?? 'local_only'), ['local_only', 'pending_sync', 'synced', 'sync_failed'], true) ? (string)$input['sync_status'] : 'local_only',
            'mapped_event_key' => $this->nullableString($input['mapped_event_key'] ?? null),
            'is_active' => $this->toBinaryFlag($input['is_active'] ?? 1),
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ];
    }

    /** @return array<string,mixed>|null */
    private function loadWhatsAppTemplate(PDO $pdo, int $templateId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadWhatsAppTemplateVariables(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_template_variables WHERE template_id = :template_id ORDER BY component_scope ASC, parameter_order ASC, id ASC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function loadWhatsAppTemplateButtons(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_template_buttons WHERE template_id = :template_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function loadWhatsAppTemplateVersions(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT id, version_number, snapshot_json, change_note, created_at FROM whatsapp_template_versions WHERE template_id = :template_id ORDER BY version_number DESC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function loadWhatsAppTemplateApprovalLogs(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT id, previous_status, new_status, meta_reason, response_payload_json, created_at FROM whatsapp_template_approval_logs WHERE template_id = :template_id ORDER BY created_at DESC, id DESC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<int,array<string,mixed>> $variables
     *  @param array<int,array<string,mixed>> $buttons
     */
    private function syncWhatsAppTemplateChildren(PDO $pdo, int $templateId, array $variables, array $buttons): void
    {
        $deleteVars = $pdo->prepare('DELETE FROM whatsapp_template_variables WHERE template_id = :template_id');
        $deleteVars->execute(['template_id' => $templateId]);
        $deleteButtons = $pdo->prepare('DELETE FROM whatsapp_template_buttons WHERE template_id = :template_id');
        $deleteButtons->execute(['template_id' => $templateId]);

        if (count($variables) > 0) {
            $insertVar = $pdo->prepare('INSERT INTO whatsapp_template_variables (template_id, variable_key, variable_label, component_scope, parameter_order, fallback_value, is_required) VALUES (:template_id, :variable_key, :variable_label, :component_scope, :parameter_order, :fallback_value, :is_required)');
            foreach ($variables as $variable) {
                $insertVar->execute([
                    'template_id' => $templateId,
                    'variable_key' => trim((string)($variable['variable_key'] ?? '')),
                    'variable_label' => trim((string)($variable['variable_label'] ?? 'Variable')),
                    'component_scope' => in_array((string)($variable['component_scope'] ?? 'body'), ['header', 'body', 'footer', 'button'], true) ? (string)$variable['component_scope'] : 'body',
                    'parameter_order' => max(1, (int)($variable['parameter_order'] ?? 1)),
                    'fallback_value' => $this->nullableString($variable['fallback_value'] ?? null),
                    'is_required' => $this->toBinaryFlag($variable['is_required'] ?? 1),
                ]);
            }
        }

        if (count($buttons) > 0) {
            $insertButton = $pdo->prepare('INSERT INTO whatsapp_template_buttons (template_id, button_type, button_text, button_value, sort_order) VALUES (:template_id, :button_type, :button_text, :button_value, :sort_order)');
            foreach ($buttons as $index => $button) {
                $insertButton->execute([
                    'template_id' => $templateId,
                    'button_type' => in_array((string)($button['button_type'] ?? 'quick_reply'), ['quick_reply', 'url', 'phone'], true) ? (string)$button['button_type'] : 'quick_reply',
                    'button_text' => trim((string)($button['button_text'] ?? '')),
                    'button_value' => $this->nullableString($button['button_value'] ?? null),
                    'sort_order' => (int)($button['sort_order'] ?? $index),
                ]);
            }
        }
    }

    private function createWhatsAppTemplateVersion(PDO $pdo, int $templateId, int $adminId, string $changeNote): void
    {
        $template = $this->loadWhatsAppTemplate($pdo, $templateId);
        if ($template === null) {
            return;
        }

        $versionStmt = $pdo->prepare('SELECT COALESCE(MAX(version_number), 0) FROM whatsapp_template_versions WHERE template_id = :template_id');
        $versionStmt->execute(['template_id' => $templateId]);
        $nextVersion = (int)($versionStmt->fetchColumn() ?: 0) + 1;

        $snapshot = [
            'template' => $template,
            'variables' => $this->loadWhatsAppTemplateVariables($pdo, $templateId),
            'buttons' => $this->loadWhatsAppTemplateButtons($pdo, $templateId),
        ];

        $insert = $pdo->prepare('INSERT INTO whatsapp_template_versions (template_id, version_number, snapshot_json, change_note, created_by) VALUES (:template_id, :version_number, :snapshot_json, :change_note, :created_by)');
        $insert->execute([
            'template_id' => $templateId,
            'version_number' => $nextVersion,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES),
            'change_note' => $changeNote,
            'created_by' => $adminId,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function whatsAppDefaultTemplatePresets(): array
    {
        return [
            ['internal_name' => 'Order Confirmation WhatsApp', 'template_key' => 'order_confirmation_whatsapp', 'meta_template_name' => 'order_confirmation_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'text', 'header_text' => 'Cakeouflage Update', 'body_text' => 'Hi {{first_name}}, your order {{order_number}} has been confirmed.', 'footer_text' => 'We bake sweet wonderful happy memories.', 'mapped_event_key' => 'order_created', 'buttons' => [['button_type' => 'quick_reply', 'button_text' => 'View Order']]],
            ['internal_name' => 'Payment Pending WhatsApp', 'template_key' => 'payment_pending_whatsapp', 'meta_template_name' => 'payment_pending_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hello {{first_name}}, invoice {{invoice_number}} of {{invoice_amount}} is pending until {{due_date}}.', 'footer_text' => 'Share your UPI reference after payment.', 'mapped_event_key' => 'payment_pending', 'buttons' => [['button_type' => 'quick_reply', 'button_text' => 'Need Help']]],
            ['internal_name' => 'Payment Received WhatsApp', 'template_key' => 'payment_received_whatsapp', 'meta_template_name' => 'payment_received_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Thank you {{first_name}}. We have received payment for invoice {{invoice_number}}.', 'footer_text' => 'We will keep you updated.', 'mapped_event_key' => 'payment_received', 'buttons' => []],
            ['internal_name' => 'Order Ready Pickup WhatsApp', 'template_key' => 'order_ready_pickup_whatsapp', 'meta_template_name' => 'order_ready_pickup_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hi {{first_name}}, your order {{order_number}} is ready for pickup at {{pickup_time}}.', 'footer_text' => 'Please carry your order confirmation.', 'mapped_event_key' => 'order_ready_for_pickup', 'buttons' => []],
            ['internal_name' => 'Out for Delivery WhatsApp', 'template_key' => 'order_out_for_delivery_whatsapp', 'meta_template_name' => 'order_out_for_delivery_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hello {{first_name}}, your order {{order_number}} is out for delivery for {{delivery_date}}.', 'footer_text' => 'Thank you for choosing Cakeouflage.', 'mapped_event_key' => 'order_out_for_delivery', 'buttons' => []],
            ['internal_name' => 'Delivered WhatsApp', 'template_key' => 'order_delivered_whatsapp', 'meta_template_name' => 'order_delivered_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Your order {{order_number}} has been delivered. Hope it made your day sweeter, {{first_name}}.', 'footer_text' => 'We would love your feedback.', 'mapped_event_key' => 'order_delivered', 'buttons' => [['button_type' => 'quick_reply', 'button_text' => 'Send Feedback']]],
            ['internal_name' => 'Due Reminder WhatsApp', 'template_key' => 'due_reminder_whatsapp', 'meta_template_name' => 'due_reminder_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Reminder {{first_name}}, invoice {{invoice_number}} for {{invoice_amount}} is due on {{due_date}}.', 'footer_text' => 'Please share payment reference after payment.', 'mapped_event_key' => 'payment_overdue', 'buttons' => []],
            ['internal_name' => 'Birthday Wish WhatsApp', 'template_key' => 'birthday_wish_whatsapp', 'meta_template_name' => 'birthday_wish_whatsapp', 'category' => 'marketing', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Happy Birthday {{first_name}} from Cakeouflage. Wishing you sweet wonderful happy memories.', 'footer_text' => 'Celebrate with a handcrafted cake.', 'mapped_event_key' => 'birthday_today', 'buttons' => [['button_type' => 'quick_reply', 'button_text' => 'Shop Cakes']]],
            ['internal_name' => 'B2B Approval WhatsApp', 'template_key' => 'b2b_approval_whatsapp', 'meta_template_name' => 'b2b_approval_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hello {{company_name}}, your Cakeouflage B2B account has been approved.', 'footer_text' => 'You can now place business orders online.', 'mapped_event_key' => 'b2b_account_approved', 'buttons' => []],
            ['internal_name' => 'Quote Response WhatsApp', 'template_key' => 'quote_response_whatsapp', 'meta_template_name' => 'quote_response_whatsapp', 'category' => 'utility', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hello {{company_name}}, quote {{quote_number}} is ready for your review.', 'footer_text' => 'Reply for revisions or confirmation.', 'mapped_event_key' => 'b2b_quote_sent', 'buttons' => []],
            ['internal_name' => 'Course Follow Up WhatsApp', 'template_key' => 'course_followup_whatsapp', 'meta_template_name' => 'course_followup_whatsapp', 'category' => 'marketing', 'language_code' => 'en_US', 'header_type' => 'none', 'header_text' => null, 'body_text' => 'Hi {{first_name}}, your workshop {{course_name}} is scheduled for {{batch_date}}.', 'footer_text' => 'Reply if you need help before the batch.', 'mapped_event_key' => 'course_follow_up', 'buttons' => []],
        ];
    }

    private function fetchCount(PDO $pdo, string $sql): int
    {
        return (int)($pdo->query($sql)->fetchColumn() ?: 0);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDatePresetRange(string $preset, string $fromDate, string $toDate): array
    {
        $today = new \DateTimeImmutable('today');

        if ($preset === 'custom') {
            $from = $this->isYmdDate($fromDate) ? $fromDate : '';
            $to = $this->isYmdDate($toDate) ? $toDate : '';
            if ($from !== '' && $to !== '' && $from > $to) {
                return [$to, $from];
            }
            return [$from, $to];
        }

        if ($preset === 'today') {
            $value = $today->format('Y-m-d');
            return [$value, $value];
        }

        if ($preset === 'weekly') {
            $start = $today->modify('monday this week')->format('Y-m-d');
            return [$start, $today->format('Y-m-d')];
        }

        if ($preset === 'yearly') {
            $start = $today->format('Y-01-01');
            return [$start, $today->format('Y-m-d')];
        }

        $start = $today->modify('first day of this month')->format('Y-m-d');
        return [$start, $today->format('Y-m-d')];
    }

    private function isYmdDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function buildRateLimitBucket(string $scope, string $identifier): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return strtolower($scope . '|' . trim($identifier) . '|' . $ip);
    }

    private function mediaBasePath(): string
    {
        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
    }

    private function ensureDirectory(string $path): string
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        @chmod($path, 0777);

        return $path;
    }

    private function effectiveUploadLimitBytes(): int
    {
        $uploadMax = $this->iniSizeToBytes((string)ini_get('upload_max_filesize'));
        $postMax = $this->iniSizeToBytes((string)ini_get('post_max_size'));
        $limits = array_values(array_filter([$uploadMax, $postMax], static function (int $value): bool {
            return $value > 0;
        }));

        $limits[] = self::MAX_MEDIA_UPLOAD_BYTES;

        return count($limits) > 0 ? min($limits) : 0;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
            $limit = $this->effectiveUploadLimitBytes();
            return 'File is larger than current server upload limit' . ($limit > 0 ? ' (' . $this->formatBytes($limit) . ')' : '') . '.';
        }
        if ($errorCode === UPLOAD_ERR_PARTIAL) {
            return 'Upload was interrupted. Please retry on a stable connection.';
        }
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return 'No media file uploaded.';
        }
        if ($errorCode === UPLOAD_ERR_NO_TMP_DIR || $errorCode === UPLOAD_ERR_CANT_WRITE) {
            return 'Server storage is not writable for uploads.';
        }
        if ($errorCode === UPLOAD_ERR_EXTENSION) {
            return 'Upload blocked by server extension policy.';
        }

        return 'Upload failed.';
    }

    private function iniSizeToBytes(string $size): int
    {
        $value = trim($size);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (float)$value;

        if ($unit === 'g') {
            $bytes *= 1024 * 1024 * 1024;
        } elseif ($unit === 'm') {
            $bytes *= 1024 * 1024;
        } elseif ($unit === 'k') {
            $bytes *= 1024;
        }

        return (int)round($bytes);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float)$bytes;
        $index = 0;
        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }

    private function deleteMediaFileIfUnreferenced(PDO $pdo, string $oldPath, ?string $newPath = null): void
    {
        $oldPath = trim($oldPath);
        $newPath = trim((string)$newPath);
        if ($oldPath === '' || $oldPath === $newPath || !$this->isValidMediaRelativePath($oldPath)) {
            return;
        }

        $absolutePath = $this->resolveMediaAbsolutePath($oldPath);
        if ($absolutePath === null) {
            return;
        }
        if (!is_file($absolutePath)) {
            return;
        }

        $referenceCount = 0;

        $productStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE featured_image = :path AND deleted_at IS NULL');
        $productStmt->execute(['path' => $oldPath]);
        $referenceCount += (int)($productStmt->fetchColumn() ?: 0);

        $imageStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE image_url = :path');
        $imageStmt->execute(['path' => $oldPath]);
        $referenceCount += (int)($imageStmt->fetchColumn() ?: 0);

        $bannerStmt = $pdo->prepare('SELECT COUNT(*) FROM banners WHERE image_url = :path');
        $bannerStmt->execute(['path' => $oldPath]);
        $referenceCount += (int)($bannerStmt->fetchColumn() ?: 0);

        $settingStmt = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_value = :path');
        $settingStmt->execute(['path' => $oldPath]);
        $referenceCount += (int)($settingStmt->fetchColumn() ?: 0);

        if ($referenceCount === 0) {
            @unlink($absolutePath);
            if ($this->mediaAssetsTableExists($pdo)) {
                $deleteAssetStmt = $pdo->prepare('DELETE FROM media_assets WHERE canonical_path = :path OR original_path = :path');
                $deleteAssetStmt->execute(['path' => $oldPath]);
            }
        }
    }

    /** @return array<string,string> */
    private function allowedMediaMimeMap(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/x-m4v' => 'm4v',
            'video/mpeg' => 'mpeg',
            'video/mp2t' => 'mpg',
        ];
    }

    /** @return array<int,string> */
    private function allowedMediaExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v', 'mpg', 'mpeg'];
    }

    private function detectMediaMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string)finfo_file($finfo, $path);
                finfo_close($finfo);
                return $mime;
            }
        }

        return (string)(mime_content_type($path) ?: '');
    }

    private function isValidMediaRelativePath(string $path): bool
    {
        return (str_starts_with($path, '/uploads/media/') || str_starts_with($path, '/public/uploads/'))
            && strpos($path, '..') === false
            && strpos($path, "\0") === false;
    }

    private function resolveMediaAbsolutePath(string $relativePath): ?string
    {
        if (!$this->isValidMediaRelativePath($relativePath)) {
            return null;
        }

        if (str_starts_with($relativePath, '/public/uploads/')) {
            $root = $this->projectRoot() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
            $baseReal = realpath($root);
            if ($baseReal === false) {
                return null;
            }

            $trimmed = ltrim(substr($relativePath, strlen('/public/uploads/')), '/');
            if ($trimmed === '') {
                return null;
            }

            $absolutePath = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);
            $parent = dirname($absolutePath);
            if (!is_dir($parent)) {
                return null;
            }
            $parentReal = realpath($parent);
            if ($parentReal === false || !str_starts_with($parentReal, $baseReal)) {
                return null;
            }

            return $absolutePath;
        }

        $base = $this->mediaBasePath();
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        $trimmed = ltrim(substr($relativePath, strlen('/uploads/media/')), '/');
        if ($trimmed === '') {
            return null;
        }

        $absolutePath = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);
        $parentReal = realpath(dirname($absolutePath));
        if ($parentReal === false || !str_starts_with($parentReal, $baseReal)) {
            return null;
        }

        return $absolutePath;
    }

    private function queueMediaTranscodeJob(PDO $pdo, string $sourcePath, string $canonicalPath, int $adminId): void
    {
        $payload = [
            'source_path' => $sourcePath,
            'canonical_path' => $canonicalPath,
            'admin_id' => $adminId,
        ];

        $stmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("media_transcode", :payload_json, "queued", NOW(), 0)');
        $stmt->execute(['payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string,mixed> $asset */
    private function upsertMediaAssetRecord(PDO $pdo, array $asset): void
    {
        if (!$this->mediaAssetsTableExists($pdo)) {
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO media_assets (original_path, canonical_path, original_filename, mime_type, media_type, file_size, conversion_status, conversion_error, version_token, uploaded_by_admin_id, uploaded_at) VALUES (:original_path, :canonical_path, :original_filename, :mime_type, :media_type, :file_size, :conversion_status, :conversion_error, :version_token, :uploaded_by_admin_id, NOW()) ON DUPLICATE KEY UPDATE original_path = VALUES(original_path), original_filename = VALUES(original_filename), mime_type = VALUES(mime_type), media_type = VALUES(media_type), file_size = VALUES(file_size), conversion_status = VALUES(conversion_status), conversion_error = VALUES(conversion_error), version_token = VALUES(version_token), uploaded_by_admin_id = VALUES(uploaded_by_admin_id), updated_at = NOW()');
        $stmt->execute([
            'original_path' => (string)($asset['original_path'] ?? ''),
            'canonical_path' => (string)($asset['canonical_path'] ?? ''),
            'original_filename' => (string)($asset['original_filename'] ?? ''),
            'mime_type' => (string)($asset['mime_type'] ?? ''),
            'media_type' => (string)($asset['media_type'] ?? 'image'),
            'file_size' => (int)($asset['file_size'] ?? 0),
            'conversion_status' => (string)($asset['conversion_status'] ?? 'ready'),
            'conversion_error' => $this->nullableString($asset['conversion_error'] ?? null),
            'version_token' => (string)time(),
            'uploaded_by_admin_id' => isset($asset['uploaded_by_admin_id']) ? (int)$asset['uploaded_by_admin_id'] : null,
        ]);

        $extraSet = [];
        $extraParams = ['canonical_path' => (string)($asset['canonical_path'] ?? '')];
        if ($this->tableHasColumn($pdo, 'media_assets', 'optimized_path')) {
            $extraSet[] = 'optimized_path = :optimized_path';
            $extraParams['optimized_path'] = (string)($asset['optimized_path'] ?? ($asset['canonical_path'] ?? ''));
        }
        if ($this->tableHasColumn($pdo, 'media_assets', 'thumbnail_path')) {
            $extraSet[] = 'thumbnail_path = :thumbnail_path';
            $extraParams['thumbnail_path'] = $this->nullableString($asset['thumbnail_path'] ?? null);
        }
        if ($this->tableHasColumn($pdo, 'media_assets', 'transcoding_status')) {
            $extraSet[] = 'transcoding_status = :transcoding_status';
            $extraParams['transcoding_status'] = (string)($asset['transcoding_status'] ?? (($asset['conversion_status'] ?? 'ready') === 'ready' ? 'optimized' : ($asset['conversion_status'] ?? 'queued')));
        }
        if ($this->tableHasColumn($pdo, 'media_assets', 'duration_seconds')) {
            $extraSet[] = 'duration_seconds = :duration_seconds';
            $extraParams['duration_seconds'] = isset($asset['duration_seconds']) ? (float)$asset['duration_seconds'] : null;
        }
        if ($this->tableHasColumn($pdo, 'media_assets', 'resolution')) {
            $extraSet[] = 'resolution = :resolution';
            $extraParams['resolution'] = $this->nullableString($asset['resolution'] ?? null);
        }

        if ($extraSet !== []) {
            $extraStmt = $pdo->prepare('UPDATE media_assets SET ' . implode(', ', $extraSet) . ', updated_at = NOW() WHERE canonical_path = :canonical_path');
            $extraStmt->execute($extraParams);
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function fetchMediaAssetMetadataMap(PDO $pdo): array
    {
        if (!$this->mediaAssetsTableExists($pdo)) {
            return [];
        }

        $stmt = $pdo->query('SELECT original_path, canonical_path, original_filename, mime_type, media_type, file_size, conversion_status, conversion_error, uploaded_at, updated_at, version_token FROM media_assets ORDER BY updated_at DESC LIMIT 500');
        if (!($stmt instanceof \PDOStatement)) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $originalPath = (string)($row['original_path'] ?? '');
            $canonicalPath = (string)($row['canonical_path'] ?? '');
            $status = (string)($row['conversion_status'] ?? 'ready');
            $preferredPath = ($status === 'ready' && $canonicalPath !== '') ? $canonicalPath : $originalPath;
            if ($preferredPath === '') {
                continue;
            }
            $map[$originalPath] = [
                'canonical_path' => $canonicalPath,
                'original_filename' => (string)($row['original_filename'] ?? ''),
                'mime' => (string)($row['mime_type'] ?? ''),
                'media_type' => (string)($row['media_type'] ?? ''),
                'size' => (int)($row['file_size'] ?? 0),
                'conversion_status' => $status,
                'conversion_error' => (string)($row['conversion_error'] ?? ''),
                'uploaded_at' => (string)($row['uploaded_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'url' => $preferredPath . '?v=' . rawurlencode((string)($row['version_token'] ?? '0')),
            ];
            if ($canonicalPath !== '') {
                $map[$canonicalPath] = $map[$originalPath];
            }
        }

        return $map;
    }

    private function countMediaPathReferences(PDO $pdo, string $path): int
    {
        $total = 0;
        $queries = [
            'SELECT COUNT(*) FROM products WHERE featured_image = :path AND deleted_at IS NULL',
            'SELECT COUNT(*) FROM product_images WHERE image_url = :path',
            'SELECT COUNT(*) FROM banners WHERE image_url = :path',
            'SELECT COUNT(*) FROM settings WHERE setting_value = :path',
        ];
        foreach ($queries as $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute(['path' => $path]);
            $total += (int)($stmt->fetchColumn() ?: 0);
        }
        return $total;
    }

    private function mediaAssetsTableExists(PDO $pdo): bool
    {
        if ($this->mediaAssetsTableExists !== null) {
            return $this->mediaAssetsTableExists;
        }

        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'media_assets'");
            $this->mediaAssetsTableExists = $stmt instanceof \PDOStatement && (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            $this->mediaAssetsTableExists = false;
        }

        return $this->mediaAssetsTableExists;
    }

    private function countOrphanOptimizedFiles(PDO $pdo): int
    {
        $baseDir = $this->projectRoot() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'optimized';
        if (!is_dir($baseDir)) {
            return 0;
        }

        $known = [];
        $stmt = $pdo->query('SELECT optimized_path FROM media_processing_queue');
        if ($stmt instanceof \PDOStatement) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $path = trim((string)($row['optimized_path'] ?? ''));
                if ($path !== '') {
                    $known[$path] = true;
                }
            }
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $path = str_replace($this->projectRoot(), '', $fileInfo->getPathname());
            $path = str_replace('\\', '/', $path);
            if (!isset($known[$path])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<int, array<string, mixed>> */
    private function scanMediaFiles(string $baseDirectory): array
    {
        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relative = str_replace($this->projectRoot(), '', $absolutePath);
            $relative = str_replace('\\', '/', $relative);
            $items[] = [
                'path' => $relative,
                'url' => $relative,
                'name' => $fileInfo->getFilename(),
                'size' => $fileInfo->getSize(),
                'updated_at' => date(DATE_ATOM, (int)$fileInfo->getMTime()),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string)$b['updated_at'], (string)$a['updated_at']);
        });
        return array_slice($items, 0, 200);
    }

    private function optimizeImageToWebp(string $sourcePath, string $targetPath, int $maxWidth, int $quality): bool
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return false;
        }
        $raw = @file_get_contents($sourcePath);
        if ($raw === false || $raw === '') {
            return false;
        }
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return false;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($src);
            return false;
        }
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int)round(($targetWidth / $width) * $height);
        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($dst === false) {
            imagedestroy($src);
            return false;
        }
        // Preserve transparency
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($dst, 0, 0, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $saved = imagewebp($dst, $targetPath, $quality);
        imagedestroy($src);
        imagedestroy($dst);
        return $saved;
    }

    private function optimizeImageToJpeg(string $sourcePath, string $targetPath, int $maxWidth, int $quality): bool
    {
        if (!function_exists('getimagesize')) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!is_array($imageInfo) || !isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])) {
            return false;
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        $mime = (string)$imageInfo['mime'];

        if ($width <= 0 || $height <= 0) {
            return false;
        }

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            return false;
        }

        $src = null;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($sourcePath);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($sourcePath);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($sourcePath);
        }

        if ($src === false || $src === null) {
            return false;
        }

        $targetWidth = $width > $maxWidth ? $maxWidth : $width;
        $targetHeight = (int)round(($targetWidth / $width) * $height);

        $dst = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($dst === false) {
            imagedestroy($src);
            return false;
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $saved = imagejpeg($dst, $targetPath, $quality);

        imagedestroy($src);
        imagedestroy($dst);

        return $saved;
    }

    private function normalizeProductImageOrder(PDO $pdo, int $productId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['product_id' => $productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare('UPDATE product_images SET sort_order = :sort_order WHERE id = :id');
        foreach ($rows as $index => $row) {
            $updateStmt->execute([
                'sort_order' => $index,
                'id' => (int)($row['id'] ?? 0),
            ]);
        }
    }

    private function enforceProductImageSlotLimit(PDO $pdo, int $productId): void
    {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM product_images
             WHERE product_id = :product_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['product_id' => $productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $keepIds = [];
        foreach ($rows as $index => $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0 && $index < 2) {
                $keepIds[] = $id;
            }
        }

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || in_array($id, $keepIds, true)) {
                continue;
            }
            $deleteStmt = $pdo->prepare('DELETE FROM product_images WHERE id = :id');
            $deleteStmt->execute(['id' => $id]);
        }

        $this->normalizeProductImageOrder($pdo, $productId);
    }

    private function toBinaryFlag(mixed $value): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === true) {
            return 1;
        }

        return ((string)$value === '1' || (int)$value === 1) ? 1 : 0;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }

    private function safeDietary(string $dietary): string
    {
        return in_array($dietary, ['regular', 'eggless', 'vegan', 'sugar_free', 'healthy'], true) ? $dietary : 'regular';
    }

    private function resolveDietaryType(array $input, ?PDO $pdo = null): string
    {
        $fallback = $this->toBinaryFlag($input['is_veg'] ?? 1) === 1 ? 'veg' : 'nonveg';
        return normalizeDietaryType((string)($input['dietary_type'] ?? $fallback), $pdo ?? 'veg_only');
    }

    private function resolveDietaryTag(string $dietaryTag, string $dietaryType): string
    {
        $resolved = $this->safeDietary($dietaryTag);
        if ($dietaryType === 'nonveg' && $resolved !== 'regular') {
            return 'regular';
        }
        return $resolved;
    }

    private function safeAvailability(string $availability): string
    {
        return in_array($availability, ['in_stock', 'out_of_stock', 'preorder', 'draft'], true) ? $availability : 'in_stock';
    }

    private function isValidPaymentStatusTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $map = [
            'pending'            => ['paid', 'failed', 'under_review'],
            'under_review'       => ['paid', 'rejected', 'pending'],
            'paid'               => ['refunded', 'partially_refunded', 'refund_pending'],
            'credit'             => ['refunded'],
            'refund_pending'     => ['partially_refunded', 'refunded', 'paid'],
            'partially_refunded' => ['refunded', 'refund_pending'],
            'refunded'           => [],
            'failed'             => ['pending', 'paid'],
            'rejected'           => ['pending'],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function timelineBadgeForAction(string $actionType): string
    {
        $map = [
            'update_order_status' => 'info',
            'bulk_import_products' => 'success',
            'bulk_import_products_dry_run' => 'neutral',
            'create_product' => 'success',
            'update_product' => 'info',
            'delete_product' => 'danger',
            'create_category' => 'success',
            'update_category' => 'info',
            'delete_category' => 'danger',
        ];

        return $map[$actionType] ?? 'neutral';
    }

    private function timelineLabelForAction(string $actionType): string
    {
        $map = [
            'update_order_status' => 'Order Updated',
            'bulk_import_products' => 'Bulk Import Commit',
            'bulk_import_products_dry_run' => 'Bulk Import Dry Run',
            'create_product' => 'Product Created',
            'update_product' => 'Product Updated',
            'delete_product' => 'Product Archived',
            'create_category' => 'Category Created',
            'update_category' => 'Category Updated',
            'delete_category' => 'Category Archived',
            'reorder_product_media_drag' => 'Gallery Reordered',
        ];

        return $map[$actionType] ?? ucwords(str_replace('_', ' ', $actionType));
    }

    /** @param array<string,mixed> $metadata */
    private function timelineMessageForAction(string $actionType, array $metadata): string
    {
        if ($actionType === 'update_order_status') {
            $parts = [];
            $orderStatus = trim((string)($metadata['order_status'] ?? ''));
            $paymentStatus = trim((string)($metadata['payment_status'] ?? ''));
            $slotLabel = trim((string)($metadata['scheduled_slot_label'] ?? ''));

            if ($orderStatus !== '') {
                $parts[] = 'Order status: ' . str_replace('_', ' ', $orderStatus);
            }
            if ($paymentStatus !== '') {
                $parts[] = 'Payment status: ' . $paymentStatus;
            }
            if ($slotLabel !== '') {
                $parts[] = 'Slot: ' . $slotLabel;
            }

            if (count($parts) > 0) {
                return implode(' | ', $parts);
            }
        }

        if ($actionType === 'reorder_product_media_drag') {
            return 'Gallery order changed using drag and drop.';
        }

        return 'Action recorded by admin.';
    }

    private function isAllowedInvoiceStatus(string $status): bool
    {
        return in_array($status, [
            'draft',
            'pending_payment',
            'part_paid',
            'paid',
            'overdue',
            'payment_under_verification',
            'unpaid_rejected',
            'cancelled',
            'refunded',
        ], true);
    }

    private function isValidInvoiceStatusTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $map = [
            'draft' => ['pending_payment', 'cancelled'],
            'pending_payment' => ['payment_under_verification', 'part_paid', 'paid', 'overdue', 'unpaid_rejected', 'cancelled'],
            'payment_under_verification' => ['part_paid', 'paid', 'unpaid_rejected', 'pending_payment', 'cancelled'],
            'part_paid' => ['paid', 'overdue', 'payment_under_verification', 'cancelled', 'refunded'],
            'overdue' => ['payment_under_verification', 'part_paid', 'paid', 'unpaid_rejected', 'cancelled'],
            'unpaid_rejected' => ['pending_payment', 'payment_under_verification', 'cancelled'],
            'paid' => ['refunded'],
            'cancelled' => [],
            'refunded' => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }

    // =========================================================================
    // SLOT MANAGEMENT — ADMIN API ENDPOINTS
    // =========================================================================

    /** GET /admin/api/slots — list all slots with today's usage */
    public function slotsList(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $stmt = $pdo->prepare(
            'SELECT s.*,
                    COALESCE(sc_today.booked_count, 0) AS booked_today
             FROM order_slots s
             LEFT JOIN slot_capacities sc_today
               ON sc_today.slot_id = s.id AND sc_today.booking_date = CURDATE()
             ORDER BY s.slot_type, s.display_order, s.start_time'
        );
        $stmt->execute();
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json(['success' => true, 'data' => ['slots' => $slots]]);
    }

    /** POST /admin/api/slots — create a new slot */
    public function slotCreate(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $input = $this->readJsonInput();
        $required = ['slot_type', 'slot_name', 'slot_label', 'start_time', 'end_time'];
        foreach ($required as $f) {
            if (empty($input[$f])) {
                Response::json(['success' => false, 'message' => "Field '{$f}' is required"], 422);
                return;
            }
        }

        if (!in_array($input['slot_type'], ['delivery', 'pickup'], true)) {
            Response::json(['success' => false, 'message' => 'slot_type must be delivery or pickup'], 422);
            return;
        }

        $svc = new \App\Services\SlotService($pdo);
        $id  = $svc->createSlot($input);

        Response::json([
            'success' => true,
            'message' => 'Slot created.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** PATCH /admin/api/slots/:id — update slot */
    public function slotUpdate(string $id): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)$id;
        $input  = $this->readJsonInput();

        if ($slotId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid slot id'], 422);
            return;
        }

        $svc     = new \App\Services\SlotService($pdo);
        $updated = $svc->updateSlot($slotId, $input);

        Response::json([
            'success' => $updated,
            'message' => $updated ? 'Slot updated.' : 'Slot not found.',
        ]);
    }

    /** DELETE /admin/api/slots/:id — deactivate slot */
    public function slotDelete(string $id): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)$id;
        if ($slotId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid slot id'], 422);
            return;
        }

        $svc = new \App\Services\SlotService($pdo);
        $svc->toggleSlotActive($slotId, false);

        Response::json(['success' => true, 'message' => 'Slot deactivated.']);
    }

    /** POST /admin/api/slots/:id/toggle — toggle active/inactive */
    public function slotToggle(string $id): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)$id;
        $input  = $this->readJsonInput();

        if ($slotId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid slot id'], 422);
            return;
        }

        $active = (bool)($input['is_active'] ?? true);
        $svc    = new \App\Services\SlotService($pdo);
        $svc->toggleSlotActive($slotId, $active);

        Response::json([
            'success' => true,
            'message' => $active ? 'Slot activated.' : 'Slot paused.',
        ]);
    }

    /** GET /admin/api/slots/:id/exceptions — list exceptions for a slot */
    public function slotExceptionsList(string $id): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)$id;
        if ($slotId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid slot id'], 422);
            return;
        }

        $svc  = new \App\Services\SlotService($pdo);
        $list = $svc->listExceptions($slotId);

        Response::json(['success' => true, 'data' => ['exceptions' => $list]]);
    }

    /** POST /admin/api/slots/:id/exceptions — upsert exception/holiday */
    public function slotExceptionCreate(string $id): void    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)$id;
        $input  = $this->readJsonInput();

        if ($slotId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid slot id'], 422);
            return;
        }

        $exDate = trim((string)($input['exception_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $exDate)) {
            Response::json(['success' => false, 'message' => 'exception_date must be YYYY-MM-DD'], 422);
            return;
        }

        $input['slot_id']        = $slotId;
        $input['exception_date'] = $exDate;

        $svc = new \App\Services\SlotService($pdo);
        $svc->upsertException($input);

        Response::json(['success' => true, 'message' => 'Exception saved.']);
    }

    /** DELETE /admin/api/slot-exceptions — delete exception by slot+date */
    public function slotExceptionDelete(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $slotId = (int)($_GET['slot_id'] ?? 0);
        $date   = trim((string)($_GET['date'] ?? ''));

        if ($slotId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['success' => false, 'message' => 'slot_id and date (YYYY-MM-DD) are required'], 422);
            return;
        }

        $svc = new \App\Services\SlotService($pdo);
        $svc->deleteException($slotId, $date);

        Response::json(['success' => true, 'message' => 'Exception removed.']);
    }

    /** GET /admin/api/holidays?from=YYYY-MM-DD&to=YYYY-MM-DD&slot_type=all|delivery|pickup */
    public function holidaysList(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $from = trim((string)($_GET['from'] ?? (new \DateTimeImmutable('today'))->format('Y-m-d')));
        $to = trim((string)($_GET['to'] ?? (new \DateTimeImmutable('today'))->modify('+45 days')->format('Y-m-d')));
        $slotType = trim((string)($_GET['slot_type'] ?? 'all'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            Response::json(['success' => false, 'message' => 'from/to must be YYYY-MM-DD'], 422);
            return;
        }
        if (!in_array($slotType, ['all', 'delivery', 'pickup'], true)) {
            Response::json(['success' => false, 'message' => 'slot_type must be all, delivery or pickup'], 422);
            return;
        }

        $sql =
            'SELECT
                ex.exception_date,
                s.slot_type,
                MAX(COALESCE(ex.note, "")) AS note,
                COUNT(*) AS affected_slots
            FROM order_slot_exceptions ex
            INNER JOIN order_slots s ON s.id = ex.slot_id
            WHERE ex.is_closed = 1
              AND ex.exception_date BETWEEN :from_date AND :to_date';
        if ($slotType !== 'all') {
            $sql .= ' AND s.slot_type = :slot_type';
        }
        $sql .= ' GROUP BY ex.exception_date, s.slot_type ORDER BY ex.exception_date ASC, s.slot_type ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':from_date', $from);
        $stmt->bindValue(':to_date', $to);
        if ($slotType !== 'all') {
            $stmt->bindValue(':slot_type', $slotType);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        Response::json([
            'success' => true,
            'data' => [
                'from' => $from,
                'to' => $to,
                'entries' => $rows,
            ],
        ]);
    }

    /** POST /admin/api/holidays — create a date closure for delivery/pickup/all */
    public function holidayCreate(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $input = $this->readJsonInput();
        $holidayDate = trim((string)($input['holiday_date'] ?? ''));
        $slotType = trim((string)($input['slot_type'] ?? 'all'));
        $note = trim((string)($input['note'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
            Response::json(['success' => false, 'message' => 'holiday_date must be YYYY-MM-DD'], 422);
            return;
        }
        if (!in_array($slotType, ['all', 'delivery', 'pickup'], true)) {
            Response::json(['success' => false, 'message' => 'slot_type must be all, delivery or pickup'], 422);
            return;
        }

        $slotSql = 'SELECT id FROM order_slots WHERE is_active = 1';
        if ($slotType !== 'all') {
            $slotSql .= ' AND slot_type = :slot_type';
        }
        $slotSql .= ' ORDER BY id ASC';
        $slotStmt = $pdo->prepare($slotSql);
        if ($slotType !== 'all') {
            $slotStmt->bindValue(':slot_type', $slotType);
        }
        $slotStmt->execute();
        $slotIds = array_map(static fn($v): int => (int)$v, $slotStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        if (empty($slotIds)) {
            Response::json(['success' => false, 'message' => 'No active slots found for selected slot_type'], 404);
            return;
        }

        $upsert = $pdo->prepare(
            'INSERT INTO order_slot_exceptions (slot_id, exception_date, override_capacity, is_closed, note)
             VALUES (:slot_id, :exception_date, NULL, 1, :note)
             ON DUPLICATE KEY UPDATE override_capacity = VALUES(override_capacity), is_closed = VALUES(is_closed), note = VALUES(note)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($slotIds as $sid) {
                $upsert->execute([
                    'slot_id' => $sid,
                    'exception_date' => $holidayDate,
                    'note' => $note,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => 'Unable to save holiday closure'], 500);
            return;
        }

        Response::json([
            'success' => true,
            'message' => 'Holiday closure saved.',
            'data' => [
                'holiday_date' => $holidayDate,
                'slot_type' => $slotType,
                'affected_slots' => count($slotIds),
            ],
        ]);
    }

    /** DELETE /admin/api/holidays?holiday_date=YYYY-MM-DD&slot_type=all|delivery|pickup */
    public function holidayDelete(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $holidayDate = trim((string)($_GET['holiday_date'] ?? ''));
        $slotType = trim((string)($_GET['slot_type'] ?? 'all'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
            Response::json(['success' => false, 'message' => 'holiday_date must be YYYY-MM-DD'], 422);
            return;
        }
        if (!in_array($slotType, ['all', 'delivery', 'pickup'], true)) {
            Response::json(['success' => false, 'message' => 'slot_type must be all, delivery or pickup'], 422);
            return;
        }

        $deleteSql =
            'DELETE ex
             FROM order_slot_exceptions ex
             INNER JOIN order_slots s ON s.id = ex.slot_id
             WHERE ex.exception_date = :holiday_date
               AND ex.is_closed = 1';
        if ($slotType !== 'all') {
            $deleteSql .= ' AND s.slot_type = :slot_type';
        }

        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->bindValue(':holiday_date', $holidayDate);
        if ($slotType !== 'all') {
            $deleteStmt->bindValue(':slot_type', $slotType);
        }
        $deleteStmt->execute();

        Response::json([
            'success' => true,
            'message' => 'Holiday closure removed.',
            'data' => [
                'holiday_date' => $holidayDate,
                'slot_type' => $slotType,
                'deleted_rows' => (int)$deleteStmt->rowCount(),
            ],
        ]);
    }

    /** GET /admin/api/slots/usage?date=YYYY-MM-DD — live usage for a date */
    public function slotUsage(): void
    {
        $adminId = $this->requireAdminId(); if ($adminId === null) return;
        $pdo = self::db(); if (!$pdo) return;

        $date = trim((string)($_GET['date'] ?? (new \DateTimeImmutable('now'))->format('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['success' => false, 'message' => 'date must be YYYY-MM-DD'], 422);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT
               s.id, s.slot_type, s.slot_name, s.slot_label,
               s.start_time, s.end_time,
               s.max_orders,
               s.is_active,
               COALESCE(sc.booked_count, 0)                 AS booked_count,
               COALESCE(ex.override_capacity, s.max_orders) AS effective_capacity,
               COALESCE(ex.is_closed, 0)                    AS is_exception_closed,
               ex.note                                       AS exception_note
             FROM order_slots s
             LEFT JOIN slot_capacities sc
               ON sc.slot_id = s.id AND sc.booking_date = :date1
             LEFT JOIN order_slot_exceptions ex
               ON ex.slot_id = s.id AND ex.exception_date = :date2
             ORDER BY s.slot_type, s.display_order, s.start_time'
        );
        $stmt->execute(['date1' => $date, 'date2' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrich with remaining/fast-selling flags
        $data = array_map(static function (array $row): array {
            $booked    = (int)$row['booked_count'];
            $cap       = (int)$row['effective_capacity'];
            $remaining = max(0, $cap - $booked);
            $row['remaining']      = $remaining;
            $row['is_full']        = $booked >= $cap;
            $row['is_fast_selling'] = !$row['is_full'] && $remaining < ceil($cap * 0.30);
            $row['pct_booked']     = $cap > 0 ? round($booked / $cap * 100, 1) : 0;
            return $row;
        }, $rows);

        Response::json([
            'success' => true,
            'data'    => [
                'date'  => $date,
                'slots' => $data,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REFUND ENDPOINTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/admin/orders/:id/refund/process
     * Strict policy mode: submit refund request only.
     * Processing is intentionally deferred to a separate approver via /api/admin/refunds/:id/approve.
     * Required permissions: order_refund
     */
    public function refundProcess(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $adminRole = (string)($_SESSION['admin_role'] ?? '');
        $pdo = \App\Core\Database::getConnection();
        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $canRequestRefund = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);
        if (!$canRequestRefund) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions to submit refund requests'], 403);
            return;
        }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order ID'], 400);
            return;
        }

        $body = $this->readJsonInput();

        $refundAmount       = isset($body['refund_amount'])        ? (float)$body['refund_amount']        : 0.0;
        $reasonCode         = isset($body['reason_code'])          ? trim((string)$body['reason_code'])         : '';
        $reasonNotes        = isset($body['reason_notes'])         ? trim((string)$body['reason_notes'])        : '';
        $settlementRef      = isset($body['settlement_reference']) ? trim((string)$body['settlement_reference']) : '';
        $settlementProofUrl = isset($body['settlement_proof_url']) ? trim((string)$body['settlement_proof_url']) : '';

        if ($refundAmount <= 0) {
            Response::json(['success' => false, 'message' => 'refund_amount must be greater than zero'], 400);
            return;
        }

        $legacyReasonMap = [
            'QUALITY_COMPLAINT' => 'QUALITY_ISSUE',
            'WRONG_CAKE_DELIVERED' => 'WRONG_ORDER',
            'DELAYED_DELIVERY' => 'ITEM_NOT_DELIVERED',
            'DAMAGED_CAKE' => 'DAMAGED_ITEM',
            'CUSTOMER_COMPLAINT' => 'QUALITY_ISSUE',
            'DUPLICATE_ORDER' => 'DUPLICATE_CHARGE',
            'KITCHEN_ISSUE' => 'QUALITY_ISSUE',
            'STAFF_ISSUE' => 'QUALITY_ISSUE',
            'FRAUD_PREVENTION' => 'DUPLICATE_CHARGE',
            'ADMIN_ADJUSTMENT' => 'OTHER',
        ];
        if ($reasonCode === '') {
            $reasonCode = 'OTHER';
        } elseif (isset($legacyReasonMap[$reasonCode])) {
            $reasonCode = $legacyReasonMap[$reasonCode];
        }

        $adminPerms = $adminPermissions;

        $reasonNotesForService = $reasonNotes;
        if ($settlementRef !== '') {
            $reasonNotesForService .= ($reasonNotesForService !== '' ? "\n" : '') . 'Settlement reference: ' . $settlementRef;
        }
        if ($settlementProofUrl !== '') {
            $reasonNotesForService .= ($reasonNotesForService !== '' ? "\n" : '') . 'Settlement proof: ' . $settlementProofUrl;
        }

        $service = new \App\Services\RefundService();
        $result  = $service->submitRequest($pdo, $orderId, [
            'requested_amount' => $refundAmount,
            'reason_code'      => $reasonCode,
            'reason_notes'     => $reasonNotesForService,
        ], $adminId, [
            'admin_role'        => $adminRole,
            'admin_permissions' => $adminPerms,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'admin_name'        => (string)($_SESSION['admin_name'] ?? 'Admin'),
        ]);

        if (!$result['success']) {
            Response::json(['success' => false, 'message' => (string)$result['message']], 422);
            return;
        }

        Response::json([
            'success'       => true,
            'message'       => 'Refund request submitted. A separate approver must process it.',
            'refund_amount' => $refundAmount,
            'refund_id'     => $result['refund_id'] ?? null,
            'refund_number' => $result['refund_number'] ?? null,
        ], 200);
    }

    /**
     * POST /api/admin/refunds/upload-proof
     * Upload a settlement proof file (image or PDF, max 5 MB).
     * Returns: { url: string }
     */
    public function refundUploadProof(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $adminRole = (string)($_SESSION['admin_role'] ?? '');
        $pdo = self::db();
        if (!$pdo) {
            return;
        }
        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $canManageRefund = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);
        if (!$canManageRefund) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions'], 403);
            return;
        }

        if (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
            return;
        }

        $file    = $_FILES['proof'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        if ($file['size'] > $maxSize) {
            Response::json(['success' => false, 'message' => 'File exceeds 5 MB limit'], 400);
            return;
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mimeType, $allowed, true)) {
            Response::json(['success' => false, 'message' => 'Only JPEG, PNG, WebP and PDF files are allowed'], 400);
            return;
        }

        // Route images through unified media service; keep PDF handling local.
        if (str_starts_with((string)$mimeType, 'image/')) {
            $upload = UnifiedMediaService::upload($file, [
                'module' => 'byoc',
                'entity_type' => 'refund_proof',
                'entity_id' => 0,
                'admin_id' => (int)($_SESSION['admin_id'] ?? 0),
                'allow_svg' => false,
                'max_bytes' => $maxSize,
            ]);
            if (!$upload['ok']) {
                Response::json(['success' => false, 'message' => (string)$upload['error']], 500);
                return;
            }

            Response::json(['success' => true, 'url' => (string)$upload['relative_url']], 200);
            return;
        }

        $ext = 'pdf';
        $filename  = 'proof_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)), '/\\') . '/public/uploads/refund-proofs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::json(['success' => false, 'message' => 'Failed to save uploaded file'], 500);
            return;
        }

        Response::json(['success' => true, 'url' => 'uploads/refund-proofs/' . $filename], 200);
    }

    /**
     * GET /api/admin/refunds/report
     * Aggregated refund data for the report page.
     */
    public function refundReport(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo      = \App\Core\Database::getConnection();
        $dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
        $dateTo   = trim((string)($_GET['date_to']   ?? date('Y-m-d')));

        // Clamp dates to sane values
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

                $settlementReferenceSelect = $this->tableHasColumn($pdo, 'refund_transactions', 'settlement_reference')
                        ? 'rt.settlement_reference'
                        : 'NULL AS settlement_reference';

                $stmt = $pdo->prepare(
                        'SELECT rt.id, rt.order_id, rt.refund_number, rt.refund_type,
                                        rt.reason_code, rt.reason_notes, rt.approved_amount,
                                        ' . $settlementReferenceSelect . ', rt.processed_at,
                                        o.order_number, o.customer_name, o.customer_email,
                                        a.full_name AS processed_by_name
                         FROM refund_transactions rt
                         JOIN orders o  ON o.id = rt.order_id
                         LEFT JOIN admins a ON a.id = rt.approved_by_admin_id
                         WHERE rt.status = "processed"
                             AND DATE(rt.processed_at) BETWEEN :date_from AND :date_to
                         ORDER BY rt.processed_at DESC
                         LIMIT 500'
                );
        $stmt->execute(['date_from' => $dateFrom, 'date_to' => $dateTo]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalAmount    = array_sum(array_column($rows, 'approved_amount'));
        $partialCount   = count(array_filter($rows, static fn($r) => $r['refund_type'] === 'partial'));
        $fullCount      = count(array_filter($rows, static fn($r) => $r['refund_type'] === 'full'));

        Response::json([
            'success'       => true,
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'total_amount'  => round($totalAmount, 2),
            'partial_count' => $partialCount,
            'full_count'    => $fullCount,
            'rows'          => $rows,
        ], 200);
    }

    /**
     * POST /api/admin/orders/:id/refund/request
     * Submit a refund request for an order.
     * Required permissions: order_refund
     */
    public function refundRequest(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $adminRole = (string)($_SESSION['admin_role'] ?? '');
        $pdo = self::db();
        if (!$pdo) {
            return;
        }
        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $canRequestRefund = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);
        if (!$canRequestRefund) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions to submit refund requests'], 403);
            return;
        }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $reasonCode   = trim((string)($input['reason_code']   ?? ''));
        $reasonNotes  = trim((string)($input['reason_notes']  ?? ''));
        $requestedAmt = isset($input['requested_amount']) ? (float)$input['requested_amount'] : null;

        if ($reasonCode === '') {
            Response::json(['success' => false, 'message' => 'reason_code is required'], 422);
            return;
        }
        if ($requestedAmt === null || $requestedAmt <= 0) {
            Response::json(['success' => false, 'message' => 'requested_amount must be a positive number'], 422);
            return;
        }

        $service = new \App\Services\RefundService();
        $result  = $service->submitRequest($pdo, $orderId, [
            'reason_code'      => $reasonCode,
            'reason_notes'     => $reasonNotes,
            'requested_amount' => $requestedAmt,
        ], $adminId, [
            'admin_role'        => (string)($_SESSION['admin_role']        ?? ''),
            'admin_permissions' => $adminPermissions,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $statusCode = $result['success'] ? 200 : 422;
        Response::json($result, $statusCode);
    }

    /**
     * POST /api/admin/refunds/:id/approve
     * Approve a pending refund transaction.
     * Required permissions: can_approve_refund OR can_force_refund
     */
    public function refundApprove(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $adminRole        = (string)($_SESSION['admin_role']        ?? '');
        $hasPermission    = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || count($adminPermissions) === 0
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);

        if (!$hasPermission) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions to approve refunds'], 403);
            return;
        }

        if (!in_array('can_approve_refund', $adminPermissions, true) && $adminRole !== 'super_admin') {
            $adminPermissions[] = 'can_approve_refund';
        }

        $refundTxId = (int)$id;
        if ($refundTxId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid refund transaction id'], 422);
            return;
        }

        $input          = $this->readJsonInput();
        $approvedAmount = isset($input['approved_amount']) ? (float)$input['approved_amount'] : null;

        if ($approvedAmount === null || $approvedAmount <= 0) {
            Response::json(['success' => false, 'message' => 'approved_amount must be a positive number'], 422);
            return;
        }

        $service = new \App\Services\RefundService();
        $result  = $service->approve($pdo, $refundTxId, $approvedAmount, $adminId, [
            'admin_role'        => $adminRole,
            'admin_permissions' => $adminPermissions,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
            'admin_name'        => (string)($_SESSION['admin_name'] ?? 'Admin'),
        ]);

        $statusCode = $result['success'] ? 200 : 422;
        Response::json($result, $statusCode);
    }

    /**
     * POST /api/admin/refunds/:id/reject
     * Reject a pending refund transaction.
     * Required permissions: can_approve_refund OR can_force_refund
     */
    public function refundReject(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $adminRole        = (string)($_SESSION['admin_role']        ?? '');
        $hasPermission    = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || count($adminPermissions) === 0
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);

        if (!$hasPermission) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions to reject refunds'], 403);
            return;
        }

        if (!in_array('can_approve_refund', $adminPermissions, true) && $adminRole !== 'super_admin') {
            $adminPermissions[] = 'can_approve_refund';
        }

        $refundTxId = (int)$id;
        if ($refundTxId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid refund transaction id'], 422);
            return;
        }

        $input = $this->readJsonInput();
        $notes = trim((string)($input['notes'] ?? ''));

        $service = new \App\Services\RefundService();
        $result  = $service->reject($pdo, $refundTxId, $notes, $adminId, [
            'admin_role'        => $adminRole,
            'admin_permissions' => $adminPermissions,
            'ip_address'        => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $statusCode = $result['success'] ? 200 : 422;
        Response::json($result, $statusCode);
    }

    /**
     * GET /api/admin/refunds
     * Paginated list of refund transactions.
     * Required permissions: can_approve_refund OR can_view_refund_reports
     */
    public function refundsList(): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $adminRole        = (string)($_SESSION['admin_role']        ?? '');
        $hasPermission    = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || count($adminPermissions) === 0
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true)
            || in_array('can_view_refund_reports', $adminPermissions, true);

        if (!$hasPermission) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions'], 403);
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
        $status = trim((string)($_GET['status'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));
        $datePreset = trim(strtolower((string)($_GET['date_preset'] ?? 'this_month')));
        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $offset = ($page - 1) * $perPage;

        $allowedStatuses = ['pending_approval', 'approved', 'rejected', 'processed'];
        $allowedDatePresets = ['today', 'weekly', 'monthly', 'yearly', 'this_month', 'custom', 'all'];
        $where  = [];
        $params = [];

        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where[]           = 'rt.status = :status';
            $params['status']  = $status;
        }

        if ($q !== '') {
            $where[] = '(rt.refund_number LIKE :q OR o.order_number LIKE :q OR o.customer_name LIKE :q OR o.customer_phone LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        if (!in_array($datePreset, $allowedDatePresets, true)) {
            $datePreset = 'this_month';
        }

        if ($datePreset !== 'all') {
            [$resolvedFromDate, $resolvedToDate] = $this->resolveDatePresetRange($datePreset, $fromDate, $toDate);
            if ($resolvedFromDate !== '') {
                $where[] = 'DATE(COALESCE(rt.processed_at, rt.requested_at, rt.created_at)) >= :from_date';
                $params['from_date'] = $resolvedFromDate;
            }
            if ($resolvedToDate !== '') {
                $where[] = 'DATE(COALESCE(rt.processed_at, rt.requested_at, rt.created_at)) <= :to_date';
                $params['to_date'] = $resolvedToDate;
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM refund_transactions rt $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $listParams        = $params;
        $listParams['lim'] = $perPage;
        $listParams['off'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT rt.id, rt.refund_number, rt.order_id, rt.refund_type, rt.reason_code,
                    rt.reason_notes, rt.requested_amount, rt.approved_amount, rt.status,
                    rt.fraud_flags, rt.requested_at, rt.approved_at, rt.processed_at,
                    o.order_number, o.customer_name, o.customer_phone,
                    req.full_name AS requested_by_name,
                    apv.full_name AS approved_by_name
             FROM   refund_transactions rt
             JOIN   orders  o   ON o.id  = rt.order_id
             LEFT JOIN admins req ON req.id = rt.requested_by_admin_id
             LEFT JOIN admins apv ON apv.id = rt.approved_by_admin_id
             $whereClause
             ORDER BY rt.requested_at DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  \PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::json([
            'success'     => true,
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'filters'     => [
                'status' => $status,
                'q' => $q,
                'date_preset' => $datePreset,
                'from_date' => $params['from_date'] ?? null,
                'to_date' => $params['to_date'] ?? null,
            ],
            'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    /**
     * GET /api/admin/orders/:id/refund-history
     * Returns the order status history + all refund transactions for one order.
     * Required permissions: orders (any admin with order access)
     */
    public function refundHistory(string $id): void
    {
        $adminId = $this->requireAdminId();
        if ($adminId === null) {
            return;
        }

        $pdo = self::db();
        if (!$pdo) {
            return;
        }

        $adminPermissions = $this->resolveAdminPermissions($pdo, $adminId);
        $adminRole = (string)($_SESSION['admin_role'] ?? '');
        $canViewOrderRefundHistory = $adminRole === 'super_admin'
            || $adminRole === 'admin'
            || in_array('orders', $adminPermissions, true)
            || in_array('order_refund', $adminPermissions, true)
            || in_array('can_view_refund_reports', $adminPermissions, true)
            || in_array('can_approve_refund', $adminPermissions, true)
            || in_array('can_force_refund', $adminPermissions, true);
        if (!$canViewOrderRefundHistory) {
            Response::json(['success' => false, 'message' => 'Insufficient permissions'], 403);
            return;
        }

        $orderId = (int)$id;
        if ($orderId <= 0) {
            Response::json(['success' => false, 'message' => 'Invalid order id'], 422);
            return;
        }

        $histStmt = $pdo->prepare(
            "SELECT osh.id, osh.previous_status, osh.new_status, osh.reason,
                    osh.created_at, a.full_name AS changed_by_name
             FROM   order_status_history osh
             LEFT JOIN admins a ON a.id = osh.changed_by_admin_id
             WHERE  osh.order_id = :order_id
             ORDER BY osh.created_at ASC"
        );
        $histStmt->execute(['order_id' => $orderId]);
        $statusHistory = $histStmt->fetchAll(\PDO::FETCH_ASSOC);

        $refStmt = $pdo->prepare(
            "SELECT rt.id, rt.refund_number, rt.refund_type, rt.reason_code,
                    rt.reason_notes, rt.requested_amount, rt.approved_amount,
                    rt.status, rt.fraud_flags, rt.requested_at, rt.approved_at,
                    rt.processed_at,
                    req.full_name AS requested_by_name,
                    apv.full_name AS approved_by_name
             FROM   refund_transactions rt
             LEFT JOIN admins req ON req.id = rt.requested_by_admin_id
             LEFT JOIN admins apv ON apv.id = rt.approved_by_admin_id
             WHERE  rt.order_id = :order_id
             ORDER BY rt.requested_at ASC"
        );
        $refStmt->execute(['order_id' => $orderId]);
        $refundTxns = $refStmt->fetchAll(\PDO::FETCH_ASSOC);

        Response::json([
            'success'        => true,
            'status_history' => $statusHistory,
            'refund_history' => $refundTxns,
        ]);
    }

}
