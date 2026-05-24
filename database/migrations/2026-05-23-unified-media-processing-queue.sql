-- Unified media async processing ledger
CREATE TABLE IF NOT EXISTS media_processing_queue (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  module_name VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  original_path VARCHAR(255) NOT NULL,
  optimized_path VARCHAR(255) NOT NULL,
  processing_status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error VARCHAR(260) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_media_processing_status (processing_status, created_at),
  INDEX idx_media_processing_entity (entity_type, entity_id),
  INDEX idx_media_processing_module (module_name, created_at)
) ENGINE=InnoDB;