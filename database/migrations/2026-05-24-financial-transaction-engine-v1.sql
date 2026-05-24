-- Financial Transaction Engine v1 foundation

CREATE TABLE IF NOT EXISTS ledger_accounts (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  account_code VARCHAR(64) NOT NULL,
  account_name VARCHAR(160) NOT NULL,
  account_type ENUM('asset','liability','equity','revenue','contra_revenue','expense') NOT NULL,
  normal_side ENUM('debit','credit') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ledger_accounts_code (account_code),
  KEY idx_ledger_accounts_type (account_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_transactions (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  transaction_type VARCHAR(64) NOT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  source_event VARCHAR(80) NOT NULL,
  source_reference VARCHAR(120) NULL,
  idempotency_key VARCHAR(190) NOT NULL,
  payment_mode VARCHAR(32) NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('posted','failed','reversed') NOT NULL DEFAULT 'posted',
  narration TEXT NULL,
  metadata_json LONGTEXT NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_by_name VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_financial_transactions_idempotency (idempotency_key),
  KEY idx_financial_transactions_ref (reference_type, reference_id),
  KEY idx_financial_transactions_type_time (transaction_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transaction_batches (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  financial_transaction_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(80) NOT NULL,
  source_module VARCHAR(80) NOT NULL,
  source_reference VARCHAR(120) NULL,
  debit_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('posted','failed','reversed') NOT NULL DEFAULT 'posted',
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_transaction_batches_number (batch_number),
  KEY idx_transaction_batches_tx (financial_transaction_id),
  CONSTRAINT fk_transaction_batches_tx FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS general_ledger_entries (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  batch_id BIGINT UNSIGNED NOT NULL,
  financial_transaction_id BIGINT UNSIGNED NOT NULL,
  line_number INT NOT NULL,
  account_code VARCHAR(64) NOT NULL,
  account_name VARCHAR(160) NOT NULL,
  debit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  credit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_mode VARCHAR(32) NULL,
  narration TEXT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  created_by_admin_id BIGINT UNSIGNED NULL,
  created_by_name VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gl_entries_batch_line (batch_id, line_number),
  KEY idx_gl_entries_account_time (account_code, created_at),
  KEY idx_gl_entries_reference (reference_type, reference_id),
  KEY idx_gl_entries_tx (financial_transaction_id),
  CONSTRAINT fk_gl_entries_batch FOREIGN KEY (batch_id) REFERENCES transaction_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_gl_entries_tx FOREIGN KEY (financial_transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_audit_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  financial_transaction_id BIGINT UNSIGNED NULL,
  batch_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  actor_admin_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(120) NULL,
  source_module VARCHAR(80) NOT NULL,
  source_reference VARCHAR(120) NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_financial_audit_tx (financial_transaction_id, created_at),
  KEY idx_financial_audit_batch (batch_id, created_at),
  KEY idx_financial_audit_event (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO ledger_accounts (account_code, account_name, account_type, normal_side, is_active)
VALUES
  ('CASH_ON_HAND', 'Cash on Hand', 'asset', 'debit', 1),
  ('BANK_CLEARING', 'Bank / UPI Clearing', 'asset', 'debit', 1),
  ('ACCOUNTS_RECEIVABLE', 'Accounts Receivable', 'asset', 'debit', 1),
  ('CUSTOMER_ADVANCES', 'Customer Advances', 'liability', 'credit', 1),
  ('SALES_REVENUE', 'Sales Revenue', 'revenue', 'credit', 1),
  ('SALES_REFUNDS', 'Sales Returns and Refunds', 'contra_revenue', 'debit', 1)
ON DUPLICATE KEY UPDATE
  account_name = VALUES(account_name),
  account_type = VALUES(account_type),
  normal_side = VALUES(normal_side),
  is_active = VALUES(is_active),
  updated_at = CURRENT_TIMESTAMP;
