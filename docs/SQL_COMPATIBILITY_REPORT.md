# SQL Compatibility Report (MariaDB)

## Objective
Eliminate MariaDB-incompatible SQL/runtime patterns discovered during live admin QA.

## Applied Fixes
1. Replaced MariaDB-incompatible upsert alias usage (`AS new ON DUPLICATE KEY UPDATE`) in active admin runtime paths.
2. Made strict-mode (`ONLY_FULL_GROUP_BY`) queries compatible in live views:
- CRM diagnostics aggregation query
- Kitchen queue order aggregation
3. Hardened refund reporting against schema drift:
- optional `settlement_reference`
- optional `settlement_proof_url`
- adaptive admin name selection (`full_name` vs first/last fallback)
4. Added defensive query execution handling (`safeQuery` + catch/log) for refund report list/count/summary paths.
5. Applied runtime DB migration:
- `orders.order_status` => `VARCHAR(64)`
- media metadata columns on `media_assets`
- migration made resilient by bootstrapping `media_assets` table when missing.

## Verification
- Migration applied successfully via PowerShell-safe piping into MariaDB container.
- Column verification confirmed:
  - `orders.order_status` as `varchar(64)`
  - `media_assets.optimized_path`
  - `media_assets.thumbnail_path`
  - `media_assets.transcoding_status`
  - `media_assets.duration_seconds`
  - `media_assets.resolution`
- Live browser re-tests of previously failing pages returned HTTP 200 (except permission-gated pages).

## Remaining SQL Risks
- Image optimization queue still has pending items due environment encoder availability mismatch (`imagewebp/cwebp` errors in historical queue rows).
- Non-media queue job backlog (communications/CRM mapping dependencies) can delay processing order for media jobs unless prioritized.
