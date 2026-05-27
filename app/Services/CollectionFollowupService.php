<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CollectionFollowupService
{
    private Database $db;
    private ?bool $hasFollowupLogTable = null;
    /** @var array<string,bool> */
    private array $orderColumnCache = [];

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
        $settlementReference = trim((string)($payload['settlement_reference'] ?? ''));
        $settlementPaymentMethod = strtolower(trim((string)($payload['settlement_payment_method'] ?? '')));
        $settledAmount = (float)($payload['settled_amount'] ?? 0);
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
                        grand_total, payment_status, payment_method, net_collected_amount, balance_due_amount, next_followup_at,
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
            $currentNetCollected = (float)($order['net_collected_amount'] ?? 0);
            $currentPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? 'pending')));
            $currentPaymentMethod = strtolower(trim((string)($order['payment_method'] ?? 'upi_manual')));
            $balanceAfterAction = $currentBalance;
            $netCollectedAfterAction = $currentNetCollected;
            $paymentStatusAfterAction = $currentPaymentStatus;
            $paymentMethodAfterAction = $currentPaymentMethod;
            $currentFollowupStatus = (string)($order['followup_status'] ?? 'no_reminder');
            $status = $this->resolveFollowupStatus($actionType, $currentFollowupStatus);

            $messageText = $note;
            $whatsappLink = null;
            $whatsappQueued = false;
            $whatsappLogId = null;
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

                $queueContext = [
                    'order_id' => $orderId,
                    'order_number' => (string)($order['order_number'] ?? ('#' . $orderId)),
                    'first_name' => (string)($order['customer_name'] ?? 'Customer'),
                    'invoice_number' => (string)($order['order_number'] ?? ('#' . $orderId)),
                    'invoice_amount' => number_format($currentBalance, 2, '.', ''),
                    'due_date' => (string)($order['next_followup_at'] ?? ''),
                    'message_text' => $messageText,
                ];
                $whatsappLogId = $this->queueWhatsAppReminder($orderId, $mobile, $queueContext);
                $whatsappQueued = $whatsappLogId > 0;
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

            $settlementTxId = null;
            $settlementPosted = false;
            if ($actionType === 'payment_collected') {
                if ($currentBalance <= 0.0001) {
                    throw new \RuntimeException('Order already has zero balance.');
                }

                if ($settlementReference === '') {
                    throw new \RuntimeException('Settlement reference is required for payment_collected action.');
                }

                if (!in_array($settlementPaymentMethod, ['cod', 'upi_manual', 'gateway'], true)) {
                    $settlementPaymentMethod = in_array($currentPaymentMethod, ['cod', 'upi_manual', 'gateway'], true)
                        ? $currentPaymentMethod
                        : 'upi_manual';
                }

                if ($settledAmount <= 0) {
                    $settledAmount = round($currentBalance, 2);
                } else {
                    $settledAmount = round($settledAmount, 2);
                }

                if ($settledAmount <= 0 || $settledAmount - $currentBalance > 0.01) {
                    throw new \RuntimeException('Settlement amount must be positive and cannot exceed outstanding balance.');
                }

                $postResult = (new AccountingPostingService())->postOrderPayment([
                    'order_id' => $orderId,
                    'order_number' => (string)($order['order_number'] ?? ''),
                    'amount' => $settledAmount,
                    'payment_method' => $settlementPaymentMethod,
                    'payment_status' => 'paid',
                    'previous_payment_status' => 'credit',
                    'source_reference' => 'collection_followup:' . $settlementReference,
                    'idempotency_key' => 'collection-settlement:' . $orderId . ':' . strtolower($settlementReference) . ':' . number_format($settledAmount, 2, '.', ''),
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'narration' => 'Collection settlement posted from follow-up action',
                ]);

                if (!$postResult['success']) {
                    throw new \RuntimeException('Accounting settlement posting failed: ' . (string)($postResult['message'] ?? 'unknown'));
                }

                $settlementPosted = (bool)($postResult['posted'] ?? false);
                $settlementTxId = isset($postResult['transaction_id']) ? (int)$postResult['transaction_id'] : null;

                if ($settlementPosted) {
                    $balanceAfterAction = max(0.0, round($currentBalance - $settledAmount, 2));
                    $netCollectedAfterAction = round($currentNetCollected + $settledAmount, 2);
                }

                $paymentStatusAfterAction = $balanceAfterAction <= 0.0001 ? 'paid' : $currentPaymentStatus;
                $paymentMethodAfterAction = $settlementPaymentMethod;

                $messageText = $messageText !== ''
                    ? $messageText
                    : ('Settlement recorded for ₹' . number_format($settledAmount, 2, '.', '') . ' via ' . strtoupper($settlementPaymentMethod));
            }

            $nextFollowup = $this->resolveNextFollowupAt($actionType, $nextFollowupAt);
            $touchesFollowup = in_array($actionType, ['reminder_whatsapp', 'reminder_email', 'followup_done', 'payment_promised', 'escalated', 'customer_responded'], true);
            $followupCount = (int)($order['followup_count'] ?? 0) + ($touchesFollowup ? 1 : 0);
            $collectionStatus = $this->resolveCollectionStatus($actionType, $balanceAfterAction);

            $updateSql = 'UPDATE orders
                 SET followup_status = :followup_status,
                     last_followup_at = NOW(),
                     next_followup_at = :next_followup_at,
                     followup_count = :followup_count,
                     collection_priority = :collection_priority,
                     collection_note = :collection_note,
                     collection_status = :collection_status';
            $updateParams = [
                'followup_status' => $status,
                'next_followup_at' => $nextFollowup,
                'followup_count' => $followupCount,
                'collection_priority' => $priority,
                'collection_note' => $note,
                'collection_status' => $collectionStatus,
                'id' => $orderId,
            ];

            if ($actionType === 'payment_collected') {
                if ($this->orderColumnExists('net_collected_amount')) {
                    $updateSql .= ', net_collected_amount = :net_collected_amount';
                    $updateParams['net_collected_amount'] = $netCollectedAfterAction;
                }
                if ($this->orderColumnExists('balance_due_amount')) {
                    $updateSql .= ', balance_due_amount = :balance_due_amount';
                    $updateParams['balance_due_amount'] = $balanceAfterAction;
                }
                if ($this->orderColumnExists('payment_status')) {
                    $updateSql .= ', payment_status = :payment_status';
                    $updateParams['payment_status'] = $paymentStatusAfterAction;
                }
                if ($this->orderColumnExists('payment_method')) {
                    $updateSql .= ', payment_method = :payment_method';
                    $updateParams['payment_method'] = $paymentMethodAfterAction;
                }
            }

            $updateSql .= ' WHERE id = :id LIMIT 1';

            $this->db->execute($updateSql, $updateParams);

            $metadata = [
                'balance_due_amount' => $balanceAfterAction,
                'collection_priority' => $priority,
                'next_followup_at' => $nextFollowup,
                'email_dispatched' => $emailDispatched,
                'whatsapp_link' => $whatsappLink,
                'whatsapp_queued' => $whatsappQueued,
                'whatsapp_log_id' => $whatsappLogId,
                'settled_amount' => $actionType === 'payment_collected' ? $settledAmount : null,
                'settlement_reference' => $actionType === 'payment_collected' ? $settlementReference : null,
                'settlement_payment_method' => $actionType === 'payment_collected' ? $settlementPaymentMethod : null,
                'settlement_posted' => $actionType === 'payment_collected' ? $settlementPosted : null,
                'settlement_transaction_id' => $actionType === 'payment_collected' ? $settlementTxId : null,
            ];

            if ($actionType === 'payment_collected') {
                try {
                    (new OrderFinanceSnapshotService())->syncOrderFinancialColumns($pdo, $orderId);
                } catch (\Throwable $snapshotErr) {
                    error_log('[CollectionFollowupService][snapshot] ' . $snapshotErr->getMessage());
                }
            }

            $timelineTotalEvents = 0;
            $loggedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            if ($this->hasCollectionFollowupLogTable()) {
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
            }

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
                'whatsapp_queued' => $whatsappQueued,
                'whatsapp_log_id' => $whatsappLogId,
                'balance_due_amount' => $balanceAfterAction,
                'net_collected_amount' => $netCollectedAfterAction,
                'payment_status' => $paymentStatusAfterAction,
                'settlement_reference' => $actionType === 'payment_collected' ? $settlementReference : null,
                'settlement_transaction_id' => $actionType === 'payment_collected' ? $settlementTxId : null,
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

    /**
     * @param array<string,mixed> $context
     */
    private function queueWhatsAppReminder(int $orderId, string $recipient, array $context): int
    {
        $payloadJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson)) {
            $payloadJson = '{}';
        }

        $logId = (int)$this->db->insert(
            'INSERT INTO communication_logs
                (order_id, channel, event_key, recipient, status, payload_json)
             VALUES
                (:order_id, "whatsapp", "payment_overdue", :recipient, "queued", :payload_json)',
            [
                'order_id' => $orderId,
                'recipient' => $recipient,
                'payload_json' => $payloadJson,
            ]
        );

        if ($logId > 0) {
            $this->db->insert(
                'INSERT INTO communication_queue
                    (communication_log_id, channel, payload_json)
                 VALUES
                    (:communication_log_id, "whatsapp", :payload_json)',
                [
                    'communication_log_id' => $logId,
                    'payload_json' => $payloadJson,
                ]
            );
        }

        return $logId;
    }

    private function hasCollectionFollowupLogTable(): bool
    {
        if ($this->hasFollowupLogTable !== null) {
            return $this->hasFollowupLogTable;
        }

        $exists = $this->db->fetchScalar(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name',
            ['table_name' => 'collection_followup_logs']
        );

        $this->hasFollowupLogTable = ((int)$exists) > 0;
        return $this->hasFollowupLogTable;
    }

    private function orderColumnExists(string $column): bool
    {
        if (array_key_exists($column, $this->orderColumnCache)) {
            return $this->orderColumnCache[$column];
        }

        $exists = $this->db->fetchScalar(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name',
            [
                'table_name' => 'orders',
                'column_name' => $column,
            ]
        );

        $this->orderColumnCache[$column] = ((int)$exists) > 0;
        return $this->orderColumnCache[$column];
    }
}
