<?php
declare(strict_types=1);

namespace App\Services;

final class VariableResolverService
{
    /** @param array<string,mixed> $context */
    public function resolveValue(string $key, array $context, ?string $fallback = null): string
    {
        $normalized = TemplateVariableRegistry::normalizeKey($key);
        if ($normalized === '') {
            return $fallback ?? '';
        }

        $registryValue = TemplateVariableRegistry::resolveFromContext($normalized, $context);
        if ($registryValue !== '') {
            return $registryValue;
        }

        $value = $context[$normalized] ?? null;
        if (is_scalar($value) && trim((string)$value) !== '') {
            return trim((string)$value);
        }

        if ($normalized === 'first_name') {
            $fullName = trim((string)($context['customer_name'] ?? $context['full_name'] ?? ''));
            if ($fullName !== '') {
                $parts = preg_split('/\s+/', $fullName) ?: [];
                if (isset($parts[0]) && $parts[0] !== '') {
                    return $parts[0];
                }
            }
        }

        if ($normalized === 'customer_name') {
            $firstName = trim((string)($context['first_name'] ?? ''));
            if ($firstName !== '') {
                return $firstName;
            }
        }

        if ($normalized === 'payment_received_amount') {
            return (string)($context['payment_received_amount'] ?? $context['grand_total'] ?? '0.00');
        }
        if ($normalized === 'delivery_method') {
            return (string)($context['delivery_method'] ?? $context['fulfilment_mode'] ?? $context['fulfillment_mode'] ?? '');
        }
        if ($normalized === 'fulfillment_status' || $normalized === 'fulfilment_status') {
            return (string)($context[$normalized] ?? $context['order_status'] ?? '');
        }
        if ($normalized === 'delivery_slot') {
            return (string)($context['delivery_slot'] ?? $context['scheduled_slot_label'] ?? $context['scheduled_slot'] ?? '');
        }
        if ($normalized === 'transaction_reference') {
            return (string)($context['transaction_reference'] ?? $context['payment_reference'] ?? $context['utr_number'] ?? $context['settlement_reference'] ?? '');
        }
        if ($normalized === 'coupon_discount' || $normalized === 'discount_amount') {
            return (string)($context[$normalized] ?? $context['discount_total'] ?? '0.00');
        }
        if ($normalized === 'support_whatsapp_url') {
            $url = trim((string)($context['support_whatsapp_url'] ?? ''));
            if ($url !== '') {
                return $url;
            }

            $phone = (string)($context['support_whatsapp'] ?? $context['support_phone'] ?? '');
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if ($digits !== '') {
                return 'https://wa.me/' . $digits;
            }
        }

        // Refund-specific variables
        if ($normalized === 'refund_amount') {
            return (string)($context['refund_amount'] ?? '0.00');
        }
        if ($normalized === 'refund_reason') {
            return (string)($context['refund_reason'] ?? '');
        }
        if ($normalized === 'refund_type') {
            return (string)($context['refund_type'] ?? '');
        }
        if ($normalized === 'refund_notes') {
            return (string)($context['refund_notes'] ?? '');
        }
        if ($normalized === 'refund_reference') {
            return (string)($context['refund_reference'] ?? $context['settlement_reference'] ?? '');
        }
        if ($normalized === 'total_refunded') {
            return (string)($context['total_refunded'] ?? '0.00');
        }
        if ($normalized === 'remaining_sales_amount') {
            if (isset($context['remaining_sales_amount'])) {
                return (string)$context['remaining_sales_amount'];
            }
            $grandTotal  = (float)($context['grand_total']    ?? 0);
            $totalRef    = (float)($context['total_refunded'] ?? 0);
            return number_format(max(0.0, $grandTotal - $totalRef), 2);
        }

        if (!TemplateVariableRegistry::isRegistered($normalized)) {
            return '';
        }

        return $fallback ?? 'Valued Customer';
    }

