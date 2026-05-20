<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\QueueWorker;
use App\Core\Response;
use App\Services\OrderAutomationService;
use App\Services\ByocQuoteExpiryService;
use App\Services\WhatsAppMetaApiService;
use App\Services\WhatsAppTemplateSyncService;

final class CronController
{
    public function queueProcess(): void
    {
        if (!$this->isAuthorized()) {
            Response::json(['success' => false, 'message' => 'Unauthorized cron invocation'], 401);
            return;
        }

        $maxJobs = (int)($_GET['max_jobs'] ?? 25);
        $pdo = Database::getConnection();
        $topOfferReconciled = $this->reconcileTopOfferBannerState($pdo);
        $byocExpiry = (new ByocQuoteExpiryService())->expireDueQuotes($pdo);
        $automation = new OrderAutomationService();
        $celebrations = $automation->generateDueCelebrationReminders($pdo, 400);
        $followUps = $automation->processDueFollowUps($pdo, $maxJobs);
        $crmSkipped = $this->failQueuedCrmTriggerJobs($pdo);
        $result = QueueWorker::process($pdo, $maxJobs);

        Response::json([
            'success' => true,
            'message' => 'Queue processing completed',
            'data' => [
                'top_offer_reconciled' => $topOfferReconciled,
                'celebrations' => $celebrations,
                'byoc_quote_expiry' => $byocExpiry,
                'follow_ups' => $followUps,
                'crm_jobs' => $crmSkipped,
                'queue' => $result,
            ],
        ]);
    }

    public function whatsappTemplateSync(): void
    {
        if (!$this->isAuthorized()) {
            Response::json(['success' => false, 'message' => 'Unauthorized cron invocation'], 401);
            return;
        }

        $pdo = Database::getConnection();
        $settingsStmt = $pdo->query('SELECT * FROM whatsapp_settings ORDER BY id DESC LIMIT 1');
        $settings = $settingsStmt instanceof \PDOStatement ? ($settingsStmt->fetch(\PDO::FETCH_ASSOC) ?: []) : [];
        $service = new WhatsAppTemplateSyncService(new WhatsAppMetaApiService($settings));

        try {
            $result = $service->sync($pdo, 0);
            Response::json([
                'success' => true,
                'message' => 'WhatsApp template sync completed',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Response::json([
                'success' => false,
                'message' => 'WhatsApp template sync failed',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    private function isAuthorized(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }

        $expectedToken = trim((string)(Env::get('QUEUE_CRON_TOKEN', '') ?? ''));
        if ($expectedToken === '') {
            return false;
        }

        $queryToken = trim((string)($_GET['token'] ?? ''));
        $headerToken = trim((string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? ''));
        $providedToken = $queryToken !== '' ? $queryToken : $headerToken;

        return hash_equals($expectedToken, $providedToken);
    }

    /** @return array{skipped:int,reason:string} */
    private function failQueuedCrmTriggerJobs(\PDO $pdo): array
    {
        if (!$this->shouldSkipCrmTriggerJobs($pdo)) {
            return [
                'skipped' => 0,
                'reason' => 'CRM trigger push mode is enabled. Queued jobs will be processed.',
            ];
        }

        $stmt = $pdo->prepare('UPDATE queue_jobs SET status = "failed", last_error = :last_error, updated_at = NOW() WHERE job_type = "crm_trigger_push" AND status = "queued"');
        $stmt->execute([
            'last_error' => 'Skipped by cron because CRM trigger push jobs are failing against the live endpoint',
        ]);

        return [
            'skipped' => $stmt->rowCount(),
            'reason' => 'CRM trigger pushes are handled externally and were failing on the live endpoint',
        ];
    }

    private function shouldSkipCrmTriggerJobs(\PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute(['key' => 'crm_queue_push_mode']);
            $value = trim((string) $stmt->fetchColumn());
            return $value !== 'enabled';
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** @return array{checked:int,deactivated:int} */
    private function reconcileTopOfferBannerState(\PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT b.id, b.linked_coupon_id, b.ends_at, c.is_active AS coupon_is_active, c.is_deleted AS coupon_is_deleted, c.ends_at AS coupon_ends_at
             FROM banners b
             LEFT JOIN coupons c ON c.id = b.linked_coupon_id
             WHERE b.placement = "site_top_offer" AND b.is_active = 1'
        );
        $rows = $stmt instanceof \PDOStatement ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        $deactivated = 0;
        $now = time();
        $update = $pdo->prepare('UPDATE banners SET is_active = 0, updated_at = NOW() WHERE id = :id LIMIT 1');

        foreach ($rows as $row) {
            $bannerId = (int)($row['id'] ?? 0);
            if ($bannerId <= 0) {
                continue;
            }

            $bannerEndsAt = trim((string)($row['ends_at'] ?? ''));
            $bannerEndsTs = $bannerEndsAt !== '' ? strtotime($bannerEndsAt) : false;
            $linkedCouponId = (int)($row['linked_coupon_id'] ?? 0);
            $couponActive = (int)($row['coupon_is_active'] ?? 0) === 1;
            $couponDeleted = (int)($row['coupon_is_deleted'] ?? 0) === 1;
            $couponEndsAt = trim((string)($row['coupon_ends_at'] ?? ''));
            $couponEndsTs = $couponEndsAt !== '' ? strtotime($couponEndsAt) : false;

            $shouldDeactivate = false;
            if ($bannerEndsTs !== false && $now > $bannerEndsTs) {
                $shouldDeactivate = true;
            }
            if ($linkedCouponId <= 0) {
                $shouldDeactivate = true;
            }
            if (!$couponActive || $couponDeleted) {
                $shouldDeactivate = true;
            }
            if ($couponEndsTs !== false && $now > $couponEndsTs) {
                $shouldDeactivate = true;
            }

            if ($shouldDeactivate) {
                $update->execute(['id' => $bannerId]);
                if ($update->rowCount() > 0) {
                    $deactivated++;
                }
            }
        }

        return [
            'checked' => count($rows),
            'deactivated' => $deactivated,
        ];
    }
}
