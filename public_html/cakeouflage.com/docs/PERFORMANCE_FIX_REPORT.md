# PERFORMANCE_FIX_REPORT

## Root Causes Confirmed
1. Local DB host DNS was unresolved, causing API 503 and fallback loader/stale UI symptoms.
2. Frontend fetch had no timeout, so slow/blocked requests could feel hung.
3. Shop JS had stale selectors in earlier revision causing render failures.
4. Category/nav work was fully DB-dependent on every request.

## Fixes Applied
1. DB connection hardening:
- Added PDO connect timeout via `DB_CONNECT_TIMEOUT` in [app/Core/Database.php](../app/Core/Database.php).

2. Frontend network resilience:
- Added fetch timeout + AbortController in [client/assets/js/utils.js](../client/assets/js/utils.js).
- Timeout now returns clean UI error instead of hanging.

3. Nav/category performance:
- Added lightweight file cache for category rows/nav tree in [app/Services/CategoryService.php](../app/Services/CategoryService.php) using [app/Core/FileCache.php](../app/Core/FileCache.php).

4. Query/index improvements:
- Added migration SQL with index improvements for categories/products/variants/images in [database/migrations/2026-04-02-performance-indexes.sql](../database/migrations/2026-04-02-performance-indexes.sql).

## Validation Focus
- Homepage/menu continue to render even when DB is slow/unavailable in local dev.
- Product listing requests now timeout rather than block indefinitely.
- UI transitions to fallback/empty/error state quickly, no infinite spinner loops.
