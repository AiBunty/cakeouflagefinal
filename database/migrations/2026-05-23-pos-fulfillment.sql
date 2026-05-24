-- ============================================================
-- Migration: Bakery POS + Fulfillment Architecture
-- Date: 2026-05-23
-- Purpose: Add order_mode, slot_id, kitchen production tracking
-- ============================================================

-- 1. Add new columns to orders table
ALTER TABLE `orders`
  ADD COLUMN `order_mode` ENUM('ready_pos','scheduled_custom','online','byoc') NULL
    COMMENT 'POS mode: ready_pos=counter sale, scheduled_custom=admin booked, online=web, byoc=quote'
    AFTER `order_source`,
  ADD COLUMN `slot_id` BIGINT UNSIGNED NULL
    COMMENT 'FK to order_slots.id — denormalized for fast fulfillment queries'
    AFTER `scheduled_slot_label`,
  ADD COLUMN `requires_kitchen_production` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '0 for ready_pos orders (cake already made), 1 for all others'
    AFTER `slot_id`,
  ADD COLUMN `production_status` ENUM('not_required','pending','in_production','decoration_pending','ready','packed','out_for_delivery','delivered') NOT NULL DEFAULT 'pending'
    COMMENT 'Kitchen production lifecycle'
    AFTER `requires_kitchen_production`;

-- 2. Add indexes for fulfillment queries
ALTER TABLE `orders`
  ADD KEY `idx_orders_order_mode` (`order_mode`),
  ADD KEY `idx_orders_production_status` (`production_status`),
  ADD KEY `idx_orders_slot_id` (`slot_id`);

-- 3. FK from orders.slot_id to order_slots.id (safe: order_slots exists from 2026-05-22 migration)
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_slot_id` FOREIGN KEY (`slot_id`)
    REFERENCES `order_slots` (`id`) ON DELETE SET NULL;

-- 4. Backfill: tag existing BYOC orders with order_mode
UPDATE `orders`
SET `order_mode` = 'byoc'
WHERE `order_source` = 'byoc_quote' AND `order_mode` IS NULL;
