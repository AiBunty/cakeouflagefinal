-- Cakeouflage hardening migration: media metadata + shop filter attributes/indexes

-- Shop filter attributes (MySQL 8 compatible additive changes)
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_veg TINYINT(1) NOT NULL DEFAULT 1 AFTER dietary_tag;
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_eggless TINYINT(1) NOT NULL DEFAULT 0 AFTER is_veg;
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_vegan TINYINT(1) NOT NULL DEFAULT 0 AFTER is_eggless;

UPDATE products SET is_eggless = CASE WHEN dietary_tag = 'eggless' THEN 1 ELSE 0 END WHERE is_eggless IS NULL OR is_eggless IN (0,1);
UPDATE products SET is_vegan = CASE WHEN dietary_tag = 'vegan' THEN 1 ELSE 0 END WHERE is_vegan IS NULL OR is_vegan IN (0,1);
UPDATE products SET is_veg = CASE WHEN dietary_tag IN ('eggless', 'vegan', 'sugar_free', 'regular') THEN 1 ELSE is_veg END WHERE is_veg IS NULL OR is_veg IN (0,1);

CREATE INDEX IF NOT EXISTS idx_products_dietary ON products (dietary_tag);
CREATE INDEX IF NOT EXISTS idx_products_is_veg ON products (is_veg);
CREATE INDEX IF NOT EXISTS idx_products_is_eggless ON products (is_eggless);
CREATE INDEX IF NOT EXISTS idx_products_is_vegan ON products (is_vegan);
CREATE INDEX IF NOT EXISTS idx_products_active_filter ON products (availability_status, deleted_at, collection_category_id);
CREATE INDEX IF NOT EXISTS idx_product_variants_filter_price ON product_variants (product_id, is_active, price, discount_price);

-- Media assets table stores canonical and original paths and conversion lifecycle
CREATE TABLE IF NOT EXISTS media_assets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  original_path VARCHAR(255) NOT NULL,
  canonical_path VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  conversion_status ENUM('ready','queued','processing','failed') NOT NULL DEFAULT 'ready',
  conversion_error VARCHAR(260) NULL,
  version_token VARCHAR(40) NOT NULL,
  uploaded_by_admin_id BIGINT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_assets_canonical (canonical_path),
  INDEX idx_media_assets_original (original_path),
  INDEX idx_media_assets_status (conversion_status, updated_at)
) ENGINE=InnoDB;
