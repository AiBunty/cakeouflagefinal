-- Production planning audit trail
-- Safe to run multiple times

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
  CONSTRAINT fk_production_audit_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_production_audit_admin
    FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
