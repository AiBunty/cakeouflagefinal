-- Migration: BYOC order confirmed communication templates
-- Adds customer + admin notification templates for when a BYOC quote is accepted and converted to an order.

INSERT IGNORE INTO communication_templates (channel, event_key, subject, body_template, is_active)
VALUES (
    'email',
    'byoc_order_confirmed_customer',
    'Your Custom Cake Order Is Confirmed - {{order_number}}',
    'Hi {{customer_name}},\n\nGreat news! Your custom cake order has been confirmed.\n\nOrder Number: {{order_number}}\nOrder Total: {{currency}} {{grand_total}}\nAdvance Paid: {{currency}} {{advance_amount}}\nRemaining Balance: {{currency}} {{remaining_balance}}\n\nDelivery Address: {{delivery_address}}\n\n{{#if event_date}}Event Date: {{event_date}}{{/if}}\n\nOur team will be in touch shortly to confirm your order details. If you have any questions, please reply to this email or WhatsApp us.\n\nThank you for choosing Cakeouflage!\n\nWarm regards,\nCakeouflage Team',
    1
);

INSERT IGNORE INTO communication_templates (channel, event_key, subject, body_template, is_active)
VALUES (
    'email',
    'byoc_order_confirmed_admin',
    'New BYOC Order Received - {{order_number}} from {{customer_name}}',
    'A BYOC quote has been accepted and converted to an order.\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}}\nEmail: {{customer_email}}\nPhone: {{customer_phone}}\n\nOrder Total: {{currency}} {{grand_total}}\nAdvance Paid: {{currency}} {{advance_amount}}\nPayment Status: {{payment_status}}\n\nDelivery Address: {{delivery_address}}\n{{#if event_date}}Event Date: {{event_date}}{{/if}}\n\nPlease review and confirm this order in the admin panel.',
    1
);

-- Also fix any existing queued jobs that used the wrong job_type (communication_send instead of send_communication)
UPDATE queue_jobs
SET job_type = 'send_communication'
WHERE job_type = 'communication_send'
  AND status = 'queued';
