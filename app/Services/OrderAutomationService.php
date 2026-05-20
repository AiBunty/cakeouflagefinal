<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class OrderAutomationService
{
    /** @var array<string,array<int,string>> */
    private const EMAIL_EVENT_ALIASES = [
        'online_order_received_customer' => ['order_created', 'order_placed_customer'],
        'online_order_received_admin' => ['admin_new_order'],
        'manual_order_received_customer' => ['manual_order_customer', 'order_created_manual_customer'],
        'manual_order_received_admin' => ['manual_order_admin', 'admin_new_order_manual'],
        'payment_confirmed_customer' => ['order_confirmed_customer'],
        'payment_confirmed_admin' => ['admin_payment_confirmed'],
        'ready_order_customer' => ['order_in_preparation', 'order_ready_for_pickup'],
        'ready_order_admin' => ['admin_order_ready'],
        'order_delivered_customer' => ['order_delivered'],
        'order_delivered_admin' => ['admin_order_delivered'],
        'reject_order_customer' => ['order_rejected', 'reject_order'],
        'reject_order_admin' => ['admin_order_rejected'],
        'follow_up_review_email' => ['follow_up_review_customer', 'follow_up_reminder'],
        'annual_reorder_email' => ['follow_up_yearly_customer', 'follow_up_yearly'],
        'birthday_greeting_email' => ['birthday_greeting'],
        'birthday_preorder_email' => ['birthday_preorder'],
        'anniversary_greeting_email' => ['anniversary_greeting'],
        'anniversary_preorder_email' => ['anniversary_preorder'],
        'celebration_combined_email' => ['celebration_combined'],
    ];

    /** @var array<string,array<int,string>> */
    private const CRM_SETTING_ALIASES = [
        'online_order_received' => ['order_placed', 'order_created'],
        'manual_order_received' => ['manual_order_created'],
        'payment_confirmed' => ['order_confirmed'],
        'ready_order' => ['order_ready', 'order_in_preparation'],
        'order_delivered' => ['delivered_order', 'order_completed'],
        'follow_up_review' => ['follow_up_reminder'],
        'annual_reorder' => ['follow_up_yearly'],
        'birthday_greeting_email' => ['birthday_greeting'],
        'birthday_preorder_email' => ['birthday_preorder'],
        'anniversary_greeting_email' => ['anniversary_greeting'],
        'anniversary_preorder_email' => ['anniversary_preorder'],
        'celebration_combined_email' => ['celebration_combined'],
    ];

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function createManualOrder(PDO $pdo, array $payload, int $adminId = 0): array
    {
        $customerName = trim((string)($payload['customer_name'] ?? ''));
        $customerEmail = strtolower(trim((string)($payload['customer_email'] ?? '')));
        $customerPhone = trim((string)($payload['customer_phone'] ?? ''));
        $itemName = trim((string)($payload['item_name'] ?? ''));
        $amount = (float)($payload['amount'] ?? 0);
        $adminNote = trim((string)($payload['admin_note'] ?? ''));
        $fulfilmentMode = (string)($payload['fulfilment_mode'] ?? 'pickup');
        $orderStatus = (string)($payload['order_status'] ?? 'confirmed');
        $paymentStatus = (string)($payload['payment_status'] ?? 'paid');

        if ($customerName === '' || $customerEmail === '' || $customerPhone === '' || $itemName === '' || $amount <= 0) {
            throw new \RuntimeException('Manual order requires name, email, phone, item, and amount');
        }
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid customer email format');
        }

        $allowedFulfilment = ['delivery', 'pickup', 'custom_delivery'];
        if (!in_array($fulfilmentMode, $allowedFulfilment, true)) {
            $fulfilmentMode = 'pickup';
        }

        $allowedOrderStatus = ['pending', 'confirmed', 'in_preparation', 'completed', 'cancelled'];
        if (!in_array($orderStatus, $allowedOrderStatus, true)) {
            $orderStatus = 'confirmed';
        }

        $allowedPaymentStatus = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($paymentStatus, $allowedPaymentStatus, true)) {
            $paymentStatus = 'paid';
        }

        $pdo->beginTransaction();
        try {
            $userId = $this->getOrCreateUserForManualOrder($pdo, $customerName, $customerEmail, $customerPhone);
            $orderNumber = $this->generateOrderNumber('MAN');
            $fallbackProductId = $this->pickFallbackProductId($pdo);
            $normalizedAmount = round($amount, 2);
            $note = $adminNote !== '' ? $adminNote : 'Created from admin manual order punch';

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (
                    order_number, user_id, customer_name, customer_email, customer_phone,
                    fulfilment_mode, order_status, payment_status, payment_method,
                    subtotal, discount_total, tax_total, grand_total, admin_note
                 ) VALUES (
                    :order_number, :user_id, :customer_name, :customer_email, :customer_phone,
                    :fulfilment_mode, :order_status, :payment_status, "upi_manual",
                    :subtotal, 0, 0, :grand_total, :admin_note
                 )'
            );
            $orderStmt->execute([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'fulfilment_mode' => $fulfilmentMode,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'subtotal' => $normalizedAmount,
                'grand_total' => $normalizedAmount,
                'admin_note' => $note,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id, product_id, variant_id, product_name_snapshot,
                    variant_snapshot, unit_price, quantity, line_total, customisation_note
                 ) VALUES (
                    :order_id, :product_id, NULL, :product_name_snapshot,
                    NULL, :unit_price, 1, :line_total, :customisation_note
                 )'
            );
            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $fallbackProductId,
                'product_name_snapshot' => $itemName,
                'unit_price' => $normalizedAmount,
                'line_total' => $normalizedAmount,
                'customisation_note' => 'Manual order entry',
            ]);

            $order = $this->loadOrder($pdo, $orderId);
            if ($order === null) {
                throw new \RuntimeException('Manual order insert failed');
            }

            $context = $this->buildOrderContext($order);
            $emailQueued = 0;
            $emailQueued += $this->queueCustomerStatusEmail($pdo, 'manual_order_received', $context);
            $emailQueued += $this->queueAdminStatusEmail($pdo, 'manual_order_received', $context);
            $crmQueued = $this->maybeQueueCrmTriggerJob($pdo, 'manual_order_received', $context);

            $pdo->commit();

            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'emails_queued' => $emailQueued,
                'crm_jobs_queued' => $crmQueued,
                'created_by_admin_id' => $adminId,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function handleOrderPlaced(PDO $pdo, int $orderId, string $source = 'online'): array
    {
        $order = $this->loadOrder($pdo, $orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found');
        }

        $eventKey = strtolower(trim($source)) === 'manual' ? 'manual_order_received' : 'online_order_received';
        $context = $this->buildOrderContext($order);
        $result = [
            'order_id' => $orderId,
            'event_key' => $eventKey,
            'emails_queued' => 0,
            'crm_jobs_queued' => 0,
        ];

        $pdo->beginTransaction();
        try {
            $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, $eventKey, $context);
            $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, $eventKey, $context);
            $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, $eventKey, $context);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function handleStatusChange(PDO $pdo, int $orderId, string $status, int $adminId = 0): array
    {
        $order = $this->loadOrder($pdo, $orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found');
        }

        $context = $this->buildOrderContext($order);
        $result = [
            'order_id' => $orderId,
            'status' => $status,
            'emails_queued' => 0,
            'crm_jobs_queued' => 0,
            'follow_ups_scheduled' => 0,
        ];

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE orders SET order_status = :order_status WHERE id = :id');
            $update->execute([
                'order_status' => $status,
                'id' => $orderId,
            ]);

            if ($status === 'confirmed') {
                $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, 'payment_confirmed', $context);
                $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, 'payment_confirmed', $context);
                $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, 'payment_confirmed', $context);
            }

            if ($status === 'cancelled') {
                $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, 'reject_order', $context);
                $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, 'reject_order', $context);
                $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, 'reject_order', $context);
                $this->cancelPendingFollowUpsForOrder($pdo, $orderId);
            }

            if ($status === 'in_preparation') {
                $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, 'ready_order', $context);
                $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, 'ready_order', $context);
                $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, 'ready_order', $context);
            }

            if ($status === 'completed') {
                $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, 'order_delivered', $context);
                $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, 'order_delivered', $context);
                $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, 'order_delivered', $context);
                $result['follow_ups_scheduled'] += $this->scheduleCompletedOrderFollowUps($pdo, $context, $adminId);
            }

            $pdo->commit();

            $verify = $pdo->prepare('SELECT order_status FROM orders WHERE id = :id LIMIT 1');
            $verify->execute(['id' => $orderId]);
            $persistedStatus = (string)($verify->fetchColumn() ?: '');
            if ($persistedStatus !== $status) {
                throw new \RuntimeException('Order status update did not persist for order #' . $orderId . ' (expected ' . $status . ', found ' . ($persistedStatus !== '' ? $persistedStatus : 'missing') . ')');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function processDueFollowUps(PDO $pdo, int $limit = 25): array
    {
        $max = min(200, max(1, $limit));
        $scheduled = 0;
        $failed = 0;
        $errors = [];

        $stmt = $pdo->prepare(
            'SELECT *
             FROM reminders
               WHERE reminder_type IN ("follow_up", "birthday") AND status = "pending" AND reminder_on <= NOW()
             ORDER BY reminder_on ASC, id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $followUpId = (int)($row['id'] ?? 0);
            $meta = json_decode((string)($row['notes'] ?? ''), true);
            $context = is_array($meta) ? $meta : [];
            $context['follow_up_id'] = $followUpId;
            $context['follow_up_type'] = (string)($context['follow_up_type'] ?? 'review');
            $context['customer_name'] = (string)($row['recipient_name'] ?? ($context['customer_name'] ?? 'Valued Customer'));
            $context['customer_email'] = (string)($row['recipient_email'] ?? ($context['customer_email'] ?? ''));
            $context['customer_phone'] = (string)($row['recipient_phone'] ?? ($context['customer_phone'] ?? ''));
            $reminderType = (string)($row['reminder_type'] ?? 'follow_up');

            $pdo->beginTransaction();
            try {
                if ($reminderType === 'follow_up') {
                    $type = (string)($context['follow_up_type'] ?? 'review');
                    if ($type === 'review' || $type === 'quarterly_reorder') {
                        $scheduled += $this->queueFollowUpEmail($pdo, 'follow_up_review', $context);
                        $scheduled += $this->maybeQueueCrmTriggerJob($pdo, 'follow_up_review', $context, $followUpId);
                        $this->scheduleNextQuarterlyFollowUp($pdo, $row, $context);
                    } elseif ($type === 'annual_reorder') {
                        $scheduled += $this->queueFollowUpEmail($pdo, 'annual_reorder', $context);
                        $scheduled += $this->maybeQueueCrmTriggerJob($pdo, 'annual_reorder', $context, $followUpId);
                        $this->scheduleNextAnnualReorder($pdo, $row, $context);
                    }
                } elseif ($reminderType === 'birthday') {
                    $scheduled += $this->queueCelebrationReminder($pdo, $context, $followUpId);
                }

                $update = $pdo->prepare('UPDATE reminders SET status = "done", updated_at = NOW() WHERE id = :id');
                $update->execute(['id' => $followUpId]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $failed++;
                $errors[] = [
                    'follow_up_id' => $followUpId,
                    'error' => $e->getMessage(),
                ];

                $errorStmt = $pdo->prepare('UPDATE reminders SET status = "cancelled", notes = :notes WHERE id = :id');
                $errorStmt->execute([
                    'notes' => json_encode(array_merge($context, array('last_error' => mb_substr($e->getMessage(), 0, 250))), JSON_UNESCAPED_SLASHES),
                    'id' => $followUpId,
                ]);
            }
        }

        return [
            'due_found' => count($rows),
            'queued_actions' => $scheduled,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /** @return array<string,mixed> */
    public function generateDueCelebrationReminders(PDO $pdo, int $limit = 300): array
    {
        $max = min(1000, max(10, $limit));
        $settings = $this->fetchFollowUpSettings($pdo);
        $leadDays = max(1, (int)($settings['celebration_reminder_days_before'] ?? '7'));
        $combineOnSameDay = in_array((string)($settings['celebration_combined_email_on_same_day'] ?? '1'), ['1', 'true', 'yes', 'on'], true);

        $stmt = $pdo->prepare(
            'SELECT
                u.id AS user_id,
                u.full_name,
                u.email,
                u.phone,
                cp.date_of_birth,
                cp.anniversary_date
             FROM customer_profiles cp
             JOIN users u ON u.id = cp.user_id
             WHERE u.role = "customer"
               AND u.deleted_at IS NULL
               AND u.is_active = 1
               AND (cp.date_of_birth IS NOT NULL OR cp.anniversary_date IS NOT NULL)
             ORDER BY u.id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tz = new \DateTimeZone('Asia/Kolkata');
        $today = new \DateTimeImmutable('today', $tz);
        $now = new \DateTimeImmutable('now', $tz);

        $generated = 0;
        $skipped = 0;
        $combined = 0;

        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $birthdayEvent = $this->resolveCelebrationEventDate((string)($row['date_of_birth'] ?? ''), $today, $tz);
            $anniversaryEvent = $this->resolveCelebrationEventDate((string)($row['anniversary_date'] ?? ''), $today, $tz);

            $birthdayToday = $birthdayEvent !== null && $birthdayEvent->format('Y-m-d') === $today->format('Y-m-d');
            $anniversaryToday = $anniversaryEvent !== null && $anniversaryEvent->format('Y-m-d') === $today->format('Y-m-d');

            $birthdayPreorderToday = false;
            if ($birthdayEvent !== null) {
                $birthdayPreorderToday = $birthdayEvent->modify('-' . $leadDays . ' days')->format('Y-m-d') === $today->format('Y-m-d');
            }

            $anniversaryPreorderToday = false;
            if ($anniversaryEvent !== null) {
                $anniversaryPreorderToday = $anniversaryEvent->modify('-' . $leadDays . ' days')->format('Y-m-d') === $today->format('Y-m-d');
            }

            $baseContext = [
                'user_id' => $userId,
                'customer_name' => (string)($row['full_name'] ?? 'Valued Customer'),
                'customer_email' => (string)($row['email'] ?? ''),
                'customer_phone' => (string)($row['phone'] ?? ''),
                'birthday_date' => (string)($row['date_of_birth'] ?? ''),
                'anniversary_date' => (string)($row['anniversary_date'] ?? ''),
                'celebration_reminder_days_before' => $leadDays,
                'profile_link' => 'https://cakeouflage.com/account',
                'order_link' => 'https://cakeouflage.com/shop',
            ];

            if ($combineOnSameDay && $birthdayToday && $anniversaryToday) {
                $eventDate = $birthdayEvent !== null ? $birthdayEvent->format('Y-m-d') : $today->format('Y-m-d');
                if ($this->createCelebrationReminder($pdo, $userId, 'Today is your special day', 'combined_greeting', $eventDate, $now, $baseContext)) {
                    $generated++;
                    $combined++;
                } else {
                    $skipped++;
                }
            } else {
                if ($birthdayToday) {
                    $eventDate = $birthdayEvent !== null ? $birthdayEvent->format('Y-m-d') : $today->format('Y-m-d');
                    if ($this->createCelebrationReminder($pdo, $userId, 'Happy Birthday from Cakeouflage', 'birthday_greeting', $eventDate, $now, $baseContext)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
                if ($anniversaryToday) {
                    $eventDate = $anniversaryEvent !== null ? $anniversaryEvent->format('Y-m-d') : $today->format('Y-m-d');
                    if ($this->createCelebrationReminder($pdo, $userId, 'Happy Anniversary from Cakeouflage', 'anniversary_greeting', $eventDate, $now, $baseContext)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
            }

            if ($combineOnSameDay && $birthdayPreorderToday && $anniversaryPreorderToday) {
                $eventDate = $birthdayEvent !== null ? $birthdayEvent->format('Y-m-d') : $today->format('Y-m-d');
                if ($this->createCelebrationReminder($pdo, $userId, 'Plan your celebration cake in advance', 'combined_preorder', $eventDate, $now, $baseContext)) {
                    $generated++;
                    $combined++;
                } else {
                    $skipped++;
                }
            } else {
                if ($birthdayPreorderToday && $birthdayEvent !== null) {
                    if ($this->createCelebrationReminder($pdo, $userId, 'Upcoming birthday reminder from Cakeouflage', 'birthday_preorder', $birthdayEvent->format('Y-m-d'), $now, $baseContext)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
                if ($anniversaryPreorderToday && $anniversaryEvent !== null) {
                    if ($this->createCelebrationReminder($pdo, $userId, 'Upcoming anniversary reminder from Cakeouflage', 'anniversary_preorder', $anniversaryEvent->format('Y-m-d'), $now, $baseContext)) {
                        $generated++;
                    } else {
                        $skipped++;
                    }
                }
            }
        }

        return [
            'profiles_scanned' => count($rows),
            'generated' => $generated,
            'combined' => $combined,
            'skipped' => $skipped,
            'lead_days' => $leadDays,
            'combined_on_same_day' => $combineOnSameDay,
        ];
    }

    /** @param array<string,mixed> $context */
    private function queueCelebrationReminder(PDO $pdo, array $context, int $followUpId): int
    {
        $purpose = trim((string)($context['celebration_purpose'] ?? ''));
        if ($purpose === '') {
            return 0;
        }

        $eventKeyMap = [
            'birthday_greeting' => 'birthday_greeting_email',
            'birthday_preorder' => 'birthday_preorder_email',
            'anniversary_greeting' => 'anniversary_greeting_email',
            'anniversary_preorder' => 'anniversary_preorder_email',
            'combined_greeting' => 'celebration_combined_email',
            'combined_preorder' => 'celebration_combined_email',
        ];

        if (!isset($eventKeyMap[$purpose])) {
            return 0;
        }

        $eventKey = $eventKeyMap[$purpose];
        $scheduled = $this->queueFollowUpEmail($pdo, $eventKey, $context);
        $scheduled += $this->maybeQueueCrmTriggerJob($pdo, $eventKey, $context, $followUpId);
        return $scheduled;
    }

    /** @param array<string,mixed> $context */
    private function createCelebrationReminder(PDO $pdo, int $userId, string $title, string $purpose, string $eventDate, \DateTimeImmutable $now, array $context): bool
    {
        $marker = $purpose . '|' . $eventDate;
        if ($this->celebrationReminderExists($pdo, $userId, $marker)) {
            return false;
        }

        $notes = $context;
        $notes['celebration_key'] = $marker;
        $notes['celebration_purpose'] = $purpose;
        $notes['event_date'] = $eventDate;

        $stmt = $pdo->prepare(
            'INSERT INTO reminders (user_id, b2b_account_id, reminder_type, title, reminder_on, status, notes, created_by_admin_id)
             VALUES (:user_id, NULL, "birthday", :title, :reminder_on, "pending", :notes, NULL)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'reminder_on' => $now->format('Y-m-d H:i:s'),
            'notes' => json_encode($notes, JSON_UNESCAPED_SLASHES),
        ]);

        return true;
    }

    private function celebrationReminderExists(PDO $pdo, int $userId, string $marker): bool
    {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM reminders
             WHERE reminder_type = "birthday"
               AND user_id = :user_id
               AND notes LIKE :notes_like
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'notes_like' => '%"celebration_key":"' . $marker . '"%',
        ]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function resolveCelebrationEventDate(string $rawDate, \DateTimeImmutable $today, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $trimmed = trim($rawDate);
        if ($trimmed === '') {
            return null;
        }

        $eventDate = \DateTimeImmutable::createFromFormat('Y-m-d', $trimmed, $tz);
        if (!$eventDate) {
            return null;
        }

        $monthDay = $eventDate->format('m-d');
        $targetYear = (int)$today->format('Y');
        $next = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%s', $targetYear, $monthDay), $tz);
        if (!$next) {
            return null;
        }

        if ($next < $today) {
            $next = $next->modify('+1 year');
        }

        return $next;
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function executeCrmTrigger(PDO $pdo, array $payload): array
    {
        $settingKey = trim((string)($payload['setting_key'] ?? ''));
        if ($settingKey === '') {
            throw new \RuntimeException('CRM trigger setting key is required');
        }

        $stmt = $pdo->prepare('SELECT id, setting_key, endpoint, api_token, is_enabled FROM crm_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $settingKey]);
        $crm = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$crm) {
            throw new \RuntimeException('CRM setting not found for key: ' . $settingKey);
        }

        if ((int)($crm['is_enabled'] ?? 0) !== 1) {
            throw new \RuntimeException('CRM setting is disabled for key: ' . $settingKey);
        }

        $endpoint = trim((string)($crm['endpoint'] ?? ''));
        $apiToken = trim((string)($crm['api_token'] ?? ''));
        if ($endpoint === '' || $apiToken === '') {
            throw new \RuntimeException('CRM setting is incomplete for key: ' . $settingKey);
        }

        $context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : [];
        $params = $this->buildCrmParams($apiToken, $context);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = $response !== false && $curlError === '' && $httpCode >= 200 && $httpCode < 400;
        $status = $ok ? 'success' : 'fail';
        $details = $response !== false ? (string)$response : $curlError;

        $log = $pdo->prepare('INSERT INTO crm_push_logs (name, mobile, status, response, created_at) VALUES (:name, :mobile, :status, :response, NOW())');
        $log->execute([
            'name' => (string)($context['customer_name'] ?? ''),
            'mobile' => (string)($context['customer_phone'] ?? ''),
            'status' => $status,
            'response' => mb_substr($details, 0, 65000),
        ]);

        if (!$ok) {
            throw new \RuntimeException($details !== '' ? $details : 'CRM push failed');
        }

        return [
            'setting_key' => $settingKey,
            'status' => $status,
            'http_code' => $httpCode,
            'response' => $details,
        ];
    }

    /**
     * Queue a CRM webhook push for Build Your Own Cake inquiry context.
     *
     * @param array<string,mixed> $context
     */
    public function queueCrmWebhookForInquiry(PDO $pdo, string $settingKey, array $context): int
    {
        return $this->maybeQueueCrmTriggerJob($pdo, $settingKey, $context);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildCustomCakeCrmContext(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $firstName = $name;
        $parts = preg_split('/\s+/', $name) ?: [];
        if (isset($parts[0]) && trim((string)$parts[0]) !== '') {
            $firstName = trim((string)$parts[0]);
        }

        $phoneCountryCode = trim((string)($input['phone_country_code'] ?? '+91'));
        if ($phoneCountryCode === '' || $phoneCountryCode[0] !== '+') {
            $phoneCountryCode = '+91';
        }

        $phoneDigits = preg_replace('/\D+/', '', (string)($input['phone'] ?? '')) ?: '';
        $fullPhone = $phoneDigits !== '' ? ($phoneCountryCode . $phoneDigits) : '';

        return [
            'customer_name' => $name,
            'first_name' => $firstName,
            'customer_email' => trim((string)($input['email'] ?? '')),
            'customer_phone' => $fullPhone,
            'event_information' => trim((string)($input['event_information'] ?? '')),
            'event_date' => trim((string)($input['event_date'] ?? '')),
            'number_of_servings_guests' => trim((string)($input['number_of_servings_guests'] ?? '')),
            'budget_range' => trim((string)($input['budget_range'] ?? '')),
            'diet_preference' => trim((string)($input['diet_preference'] ?? '')),
            'design_breif_notes' => trim((string)($input['design_breif_notes'] ?? '')),
            'reference_file' => trim((string)($input['reference_file'] ?? '')),

            // Exact CRM placeholders requested by business.
            'contact.first_name' => $firstName,
            'contact.email' => trim((string)($input['email'] ?? '')),
            'contact.phone' => $fullPhone,
            'contact.diet_preference' => trim((string)($input['diet_preference'] ?? '')),
            'contact.event_information' => trim((string)($input['event_information'] ?? '')),
            'contact.referen_prsjad' => trim((string)($input['reference_file'] ?? '')),
            'contact.design_breif_notes' => trim((string)($input['design_breif_notes'] ?? '')),
            'contact.budget_range' => trim((string)($input['budget_range'] ?? '')),
            'contact.number__gculaj' => trim((string)($input['number_of_servings_guests'] ?? '')),
            'contact.event_date' => trim((string)($input['event_date'] ?? '')),
        ];
    }

    /** @return array<string,mixed>|null */
    private function loadOrder(PDO $pdo, int $orderId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }

        $itemsStmt = $pdo->prepare('SELECT product_name_snapshot FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $itemsStmt->execute(['order_id' => $orderId]);
        $itemNames = [];
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $name = trim((string)($item['product_name_snapshot'] ?? ''));
            if ($name !== '') {
                $itemNames[] = $name;
            }
        }

        $order['item_names'] = implode(', ', $itemNames);
        return $order;
    }

    private function getOrCreateUserForManualOrder(PDO $pdo, string $name, string $email, string $phone): int
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $userId = (int)($existing['id'] ?? 0);
            if ($userId > 0) {
                $update = $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, updated_at = NOW() WHERE id = :id');
                $update->execute([
                    'full_name' => $name,
                    'phone' => $phone,
                    'id' => $userId,
                ]);
                return $userId;
            }
        }

        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password_hash, role)
             VALUES (:full_name, :email, :phone, :password_hash, "customer")'
        );
        $insert->execute([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
        ]);

        return (int)$pdo->lastInsertId();
    }

    private function pickFallbackProductId(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
        $productId = (int)($stmt ? $stmt->fetchColumn() : 0);
        if ($productId <= 0) {
            throw new \RuntimeException('Cannot create manual order because no products exist in catalog');
        }
        return $productId;
    }

    private function generateOrderNumber(string $prefix = 'ORD'): string
    {
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($prefix)) ?: 'ORD';
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /** @param array<string,mixed> $order
     *  @return array<string,mixed>
     */
    private function buildOrderContext(array $order): array
    {
        $customerName = trim((string)($order['customer_name'] ?? 'Valued Customer'));
        $firstName = $customerName;
        $parts = preg_split('/\s+/', $customerName) ?: [];
        if (isset($parts[0]) && trim((string)$parts[0]) !== '') {
            $firstName = trim((string)$parts[0]);
        }

        $email = trim((string)($order['customer_email'] ?? ''));
        $phone = trim((string)($order['customer_phone'] ?? ''));
        $itemNames = trim((string)($order['item_names'] ?? 'Cake order'));
        $amount = number_format((float)($order['grand_total'] ?? 0), 2, '.', '');

        return [
            'order_id' => (int)($order['id'] ?? 0),
            'user_id' => (int)($order['user_id'] ?? 0),
            'order_number' => (string)($order['order_number'] ?? ''),
            'customer_name' => $customerName,
            'first_name' => $firstName,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'item_names' => $itemNames,
            'grand_total' => $amount,
            'upi_link' => 'upi://pay?pa=test@upi&pn=Cakeouflage&am=' . $amount,
            'contact.name' => $customerName,
            'contact.first_name' => $firstName,
            'contact.mobile' => $phone,
            'contact.phone' => $phone,
            'contact.email' => $email,
            'contact.orderid' => (string)($order['order_number'] ?? ''),
            'contact.item' => $itemNames,
            'contact.amount' => $amount,
            'contact.upi_link' => 'upi://pay?pa=test@upi&pn=Cakeouflage&am=' . $amount,
        ];
    }

    /** @param array<string,mixed> $context */
    private function queueCustomerStatusEmail(PDO $pdo, string $eventKey, array $context): int
    {
        $recipient = trim((string)($context['customer_email'] ?? ''));
        if ($recipient === '') {
            return 0;
        }

        list($subject, $body) = $this->buildCustomerEmailContent($eventKey, $context);
        $this->queueEmailCommunication($pdo, (int)($context['user_id'] ?? 0), (int)($context['order_id'] ?? 0), $recipient, $eventKey . '_customer', $context, $subject, $body);
        return 1;
    }

    /** @param array<string,mixed> $context */
    private function queueAdminStatusEmail(PDO $pdo, string $eventKey, array $context): int
    {
        $recipients = $this->resolveAdminEmailRecipients($pdo);
        if (count($recipients) === 0) {
            return 0;
        }

        list($subject, $body) = $this->buildAdminEmailContent($eventKey, $context);
        $queued = 0;
        $primary = $recipients[0];
        $primaryContext = $context;
        $primaryContext['admin_primary_email'] = $primary;
        $primaryContext['admin_cc_emails'] = implode(', ', array_slice($recipients, 1));
        $primaryContext['recipient_role'] = 'admin_primary';
        $this->queueEmailCommunication($pdo, 0, (int)($context['order_id'] ?? 0), $primary, $eventKey . '_admin', $primaryContext, $subject, $body);
        $queued++;

        foreach (array_slice($recipients, 1) as $ccEmail) {
            $ccContext = $context;
            $ccContext['admin_primary_email'] = $primary;
            $ccContext['admin_cc_emails'] = implode(', ', array_slice($recipients, 1));
            $ccContext['recipient_role'] = 'admin_cc';
            $this->queueEmailCommunication($pdo, 0, (int)($context['order_id'] ?? 0), $ccEmail, $eventKey . '_admin', $ccContext, $subject, $body);
            $queued++;
        }

        return $queued;
    }

    /** @return array<int,string> */
    private function resolveAdminEmailRecipients(PDO $pdo): array
    {
        $toEmail = '';
        $ccIdsRaw = '';

        $settingsStmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("communication_admin_to_email", "communication_admin_cc_admin_ids")');
        $settingsStmt->execute();
        $settings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settings as $setting) {
            $key = trim((string)($setting['setting_key'] ?? ''));
            $value = trim((string)($setting['setting_value'] ?? ''));
            if ($key === 'communication_admin_to_email') {
                $toEmail = $value;
            } elseif ($key === 'communication_admin_cc_admin_ids') {
                $ccIdsRaw = $value;
            }
        }

        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $toEmail = 'cakeouflage@gmail.com';
        }

        $recipients = [strtolower($toEmail)];
        $ccIds = array_values(array_unique(array_filter(array_map(static function (string $value): int {
            return (int)$value;
        }, preg_split('/\s*,\s*/', $ccIdsRaw) ?: []), static function (int $id): bool {
            return $id > 0;
        })));

        if (count($ccIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($ccIds), '?'));
            $ccStmt = $pdo->prepare('SELECT email FROM admins WHERE is_active = 1 AND id IN (' . $placeholders . ') ORDER BY id ASC');
            $ccStmt->execute($ccIds);
            $rows = $ccStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }

    /** @param array<string,mixed> $context */
    private function queueFollowUpEmail(PDO $pdo, string $eventKey, array $context): int
    {
        $recipient = trim((string)($context['customer_email'] ?? ''));
        if ($recipient === '') {
            return 0;
        }

        list($subject, $body) = $this->buildFollowUpEmailContent($eventKey, $context);
        $this->queueEmailCommunication($pdo, (int)($context['user_id'] ?? 0), (int)($context['order_id'] ?? 0), $recipient, $eventKey . '_email', $context, $subject, $body);
        return 1;
    }

    /** @param array<string,mixed> $context */
    private function maybeQueueCrmTriggerJob(PDO $pdo, string $settingKey, array $context, int $followUpId = 0): int
    {
        $this->ensureCrmSettingExists($pdo, $settingKey);

        $resolvedSettingKey = $this->resolveCrmSettingKey($pdo, $settingKey);

        $check = $pdo->prepare('SELECT id FROM crm_settings WHERE setting_key = :setting_key AND is_enabled = 1 AND COALESCE(endpoint, "") <> "" AND COALESCE(api_token, "") <> "" LIMIT 1');
        $check->execute(['setting_key' => $resolvedSettingKey]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            return 0;
        }

        $payload = [
            'setting_key' => $resolvedSettingKey,
            'setting_key_requested' => $settingKey,
            'setting_key_resolved' => $resolvedSettingKey,
            'follow_up_id' => $followUpId,
            'context' => $context,
        ];
        $stmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("crm_trigger_push", :payload_json, "queued", NOW(), 0)');
        $stmt->execute([
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);

        return 1;
    }

    private function ensureCrmSettingExists(PDO $pdo, string $settingKey): void
    {
        $settingKey = trim($settingKey);
        if ($settingKey === '') {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO crm_settings (setting_key, endpoint, api_token, is_enabled)
             VALUES (:setting_key, "", "", 0)
             ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key)'
        );
        $stmt->execute(['setting_key' => $settingKey]);

        if (!in_array($settingKey, ['manual_order_received', 'online_order_received', 'ready_order', 'order_delivered'], true)) {
            return;
        }

        $currentStmt = $pdo->prepare('SELECT endpoint, api_token, is_enabled FROM crm_settings WHERE setting_key = :setting_key LIMIT 1');
        $currentStmt->execute(['setting_key' => $settingKey]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $currentEndpoint = trim((string)($current['endpoint'] ?? ''));
        $currentToken = trim((string)($current['api_token'] ?? ''));
        $currentEnabled = (int)($current['is_enabled'] ?? 0);

        if ($currentEndpoint !== '' && $currentToken !== '' && $currentEnabled === 1) {
            return;
        }

        $templateStmt = $pdo->prepare('SELECT endpoint, api_token, is_enabled FROM crm_settings WHERE setting_key = :setting_key LIMIT 1');
        $templateStmt->execute(['setting_key' => 'payment_confirmed']);
        $template = $templateStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $templateEndpoint = trim((string)($template['endpoint'] ?? ''));
        $templateToken = trim((string)($template['api_token'] ?? ''));
        $templateEnabled = (int)($template['is_enabled'] ?? 0) === 1 ? 1 : 0;

        if ($templateEndpoint === '' || $templateToken === '') {
            return;
        }

        $updateStmt = $pdo->prepare('UPDATE crm_settings SET endpoint = :endpoint, api_token = :api_token, is_enabled = :is_enabled WHERE setting_key = :setting_key');
        $updateStmt->execute([
            'endpoint' => $templateEndpoint,
            'api_token' => $templateToken,
            'is_enabled' => $templateEnabled,
            'setting_key' => $settingKey,
        ]);
    }

    /** @param array<string,mixed> $context */
    private function queueEmailCommunication(PDO $pdo, int $userId, int $orderId, string $recipient, string $eventKey, array $context, string $subject, string $body): void
    {
        $resolvedEventKey = $this->resolveEmailEventKey($pdo, $eventKey);

        $payload = $context;
        $payload['trigger_requested_key'] = $eventKey;
        $payload['trigger_resolved_key'] = $resolvedEventKey;

        $stmt = $pdo->prepare('INSERT INTO communication_logs (user_id, order_id, channel, event_key, recipient, status, payload_json) VALUES (:user_id, :order_id, "email", :event_key, :recipient, "queued", :payload_json)');
        $stmt->execute([
            'user_id' => $userId > 0 ? $userId : null,
            'order_id' => $orderId > 0 ? $orderId : null,
            'event_key' => $resolvedEventKey,
            'recipient' => $recipient,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        $logId = (int)$pdo->lastInsertId();

        $queueStmt = $pdo->prepare('INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:communication_log_id, "email", :payload_json)');
        $queueStmt->execute([
            'communication_log_id' => $logId,
            'payload_json' => json_encode(['log_id' => $logId], JSON_UNESCAPED_SLASHES),
        ]);

        $jobStmt = $pdo->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ("send_communication", :payload_json, "queued", NOW(), 0)');
        $jobStmt->execute([
            'payload_json' => json_encode([
                'log_id' => $logId,
                'channel' => 'email',
                'event_key' => $resolvedEventKey,
                'recipient' => $recipient,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<int,string> */
    private function communicationAliasCandidates(string $eventKey): array
    {
        $candidates = [$eventKey];
        if (isset(self::EMAIL_EVENT_ALIASES[$eventKey])) {
            $candidates = array_merge($candidates, self::EMAIL_EVENT_ALIASES[$eventKey]);
        }

        foreach (self::EMAIL_EVENT_ALIASES as $canonical => $aliases) {
            if (in_array($eventKey, $aliases, true)) {
                $candidates = array_merge([$canonical], $aliases, $candidates);
            }
        }

        return array_values(array_unique(array_filter($candidates, static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        })));
    }

    private function resolveEmailEventKey(PDO $pdo, string $eventKey): string
    {
        $candidates = $this->communicationAliasCandidates($eventKey);
        if (count($candidates) === 0) {
            return $eventKey;
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $sql = 'SELECT event_key FROM communication_templates WHERE channel = "email" AND is_active = 1 AND event_key IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($candidates);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return $eventKey;
        }

        $activeKeys = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['event_key'] ?? ''));
            if ($key !== '') {
                $activeKeys[$key] = true;
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($activeKeys[$candidate])) {
                return $candidate;
            }
        }

        return $eventKey;
    }

    /** @return array<int,string> */
    private function crmAliasCandidates(string $settingKey): array
    {
        $candidates = [$settingKey];
        if (isset(self::CRM_SETTING_ALIASES[$settingKey])) {
            $candidates = array_merge($candidates, self::CRM_SETTING_ALIASES[$settingKey]);
        }

        foreach (self::CRM_SETTING_ALIASES as $canonical => $aliases) {
            if (in_array($settingKey, $aliases, true)) {
                $candidates = array_merge([$canonical], $aliases, $candidates);
            }
        }

        return array_values(array_unique(array_filter($candidates, static function ($value): bool {
            return is_string($value) && trim($value) !== '';
        })));
    }

    private function resolveCrmSettingKey(PDO $pdo, string $settingKey): string
    {
        $candidates = $this->crmAliasCandidates($settingKey);
        if (count($candidates) === 0) {
            return $settingKey;
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $sql = 'SELECT setting_key, is_enabled, endpoint, api_token FROM crm_settings WHERE setting_key IN (' . $placeholders . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($candidates);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return $settingKey;
        }

        $byKey = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $row;
            }
        }

        foreach ($candidates as $candidate) {
            if (!isset($byKey[$candidate])) {
                continue;
            }

            $row = $byKey[$candidate];
            $enabled = (int)($row['is_enabled'] ?? 0) === 1;
            $endpoint = trim((string)($row['endpoint'] ?? ''));
            $token = trim((string)($row['api_token'] ?? ''));
            if ($enabled && $endpoint !== '' && $token !== '') {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (isset($byKey[$candidate])) {
                return $candidate;
            }
        }

        return $settingKey;
    }

    /** @param array<string,mixed> $context */
    private function scheduleCompletedOrderFollowUps(PDO $pdo, array $context, int $adminId): int
    {
        $settings = $this->fetchFollowUpSettings($pdo);
        $scheduled = 0;

        $this->cancelPendingFollowUpsForOrder($pdo, (int)($context['order_id'] ?? 0));

        $anchor = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $context['last_order_month'] = $anchor->format('F');
        $context['last_order_year'] = $anchor->format('Y');

        $quarterlyInterval = max(1, (int)$settings['quarterly_follow_up_interval_months']);
        $quarterlyAt = $anchor->modify('+' . $quarterlyInterval . ' months');
        $context['quarterly_follow_up_interval_months'] = $quarterlyInterval;

        $scheduled += $this->insertFollowUp(
            $pdo,
            'quarterly_reorder',
            'follow_up_review',
            $quarterlyAt->format('Y-m-d H:i:s'),
            $context,
            $adminId
        );

        $basis = (string)$settings['annual_reminder_basis'];
        if ($basis === 'last_completed_order') {
            $this->cancelPendingAnnualReordersForCustomer($pdo, $context);
        }

        $annualLead = max(1, (int)$settings['annual_reminder_days_before']);
        $annualAt = $anchor->modify('+1 year')->modify('-' . $annualLead . ' days');
        $context['annual_anchor_completed_at'] = $anchor->format('Y-m-d H:i:s');
        $context['annual_lead_days'] = $annualLead;

        $scheduled += $this->insertFollowUp(
            $pdo,
            'annual_reorder',
            'annual_reorder',
            $annualAt->format('Y-m-d H:i:s'),
            $context,
            $adminId
        );

        return $scheduled;
    }

    /** @param array<string,mixed> $context */
    private function insertFollowUp(PDO $pdo, string $followUpType, string $triggerKey, string $scheduledFor, array $context, int $adminId): int
    {
        $notes = $context;
        $notes['follow_up_type'] = $followUpType;
        $notes['trigger_key'] = $triggerKey;
        $notes['scheduled_for'] = $scheduledFor;
        $notes['created_by_admin_id'] = $adminId;

        $stmt = $pdo->prepare(
            'INSERT INTO reminders (user_id, b2b_account_id, reminder_type, title, reminder_on, status, notes, created_by_admin_id)
             VALUES (:user_id, NULL, "follow_up", :title, :reminder_on, "pending", :notes, :created_by_admin_id)'
        );
        $title = 'Review follow-up';
        if ($followUpType === 'annual_reorder') {
            $title = 'Annual reorder reminder';
        } elseif ($followUpType === 'quarterly_reorder') {
            $title = 'Quarterly reorder reminder';
        }

        $stmt->execute([
            'user_id' => (int)($context['user_id'] ?? 0) > 0 ? (int)$context['user_id'] : null,
            'title' => $title,
            'reminder_on' => $scheduledFor,
            'notes' => json_encode($notes, JSON_UNESCAPED_SLASHES),
            'created_by_admin_id' => $adminId > 0 ? $adminId : null,
        ]);

        return 1;
    }

    private function cancelPendingFollowUpsForOrder(PDO $pdo, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE reminders SET status = "cancelled", updated_at = NOW() WHERE reminder_type = "follow_up" AND status = "pending" AND notes LIKE :notes_like');
        $stmt->execute(['notes_like' => '%"order_id":' . $orderId . '%']);
    }

    /** @param array<string,mixed> $context */
    private function cancelPendingAnnualReordersForCustomer(PDO $pdo, array $context): void
    {
        $userId = (int)($context['user_id'] ?? 0);
        $email = trim((string)($context['customer_email'] ?? ''));

        if ($userId > 0) {
            $stmt = $pdo->prepare('UPDATE reminders SET status = "cancelled", updated_at = NOW() WHERE reminder_type = "follow_up" AND status = "pending" AND notes LIKE :notes_like');
            $stmt->execute(['notes_like' => '%"follow_up_type":"annual_reorder"%"user_id":' . $userId . '%']);
            return;
        }

        if ($email !== '') {
            $stmt = $pdo->prepare('UPDATE reminders SET status = "cancelled", updated_at = NOW() WHERE reminder_type = "follow_up" AND status = "pending" AND notes LIKE :notes_like');
            $stmt->execute(['notes_like' => '%"follow_up_type":"annual_reorder"%"customer_email":"' . $email . '"%']);
        }
    }

    /** @param array<string,mixed> $row
     *  @param array<string,mixed> $context
     */
    private function scheduleNextAnnualReorder(PDO $pdo, array $row, array $context): void
    {
            $nextAt = new \DateTimeImmutable((string)$row['reminder_on'], new \DateTimeZone('Asia/Kolkata'));
        $nextAt = $nextAt->modify('+1 year');

        $exists = $pdo->prepare(
                        'SELECT id
                         FROM reminders
                         WHERE reminder_type = "follow_up"
                             AND status = "pending"
                             AND reminder_on = :scheduled_for
                             AND notes LIKE :notes_like
                         LIMIT 1'
        );
        $exists->bindValue(':scheduled_for', $nextAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
                $exists->bindValue(':notes_like', '%"follow_up_type":"annual_reorder"%' . '%"order_number":"' . (string)($context['order_number'] ?? '') . '"%', PDO::PARAM_STR);
        $exists->execute();

        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $context['annual_anchor_completed_at'] = (string)($context['annual_anchor_completed_at'] ?? '');
        $this->insertFollowUp(
            $pdo,
            'annual_reorder',
            'annual_reorder',
            $nextAt->format('Y-m-d H:i:s'),
            $context,
            (int)($row['created_by_admin_id'] ?? 0)
        );
    }

    /** @param array<string,mixed> $row
     *  @param array<string,mixed> $context
     */
    private function scheduleNextQuarterlyFollowUp(PDO $pdo, array $row, array $context): void
    {
        $months = max(1, (int)($context['quarterly_follow_up_interval_months'] ?? 3));
        $nextAt = new \DateTimeImmutable((string)$row['reminder_on'], new \DateTimeZone('Asia/Kolkata'));
        $nextAt = $nextAt->modify('+' . $months . ' months');

        $exists = $pdo->prepare(
            'SELECT id
             FROM reminders
             WHERE reminder_type = "follow_up"
               AND status = "pending"
               AND reminder_on = :scheduled_for
               AND notes LIKE :notes_like
             LIMIT 1'
        );
        $exists->bindValue(':scheduled_for', $nextAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $exists->bindValue(':notes_like', '%"follow_up_type":"quarterly_reorder"%' . '%"order_number":"' . (string)($context['order_number'] ?? '') . '"%', PDO::PARAM_STR);
        $exists->execute();

        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            return;
        }

        $context['quarterly_follow_up_interval_months'] = $months;
        $this->insertFollowUp(
            $pdo,
            'quarterly_reorder',
            'follow_up_review',
            $nextAt->format('Y-m-d H:i:s'),
            $context,
            (int)($row['created_by_admin_id'] ?? 0)
        );
    }

    /** @return array<string,string> */
    private function fetchFollowUpSettings(PDO $pdo): array
    {
        $settings = [
            'google_review_link' => '',
            'review_delay_days' => '3',
            'quarterly_follow_up_interval_months' => '3',
            'annual_reminder_days_before' => '7',
            'annual_reminder_basis' => 'last_completed_order',
            'celebration_reminder_days_before' => '7',
            'celebration_combined_email_on_same_day' => '1',
            'whatsapp_send_mode' => 'crm_trigger',
            'required_fields_note' => 'Name, Mobile and Email are compulsory for every CRM trigger push.',
        ];

        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("google_review_link", "review_delay_days", "quarterly_follow_up_interval_months", "annual_reminder_days_before", "annual_reminder_basis", "celebration_reminder_days_before", "celebration_combined_email_on_same_day", "whatsapp_send_mode", "required_fields_note")');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string)($row['setting_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $settings)) {
                $settings[$key] = (string)($row['setting_value'] ?? $settings[$key]);
            }
        }

        return $settings;
    }

    /** @param array<string,mixed> $context
     *  @return array{0:string,1:string}
     */
    private function buildCustomerEmailContent(string $eventKey, array $context): array
    {
        $name = htmlspecialchars((string)($context['customer_name'] ?? 'Valued Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string)($context['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $items = htmlspecialchars((string)($context['item_names'] ?? 'Cake order'), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string)($context['grand_total'] ?? '0.00'), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string)($context['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string)($context['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($eventKey === 'payment_confirmed') {
            return [
                'Payment Confirmed - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Your payment has been confirmed and your order is now in production.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Email:</strong> ' . $email . '</p>',
            ];
        }

        if ($eventKey === 'reject_order') {
            return [
                'Order Cancelled - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Your order has been marked as cancelled. If this needs correction, please contact Cakeouflage.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        if ($eventKey === 'manual_order_received') {
            return [
                'Order Received - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Your order has been punched by Team Cakeouflage and is now registered in your account.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Email:</strong> ' . $email . '</p><p>You can log in with email OTP to check order status anytime.</p>',
            ];
        }

        if ($eventKey === 'online_order_received') {
            return [
                'Order Placed - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Thank you for placing your order online with Cakeouflage.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Email:</strong> ' . $email . '</p><p>We will notify you as your order progresses.</p>',
            ];
        }

        if ($eventKey === 'ready_order') {
            return [
                'Order Ready - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Your order is now ready.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '</p>',
            ];
        }

        if ($eventKey === 'order_delivered') {
            return [
                'Order Delivered - ' . $orderNumber,
                '<p>Hello ' . $name . ',</p><p>Your order has been marked delivered. Thank you for choosing Cakeouflage.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p><p>We will follow up shortly for your feedback.</p>',
            ];
        }

        return [
            'Order Update - ' . $orderNumber,
            '<p>Hello ' . $name . ',</p><p>Your order has been updated.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '</p>',
        ];
    }

    /** @param array<string,mixed> $context
     *  @return array{0:string,1:string}
     */
    private function buildAdminEmailContent(string $eventKey, array $context): array
    {
        $name = htmlspecialchars((string)($context['customer_name'] ?? 'Valued Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string)($context['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $items = htmlspecialchars((string)($context['item_names'] ?? 'Cake order'), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string)($context['grand_total'] ?? '0.00'), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string)($context['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');

        if ($eventKey === 'payment_confirmed') {
            return [
                'Payment Confirmed - ' . $orderNumber,
                '<p>Customer payment has been confirmed.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        if ($eventKey === 'reject_order') {
            return [
                'Order Cancelled - ' . $orderNumber,
                '<p>An order has been marked cancelled.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        if ($eventKey === 'manual_order_received') {
            return [
                'Manual Order Punched - ' . $orderNumber,
                '<p>A manual admin order has been created.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p><p>Email and CRM WhatsApp trigger were queued.</p>',
            ];
        }

        if ($eventKey === 'online_order_received') {
            return [
                'New Online Order - ' . $orderNumber,
                '<p>A new online order has been received.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        if ($eventKey === 'ready_order') {
            return [
                'Order Ready - ' . $orderNumber,
                '<p>An order has been marked ready.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        if ($eventKey === 'order_delivered') {
            return [
                'Order Delivered - ' . $orderNumber,
                '<p>An order has been marked delivered.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
            ];
        }

        return [
            'Order Update - ' . $orderNumber,
            '<p>An order has been updated.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
        ];
    }

    /** @param array<string,mixed> $context
     *  @return array{0:string,1:string}
     */
    private function buildFollowUpEmailContent(string $eventKey, array $context): array
    {
        $name = htmlspecialchars((string)($context['customer_name'] ?? 'Valued Customer'), ENT_QUOTES, 'UTF-8');
        $orderNumber = htmlspecialchars((string)($context['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $items = htmlspecialchars((string)($context['item_names'] ?? 'Cake order'), ENT_QUOTES, 'UTF-8');
        $lastOrderMonth = htmlspecialchars((string)($context['last_order_month'] ?? date('F')), ENT_QUOTES, 'UTF-8');
        $annualLead = max(1, (int)($context['annual_lead_days'] ?? 7));
        $birthdayDate = htmlspecialchars((string)($context['birthday_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $anniversaryDate = htmlspecialchars((string)($context['anniversary_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $orderLink = htmlspecialchars((string)($context['order_link'] ?? 'https://cakeouflage.com/shop'), ENT_QUOTES, 'UTF-8');

        if ($eventKey === 'follow_up_review') {
            $reviewLink = htmlspecialchars((string)($this->fetchFollowUpSettingsCache($context, 'google_review_link')), ENT_QUOTES, 'UTF-8');
            $cta = $reviewLink !== '' ? '<p><a href="' . $reviewLink . '">Leave a Google Review</a></p>' : '';
            return [
                'Time for your next Cakeouflage order',
                '<p>Hello ' . $name . ',</p><p>You last ordered in ' . $lastOrderMonth . ' (order ' . $orderNumber . ').</p><p>We will be happy to serve you again for your next celebration cake.</p><p>Your last order item was ' . $items . '.</p>' . $cta,
            ];
        }

        if ($eventKey === 'birthday_greeting_email') {
            return [
                'Happy Birthday from Cakeouflage',
                '<p>Hello ' . $name . ',</p><p>Happy Birthday from all of us at Cakeouflage.</p><p>Wishing you a joyful year full of celebrations and sweet memories.</p><p><a href="' . $orderLink . '">Order your birthday cake</a></p>',
            ];
        }

        if ($eventKey === 'birthday_preorder_email') {
            return [
                'Your birthday is coming up - plan your cake',
                '<p>Hello ' . $name . ',</p><p>Your birthday date (' . $birthdayDate . ') is approaching.</p><p>Book your cake in advance so we can prepare your preferred design on time.</p><p><a href="' . $orderLink . '">Pre-order birthday cake now</a></p>',
            ];
        }

        if ($eventKey === 'anniversary_greeting_email') {
            return [
                'Happy Anniversary from Cakeouflage',
                '<p>Hello ' . $name . ',</p><p>Happy Anniversary from Team Cakeouflage.</p><p>Wishing your celebration a beautiful and sweet start.</p><p><a href="' . $orderLink . '">Explore anniversary cakes</a></p>',
            ];
        }

        if ($eventKey === 'anniversary_preorder_email') {
            return [
                'Your anniversary is near - order cake in advance',
                '<p>Hello ' . $name . ',</p><p>Your anniversary date (' . $anniversaryDate . ') is coming soon.</p><p>Place your order early to secure your preferred slot and design.</p><p><a href="' . $orderLink . '">Pre-order anniversary cake now</a></p>',
            ];
        }

        if ($eventKey === 'celebration_combined_email') {
            return [
                'Special celebration wishes from Cakeouflage',
                '<p>Hello ' . $name . ',</p><p>Wishing you a wonderful celebration season from all of us at Cakeouflage.</p><p>If you are planning your next event, we would love to craft your cake.</p><p><a href="' . $orderLink . '">Plan your celebration cake</a></p>',
            ];
        }

        return [
            'Plan your celebration cake early',
            '<p>Hello ' . $name . ',</p><p>Your yearly celebration date for order ' . $orderNumber . ' is approaching in about ' . $annualLead . ' days.</p><p>To avoid any last moment rush, order your celebration cake now and get it ready on your desired date.</p><p>We would love to help again with ' . $items . '.</p>',
        ];
    }

    /** @param array<string,mixed> $context */
    private function fetchFollowUpSettingsCache(array $context, string $key): string
    {
        if (isset($context[$key]) && is_scalar($context[$key])) {
            return trim((string)$context[$key]);
        }
        return '';
    }

    /** @param array<string,mixed> $context
     *  @return array<string,string>
     */
    private function buildCrmParams(string $apiToken, array $context): array
    {
        $customerName = trim((string)($context['customer_name'] ?? ''));
        $customerEmail = trim((string)($context['customer_email'] ?? ''));
        $phone = $this->normalizePhoneForCrm((string)($context['customer_phone'] ?? ''));

        $params = [
            'api_token' => $apiToken,
            'contact_name' => $customerName,
            'contact_phone' => $phone,
            'contact_email' => $customerEmail,
            'contact.name' => $customerName,
            'contact.first_name' => trim((string)($context['first_name'] ?? $customerName)),
            'contact.mobile' => $phone,
            'contact.phone' => $phone,
            'contact.email' => $customerEmail,
            'contact.orderid' => trim((string)($context['order_number'] ?? '')),
            'contact.amount' => trim((string)($context['grand_total'] ?? '0.00')),
            'contact.item' => trim((string)($context['item_names'] ?? 'Cake order')),
            'contact.upi_link' => trim((string)($context['upi_link'] ?? '')),
        ];

        // Allow custom CRM keys provided by specific workflows (e.g., build-your-own-cake webhook mapping).
        foreach ($context as $key => $value) {
            if (!is_string($key) || strpos($key, 'contact.') !== 0 || !is_scalar($value)) {
                continue;
            }
            $params[$key] = trim((string)$value);
        }

        return $params;
    }

    private function normalizePhoneForCrm(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        if (strpos($trimmed, '+') === 0) {
            $withPlusDigits = '+' . (preg_replace('/\D+/', '', substr($trimmed, 1)) ?: '');
            return $withPlusDigits === '+' ? '' : $withPlusDigits;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '91') !== 0) {
            $digits = '91' . $digits;
        }

        return '+' . $digits;
    }
}