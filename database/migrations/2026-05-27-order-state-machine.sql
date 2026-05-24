-- =============================================================================
-- Migration: Order State Machine (13-state ENUM + refund tables + audit trail)
-- Date:      2026-05-27
-- Requires:  ~30s maintenance window (order_status ENUM rebuild = full table rewrite)
--
-- Execution order:
--   1. Add refund columns to orders   (additive — instant)
--   2. Expand order_status ENUM       (holds old + new values so no row violation)
--   3. Data migration                 (pending→pending_payment, in_preparation→preparing, completed→delivered)
--   4. Shrink order_status ENUM       (final 13 values)
--   5. Rebuild payment_status ENUM    (adds refund_pending, partially_refunded; removes unused partial/confirmed)
--   6. Add refund_status index        (query performance)
--   7. CREATE order_status_history    (immutable audit trail)
--   8. CREATE refund_transactions     (refund lifecycle records)
--   9. CREATE refund_approval_logs    (approval audit trail)
--  10. Seed accounting_lock_days=30   (default lock period for RefundService)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 1: Add refund columns to orders
--         All columns are nullable; no existing row is affected.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    ADD COLUMN `refund_status`               ENUM('none','requested','approved','rejected','processed')
                                             NOT NULL DEFAULT 'none'            AFTER `payment_status`,
    ADD COLUMN `refund_amount`               DECIMAL(10,2)   NULL DEFAULT NULL  AFTER `refund_status`,
    ADD COLUMN `refund_reason`               VARCHAR(100)    NULL DEFAULT NULL  AFTER `refund_amount`,
    ADD COLUMN `refund_notes`                TEXT            NULL DEFAULT NULL  AFTER `refund_reason`,
    ADD COLUMN `refunded_by_admin_id`        BIGINT UNSIGNED NULL DEFAULT NULL  AFTER `refund_notes`,
    ADD COLUMN `refund_approved_by_admin_id` BIGINT UNSIGNED NULL DEFAULT NULL  AFTER `refunded_by_admin_id`,
    ADD COLUMN `refund_requested_at`         DATETIME        NULL DEFAULT NULL  AFTER `refund_approved_by_admin_id`,
    ADD COLUMN `refunded_at`                 DATETIME        NULL DEFAULT NULL  AFTER `refund_requested_at`;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 2: Expand order_status ENUM
--         Must include ALL currently-used values so no row violates the constraint
--         during the next UPDATE step.
--         Old values kept temporarily: 'pending', 'in_preparation'
--         ('completed' is also kept because one live row uses it)
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    MODIFY `order_status` ENUM(
        -- Legacy values (removed after data migration in STEP 4)
        'pending',
        'in_preparation',
        -- New canonical 13 values
        'pending_payment',
        'payment_under_review',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
        'refund_requested',
        'refunded',
        'partially_refunded',
        'rejected'
    ) NOT NULL DEFAULT 'pending_payment';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 3: Data migration — map old status names to canonical names
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `orders` SET `order_status` = 'pending_payment' WHERE `order_status` = 'pending';
UPDATE `orders` SET `order_status` = 'preparing'       WHERE `order_status` = 'in_preparation';
-- 'completed' in the old schema = order physically delivered + closed.
-- In the new schema that maps to 'delivered' (physical handoff);
-- 'completed' now means the order has been fully reconciled and closed.
UPDATE `orders` SET `order_status` = 'delivered'       WHERE `order_status` = 'completed';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 4: Shrink order_status ENUM to the final 13 canonical values
--         All rows must already be using new values (guaranteed by STEP 3).
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    MODIFY `order_status` ENUM(
        'pending_payment',
        'payment_under_review',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
        'refund_requested',
        'refunded',
        'partially_refunded',
        'rejected'
    ) NOT NULL DEFAULT 'pending_payment';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 5: Rebuild payment_status ENUM
