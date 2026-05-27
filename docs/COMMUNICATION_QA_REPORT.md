# Communication QA Report

Date: 2026-05-25

## Scope
- Communications module template rendering and queue-driven email dispatch.
- Branding variables including WhatsApp URL footer rendering.
- Order/refund event key coverage and variable insertion tools.

## Code-Level Validation Completed
- Added missing refund processed templates in admin seeding layer.
- Expanded template variable registries in TinyMCE merge tags and custom variable panel.
- Expanded order context payload with payment, delivery, coupon, and totals fields.
- Added variable resolver fallbacks for new aliases.
- Added support_whatsapp settings persistence and branding exposure.
- Added runtime aliases in QueueWorker for payment_received_amount and support_whatsapp_url.

## Pending Runtime QA
- Full browser-driven E2E for Online, Manual, and BYOC order paths with test profile:
  - Name: Parin Daulat
  - Email: parin11@gmail.com
  - Phone: +919330033000
- Validation pending for final SMTP inbox evidence and communication_logs status evidence on all transitions.

## Current Status
- Implementation: In progress and applied to codebase.
- Runtime validation: Pending final execution pass.
