# Cakeouflage Admin Guide (Scaffold)

## Admin Areas
- Dashboard
- Products and categories
- Orders and fulfilment
- Finance dashboard and invoice operations
- Manual payment verification with proof upload
- SMTP settings and delivery tests
- Meta WhatsApp integration, templates, mappings, and logs
- Communication templates and logs
- Automation rules, reminders, and queue monitor
- Upcoming birthdays CRM pipeline
- Courses and batches
- Content and banners
- Media and bulk import
- B2B accounts, quotes, and orders
- Reports

## Finance Workflow
- Generate invoice linked to retail or B2B record
- Keep invoice in `pending_payment` or `payment_under_verification` for manual UPI/bank proof checks
- Record payment references and optional screenshot proof
- Verify payment to move invoice to `part_paid` or `paid`
- Track overdue and receivables via finance dashboard + ageing buckets

## Communication Workflow
- Maintain SMTP settings from admin communications and queue a real SMTP test email after changes
- Maintain Meta WhatsApp credentials, phone number IDs, and WABA details from the dedicated integration page
- Draft WhatsApp templates locally with readable variables such as `{{customer_name}}` and submit them to Meta as numbered parameters
- Sync template approvals/rejections from Meta before enabling mappings or live sends
- Clone rejected templates, fix content, and resubmit from the templates module
- Map business events to approved templates only and use test-send before enabling production traffic
- Keep templates editable by event key and channel
- Queue test sends and retries via queue jobs (cron worker friendly)
- Maintain per-recipient communication logs for audit and resend
- Review sync logs, approval logs, failed queue items, and usage reports from the WhatsApp logs page
- Run queue manually from Admin Automation page for immediate retries/testing
- Schedule cron execution for continuous background processing and WhatsApp template sync

## Auth Recovery
- Customer login requests are rate limited by IP and email bucket to reduce brute-force attempts.
- Admin login requests are rate limited separately from customer auth.
- Customers can request a reset token from `/forgot-password` and complete the reset on `/reset-password`.
- Reset emails are queued through the same SMTP-backed communication pipeline used for transactional email.

## B2B + Content Operations
- B2B accounts page tracks credit terms, approval state, assigned manager, and notes.
- B2B quotes page supports status changes and quote-to-order conversion.
- B2B orders page mirrors fulfilment/payment controls for wholesale workflows.
- Banner management controls homepage/admin promotional slots.
- Content management supports editable page sections stored in the database.
- Reports page provides summary KPIs for orders, invoices, customers, B2B activity, communications, and queue health.

## Delivery Rules
- Radius service up to 30 km
- Slabs: 0-5, 5-10, 10-20, 20-30
- Above 30 km requires manual approval
- Pickup and delivery time slots configurable

## Security Baseline
- Session-based auth
- Role checks for admin routes
- CSRF tokens required on all state-changing API requests
- Shared frontend helpers already attach CSRF automatically for JSON and multipart admin actions
- Auth rate limiting at web server/application layer
- Bcrypt password hashes
- Password reset tokens are time boxed and invalidated after successful use
