<?php
declare(strict_types=1);

namespace App\Services;

final class WhatsAppTemplateRendererService
{
    /** @var VariableResolverService */
    private $resolver;

    public function __construct(?VariableResolverService $resolver = null)
    {
        $this->resolver = $resolver ?: new VariableResolverService();
    }

    /** @param array<string,mixed> $template
     *  @param array<int,array<string,mixed>> $variables
     *  @param array<int,array<string,mixed>> $buttons
     *  @param array<string,mixed>|null $context
     *  @return array<string,mixed>
     */
    public function preview(array $template, array $variables = [], array $buttons = [], ?array $context = null): array
    {
        $sample = $context ?? $this->resolver->sampleContext();
        $usedVariableKeys = [];
        foreach ($variables as $variable) {
            $usedVariableKeys[] = (string)($variable['variable_key'] ?? '');
        }

        $missing = [];
        foreach ($usedVariableKeys as $key) {
            if (!array_key_exists($key, $sample) && !in_array($key, ['first_name', 'customer_name'], true)) {
                $missing[] = $key;
            }
        }

        return [
            'header' => $this->resolver->render((string)($template['header_text'] ?? ''), $sample),
            'body' => $this->resolver->render((string)($template['body_text'] ?? ''), $sample),
            'footer' => $this->resolver->render((string)($template['footer_text'] ?? ''), $sample, ''),
            'buttons' => array_map(static function (array $button): array {
                return [
                    'type' => (string)($button['button_type'] ?? 'quick_reply'),
                    'text' => (string)($button['button_text'] ?? ''),
                    'value' => (string)($button['button_value'] ?? ''),
                ];
            }, $buttons),
            'missing_variables' => array_values(array_unique(array_filter($missing))),
            'sample_context' => $sample,
        ];
    }
}