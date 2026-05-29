<?php

declare(strict_types=1);

if (!function_exists('normalize_business_url')) {
    function normalize_business_url(?string $url): string
    {
        $value = trim((string)$url);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }
}

if (!function_exists('normalize_whatsapp_number')) {
    function normalize_whatsapp_number(?string $number, string $defaultCountryCode = '91'): string
    {
        $digits = preg_replace('/\D+/', '', (string)$number) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            $digits = $defaultCountryCode . $digits;
        }

        return $digits;
    }
}

if (!function_exists('build_whatsapp_link')) {
    function build_whatsapp_link(?string $number, string $message = ''): string
    {
        $digits = normalize_whatsapp_number($number);
        if ($digits === '') {
            return '';
        }

        $url = 'https://wa.me/' . $digits;
        if ($message !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }
}

if (!function_exists('build_tel_href')) {
    function build_tel_href(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string)$phone) ?? '';
        if ($digits === '') {
            return '';
        }

        return 'tel:+' . $digits;
    }
}