--         Removed: 'partial' (0 live rows), 'confirmed' (0 live rows)
--         Added:   'refund_pending', 'partially_refunded'
--         All live rows have payment_status = 'paid', which is retained.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    MODIFY `payment_status` ENUM(
        'pending',
        'under_review',
        'paid',
        'credit',
        'refund_pending',
        'partially_refunded',
        'refunded',
        'failed',
        'rejected'
    ) NOT NULL DEFAULT 'pending';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 6: Index on orders.refund_status for pending-refund queue queries
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    ADD INDEX `idx_orders_refund_status` (`refund_status`);

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 7: order_status_history — immutable audit trail for every status change
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`            BIGINT UNSIGNED NOT NULL,
    `previous_status`     VARCHAR(40)     NULL     DEFAULT NULL
                          COMMENT 'NULL for the initial status row on order creation',
    `new_status`          VARCHAR(40)     NOT NULL,
    `changed_by_admin_id` BIGINT UNSIGNED NULL     DEFAULT NULL,
    `admin_role`          VARCHAR(40)     NULL     DEFAULT NULL,
    `ip_address`          VARCHAR(45)     NULL     DEFAULT NULL,
    `reason`              TEXT            NULL     DEFAULT NULL,
    `metadata`            JSON            NULL     DEFAULT NULL
                          COMMENT 'Arbitrary extra data: refund_id, automation trigger, etc.',
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_osh_order_id`   (`order_id`),
    KEY `idx_osh_admin_id`   (`changed_by_admin_id`),
    KEY `idx_osh_created_at` (`created_at`),

    CONSTRAINT `fk_osh_order_id`
        FOREIGN KEY (`order_id`)            REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_osh_admin_id`
        FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log of every order status transition';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 8: refund_transactions — lifecycle record for each refund request
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `refund_transactions` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`              BIGINT UNSIGNED NOT NULL,
    `refund_number`         VARCHAR(40)     NOT NULL
                            COMMENT 'Unique human-readable ref, e.g. RFD-20260527-0001',
    `refund_type`           ENUM('full','partial') NOT NULL,
    `reason_code`           VARCHAR(100)    NOT NULL
                            COMMENT 'Standardised code, e.g. DAMAGED_ITEM, WRONG_ORDER',
    `reason_notes`          TEXT            NULL DEFAULT NULL,
    `requested_amount`      DECIMAL(10,2)   NOT NULL
                            COMMENT 'Amount requested by admin; capped at grand_total - delivery_fee',
    `approved_amount`       DECIMAL(10,2)   NULL DEFAULT NULL
                            COMMENT 'Final approved amount; may differ from requested_amount',
    `status`                ENUM('pending_approval','approved','rejected','processed')
                            NOT NULL DEFAULT 'pending_approval',
    `requested_by_admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `approved_by_admin_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
    `previous_order_status` VARCHAR(40)     NULL DEFAULT NULL
                            COMMENT 'Snapshot of order_status at request time; used to revert on rejection',
    `fraud_flags`           JSON            NULL DEFAULT NULL
                            COMMENT 'Array of triggered fraud-check codes, e.g. ["DUPLICATE_REFUND"]',
    `requested_at`          DATETIME        NULL DEFAULT NULL,
    `approved_at`           DATETIME        NULL DEFAULT NULL,
    `processed_at`          DATETIME        NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_rt_refund_number`  (`refund_number`),
    KEY `idx_rt_order_id`             (`order_id`),
    KEY `idx_rt_status`               (`status`),
    KEY `idx_rt_requested_by`         (`requested_by_admin_id`),
    KEY `idx_rt_requested_at`         (`requested_at`),

    CONSTRAINT `fk_rt_order_id`
        FOREIGN KEY (`order_id`)              REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_rt_requested_by`
        FOREIGN KEY (`requested_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_rt_approved_by`
        FOREIGN KEY (`approved_by_admin_id`)  REFERENCES `admins` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='One row per refund request; ON DELETE RESTRICT protects refunded orders from deletion';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 9: refund_approval_logs — append-only log of every action on a refund
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `refund_approval_logs` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_transaction_id` BIGINT UNSIGNED NOT NULL,
    `action`                ENUM('submitted','approved','rejected','escalated') NOT NULL,
    `performed_by_admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `admin_role`            VARCHAR(40)     NULL DEFAULT NULL,
    `ip_address`            VARCHAR(45)     NULL DEFAULT NULL,
    `notes`                 TEXT            NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_ral_refund_id`  (`refund_transaction_id`),
    KEY `idx_ral_admin_id`   (`performed_by_admin_id`),
    KEY `idx_ral_created_at` (`created_at`),

    CONSTRAINT `fk_ral_refund_id`
        FOREIGN KEY (`refund_transaction_id`) REFERENCES `refund_transactions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ral_admin_id`
        FOREIGN KEY (`performed_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Append-only audit log for every approval action taken on a refund';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 10: Seed default settings
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_by_admin_id`, `updated_at`)
VALUES ('accounting_lock_days', '30', NULL, NOW())
ON DUPLICATE KEY UPDATE `updated_at` = `updated_at`;

SET FOREIGN_KEY_CHECKS = 1;
