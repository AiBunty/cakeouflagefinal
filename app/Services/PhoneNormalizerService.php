<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Normalises phone numbers to E.164 format for storage and search.
 *
 * Rules (defaultCode = '+91', i.e. India):
 *   - 10-digit input      → +91XXXXXXXXXX
 *   - 12-digit "91..."    → +91XXXXXXXXXX   (strip leading 91)
 *   - Already starts with '+' → strip non-digits after the '+'
 *   - Anything else       → prepend defaultCode to stripped digits
 *
 * Returns '' on empty / unparseable input (do NOT store in DB).
 */
final class PhoneNormalizerService
{
    /**
     * Normalise a raw phone string to E.164 (e.g. "+919876543210").
     *
     * @param string $raw         Raw phone as entered by user (may include spaces, hyphens, parens, etc.)
     * @param string $defaultCode Country dial code with '+' prefix (e.g. '+91').
     * @return string             E.164 string on success, '' on failure.
     */
    public static function normalize(string $raw, string $defaultCode = '+91'): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Preserve '+' indicator before stripping non-digits
        $hasPlus = $raw[0] === '+';
        $digits  = preg_replace('/\D/', '', $raw);

        if ($digits === '' || $digits === null) {
            return '';
        }

        // Country code digits only (e.g. '91' for India)
        $codeDigits = ltrim($defaultCode, '+');

        // Already a full international number with '+'
        if ($hasPlus) {
            // Validate sensible E.164 length (7–15 digits)
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                return '';
            }
            return '+' . $digits;
        }

        // 10-digit local number for default country
        if (strlen($digits) === 10) {
            return $defaultCode . $digits;
        }

        // 12-digit number starting with the default country code
        $codeLen = strlen($codeDigits);
        if (strlen($digits) === (10 + $codeLen) && strpos($digits, $codeDigits) === 0) {
            return $defaultCode . substr($digits, $codeLen);
        }

        // General international number (no '+', length 7-15)
        if (strlen($digits) >= 7 && strlen($digits) <= 15) {
            // If it starts with the country-code digits, keep as-is with '+'
            if (strpos($digits, $codeDigits) === 0) {
                return '+' . $digits;
            }
            // Otherwise assume it's a local number and prepend country code
            return $defaultCode . $digits;
        }

        return '';
    }

    /**
     * Return the pure-digit form of a stored phone (for LIKE searches).
     * Strips '+', spaces, hyphens — useful for "contains" DB searches.
     */
    public static function toSearchable(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    /**
     * Loose comparison: checks whether two phone strings refer to the same number,
     * ignoring formatting differences and leading country codes.
     */
    public static function matches(string $a, string $b): bool
    {
        $da = self::toSearchable($a);
        $db = self::toSearchable($b);

        if ($da === '' || $db === '') {
            return false;
        }
        if ($da === $db) {
            return true;
        }

        // Compare last 10 digits (local number portion)
        $last10a = substr($da, -10);
        $last10b = substr($db, -10);

        return strlen($last10a) === 10 && $last10a === $last10b;
    }
}
