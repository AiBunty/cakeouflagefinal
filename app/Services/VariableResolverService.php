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
        ];
    }
}