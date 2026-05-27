<?php
declare(strict_types=1);

namespace App\Services;

final class TemplateVariableRegistry
{
    /** @var array<string,string> */
    private const ALIASES = [
        'actual_received_amount' => 'payment_received_amount',
        'fulfilment_status' => 'fulfillment_status',
        'fulfilment_mode' => 'delivery_method',
        'discount_amount' => 'coupon_discount',
        'item_names' => 'product_summary',
        'email_logo_url' => 'business_logo',
        'currency' => 'currency_symbol',
        'remaining_balance' => 'remaining_sales_amount',
    ];

    /**
     * Verified variable definitions used by communications templates.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function definitions(): array
    {
        return [
            'customer_name' => ['label' => 'Customer Name', 'group' => 'Customer', 'context_keys' => ['customer_name']],
            'first_name' => ['label' => 'First Name', 'group' => 'Customer', 'context_keys' => ['first_name', 'customer_name']],
            'customer_email' => ['label' => 'Customer Email', 'group' => 'Customer', 'context_keys' => ['customer_email']],
            'customer_phone' => ['label' => 'Customer Phone', 'group' => 'Customer', 'context_keys' => ['customer_phone']],

            'order_number' => ['label' => 'Order Number', 'group' => 'Order', 'context_keys' => ['order_number']],
            'order_status' => ['label' => 'Order Status', 'group' => 'Order', 'context_keys' => ['order_status']],
            'fulfillment_status' => ['label' => 'Fulfillment Status', 'group' => 'Order', 'context_keys' => ['fulfillment_status', 'order_status']],
            'delivery_date' => ['label' => 'Delivery Date', 'group' => 'Order', 'context_keys' => ['delivery_date']],
            'product_summary' => ['label' => 'Product Summary', 'group' => 'Order', 'context_keys' => ['product_summary', 'item_names']],

            'payment_status' => ['label' => 'Payment Status', 'group' => 'Payment', 'context_keys' => ['payment_status']],
            'payment_method' => ['label' => 'Payment Method', 'group' => 'Payment', 'context_keys' => ['payment_method']],
            'transaction_reference' => ['label' => 'Transaction Reference', 'group' => 'Payment', 'context_keys' => ['transaction_reference', 'payment_reference', 'utr_number', 'settlement_reference']],
            'payment_received_amount' => ['label' => 'Payment Received Amount', 'group' => 'Payment', 'context_keys' => ['payment_received_amount', 'grand_total']],

            'coupon_code' => ['label' => 'Coupon Code', 'group' => 'Coupon', 'context_keys' => ['coupon_code']],
            'coupon_discount' => ['label' => 'Coupon Discount', 'group' => 'Coupon', 'context_keys' => ['coupon_discount', 'discount_total']],
            'grand_total' => ['label' => 'Grand Total', 'group' => 'Coupon', 'context_keys' => ['grand_total']],

            'refund_amount' => ['label' => 'Refund Amount', 'group' => 'Refund', 'context_keys' => ['refund_amount']],
            'refund_reason' => ['label' => 'Refund Reason', 'group' => 'Refund', 'context_keys' => ['refund_reason']],
            'refund_status' => ['label' => 'Refund Status', 'group' => 'Refund', 'context_keys' => ['refund_status', 'order_status']],

            'business_logo' => ['label' => 'Business Logo', 'group' => 'Business', 'context_keys' => ['business_logo', 'email_logo_url', 'navbar_logo_url']],
            'business_name' => ['label' => 'Business Name', 'group' => 'Business', 'context_keys' => ['business_name']],
            'support_email' => ['label' => 'Support Email', 'group' => 'Business', 'context_keys' => ['support_email']],
            'support_phone' => ['label' => 'Support Phone', 'group' => 'Business', 'context_keys' => ['support_phone']],
            'support_whatsapp' => ['label' => 'Support WhatsApp', 'group' => 'Business', 'context_keys' => ['support_whatsapp', 'support_phone']],
            'support_whatsapp_url' => ['label' => 'Support WhatsApp URL', 'group' => 'Business', 'context_keys' => ['support_whatsapp_url']],
            'business_address' => ['label' => 'Business Address', 'group' => 'Business', 'context_keys' => ['business_address', 'business_address_line1', 'business_address_line2']],
            'business_website' => ['label' => 'Business Website', 'group' => 'Business', 'context_keys' => ['business_website']],
            'currency_symbol' => ['label' => 'Currency Symbol', 'group' => 'Business', 'context_keys' => ['currency_symbol']],

            'delivery_slot' => ['label' => 'Delivery Slot', 'group' => 'Delivery', 'context_keys' => ['delivery_slot', 'scheduled_slot_label', 'scheduled_slot']],
            'delivery_method' => ['label' => 'Delivery Method', 'group' => 'Delivery', 'context_keys' => ['delivery_method', 'fulfillment_mode', 'fulfilment_mode']],
            'delivery_address' => ['label' => 'Delivery Address', 'group' => 'Delivery', 'context_keys' => ['delivery_address']],

            'invoice_number' => ['label' => 'Invoice Number', 'group' => 'Invoice', 'context_keys' => ['invoice_number', 'order_number']],
            'invoice_date' => ['label' => 'Invoice Date', 'group' => 'Invoice', 'context_keys' => ['invoice_date', 'created_at', 'delivery_date']],
            'invoice_download_link' => ['label' => 'Invoice Download Link', 'group' => 'Invoice', 'context_keys' => ['invoice_download_link']],

            // Compatibility variables retained for runtime rendering only.
            'refund_type' => ['label' => 'Refund Type', 'group' => 'Compatibility', 'context_keys' => ['refund_type'], 'expose' => false],
            'refund_reference' => ['label' => 'Refund Reference', 'group' => 'Compatibility', 'context_keys' => ['refund_reference', 'transaction_reference'], 'expose' => false],
            'total_refunded' => ['label' => 'Total Refunded', 'group' => 'Compatibility', 'context_keys' => ['total_refunded', 'refund_amount'], 'expose' => false],
            'remaining_sales_amount' => ['label' => 'Remaining Sales Amount', 'group' => 'Compatibility', 'context_keys' => ['remaining_sales_amount'], 'expose' => false],
            'quote_number' => ['label' => 'Quote Number', 'group' => 'Compatibility', 'context_keys' => ['quote_number'], 'expose' => false],
            'quote_amount' => ['label' => 'Quote Amount', 'group' => 'Compatibility', 'context_keys' => ['quote_amount'], 'expose' => false],
            'quote_description' => ['label' => 'Quote Description', 'group' => 'Compatibility', 'context_keys' => ['quote_description'], 'expose' => false],
            'quote_accept_link' => ['label' => 'Quote Accept Link', 'group' => 'Compatibility', 'context_keys' => ['quote_accept_link'], 'expose' => false],
            'inquiry_id' => ['label' => 'Inquiry ID', 'group' => 'Compatibility', 'context_keys' => ['inquiry_id'], 'expose' => false],
            'advance_amount' => ['label' => 'Advance Amount', 'group' => 'Compatibility', 'context_keys' => ['advance_amount'], 'expose' => false],
            'budget_range' => ['label' => 'Budget Range', 'group' => 'Compatibility', 'context_keys' => ['budget_range'], 'expose' => false],
            'design_brief_notes' => ['label' => 'Design Brief Notes', 'group' => 'Compatibility', 'context_keys' => ['design_brief_notes'], 'expose' => false],
            'diet_preference' => ['label' => 'Diet Preference', 'group' => 'Compatibility', 'context_keys' => ['diet_preference'], 'expose' => false],
            'event_date' => ['label' => 'Event Date', 'group' => 'Compatibility', 'context_keys' => ['event_date'], 'expose' => false],
            'event_information' => ['label' => 'Event Information', 'group' => 'Compatibility', 'context_keys' => ['event_information'], 'expose' => false],
            'number_of_servings_guests' => ['label' => 'Guests Count', 'group' => 'Compatibility', 'context_keys' => ['number_of_servings_guests'], 'expose' => false],
            'google_review_link' => ['label' => 'Google Review Link', 'group' => 'Compatibility', 'context_keys' => ['google_review_link'], 'expose' => false],
            'last_order_month' => ['label' => 'Last Order Month', 'group' => 'Compatibility', 'context_keys' => ['last_order_month'], 'expose' => false],
            'otp_code' => ['label' => 'OTP Code', 'group' => 'Compatibility', 'context_keys' => ['otp_code'], 'expose' => false],
            'otp_expiry' => ['label' => 'OTP Expiry', 'group' => 'Compatibility', 'context_keys' => ['otp_expiry'], 'expose' => false],
            'phone_country_code' => ['label' => 'Phone Country Code', 'group' => 'Compatibility', 'context_keys' => ['phone_country_code'], 'expose' => false],
            'profile_link' => ['label' => 'Profile Link', 'group' => 'Compatibility', 'context_keys' => ['profile_link'], 'expose' => false],
            'reset_link' => ['label' => 'Reset Link', 'group' => 'Compatibility', 'context_keys' => ['reset_link'], 'expose' => false],
            'invoice_html' => ['label' => 'Invoice HTML', 'group' => 'Compatibility', 'context_keys' => ['invoice_html'], 'expose' => false],
        ];
    }

    public static function normalizeKey(string $key): string
    {
        $trimmed = trim($key);
        return self::ALIASES[$trimmed] ?? $trimmed;
    }

    public static function isRegistered(string $key): bool
    {
        $definitions = self::definitions();
        return isset($definitions[self::normalizeKey($key)]);
    }

    /** @return array<int,array{label:string,vars:array<int,array{name:string,token:string}>}> */
    public static function forEditorGroups(): array
    {
        $definitions = self::definitions();
        $groupOrder = ['Customer', 'Order', 'Payment', 'Coupon', 'Refund', 'Business', 'Delivery', 'Invoice'];

        $buckets = [];
        foreach ($groupOrder as $group) {
            $buckets[$group] = [];
        }

        foreach ($definitions as $key => $meta) {
            if (array_key_exists('expose', $meta) && $meta['expose'] === false) {
                continue;
            }

            $group = (string)($meta['group'] ?? 'Other');
            if (!isset($buckets[$group])) {
                $buckets[$group] = [];
            }

            $buckets[$group][] = [
                'name' => (string)($meta['label'] ?? $key),
                'token' => '{{' . $key . '}}',
            ];
        }

        $output = [];
        foreach ($buckets as $group => $vars) {
            if ($vars === []) {
                continue;
            }
            $output[] = ['label' => $group, 'vars' => $vars];
        }

        return $output;
    }

