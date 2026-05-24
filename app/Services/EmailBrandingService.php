<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Centralised email branding reader.
 *
 * Fetches brand settings from the `settings` key-value table and exposes
 * them as an array of template variables so that every transactional email
 * can render {{email_logo_url}}, {{business_name}}, etc. without any caller
 * having to know which DB table the values live in.
 */
final class EmailBrandingService
{
    /**
     * Returns branding context to be merged into every email template render.
     *
     * Keys returned:
     *   email_logo_url       – absolute URL to the white/colour logo used in email headers
     *   business_name        – display name of the business
     *   brand_primary_color  – hex colour for email header background
     *   brand_secondary_color – hex colour for email footer background
     *   support_email        – reply-to / contact email shown in email footers
     *   support_phone        – phone number shown in email footers
     *
     * @return array<string,string>
     */
    public static function getEmailBranding(\PDO $pdo): array
    {
        $keys = [
            'email_logo_url',
            'business_name',
            'brand_primary_color',
            'brand_secondary_color',
            'support_email',
            'support_phone',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            'SELECT setting_key, setting_value FROM settings WHERE setting_key IN (' . $placeholders . ')'
        );
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'email_logo_url'       => (string)($rows['email_logo_url'] ?? ''),
            'business_name'        => (string)($rows['business_name'] ?? 'Cakeouflage'),
            'brand_primary_color'  => (string)($rows['brand_primary_color'] ?? '#80001F'),
            'brand_secondary_color' => (string)($rows['brand_secondary_color'] ?? '#140b0f'),
            'support_email'        => (string)($rows['support_email'] ?? ''),
            'support_phone'        => (string)($rows['support_phone'] ?? ''),
        ];
    }

    /**
     * Returns a fixed sample branding array for template preview / test emails.
     * No DB access — safe to call from anywhere.
     *
     * @return array<string,string>
     */
    public static function sampleBranding(): array
    {
        return [
            'email_logo_url'       => 'https://via.placeholder.com/240x80/80001F/ffffff?text=YOUR+LOGO',
            'business_name'        => 'Cakeouflage',
            'brand_primary_color'  => '#80001F',
            'brand_secondary_color' => '#140b0f',
            'support_email'        => 'support@cakeouflage.com',
            'support_phone'        => '+91 00000 00000',
        ];
    }
}
