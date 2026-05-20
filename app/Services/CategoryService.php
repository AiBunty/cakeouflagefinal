<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\FileCache;

/**
 * CategoryService — DB-driven category tree, breadcrumbs, and lookup.
 * Results are cached per request so the DB is only hit once per page load.
 */
final class CategoryService
{
    /** @var array<int,array<string,mixed>> Flat keyed by id */
    private static $byId = [];

    /** @var array<string,array<string,mixed>> Flat keyed by slug */
    private static $bySlug = [];

    /** @var list<array<string,mixed>> Full nested tree (roots only, children nested) */
    private static $tree = [];

    private static $loaded = false;

    private const NAV_CACHE_KEY = 'category_nav_rows_v1';
    private const NAV_CACHE_TTL = 300;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return nested navigation tree for all active, show_in_menu categories.
     * @return list<array<string,mixed>>
     */
    public static function getNavTree(): array
    {
        self::loadAll();
        return self::$tree;
    }

    /**
     * Return a single category by slug, or null if not found / inactive.
     * @return array<string,mixed>|null
     */
    public static function getBySlug(string $slug): ?array
    {
        self::loadAll();
        return self::$bySlug[$slug] ?? null;
    }

    /**
     * Return a single category by id, or null if not found.
     * @return array<string,mixed>|null
     */
    public static function getById(int $id): ?array
    {
        self::loadAll();
        return self::$byId[$id] ?? null;
    }

    /**
     * Return immediate children of a category id.
     * @return list<array<string,mixed>>
     */
    public static function getChildren(int $parentId): array
    {
        self::loadAll();
        $children = [];
        foreach (self::$byId as $cat) {
            if ((int)($cat['parent_id'] ?? 0) === $parentId && $cat['is_active']) {
                $children[] = $cat;
            }
        }
        usort($children, static function ($a, $b) {
            return (int)$a['sort_order'] - (int)$b['sort_order'];
        });
        return $children;
    }

    /**
     * Return all descendant IDs (children + grandchildren) for a category.
     * Used to collect all products that live anywhere under a parent category.
     * @return list<int>
     */
    public static function getDescendantIds(int $categoryId): array
    {
        self::loadAll();
        $ids = [];
        self::collectDescendants($categoryId, $ids);
        return $ids;
    }

    /**
     * Build breadcrumb chain for a given slug.
     * Returns oldest ancestor first through to the given slug.
     * @return list<array{label:string,href:string,active:bool}>
     */
    public static function getBreadcrumb(string $slug): array
    {
        self::loadAll();
        $cat = self::$bySlug[$slug] ?? null;
        if (!$cat) {
            return [];
        }

        $chain = [];
        $current = $cat;
        while ($current) {
            array_unshift($chain, $current);
            $parentId = (int)($current['parent_id'] ?? 0);
            $current = $parentId > 0 ? (self::$byId[$parentId] ?? null) : null;
        }

        $crumbs = [['label' => 'Home', 'href' => '/', 'active' => false]];
        foreach ($chain as $i => $node) {
            $crumbs[] = [
                'label'  => $node['name'],
                'href'   => self::categoryUrl($node['slug']),
                'active' => $i === count($chain) - 1,
            ];
        }
        return $crumbs;
    }

    /**
     * Return featured categories.
     * @return list<array<string,mixed>>
     */
    public static function getFeatured(int $limit = 8): array
    {
        self::loadAll();
        $featured = [];
        foreach (self::$byId as $cat) {
            if ($cat['is_featured'] && $cat['is_active']) {
                $featured[] = $cat;
            }
        }
        usort($featured, static function ($a, $b) {
            return (int)$a['sort_order'] - (int)$b['sort_order'];
        });
        return array_slice($featured, 0, $limit);
    }

    /**
     * Return all root-level (parent_id IS NULL) active categories.
     * @return list<array<string,mixed>>
     */
    public static function getRoots(): array
    {
        self::loadAll();
        $roots = [];
        foreach (self::$byId as $cat) {
            if (empty($cat['parent_id']) && $cat['is_active']) {
                $roots[] = $cat;
            }
        }
        usort($roots, static function ($a, $b) {
            return (int)$a['sort_order'] - (int)$b['sort_order'];
        });
        return $roots;
    }

    /**
     * Force reload on next access (e.g. after admin save).
     */
    public static function clearCache(): void
    {
        self::$loaded = false;
        self::$byId   = [];
        self::$bySlug = [];
        self::$tree   = [];
        FileCache::forget(self::NAV_CACHE_KEY);
    }

    // -------------------------------------------------------------------------
    // URL helper
    // -------------------------------------------------------------------------

