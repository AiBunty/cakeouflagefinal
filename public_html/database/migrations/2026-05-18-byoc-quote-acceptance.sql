-- BYOC quote acceptance and order source separation

CREATE TABLE IF NOT EXISTS byoc_quotes (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  inquiry_id BIGINT UNSIGNED NOT NULL,
  quote_number VARCHAR(50) NOT NULL UNIQUE,
  quote_subject VARCHAR(180) NOT NULL,
  quote_message TEXT NULL,
  quote_amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('sent','accepted','expired','cancelled') NOT NULL DEFAULT 'sent',
  expires_at DATETIME NULL,
  accepted_at DATETIME NULL,
  order_id BIGINT UNSIGNED NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_byoc_quotes_inquiry (inquiry_id),
  INDEX idx_byoc_quotes_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS byoc_quote_links (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  byoc_quote_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(120) NOT NULL UNIQUE,
  expires_at DATETIME NULL,
  used_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_byoc_quote_links_quote (byoc_quote_id),
  FOREIGN KEY (byoc_quote_id) REFERENCES byoc_quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET @has_order_source := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'orders'
    AND column_name = 'order_source'
);
SET @sql := IF(@has_order_source = 0,
  'ALTER TABLE orders ADD COLUMN order_source ENUM("retail","byoc_quote") NOT NULL DEFAULT "retail" AFTER payment_method',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_byoc_quote_id := (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'orders'
    AND column_name = 'byoc_quote_id'
);
SET @sql := IF(@has_byoc_quote_id = 0,
  'ALTER TABLE orders ADD COLUMN byoc_quote_id BIGINT UNSIGNED NULL AFTER order_source',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_source_index := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'orders'
    AND index_name = 'idx_orders_source'
);
SET @sql := IF(@has_source_index = 0,
  'CREATE INDEX idx_orders_source ON orders(order_source)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_byoc_unique := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'orders'
    AND index_name = 'uq_orders_byoc_quote'
);
SET @sql := IF(@has_byoc_unique = 0,
  'CREATE UNIQUE INDEX uq_orders_byoc_quote ON orders(byoc_quote_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE byoc_quotes ADD CONSTRAINT fk_byoc_quotes_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE;
