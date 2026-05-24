<?php
declare(strict_types=1);

use App\Core\Database;

final class RepairOrderStateCommand
{
    private const PAID_STATES = ['paid', 'credit', 'refund_pending', 'partially_refunded', 'refunded'];
    private const REFUND_ORDER_STATES = ['partially_refunded', 'fully_refunded', 'refunded'];

    public function run(array $argv): int
    {
        $apply = in_array('--apply', $argv, true);
        $dryRun = in_array('--dry-run', $argv, true) || !$apply;
        $limit = $this->extractLimit($argv);

        $pdo = Database::getConnection();

        $report = [
            'checked' => 0,
            'issues' => 0,
            'fixed' => 0,
            'exceptions' => 0,
            'categories' => [
                'paid_cancelled' => 0,
                'delivered_then_cancelled' => 0,
                'refund_revenue_mismatch' => 0,
                'invalid_combo' => 0,
                'duplicate_closure' => 0,
            ],
        ];

        $orders = $pdo->query('SELECT id, order_number, order_status, payment_status, grand_total, COALESCE(total_refunded,0) AS total_refunded, created_at FROM orders ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as $order) {
            $report['checked']++;
            $orderId = (int)$order['id'];
            $orderStatus = (string)$order['order_status'];
            $paymentStatus = (string)$order['payment_status'];
            $grandTotal = (float)$order['grand_total'];
            $totalRefunded = (float)$order['total_refunded'];

            $issues = [];
            $recommendation = null;

            if ($orderStatus === 'cancelled' && in_array($paymentStatus, self::PAID_STATES, true)) {
                $issues[] = 'paid_cancelled';
                $report['categories']['paid_cancelled']++;
                $recommendation = 'convert_to_fully_refunded';
            }

            $hasDeliveredThenCancelled = $this->hasDeliveredThenCancelled($pdo, $orderId);
            if ($hasDeliveredThenCancelled) {
                $issues[] = 'delivered_then_cancelled';
                $report['categories']['delivered_then_cancelled']++;
                $recommendation = 'convert_to_fully_refunded';
            }

            if (in_array($orderStatus, self::REFUND_ORDER_STATES, true) && $totalRefunded <= 0) {
                $issues[] = 'refund_revenue_mismatch';
                $report['categories']['refund_revenue_mismatch']++;
                if ($recommendation === null) {
                    $recommendation = 'rebuild_refund_totals';
                }
            }

            if ($orderStatus === 'cancelled' && in_array($paymentStatus, ['refunded', 'partially_refunded'], true)) {
                $issues[] = 'invalid_combo';
                $report['categories']['invalid_combo']++;
                if ($recommendation === null) {
                    $recommendation = 'convert_to_fully_refunded';
                }
            }

            if ($this->hasDuplicateTerminalClosures($pdo, $orderId)) {
                $issues[] = 'duplicate_closure';
                $report['categories']['duplicate_closure']++;
                if ($recommendation === null) {
                    $recommendation = 'mark_historical_exception';
                }
            }

            if (empty($issues)) {
                continue;
            }

            $report['issues']++;
            $this->printIssue($order, $issues, (string)$recommendation, $dryRun);

            if ($dryRun || $recommendation === null) {
                continue;
            }

            $fixed = $this->applyRecommendation($pdo, $order, (string)$recommendation, $issues);
            if ($fixed) {
                $report['fixed']++;
            } else {
                $report['exceptions']++;
            }
        }

        $this->printSummary($report, $dryRun);

        $pass = $report['issues'] === 0 || ($apply && $report['issues'] === $report['fixed']);
        return $pass ? 0 : 2;
    }

    private function hasDeliveredThenCancelled(PDO $pdo, int $orderId): bool
    {
        $stmt = $pdo->prepare('SELECT new_status FROM order_status_history WHERE order_id = :order_id ORDER BY created_at ASC, id ASC');
        $stmt->execute(['order_id' => $orderId]);
        $history = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $seenDelivered = false;
        foreach ($history as $status) {
            $status = (string)$status;
            if ($status === 'delivered') {
                $seenDelivered = true;
            }
            if ($seenDelivered && $status === 'cancelled') {
                return true;
            }
        }
        return false;
    }

