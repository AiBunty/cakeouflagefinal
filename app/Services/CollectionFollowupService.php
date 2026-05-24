<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CollectionFollowupService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function applyAction(array $payload): array
    {
        $orderId = (int)($payload['order_id'] ?? 0);
        $actionType = strtolower(trim((string)($payload['action_type'] ?? '')));
        $note = trim((string)($payload['note'] ?? ''));
        $priority = strtolower(trim((string)($payload['collection_priority'] ?? '')));
        $nextFollowupAt = trim((string)($payload['next_followup_at'] ?? ''));
        $adminId = (int)($payload['admin_id'] ?? 0);
        $adminName = trim((string)($payload['admin_name'] ?? ''));
        $emailSubject = trim((string)($payload['email_subject'] ?? ''));
        $emailMessage = trim((string)($payload['email_message'] ?? ''));
        $promiseDate = trim((string)($payload['promise_date'] ?? ''));
        $adminRole = strtolower(trim((string)($payload['admin_role'] ?? 'admin')));
        $adminPermissions = $payload['admin_permissions'] ?? [];
        if (!is_array($adminPermissions)) {
            $adminPermissions = [];
        }

        $allowedActions = [
            'reminder_whatsapp',
            'reminder_email',
            'followup_done',
            'internal_note',
            'payment_promised',
            'escalated',
            'payment_collected',
            'customer_responded',
        ];
        if ($orderId <= 0 || !in_array($actionType, $allowedActions, true)) {
            throw new \InvalidArgumentException('Invalid collection action payload.');
        }

        if (!$this->isActionAllowedForRole($actionType, $adminRole, $adminPermissions)) {
            throw new \RuntimeException('You do not have permission to perform this collection action.');
        }

        if (!in_array($priority, ['normal', 'high'], true)) {
            $priority = 'normal';
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $order = $this->db->fetchOne(
                'SELECT id, order_number, customer_name, customer_phone, customer_phone_e164, customer_email,
                        grand_total, net_collected_amount, balance_due_amount, collection_due_date,
                        followup_status, collection_priority, followup_count
                 FROM orders
                 WHERE id = :id
                 LIMIT 1
                 FOR UPDATE',
                ['id' => $orderId]
            );

            if (!$order) {
                throw new \RuntimeException('Order not found.');
            }

            $currentBalance = (float)($order['balance_due_amount'] ?? 0);
            $currentFollowupStatus = (string)($order['followup_status'] ?? 'no_reminder');
            $status = $this->resolveFollowupStatus($actionType, $currentFollowupStatus);

            $messageText = $note;
            $whatsappLink = null;
            $emailDispatched = false;

            if ($actionType === 'reminder_whatsapp') {
                $mobile = $this->normalizePhone((string)($order['customer_phone_e164'] ?: $order['customer_phone']));
                if ($mobile === '') {
                    throw new \RuntimeException('Customer phone not available for WhatsApp reminder.');
                }

                $messageText = $messageText !== ''
                    ? $messageText
                    : sprintf(
                        'Hi %s, this is a reminder for your pending balance of Rs %s against order %s. Kindly complete the payment today.',
                        (string)($order['customer_name'] ?? 'Customer'),
                        number_format($currentBalance, 2),
                        (string)($order['order_number'] ?? ('#' . $orderId))
                    );

                $whatsappLink = 'https://wa.me/' . $mobile . '?text=' . rawurlencode($messageText);
            }

            if ($actionType === 'reminder_email') {
                $to = trim((string)($order['customer_email'] ?? ''));
                if ($to === '') {
                    throw new \RuntimeException('Customer email not available for email reminder.');
                }

                $subject = $emailSubject !== ''
                    ? $emailSubject
                    : sprintf('Payment reminder for order %s', (string)($order['order_number'] ?? ('#' . $orderId)));
                $bodyText = $emailMessage !== ''
                    ? $emailMessage
                    : sprintf(
                        'Hello %s,<br><br>This is a reminder that Rs %s is pending against your order %s. Please complete payment at the earliest.<br><br>Thanks,<br>Cakeouflage Team',
                        htmlspecialchars((string)($order['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8'),
                        number_format($currentBalance, 2),
                        htmlspecialchars((string)($order['order_number'] ?? ('#' . $orderId)), ENT_QUOTES, 'UTF-8')
                    );

                MailService::sendRawEmail([$to], $subject, $bodyText);
                $emailDispatched = true;
                $messageText = strip_tags($bodyText);
            }

            if ($actionType === 'payment_promised' && $promiseDate !== '' && $nextFollowupAt === '') {
                $nextFollowupAt = $promiseDate . ' 10:00:00';
            }

            $nextFollowup = $this->resolveNextFollowupAt($actionType, $nextFollowupAt);
            $touchesFollowup = in_array($actionType, ['reminder_whatsapp', 'reminder_email', 'followup_done', 'payment_promised', 'escalated', 'customer_responded'], true);
            $followupCount = (int)($order['followup_count'] ?? 0) + ($touchesFollowup ? 1 : 0);
            $collectionStatus = $this->resolveCollectionStatus($actionType, $currentBalance);

            $this->db->execute(
                'UPDATE orders
                 SET followup_status = :followup_status,
                     last_followup_at = NOW(),
                     next_followup_at = :next_followup_at,
                     followup_count = :followup_count,
                     collection_priority = :collection_priority,
                     collection_note = :collection_note,
                     collection_status = :collection_status
                 WHERE id = :id
                 LIMIT 1',
                [
                    'followup_status' => $status,
                    'next_followup_at' => $nextFollowup,
                    'followup_count' => $followupCount,
                    'collection_priority' => $priority,
                    'collection_note' => $note,
                    'collection_status' => $collectionStatus,
                    'id' => $orderId,
                ]
            );

            $metadata = [
                'balance_due_amount' => $currentBalance,
                'collection_priority' => $priority,
                'next_followup_at' => $nextFollowup,
                'email_dispatched' => $emailDispatched,
                'whatsapp_link' => $whatsappLink,
            ];

            $this->db->execute(
                'INSERT INTO collection_followup_logs
                    (order_id, customer_name, customer_phone, action_type, followup_status, message_text, metadata_json, actor_admin_id, actor_name)
                 VALUES
                    (:order_id, :customer_name, :customer_phone, :action_type, :followup_status, :message_text, :metadata_json, :actor_admin_id, :actor_name)',
                [
                    'order_id' => $orderId,
                    'customer_name' => (string)($order['customer_name'] ?? ''),
                    'customer_phone' => (string)($order['customer_phone_e164'] ?: $order['customer_phone'] ?? ''),
                    'action_type' => $actionType,
                    'followup_status' => $status,
                    'message_text' => $messageText,
                    'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'actor_admin_id' => $adminId > 0 ? $adminId : null,
                    'actor_name' => $adminName,
                ]
            );

            $timelineTotalEvents = (int)$this->db->fetchScalar(
                'SELECT COUNT(*) FROM collection_followup_logs WHERE order_id = :order_id',
                ['order_id' => $orderId]
            );
            $loggedAt = (string)$this->db->fetchScalar(
                'SELECT MAX(created_at) FROM collection_followup_logs WHERE order_id = :order_id',
                ['order_id' => $orderId]
            );

            $pdo->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'followup_status' => $status,
                'next_followup_at' => $nextFollowup,
                'followup_count' => $followupCount,
                'collection_priority' => $priority,
                'collection_status' => $collectionStatus,
                'whatsapp_link' => $whatsappLink,
                'email_dispatched' => $emailDispatched,
                'actor_name' => $adminName !== '' ? $adminName : 'System',
                'logged_at' => $loggedAt,
                'timeline_total_events' => $timelineTotalEvents,
                'message' => 'Collection action saved successfully.',
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTimeline(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, order_id, action_type, followup_status, message_text, metadata_json, actor_admin_id, actor_name, created_at
             FROM collection_followup_logs
             WHERE order_id = :order_id
             ORDER BY created_at DESC, id DESC',
            ['order_id' => $orderId]
        );
    }

    /**
     * @param array<int|string, mixed> $adminPermissions
     */
    private function isActionAllowedForRole(string $actionType, string $adminRole, array $adminPermissions): bool
    {
        $normalized = [];
        foreach ($adminPermissions as $permission) {
            if (is_string($permission) && $permission !== '') {
                $normalized[] = $permission;
            }
        }

        if ($adminRole === 'super_admin') {
            return true;
        }

        if ($actionType === 'escalated') {
            return in_array($adminRole, ['admin', 'ops_manager'], true)
                || in_array('order_reject', $normalized, true)
                || in_array('can_approve_refund', $normalized, true);
        }

        if ($actionType === 'payment_collected') {
            return in_array($adminRole, ['admin', 'sales_manager', 'ops_manager'], true)
                || in_array('order_credit', $normalized, true)
                || in_array('order_edit', $normalized, true);
        }

        return true;
    }

    private function resolveFollowupStatus(string $actionType, string $currentStatus): string
    {
        return match ($actionType) {
            'reminder_whatsapp', 'reminder_email', 'followup_done' => 'reminder_sent',
            'customer_responded' => 'customer_responded',
            'payment_promised' => 'payment_promised',
            'escalated' => 'escalated',
            'payment_collected' => 'settled',
            default => $currentStatus !== '' ? $currentStatus : 'no_reminder',
        };
    }

    private function resolveCollectionStatus(string $actionType, float $balance): string
    {
        if ($actionType === 'payment_collected' || $balance <= 0.0001) {
            return 'fully_paid';
        }
        if ($actionType === 'escalated' && $balance > 0) {
            return 'overdue';
        }
        return $balance > 0 ? 'payment_pending' : 'fully_paid';
    }

    private function resolveNextFollowupAt(string $actionType, string $requested): ?string
    {
        $requested = trim($requested);
        if ($requested !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $requested)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $requested)
                ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $requested);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        $now = new \DateTimeImmutable('now');
        return match ($actionType) {
            'reminder_whatsapp', 'reminder_email', 'followup_done', 'customer_responded' => $now->modify('+1 day')->format('Y-m-d H:i:s'),
            'payment_promised' => $now->modify('+2 days')->format('Y-m-d H:i:s'),
            'escalated' => $now->modify('+12 hours')->format('Y-m-d H:i:s'),
            'payment_collected' => null,
            default => $now->modify('+1 day')->format('Y-m-d H:i:s'),
        };
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }
}
