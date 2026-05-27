# Live Hotfix Rollback Instructions (v3)

## Fast rollback
1. Restore these files from pre-hotfix backup:
- `.htaccess`
- `admin/login.php`
- `admin/products.php`
- `admin/collections_queue.php`
- `admin/includes/crm_report_helpers.php`
- `app/Controllers/ApiController.php`

2. Purge CDN cache for `/admin/*` and `/api/*`.

## DB rollback note
- Migration `2026-05-26-collections-followup-logs.sql` only creates a table if missing.
- It is additive and can remain in place safely.

## Validation after rollback
- Check `/admin/login.php` renders.
- Check `/admin/dashboard.php` loads.
- Check `/api/health` status.
- Confirm no new fatals in `storage/logs/php-error.log`.
