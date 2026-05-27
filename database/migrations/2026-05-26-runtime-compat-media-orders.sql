-- Runtime compatibility hardening for MariaDB + media pipeline
-- 1) Avoid ENUM truncation on evolving order statuses.
-- 2) Extend media_assets with professional transcode metadata fields.

ALTER TABLE orders
  MODIFY COLUMN order_status VARCHAR(64) NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS media_assets (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  original_path VARCHAR(255) NOT NULL,
  canonical_path VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  media_type ENUM('image','video') NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  conversion_status ENUM('ready','queued','processing','failed') NOT NULL DEFAULT 'ready',
  conversion_error VARCHAR(260) NULL,
  version_token VARCHAR(40) NOT NULL,
  uploaded_by_admin_id BIGINT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_assets_canonical (canonical_path),
  INDEX idx_media_assets_original (original_path),
  INDEX idx_media_assets_status (conversion_status, updated_at),
  FOREIGN KEY (uploaded_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE media_assets
  ADD COLUMN IF NOT EXISTS optimized_path VARCHAR(255) NULL AFTER canonical_path,
  ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(255) NULL AFTER optimized_path,
  ADD COLUMN IF NOT EXISTS transcoding_status VARCHAR(32) NULL AFTER conversion_status,
  ADD COLUMN IF NOT EXISTS duration_seconds DECIMAL(10,2) NULL AFTER file_size,
  ADD COLUMN IF NOT EXISTS resolution VARCHAR(32) NULL AFTER duration_seconds;

UPDATE media_assets
SET optimized_path = canonical_path
WHERE (optimized_path IS NULL OR optimized_path = '')
  AND canonical_path IS NOT NULL
  AND canonical_path <> '';

UPDATE media_assets
SET transcoding_status = CASE
    WHEN conversion_status = 'ready' THEN 'optimized'
    ELSE conversion_status
END
WHERE transcoding_status IS NULL OR transcoding_status = '';
