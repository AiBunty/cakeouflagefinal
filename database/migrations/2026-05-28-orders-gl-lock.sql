-- Migration: 2026-05-28-orders-gl-lock.sql
-- Adds GL posting lock columns and order revision tracking to the orders table.
-- gl_posted_at / gl_transaction_id: prevent double-posting (SAP posting-block pattern)
-- is_revised / current_revision_no / revised_grand_total: support order amendment flow.
-- Safe to re-run (ALTER TABLE ADD COLUMN IF NOT EXISTS).

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS gl_posted_at          DATETIME         NULL     COMMENT 'Timestamp of first successful GL posting; NULL = not yet posted'
    AFTER refunded_at,
  ADD COLUMN IF NOT EXISTS gl_transaction_id     BIGINT UNSIGNED  NULL     COMMENT 'FK to financial_transactions.id of the first posting'
    AFTER gl_posted_at,
  ADD COLUMN IF NOT EXISTS is_revised            TINYINT(1)       NOT NULL DEFAULT 0  COMMENT '1 = order has been revised at least once'
    AFTER gl_transaction_id,
  ADD COLUMN IF NOT EXISTS current_revision_no   INT UNSIGNED     NOT NULL DEFAULT 0  COMMENT 'Latest revision sequence number; 0 = original'
    AFTER is_revised,
  ADD COLUMN IF NOT EXISTS revised_grand_total   DECIMAL(12,2)    NULL     COMMENT 'Current canonical order total after revisions; NULL = use grand_total'
    AFTER current_revision_no;

-- Indexes
ALTER TABLE orders
  ADD INDEX IF NOT EXISTS idx_orders_gl_posted    (gl_posted_at),
  ADD INDEX IF NOT EXISTS idx_orders_is_revised   (is_revised);
