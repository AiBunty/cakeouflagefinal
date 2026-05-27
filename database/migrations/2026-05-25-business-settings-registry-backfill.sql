-- Backfill newly governed communications/business settings keys.

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'currency_code', 'INR', 1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'currency_code'
);

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'currency_symbol', 'Rs', 1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'currency_symbol'
);

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'business_logo', COALESCE(NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'email_logo_url' LIMIT 1), ''), ''), 1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'business_logo'
);

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'business_address',
       TRIM(BOTH ', ' FROM CONCAT_WS(', ',
         NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'business_address_line1' LIMIT 1), ''),
         NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'business_address_line2' LIMIT 1), ''),
         NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'business_city' LIMIT 1), ''),
         NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'business_state' LIMIT 1), ''),
         NULLIF((SELECT setting_value FROM settings WHERE setting_key = 'business_postal_code' LIMIT 1), '')
       )),
       1
WHERE NOT EXISTS (
  SELECT 1 FROM settings WHERE setting_key = 'business_address'
);
