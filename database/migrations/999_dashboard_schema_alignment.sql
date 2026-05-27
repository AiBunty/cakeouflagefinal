-- 999_dashboard_schema_alignment.sql
-- Idempotent schema alignment for dashboard/collection/report compatibility.

SET @schema_name := DATABASE();

-- Ensure order_status enum supports all workflow states referenced by services/UI.
ALTER TABLE orders
  MODIFY COLUMN order_status ENUM(
    'pending_payment',
    'payment_under_review',
    'awaiting_confirmation',
    'confirmed',
    'preparing',
    'ready_for_pickup',
    'out_for_delivery',
    'delivered',
    'completed',
    'cancelled',
    'refund_requested',
    'refunded',
    'partially_refunded',
    'fully_refunded',
    'rejected'
  ) NOT NULL DEFAULT 'pending_payment';

-- orders.followup_status
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'followup_status'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN followup_status VARCHAR(40) NOT NULL DEFAULT ''no_reminder'' AFTER advance_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.followup_count
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'followup_count'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN followup_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER followup_status',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.collection_priority
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'collection_priority'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN collection_priority VARCHAR(20) NOT NULL DEFAULT ''normal'' AFTER followup_count',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.collection_note
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'collection_note'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN collection_note TEXT NULL AFTER collection_priority',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.last_followup_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'last_followup_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN last_followup_at DATETIME NULL AFTER collection_note',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.next_followup_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'next_followup_at'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN next_followup_at DATETIME NULL AFTER last_followup_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.advance_received_amount
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'advance_received_amount'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN advance_received_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER advance_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.net_collected_amount
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'net_collected_amount'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN net_collected_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_collected',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.balance_due_amount
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'balance_due_amount'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN balance_due_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER net_collected_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.collection_status
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'collection_status'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN collection_status VARCHAR(40) NOT NULL DEFAULT ''payment_pending'' AFTER balance_due_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders.total_refunded
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'total_refunded'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE orders ADD COLUMN total_refunded DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER refund_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Helpful indexes.
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_orders_followup_status'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_followup_status (followup_status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'idx_orders_collection_priority'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_collection_priority (collection_priority)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
