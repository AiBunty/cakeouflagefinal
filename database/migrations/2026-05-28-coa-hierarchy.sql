-- Migration: 2026-05-28-coa-hierarchy.sql
-- Adds Chart-of-Accounts hierarchy to ledger_accounts:
--   • account_number    — numeric CoA code (1xxx=Assets, 2xxx=Liabilities, 3xxx=Equity,
--                         4xxx=Revenue, 5xxx=Expenses)
--   • parent_account_id — self-referencing FK for tree structure
--   • account_group     — human-readable group label
-- Seeds 5 root group rows and links all existing accounts.
-- Safe to re-run (ON DUPLICATE KEY / IF NOT EXISTS).

-- ── Schema additions ──────────────────────────────────────────────────────────

ALTER TABLE ledger_accounts
  ADD COLUMN IF NOT EXISTS account_number     VARCHAR(10)   NULL     COMMENT 'CoA numeric code e.g. 1100'
    AFTER account_code,
  ADD COLUMN IF NOT EXISTS parent_account_id  BIGINT UNSIGNED NULL   COMMENT 'Self-FK for CoA tree; NULL = root group'
    AFTER account_name,
  ADD COLUMN IF NOT EXISTS account_group      VARCHAR(80)   NULL     COMMENT 'Top-level group: Assets/Liabilities/Equity/Revenue/Expenses'
    AFTER parent_account_id;

-- Unique index on account_number (allow NULL for legacy rows)
ALTER TABLE ledger_accounts
  ADD INDEX IF NOT EXISTS idx_la_account_number  (account_number),
  ADD INDEX IF NOT EXISTS idx_la_parent          (parent_account_id);

-- FK self-reference (add only if absent)
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME         = 'ledger_accounts'
    AND CONSTRAINT_NAME    = 'fk_la_parent_account'
    AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
);
SET @fk_sql := IF(@fk_exists = 0,
  'ALTER TABLE ledger_accounts
     ADD CONSTRAINT fk_la_parent_account
     FOREIGN KEY (parent_account_id)
     REFERENCES ledger_accounts(id)
     ON DELETE SET NULL',
  'SELECT ''fk_la_parent_account already exists, skipping'''
);
PREPARE fk_stmt FROM @fk_sql; EXECUTE fk_stmt; DEALLOCATE PREPARE fk_stmt;

-- ── Seed root group headers ───────────────────────────────────────────────────
-- These are virtual group nodes (no debit/credit entries posted directly to them).

INSERT INTO ledger_accounts
  (account_code, account_number, account_name, account_type, normal_side, account_group, is_active)
VALUES
  ('GROUP_ASSETS',       '1000', 'Assets',      'asset',     'debit',  'Assets',      1),
  ('GROUP_LIABILITIES',  '2000', 'Liabilities', 'liability', 'credit', 'Liabilities', 1),
  ('GROUP_EQUITY',       '3000', 'Equity',      'equity',    'credit', 'Equity',      1),
  ('GROUP_REVENUE',      '4000', 'Revenue',     'revenue',   'credit', 'Revenue',     1),
  ('GROUP_EXPENSES',     '5000', 'Expenses',    'expense',   'debit',  'Expenses',    1)
ON DUPLICATE KEY UPDATE
  account_number = VALUES(account_number),
  account_name   = VALUES(account_name),
  account_group  = VALUES(account_group),
  updated_at     = CURRENT_TIMESTAMP;

-- ── Link existing accounts to their parent groups ────────────────────────────

-- Assets
UPDATE ledger_accounts a
  JOIN ledger_accounts g ON g.account_code = 'GROUP_ASSETS'
SET a.parent_account_id = g.id,
    a.account_group     = 'Assets',
    a.account_number    = CASE a.account_code
      WHEN 'CASH_ON_HAND'          THEN '1100'
      WHEN 'BANK_CLEARING'         THEN '1110'
      WHEN 'ACCOUNTS_RECEIVABLE'   THEN '1200'
      ELSE a.account_number
    END
WHERE a.account_code IN ('CASH_ON_HAND','BANK_CLEARING','ACCOUNTS_RECEIVABLE');

-- Liabilities
UPDATE ledger_accounts a
  JOIN ledger_accounts g ON g.account_code = 'GROUP_LIABILITIES'
SET a.parent_account_id = g.id,
    a.account_group     = 'Liabilities',
    a.account_number    = CASE a.account_code
      WHEN 'CUSTOMER_ADVANCES'      THEN '2100'
      WHEN 'CUSTOMER_CREDIT_WALLET' THEN '2110'
      ELSE a.account_number
    END
WHERE a.account_code IN ('CUSTOMER_ADVANCES','CUSTOMER_CREDIT_WALLET');

-- Revenue
UPDATE ledger_accounts a
  JOIN ledger_accounts g ON g.account_code = 'GROUP_REVENUE'
SET a.parent_account_id = g.id,
    a.account_group     = 'Revenue',
    a.account_number    = CASE a.account_code
      WHEN 'SALES_REVENUE'              THEN '4100'
      WHEN 'SALES_ADJUSTMENT_REVENUE'   THEN '4110'
      WHEN 'SALES_REFUNDS'              THEN '4200'
      WHEN 'SALES_DISCOUNT_CONTRA'      THEN '4210'
      ELSE a.account_number
    END
WHERE a.account_code IN (
  'SALES_REVENUE','SALES_ADJUSTMENT_REVENUE','SALES_REFUNDS','SALES_DISCOUNT_CONTRA'
);

-- Expenses
UPDATE ledger_accounts a
  JOIN ledger_accounts g ON g.account_code = 'GROUP_EXPENSES'
SET a.parent_account_id = g.id,
    a.account_group     = 'Expenses',
    a.account_number    = CASE a.account_code
      WHEN 'DISCOUNT_EXPENSE'           THEN '5100'
      WHEN 'BAD_DEBT_EXPENSE'           THEN '5110'
      WHEN 'SALES_ADJUSTMENT_EXPENSE'   THEN '5120'
      ELSE a.account_number
    END
WHERE a.account_code IN (
  'DISCOUNT_EXPENSE','BAD_DEBT_EXPENSE','SALES_ADJUSTMENT_EXPENSE'
);
