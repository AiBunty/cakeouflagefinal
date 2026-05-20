<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\MailService;
use App\Services\SmtpTransportService;
use App\Services\OrderAutomationService;
use App\Services\VariableResolverService;
use App\Services\WhatsAppDispatchService;
use App\Services\WhatsAppMetaApiService;
use PDO;
use Throwable;

final class QueueWorker
{
    /** @return array<string,mixed> */
    public static function process(PDO $pdo, int $maxJobs = 20): array
    {
        $max = min(200, max(1, $maxJobs));
        $processed = 0;
        $completed = 0;
        $failed = 0;
        $requeued = 0;
        $errors = [];

        for ($i = 0; $i < $max; $i++) {
            $job = self::claimNextJob($pdo);
            if ($job === null) {
                break;
            }

            $processed++;
            $jobId = (int)$job['id'];
            $attempts = (int)$job['attempts'];
            $jobType = (string)($job['job_type'] ?? '');

            try {
                self::executeJob($pdo, $job);
                self::markCompleted($pdo, $jobId);
                $completed++;
            } catch (Throwable $e) {
                $errors[] = ['job_id' => $jobId, 'error' => $e->getMessage()];
                if ($jobType === 'crm_trigger_push') {
                    self::markFailed($pdo, $jobId, $e->getMessage());
                    $failed++;
                } elseif ($attempts >= 5) {
                    self::markFailed($pdo, $jobId, $e->getMessage());
                    $failed++;
                } else {
                    self::requeue($pdo, $jobId, $attempts, $e->getMessage());
                    $requeued++;
                }
            }
        }

        return [
            'processed' => $processed,
            'completed' => $completed,
            'failed' => $failed,
            'requeued' => $requeued,
            'errors' => $errors,
        ];
    }

