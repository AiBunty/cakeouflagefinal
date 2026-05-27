-- Normalize legacy branding tokens in stored email templates.

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'https://i.ibb.co/hRytXC3F/whitelogo.png', '{{business_logo}}')
WHERE channel = 'email'
  AND body_template LIKE '%i.ibb.co%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, '{{email_logo_url}}', '{{business_logo}}')
WHERE channel = 'email'
  AND body_template LIKE '%{{email_logo_url}}%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'alt="Cakeouflage Logo"', 'alt="{{business_name}} Logo"')
WHERE channel = 'email'
  AND body_template LIKE '%alt="Cakeouflage Logo"%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'Team Cakeouflage', 'Team {{business_name}}')
WHERE channel = 'email'
  AND body_template LIKE '%Team Cakeouflage%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'https://www.cakeouflage.com', '{{business_website}}')
WHERE channel = 'email'
  AND body_template LIKE '%https://www.cakeouflage.com%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'www.cakeouflage.com', '{{business_website}}')
WHERE channel = 'email'
  AND body_template LIKE '%www.cakeouflage.com%';

UPDATE communication_templates
SET body_template = REPLACE(body_template, 'style="height:100px;display:block;"', 'style="height:72px;display:block;width:auto;max-width:260px;background:rgba(255,255,255,0.96);padding:10px 16px;border-radius:16px;box-sizing:border-box;"')
WHERE channel = 'email'
  AND body_template LIKE '%style="height:100px;display:block;"%';
