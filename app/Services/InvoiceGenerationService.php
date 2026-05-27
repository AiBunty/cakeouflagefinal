<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class InvoiceGenerationService
{
    /**
     * @param array<string,mixed> $options
     * @return array{success:bool,message:string,created?:bool,invoice_id?:int,invoice_number?:string}
     */
    public function ensureInvoiceForOrder(PDO $pdo, int $orderId, array $options = []): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order id'];
        }

        try {
            $existingStmt = $pdo->prepare('SELECT id, invoice_number FROM invoices WHERE order_id = :order_id LIMIT 1');
            $existingStmt->execute(['order_id' => $orderId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return [
                    'success' => true,
                    'message' => 'Invoice already exists',
                    'created' => false,
                    'invoice_id' => (int)$existing['id'],
                    'invoice_number' => (string)$existing['invoice_number'],
                ];
            }

            $orderStmt = $pdo->prepare(
                'SELECT id, user_id, grand_total, subtotal, discount_total, tax_total, payment_status, payment_method
                 FROM orders WHERE id = :id LIMIT 1'
            );
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return ['success' => false, 'message' => 'Order not found for invoice generation'];
            }

            $subtotal = round((float)($order['subtotal'] ?? 0), 2);
            $discountTotal = round((float)($order['discount_total'] ?? 0), 2);
            $taxTotal = round((float)($order['tax_total'] ?? 0), 2);
            $grandTotal = round((float)($order['grand_total'] ?? 0), 2);
            $paymentStatus = strtolower(trim((string)($options['payment_status'] ?? $order['payment_status'] ?? 'pending')));
            $paymentMethod = strtolower(trim((string)($options['payment_method'] ?? $order['payment_method'] ?? 'upi_manual')));

            if ($subtotal <= 0 && $grandTotal > 0) {
                $subtotal = $grandTotal + $discountTotal - $taxTotal;
            }
            if ($subtotal < 0) {
                $subtotal = $grandTotal;
            }

            $invoiceStatus = $paymentStatus === 'paid' ? 'paid' : 'pending_payment';
            $paidAmount = $invoiceStatus === 'paid' ? $grandTotal : 0.0;
            $balanceDue = max(0.0, round($grandTotal - $paidAmount, 2));

            $invoiceNumber = $this->generateInvoiceNumber();
            $invoicePaymentMethod = $this->mapInvoicePaymentMethod($paymentMethod);

            $insertInvoice = $pdo->prepare(
                'INSERT INTO invoices (
                    invoice_number, order_id, user_id, customer_type, invoice_status,
                    payment_method, subtotal, discount_total, tax_total, grand_total,
                    paid_amount, balance_due, due_on, issued_on, internal_note
                ) VALUES (
                    :invoice_number, :order_id, :user_id, "retail", :invoice_status,
                    :payment_method, :subtotal, :discount_total, :tax_total, :grand_total,
                    :paid_amount, :balance_due, CURDATE(), CURDATE(), :internal_note
                )'
            );
            $insertInvoice->execute([
                'invoice_number' => $invoiceNumber,
                'order_id' => $orderId,
                'user_id' => (int)($order['user_id'] ?? 0) ?: null,
                'invoice_status' => $invoiceStatus,
                'payment_method' => $invoicePaymentMethod,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'internal_note' => 'Auto-generated at payment confirmation',
            ]);

            $invoiceId = (int)$pdo->lastInsertId();

            $itemsStmt = $pdo->prepare(
                'SELECT product_name_snapshot, quantity, unit_price, line_total
                 FROM order_items WHERE order_id = :order_id ORDER BY id ASC'
            );
            $itemsStmt->execute(['order_id' => $orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insertItem = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, item_label, quantity, unit_price, line_total)
                 VALUES (:invoice_id, :item_label, :quantity, :unit_price, :line_total)'
            );

            if (count($items) === 0) {
                $insertItem->execute([
                    'invoice_id' => $invoiceId,
                    'item_label' => 'Order #' . $orderId,
                    'quantity' => 1,
                    'unit_price' => $grandTotal,
                    'line_total' => $grandTotal,
                ]);
            } else {
                foreach ($items as $item) {
                    $qty = max(1, (int)($item['quantity'] ?? 1));
                    $unit = round((float)($item['unit_price'] ?? 0), 2);
                    $line = round((float)($item['line_total'] ?? ($unit * $qty)), 2);
                    if ($line <= 0 && $unit > 0) {
                        $line = round($unit * $qty, 2);
                    }

                    $insertItem->execute([
                        'invoice_id' => $invoiceId,
                        'item_label' => trim((string)($item['product_name_snapshot'] ?? 'Cake item')),
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $line,
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Invoice generated',
                'created' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Invoice generation failed: ' . $e->getMessage()];
        }
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function mapInvoicePaymentMethod(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'gateway' => 'payment_link',
            'cash' => 'cash',
            'pos_card' => 'pos_card',
            'bank_transfer' => 'bank_transfer',
            default => 'upi',
        };
    }
}
