<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class WhatsAppDispatchService
{
    /** @var WhatsAppMetaApiService */
    private $metaApi;

    /** @var VariableResolverService */
    private $resolver;

    public function __construct(
        WhatsAppMetaApiService $metaApi,
        ?VariableResolverService $resolver = null
    ) {
        $this->metaApi = $metaApi;
        $this->resolver = $resolver ?: new VariableResolverService();
    }

    /** @param array<string,mixed> $context
     *  @return array<string,mixed>
     */
    public function testSend(PDO $pdo, int $templateId, string $recipient, array $context): array
    {
        $template = $this->loadTemplate($pdo, $templateId);
        if ($template === null) {
            throw new \RuntimeException('WhatsApp template not found');
        }
        if ((string)($template['approval_status'] ?? '') !== 'approved') {
            throw new \RuntimeException('Only approved templates can be sent');
        }

        $variables = $this->loadVariables($pdo, $templateId);
        $buttons = $this->loadButtons($pdo, $templateId);
        $payload = $this->buildSendPayload($template, $variables, $buttons, $recipient, $context);
        return $this->metaApi->sendTemplateMessage($payload);
    }

    /** @return array<string,mixed> */
    public function dispatchLog(PDO $pdo, int $logId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM communication_logs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            throw new \RuntimeException('Communication log not found');
        }

        $eventKey = trim((string)($log['event_key'] ?? ''));
        $mapStmt = $pdo->prepare('SELECT m.template_id, t.approval_status FROM whatsapp_template_mappings m JOIN whatsapp_templates t ON t.id = m.template_id WHERE m.event_key = :event_key AND m.is_active = 1 LIMIT 1');
        $mapStmt->execute(['event_key' => $eventKey]);
        $mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);
        if (!$mapping) {
            throw new \RuntimeException('No active approved WhatsApp template mapping for event: ' . $eventKey);
        }

        $templateId = (int)($mapping['template_id'] ?? 0);
        if ((string)($mapping['approval_status'] ?? '') !== 'approved') {
            throw new \RuntimeException('Mapped WhatsApp template is not approved');
        }

        $context = json_decode((string)($log['payload_json'] ?? ''), true);
        if (!is_array($context)) {
            $context = [];
        }

        $response = $this->testSend($pdo, $templateId, (string)$log['recipient'], $context);
        $providerMessageId = (string)($response['messages'][0]['id'] ?? $response['message_id'] ?? 'meta-sent');

        $update = $pdo->prepare('UPDATE communication_logs SET whatsapp_template_id = :template_id, provider_message_id = :provider_message_id, status = "sent", error_message = NULL, sent_at = NOW() WHERE id = :id');
        $update->execute([
            'template_id' => $templateId,
            'provider_message_id' => $providerMessageId,
            'id' => $logId,
        ]);

        return $response;
    }

    /** @param array<string,mixed> $template
     *  @param array<int,array<string,mixed>> $variables
     *  @param array<int,array<string,mixed>> $buttons
     *  @param array<string,mixed> $context
     *  @return array<string,mixed>
     */
    private function buildSendPayload(array $template, array $variables, array $buttons, string $recipient, array $context): array
    {
        $components = [];

        $bodyVars = array_values(array_filter($variables, static function (array $variable): bool {
            return (string)($variable['component_scope'] ?? 'body') === 'body';
        }));
        if (count($bodyVars) > 0) {
            $components[] = [
                'type' => 'body',
                'parameters' => $this->resolver->buildMetaParameters($bodyVars, $context),
            ];
        }

        $headerVars = array_values(array_filter($variables, static function (array $variable): bool {
            return (string)($variable['component_scope'] ?? '') === 'header';
        }));
        if ((string)($template['header_type'] ?? 'none') === 'text' && count($headerVars) > 0) {
            $components[] = [
                'type' => 'header',
                'parameters' => $this->resolver->buildMetaParameters($headerVars, $context),
            ];
        }

        foreach ($buttons as $index => $button) {
            if ((string)($button['button_type'] ?? 'quick_reply') !== 'url') {
                continue;
            }
            $value = trim((string)($button['button_value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string)$index,
                'parameters' => [[
                    'type' => 'text',
                    'text' => $this->resolver->render($value, $context, ''),
                ]],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => (string)$template['meta_template_name'],
                'language' => [
                    'code' => (string)($template['language_code'] ?? 'en_US'),
                ],
                'components' => $components,
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private function loadTemplate(PDO $pdo, int $templateId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadVariables(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_template_variables WHERE template_id = :template_id ORDER BY component_scope ASC, parameter_order ASC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function loadButtons(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_template_buttons WHERE template_id = :template_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}