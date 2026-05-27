-- Product master v2: canonical products.description + dynamic variant metadata

ALTER TABLE products
  ADD COLUMN description TEXT NULL AFTER short_description;

UPDATE products
SET description = COALESCE(NULLIF(description, ''), NULLIF(long_description, ''), short_description)
WHERE description IS NULL OR description = '';

ALTER TABLE product_variants
  ADD COLUMN variant_name VARCHAR(120) NULL AFTER variant_label,
  ADD COLUMN unit_type VARCHAR(40) NULL AFTER weight_or_size,
  ADD COLUMN sku VARCHAR(120) NULL AFTER sku_suffix;

UPDATE product_variants
SET
  variant_name = COALESCE(NULLIF(variant_name, ''), NULLIF(variant_label, ''), weight_or_size),
  unit_type = COALESCE(NULLIF(unit_type, ''), 'custom')
WHERE variant_name IS NULL OR variant_name = '' OR unit_type IS NULL OR unit_type = '';

ALTER TABLE product_variants
  MODIFY COLUMN variant_name VARCHAR(120) NOT NULL,
  MODIFY COLUMN unit_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  ADD UNIQUE KEY uq_product_variant_name_unit (product_id, variant_name, unit_type),
  ADD INDEX idx_product_variants_sku (sku);
