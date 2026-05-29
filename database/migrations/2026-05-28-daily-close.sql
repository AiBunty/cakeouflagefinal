-- Migration: 2026-05-28-daily-close.sql
-- Accounting day-close log (Tally day-book / SAP period-close equivalent).
-- Each row represents one closed business date with totals frozen at close time.
-- is_locked = 1 prevents new GL postings for that date (enforced in PHP layer).
-- Reopen is allowed for super_admin only; every reopen is audit-logged.

CREATE TABLE IF NOT EXISTS accounting_close_log (
  id                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  close_date            DATE             NOT NULL                COMMENT 'The business date being closed',
  closed_by_admin_id    BIGINT UNSIGNED  NULL,
  cash_total            DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Total cash collected on this date',
  bank_total            DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Total bank/UPI collected on this date',
  net_revenue           DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Net revenue = SALES_REVENUE - discounts - refunds',
  refunds_total         DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Total refunds processed on this date',
  discounts_total       DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Total coupon/discount contra on this date',
  upgrade_revenue       DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Order upgrade adjustment revenue on this date',
  downgrade_expense     DECIMAL(12,2)    NOT NULL DEFAULT 0      COMMENT 'Order downgrade adjustment expense on this date',
  is_locked             TINYINT(1)       NOT NULL DEFAULT 1      COMMENT '1 = GL postings blocked for this date',
  notes                 TEXT             NULL,
  closed_at             DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_reopened_at      DATETIME         NULL                    COMMENT 'Set when super_admin unlocks',
  reopened_by_admin_id  BIGINT UNSIGNED  NULL,

  PRIMARY KEY (id),
  UNIQUE KEY  uq_acl_close_date    (close_date),
  KEY         idx_acl_is_locked    (is_locked, close_date),

  CONSTRAINT fk_acl_closed_by
    FOREIGN KEY (closed_by_admin_id)   REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT fk_acl_reopened_by
    FOREIGN KEY (reopened_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
