-- 999_schema_alignment.sql
-- Idempotent release stabilization alignment for refund/accounting columns.

SET @schema_name := DATABASE();

-- refund_transactions.processed_amount
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'refund_transactions'
      AND COLUMN_NAME = 'processed_amount'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE refund_transactions ADD COLUMN processed_amount DECIMAL(12,2) NULL AFTER approved_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- refund_transactions.refund_status
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'refund_transactions'
      AND COLUMN_NAME = 'refund_status'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE refund_transactions ADD COLUMN refund_status VARCHAR(40) NOT NULL DEFAULT ''pending_approval'' AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- refund_transactions.refund_closed_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'refund_transactions'
      AND COLUMN_NAME = 'refund_closed_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE refund_transactions ADD COLUMN refund_closed_at DATETIME NULL AFTER processed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- financial_transactions.transaction_source
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'financial_transactions'
      AND COLUMN_NAME = 'transaction_source'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE financial_transactions ADD COLUMN transaction_source VARCHAR(80) NULL AFTER source_event',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- financial_transactions.linked_refund_id
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'financial_transactions'
      AND COLUMN_NAME = 'linked_refund_id'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE financial_transactions ADD COLUMN linked_refund_id BIGINT UNSIGNED NULL AFTER reference_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- index for linked_refund_id
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'financial_transactions'
      AND INDEX_NAME = 'idx_financial_transactions_linked_refund'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE financial_transactions ADD INDEX idx_financial_transactions_linked_refund (linked_refund_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- foreign key for linked_refund_id -> refund_transactions.id
SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'financial_transactions'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
      AND CONSTRAINT_NAME = 'fk_financial_transactions_refund'
);
SET @target_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'refund_transactions'
      AND COLUMN_NAME = 'id'
);
SET @sql := IF(
    @fk_exists = 0 AND @target_exists > 0,
    'ALTER TABLE financial_transactions ADD CONSTRAINT fk_financial_transactions_refund FOREIGN KEY (linked_refund_id) REFERENCES refund_transactions(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