    /** @return array<int,array{title:string,menu:array<int,array{value:string,title:string}>}> */
    public static function forTinyMceMergeTags(): array
    {
        $groups = self::forEditorGroups();
        $result = [];
        foreach ($groups as $group) {
            $menu = [];
            foreach ($group['vars'] as $var) {
                $token = (string)$var['token'];
                $value = trim($token, '{}');
                $menu[] = [
                    'value' => $value,
                    'title' => (string)$var['name'],
                ];
            }

            $result[] = [
                'title' => (string)$group['label'],
                'menu' => $menu,
            ];
        }

        return $result;
    }

    /** @param array<string,mixed> $context */
    public static function resolveFromContext(string $key, array $context): string
    {
        $definitions = self::definitions();
        $normalizedKey = self::normalizeKey($key);
        $meta = $definitions[$normalizedKey] ?? null;
        if (!is_array($meta)) {
            return '';
        }

        $candidates = $meta['context_keys'] ?? [];
        if (!is_array($candidates)) {
            $candidates = [];
        }

        foreach ($candidates as $candidate) {
            $candidateKey = (string)$candidate;
            $value = $context[$candidateKey] ?? null;
            if (!is_scalar($value)) {
                continue;
            }
            $str = trim((string)$value);
            if ($str !== '') {
                return $str;
            }
        }

        if ($normalizedKey === 'support_whatsapp') {
            $phone = (string)($context['support_phone'] ?? '');
            return trim($phone);
        }

        if ($normalizedKey === 'support_whatsapp_url') {
            $phone = trim((string)($context['support_whatsapp'] ?? $context['support_phone'] ?? ''));
            if ($phone !== '') {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                if ($digits !== '') {
                    return 'https://wa.me/' . $digits;
                }
            }
        }

        if ($normalizedKey === 'delivery_method') {
            return trim((string)($context['delivery_method'] ?? $context['fulfillment_mode'] ?? $context['fulfilment_mode'] ?? ''));
        }

        if ($normalizedKey === 'payment_received_amount') {
            return (string)($context['payment_received_amount'] ?? $context['grand_total'] ?? '0.00');
        }

        if ($normalizedKey === 'remaining_sales_amount') {
            $grandTotal = (float)($context['grand_total'] ?? 0);
            $totalRefunded = (float)($context['total_refunded'] ?? $context['refund_amount'] ?? 0);
            return number_format(max(0, $grandTotal - $totalRefunded), 2, '.', '');
        }

        return '';
    }
}
