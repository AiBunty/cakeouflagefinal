<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class WhatsAppTemplateSyncService
{
    /** @var WhatsAppMetaApiService */
    private $metaApi;

    public function __construct(WhatsAppMetaApiService $metaApi)
    {
        $this->metaApi = $metaApi;
    }

    /** @return array<string,mixed> */
    public function sync(PDO $pdo, int $adminId): array
    {
        $remoteTemplates = $this->metaApi->fetchTemplates();
        $imported = 0;
        $updated = 0;

        $find = $pdo->prepare('SELECT id, approval_status FROM whatsapp_templates WHERE meta_template_name = :meta_template_name LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO whatsapp_templates (internal_name, template_key, meta_template_name, meta_template_id_or_reference, waba_id, phone_number_id, category, language_code, header_type, header_text, body_text, footer_text, buttons_json, variables_json, approval_status, approval_reason, sync_status, last_synced_at, is_active, created_by, updated_by) VALUES (:internal_name, :template_key, :meta_template_name, :meta_template_id_or_reference, :waba_id, :phone_number_id, :category, :language_code, :header_type, :header_text, :body_text, :footer_text, :buttons_json, :variables_json, :approval_status, :approval_reason, :sync_status, NOW(), 1, :created_by, :updated_by)');
        $update = $pdo->prepare('UPDATE whatsapp_templates SET meta_template_id_or_reference = :reference, category = :category, language_code = :language_code, approval_status = :approval_status, approval_reason = :approval_reason, sync_status = "synced", last_synced_at = NOW(), updated_by = :updated_by WHERE id = :id');
        $syncLog = $pdo->prepare('INSERT INTO whatsapp_template_sync_logs (template_id, sync_direction, status, request_payload_json, response_payload_json, message, synced_by) VALUES (:template_id, :sync_direction, :status, :request_payload_json, :response_payload_json, :message, :synced_by)');

        foreach ($remoteTemplates as $remote) {
            $name = strtolower(trim((string)($remote['name'] ?? '')));
            if ($name === '') {
                continue;
            }

            $find->execute(['meta_template_name' => $name]);
            $existing = $find->fetch(PDO::FETCH_ASSOC) ?: null;

            $status = strtolower(trim((string)($remote['status'] ?? 'in_review')));
            $reason = (string)($remote['rejected_reason'] ?? $remote['reason'] ?? '');
            if ($existing) {
                $update->execute([
                    'reference' => (string)($remote['id'] ?? $name),
                    'category' => strtolower((string)($remote['category'] ?? 'utility')),
                    'language_code' => (string)($remote['language'] ?? 'en_US'),
                    'approval_status' => $this->normalizeApprovalStatus($status),
                    'approval_reason' => $reason !== '' ? $reason : null,
                    'updated_by' => $adminId > 0 ? $adminId : null,
                    'id' => (int)$existing['id'],
                ]);
                $templateId = (int)$existing['id'];
                $updated++;
            } else {
                $templateKey = preg_replace('/[^a-z0-9_]+/', '_', $name) ?: 'meta_template';
                $insert->execute([
                    'internal_name' => ucwords(str_replace('_', ' ', $templateKey)),
                    'template_key' => $templateKey . '_' . substr(md5($name), 0, 6),
                    'meta_template_name' => $name,
                    'meta_template_id_or_reference' => (string)($remote['id'] ?? $name),
                    'waba_id' => (string)($remote['waba_id'] ?? ''),
                    'phone_number_id' => (string)($remote['phone_number_id'] ?? ''),
                    'category' => strtolower((string)($remote['category'] ?? 'utility')),
                    'language_code' => (string)($remote['language'] ?? 'en_US'),
                    'header_type' => 'none',
                    'header_text' => null,
                    'body_text' => (string)($remote['components'][0]['text'] ?? 'Imported from Meta'),
                    'footer_text' => null,
                    'buttons_json' => json_encode([], JSON_UNESCAPED_SLASHES),
                    'variables_json' => json_encode([], JSON_UNESCAPED_SLASHES),
                    'approval_status' => $this->normalizeApprovalStatus($status),
                    'approval_reason' => $reason !== '' ? $reason : null,
                    'sync_status' => 'synced',
                    'created_by' => $adminId > 0 ? $adminId : null,
                    'updated_by' => $adminId > 0 ? $adminId : null,
                ]);
                $templateId = (int)$pdo->lastInsertId();
                $imported++;
            }

            $syncLog->execute([
                'template_id' => $templateId,
                'sync_direction' => 'pull_from_meta',
                'status' => 'success',
                'request_payload_json' => null,
                'response_payload_json' => json_encode($remote, JSON_UNESCAPED_SLASHES),
                'message' => 'Template synchronized from Meta',
                'synced_by' => $adminId > 0 ? $adminId : null,
            ]);
        }

        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'count' => count($remoteTemplates),
        ];
    }

    private function normalizeApprovalStatus(string $status): string
    {
        $map = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'paused' => 'paused',
            'disabled' => 'disabled',
            'pending' => 'submitted',
            'pending_deletion' => 'disabled',
            'in_review' => 'in_review',
        ];

        return $map[$status] ?? 'in_review';
    }
}