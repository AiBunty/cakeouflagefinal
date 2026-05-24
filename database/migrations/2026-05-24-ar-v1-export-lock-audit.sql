-- AR V1 export lock monthly audit archive

CREATE TABLE IF NOT EXISTS ar_export_lock_audit (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  archive_month CHAR(7) NOT NULL,
  lock_token VARCHAR(64) NOT NULL,
  source VARCHAR(64) NOT NULL,
  event_type ENUM('issued','validated','exported','failed','expired','missing','invalidated') NOT NULL,
  variant VARCHAR(64) NOT NULL,
  format VARCHAR(32) NOT NULL,
  fingerprint CHAR(64) NULL,
  filters_json LONGTEXT NULL,
  issued_by_admin_id BIGINT UNSIGNED NULL,
  issued_by_name VARCHAR(120) NULL,
  request_ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ar_export_audit_month (archive_month, created_at),
  INDEX idx_ar_export_audit_token (lock_token, created_at),
  INDEX idx_ar_export_audit_event (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
