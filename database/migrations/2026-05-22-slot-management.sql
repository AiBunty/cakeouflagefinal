-- =============================================================================
-- SLOT MANAGEMENT SYSTEM — Production Migration
-- Target: MySQL 8, InnoDB, utf8mb4_unicode_ci
-- Backward-compatible: legacy delivery_time_slots table is UNTOUCHED
-- =============================================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: order_slots
-- Master slot definition catalogue. One row per slot template.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_slots (
  id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,

  -- Slot identity
  slot_type        ENUM('pickup','delivery')  NOT NULL,
  slot_name        VARCHAR(120)               NOT NULL COMMENT 'Internal name e.g. "Morning Pickup"',
  slot_label       VARCHAR(120)               NOT NULL COMMENT 'Customer-visible label e.g. "10:00 AM – 12:00 PM"',

  -- Time window
  start_time       TIME NOT NULL,
  end_time         TIME NOT NULL,

  -- Capacity & scheduling
  max_orders         SMALLINT UNSIGNED NOT NULL DEFAULT 10   COMMENT 'Baseline daily order cap',
  prep_buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120 COMMENT 'Minimum lead time in minutes before slot start',
  cutoff_hour        TINYINT UNSIGNED  NOT NULL DEFAULT 20   COMMENT 'Hard same-day cutoff (24h hour, e.g. 20 = 8 PM)',

  -- Admin controls
  display_order    SMALLINT          NOT NULL DEFAULT 0,
  is_active        TINYINT(1)        NOT NULL DEFAULT 1,
  is_recommended   TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Shows "Recommended" badge in checkout',

  -- Audit
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_order_slots_type_active   (slot_type, is_active),
  INDEX idx_order_slots_active_order  (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Master slot definitions for pickup and delivery windows';

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: order_slot_exceptions
-- Per-date overrides: holidays, closures, festival capacity adjustments.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS order_slot_exceptions (
  id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  slot_id           BIGINT UNSIGNED  NOT NULL,
  exception_date    DATE             NOT NULL,
  override_capacity SMALLINT UNSIGNED NULL     COMMENT 'NULL = use slot default',
  is_closed         TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '1 = slot completely blocked for this date',
  note              VARCHAR(255)     NULL     COMMENT 'Admin note e.g. "Diwali closure"',
  created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_slot_exception_date   (slot_id, exception_date),
  INDEX idx_slot_exceptions_date      (exception_date),
  INDEX idx_slot_exceptions_slot_date (slot_id, exception_date),
  CONSTRAINT fk_slot_exceptions_slot
    FOREIGN KEY (slot_id) REFERENCES order_slots (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Date-specific capacity overrides and closures per slot';

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: slot_capacities
-- Atomic reservation counter. Hot-path table.
-- Uses INSERT ... ON DUPLICATE KEY UPDATE for race-condition-free increments.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slot_capacities (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slot_id      BIGINT UNSIGNED NOT NULL,
  booking_date DATE            NOT NULL,
  booked_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_slot_capacity_date (slot_id, booking_date),
  INDEX idx_slot_capacities_date   (booking_date),
  CONSTRAINT fk_slot_capacities_slot
    FOREIGN KEY (slot_id) REFERENCES order_slots (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Atomic per-slot per-date booking counter for high-concurrency reservation';

-- ─────────────────────────────────────────────────────────────────────────────
-- TABLE: slot_booking_logs
-- Immutable audit trail for every reservation event.
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS slot_booking_logs (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slot_id      BIGINT UNSIGNED NULL,
  booking_date DATE            NULL,
  order_id     BIGINT UNSIGNED NULL,
  event_type   ENUM('reserved','released','fallback','over_capacity','exception_closed') NOT NULL,
  booked_count_after SMALLINT UNSIGNED NULL,
  capacity_at_event  SMALLINT UNSIGNED NULL,
  note         VARCHAR(500)    NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_slot_logs_slot_date  (slot_id, booking_date),
  INDEX idx_slot_logs_order      (order_id),
  INDEX idx_slot_logs_event      (event_type),
  INDEX idx_slot_logs_created    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable audit log for all slot reservation events';

-- ─────────────────────────────────────────────────────────────────────────────
-- SEED: Default production slot set (safe to run multiple times via INSERT IGNORE)
-- ─────────────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO order_slots
  (id, slot_type, slot_name, slot_label, start_time, end_time, max_orders, prep_buffer_minutes, cutoff_hour, display_order, is_active, is_recommended)
VALUES
  (1, 'delivery', 'Morning Delivery',   '10:00 AM – 12:00 PM', '10:00:00', '12:00:00', 8,  180, 20, 1, 1, 0),
  (2, 'delivery', 'Afternoon Delivery', '12:00 PM – 03:00 PM', '12:00:00', '15:00:00', 10, 180, 20, 2, 1, 1),
  (3, 'delivery', 'Evening Delivery',   '05:00 PM – 08:00 PM', '17:00:00', '20:00:00', 8,  180, 14, 3, 1, 0),
  (4, 'pickup',   'Morning Pickup',     '10:00 AM – 12:00 PM', '10:00:00', '12:00:00', 12, 120, 20, 1, 1, 0),
  (5, 'pickup',   'Afternoon Pickup',   '12:00 PM – 03:00 PM', '12:00:00', '15:00:00', 15, 120, 20, 2, 1, 1),
  (6, 'pickup',   'Evening Pickup',     '05:00 PM – 08:00 PM', '17:00:00', '20:00:00', 12, 120, 14, 3, 1, 0);

SET foreign_key_checks = 1;
