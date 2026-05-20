# StackCP Deployment Guide (PHP-FPM + MySQL)

## Target Platform
- Shared hosting managed through StackCP
- PHP 8.1 (PHP-FPM)
- MySQL
- Apache with `.htaccess`
- SSH/SFTP and scheduled tasks (cron)

## Deployment First (Recommended Order)
Use this sequence when deploying to a fresh StackCP host.

1. Put the site in maintenance mode (or deploy during low traffic).
2. Upload application files to `public_html/cakeouflage.com`.
3. Create/update `.env` with production values.
4. Import database dump (prefer `cakeouflage.sql` for full migration).
5. Validate HTTP and DB connectivity APIs.
6. Re-enable traffic.

## Pre-Deployment Checklist
- Database created in StackCP and credentials available
- Domain or subdomain points to the site root
- PHP version set to 8.1
- PHP-FPM enabled for the domain
- `.env` prepared with production values
- Queue cron token generated and stored in `.env`
- SMTP credentials available for the production sender
- Meta WhatsApp app, system user token, WABA ID, and phone number ID ready if WhatsApp sending is enabled

## Upload and Structure
1. Upload the full project to the web root (`public_html` or configured document root).
2. Ensure `index.php` and `.htaccess` are in the web root.
3. Confirm folder permissions allow writing to `uploads/` if media features are enabled.
4. If the site is hosted under a subdirectory such as `/backend`, set `APP_BASE_PATH=/backend` in production and point the FTP deploy target at `public_html/backend/`.

## Database Setup
1. Open phpMyAdmin in StackCP.
2. Import `database/schema.sql`.
3. Import `database/seed.sql` (optional for production, useful for first verification).

### Full Migration From Dump (`cakeouflage.sql`)
If you are migrating existing live data, import the attached full dump file instead of schema/seed.

1. Open StackCP phpMyAdmin for database `cakeouflage-3530373538ac`.
2. Choose `Import`.
3. Select `cakeouflage.sql`.
4. Keep format `SQL`, then run import.
5. Confirm key tables exist (for example: `products`, `orders`, `users`, `admins`).

Notes:
- The dump can be imported directly into the selected target database.
- If import size exceeds phpMyAdmin limits, split the dump or import over SSH if your hosting plan permits CLI MySQL access.

## Environment Configuration
Create `.env` from `.env.example` and set at minimum:
- `APP_ENV=production`
- `APP_BASE_URL=https://your-domain.example`
- `SESSION_SECRET=<long-random-secret>`
- `SESSION_COOKIE_SECURE=1`
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`
- `QUEUE_CRON_TOKEN=<long-random-token>`

## Production Admin Setup
After the first login, configure these areas before going live:
- Admin Communications: save SMTP host, port, sender, encryption, username, and password; then queue a test email
- Admin WhatsApp Meta Integration: save provider, Graph API base URL, WABA ID, phone number ID, app credentials, and access token; then run connection test
- Admin WhatsApp Templates: generate starter drafts, preview variables, submit to Meta, and wait for approval sync
- Admin WhatsApp Mappings: link live business events only to approved templates
- Admin Content and Banners: replace seeded copy and creative assets
- Admin B2B pages: review seeded wholesale data and remove or replace as needed

## Apache Rewrite Requirement
`.htaccess` already routes all non-file requests through `index.php`:
- Existing files and folders are served directly
- Application routes are handled by the PHP front controller

## Cron (Scheduled Tasks)
Use StackCP Scheduled Tasks for recurring jobs.

Typical command pattern:
```bash
php /home/USERNAME/public_html/index.php /cron/task-name
```

Examples:
- Daily operational report
- Expired draft/cart cleanup
- Reminder notifications
- Queue processing (every 2-5 minutes):
```bash
php /home/USERNAME/public_html/index.php "/cron/queue/process?token=YOUR_QUEUE_CRON_TOKEN&max_jobs=25"
```
- WhatsApp template sync (every 15-30 minutes):
```bash
php /home/USERNAME/public_html/index.php "/cron/whatsapp/templates/sync?token=YOUR_QUEUE_CRON_TOKEN"
```

## SSH Verification
Run from project root:
```bash
php -v
php -l index.php
find app -name "*.php" -print0 | xargs -0 -n1 php -l
```

## Functional Test Checklist
- API smoke tests:
- `GET /api/health` should return `success: true`
- `GET /api/health/db` should return `success: true` and `connected: true`
- Confirm customer registration, login, forgot-password, and reset-password flows work over HTTPS
- Confirm admin login rate limiting returns a throttled response after repeated failures
- Save SMTP settings and verify a queued test email is delivered successfully
- Run queue processing and confirm `communication_logs`, `communication_queue`, and `queue_jobs` transition to sent/completed states
- Save Meta settings and confirm connection test succeeds
- Create or auto-generate a WhatsApp draft template, preview sample variables, submit it, and sync status from Meta
- Test send an approved template only after it shows approved status locally
- Verify banner/content/B2B pages load and persist edits
- Verify invoice, order, and reminder flows still work after the comms migration

## Security Notes
- Keep `.env` out of public exposure
- Use strong DB credentials
- Set `SESSION_COOKIE_SECURE=1` in production
- Keep the shared layout meta CSRF token enabled and do not strip it from templates
- If you add custom admin forms or JS requests, send `X-CSRF-Token` or `_csrf`
- Keep file upload validation enabled in application logic
- Enable HTTPS and force redirects at domain level

## No Node Runtime Dependency
This application is pure PHP + MySQL and does not require Node.js, PM2, Docker, or server-side JavaScript hosting.
