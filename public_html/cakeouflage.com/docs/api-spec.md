# Cakeouflage API Spec (PHP Baseline)

## Base URL
- `/api`

## Runtime
- PHP 8.1+
- PDO MySQL

## Health
- `GET /api/health`

## Catalog
- `GET /api/catalog/categories`
- `GET /api/catalog/products`
- `GET /api/catalog/products/:slug`
- `GET /api/catalog/courses`

## Auth
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/auth/me`
- `POST /api/auth/logout`

## Cart
- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/:id`
- `DELETE /api/cart/items/:id`
- `POST /api/cart/coupon`

## Fulfilment + Checkout
- `GET /api/fulfilment/pincode/:postalCode`
- `GET /api/fulfilment/slots`
- `POST /api/checkout/preview`
- `POST /api/orders/place`

## B2B + Admin Snapshot
- `POST /api/b2b/inquiry`
- `POST /api/b2b/quote`
- `GET /api/admin/dashboard/summary`

## Admin Auth
- `POST /api/admin/auth/login`
- `POST /api/admin/auth/logout`
- `GET /api/admin/auth/me`

## Admin Products CRUD
- `GET /api/admin/products`
- `POST /api/admin/products`
- `PATCH /api/admin/products/:id`
- `DELETE /api/admin/products/:id`

## Admin Categories CRUD
- `GET /api/admin/categories`
- `POST /api/admin/categories`
- `PATCH /api/admin/categories/:id`
- `DELETE /api/admin/categories/:id`

## Admin Bulk Import
- `GET /api/admin/import/template`
- `POST /api/admin/import/products`
- `GET /api/admin/import/logs`
- `GET /api/admin/import/logs/:file/failed-rows`
- Import flags on multipart form:
  - `strict_variants=1|0` (default `1`)
  - `dry_run=1|0` (default `0`)
  - `abort_on_error=1|0` (default `0`)

## Admin Media Manager
- `GET /api/admin/media`
- `POST /api/admin/media/upload`
- `POST /api/admin/media/delete`
- `POST /api/admin/products/:id/media/attach` (mode: `featured|gallery`)
- `GET /api/admin/products/:id/media`
- `PATCH /api/admin/products/:id/media/reorder` (body: `ordered_ids[]`)
- `PATCH /api/admin/products/:id/media/:imageId/reorder` (direction: `up|down`)
- `DELETE /api/admin/products/:id/media/:imageId`

## Admin Order Management
- `GET /api/admin/orders`
- `GET /api/admin/orders/:id`
- `PATCH /api/admin/orders/:id/status`
- `GET /api/admin/orders/export`
- Status transition validation rules are enforced server-side:
  - Order status cannot move backwards (for example `completed -> pending` is rejected)
  - Fulfilment-aware restrictions apply (`pickup` cannot move to `out_for_delivery`, `delivery` cannot move to `ready_for_pickup`)
  - Payment status transitions are restricted (`paid -> refunded` allowed, `refunded -> paid` rejected)
- Timeline payload in order detail includes UI fields:
  - `badge` (`neutral|info|success|danger`)
  - `label` (human-readable action title)
  - `message` (human-readable action summary)

## Admin Finance + Invoices
- `GET /api/admin/finance/summary`
- `GET /api/admin/finance/ageing`
- `GET /api/admin/invoices`
- `GET /api/admin/invoices/:id`
- `PATCH /api/admin/invoices/:id/status`
- `POST /api/admin/invoices/:id/payments` (multipart form, supports `proof` image upload)
- Invoice statuses:
  - `draft`
  - `pending_payment`
  - `part_paid`
  - `paid`
  - `overdue`
  - `payment_under_verification`
  - `unpaid_rejected`
  - `cancelled`
  - `refunded`

