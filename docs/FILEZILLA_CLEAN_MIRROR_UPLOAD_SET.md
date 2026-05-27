# FileZilla Clean Mirror Upload Set (Local -> Production)

Use this exact sequence for Control Panel SQL import and FTP mirror upload.

## 1) Fresh SQL Dumps (Control Panel import)

Primary full dump (schema + all data, CP-ready with USE):
- storage/backups/mariadb_full_liveprep_cp_ready_20260526_093845.sql

Backup product/catalog dump (products + categories + coupons + product media/import runs):
- storage/backups/mariadb_product_catalog_cp_ready_20260526_093845.sql

Import order in CP:
1. Create/select production database.
2. Import full dump first.
3. Use product/catalog backup only as fallback or selective restore.

If your Control Panel database name is different, open the SQL file and update only line 2:
- `USE \`cakeouflageweb-353032394d0c\`;`

## 2) Folders to Upload via FileZilla (recursive)

- admin/
- app/
- client/
- config/
- database/migrations/
- public/
- scripts/
- vendor/

Optional:
- docs/

## 3) Root Files to Upload

- .htaccess
- .user.ini
- index.php
- media.php
- order.php
- config.php
- composer.json
- composer.lock

## 4) Keep on Server (do not delete during mirror cleanup)

- uploads/
- storage/
- .env

## 5) Exclude from FTP Upload

- .git/
- .vscode/
- .docker/
- .sixth/
- docker-compose.yml
- Dockerfile
- all *.7z archives
- .env
- .env.example
- .env.local.production.example
- .env.production
- db-runtime-diagnostic.php
- pdo-runtime-diagnostic.php
- crm_webhook_mock.php
- debug.txt

## 6) env.production Live-Ready Status

The production token placeholders were replaced with strong generated values in:
- .env.production

Before go-live, copy values from .env.production into server-side .env (or CP env manager):
- SESSION_SECRET
- QUEUE_CRON_TOKEN
- SEED_WEB_TOKEN
- DEPLOY_MIGRATE_TOKEN

Do not upload .env.production directly if your server already uses .env in web root.

## 7) Clean Mirror Procedure (FileZilla)

1. Connect FTP and open live document root.
2. Download backup of current live .env, uploads/, storage/.
3. Delete old code from web root except uploads/, storage/, .env.
4. Upload folders listed in section 2 and files in section 3.
5. Set write permissions for uploads/ and storage/.
6. Import SQL dump in Control Panel (section 1).
7. Verify site:
   - /
   - /admin/login.php
   - /admin/products.php
   - /admin/categories.php

## 8) Post-Deploy Quick Check

- APP_URL in .env matches live domain.
- DB credentials in .env point to production DB.
- Admin login works.
- Product and category pages load.
- Media upload path is writable.
