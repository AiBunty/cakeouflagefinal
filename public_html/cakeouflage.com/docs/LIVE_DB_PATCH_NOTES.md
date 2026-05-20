# LIVE_DB_PATCH_NOTES

## Files Added For Live Patch
1. [__repair_product_images.php](../__repair_product_images.php)
2. [database/migrations/2026-04-02-performance-indexes.sql](../database/migrations/2026-04-02-performance-indexes.sql)
3. Placeholder assets under [client/assets/images/placeholders](../client/assets/images/placeholders)

## Safe Rollout Steps
1. Upload updated code and placeholder files.
2. Set environment variable in production `.env`:
- `IMAGE_REPAIR_KEY=<strong-random-key>`
- `DB_CONNECT_TIMEOUT=5`
3. Run dry run:
- `https://cakeouflage.com/__repair_product_images.php?k=<key>&dry_run=1`
4. Execute repair:
- `https://cakeouflage.com/__repair_product_images.php?k=<key>`
5. Verify:
- `/api/catalog/products?limit=12`
- `/shop`
- representative `/product/{slug}` pages

## What The Repair Script Changes
- Updates only missing `featured_image` values.
- Inserts gallery image row only if product has zero gallery rows.
- Never overwrites non-empty valid `featured_image` values.

## Rollback / Safety
- Script is idempotent and only patches missing data.
- Remove or rename `__repair_product_images.php` after successful run.
- Rotate `IMAGE_REPAIR_KEY` after execution.
