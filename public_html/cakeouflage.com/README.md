# Cakeouflage E-commerce

Production-ready retail + B2B bakery commerce starter built for StackCP-managed shared hosting.

## Stack
- PHP 8.1 (PHP-FPM compatible)
- MySQL 8+
- Apache `.htaccess` front-controller routing
- Shared PHP partials and reusable CSS/JS assets

## Project Structure
```text
app/
  Core/
  Controllers/
  Views/
    partials/
    pages/
    errors/
client/
  assets/
    css/
    js/
    images/
database/
  schema.sql
  seed.sql
docs/
  api-spec.md
  admin-guide.md
index.php
.htaccess
```

## Quick Start (PHP)
1. Copy environment file
```bash
copy .env.example .env
```
2. Create and seed database
```sql
SOURCE database/schema.sql;
SOURCE database/seed.sql;
```
3. Run local PHP server from project root
```bash
php -S localhost:8000
```
4. Open
```text
http://localhost:8000
```

## StackCP Shared Hosting Deployment
1. Create a MySQL database and user in StackCP.
2. Upload project files with SFTP or SSH to the site root (`public_html` or configured web root).
3. Ensure `.htaccess` is present at web root.
4. Set production values in `.env` (database, app URL, session secret).
5. Import `database/schema.sql` and `database/seed.sql` using phpMyAdmin or MySQL CLI.
6. Select PHP 8.1 in StackCP and run in PHP-FPM mode.
7. Configure cron jobs in StackCP only for scheduled tasks (for example reminders, cleanup, reports).

Detailed guide: `docs/deployment-stackcp.md`.

## Current Delivery
- Pure PHP runtime (no Node/Express dependency).
- Shared layout shell with reusable partials.
- Public pages implemented for shop, product, cart, checkout, login/register, account, and policy pages.
- Password reset request and reset completion pages are implemented for customer auth recovery.
- Admin pages implemented:
  - Dashboard, products, categories, orders
  - Finance dashboard and invoices
  - Communications, automation, birthdays
  - Bulk import and media manager
  - Meta WhatsApp integration, template lifecycle, mappings, and logs
  - Customers, B2B accounts, B2B quotes, B2B orders
  - Banners, content management, reports
- API phase implemented:
  - Catalog: categories, products, product detail, courses
  - Auth: register, login, forgot password, reset password, me, logout
  - Cart: fetch, add, update, remove, coupon
  - Fulfilment: pincode serviceability, slots
  - Checkout: preview and place order
  - B2B intake: inquiry and quote request
  - Admin catalog CRUD, gallery management, import logs, order workflows
  - Finance: invoice list/detail, manual payment verification, ageing summary
  - Communications: SMTP/WhatsApp settings, template CRUD, Meta template sync/approval/test-send, retry queue
  - CRM/automation: reminders, birthdays, queue monitor, cron queue processing
  - B2B ops: account, quote, order, and conversion workflows
  - Content ops: banners, pages, and summary reports

## CSRF Protection
- All state-changing `/api/*` requests now require a CSRF token.
- The token is emitted in the shared page layout as a `meta[name="csrf-token"]` tag.
- Shared frontend helpers already send the token automatically for JSON requests.
- Multipart admin uploads append `_csrf` automatically through shared JS helpers.

## Seed Overview
- Product and course seed baseline is preserved in `database/seed.sql` with retail + B2B coverage.

## Notes
- Designed for standard shared hosting: Apache + PHP-FPM + MySQL.
- Queue jobs can be processed manually from admin or by StackCP cron.
- SMTP test and operational emails are sent through the configured SMTP transport, not PHP mail().
- WhatsApp execution now supports a Meta-connected template workflow with local drafts, approval sync, mappings, preview, and approved-only sends.
