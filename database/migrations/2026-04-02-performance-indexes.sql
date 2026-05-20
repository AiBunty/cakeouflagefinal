-- Performance indexes for shared hosting latency reduction
-- Safe to run multiple times on MariaDB 10.6+ (IF NOT EXISTS supported)

ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_categories_parent_active (parent_id, is_active);
ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_categories_slug_active (slug, is_active);

ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_category_status (collection_category_id, availability_status, deleted_at);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_slug_status (slug, availability_status, deleted_at);

ALTER TABLE product_variants ADD INDEX IF NOT EXISTS idx_variants_product_active (product_id, is_active);
ALTER TABLE product_images ADD INDEX IF NOT EXISTS idx_images_product_sort (product_id, sort_order);
