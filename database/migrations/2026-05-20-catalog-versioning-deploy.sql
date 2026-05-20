-- ============================================================
-- PRODUCTION DEPLOY MIGRATION  2026-05-20
-- Catalog versioning tables + pending schema / data patches
-- All statements are idempotent (IF NOT EXISTS / IGNORE guards)
-- Run via: __deploy_migrate_20260520.php?token=<DEPLOY_TOKEN>
-- ============================================================

-- ----------------------------------------------------------------
-- 1. product_import_runs
--    Tracks every import / restore operation (mode, status, stats)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_import_runs` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mode`                  ENUM('commit','dry_run','restore') NOT NULL DEFAULT 'commit',
  `status`                ENUM('pending','success','partial','failed') NOT NULL DEFAULT 'pending',
  `source_file`           VARCHAR(255) DEFAULT NULL,
  `backup_file`           VARCHAR(255) DEFAULT NULL,
  `created_count`         INT NOT NULL DEFAULT 0,
  `updated_count`         INT NOT NULL DEFAULT 0,
  `deleted_count`         INT NOT NULL DEFAULT 0,
  `failed_count`          INT NOT NULL DEFAULT 0,
  `total_rows`            INT NOT NULL DEFAULT 0,
  `restored_from_run_id`  BIGINT UNSIGNED DEFAULT NULL,
  `metadata_json`         LONGTEXT DEFAULT NULL,
  `created_by_admin_id`   BIGINT UNSIGNED DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by_admin_id`                  (`created_by_admin_id`),
  KEY `idx_product_import_runs_created`      (`created_at`),
  KEY `idx_product_import_runs_mode`         (`mode`),
  KEY `idx_product_import_runs_restored`     (`restored_from_run_id`),
  CONSTRAINT `product_import_runs_fk_admin`
    FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------
-- 2. product_import_snapshots
--    Full JSON snapshot of the entire catalog before each import
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_import_snapshots` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`        BIGINT UNSIGNED NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_import_snapshot_run` (`run_id`),
  CONSTRAINT `product_import_snapshots_fk_run`
    FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------
-- 3. product_snapshots
--    Per-product snapshot row for each import operation
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_snapshots` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id`          BIGINT UNSIGNED NOT NULL,
  `product_id`      BIGINT UNSIGNED NOT NULL,
  `sku`             VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_data`    LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL
                      COMMENT 'JSON snapshot of complete product record',
  `operation`       ENUM('insert','update','delete','restore')
                      COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence_number` INT UNSIGNED NOT NULL COMMENT 'Order within the import run',
  `has_variants`    TINYINT(1) DEFAULT 0,
  `variant_count`   INT UNSIGNED DEFAULT 0,
  `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_run_id`        (`run_id`),
  KEY `idx_product_id`    (`product_id`),
  KEY `idx_sku`           (`sku`),
  KEY `idx_operation`     (`operation`),
  KEY `idx_created_at`    (`created_at` DESC),
  KEY `idx_run_product`   (`run_id`, `product_id`),
  KEY `idx_product_latest`(`product_id`, `run_id` DESC),
  CONSTRAINT `product_snapshots_fk_run`
    FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Snapshot of product data at each import operation for version history and restore';

-- ----------------------------------------------------------------
-- 4. product_variant_snapshots
--    Per-variant snapshot rows linked to a product_snapshot
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_variant_snapshots` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `snapshot_id`          BIGINT UNSIGNED NOT NULL,
  `run_id`               BIGINT UNSIGNED NOT NULL,
  `product_id`           BIGINT UNSIGNED NOT NULL,
  `variant_id`           BIGINT UNSIGNED NOT NULL,
  `variant_sku`          VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_data`         LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL
                           COMMENT 'JSON snapshot of variant record',
  `variant_option_values`VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
                           COMMENT 'e.g. "Size: Large, Color: Red"',
  `variant_price`        DECIMAL(10,2) DEFAULT NULL,
  `variant_stock`        INT UNSIGNED DEFAULT 0,
  `sequence_number`      INT UNSIGNED NOT NULL,
  `created_at`           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot_id` (`snapshot_id`),
  KEY `idx_run_id`      (`run_id`),
  KEY `idx_product_id`  (`product_id`),
  KEY `idx_variant_id`  (`variant_id`),
  KEY `idx_created_at`  (`created_at` DESC),
  CONSTRAINT `product_variant_snapshots_fk_snapshot`
    FOREIGN KEY (`snapshot_id`) REFERENCES `product_snapshots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_variant_snapshots_fk_run`
    FOREIGN KEY (`run_id`) REFERENCES `product_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Snapshot of product variants at import time for complete version history';

