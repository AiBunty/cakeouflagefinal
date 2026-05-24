-- Ensure legacy-required tables exist for production parity.
-- MySQL 8 compatible.

CREATE TABLE IF NOT EXISTS media_center (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  media_type ENUM('image','video') NOT NULL DEFAULT 'image',
  file_path VARCHAR(255) NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  alt_text VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_media_center_active (is_active),
  KEY idx_media_center_type (media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homepage_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section_key VARCHAR(64) NOT NULL,
  media_type ENUM('image','video') NOT NULL DEFAULT 'image',
  media_url VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_homepage_media_section (section_key),
  KEY idx_homepage_media_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
