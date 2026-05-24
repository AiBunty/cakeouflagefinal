-- AR V1 foundation: order-level receivable fields + follow-up timeline log

ALTER TABLE orders
  ADD COLUMN gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER grand_total,
  ADD COLUMN advance_received_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER advance_amount,
  ADD COLUMN net_collected_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER advance_received_amount,
  ADD COLUMN balance_due_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER net_collected_amount,
  ADD COLUMN collection_due_date DATE NULL AFTER scheduled_slot,
  ADD COLUMN collection_status ENUM('fully_paid','advance_paid','payment_pending','overdue','refunded') NOT NULL DEFAULT 'payment_pending' AFTER balance_due_amount,
  ADD COLUMN followup_status ENUM('no_reminder','reminder_sent','customer_responded','payment_promised','escalated','settled') NOT NULL DEFAULT 'no_reminder' AFTER collection_status,
  ADD COLUMN last_followup_at DATETIME NULL AFTER followup_status,
  ADD COLUMN next_followup_at DATETIME NULL AFTER last_followup_at,
  ADD COLUMN followup_count INT NOT NULL DEFAULT 0 AFTER next_followup_at,
  ADD COLUMN collection_priority ENUM('normal','high') NOT NULL DEFAULT 'normal' AFTER followup_count,
  ADD COLUMN collection_note TEXT NULL AFTER collection_priority,
  ADD COLUMN last_collection_at DATETIME NULL AFTER collection_note;

ALTER TABLE orders
  ADD INDEX idx_orders_ar_balance (balance_due_amount, collection_status),
  ADD INDEX idx_orders_ar_due (collection_due_date, balance_due_amount),
  ADD INDEX idx_orders_followup_queue (followup_status, next_followup_at),
  ADD INDEX idx_orders_channel_outstanding (payment_method, balance_due_amount);

CREATE TABLE IF NOT EXISTS collection_followup_logs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(160) NULL,
  customer_phone VARCHAR(32) NULL,
  action_type ENUM('reminder_whatsapp','reminder_email','followup_done','internal_note','payment_promised','escalated','payment_collected') NOT NULL,
  followup_status ENUM('no_reminder','reminder_sent','customer_responded','payment_promised','escalated','settled') NOT NULL DEFAULT 'no_reminder',
  message_text TEXT NULL,
  metadata_json JSON NULL,
  actor_admin_id BIGINT UNSIGNED NULL,
  actor_name VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_collection_followup_order (order_id, created_at),
  INDEX idx_collection_followup_status (followup_status, created_at),
  CONSTRAINT fk_collection_followup_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Backfill AR snapshots from current order state
UPDATE orders
SET
  gross_amount = COALESCE(NULLIF(gross_amount, 0), grand_total, 0),
  advance_received_amount = LEAST(COALESCE(advance_amount, 0), COALESCE(grand_total, 0)),
  net_collected_amount = GREATEST(
    LEAST(
      CASE
        WHEN payment_status IN ('paid', 'partially_refunded', 'refunded') THEN COALESCE(grand_total, 0)
        WHEN COALESCE(advance_amount, 0) > 0 THEN COALESCE(advance_amount, 0)
        ELSE 0
      END,
      COALESCE(grand_total, 0)
    ) - COALESCE(total_refunded, 0),
    0
  ),
  balance_due_amount = GREATEST(
    CASE
      WHEN payment_status IN ('refunded', 'partially_refunded') OR order_status IN ('fully_refunded', 'partially_refunded', 'cancelled', 'rejected') THEN 0
      ELSE COALESCE(grand_total, 0) - LEAST(
        CASE
          WHEN payment_status IN ('paid', 'partially_refunded', 'refunded') THEN COALESCE(grand_total, 0)
          WHEN COALESCE(advance_amount, 0) > 0 THEN COALESCE(advance_amount, 0)
          ELSE 0
        END,
        COALESCE(grand_total, 0)
      )
    END,
    0
  ),
  collection_due_date = COALESCE(collection_due_date, DATE(scheduled_slot), DATE(created_at)),
  collection_status = CASE
    WHEN payment_status IN ('refunded', 'partially_refunded') OR order_status IN ('fully_refunded', 'partially_refunded') THEN 'refunded'
    WHEN GREATEST(
      CASE
        WHEN payment_status IN ('refunded', 'partially_refunded') OR order_status IN ('fully_refunded', 'partially_refunded', 'cancelled', 'rejected') THEN 0
        ELSE COALESCE(grand_total, 0) - LEAST(
          CASE
            WHEN payment_status IN ('paid', 'partially_refunded', 'refunded') THEN COALESCE(grand_total, 0)
            WHEN COALESCE(advance_amount, 0) > 0 THEN COALESCE(advance_amount, 0)
            ELSE 0
          END,
          COALESCE(grand_total, 0)
        )
      END,
      0
    ) > 0 AND COALESCE(collection_due_date, DATE(scheduled_slot), DATE(created_at)) < CURDATE() THEN 'overdue'
    WHEN GREATEST(
      CASE
        WHEN payment_status IN ('refunded', 'partially_refunded') OR order_status IN ('fully_refunded', 'partially_refunded', 'cancelled', 'rejected') THEN 0
        ELSE COALESCE(grand_total, 0) - LEAST(
          CASE
            WHEN payment_status IN ('paid', 'partially_refunded', 'refunded') THEN COALESCE(grand_total, 0)
            WHEN COALESCE(advance_amount, 0) > 0 THEN COALESCE(advance_amount, 0)
            ELSE 0
          END,
          COALESCE(grand_total, 0)
        )
      END,
      0
    ) > 0 AND LEAST(COALESCE(advance_amount, 0), COALESCE(grand_total, 0)) > 0 THEN 'advance_paid'
    WHEN GREATEST(
      CASE
        WHEN payment_status IN ('refunded', 'partially_refunded') OR order_status IN ('fully_refunded', 'partially_refunded', 'cancelled', 'rejected') THEN 0
        ELSE COALESCE(grand_total, 0) - LEAST(
          CASE
            WHEN payment_status IN ('paid', 'partially_refunded', 'refunded') THEN COALESCE(grand_total, 0)
            WHEN COALESCE(advance_amount, 0) > 0 THEN COALESCE(advance_amount, 0)
            ELSE 0
          END,
          COALESCE(grand_total, 0)
        )
      END,
      0
    ) > 0 THEN 'payment_pending'
    ELSE 'fully_paid'
  END;
