-- Order Destructive Governance v1
-- Archive-first metadata + immutable destructive action logs

SET @has_is_archived := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'is_archived'
);
SET @sql_is_archived := IF(
    @has_is_archived = 0,
    'ALTER TABLE orders ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at',
    'SELECT 1'
);
PREPARE stmt_is_archived FROM @sql_is_archived;
EXECUTE stmt_is_archived;
DEALLOCATE PREPARE stmt_is_archived;

SET @has_archived_at := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'archived_at'
);
SET @sql_archived_at := IF(
    @has_archived_at = 0,
    'ALTER TABLE orders ADD COLUMN archived_at DATETIME NULL AFTER is_archived',
    'SELECT 1'
);
PREPARE stmt_archived_at FROM @sql_archived_at;
EXECUTE stmt_archived_at;
DEALLOCATE PREPARE stmt_archived_at;

SET @has_archived_by := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'archived_by_admin_id'
);
SET @sql_archived_by := IF(
    @has_archived_by = 0,
    'ALTER TABLE orders ADD COLUMN archived_by_admin_id BIGINT UNSIGNED NULL AFTER archived_at',
    'SELECT 1'
);
PREPARE stmt_archived_by FROM @sql_archived_by;
EXECUTE stmt_archived_by;
DEALLOCATE PREPARE stmt_archived_by;

SET @has_archive_reason := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'archive_reason_code'
);
SET @sql_archive_reason := IF(
    @has_archive_reason = 0,
    'ALTER TABLE orders ADD COLUMN archive_reason_code VARCHAR(64) NULL AFTER archived_by_admin_id',
    'SELECT 1'
);
PREPARE stmt_archive_reason FROM @sql_archive_reason;
EXECUTE stmt_archive_reason;
DEALLOCATE PREPARE stmt_archive_reason;

SET @has_archive_notes := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'archive_reason_notes'
);
SET @sql_archive_notes := IF(
    @has_archive_notes = 0,
    'ALTER TABLE orders ADD COLUMN archive_reason_notes TEXT NULL AFTER archive_reason_code',
    'SELECT 1'
);
PREPARE stmt_archive_notes FROM @sql_archive_notes;
EXECUTE stmt_archive_notes;
DEALLOCATE PREPARE stmt_archive_notes;

SET @has_idx_archived := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_archived_state'
);
SET @sql_idx_archived := IF(
    @has_idx_archived = 0,
    'ALTER TABLE orders ADD INDEX idx_orders_archived_state (is_archived, archived_at, order_status)',
    'SELECT 1'
);
PREPARE stmt_idx_archived FROM @sql_idx_archived;
EXECUTE stmt_idx_archived;
DEALLOCATE PREPARE stmt_idx_archived;

CREATE TABLE IF NOT EXISTS order_destructive_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NULL,
    action_type ENUM('archive','restore','force_purge') NOT NULL,
    reason_code VARCHAR(64) NOT NULL,
    reason_notes TEXT NULL,
    financial_impact_level ENUM('none','contains_financial_entries','financial_entries_reversed','financial_entries_purged') NOT NULL DEFAULT 'none',
    requires_delete_password TINYINT(1) NOT NULL DEFAULT 1,
    actor_admin_id BIGINT UNSIGNED NULL,
    actor_role VARCHAR(64) NULL,
    actor_name VARCHAR(120) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    order_snapshot_json LONGTEXT NULL,
    recovery_payload_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_destructive_logs_order (order_id, created_at),
    KEY idx_order_destructive_logs_actor (actor_admin_id, created_at),
    KEY idx_order_destructive_logs_action (action_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
