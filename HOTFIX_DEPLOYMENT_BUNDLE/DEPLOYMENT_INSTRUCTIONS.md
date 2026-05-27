# Live Hotfix Deployment Instructions (v3)

## Scope
This bundle contains runtime stabilization and admin module recovery fixes for:
- PHP runtime mismatch (force PHP 8.2 handler)
- Admin session continuity (login/products)
- API health endpoints
- CRM users report ONLY_FULL_GROUP_BY compliance
- Collections queue resilience when timeline table is missing

## Files in this bundle
- `.htaccess`
- `admin/login.php`
- `admin/products.php`
- `admin/collections_queue.php`
- `admin/includes/crm_report_helpers.php`
- `app/Controllers/ApiController.php`
- `database/migrations/2026-05-26-collections-followup-logs.sql`

## Deployment order
1. Upload all PHP and `.htaccess` files to matching live paths.
2. Clear CDN cache for `/admin/*` and `/api/*` if enabled.
3. Run SQL migration:
   - `database/migrations/2026-05-26-collections-followup-logs.sql`
4. Verify:
   - `/api/health`
   - `/api/health/db`
   - `/admin/products.php`
   - `/admin/collections_queue.php`
   - `/admin/crm_report.php?sub_report=users&per_page=20&page=1`

## Post-deploy smoke checks
- Ensure admin login OTP lands on dashboard without redirect loop.
- Confirm products page opens with authenticated session.
- Confirm collections queue page loads and table renders.
- Confirm CRM users report loads without SQL 1055 errors.