## Admin Communication Settings + Logs
- `GET /api/admin/settings/smtp`
- `PATCH /api/admin/settings/smtp`
- `POST /api/admin/settings/smtp/test`
- `GET /api/admin/settings/whatsapp`
- `PATCH /api/admin/settings/whatsapp`
- `POST /api/admin/settings/whatsapp/test`
- `GET /api/admin/communication/templates`
- `PATCH /api/admin/communication/templates/:id`
- `GET /api/admin/communication/logs`
- `POST /api/admin/communication/logs/:id/retry`

## Admin WhatsApp Meta Templates
- `GET /api/admin/whatsapp/templates`
- `GET /api/admin/whatsapp/templates/:id`
- `POST /api/admin/whatsapp/templates`
- `PATCH /api/admin/whatsapp/templates/:id`
- `POST /api/admin/whatsapp/templates/auto-generate`
- `POST /api/admin/whatsapp/templates/sync`
- `POST /api/admin/whatsapp/templates/bulk-submit`
- `POST /api/admin/whatsapp/templates/:id/preview`
- `POST /api/admin/whatsapp/templates/:id/submit`
- `POST /api/admin/whatsapp/templates/:id/clone-fix`
- `POST /api/admin/whatsapp/templates/:id/test-send`
- `GET /api/admin/whatsapp/templates/:id/versions`
- `GET /api/admin/whatsapp/mappings`
- `PATCH /api/admin/whatsapp/mappings/:id`
- `GET /api/admin/whatsapp/logs/overview`
- `GET /api/admin/whatsapp/logs/sync`
- `GET /api/admin/whatsapp/logs/approval`
- `GET /api/admin/whatsapp/logs/send`
- `GET /api/admin/whatsapp/logs/failed-queue`
- `GET /api/admin/whatsapp/logs/usage-report`
- Template authoring rules:
  - Local drafts use readable business variables like `{{customer_name}}`, `{{order_number}}`, and `{{delivery_slot}}`
  - Submission to Meta converts variables to numbered placeholders required by approved templates
  - Only approved templates should be used in live mappings and test-send endpoints

## Admin Customers + B2B + Content
- `GET /api/admin/customers`
- `GET /api/admin/b2b/accounts`
- `PATCH /api/admin/b2b/accounts/:id`
- `GET /api/admin/b2b/quotes`
- `PATCH /api/admin/b2b/quotes/:id`
- `POST /api/admin/b2b/quotes/:id/convert-to-order`
- `GET /api/admin/b2b/orders`
- `PATCH /api/admin/b2b/orders/:id`
- `GET /api/admin/banners`
- `POST /api/admin/banners`
- `PATCH /api/admin/banners/:id`
- `DELETE /api/admin/banners/:id`
- `GET /api/admin/pages`
- `PATCH /api/admin/pages/:id`
- `GET /api/admin/reports/summary`

## Admin Automation + CRM
- `GET /api/admin/automation/rules`
- `PATCH /api/admin/automation/rules/:id`
- `GET /api/admin/reminders`
- `POST /api/admin/reminders`
- `PATCH /api/admin/reminders/:id`
- `GET /api/admin/customers/upcoming-birthdays?days=30`
- `GET /api/admin/queue/jobs`
- `POST /api/admin/queue/process`

## Cron Queue Processing
- `GET /cron/queue/process?token=QUEUE_CRON_TOKEN&max_jobs=25`
- `GET /cron/whatsapp/templates/sync?token=QUEUE_CRON_TOKEN`
- Supports CLI execution via front controller:
  - `php index.php /cron/queue/process?token=QUEUE_CRON_TOKEN&max_jobs=25`
  - `php index.php /cron/whatsapp/templates/sync?token=QUEUE_CRON_TOKEN`

## Response Contract
```json
{
  "success": true,
  "message": "ok",
  "data": {}
}
```

## CSRF Requirement
- All state-changing API routes under `/api` require a CSRF token.
- Send it as the `X-CSRF-Token` header for JSON requests.
- For multipart form uploads, include `_csrf` in the form body.
- The token is emitted in the shared HTML layout as `meta[name="csrf-token"]`.

## Error Contract
```json
{
  "success": false,
  "message": "bad request",
  "details": null
}
```
