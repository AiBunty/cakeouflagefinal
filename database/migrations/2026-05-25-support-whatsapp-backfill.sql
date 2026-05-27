INSERT INTO settings (setting_key, setting_value)
SELECT 'support_whatsapp', COALESCE((
    SELECT setting_value FROM settings WHERE setting_key = 'support_phone' LIMIT 1
), '')
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE setting_key = 'support_whatsapp'
);
