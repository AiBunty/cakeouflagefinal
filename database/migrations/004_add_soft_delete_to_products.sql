-- Migration: Add soft_delete support to products table
-- Purpose: Enable soft delete for products that were removed in a specific import version

-- This may fail if column already exists, which is fine
ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL;
