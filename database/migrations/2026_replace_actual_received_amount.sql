UPDATE communication_templates
SET body_template = REPLACE(body_template, '{{actual_received_amount}}', '{{payment_received_amount}}')
WHERE body_template LIKE '%actual_received_amount%';
