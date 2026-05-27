-- Serverbyt production sync
-- Generated from verified local-vs-production diff on 2026-05-26
-- Local is master source of truth.
-- Safety policy:
--   * no DROP statements
--   * no DELETE statements
--   * no destructive ALTER TYPE statements
--   * only additive schema alignment for items missing on production

SET NAMES utf8mb4;

-- Production is currently missing this local-master table.
CREATE TABLE IF NOT EXISTS crm_customer_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  tag_key VARCHAR(60) NOT NULL,
  tagged_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_crm_customer_tag (user_id, tag_key),
  KEY idx_crm_customer_tags_user (user_id),
  KEY fk_crm_customer_tags_admin (tagged_by_admin_id),
  CONSTRAINT fk_crm_customer_tags_admin FOREIGN KEY (tagged_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT fk_crm_customer_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Production is currently missing this local-master table.
CREATE TABLE IF NOT EXISTS order_production_plan (
  order_id BIGINT UNSIGNED NOT NULL,
  is_excluded TINYINT(1) NOT NULL DEFAULT 0,
  override_slot DATETIME NULL,
  override_reason VARCHAR(255) NULL,
  override_updated_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  KEY idx_production_plan_excluded (is_excluded),
  KEY idx_production_plan_override_slot (override_slot),
  CONSTRAINT fk_production_plan_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Production is currently missing this local-master table.
CREATE TABLE IF NOT EXISTS order_production_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('exclude','include','override_slot','clear_override') NOT NULL,
  event_note VARCHAR(500) NULL,
  changed_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_production_audit_order (order_id),
  KEY idx_production_audit_created (created_at),
  KEY fk_production_audit_admin (changed_by_admin_id),
  CONSTRAINT fk_production_audit_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT fk_production_audit_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- No additive column or index drift was detected on already-shared production tables.
-- Existing production-only tables/data are intentionally preserved.