    public static function categoryUrl(string $slug): string
    {
        // Special single-page destinations
        $map = ['courses' => '/course', 'b2b-bulk-orders' => '/b2b', 'b2b' => '/b2b'];
        return $map[$slug] ?? '/category/' . $slug;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private static function loadAll(): void
    {
        if (self::$loaded) {
            return;
        }

        try {
            $rows = FileCache::get(self::NAV_CACHE_KEY, self::NAV_CACHE_TTL);
            if ($rows === null) {
                $db = Database::getInstance();
                $rows = $db->query(
                    'SELECT id, parent_id, name, slug, description,
                            image, banner_image, menu_icon,
                            sort_order, show_in_menu, is_featured, is_active,
                            seo_title, seo_description
                       FROM categories
                      WHERE deleted_at IS NULL
                      ORDER BY sort_order ASC, id ASC'
                );
                FileCache::set(self::NAV_CACHE_KEY, $rows);
            }

            self::hydrateRows($rows);

            self::$loaded = true;
        } catch (\Throwable $e) {
            if (Env::get('APP_ENV', 'production') === 'development') {
                self::loadDevelopmentFallback();
                self::$loaded = true;
                return;
            }

            // Silently degrade — menu renders empty rather than crashing the page
            self::$loaded = true;
        }
    }

    /** @param array<int, array<string,mixed>> $rows */
    private static function hydrateRows(array $rows): void
    {
        foreach ($rows as $row) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
            $row['children'] = [];
            $row['has_children'] = false;

            self::$byId[$row['id']] = $row;
            self::$bySlug[$row['slug']] = &self::$byId[$row['id']];
        }

        foreach (self::$byId as $id => $cat) {
            if ($cat['parent_id'] !== null && isset(self::$byId[$cat['parent_id']])) {
                self::$byId[$cat['parent_id']]['children'][] = &self::$byId[$id];
                self::$byId[$cat['parent_id']]['has_children'] = true;
            }
        }

        self::$tree = [];
        foreach (self::$byId as $id => $cat) {
            if ($cat['parent_id'] === null && $cat['is_active'] && $cat['show_in_menu']) {
                self::$tree[] = &self::$byId[$id];
            }
        }
    }

    private static function loadDevelopmentFallback(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Cakes', 'slug' => 'cakes', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => 'cake', 'sort_order' => 1, 'show_in_menu' => 1, 'is_featured' => 1, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Classic Cakes', 'slug' => 'classic-cakes', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => '', 'sort_order' => 1, 'show_in_menu' => 1, 'is_featured' => 0, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Cheesecakes', 'slug' => 'cheesecakes', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => '', 'sort_order' => 2, 'show_in_menu' => 1, 'is_featured' => 0, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
            ['id' => 4, 'parent_id' => null, 'name' => 'Gifting', 'slug' => 'gifting', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => 'gift', 'sort_order' => 2, 'show_in_menu' => 1, 'is_featured' => 1, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
            ['id' => 5, 'parent_id' => 4, 'name' => 'Gift Hampers', 'slug' => 'gift-hampers', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => '', 'sort_order' => 1, 'show_in_menu' => 1, 'is_featured' => 0, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
            ['id' => 6, 'parent_id' => 4, 'name' => 'Corporate Gifting', 'slug' => 'corporate-gifting', 'description' => '', 'image' => '', 'banner_image' => '', 'menu_icon' => '', 'sort_order' => 2, 'show_in_menu' => 1, 'is_featured' => 0, 'is_active' => 1, 'seo_title' => '', 'seo_description' => ''],
        ];

        foreach ($rows as $row) {
            $row['children'] = [];
            $row['has_children'] = false;
            self::$byId[(int)$row['id']] = $row;
            self::$bySlug[(string)$row['slug']] = &self::$byId[(int)$row['id']];
        }

        foreach (self::$byId as $id => $cat) {
            if ($cat['parent_id'] !== null && isset(self::$byId[$cat['parent_id']])) {
                self::$byId[$cat['parent_id']]['children'][] = &self::$byId[$id];
                self::$byId[$cat['parent_id']]['has_children'] = true;
            }
        }

        self::$tree = [];
        foreach (self::$byId as $id => $cat) {
            if ($cat['parent_id'] === null && $cat['is_active'] && $cat['show_in_menu']) {
                self::$tree[] = &self::$byId[$id];
            }
        }
    }

    /**
     * @param list<int> $ids (passed by reference to accumulate)
     */
    private static function collectDescendants(int $parentId, array &$ids): void
    {
        foreach (self::$byId as $cat) {
            if ((int)($cat['parent_id'] ?? 0) === $parentId) {
                $ids[] = $cat['id'];
                self::collectDescendants($cat['id'], $ids);
            }
        }
    }
}
