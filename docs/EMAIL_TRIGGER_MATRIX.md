# Email Trigger Matrix

Date: 2026-05-25

## Lifecycle Triggers
- online_order_received -> online_order_received_customer, online_order_received_admin
- manual_order_received -> manual_order_received_customer, manual_order_received_admin
- payment_confirmed -> payment_confirmed_customer, payment_confirmed_admin
- ready_order -> ready_order_customer, ready_order_admin
- order_delivered -> order_delivered_customer, order_delivered_admin
- reject_order -> reject_order_customer, reject_order_admin

## Refund Triggers
- partially_refunded -> partial_refund_processed_customer, partial_refund_processed_admin, refund_processed_customer, refund_processed_admin
- fully_refunded -> full_refund_processed_customer, full_refund_processed_admin, refund_processed_customer, refund_processed_admin
- refund_requested -> crm_trigger_push (CRM side)

## Follow-up and Campaign Triggers
- follow_up_review -> follow_up_review_email
- annual_reorder -> annual_reorder_email
- birthday and anniversary reminder keys -> corresponding campaign templates

## API/Account Triggers
- forgot password -> password_reset

## Queue and Logging Mapping
- queue_jobs.job_type=send_communication
- communication_logs.event_key stores resolved trigger template key
- communication_logs.payload_json stores render context and diagnostics
- communication_logs.status transitions queued -> sent or failed
