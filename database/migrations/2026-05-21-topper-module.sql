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
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS topper_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = allow topper selection on PDP';

-- 4. Add per-item customisation columns to cart_items
ALTER TABLE cart_items
    ADD COLUMN IF NOT EXISTS cake_message         VARCHAR(200) NULL       COMMENT 'Message to write on cake',
    ADD COLUMN IF NOT EXISTS topper_id            INT UNSIGNED NULL       COMMENT 'FK to cake_toppers.id',
    ADD COLUMN IF NOT EXISTS topper_name_snapshot VARCHAR(100) NULL       COMMENT 'Topper name at time of add',
    ADD COLUMN IF NOT EXISTS topper_price         DECIMAL(8,2) NOT NULL DEFAULT 0.00;

-- 5. Add per-item customisation columns to order_items
ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS cake_message          VARCHAR(200) NULL,
    ADD COLUMN IF NOT EXISTS topper_id             INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS topper_name_snapshot  VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS topper_price_snapshot DECIMAL(8,2) NOT NULL DEFAULT 0.00;

-- 6. Add note_enabled flag to products (controls "Note on the Cake" input on PDP)
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS note_enabled TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = show Note on the Cake input on PDP, 0 = hide it';
