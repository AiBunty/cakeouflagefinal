<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class OrderAutomationService
{
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

            if ($status === 'completed') {
                $result['emails_queued'] += $this->queueCustomerStatusEmail($pdo, 'ready_order', $context);
                $result['emails_queued'] += $this->queueAdminStatusEmail($pdo, 'ready_order', $context);
                $result['crm_jobs_queued'] += $this->maybeQueueCrmTriggerJob($pdo, 'ready_order', $context);
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
             WHERE reminder_type = "follow_up" AND status = "pending" AND reminder_on <= NOW()
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

            $pdo->beginTransaction();
            try {
                $type = (string)($context['follow_up_type'] ?? 'review');
                if ($type === 'review') {
                    $scheduled += $this->queueFollowUpEmail($pdo, 'follow_up_review', $context);
                    $scheduled += $this->maybeQueueCrmTriggerJob($pdo, 'follow_up_review', $context, $followUpId);
                } elseif ($type === 'annual_reorder') {
                    $scheduled += $this->queueFollowUpEmail($pdo, 'annual_reorder', $context);
                    $scheduled += $this->maybeQueueCrmTriggerJob($pdo, 'annual_reorder', $context, $followUpId);
                    $this->scheduleNextAnnualReorder($pdo, $row, $context);
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
        list($subject, $body) = $this->buildAdminEmailContent($eventKey, $context);
        $this->queueEmailCommunication($pdo, 0, (int)($context['order_id'] ?? 0), 'cakeouflage@gmail.com', $eventKey . '_admin', $context, $subject, $body);
        return 1;
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

        $check = $pdo->prepare('SELECT id FROM crm_settings WHERE setting_key = :setting_key AND is_enabled = 1 AND COALESCE(endpoint, "") <> "" AND COALESCE(api_token, "") <> "" LIMIT 1');
        $check->execute(['setting_key' => $settingKey]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            return 0;
        }

        $payload = [
            'setting_key' => $settingKey,
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
    }

    /** @param array<string,mixed> $context */
    private function queueEmailCommunication(PDO $pdo, int $userId, int $orderId, string $recipient, string $eventKey, array $context, string $subject, string $body): void
    {
        $payload = $context;
        $payload['subject'] = $subject;
        $payload['body_template'] = $body;

        $stmt = $pdo->prepare('INSERT INTO communication_logs (user_id, order_id, channel, event_key, recipient, status, payload_json) VALUES (:user_id, :order_id, "email", :event_key, :recipient, "queued", :payload_json)');
        $stmt->execute([
            'user_id' => $userId > 0 ? $userId : null,
            'order_id' => $orderId > 0 ? $orderId : null,
            'event_key' => $eventKey,
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
                'event_key' => $eventKey,
                'recipient' => $recipient,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string,mixed> $context */
    private function scheduleCompletedOrderFollowUps(PDO $pdo, array $context, int $adminId): int
    {
        $settings = $this->fetchFollowUpSettings($pdo);
        $scheduled = 0;

        $this->cancelPendingFollowUpsForOrder($pdo, (int)($context['order_id'] ?? 0));

        $reviewDelay = max(1, (int)$settings['review_delay_days']);
        $reviewAt = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $reviewAt = $reviewAt->modify('+' . $reviewDelay . ' days');
        $scheduled += $this->insertFollowUp(
            $pdo,
            'review',
            'follow_up_review',
            $reviewAt->format('Y-m-d H:i:s'),
            $context,
            $adminId
        );

        $basis = (string)$settings['annual_reminder_basis'];
        if ($basis === 'last_completed_order') {
            $this->cancelPendingAnnualReordersForCustomer($pdo, $context);
        }

        $annualLead = max(1, (int)$settings['annual_reminder_days_before']);
        $anchor = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
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
        $stmt->execute([
            'user_id' => (int)($context['user_id'] ?? 0) > 0 ? (int)$context['user_id'] : null,
            'title' => $followUpType === 'annual_reorder' ? 'Annual reorder reminder' : 'Review follow-up',
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

    /** @return array<string,string> */
    private function fetchFollowUpSettings(PDO $pdo): array
    {
        $settings = [
            'google_review_link' => '',
            'review_delay_days' => '3',
            'annual_reminder_days_before' => '6',
            'annual_reminder_basis' => 'last_completed_order',
            'whatsapp_send_mode' => 'crm_trigger',
            'required_fields_note' => 'Name, Mobile and Email are compulsory for every CRM trigger push.',
        ];

        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings WHERE setting_key IN ("google_review_link", "review_delay_days", "annual_reminder_days_before", "annual_reminder_basis", "whatsapp_send_mode", "required_fields_note")');
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

        return [
            'Order Ready - ' . $orderNumber,
            '<p>Hello ' . $name . ',</p><p>Your order is ready for pickup or delivery.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Item:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '<br><strong>Phone:</strong> ' . $phone . '</p>',
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

        return [
            'Order Ready - ' . $orderNumber,
            '<p>An order has been marked ready/completed.</p><p><strong>Order ID:</strong> ' . $orderNumber . '<br><strong>Customer:</strong> ' . $name . '<br><strong>Phone:</strong> ' . $phone . '<br><strong>Items:</strong> ' . $items . '<br><strong>Amount:</strong> Rs ' . $amount . '</p>',
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

        if ($eventKey === 'follow_up_review') {
            $reviewLink = htmlspecialchars((string)($this->fetchFollowUpSettingsCache($context, 'google_review_link')), ENT_QUOTES, 'UTF-8');
            $cta = $reviewLink !== '' ? '<p><a href="' . $reviewLink . '">Leave a Google Review</a></p>' : '';
            return [
                'How was your Cakeouflage order?',
                '<p>Hello ' . $name . ',</p><p>We hope you enjoyed your order ' . $orderNumber . '.</p><p>If you have a minute, please share a quick review for ' . $items . '.</p>' . $cta,
            ];
        }

        return [
            'Time to plan your next cake order',
            '<p>Hello ' . $name . ',</p><p>It has almost been a year since order ' . $orderNumber . '.</p><p>If you are planning a repeat celebration, we would love to help again with ' . $items . '.</p>',
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

        return [
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
    }

    private function normalizePhoneForCrm(string $phone): string
    {
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