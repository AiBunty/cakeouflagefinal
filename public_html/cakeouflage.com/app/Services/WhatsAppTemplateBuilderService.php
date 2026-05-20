<?php
declare(strict_types=1);

namespace App\Services;

final class WhatsAppTemplateBuilderService
{
    /** @param array<string,mixed> $template
     *  @param array<int,array<string,mixed>> $buttons
     *  @return array<string,mixed>
     */
    public function build(array $template, array $buttons = []): array
    {
        $errors = $this->validate($template, $buttons);
        if (count($errors) > 0) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $headerText = trim((string)($template['header_text'] ?? ''));
        $bodyText = trim((string)($template['body_text'] ?? ''));
        $footerText = trim((string)($template['footer_text'] ?? ''));
        $headerType = (string)($template['header_type'] ?? 'none');

        $headerBuild = $this->convertReadableVariables($headerText);
        $bodyBuild = $this->convertReadableVariables($bodyText);
        $footerBuild = $this->convertReadableVariables($footerText);

        $components = [];
        $variables = [];

        if ($headerType !== 'none') {
            $component = ['type' => 'HEADER', 'format' => strtoupper($headerType)];
            if ($headerType === 'text') {
                $component['text'] = $headerBuild['text'];
                foreach ($headerBuild['variables'] as $order => $key) {
                    $variables[] = [
                        'variable_key' => $key,
                        'variable_label' => ucwords(str_replace('_', ' ', $key)),
                        'component_scope' => 'header',
                        'parameter_order' => $order + 1,
                        'fallback_value' => $key === 'first_name' ? 'Valued Customer' : null,
                        'is_required' => 1,
                    ];
                }
            } else {
                $component['example'] = ['header_handle' => [(string)($template['header_media_example'] ?? '')]];
            }
            $components[] = $component;
        }

        $components[] = ['type' => 'BODY', 'text' => $bodyBuild['text']];
        foreach ($bodyBuild['variables'] as $order => $key) {
            $variables[] = [
                'variable_key' => $key,
                'variable_label' => ucwords(str_replace('_', ' ', $key)),
                'component_scope' => 'body',
                'parameter_order' => $order + 1,
                'fallback_value' => $key === 'first_name' ? 'Valued Customer' : null,
                'is_required' => 1,
            ];
        }

        if ($footerText !== '') {
            $components[] = ['type' => 'FOOTER', 'text' => $footerBuild['text']];
        }

        if (count($buttons) > 0) {
            $buttonComponents = [];
            foreach ($buttons as $index => $button) {
                $buttonType = strtoupper((string)($button['button_type'] ?? 'quick_reply'));
                $entry = [
                    'type' => 'BUTTONS',
                    'buttons' => [[
                        'type' => $buttonType === 'PHONE' ? 'PHONE_NUMBER' : ($buttonType === 'URL' ? 'URL' : 'QUICK_REPLY'),
                        'text' => trim((string)($button['button_text'] ?? '')),
                    ]],
                ];
                $value = trim((string)($button['button_value'] ?? ''));
                if ($value !== '') {
                    if ($buttonType === 'PHONE') {
                        $entry['buttons'][0]['phone_number'] = $value;
                    } elseif ($buttonType === 'URL') {
                        $entry['buttons'][0]['url'] = $value;
                    }
                }
                $entry['buttons'][0]['index'] = (string)$index;
                $buttonComponents[] = $entry;
            }
            $components = array_merge($components, $buttonComponents);
        }

        $payload = [
            'name' => $this->normalizeMetaTemplateName((string)($template['meta_template_name'] ?? $template['template_key'] ?? '')),
            'language' => (string)($template['language_code'] ?? 'en_US'),
            'category' => strtoupper((string)($template['category'] ?? 'utility')),
            'components' => $components,
        ];

        return [
            'success' => true,
            'payload' => $payload,
            'variables' => $variables,
            'meta_body_text' => $bodyBuild['text'],
        ];
    }

    /** @param array<string,mixed> $template
     *  @param array<int,array<string,mixed>> $buttons
     *  @return array<int,string>
     */
    public function validate(array $template, array $buttons = []): array
    {
        $errors = [];
        $category = (string)($template['category'] ?? '');
        if (!in_array($category, ['utility', 'marketing', 'authentication'], true)) {
            $errors[] = 'Category must be utility, marketing, or authentication';
        }

        $language = trim((string)($template['language_code'] ?? ''));
        if ($language === '') {
            $errors[] = 'Language is required';
        }

        $metaName = $this->normalizeMetaTemplateName((string)($template['meta_template_name'] ?? $template['template_key'] ?? ''));
        if ($metaName === '') {
            $errors[] = 'Meta template name is required';
        }

        $body = trim((string)($template['body_text'] ?? ''));
        if ($body === '') {
            $errors[] = 'Body content is required';
        }

        $headerType = (string)($template['header_type'] ?? 'none');
        if (!in_array($headerType, ['none', 'text', 'image', 'video', 'document'], true)) {
            $errors[] = 'Invalid header type';
        }
        if ($headerType === 'text' && trim((string)($template['header_text'] ?? '')) === '') {
            $errors[] = 'Header text is required when header type is text';
        }

        if (count($buttons) > 10) {
            $errors[] = 'Meta allows a maximum of 10 buttons';
        }

        foreach ($buttons as $button) {
            $text = trim((string)($button['button_text'] ?? ''));
            if ($text === '') {
                $errors[] = 'Button text is required';
            }
        }

        return $errors;
    }

    public function normalizeMetaTemplateName(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');
        return substr($normalized, 0, 180);
    }

    /** @return array{text:string,variables:array<int,string>} */
    private function convertReadableVariables(string $content): array
    {
        $variables = [];
        $text = preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $matches) use (&$variables): string {
            $key = strtolower((string)($matches[1] ?? ''));
            $index = array_search($key, $variables, true);
            if ($index === false) {
                $variables[] = $key;
                $index = count($variables) - 1;
            }
            return '{{' . ($index + 1) . '}}';
        }, $content) ?? $content;

        return [
            'text' => $text,
            'variables' => $variables,
        ];
    }
}