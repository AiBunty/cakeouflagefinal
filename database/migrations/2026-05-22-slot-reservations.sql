-- ============================================================
-- Migration: slot_reservations + orders missing columns
-- Date: 2026-05-22
-- Purpose: Implement HOLD→CONFIRMED slot lifecycle
-- ============================================================

-- 1. Fix missing orders columns (applied separately, here for reference)
-- ALTER TABLE `orders`
--   ADD COLUMN `delivery_street` VARCHAR(255) NULL AFTER `delivery_postal_code`,
--   ADD COLUMN `delivery_maps_link` VARCHAR(500) NULL AFTER `delivery_street`,
--   ADD COLUMN `advance_amount` DECIMAL(10,2) NULL AFTER `grand_total`;

-- 2. Slot reservations lifecycle table
CREATE TABLE IF NOT EXISTS `slot_reservations` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`           BIGINT UNSIGNED NOT NULL,
    `slot_id`            BIGINT UNSIGNED NOT NULL,
    `booking_date`       DATE            NOT NULL,
    `reservation_status` ENUM('hold','confirmed','released','expired','cancelled')
                         NOT NULL DEFAULT 'hold',
    `expires_at`         DATETIME        NULL COMMENT 'Auto-expiry time for hold state',
    `confirmed_at`       DATETIME        NULL,
    `released_at`        DATETIME        NULL,
    `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_reservation` (`order_id`),
    KEY `idx_slot_date_status` (`slot_id`, `booking_date`, `reservation_status`),
    KEY `idx_expires_at` (`expires_at`),
    CONSTRAINT `fk_sr_order` FOREIGN KEY (`order_id`)  REFERENCES `orders`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_sr_slot`  FOREIGN KEY (`slot_id`)   REFERENCES `order_slots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. New business settings for slot hold system
INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES
    ('enable_slot_holds',                  '1'),
    ('hold_expiry_minutes',                '60'),
    ('max_hold_percentage',                '50'),
    ('require_admin_payment_confirmation', '1'),
    ('auto_release_expired_holds',         '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
