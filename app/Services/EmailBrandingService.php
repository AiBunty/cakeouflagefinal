<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Centralised email branding reader.
 *
 * Fetches brand settings from the `settings` key-value table and exposes
 * them as an array of template variables so that every transactional email
 * can render {{business_logo}}, {{business_name}}, etc. without any caller
 * having to know which DB table the values live in.
 */
final class EmailBrandingService
{
    private static function toWhatsappUrl(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        return 'https://wa.me/' . $digits;
    }

    /**
     * Returns branding context to be merged into every email template render.
     *
     * Keys returned:
     *   email_logo_url       – absolute URL to the white/colour logo used in email headers
    *   business_logo        – canonical business logo variable used by templates
     *   business_name        – display name of the business
     *   brand_primary_color  – hex colour for email header background
     *   brand_secondary_color – hex colour for email footer background
     *   support_email        – reply-to / contact email shown in email footers
     *   support_phone        – phone number shown in email footers
    *   support_whatsapp     – WhatsApp contact number shown in email footers
    *   support_whatsapp_url – wa.me URL generated from support_whatsapp/support_phone
    *   business_address     – formatted business address line for footer and invoice links
    *   business_website     – website URL shown in email footer
    *   currency_code        – currency code used in accounting and templates
    *   currency_symbol      – currency symbol used in accounting and templates
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
            'support_whatsapp',
            'business_logo',
            'business_address',
            'business_address_line1',
            'business_address_line2',
            'business_city',
            'business_state',
            'business_postal_code',
            'business_website',
            'currency_code',
            'currency_symbol',
        ];

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            'SELECT setting_key, setting_value FROM settings WHERE setting_key IN (' . $placeholders . ')'
        );
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];

        $supportPhone = (string)($rows['support_phone'] ?? '');
        $supportWhatsapp = (string)($rows['support_whatsapp'] ?? $supportPhone);
        $address = trim((string)($rows['business_address'] ?? ''));
        if ($address === '') {
            $parts = array_filter([
                trim((string)($rows['business_address_line1'] ?? '')),
                trim((string)($rows['business_address_line2'] ?? '')),
                trim((string)($rows['business_city'] ?? '')),
                trim((string)($rows['business_state'] ?? '')),
                trim((string)($rows['business_postal_code'] ?? '')),
            ], static fn(string $part): bool => $part !== '');
            $address = implode(', ', $parts);
        }

        $emailLogo = (string)($rows['email_logo_url'] ?? '');
        $businessLogo = (string)($rows['business_logo'] ?? $emailLogo);

        return [
            'email_logo_url'       => (string)($rows['email_logo_url'] ?? ''),
            'business_logo'        => $businessLogo,
            'business_name'        => (string)($rows['business_name'] ?? 'Cakeouflage'),
            'brand_primary_color'  => (string)($rows['brand_primary_color'] ?? '#80001F'),
            'brand_secondary_color' => (string)($rows['brand_secondary_color'] ?? '#140b0f'),
            'support_email'        => (string)($rows['support_email'] ?? ''),
            'support_phone'        => $supportPhone,
            'support_whatsapp'     => $supportWhatsapp,
            'support_whatsapp_url' => self::toWhatsappUrl($supportWhatsapp),
            'business_address'     => $address,
            'business_website'     => (string)($rows['business_website'] ?? 'https://www.cakeouflage.com'),
            'currency_code'        => (string)($rows['currency_code'] ?? 'INR'),
            'currency_symbol'      => (string)($rows['currency_symbol'] ?? 'Rs'),
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
            'email_logo_url'       => '/client/assets/images/mainlogo.svg',
            'business_logo'        => '/client/assets/images/mainlogo.svg',
            'business_name'        => 'Cakeouflage',
            'brand_primary_color'  => '#80001F',
            'brand_secondary_color' => '#140b0f',
            'support_email'        => 'support@cakeouflage.com',
            'support_phone'        => '+91 00000 00000',
            'support_whatsapp'     => '+91 00000 00000',
            'support_whatsapp_url' => 'https://wa.me/910000000000',
            'business_address'     => '123 Celebration Street, Nashik, Maharashtra 422001',
            'business_website'     => 'https://www.cakeouflage.com',
            'currency_code'        => 'INR',
            'currency_symbol'      => 'Rs',
        ];
    }
}
