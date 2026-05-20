# Email Template Link Audit

Date: 2026-05-16
Scope: Email channel only

## Summary

Goal: Make Communications the master for all generated emails.

Current state after this implementation pass:
- OTP: Connected to `communication_templates` (`event_key=otp`) and editable in Communications.
- Queue-based Email sends: Template-first in `QueueWorker` with fallback allowed.
- Invoice (`invoice_paid`): Connected by adding template seed and `invoice_html` variable support.
- Legacy hardcoded email methods in `MailService`: Still present and should be migrated/retired in next phase.

## Communications Template Keys (Email)

Seeded/default keys in Communications UI include:
- `manual_order_received_customer`
- `order_created`
- `payment_confirmed_customer`
- `reject_order_customer`
- `ready_order_customer`
- `follow_up_review_customer`
- `follow_up_yearly_customer`
- `online_order_received_customer`
- `online_order_received_admin`
- `manual_order_received_admin`
- `order_confirmed`
- `payment_confirmed_admin`
- `payment_overdue`
- `reject_order_admin`
- `ready_order_admin`
- `order_ready_for_pickup`
- `order_delivered_customer`
- `order_delivered_admin`
- `order_delivered`
- `order_in_preparation`
- `follow_up_reminder`
- `otp`
- `password_reset`
- `invoice_paid` (added in this pass)
- `admin_new_order`
- `admin_payment_confirmed`
- `admin_order_ready`
- `admin_order_rejected`

## Trigger Link Status

### Connected
- `otp` via `MailService::sendOtp()` template lookup.
- Queue email worker via `QueueWorker::executeCommunication()` template lookup by `event_key`.
- Order automation queue jobs through `OrderAutomationService` alias resolution + queue insert.
- Password reset queued communication (`event_key=password_reset`).

### Fixed in this pass
- Added `invoice_paid` template seed in Communications bootstrap and `database/seed.sql`.
- Added `invoice_html` context in invoice queue payload so template can render invoice body.
- Added fallback tracing in queue worker (`template_fallback_used=true`, `template_fallback_reason=missing_active_template`) into communication log payload.

### Still to migrate (next pass)
- Retire/replace direct hardcoded HTML sender methods in `app/Services/MailService.php` with template-event dispatch.
- Enforce coverage check so no live event key is missing an active Communications template.

## OTP Cooldown Implementation

Implemented 60-second persisted cooldown in all OTP UIs:
- Checkout
- Login
- Register
- Admin Login

Behavior:
- Send button disables and shows countdown (`Resend OTP in XXs`).
- Timer persists through refresh using browser local storage.
- Re-enables automatically at expiry.

## Next Migration Steps

1. Move all remaining direct email sends to event-key queue path.
2. Add Communications coverage diagnostics panel (missing template warnings).
3. Add per-trigger required placeholder validation in Communications editor.
4. Add optional strict mode to block sends when template is missing.
