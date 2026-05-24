-- =============================================================================
-- Migration: Atomic Single-Step Refund Governance
-- Date:      2026-05-23
-- Depends:   2026-05-27-order-state-machine.sql (must already be applied)
--
-- What this migration does:
--   1.  Expand order_status ENUM → add 'fully_refunded'
--   2.  Expand orders.refund_status ENUM → add 'partially_refunded','fully_refunded'
--   3.  Add total_refunded to orders
--   4.  Add settlement_reference / settlement_proof to orders
--   5.  Add settlement columns to refund_transactions
--   6.  Extend refund_approval_logs.action ENUM → add 'processed'
--   7.  Add idx_rt_processed_at index on refund_transactions
--   8.  Backfill total_refunded from legacy refund_amount
--   9.  Seed 6 communication_templates (3 × customer + 3 × admin)
--  10.  Seed uploads directory guard row in settings
--
-- Idempotent: every DDL step is guarded with IF NOT EXISTS or column-existence
--             checks so re-running causes zero harm.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 1: Expand order_status ENUM — add 'fully_refunded'
--         We must list ALL currently valid values to avoid ENUM truncation.
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
        'fully_refunded',
        'rejected'
    ) NOT NULL DEFAULT 'pending_payment';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 2: Expand orders.refund_status ENUM
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `orders`
    MODIFY `refund_status` ENUM(
        'none',
        'requested',
        'approved',
        'rejected',
        'processed',
        'partially_refunded',
        'fully_refunded'
    ) NOT NULL DEFAULT 'none';

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 3: Add total_refunded to orders (tracks cumulative amount returned)
--         Guarded: only added if the column does not already exist.
-- ─────────────────────────────────────────────────────────────────────────────
SET @col_exists_total_refunded = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'total_refunded'
);

SET @sql_total_refunded = IF(
    @col_exists_total_refunded = 0,
    'ALTER TABLE `orders` ADD COLUMN `total_refunded` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `refund_amount`',
    'SELECT 1 -- total_refunded already exists'
);
PREPARE stmt FROM @sql_total_refunded;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 4: Add settlement_reference + settlement_proof to orders
-- ─────────────────────────────────────────────────────────────────────────────
SET @col_exists_settlement_ref = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'orders'
      AND COLUMN_NAME  = 'settlement_reference'
);

SET @sql_settlement_ref = IF(
    @col_exists_settlement_ref = 0,
    'ALTER TABLE `orders` ADD COLUMN `settlement_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `refunded_at`, ADD COLUMN `settlement_proof` VARCHAR(500) NULL DEFAULT NULL AFTER `settlement_reference`',
    'SELECT 1 -- settlement columns already exist'
);
PREPARE stmt FROM @sql_settlement_ref;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 5: Add settlement columns to refund_transactions
-- ─────────────────────────────────────────────────────────────────────────────
SET @col_exists_rt_ref = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'refund_transactions'
      AND COLUMN_NAME  = 'settlement_reference'
);

SET @sql_rt_settlement = IF(
    @col_exists_rt_ref = 0,
    'ALTER TABLE `refund_transactions` ADD COLUMN `settlement_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `fraud_flags`, ADD COLUMN `settlement_proof_url` VARCHAR(500) NULL DEFAULT NULL AFTER `settlement_reference`',
    'SELECT 1 -- rt settlement columns already exist'
);
PREPARE stmt FROM @sql_rt_settlement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 6: Extend refund_approval_logs.action ENUM → add 'processed'
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `refund_approval_logs`
    MODIFY `action` ENUM('submitted','approved','rejected','escalated','processed') NOT NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 7: Index on refund_transactions.processed_at for report queries
-- ─────────────────────────────────────────────────────────────────────────────
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'refund_transactions'
      AND INDEX_NAME   = 'idx_rt_processed_at'
);

