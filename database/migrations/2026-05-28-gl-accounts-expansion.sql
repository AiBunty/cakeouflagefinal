-- Migration: 2026-05-28-gl-accounts-expansion.sql
-- Adds expense, contra-revenue, adjustment and wallet ledger accounts.
-- v1: DISCOUNT_EXPENSE, BAD_DEBT_EXPENSE
-- v2: SALES_DISCOUNT_CONTRA (contra-revenue for gross method coupon accounting),
--     SALES_ADJUSTMENT_REVENUE (order upgrade revenue),
--     SALES_ADJUSTMENT_EXPENSE (order downgrade expense),
--     CUSTOMER_CREDIT_WALLET (store-credit liability)
-- Safe to run multiple times (ON DUPLICATE KEY UPDATE).

INSERT INTO ledger_accounts (account_code, account_name, account_type, normal_side, is_active)
VALUES
  ('DISCOUNT_EXPENSE',          'Discount & Coupon Expense',         'expense',        'debit',   1),
  ('BAD_DEBT_EXPENSE',          'Bad Debt Expense',                  'expense',        'debit',   1),
  ('SALES_DISCOUNT_CONTRA',     'Sales Discount Contra',             'contra_revenue', 'debit',   1),
  ('SALES_ADJUSTMENT_REVENUE',  'Sales Adjustment Revenue (Upgrade)','revenue',        'credit',  1),
  ('SALES_ADJUSTMENT_EXPENSE',  'Sales Adjustment Expense (Downgrade)','expense',      'debit',   1),
  ('CUSTOMER_CREDIT_WALLET',    'Customer Credit Wallet',            'liability',      'credit',  1)
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  normal_side  = VALUES(normal_side),
  is_active    = VALUES(is_active),
  updated_at   = CURRENT_TIMESTAMP;