    private function hasDuplicateTerminalClosures(PDO $pdo, int $orderId): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_status_history WHERE order_id = :order_id AND new_status IN ("cancelled", "partially_refunded", "fully_refunded", "refunded")');
        $stmt->execute(['order_id' => $orderId]);
        $count = (int)$stmt->fetchColumn();
        return $count > 1;
    }

    /**
     * @param array<string,mixed> $order
     * @param array<int,string> $issues
     */
    private function applyRecommendation(PDO $pdo, array $order, string $recommendation, array $issues): bool
    {
        $orderId = (int)$order['id'];
        $adminId = 0;
        $grandTotal = (float)$order['grand_total'];

        try {
            $pdo->beginTransaction();

            if ($recommendation === 'convert_to_fully_refunded') {
                $stmt = $pdo->prepare('UPDATE orders SET order_status = "fully_refunded", payment_status = "refunded", refund_status = "processed", total_refunded = GREATEST(COALESCE(total_refunded,0), :grand_total), refunded_at = COALESCE(refunded_at, NOW()), updated_at = NOW() WHERE id = :id');
                $stmt->execute(['id' => $orderId, 'grand_total' => $grandTotal]);

                $hist = $pdo->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_admin_id, reason, metadata) VALUES (:order_id, :previous_status, "fully_refunded", NULL, :reason, :metadata)');
                $hist->execute([
                    'order_id' => $orderId,
                    'previous_status' => (string)$order['order_status'],
                    'reason' => 'RepairOrderStateCommand: converted paid/cancelled inconsistency to fully_refunded',
                    'metadata' => json_encode(['issues' => $issues], JSON_UNESCAPED_UNICODE),
                ]);
            } elseif ($recommendation === 'rebuild_refund_totals') {
                $stmt = $pdo->prepare('UPDATE orders SET total_refunded = :grand_total, refund_status = "processed", payment_status = "refunded", updated_at = NOW() WHERE id = :id');
                $stmt->execute(['id' => $orderId, 'grand_total' => $grandTotal]);
            } else {
                $note = $pdo->prepare('UPDATE orders SET admin_note = CONCAT(IFNULL(admin_note, ""), :note), updated_at = NOW() WHERE id = :id');
                $note->execute([
                    'id' => $orderId,
                    'note' => "\n[RECON EXCEPTION] Duplicate terminal closure detected; manual finance review required.",
                ]);
            }

            $audit = $pdo->prepare('INSERT INTO order_audit_logs (order_id, action_type, previous_status, new_status, payment_status, admin_id, admin_role, message, metadata, created_at) VALUES (:order_id, "reconciliation_fix", :previous_status, :new_status, :payment_status, :admin_id, "system", :message, :metadata, NOW())');
            $audit->execute([
                'order_id' => $orderId,
                'previous_status' => (string)$order['order_status'],
                'new_status' => $recommendation === 'convert_to_fully_refunded' ? 'fully_refunded' : (string)$order['order_status'],
                'payment_status' => $recommendation === 'convert_to_fully_refunded' || $recommendation === 'rebuild_refund_totals' ? 'refunded' : (string)$order['payment_status'],
                'admin_id' => $adminId > 0 ? $adminId : null,
                'message' => 'Automated reconciliation repair applied',
                'metadata' => json_encode(['issues' => $issues, 'recommendation' => $recommendation], JSON_UNESCAPED_UNICODE),
            ]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDERR, '[repair] failed order #' . $orderId . ': ' . $e->getMessage() . PHP_EOL);
            return false;
        }
    }

    /**
     * @param array<string,mixed> $order
     * @param array<int,string> $issues
     */
    private function printIssue(array $order, array $issues, string $recommendation, bool $dryRun): void
    {
        $mode = $dryRun ? 'DRY-RUN' : 'APPLY';
        echo '[' . $mode . '] Order #' . (int)$order['id'] . ' (' . (string)$order['order_number'] . ')';
        echo ' status=' . (string)$order['order_status'] . ' payment=' . (string)$order['payment_status'];
        echo ' issues=' . implode(',', $issues);
        echo ' recommendation=' . $recommendation . PHP_EOL;
    }

    /**
     * @param array<string,mixed> $report
     */
    private function printSummary(array $report, bool $dryRun): void
    {
        echo PHP_EOL . '==== REPAIR ORDER STATE SUMMARY ====' . PHP_EOL;
        echo 'mode: ' . ($dryRun ? 'dry-run' : 'apply') . PHP_EOL;
        echo 'checked: ' . (int)$report['checked'] . PHP_EOL;
        echo 'issues: ' . (int)$report['issues'] . PHP_EOL;
        echo 'fixed: ' . (int)$report['fixed'] . PHP_EOL;
        echo 'exceptions: ' . (int)$report['exceptions'] . PHP_EOL;
        echo 'category.paid_cancelled: ' . (int)$report['categories']['paid_cancelled'] . PHP_EOL;
        echo 'category.delivered_then_cancelled: ' . (int)$report['categories']['delivered_then_cancelled'] . PHP_EOL;
        echo 'category.refund_revenue_mismatch: ' . (int)$report['categories']['refund_revenue_mismatch'] . PHP_EOL;
        echo 'category.invalid_combo: ' . (int)$report['categories']['invalid_combo'] . PHP_EOL;
        echo 'category.duplicate_closure: ' . (int)$report['categories']['duplicate_closure'] . PHP_EOL;
        echo 'result: ' . (((int)$report['issues'] === 0 || ((int)$report['issues'] === (int)$report['fixed'] && !$dryRun)) ? 'PASS' : 'FAIL') . PHP_EOL;
    }

    private function extractLimit(array $argv): int
    {
        foreach ($argv as $arg) {
            if (str_starts_with((string)$arg, '--limit=')) {
                $value = (int)substr((string)$arg, 8);
                if ($value > 0) {
                    return min($value, 50000);
                }
            }
        }
        return 5000;
    }
}
