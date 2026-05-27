-- Add canonical dietary_type to products, backfill from legacy is_veg, and ensure default store food mode.

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS dietary_type ENUM('veg','nonveg') NOT NULL DEFAULT 'veg' AFTER occasion_tag;

UPDATE products
SET dietary_type = CASE
    WHEN is_veg = 0 THEN 'nonveg'
    ELSE 'veg'
END
WHERE dietary_type IS NULL OR dietary_type = '';

ALTER TABLE products
  ADD INDEX IF NOT EXISTS idx_products_dietary_type (dietary_type);

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'store_food_mode', 'veg_only', 1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'store_food_mode'
);
