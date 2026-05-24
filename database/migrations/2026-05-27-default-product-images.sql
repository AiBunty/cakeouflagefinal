-- =============================================================================
-- Migration: 2026-05-27-default-product-images.sql
-- Purpose:   Backfill any product with NULL or empty featured_image to use the
--            branded Cakeouflage default image.  Safe to re-run (idempotent).
-- =============================================================================

-- 1. Backfill NULL featured_image
UPDATE products
SET    featured_image = '/assets/defaults/default-product-image.webp'
WHERE  featured_image IS NULL
  AND  deleted_at IS NULL;

-- 2. Backfill empty-string featured_image
UPDATE products
SET    featured_image = '/assets/defaults/default-product-image.webp'
WHERE  featured_image = ''
  AND  deleted_at IS NULL;

-- Sanity check — should return 0 after the migration runs:
-- SELECT COUNT(*) AS still_empty FROM products
-- WHERE (featured_image IS NULL OR featured_image = '') AND deleted_at IS NULL;