SET @sql_idx = IF(
    @idx_exists = 0,
    'ALTER TABLE `refund_transactions` ADD INDEX `idx_rt_processed_at` (`processed_at`)',
    'SELECT 1 -- index already exists'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 8: Backfill total_refunded from existing refund_amount values
--         Only updates rows where total_refunded is still at its default of 0
--         and refund_amount has a value — safe to re-run.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `orders`
SET    `total_refunded` = COALESCE(`refund_amount`, 0.00)
WHERE  `total_refunded` = 0.00
  AND  `refund_amount`  IS NOT NULL
  AND  `refund_amount`  > 0;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 9: Seed communication_templates for refund events
--         Uses ON DUPLICATE KEY UPDATE so re-running never creates duplicates.
--         Unique key on (channel, event_key) is assumed from schema.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `communication_templates` (`channel`, `event_key`, `subject`, `body_template`, `is_active`) VALUES

-- Partial refund — customer
('email', 'partial_refund_processed_customer',
 'Partial Refund Processed - {{order_number}}',
 '<div style="background:#fffbeb;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#92400e;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Partial Refund Processed</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A partial refund has been processed for your order with Cakeouflage.</p><div style="margin-top:28px;background:#fffbeb;border:1px solid #fde68a;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Refund Amount:</strong> &#8377;{{refund_amount}}</p><p><strong>Reason:</strong> {{refund_reason}}</p><p><strong>Reference:</strong> {{refund_reference}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">If you have any questions regarding this refund, please contact our support team.</div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>',
 1),

-- Partial refund — admin
('email', 'partial_refund_processed_admin',
 'Partial Refund Processed - {{order_number}}',
 '<div style="background:#fffbeb;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#92400e;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Partial Refund Alert</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Partial Refund Processed</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A partial refund was processed by the team. Details are below.</p><div style="margin-top:28px;background:#fffbeb;border:1px solid #fde68a;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Refund Amount:</strong> &#8377;{{refund_amount}}</p><p><strong>Reason:</strong> {{refund_reason}}</p><p><strong>Notes:</strong> {{refund_notes}}</p><p><strong>Reference:</strong> {{refund_reference}}</p></div></div><div style="background:#140b0f;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>',
 1),

-- Full refund — customer
('email', 'full_refund_processed_customer',
 'Full Refund Processed - {{order_number}}',
 '<div style="background:#f0fdf4;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#166534;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Full Refund Processed</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A full refund has been processed for your order with Cakeouflage.</p><div style="margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Refund Amount:</strong> &#8377;{{refund_amount}}</p><p><strong>Reason:</strong> {{refund_reason}}</p><p><strong>Reference:</strong> {{refund_reference}}</p></div><div style="margin-top:22px;background:#fff5da;padding:18px;border-radius:12px;color:#7a5300;">We are sorry for any inconvenience. Thank you for your understanding.</div></div><div style="background:#052e16;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">Premium Designer Cakes crafted with elegance and creativity.</p><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>',
 1),

-- Full refund — admin
('email', 'full_refund_processed_admin',
 'Full Refund Processed - {{order_number}}',
 '<div style="background:#f0fdf4;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#166534;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Full Refund Alert</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Full Refund Processed</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">A full refund was processed by the team. Details are below.</p><div style="margin-top:28px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order ID:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Refund Amount:</strong> &#8377;{{refund_amount}}</p><p><strong>Reason:</strong> {{refund_reason}}</p><p><strong>Notes:</strong> {{refund_notes}}</p><p><strong>Reference:</strong> {{refund_reference}}</p></div></div><div style="background:#052e16;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>',
 1),

-- Generic refund processed — customer (fires for both partial and full)
('email', 'refund_processed_customer',
 'Refund Processed - {{order_number}}',
 '<div style="background:#f5f5f4;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#44403c;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Refund Processed</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Hi {{customer_name}}</h2><p style="color:#5f4c55;font-size:16px;line-height:1.8;">Your refund of &#8377;{{refund_amount}} for order {{order_number}} has been processed.</p><div style="margin-top:28px;background:#f5f5f4;border:1px solid #e7e5e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Refund Type:</strong> {{refund_type}}</p><p><strong>Reason:</strong> {{refund_reason}}</p><p><strong>Reference:</strong> {{refund_reference}}</p></div></div><div style="background:#1c1917;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3><p style="color:#d7c6cc;font-size:14px;">&#127760; www.cakeouflage.com</p></div></div></div>',
 1),

-- Generic refund processed — admin
('email', 'refund_processed_admin',
 'Refund Processed - {{order_number}}',
 '<div style="background:#f5f5f4;padding:40px;font-family:Arial,sans-serif;"><div style="max-width:650px;margin:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,0.08);"><div style="background:#44403c;padding:28px;color:#fff;"><div style="margin-bottom:12px;"><img src="{{email_logo_url}}" alt="Cakeouflage Logo" style="height:100px;display:block;"></div><p style="margin-top:10px;font-size:14px;opacity:0.9;">Refund Processed Alert</p></div><div style="padding:40px;"><h2 style="margin-top:0;color:#1d1115;font-size:30px;">Refund Processed</h2><div style="margin-top:28px;background:#f5f5f4;border:1px solid #e7e5e4;border-radius:16px;padding:24px;color:#3b252d;line-height:1.75;"><p><strong>Order:</strong> {{order_number}}</p><p><strong>Customer:</strong> {{customer_name}}</p><p><strong>Refund Type:</strong> {{refund_type}}</p><p><strong>Amount:</strong> &#8377;{{refund_amount}}</p><p><strong>Reason:</strong> {{refund_reason}}</p></div></div><div style="background:#1c1917;padding:30px;color:#fff;"><h3 style="margin-top:0;font-family:Georgia,serif;">Team Cakeouflage</h3></div></div></div>',
 1)

ON DUPLICATE KEY UPDATE
    `subject`       = VALUES(`subject`),
    `body_template` = VALUES(`body_template`),
    `is_active`     = VALUES(`is_active`);

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 10: Ensure refund-proofs upload directory setting exists
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_by_admin_id`, `updated_at`)
VALUES ('refund_proof_upload_path', 'uploads/refund-proofs/', NULL, NOW())
ON DUPLICATE KEY UPDATE `updated_at` = `updated_at`;

SET FOREIGN_KEY_CHECKS = 1;
