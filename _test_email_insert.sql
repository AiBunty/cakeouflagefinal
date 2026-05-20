-- Insert test email communication log
INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json)
VALUES (
  'email',
  'build_your_cake_quote_email',
  'parin11@gmail.com',
  'queued',
  JSON_OBJECT(
    'first_name', 'Parin',
    'name', 'Parin Daulat',
    'quote_number', 'BYOC-TEST-001',
    'quote_subject', 'Custom Birthday Cake Quote',
    'quote_message', 'We have crafted a beautiful custom cake design just for you! Your order will feature a 2kg fondant cake with a personalised topper, matching the birthday theme you described. Freshly baked with premium ingredients and delivered with care.',
    'quote_amount', '2200.00',
    'advance_amount', '1100.00',
    'event_information', 'Birthday',
    'event_date', '2026-06-10',
    'number_of_servings_guests', '25',
    'budget_range', '2000 - 2500',
    'diet_preference', 'Eggless',
    'quote_accept_link', 'http://localhost:8888/quote/accept/TESTPREVIEWTOKEN123',
    'quote_expiry_at', '2026-05-26 17:00:00',
    'quote_expiry_display', '26 May 2026 at 05:00 PM'
  )
);

-- Queue the job using the inserted log ID
INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts)
VALUES (
  'send_communication',
  JSON_OBJECT(
    'log_id', LAST_INSERT_ID(),
    'channel', 'email',
    'event_key', 'build_your_cake_quote_email',
    'recipient', 'parin11@gmail.com'
  ),
  'queued',
  NOW(),
  0
);

SELECT LAST_INSERT_ID() as queue_job_id;