    /** @return array<string,mixed>|null */
    private static function claimNextJob(PDO $pdo): ?array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->query(
                'SELECT id, job_type, payload_json, attempts
                 FROM queue_jobs
                 WHERE status = "queued" AND available_at <= NOW()
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE'
            );
            $job = $stmt instanceof \PDOStatement ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!is_array($job)) {
                $pdo->commit();
                return null;
            }

            $update = $pdo->prepare('UPDATE queue_jobs SET status = "processing", attempts = attempts + 1, last_error = NULL WHERE id = :id');
            $update->execute(['id' => (int)$job['id']]);

            $job['attempts'] = (int)($job['attempts'] ?? 0) + 1;
            $pdo->commit();
            return $job;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $job */
    private static function executeJob(PDO $pdo, array $job): void
    {
        $jobType = (string)($job['job_type'] ?? '');
        $payloadRaw = (string)($job['payload_json'] ?? '');
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        if ($jobType === 'smtp_test_email') {
            self::executeSmtpTest($pdo, $payload);
            return;
        }

        if ($jobType === 'send_communication') {
            self::executeCommunication($pdo, $payload);
            return;
        }

        if ($jobType === 'crm_trigger_push') {
            self::executeCrmTriggerPush($pdo, $payload);
            return;
        }

        if ($jobType === 'birthday_reminder') {
            // Placeholder for future campaign pipeline.
            return;
        }

        throw new \RuntimeException('Unsupported job type: ' . $jobType);
    }

    /** @param array<string,mixed> $payload */
    private static function executeSmtpTest(PDO $pdo, array $payload): void
    {
        $recipient = trim((string)($payload['to'] ?? ''));
        if ($recipient === '') {
            throw new \RuntimeException('SMTP test recipient is missing');
        }

        $subject = trim((string)($payload['subject'] ?? 'Cakeouflage SMTP Test'));
        $body = "This is a SMTP queue test from Cakeouflage at " . date(DATE_ATOM);

        self::sendEmail($pdo, [$recipient], $subject, $body);

        if (isset($payload['log_id']) && (int)$payload['log_id'] > 0) {
            self::markCommunicationLogSent($pdo, (int)$payload['log_id'], 'smtp-test-' . date('YmdHis'));
        }
    }

    /** @param array<string,mixed> $payload */
    private static function executeCommunication(PDO $pdo, array $payload): void
    {
        $logId = (int)($payload['log_id'] ?? 0);
        if ($logId <= 0) {
            throw new \RuntimeException('Communication log id is required');
        }

        $stmt = $pdo->prepare('SELECT id, channel, event_key, recipient, payload_json FROM communication_logs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $logId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            throw new \RuntimeException('Communication log not found: ' . $logId);
        }

        $channel = (string)($log['channel'] ?? 'email');
        $recipient = trim((string)($log['recipient'] ?? ''));
        if ($recipient === '') {
            throw new \RuntimeException('Communication recipient is missing');
        }

        if ($channel === 'email') {
            $eventKey = trim((string)($log['event_key'] ?? ''));
            $templateStmt = $pdo->prepare('SELECT subject, body_template FROM communication_templates WHERE channel = "email" AND event_key = :event_key AND is_active = 1 LIMIT 1');
            $templateStmt->execute(['event_key' => $eventKey]);
            $template = $templateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $context = json_decode((string)($log['payload_json'] ?? ''), true);
            if (!is_array($context)) {
                $context = [];
            }

            $templateFound = isset($template['subject']) || isset($template['body_template']);
            if (!$templateFound) {
                $context['template_missing'] = true;
                $context['template_missing_reason'] = 'missing_active_template';
                $updatePayloadStmt = $pdo->prepare('UPDATE communication_logs SET payload_json = :payload_json WHERE id = :id');
                $updatePayloadStmt->execute([
                    'payload_json' => json_encode($context, JSON_UNESCAPED_SLASHES),
                    'id' => $logId,
                ]);

                throw new \RuntimeException('Missing active communication template for event key: ' . $eventKey);
            }

            $subject = trim((string)($template['subject'] ?? ($context['subject'] ?? 'Notification')));
            $body = trim((string)($template['body_template'] ?? ($context['body_template'] ?? '')));
            $subject = self::renderTemplate($subject, $context);
            $body = self::renderTemplate($body, $context);
            $attachments = self::extractAttachmentsFromContext($context);

            self::sendEmail($pdo, [$recipient], $subject, $body, $attachments);
            self::markCommunicationLogSent($pdo, $logId, 'smtp-' . date('YmdHis'));
            return;
        }

        if ($channel === 'whatsapp') {
            $waSettingsStmt = $pdo->query('SELECT * FROM whatsapp_settings ORDER BY id DESC LIMIT 1');
            $waSettings = $waSettingsStmt instanceof \PDOStatement ? ($waSettingsStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            $dispatch = new WhatsAppDispatchService(
                new WhatsAppMetaApiService($waSettings),
                new VariableResolverService()
            );
            $response = $dispatch->dispatchLog($pdo, $logId);
            $providerMessageId = (string)($response['messages'][0]['id'] ?? $response['message_id'] ?? 'meta-' . date('YmdHis'));
            self::markCommunicationLogSent($pdo, $logId, $providerMessageId);
            return;
        }

        self::markCommunicationLogSent($pdo, $logId);
    }

    /** @param array<string,mixed> $payload */
    private static function executeCrmTriggerPush(PDO $pdo, array $payload): void
    {
        $service = new OrderAutomationService();
        $result = $service->executeCrmTrigger($pdo, $payload);

        $followUpId = (int)($payload['follow_up_id'] ?? 0);
        if ($followUpId > 0) {
            $stmt = $pdo->prepare('UPDATE reminders SET status = "done", notes = :notes, updated_at = NOW() WHERE id = :id');
            $context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : [];
            $context['crm_result'] = $result;
            $stmt->execute([
                'notes' => json_encode($context, JSON_UNESCAPED_SLASHES),
                'id' => $followUpId,
            ]);
        }
    }

    /** @param array<string,mixed> $context */
    private static function renderTemplate(string $template, array $context): string
    {
        $output = $template;
        foreach ($context as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $output = str_replace('{{' . (string)$key . '}}', (string)$value, $output);
        }
        return $output;
    }

    /** @param array<int,string> $recipients
     *  @param array<int,array<string,string>> $attachments
     */
    private static function sendEmail(PDO $pdo, array $recipients, string $subject, string $body, array $attachments = []): void
    {
        $transport = SmtpTransportService::fromDatabase($pdo);
        if ($transport->isConfigured()) {
            $transport->send($recipients, $subject, $body, null, $attachments);
            return;
        }

        MailService::sendRawEmail($recipients, $subject, $body, $attachments);
    }

    /** @param array<string,mixed> $context
     *  @return array<int,array<string,string>>
     */
    private static function extractAttachmentsFromContext(array $context): array
    {
        $raw = $context['attachments'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $filename = trim((string)($item['filename'] ?? 'attachment.bin'));
            $mimeType = trim((string)($item['mime_type'] ?? 'application/octet-stream'));
            $contentBase64 = trim((string)($item['content_base64'] ?? ''));
            if ($filename === '' || $contentBase64 === '') {
                continue;
            }
            if (base64_decode($contentBase64, true) === false) {
                continue;
            }
            $out[] = [
                'filename' => $filename,
                'mime_type' => $mimeType,
                'content_base64' => $contentBase64,
            ];
        }

        return $out;
    }

    private static function markCommunicationLogSent(PDO $pdo, int $logId, string $providerMessageId = ''): void
    {
        $stmt = $pdo->prepare('UPDATE communication_logs SET status = "sent", provider_message_id = :provider_message_id, error_message = NULL, sent_at = NOW() WHERE id = :id');
        $stmt->execute([
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : 'local-delivery-' . date('YmdHis'),
            'id' => $logId,
        ]);

        $queueStmt = $pdo->prepare('UPDATE communication_queue SET queue_status = "completed", updated_at = NOW() WHERE communication_log_id = :communication_log_id');
        $queueStmt->execute(['communication_log_id' => $logId]);
    }

    private static function markCompleted(PDO $pdo, int $jobId): void
    {
        $stmt = $pdo->prepare('UPDATE queue_jobs SET status = "completed", updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $jobId]);
    }

    private static function markFailed(PDO $pdo, int $jobId, string $error): void
    {
        $stmt = $pdo->prepare('UPDATE queue_jobs SET status = "failed", last_error = :last_error, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['last_error' => mb_substr($error, 0, 250), 'id' => $jobId]);
    }

    private static function requeue(PDO $pdo, int $jobId, int $attempts, string $error): void
    {
        $delayMinutes = min(30, max(1, $attempts * 2));
        $stmt = $pdo->prepare(
            'UPDATE queue_jobs
             SET status = "queued", available_at = DATE_ADD(NOW(), INTERVAL :delay_minutes MINUTE), last_error = :last_error, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':delay_minutes', $delayMinutes, PDO::PARAM_INT);
        $stmt->bindValue(':last_error', mb_substr($error, 0, 250), PDO::PARAM_STR);
        $stmt->bindValue(':id', $jobId, PDO::PARAM_INT);
        $stmt->execute();

        $logId = self::findCommunicationLogIdForJob($pdo, $jobId);
        if ($logId > 0) {
            $logStmt = $pdo->prepare('UPDATE communication_logs SET status = "failed", error_message = :error_message WHERE id = :id');
            $logStmt->execute([
                'error_message' => mb_substr($error, 0, 250),
                'id' => $logId,
            ]);
        }

        $queueStmt = $pdo->prepare('UPDATE communication_queue SET queue_status = "failed", attempts = attempts + 1, last_error = :last_error, updated_at = NOW() WHERE communication_log_id = :communication_log_id');
        $queueStmt->execute([
            'last_error' => mb_substr($error, 0, 250),
            'communication_log_id' => $logId,
        ]);
    }

    private static function findCommunicationLogIdForJob(PDO $pdo, int $jobId): int
    {
        $stmt = $pdo->prepare('SELECT payload_json FROM queue_jobs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $jobId]);
        $raw = (string)($stmt->fetchColumn() ?: '');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return 0;
        }
        return (int)($decoded['log_id'] ?? 0);
    }
}