    /** @param array<string,mixed> $context */
    public function render(string $template, array $context, string $defaultFallback = 'Valued Customer'): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($context, $defaultFallback): string {
            $key = (string)($matches[1] ?? '');
            return $this->resolveValue($key, $context, $defaultFallback);
        }, $template) ?? $template;
    }

    /** @param array<string,mixed> $context */
    public function renderStrict(string $template, array $context): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use ($context): string {
            $key = (string)($matches[1] ?? '');
            return $this->resolveValue($key, $context, '');
        }, $template) ?? $template;
    }

    /** @param array<int,array<string,mixed>> $variables
     *  @param array<string,mixed> $context
     *  @return array<int,array{type:string,text:string}>
     */
    public function buildMetaParameters(array $variables, array $context): array
    {
        usort($variables, static function (array $a, array $b): int {
            return ((int)($a['parameter_order'] ?? 0)) <=> ((int)($b['parameter_order'] ?? 0));
        });

        $params = [];
        foreach ($variables as $variable) {
            $params[] = [
                'type' => 'text',
                'text' => $this->resolveValue((string)($variable['variable_key'] ?? ''), $context, (string)($variable['fallback_value'] ?? 'Valued Customer')),
            ];
        }

        return $params;
    }

    /** @return array<string,string> */
    public function sampleContext(): array
    {
        return [
            // Branding (injected at send-time by EmailBrandingService)
            'email_logo_url'        => '/client/assets/images/mainlogo.svg',
            'business_name'         => 'Cakeouflage',
            'brand_primary_color'   => '#80001F',
            'brand_secondary_color' => '#140b0f',
            'support_email'         => 'support@cakeouflage.com',
            'support_phone'         => '+91 00000 00000',
            'support_whatsapp'      => '+91 00000 00000',
            'support_whatsapp_url'  => 'https://wa.me/910000000000',
            'business_logo'         => '/client/assets/images/mainlogo.svg',
            'business_address'      => '123 Celebration Street, Nashik, Maharashtra 422001',
            'business_website'      => 'https://www.cakeouflage.com',
            'currency_symbol'       => 'Rs',
            'currency_code'         => 'INR',
            // Customer / order context
            'customer_name' => 'Priya Sharma',
            'first_name' => 'Priya',
            'customer_email' => 'priya.sharma@example.com',
            'customer_phone' => '+91 98765 43210',
            'order_number' => 'CK1024',
            'order_status' => 'confirmed',
            'product_summary' => 'Chocolate Fantasy Cake x1',
            'payment_status' => 'paid',
            'payment_method' => 'upi',
            'transaction_reference' => 'UPI-REF-424242',
            'invoice_number' => 'INV-2026-0008',
            'invoice_date' => '2026-04-05',
            'invoice_download_link' => 'https://cakeouflage.com/order_invoice.php?id=123',
            'delivery_date' => '2026-04-06',
            'delivery_slot' => '09:00-11:00',
            'delivery_method' => 'delivery',
            'delivery_address' => '123 Celebration Street, Nashik',
            'pickup_time' => '05:30 PM',
            'course_name' => 'Beginner Cake Workshop',
            'batch_date' => '2026-04-18',
            'quote_number' => 'QTE-2026-0042',
            'company_name' => 'Nashik Corporate Events LLP',
            'cake_message' => 'Happy Birthday Sarah!',
            'topper_choice' => 'Happy Birthday Topper',
            'topper_price' => '₹0',
            'special_instructions' => 'No nuts please',
            'item_details' => '1× Chocolate Fantasy Cake',
            'item_names' => 'Chocolate Fantasy Cake',
            'item_count' => '1',
            'grand_total' => '1850.00',
            'payment_received_amount' => '1850.00',
            'coupon_discount' => '0.00',
            'coupon_code' => '',
            // Refund preview values
            'refund_amount'          => '1250.00',
            'refund_reason'          => 'Quality Complaint',
            'refund_status'          => 'processed',
        ];
    }
}