-- Align orders.payment_status enum with Phase 1 accounting requirements.
-- Adds explicit part_paid state while preserving existing finance/reconciliation statuses.

ALTER TABLE orders
  MODIFY COLUMN payment_status ENUM(
    'pending',
    'under_review',
    'paid',
    'part_paid',
    'credit',
    'refund_pending',
    'partially_refunded',
    'refunded',
    'failed',
    'rejected'
  ) NOT NULL DEFAULT 'pending';
