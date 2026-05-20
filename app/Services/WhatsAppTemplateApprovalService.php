<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class WhatsAppTemplateApprovalService
{
    /** @var WhatsAppTemplateBuilderService */
    private $builder;

    /** @var WhatsAppMetaApiService */
    private $metaApi;

    public function __construct(
        WhatsAppTemplateBuilderService $builder,
        WhatsAppMetaApiService $metaApi
    ) {
        $this->builder = $builder;
        $this->metaApi = $metaApi;
    }

    /** @return array<string,mixed> */
    public function submit(PDO $pdo, int $templateId, int $adminId): array
    {
        $template = $this->loadTemplate($pdo, $templateId);
        if ($template === null) {
            throw new \RuntimeException('Template not found');
        }

        $buttons = $this->loadButtons($pdo, $templateId);
        $build = $this->builder->build($template, $buttons);
        if (($build['success'] ?? false) !== true) {
            return [
                'success' => false,
                'errors' => $build['errors'] ?? ['Template validation failed'],
            ];
        }

        $response = $this->metaApi->submitTemplate($build['payload']);
        $reference = (string)($response['id'] ?? $response['name'] ?? $template['meta_template_name']);
        $status = 'submitted';

        $update = $pdo->prepare('UPDATE whatsapp_templates SET meta_template_name = :meta_template_name, meta_template_id_or_reference = :reference, approval_status = :approval_status, approval_reason = NULL, sync_status = "pending_sync", last_synced_at = NOW(), updated_by = :updated_by WHERE id = :id');
        $update->execute([
            'meta_template_name' => $build['payload']['name'],
            'reference' => $reference,
            'approval_status' => $status,
            'updated_by' => $adminId,
            'id' => $templateId,
        ]);

        $log = $pdo->prepare('INSERT INTO whatsapp_template_approval_logs (template_id, previous_status, new_status, meta_reason, response_payload_json, changed_by) VALUES (:template_id, :previous_status, :new_status, :meta_reason, :response_payload_json, :changed_by)');
        $log->execute([
            'template_id' => $templateId,
            'previous_status' => (string)($template['approval_status'] ?? 'draft'),
            'new_status' => $status,
            'meta_reason' => null,
            'response_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'changed_by' => $adminId,
        ]);

        $syncLog = $pdo->prepare('INSERT INTO whatsapp_template_sync_logs (template_id, sync_direction, status, request_payload_json, response_payload_json, message, synced_by) VALUES (:template_id, :sync_direction, :status, :request_payload_json, :response_payload_json, :message, :synced_by)');
        $syncLog->execute([
            'template_id' => $templateId,
            'sync_direction' => 'push_to_meta',
            'status' => 'success',
            'request_payload_json' => json_encode($build['payload'], JSON_UNESCAPED_SLASHES),
            'response_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'message' => 'Template submitted to Meta',
            'synced_by' => $adminId,
        ]);

        return [
            'success' => true,
            'payload' => $build['payload'],
            'response' => $response,
        ];
    }

    /** @return array<string,mixed>|null */
    private function loadTemplate(PDO $pdo, int $templateId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($template) ? $template : null;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadButtons(PDO $pdo, int $templateId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_template_buttons WHERE template_id = :template_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['template_id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}