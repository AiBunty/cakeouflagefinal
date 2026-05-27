# Email Architecture Report

Date: 2026-05-25

## Master Of Truth Policy
- The communication_templates table is the single source of truth for all queue-driven non-OTP emails.
- OTP delivery remains hardcoded in MailService::sendOtp by policy exception.

## Runtime Flow
1. Order and API services enqueue communication logs with event_key and payload_json.
2. QueueWorker consumes send_communication jobs.
3. QueueWorker loads active email template by channel=email and event_key.
4. QueueWorker merges branding context from EmailBrandingService.
5. QueueWorker applies variable rendering and sends via SMTP transport.
6. communication_logs status is updated to sent or failed.

## Services In Use
- OrderAutomationService: queues customer/admin lifecycle emails and CRM push jobs.
- QueueWorker::executeCommunication: template load, branding merge, variable render, send.
- EmailBrandingService: settings-backed branding and support contacts.
- VariableResolverService: placeholder fallback resolution.
- MailService and SmtpTransportService: SMTP delivery layer.

## Key Implementation Updates
- Added runtime aliases for payment_received_amount and WhatsApp URL in queue rendering.
- Added support_whatsapp and support_whatsapp_url branding variables.
- Added standalone shared layout helper at app/Views/email/master-layout.php.
- Added migration to replace deprecated {{actual_received_amount}} with {{payment_received_amount}}.

## Non-OTP Templates Covered
- order_received
- payment_confirmed
- preparing
- ready_for_pickup
- delivered
- refund_in_process
- refund_closed
- admin_notification
- telecalling_order_created
- byoc_order_created
- plus operational templates used by queue dispatch keys (customer/admin variants, follow-up, BYOC, password_reset, refund-processed variants)
