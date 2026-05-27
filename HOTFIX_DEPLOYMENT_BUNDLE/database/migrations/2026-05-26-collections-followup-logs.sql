CREATE TABLE IF NOT EXISTS collection_followup_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(64) NOT NULL,
  followup_status VARCHAR(64) NOT NULL,
  message_text TEXT NULL,
  actor_name VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_collection_followup_logs_order_id (order_id),
  KEY idx_collection_followup_logs_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
