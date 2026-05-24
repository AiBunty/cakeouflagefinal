-- Migration: add payment_proof_url and payment_proof_uploaded_at to orders table
-- Date: 2026-05-22
-- Safe to re-run (IF NOT EXISTS guards)

ALTER TABLE `orders`
  ADD COLUMN `payment_proof_url` VARCHAR(500) NULL AFTER `grand_total`,
  ADD COLUMN `payment_proof_uploaded_at` DATETIME NULL AFTER `payment_proof_url`;
