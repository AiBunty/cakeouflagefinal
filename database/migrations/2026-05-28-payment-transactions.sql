-- Migration: 2026-05-28-payment-transactions.sql
-- Dedicated payment ledger table to support split payments (e.g. ₹500 cash + ₹500 UPI)
-- and multi-payment-per-order tracking with individual GL references.

CREATE TABLE IF NOT EXISTS payment_transactions (
  id                    BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  order_id              BIGINT UNSIGNED  NOT NULL            COMMENT 'FK to orders.id',
  payment_method        ENUM(
                          'cash','upi','bank_transfer',
                          'pos_card','payment_link','store_credit'
                        )                NOT NULL            COMMENT 'How this portion was paid',
  amount                DECIMAL(12,2)    NOT NULL            COMMENT 'Amount of this payment leg',
  reference_code        VARCHAR(120)     NULL                COMMENT 'UTR / transaction ID / receipt number',
  status                ENUM('pending','verified','rejected')
                                         NOT NULL DEFAULT 'pending',
  gl_transaction_id     BIGINT UNSIGNED  NULL                COMMENT 'FK to financial_transactions.id after GL posting',
  verified_by_admin_id  BIGINT UNSIGNED  NULL,
  verified_at           DATETIME         NULL,
  notes                 TEXT             NULL,
  created_by_admin_id   BIGINT UNSIGNED  NULL,
  created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_pt_order        (order_id),
  KEY idx_pt_status       (status),
  KEY idx_pt_gl_tx        (gl_transaction_id),
  KEY idx_pt_created_at   (created_at),

  CONSTRAINT fk_pt_order
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pt_verified_by
    FOREIGN KEY (verified_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT fk_pt_created_by
    FOREIGN KEY (created_by_admin_id)  REFERENCES admins(id) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
