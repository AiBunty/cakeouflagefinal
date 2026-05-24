<?php
declare(strict_types=1);

namespace App\Services;

final class VariableResolverService
{
    /** @param array<string,mixed> $context */
    public function resolveValue(string $key, array $context, ?string $fallback = null): string
    {
        $normalized = trim($key);
        if ($normalized === '') {
            return $fallback ?? '';
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
            'email_logo_url'        => 'https://via.placeholder.com/240x80/80001F/ffffff?text=YOUR+LOGO',
            'business_name'         => 'Cakeouflage',
            'brand_primary_color'   => '#80001F',
            'brand_secondary_color' => '#140b0f',
            'support_email'         => 'support@cakeouflage.com',
            'support_phone'         => '+91 00000 00000',
            // Customer / order context
            'customer_name' => 'Priya Sharma',
            'first_name' => 'Priya',
            'order_number' => 'CK1024',
            'invoice_number' => 'INV-2026-0008',
            'invoice_amount' => 'INR 1,850',
            'due_date' => '2026-04-05',
            'delivery_date' => '2026-04-06',
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
            // Refund preview values
            'refund_amount'          => '1250.00',
            'refund_reason'          => 'Quality Complaint',
            'refund_type'            => 'Partial',
            'refund_notes'           => 'Customer received damaged cake',
            'refund_reference'       => 'REF-20260523-A1B2',
            'total_refunded'         => '1250.00',
            'remaining_sales_amount' => '600.00',
        ];
    }
}