-- =============================================================
--  Topper Module Migration — 2026-05-21
--  Run this once against the live database.
-- =============================================================

-- 1. Cake Toppers master table
CREATE TABLE IF NOT EXISTS cake_toppers (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    price      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(200) NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_topper_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed default toppers (safe to re-run)
INSERT IGNORE INTO cake_toppers (name, price, sort_order) VALUES
    ('No Topper',          0.00, 0),
    ('Happy Birthday',     0.00, 1),
    ('Happy Anniversary',  0.00, 2),
    ('Happy Wedding',      0.00, 3),
    ('Baby Shower',        0.00, 4),
    ('Custom Message',     0.00, 5);

-- 3. Add topper_enabled flag to products
SET @schema_name := DATABASE();

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'products'
    AND column_name = 'topper_enabled';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE products ADD COLUMN topper_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = allow topper selection on PDP''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Add per-item customisation columns to cart_items
SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'cart_items'
    AND column_name = 'cake_message';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE cart_items ADD COLUMN cake_message VARCHAR(200) NULL COMMENT ''Message to write on cake''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'cart_items'
    AND column_name = 'topper_id';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE cart_items ADD COLUMN topper_id INT UNSIGNED NULL COMMENT ''FK to cake_toppers.id''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'cart_items'
    AND column_name = 'topper_name_snapshot';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE cart_items ADD COLUMN topper_name_snapshot VARCHAR(100) NULL COMMENT ''Topper name at time of add''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'cart_items'
    AND column_name = 'topper_price';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE cart_items ADD COLUMN topper_price DECIMAL(8,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Add per-item customisation columns to order_items
SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'order_items'
    AND column_name = 'cake_message';
SET @sql := IF(@col_exists = 0, 'ALTER TABLE order_items ADD COLUMN cake_message VARCHAR(200) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'order_items'
    AND column_name = 'topper_id';
SET @sql := IF(@col_exists = 0, 'ALTER TABLE order_items ADD COLUMN topper_id INT UNSIGNED NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'order_items'
    AND column_name = 'topper_name_snapshot';
SET @sql := IF(@col_exists = 0, 'ALTER TABLE order_items ADD COLUMN topper_name_snapshot VARCHAR(100) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'order_items'
    AND column_name = 'topper_price_snapshot';
SET @sql := IF(@col_exists = 0, 'ALTER TABLE order_items ADD COLUMN topper_price_snapshot DECIMAL(8,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Add note_enabled flag to products (controls "Note on the Cake" input on PDP)
SELECT COUNT(*) INTO @col_exists
FROM information_schema.columns
WHERE table_schema = @schema_name
    AND table_name = 'products'
    AND column_name = 'note_enabled';
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE products ADD COLUMN note_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = show Note on the Cake input on PDP, 0 = hide it''',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
