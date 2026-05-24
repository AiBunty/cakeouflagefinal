<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class PaymentReceiptService
{
    private PDO $pdo;
    private static ?bool $schemaReady = null;
    /** @var array<string,bool> */
    private static array $tablePresence = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @return array{success:bool,existing:bool,message:string,receipt?:array<string,mixed>}
     */
    public function issueAdvanceReceipt(int $orderId, array $context = []): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'existing' => false, 'message' => 'Invalid order id'];
        }
        if (!$this->isSchemaReady()) {
            return ['success' => false, 'existing' => false, 'message' => 'Payment receipt schema is not ready'];
        }

        $sourceEvent = trim((string)($context['source_event'] ?? ''));
        $sourceReference = trim((string)($context['source_reference'] ?? ''));
        if ($sourceEvent === '' || $sourceReference === '') {
            return ['success' => false, 'existing' => false, 'message' => 'Receipt source context is required'];
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $existing = $this->findReceiptBySource($orderId, $sourceEvent, $sourceReference, true);
            if ($existing) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return ['success' => true, 'existing' => true, 'message' => 'Receipt already exists', 'receipt' => $existing];
            }

            $order = $this->fetchOrderSnapshot($orderId, true);
            if (!$order) {
                throw new \RuntimeException('Order not found');
            }

            $amount = round((float)($context['amount'] ?? $this->resolveReceivedAmount($order)), 2);
            if ($amount <= 0) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                }
                return ['success' => false, 'existing' => false, 'message' => 'No advance amount available for receipt'];
            }

            $sequenceNo = $this->nextSequenceNumber($orderId);
            $receiptNumber = $this->buildReceiptNumber((string)($order['order_number'] ?? ('ORD-' . $orderId)), $sequenceNo);
            $balanceDue = round((float)($context['balance_due'] ?? $this->resolveBalanceDue($order, $amount)), 2);
            $issuedAt = trim((string)($context['issued_at'] ?? ''));
            if ($issuedAt === '') {
                $issuedAt = date('Y-m-d H:i:s');
            }

            $metadata = $context['metadata'] ?? [];
            if (!is_array($metadata)) {
                $metadata = ['value' => $metadata];
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO payment_receipts
                    (order_id, receipt_number, receipt_type, sequence_no, amount, balance_due, payment_method, payment_status_snapshot, collection_status_snapshot, source_event, source_reference, financial_transaction_id, payment_id, issued_by_admin_id, receipt_html, metadata_json, issued_at, created_at, updated_at)
                 VALUES
                    (:order_id, :receipt_number, :receipt_type, :sequence_no, :amount, :balance_due, :payment_method, :payment_status_snapshot, :collection_status_snapshot, :source_event, :source_reference, :financial_transaction_id, :payment_id, :issued_by_admin_id, :receipt_html, :metadata_json, :issued_at, NOW(), NOW())'
            );
            $stmt->execute([
                'order_id' => $orderId,
                'receipt_number' => $receiptNumber,
                'receipt_type' => (string)($context['receipt_type'] ?? 'advance'),
                'sequence_no' => $sequenceNo,
                'amount' => $amount,
                'balance_due' => $balanceDue,
                'payment_method' => (string)($context['payment_method'] ?? ($order['payment_method'] ?? '')),
                'payment_status_snapshot' => (string)($context['payment_status'] ?? ($order['payment_status'] ?? '')),
                'collection_status_snapshot' => (string)($context['collection_status'] ?? ($order['collection_status'] ?? '')),
                'source_event' => $sourceEvent,
                'source_reference' => $sourceReference,
                'financial_transaction_id' => isset($context['financial_transaction_id']) ? (int)$context['financial_transaction_id'] : null,
                'payment_id' => isset($context['payment_id']) ? (int)$context['payment_id'] : null,
                'issued_by_admin_id' => isset($context['issued_by_admin_id']) ? (int)$context['issued_by_admin_id'] : null,
                'receipt_html' => isset($context['receipt_html']) ? (string)$context['receipt_html'] : null,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'issued_at' => $issuedAt,
            ]);

            $receipt = $this->getReceiptById((int)$this->pdo->lastInsertId());
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return ['success' => true, 'existing' => false, 'message' => 'Receipt issued', 'receipt' => $receipt ?? []];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'existing' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getLatestReceiptForOrder(int $orderId): ?array
    {
        if ($orderId <= 0 || !$this->isSchemaReady()) {
            return null;
        }

        $stmt = $this->pdo->prepare($this->receiptSelectSql('pr.order_id = :order_id') . ' ORDER BY pr.issued_at DESC, pr.id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getReceiptHistoryForOrder(int $orderId): array
    {
        if ($orderId <= 0 || !$this->isSchemaReady()) {
            return [];
        }

        $stmt = $this->pdo->prepare($this->receiptSelectSql('pr.order_id = :order_id') . ' ORDER BY pr.issued_at DESC, pr.id DESC');
        $stmt->execute(['order_id' => $orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function isSchemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        self::$schemaReady = $this->tableExists('payment_receipts');
        return self::$schemaReady;
    }

    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, self::$tablePresence)) {
            return self::$tablePresence[$tableName];
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            self::$tablePresence[$tableName] = false;
            return false;
        }

        $stmt = $this->pdo->query("SHOW TABLES LIKE '" . $tableName . "'");
        self::$tablePresence[$tableName] = (bool)$stmt->fetchColumn();
        return self::$tablePresence[$tableName];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchOrderSnapshot(int $orderId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT id, user_id, order_number, customer_name, customer_phone, customer_email, payment_method, payment_status, collection_status, grand_total, advance_amount, advance_received_amount, net_collected_amount, balance_due_amount FROM orders WHERE id = :id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findReceiptBySource(int $orderId, string $sourceEvent, string $sourceReference, bool $forUpdate = false): ?array
    {
        $sql = $this->receiptSelectSql('pr.order_id = :order_id AND pr.source_event = :source_event AND pr.source_reference = :source_reference') . ' LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'order_id' => $orderId,
            'source_event' => $sourceEvent,
            'source_reference' => $sourceReference,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getReceiptById(int $receiptId): ?array
    {
        if ($receiptId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare($this->receiptSelectSql('pr.id = :id') . ' LIMIT 1');
        $stmt->execute(['id' => $receiptId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function receiptSelectSql(string $whereClause): string
    {
        $select = 'SELECT pr.*, a.full_name AS issued_by_name';
        $joins = ' FROM payment_receipts pr LEFT JOIN admins a ON a.id = pr.issued_by_admin_id';

        if ($this->tableExists('financial_transactions')) {
            $select .= ', ft.transaction_type AS financial_transaction_type, ft.narration AS financial_narration, ft.amount AS financial_amount, ft.created_at AS financial_created_at';
            $joins .= ' LEFT JOIN financial_transactions ft ON ft.id = pr.financial_transaction_id';
        } else {
            $select .= ', NULL AS financial_transaction_type, NULL AS financial_narration, NULL AS financial_amount, NULL AS financial_created_at';
        }

        return $select . $joins . ' WHERE ' . $whereClause;
    }

    private function nextSequenceNumber(int $orderId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM payment_receipts WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);
        $value = $stmt->fetchColumn();
        return max(1, (int)$value);
    }

    private function buildReceiptNumber(string $orderNumber, int $sequenceNo): string
    {
        $normalizedOrder = preg_replace('/[^A-Za-z0-9\-]/', '', $orderNumber);
        $normalizedOrder = is_string($normalizedOrder) && $normalizedOrder !== '' ? $normalizedOrder : 'ORDER';
        return 'PR-' . $normalizedOrder . '-' . str_pad((string)$sequenceNo, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string,mixed> $order
     */
    private function isFullyPaid(array $order): bool
    {
        $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
        if ($paymentStatus !== 'paid') {
            return false;
        }

        $grandTotal = (float)($order['grand_total'] ?? 0);
        if ($grandTotal <= 0.01) {
            return false;
        }

        $balanceDue = $this->resolveBalanceDue($order, $this->resolveReceivedAmount($order));
        return $balanceDue <= 0.01;
    }

    /**
     * @param array<string,mixed> $order
     */
    private function resolveReceivedAmount(array $order): float
    {
        return max(
            (float)($order['advance_received_amount'] ?? 0),
            (float)($order['net_collected_amount'] ?? 0),
            (float)($order['advance_amount'] ?? 0)
        );
    }

    /**
     * @param array<string,mixed> $order
     */
    private function resolveBalanceDue(array $order, float $receivedAmount): float
    {
        $balanceDue = (float)($order['balance_due_amount'] ?? 0);
        if ($balanceDue > 0.01) {
            return $balanceDue;
        }

        $grandTotal = (float)($order['grand_total'] ?? 0);
        if ($grandTotal <= 0.01) {
            return 0.0;
        }

        return max(0.0, round($grandTotal - $receivedAmount, 2));
    }
}