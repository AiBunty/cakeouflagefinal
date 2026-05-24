-- Performance indexes for shared hosting latency reduction
-- MySQL 8-safe: check INFORMATION_SCHEMA.STATISTICS before ALTER TABLE ... ADD INDEX

SET @schema_name := DATABASE();

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'categories'
	AND index_name = 'idx_categories_parent_active';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE categories ADD INDEX idx_categories_parent_active (parent_id, is_active)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'categories'
	AND index_name = 'idx_categories_slug_active';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE categories ADD INDEX idx_categories_slug_active (slug, is_active)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'products'
	AND index_name = 'idx_products_category_status';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE products ADD INDEX idx_products_category_status (collection_category_id, availability_status, deleted_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'products'
	AND index_name = 'idx_products_slug_status';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE products ADD INDEX idx_products_slug_status (slug, availability_status, deleted_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'product_variants'
	AND index_name = 'idx_variants_product_active';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE product_variants ADD INDEX idx_variants_product_active (product_id, is_active)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists
FROM information_schema.statistics
WHERE table_schema = @schema_name
	AND table_name = 'product_images'
	AND index_name = 'idx_images_product_sort';
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE product_images ADD INDEX idx_images_product_sort (product_id, sort_order)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
