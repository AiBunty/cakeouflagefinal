# Staging Runbook: Celebration Cron Verification

Use this runbook to execute one cron cycle in staging and verify queued communication events for a test profile that has both DOB and DOA.

## 1) Preconditions

- Staging app is deployed with latest code.
- `QUEUE_CRON_TOKEN` for staging is available.
- One test customer profile exists with:
  - `date_of_birth` set
  - `anniversary_date` set
  - valid email address
- Optional combined check: set DOB and DOA to same month/day.

## 2) Prepare test profile

Run on staging DB:

```sql
-- Locate profile
SELECT u.id, u.full_name, u.email, cp.date_of_birth, cp.anniversary_date
FROM users u
JOIN customer_profiles cp ON cp.user_id = u.id
WHERE u.email = 'test-celebration@example.com';

-- Set dates (example)
UPDATE customer_profiles
SET date_of_birth = '1992-05-17',
    anniversary_date = '2018-05-17'
WHERE user_id = <USER_ID>;
```

## 3) Trigger one cron cycle

```bash
curl -s "https://<staging-host>/cron/queue/process?token=<QUEUE_CRON_TOKEN>&max_jobs=50"
```

Expected response includes:

- `data.celebrations.generated`
- `data.follow_ups.queued_actions`
- `data.queue` summary

## 4) Verify reminder generation

```sql
SELECT id, user_id, reminder_type, title, reminder_on, status, created_at, notes
FROM reminders
WHERE reminder_type = 'birthday'
  AND user_id = <USER_ID>
ORDER BY id DESC
LIMIT 20;
```

Check `notes` JSON for:

- `celebration_purpose`
- `event_date`
- `celebration_key`

## 5) Verify queue + communication logs

```sql
SELECT id, event_key, recipient, status, created_at
FROM communication_logs
WHERE user_id = <USER_ID>
  AND event_key IN (
    'birthday_greeting_email',
    'birthday_preorder_email',
    'anniversary_greeting_email',
    'anniversary_preorder_email',
    'celebration_combined_email'
  )
ORDER BY id DESC
LIMIT 30;

SELECT id, channel, status, created_at
FROM communication_queue
ORDER BY id DESC
LIMIT 30;

SELECT id, job_type, status, attempts, last_error, created_at, updated_at
FROM queue_jobs
WHERE job_type IN ('send_communication', 'crm_trigger_push')
ORDER BY id DESC
LIMIT 30;
```

## 6) Idempotency check (no duplicates)

Run the same cron URL one more time, then verify no duplicate reminder for same marker/date:

```sql
SELECT
  user_id,
  COUNT(*) AS cnt,
  JSON_EXTRACT(notes, '$.celebration_key') AS celebration_key
FROM reminders
WHERE reminder_type = 'birthday'
  AND user_id = <USER_ID>
GROUP BY user_id, JSON_EXTRACT(notes, '$.celebration_key')
HAVING COUNT(*) > 1;
```

Expected: zero rows.

## 7) Combined email policy validation

If `celebration_combined_email_on_same_day = 1` and DOB/DOA fall on same date:

```sql
SELECT id, event_key, status, created_at
FROM communication_logs
WHERE user_id = <USER_ID>
  AND DATE(created_at) = CURDATE()
ORDER BY id DESC;
```

Expected: combined email key used (no duplicate greeting pair for same day).

## 8) Quick troubleshooting

- If no reminders generated:
  - Verify profile dates are valid and user is active customer.
  - Verify cron response `data.celebrations.profiles_scanned` > 0.
- If reminders exist but no queue jobs:
  - Check template keys exist and are active in `communication_templates`.
- If queue exists but no send:
  - Inspect `queue_jobs.last_error` and SMTP configuration.
