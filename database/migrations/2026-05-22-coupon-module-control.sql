-- Migration: 2026-05-22 — Coupon Module Control
-- Adds auto_apply flag and applicable_to scope to the coupons table.
--
-- auto_apply: 1 = coupon is auto-applied at cart load; 0 = must be entered manually
-- applicable_to: comma-delimited modules that may use this coupon
--   values: 'online', 'manual', 'byoc'
--
-- Note: MySQL 8 does not support ADD COLUMN IF NOT EXISTS.
-- Run only on databases that do not already have these columns.

ALTER TABLE `coupons`
    ADD COLUMN `auto_apply` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = auto-apply at cart; 0 = manual entry only'
        AFTER `target_mode`;

ALTER TABLE `coupons`
    ADD COLUMN `applicable_to` VARCHAR(64) NOT NULL DEFAULT 'online'
        COMMENT 'Comma-delimited modules: online, manual, byoc'
        AFTER `auto_apply`;
