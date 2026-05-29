-- Migration: 2026-05-28-order-revisions.sql
-- Order revision / amendment log (ERP amendment document pattern).
-- Every change to a confirmed order creates a row here.
-- order_items stays as the LATEST version for production.
-- old_items_snapshot / new_items_snapshot preserve the full history.
-- GL posting is linked via gl_transaction_id.

CREATE TABLE IF NOT EXISTS order_revisions (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id                BIGINT UNSIGNED NOT NULL              COMMENT 'FK to orders.id',
  revision_no             INT UNSIGNED    NOT NULL              COMMENT 'Sequential revision number starting at 1',
  revision_type           ENUM(
                            'upgrade',
                            'downgrade',
                            'topper_addition',
                            'flavor_change',
                            'delivery_change',
                            'customer_request',
                            'admin_adjustment'
                          )               NOT NULL,
  old_grand_total         DECIMAL(12,2)   NOT NULL              COMMENT 'Order total before this revision',
  new_grand_total         DECIMAL(12,2)   NOT NULL              COMMENT 'Order total after this revision',
  difference_amount       DECIMAL(12,2)   NOT NULL              COMMENT 'new_grand_total - old_grand_total; positive = upgrade, negative = downgrade',
  old_items_snapshot      JSON            NULL                  COMMENT 'Serialised order_items rows before revision',
  new_items_snapshot      JSON            NULL                  COMMENT 'Serialised order_items rows after revision (new production version)',
  revision_reason         TEXT            NULL                  COMMENT 'Admin free-text note',
  downgrade_resolution    ENUM('refund','store_credit') NULL   COMMENT 'For downgrade: what was done with the excess amount',
  gl_transaction_id       BIGINT UNSIGNED NULL                  COMMENT 'FK to financial_transactions.id for the adjustment posting',
  revision_status         ENUM('pending','confirmed','cancelled')
                                          NOT NULL DEFAULT 'pending',
  requires_super_approval TINYINT(1)      NOT NULL DEFAULT 0   COMMENT '1 = order was delivered/closed, needs super_admin sign-off',
  created_by_admin_id     BIGINT UNSIGNED NULL,
  approved_by_admin_id    BIGINT UNSIGNED NULL,
  created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY  uq_or_order_revision   (order_id, revision_no),
  KEY         idx_or_order_id        (order_id),
  KEY         idx_or_status          (revision_status),
  KEY         idx_or_created_at      (created_at),
  KEY         idx_or_gl_tx           (gl_transaction_id),

  CONSTRAINT fk_or_order_id
    FOREIGN KEY (order_id)             REFERENCES orders(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_or_created_by
    FOREIGN KEY (created_by_admin_id)  REFERENCES admins(id)   ON DELETE SET NULL,
  CONSTRAINT fk_or_approved_by
    FOREIGN KEY (approved_by_admin_id) REFERENCES admins(id)   ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
