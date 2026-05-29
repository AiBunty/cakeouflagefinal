START TRANSACTION;

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'contact_phone', COALESCE(
  (SELECT setting_value FROM settings WHERE setting_key = 'support_phone' LIMIT 1),
  (SELECT setting_value FROM settings WHERE setting_key = 'business_phone' LIMIT 1),
  ''
), NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'contact_phone');

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'whatsapp_number', COALESCE(
  (SELECT setting_value FROM settings WHERE setting_key = 'support_whatsapp' LIMIT 1),
  (SELECT setting_value FROM settings WHERE setting_key = 'business_phone' LIMIT 1),
  ''
), NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'whatsapp_number');

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'facebook_url', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'facebook_url');

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'instagram_url', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'instagram_url');

INSERT INTO settings (setting_key, setting_value, updated_by_admin_id)
SELECT 'google_maps_url', '', NULL
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'google_maps_url');

COMMIT;
