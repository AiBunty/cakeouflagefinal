-- Migration: 2026-05-28-gl-engine-v2.sql
-- ERP-grade upgrade to the GL engine:
--   • business_date       — operational posting date (bakery closes after midnight)
--   • source_channel      — website / whatsapp / walkin / byoc / corporate / manual
--   • is_reversal         — flags entries that mirror-reverse a prior transaction
--   • reversal_of_transaction_id — FK back to the original being reversed
--   • review_required     — flag for high-value entries pending admin review
--   • entry_type          — GL line classification: sale/refund/advance/settlement/discount/writeoff/adjustment/reversal
--   • running_balance_after — per-account running balance snapshot for fast statements
-- All columns are ADD IF NOT EXISTS-safe via separate procedures.
-- Safe to re-run.

-- ── financial_transactions additions ─────────────────────────────────────────

ALTER TABLE financial_transactions
  ADD COLUMN IF NOT EXISTS business_date                DATE              NULL     COMMENT 'Operational posting date; may differ from created_at timestamp'
    AFTER source_reference,
  ADD COLUMN IF NOT EXISTS source_channel               VARCHAR(32)       NULL     COMMENT 'website|whatsapp|walkin|byoc|corporate|manual'
    AFTER business_date,
  ADD COLUMN IF NOT EXISTS is_reversal                  TINYINT(1)        NOT NULL DEFAULT 0  COMMENT '1 = this entry reverses another transaction'
    AFTER source_channel,
  ADD COLUMN IF NOT EXISTS reversal_of_transaction_id   BIGINT UNSIGNED   NULL     COMMENT 'FK to financial_transactions.id being reversed'
    AFTER is_reversal,
  ADD COLUMN IF NOT EXISTS review_required              TINYINT(1)        NOT NULL DEFAULT 0  COMMENT '1 = high-value entry pending admin confirmation'
    AFTER reversal_of_transaction_id;

-- Backfill business_date for existing rows (use date portion of created_at)
UPDATE financial_transactions
SET business_date = DATE(created_at)
WHERE business_date IS NULL;

-- Make business_date NOT NULL after backfill
ALTER TABLE financial_transactions
  MODIFY COLUMN business_date DATE NOT NULL DEFAULT (CURDATE())
    COMMENT 'Operational posting date; may differ from created_at timestamp';

-- FK for reversal self-reference (add only if it doesn't already exist)
SET @constraint_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME         = 'financial_transactions'
    AND CONSTRAINT_NAME    = 'fk_ft_reversal_of'
    AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
);
SET @sql := IF(@constraint_exists = 0,
  'ALTER TABLE financial_transactions
     ADD CONSTRAINT fk_ft_reversal_of
     FOREIGN KEY (reversal_of_transaction_id)
     REFERENCES financial_transactions(id)
     ON DELETE SET NULL',
  'SELECT ''fk_ft_reversal_of already exists, skipping'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes
ALTER TABLE financial_transactions
  ADD INDEX IF NOT EXISTS idx_ft_business_date    (business_date),
  ADD INDEX IF NOT EXISTS idx_ft_source_channel   (source_channel, business_date),
  ADD INDEX IF NOT EXISTS idx_ft_is_reversal      (is_reversal);

-- ── general_ledger_entries additions ─────────────────────────────────────────

ALTER TABLE general_ledger_entries
  ADD COLUMN IF NOT EXISTS entry_type             VARCHAR(32)   NULL     COMMENT 'sale|refund|advance|settlement|discount|writeoff|adjustment|reversal'
    AFTER narration,
  ADD COLUMN IF NOT EXISTS running_balance_after  DECIMAL(15,2) NULL     COMMENT 'Per-account running balance after this line; + = debit-heavy'
    AFTER entry_type;

-- Index for account balance lookups
ALTER TABLE general_ledger_entries
  ADD INDEX IF NOT EXISTS idx_gle_account_date (account_code, created_at),
  ADD INDEX IF NOT EXISTS idx_gle_entry_type   (entry_type);
