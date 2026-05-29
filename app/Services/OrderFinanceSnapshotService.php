<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class OrderFinanceSnapshotService
{
    /** @var array<string,bool>|null */
    private static ?array $ordersColumnMap = null;
    /** @var array<int,string>|null */
    private static ?array $refundStatusEnumValues = null;

    /**
     * @return array<string,mixed>
     */
    public function buildSnapshot(PDO $pdo, int $orderId): array
    {
        $orderStmt = $pdo->prepare(
            'SELECT id, order_number, payment_status, grand_total,
                COALESCE(revised_grand_total, grand_total) AS effective_total,
                    COALESCE(advance_amount, 0) AS advance_amount,
                    COALESCE(advance_received_amount, 0) AS advance_received_amount,
                    COALESCE(refund_amount, 0) AS refund_amount,
                    COALESCE(total_refunded, 0) AS total_refunded
             FROM orders
             WHERE id = :id
             LIMIT 1'
        );
        $orderStmt->execute([':id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return [
                'ok' => false,
                'error' => 'Order not found',
            ];
        }

        $grossTotal = round((float)($order['effective_total'] ?? $order['grand_total'] ?? 0), 2);
        $paymentStatus = strtolower(trim((string)($order['payment_status'] ?? 'pending')));

        $refundAgg = $this->refundAgg($pdo, $orderId);
        $refundTotal = max(
            round((float)($order['refund_amount'] ?? 0), 2),
            round((float)($order['total_refunded'] ?? 0), 2),
            round((float)($refundAgg['processed_total'] ?? 0), 2)
        );

        $invoiceAgg = $this->invoiceAgg($pdo, $orderId);
        $paymentAgg = $this->paymentAgg($pdo, $orderId);
        $financialAgg = $this->financialAgg($pdo, $orderId);

        $advancePlanned = round((float)($order['advance_amount'] ?? 0), 2);
        $advanceReceived = round((float)($order['advance_received_amount'] ?? 0), 2);
        $invoicePaid = round((float)($invoiceAgg['paid_total'] ?? 0), 2);
        $paymentsVerified = round((float)($paymentAgg['verified_total'] ?? 0), 2);
        $financialPosted = round((float)($financialAgg['posted_collection_total'] ?? 0), 2);

        $measuredCollected = max($advanceReceived, $invoicePaid, $paymentsVerified, $financialPosted);
        $recognizedFromStatus = 0.0;
        if ($measuredCollected <= 0.01 && in_array($paymentStatus, ['paid', 'partially_refunded', 'refunded'], true)) {
            $recognizedFromStatus = max(0.0, round($grossTotal - $refundTotal, 2));
        }

        $rawCollected = max($measuredCollected, $recognizedFromStatus);
        $netPayable = max(0.0, round($grossTotal - $refundTotal, 2));
        $collected = min($rawCollected, $netPayable > 0 ? $netPayable : $rawCollected);
        $advanceReceivedDerived = $advanceReceived;
        if ($advanceReceivedDerived <= 0 && $advancePlanned > 0 && $collected > 0) {
            $advanceReceivedDerived = min($advancePlanned, $collected);
        }
        $balanceDue = max(0.0, round($netPayable - $collected, 2));

        $collectionStatus = 'payment_pending';
        if ($grossTotal > 0 && $refundTotal >= $grossTotal) {
            $collectionStatus = 'refunded';
        } elseif ($balanceDue <= 0.01 && $collected > 0) {
            $collectionStatus = 'fully_paid';
        } elseif ($collected > 0) {
            $collectionStatus = 'advance_paid';
        }

        return [
            'ok' => true,
            'order_id' => (int)$order['id'],
            'order_number' => (string)$order['order_number'],
            'payment_status' => $paymentStatus,
            'gross_total' => $grossTotal,
            'advance_planned' => $advancePlanned,
            'advance_received' => $advanceReceivedDerived,
            'refund_total' => $refundTotal,
            'refund_processed_total' => round((float)($refundAgg['processed_total'] ?? 0), 2),
            'invoice_paid_total' => $invoicePaid,
            'invoice_balance_total' => round((float)($invoiceAgg['balance_total'] ?? 0), 2),
            'payments_verified_total' => $paymentsVerified,
            'financial_posted_collection_total' => $financialPosted,
            'collected_total' => $collected,
            'balance_due' => $balanceDue,
            'collection_status' => $collectionStatus,
            'suggested_refund_status' => $this->deriveRefundStatus($grossTotal, $refundAgg),
            'invoice_status_hint' => (string)($invoiceAgg['status_hint'] ?? ''),
            'financial_last_event' => (string)($financialAgg['last_event'] ?? ''),
        ];
    }

    public function syncOrderFinancialColumns(PDO $pdo, int $orderId): void
    {
        $snapshot = $this->buildSnapshot($pdo, $orderId);
        if (empty($snapshot['ok'])) {
            return;
        }

        $columns = $this->ordersColumns($pdo);
        $sets = [];
        $params = [':id' => $orderId];

        if (!empty($columns['advance_received_amount'])) {
            $sets[] = 'advance_received_amount = :advance_received_amount';
            $params[':advance_received_amount'] = (float)$snapshot['advance_received'];
        }
        if (!empty($columns['net_collected_amount'])) {
            $sets[] = 'net_collected_amount = :net_collected_amount';
            $params[':net_collected_amount'] = (float)$snapshot['collected_total'];
        }
        if (!empty($columns['balance_due_amount'])) {
            $sets[] = 'balance_due_amount = :balance_due_amount';
            $params[':balance_due_amount'] = (float)$snapshot['balance_due'];
        }
        if (!empty($columns['collection_status'])) {
            $sets[] = 'collection_status = :collection_status';
            $params[':collection_status'] = (string)$snapshot['collection_status'];
        }
        if (!empty($columns['total_refunded'])) {
            $sets[] = 'total_refunded = :total_refunded';
            $params[':total_refunded'] = (float)$snapshot['refund_total'];
        }
        if (!empty($columns['refund_amount'])) {
            $sets[] = 'refund_amount = :refund_amount';
            $params[':refund_amount'] = (float)$snapshot['refund_total'];
        }
        if (!empty($columns['refund_status'])) {
            $enumValues = $this->refundStatusEnumValues($pdo);
            $targetRefundStatus = $this->normalizeRefundStatusForEnum((string)($snapshot['suggested_refund_status'] ?? 'none'), $enumValues);
            $sets[] = 'refund_status = :refund_status';
            $params[':refund_status'] = $targetRefundStatus;
        }

        // Keep order payment status aligned when financial collection proves full settlement.
        $currentPaymentStatus = (string)$snapshot['payment_status'];
        if (in_array($currentPaymentStatus, ['pending', 'under_review', 'failed', 'rejected'], true)
            && (float)$snapshot['balance_due'] <= 0.01
            && (float)$snapshot['collected_total'] > 0
            && !empty($columns['payment_status'])
        ) {
            $sets[] = 'payment_status = :payment_status';
            $params[':payment_status'] = 'paid';
            if (!empty($columns['payment_confirmed_at'])) {
                $sets[] = 'payment_confirmed_at = COALESCE(payment_confirmed_at, NOW())';
            }
        }

        if ($sets === []) {
            return;
        }

        $sql = 'UPDATE orders SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /** @return array<string,mixed> */
    private function refundAgg(PDO $pdo, int $orderId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(CASE WHEN status = "processed" THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS processed_total,
                        COALESCE(SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END), 0) AS processed_count,
                        COALESCE(SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END), 0) AS approved_count,
                        COALESCE(SUM(CASE WHEN status = "requested" THEN 1 ELSE 0 END), 0) AS requested_count
                 FROM refund_transactions
                 WHERE order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
            return (array)($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            return [
                'processed_total' => 0.0,
                'processed_count' => 0,
                'approved_count' => 0,
                'requested_count' => 0,
            ];
        }
    }

    /** @param array<string,mixed> $refundAgg */
    private function deriveRefundStatus(float $grossTotal, array $refundAgg): string
    {
        $processedTotal = round((float)($refundAgg['processed_total'] ?? 0), 2);
        $processedCount = (int)($refundAgg['processed_count'] ?? 0);
        $approvedCount = (int)($refundAgg['approved_count'] ?? 0);
        $requestedCount = (int)($refundAgg['requested_count'] ?? 0);

        if ($processedCount > 0 || $processedTotal > 0.0) {
            if ($grossTotal > 0.0 && $processedTotal >= round($grossTotal - 0.01, 2)) {
                return 'fully_refunded';
            }
            return 'partially_refunded';
        }

        if ($approvedCount > 0) {
            return 'approved';
        }
        if ($requestedCount > 0) {
            return 'requested';
        }

        return 'none';
    }

    /** @param array<int,string> $enumValues */
    private function normalizeRefundStatusForEnum(string $targetStatus, array $enumValues): string
    {
        if ($enumValues === []) {
            return $targetStatus;
        }

        if (in_array($targetStatus, $enumValues, true)) {
            return $targetStatus;
        }

        if (in_array($targetStatus, ['partially_refunded', 'fully_refunded'], true) && in_array('processed', $enumValues, true)) {
            return 'processed';
        }

        if (in_array('none', $enumValues, true)) {
            return 'none';
        }

        return $enumValues[0];
    }

    /** @return array<int,string> */
    private function refundStatusEnumValues(PDO $pdo): array
    {
        if (is_array(self::$refundStatusEnumValues)) {
            return self::$refundStatusEnumValues;
        }

        self::$refundStatusEnumValues = [];

        try {
            $stmt = $pdo->query(
                'SELECT COLUMN_TYPE
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = "orders"
                   AND column_name = "refund_status"
                 LIMIT 1'
            );
            $columnType = (string)($stmt ? $stmt->fetchColumn() : '');
            if ($columnType !== '' && preg_match('/^enum\\((.*)\\)$/i', $columnType, $m)) {
                $parts = str_getcsv($m[1], ',', "'");
                $values = [];
                foreach ($parts as $part) {
                    $v = trim((string)$part);
                    if ($v !== '') {
                        $values[] = $v;
                    }
                }
                if ($values !== []) {
                    self::$refundStatusEnumValues = $values;
                }
            }
        } catch (Throwable $e) {
            self::$refundStatusEnumValues = [];
        }

        return self::$refundStatusEnumValues;
    }

    /** @return array<string,mixed> */
    private function invoiceAgg(PDO $pdo, int $orderId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(COALESCE(paid_amount, 0)), 0) AS paid_total,
                        COALESCE(SUM(COALESCE(balance_due, 0)), 0) AS balance_total,
                        MAX(COALESCE(invoice_status, "")) AS status_hint
                 FROM invoices
                 WHERE order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
            return (array)($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            return ['paid_total' => 0.0, 'balance_total' => 0.0, 'status_hint' => ''];
        }
    }

    /** @return array<string,mixed> */
    private function paymentAgg(PDO $pdo, int $orderId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(CASE WHEN p.payment_status IN ("verified", "paid", "success", "part_paid") THEN COALESCE(p.amount, 0) ELSE 0 END), 0) AS verified_total
                 FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.order_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
            return (array)($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            return ['verified_total' => 0.0];
        }
    }

    /** @return array<string,mixed> */
    private function financialAgg(PDO $pdo, int $orderId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COALESCE(SUM(CASE
                            WHEN status = "posted" AND transaction_type IN ("order_payment_received", "payment_received", "balance_settled")
                            THEN COALESCE(amount, 0) ELSE 0 END), 0) AS posted_collection_total,
                        COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(transaction_type ORDER BY created_at DESC, id DESC SEPARATOR ","), ",", 1), "") AS last_event
                 FROM financial_transactions
                 WHERE reference_type = "order" AND reference_id = :order_id'
            );
            $stmt->execute([':order_id' => $orderId]);
            return (array)($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable $e) {
            return ['posted_collection_total' => 0.0, 'last_event' => ''];
        }
    }

    /** @return array<string,bool> */
    private function ordersColumns(PDO $pdo): array
    {
        if (is_array(self::$ordersColumnMap)) {
            return self::$ordersColumnMap;
        }

        self::$ordersColumnMap = [];
        $schemaStmt = $pdo->query('SELECT DATABASE()');
        $schema = (string)($schemaStmt ? $schemaStmt->fetchColumn() : '');
        if ($schema === '') {
            return self::$ordersColumnMap;
        }

        $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = :schema AND table_name = "orders"');
        $stmt->execute([':schema' => $schema]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            self::$ordersColumnMap[(string)$col] = true;
        }

        return self::$ordersColumnMap;
    }
}
