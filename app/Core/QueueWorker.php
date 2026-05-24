<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\EmailBrandingService;
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
                if ($jobType === 'media_transcode') {
                    $payloadRaw = (string)($job['payload_json'] ?? '');
                    $payloadDecoded = json_decode($payloadRaw, true);
                    if (is_array($payloadDecoded)) {
                        $canonicalPath = trim((string)($payloadDecoded['canonical_path'] ?? ''));
                        if ($canonicalPath !== '') {
                            $retryState = $attempts >= 5 ? 'failed' : 'queued';
                            self::setMediaAssetStatus($pdo, $canonicalPath, $retryState, mb_substr($e->getMessage(), 0, 250));
                        }
                    }
                }
                if ($jobType === 'media_image_optimize') {
                    $payloadRaw = (string)($job['payload_json'] ?? '');
                    $payloadDecoded = json_decode($payloadRaw, true);
                    if (is_array($payloadDecoded)) {
                        $mediaQueueId = (int)($payloadDecoded['media_queue_id'] ?? 0);
                        $retryState = $attempts >= 5 ? 'failed' : 'pending';
                        self::setMediaProcessingStatus($pdo, $mediaQueueId, $retryState, mb_substr($e->getMessage(), 0, 250));
                    }
                }
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

        if ($jobType === 'media_transcode') {
            self::executeMediaTranscode($pdo, $payload);
            return;
        }

        if ($jobType === 'media_image_optimize') {
            self::executeMediaImageOptimize($pdo, $payload);
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

            // Inject branding vars — fills {{email_logo_url}}, {{business_name}} etc.
            // Branding is low-priority: caller-supplied context values take precedence.
            $branding = EmailBrandingService::getEmailBranding($pdo);
            $context = array_merge($branding, $context);

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

    /** @param array<string,mixed> $payload */
    private static function executeMediaTranscode(PDO $pdo, array $payload): void
    {
        $sourcePath = trim((string)($payload['source_path'] ?? ''));
        $canonicalPath = trim((string)($payload['canonical_path'] ?? ''));

        if (!self::isValidMediaRelativePath($sourcePath) || !self::isValidMediaRelativePath($canonicalPath)) {
            throw new \RuntimeException('Invalid media conversion path payload');
        }

        $sourceAbsolute = self::resolveMediaAbsolutePath($sourcePath);
        $canonicalAbsolute = self::resolveMediaAbsolutePath($canonicalPath);
        if ($sourceAbsolute === null || $canonicalAbsolute === null || !is_file($sourceAbsolute)) {
            throw new \RuntimeException('Source media file missing for conversion');
        }

        $canonicalDir = dirname($canonicalAbsolute);
        if (!is_dir($canonicalDir)) {
            @mkdir($canonicalDir, 0775, true);
        }

        self::setMediaAssetStatus($pdo, $canonicalPath, 'processing', null);

        $ffmpeg = self::findFfmpegBinary();
        if ($ffmpeg === null) {
            $sourceExt = strtolower((string)pathinfo($sourceAbsolute, PATHINFO_EXTENSION));
            if ($sourceExt === 'mp4') {
                if ($sourceAbsolute !== $canonicalAbsolute) {
                    if (!@rename($sourceAbsolute, $canonicalAbsolute)) {
                        if (!@copy($sourceAbsolute, $canonicalAbsolute)) {
                            throw new \RuntimeException('FFmpeg unavailable and fallback copy failed');
                        }
                        @unlink($sourceAbsolute);
                    }
                }
            } else {
                throw new \RuntimeException('FFmpeg is not available for non-MP4 source conversion');
            }
        } else {
            $command = escapeshellcmd($ffmpeg)
                . ' -y -i ' . escapeshellarg($sourceAbsolute)
                . ' -map 0:v:0 -map 0:a? -c:v libx264 -preset veryfast -crf 23'
                . ' -vf ' . escapeshellarg('scale=min(1920,iw):-2:force_original_aspect_ratio=decrease')
                . ' -c:a aac -b:a 128k -movflags +faststart '
                . escapeshellarg($canonicalAbsolute)
                . ' 2>&1';

            $output = [];
            $exitCode = 1;
            @exec($command, $output, $exitCode);
            if ($exitCode !== 0 || !is_file($canonicalAbsolute)) {
                @unlink($canonicalAbsolute);
                throw new \RuntimeException('Video conversion failed via FFmpeg');
            }

            if ($sourceAbsolute !== $canonicalAbsolute) {
                @unlink($sourceAbsolute);
            }
        }

        $canonicalSize = filesize($canonicalAbsolute);
        if ($canonicalSize === false || $canonicalSize <= 0) {
            throw new \RuntimeException('Converted media output is invalid');
        }

        self::setMediaAssetStatus($pdo, $canonicalPath, 'ready', null, (int)$canonicalSize);
    }

    /** @param array<string,mixed> $payload */
    private static function executeMediaImageOptimize(PDO $pdo, array $payload): void
    {
        $queueId = (int)($payload['media_queue_id'] ?? 0);
        $originalPath = trim((string)($payload['original_path'] ?? ''));
        $optimizedPath = trim((string)($payload['optimized_path'] ?? ''));

        if (!self::isValidUnifiedUploadPath($originalPath) || !self::isValidUnifiedUploadPath($optimizedPath)) {
            throw new \RuntimeException('Invalid unified media optimization payload');
        }

        $sourceAbs = self::resolveUnifiedUploadAbsolutePath($originalPath);
        $optimizedAbs = self::resolveUnifiedUploadAbsolutePath($optimizedPath);
        if ($sourceAbs === null || $optimizedAbs === null || !is_file($sourceAbs)) {
            throw new \RuntimeException('Unified media source file missing');
        }

        self::setMediaProcessingStatus($pdo, $queueId, 'processing', null);

        self::ensureDir(dirname($optimizedAbs));

        // SVG cannot be converted via GD. Keep original and mark completed.
        $ext = strtolower((string)pathinfo($sourceAbs, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            self::setMediaProcessingStatus($pdo, $queueId, 'completed', null);
            return;
        }

        self::generateVariant($sourceAbs, $optimizedAbs, 1200, 85);

        $variants = [
            'thumbnail' => 400,
            'mobile' => 600,
            'grid' => 800,
            'detail' => 1200,
            'retina' => 1600,
        ];

        foreach ($variants as $name => $size) {
            $variantPath = self::deriveUnifiedVariantPath($optimizedPath, $name);
            $variantAbs = self::resolveUnifiedUploadAbsolutePath($variantPath);
            if ($variantAbs === null) {
                continue;
            }
            self::ensureDir(dirname($variantAbs));
            self::generateVariant($sourceAbs, $variantAbs, $size, 85);
        }

        self::setMediaProcessingStatus($pdo, $queueId, 'completed', null);
    }

    private static function setMediaProcessingStatus(PDO $pdo, int $queueId, string $status, ?string $error): void
    {
        if ($queueId <= 0) {
            return;
        }

        $exists = $pdo->query("SHOW TABLES LIKE 'media_processing_queue'");
        if (!($exists instanceof \PDOStatement) || !$exists->fetchColumn()) {
            return;
        }

        if ($status === 'processing') {
            $stmt = $pdo->prepare(
                'UPDATE media_processing_queue
                 SET processing_status = :processing_status,
                     attempts = attempts + 1,
                     last_error = :last_error,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'processing_status' => $status,
                'last_error' => $error,
                'id' => $queueId,
            ]);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE media_processing_queue
             SET processing_status = :processing_status,
                 last_error = :last_error,
                 processed_at = CASE WHEN :is_terminal = 1 THEN NOW() ELSE processed_at END,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'processing_status' => $status,
            'last_error' => $error,
            'is_terminal' => in_array($status, ['completed', 'failed'], true) ? 1 : 0,
            'id' => $queueId,
        ]);
    }

    private static function generateVariant(string $sourceAbs, string $targetAbs, int $maxDimension, int $quality): void
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            self::generateVariantViaCwebp($sourceAbs, $targetAbs, $maxDimension, $quality);
            return;
        }

        $raw = @file_get_contents($sourceAbs);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Failed to read source image for optimization');
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            throw new \RuntimeException('Invalid source image for optimization');
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        if ($origW <= $maxDimension && $origH <= $maxDimension) {
            $newW = $origW;
            $newH = $origH;
        } elseif ($origW >= $origH) {
            $newW = $maxDimension;
            $newH = (int)round($origH * $maxDimension / max(1, $origW));
        } else {
            $newH = $maxDimension;
            $newW = (int)round($origW * $maxDimension / max(1, $origH));
        }

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Failed to create destination image for optimization');
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        if (!imagewebp($dst, $targetAbs, $quality)) {
            imagedestroy($src);
            imagedestroy($dst);
            throw new \RuntimeException('Failed writing optimized WebP image');
        }

        imagedestroy($src);
        imagedestroy($dst);
    }

    private static function generateVariantViaCwebp(string $sourceAbs, string $targetAbs, int $maxDimension, int $quality): void
    {
        $cwebp = self::findCwebpBinary();
        if ($cwebp === null) {
            throw new \RuntimeException('No WebP encoder available (imagewebp/cwebp missing)');
        }

        $dims = @getimagesize($sourceAbs);
        if (!is_array($dims) || !isset($dims[0], $dims[1])) {
            throw new \RuntimeException('Failed reading source image dimensions');
        }
        $origW = (int)$dims[0];
        $origH = (int)$dims[1];
        if ($origW <= 0 || $origH <= 0) {
            throw new \RuntimeException('Invalid source image dimensions');
        }

        if ($origW <= $maxDimension && $origH <= $maxDimension) {
            $newW = $origW;
            $newH = $origH;
        } elseif ($origW >= $origH) {
            $newW = $maxDimension;
            $newH = (int)round($origH * $maxDimension / max(1, $origW));
        } else {
            $newH = $maxDimension;
            $newW = (int)round($origW * $maxDimension / max(1, $origH));
        }

        $cmd = escapeshellcmd($cwebp)
            . ' -q ' . (int)$quality
            . ' -resize ' . (int)$newW . ' ' . (int)$newH
            . ' ' . escapeshellarg($sourceAbs)
            . ' -o ' . escapeshellarg($targetAbs)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($cmd, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($targetAbs)) {
            throw new \RuntimeException('cwebp conversion failed');
        }
    }

    private static function findCwebpBinary(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $disabled = (string)ini_get('disable_functions');
        if ($disabled !== '') {
            $list = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $list, true)) {
                return null;
            }
        }

        $candidates = ['cwebp', '/usr/bin/cwebp', '/usr/local/bin/cwebp'];
        foreach ($candidates as $candidate) {
            $output = [];
            $exitCode = 1;
            @exec(escapeshellcmd($candidate) . ' -version 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private static function deriveUnifiedVariantPath(string $optimizedPath, string $variant): string
    {
        $parts = pathinfo($optimizedPath);
        $dir = (string)($parts['dirname'] ?? '');
        $file = (string)($parts['filename'] ?? '');
        $bucket = trim(str_replace('/public/uploads/optimized/', '', $dir), '/');
        return '/public/uploads/thumbnails/' . $bucket . '/' . $file . '_' . $variant . '.webp';
    }

    private static function isValidUnifiedUploadPath(string $path): bool
    {
        return (str_starts_with($path, '/public/uploads/originals/') || str_starts_with($path, '/public/uploads/optimized/') || str_starts_with($path, '/public/uploads/thumbnails/'))
            && strpos($path, '..') === false
            && strpos($path, "\0") === false;
    }

    private static function resolveUnifiedUploadAbsolutePath(string $relativePath): ?string
    {
        if (!self::isValidUnifiedUploadPath($relativePath)) {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $baseDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($baseDir)) {
            return null;
        }
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return null;
        }

        $trimmed = ltrim(substr($relativePath, strlen('/public/uploads/')), '/');
        if ($trimmed === '') {
            return null;
        }

        $absolutePath = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);
        $parent = dirname($absolutePath);
        if (!is_dir($parent)) {
            @mkdir($parent, 0777, true);
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || !str_starts_with($parentReal, $baseReal)) {
            return null;
        }

        return $absolutePath;
    }

    private static function ensureDir(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    }

    private static function setMediaAssetStatus(PDO $pdo, string $canonicalPath, string $status, ?string $error, ?int $fileSize = null): void
    {
        $exists = $pdo->query("SHOW TABLES LIKE 'media_assets'");
        if (!($exists instanceof \PDOStatement) || !$exists->fetchColumn()) {
            return;
        }

        if ($fileSize !== null) {
            $stmt = $pdo->prepare('UPDATE media_assets SET conversion_status = :status, conversion_error = :conversion_error, mime_type = :mime_type, file_size = :file_size, version_token = :version_token, updated_at = NOW() WHERE canonical_path = :canonical_path');
            $stmt->execute([
                'status' => $status,
                'conversion_error' => $error,
                'mime_type' => 'video/mp4',
                'file_size' => $fileSize,
                'version_token' => (string)time(),
                'canonical_path' => $canonicalPath,
            ]);
            return;
        }

        $stmt = $pdo->prepare('UPDATE media_assets SET conversion_status = :status, conversion_error = :conversion_error, updated_at = NOW() WHERE canonical_path = :canonical_path');
        $stmt->execute([
            'status' => $status,
            'conversion_error' => $error,
            'canonical_path' => $canonicalPath,
        ]);
    }

    private static function isValidMediaRelativePath(string $path): bool
    {
        return str_starts_with($path, '/uploads/media/')
            && strpos($path, '..') === false
            && strpos($path, "\0") === false;
    }

    private static function resolveMediaAbsolutePath(string $relativePath): ?string
    {
        if (!self::isValidMediaRelativePath($relativePath)) {
            return null;
        }

        $root = dirname(__DIR__, 2);
        $baseDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($baseDir)) {
            return null;
        }
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return null;
        }

        $trimmed = ltrim(substr($relativePath, strlen('/uploads/media/')), '/');
        if ($trimmed === '') {
            return null;
        }

        $absolutePath = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);
        $parentReal = realpath(dirname($absolutePath));
        if ($parentReal === false || !str_starts_with($parentReal, $baseReal)) {
            return null;
        }

        return $absolutePath;
    }

    private static function findFfmpegBinary(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $disabled = (string)ini_get('disable_functions');
        if ($disabled !== '') {
            $list = array_map('trim', explode(',', $disabled));
            if (in_array('exec', $list, true)) {
                return null;
            }
        }

        $candidates = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'];
        foreach ($candidates as $candidate) {
            $output = [];
            $exitCode = 1;
            @exec(escapeshellcmd($candidate) . ' -version 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                return $candidate;
            }
        }

        return null;
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
