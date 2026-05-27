CREATE TABLE IF NOT EXISTS admin_action_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(120) NOT NULL,
  target_type VARCHAR(120) NOT NULL,
  target_id BIGINT NULL,
  entity_type VARCHAR(120) NULL,
  entity_id VARCHAR(60) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_action_logs_admin_id (admin_id),
  INDEX idx_admin_action_logs_target (target_type, target_id),
  INDEX idx_admin_action_logs_created_at (created_at),
  CONSTRAINT fk_admin_action_logs_admin
    FOREIGN KEY (admin_id) REFERENCES admins(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
