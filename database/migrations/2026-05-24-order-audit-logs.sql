-- =============================================================================
-- Migration: Order Governance Audit Logs
-- Date:      2026-05-24
-- Purpose:   Add immutable order_audit_logs for governance, refunds, cancellations,
--            payment confirmations, and reconciliation actions.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `order_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `action_type` VARCHAR(60) NOT NULL COMMENT 'status_transition|payment_status_update|refund_processed|cancel_request|reconciliation_fix|etc',
  `previous_status` VARCHAR(40) NULL DEFAULT NULL,
  `new_status` VARCHAR(40) NULL DEFAULT NULL,
  `payment_status` VARCHAR(40) NULL DEFAULT NULL,
  `admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `admin_role` VARCHAR(40) NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `message` VARCHAR(255) NULL DEFAULT NULL,
  `metadata` JSON NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oal_order_id` (`order_id`),
  KEY `idx_oal_action_type` (`action_type`),
  KEY `idx_oal_created_at` (`created_at`),
  KEY `idx_oal_admin_id` (`admin_id`),
  CONSTRAINT `fk_oal_order_id`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oal_admin_id`
    FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit trail for order governance and financial control actions';