-- ----------------------------------------------------------------
-- 5. products.deleted_at  (soft-delete column — idempotent)
-- ----------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND COLUMN_NAME  = 'deleted_at'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ----------------------------------------------------------------
-- 6. products.is_veg  (dietary veg flag — idempotent)
-- ----------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'products'
    AND COLUMN_NAME  = 'is_veg'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE products ADD COLUMN is_veg TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT 1'
);
PREPARE _stmt FROM @ddl; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ----------------------------------------------------------------
-- 7. orders.payment_status — add 'partial' ENUM value
-- ----------------------------------------------------------------
ALTER TABLE `orders`
  MODIFY COLUMN `payment_status`
    ENUM('pending','paid','failed','refunded','credit','partial')
    NOT NULL DEFAULT 'pending';

-- ----------------------------------------------------------------
-- 8. orders.payment_method — ensure 'credit' ENUM value exists
-- ----------------------------------------------------------------
ALTER TABLE `orders`
  MODIFY COLUMN `payment_method`
    ENUM('upi_manual','cod','gateway','credit')
    NOT NULL DEFAULT 'upi_manual';

-- ----------------------------------------------------------------
-- 9. BYOC order confirmed email templates
-- ----------------------------------------------------------------
INSERT IGNORE INTO `communication_templates`
  (`channel`, `event_key`, `subject`, `body_template`, `is_active`)
VALUES (
  'email',
  'byoc_order_confirmed_customer',
  'Your Custom Cake Order Is Confirmed - {{order_number}}',
  'Hi {{customer_name}},\n\nGreat news! Your custom cake order has been confirmed.\n\nOrder Number: {{order_number}}\nOrder Total: {{currency}} {{grand_total}}\nAdvance Paid: {{currency}} {{advance_amount}}\nRemaining Balance: {{currency}} {{remaining_balance}}\n\nDelivery Address: {{delivery_address}}\n\n{{#if event_date}}Event Date: {{event_date}}{{/if}}\n\nOur team will be in touch shortly to confirm your order details. If you have any questions, please reply to this email or WhatsApp us.\n\nThank you for choosing Cakeouflage!\n\nWarm regards,\nCakeouflage Team',
  1
);

INSERT IGNORE INTO `communication_templates`
  (`channel`, `event_key`, `subject`, `body_template`, `is_active`)
VALUES (
  'email',
  'byoc_order_confirmed_admin',
  'New BYOC Order Received - {{order_number}} from {{customer_name}}',
  'A BYOC quote has been accepted and converted to an order.\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}}\nEmail: {{customer_email}}\nPhone: {{customer_phone}}\n\nOrder Total: {{currency}} {{grand_total}}\nAdvance Paid: {{currency}} {{advance_amount}}\nPayment Status: {{payment_status}}\n\nDelivery Address: {{delivery_address}}\n{{#if event_date}}Event Date: {{event_date}}{{/if}}\n\nPlease review and confirm this order in the admin panel.',
  1
);

-- ----------------------------------------------------------------
-- 10. Fix any queued jobs with wrong job_type (communication_send)
-- ----------------------------------------------------------------
UPDATE `queue_jobs`
SET    `job_type` = 'send_communication'
WHERE  `job_type` = 'communication_send'
  AND  `status`   = 'queued';

-- ============================================================
-- END OF MIGRATION 2026-05-20
-- ============================================================
