-- Migration: add is_veg column to products table
-- Defaults all existing products to Veg (1) — safe for a bakery context
ALTER TABLE products
  ADD COLUMN is_veg TINYINT(1) NOT NULL DEFAULT 1 AFTER dietary_tag;
