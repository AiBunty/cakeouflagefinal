-- Backfill business website setting used by shared email branding.

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'business_website', 'https://www.cakeouflage.com', 1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'business_website'
);
