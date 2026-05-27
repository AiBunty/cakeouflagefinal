-- Production planning controls foundation
-- Safe to run multiple times

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
  CONSTRAINT fk_order_production_plan_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
VALUES ('production_default_cutoff_time', '23:45', NULL)
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
VALUES ('production_slot_cutoff_map', '{}', NULL)
ON DUPLICATE KEY UPDATE setting_value = setting_value;
