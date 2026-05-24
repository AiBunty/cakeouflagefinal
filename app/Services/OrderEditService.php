<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class OrderEditService
{
    /** @var array<string, array<string, bool>> */
    private static array $columnCache = [];
    private static ?bool $manualSourceSupported = null;

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array{success: bool, message: string, updated_totals?: array<string,float>}
     */
    public function apply(PDO $pdo, int $orderId, array $payload, int $adminId, array $context = []): array
    {
        if ($orderId <= 0) {
            return ['success' => false, 'message' => 'Invalid order id'];
        }

        $reason = trim((string)($payload['edit_reason'] ?? ''));
        if ($reason === '') {
            return ['success' => false, 'message' => 'Edit reason is required'];
        }

        $stateManager = new OrderStateManager();
        $adminRole = (string)($context['admin_role'] ?? '');
        $adminPermissions = (array)($context['admin_permissions'] ?? []);
        $ipAddress = (string)($context['ip_address'] ?? '');

        $pdo->beginTransaction();
        try {
            $orderStmt = $pdo->prepare(
                'SELECT id, order_number, order_source, byoc_quote_id, order_status, payment_status,
                        payment_method, production_status, customer_phone, customer_phone_e164,
                        scheduled_slot_label, subtotal, discount_total, tax_total, delivery_fee,
                        grand_total, admin_note
                 FROM orders WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $orderStmt->execute(['id' => $orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if ($order === false) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Order not found'];
            }

            $orderStatus = (string)($order['order_status'] ?? '');
            $paymentStatus = (string)($order['payment_status'] ?? 'pending');
            $orderSource = (string)($order['order_source'] ?? 'retail');
            $productionStatus = (string)($order['production_status'] ?? 'pending');

            if (in_array($orderStatus, ['partially_refunded', 'fully_refunded', 'refunded'], true)
                || in_array($paymentStatus, ['partially_refunded', 'refunded'], true)
            ) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Refunded orders are fully locked and cannot be edited'];
            }

            $isManual = $this->isManualEditCapableSource($pdo, $orderSource);
            $isByoc = $orderSource === 'byoc_quote' || (int)($order['byoc_quote_id'] ?? 0) > 0;
            $itemsEditableLifecycle = in_array($paymentStatus, ['pending', 'under_review', 'failed', 'rejected'], true)
                && in_array($productionStatus, ['not_required', 'pending'], true);

            $customerPhone = $this->sanitizePhone((string)($payload['customer_phone'] ?? (string)($order['customer_phone'] ?? '')));
            $adminNote = $this->truncate((string)($payload['admin_note'] ?? (string)($order['admin_note'] ?? '')), 2000);
            $scheduledSlotLabel = $this->truncate((string)($payload['scheduled_slot_label'] ?? (string)($order['scheduled_slot_label'] ?? '')), 100);

            $beforeSnapshot = [
                'customer_phone' => (string)($order['customer_phone'] ?? ''),
                'admin_note' => (string)($order['admin_note'] ?? ''),
                'scheduled_slot_label' => (string)($order['scheduled_slot_label'] ?? ''),
                'subtotal' => (float)($order['subtotal'] ?? 0),
                'discount_total' => (float)($order['discount_total'] ?? 0),
                'tax_total' => (float)($order['tax_total'] ?? 0),
                'delivery_fee' => (float)($order['delivery_fee'] ?? 0),
                'grand_total' => (float)($order['grand_total'] ?? 0),
            ];

            $this->updateOrderBasics($pdo, $orderId, $customerPhone, $adminNote, $scheduledSlotLabel);

            $updatedTotals = [
                'subtotal' => (float)($order['subtotal'] ?? 0),
                'discount_total' => (float)($order['discount_total'] ?? 0),
                'tax_total' => (float)($order['tax_total'] ?? 0),
                'delivery_fee' => (float)($order['delivery_fee'] ?? 0),
                'grand_total' => (float)($order['grand_total'] ?? 0),
            ];

            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $itemsNew = is_array($payload['items_new'] ?? null) ? $payload['items_new'] : [];
            $deleteItemIds = is_array($payload['delete_item_ids'] ?? null) ? $payload['delete_item_ids'] : [];

            $hasItemMutation = !empty($items) || !empty($itemsNew) || !empty($deleteItemIds);
            if ($hasItemMutation) {
                if (!($isManual || $isByoc)) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Item edits are supported only for manual and BYOC orders'];
                }

                if (!$itemsEditableLifecycle) {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => 'Item edits are allowed only before production and before payment finalization'];
                }

                $this->applyItemMutations($pdo, $orderId, $items, $itemsNew, $deleteItemIds, $isManual, $isByoc);

                $updatedTotals = $this->recalculateOrderTotals($pdo, $orderId, [
                    'discount_override' => $payload['discount_override'] ?? null,
                    'delivery_fee_override' => $payload['delivery_fee_override'] ?? null,
                ]);
            }

            $afterSnapshot = [
                'customer_phone' => $customerPhone,
                'admin_note' => $adminNote,
                'scheduled_slot_label' => $scheduledSlotLabel,
                'subtotal' => $updatedTotals['subtotal'],
                'discount_total' => $updatedTotals['discount_total'],
                'tax_total' => $updatedTotals['tax_total'],
                'delivery_fee' => $updatedTotals['delivery_fee'],
                'grand_total' => $updatedTotals['grand_total'],
            ];

            $stateManager->writeOrderAudit($pdo, [
                'order_id' => $orderId,
                'action_type' => 'order_edit',
                'previous_status' => $orderStatus,
                'new_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'admin_id' => $adminId,
                'admin_role' => $adminRole,
                'ip_address' => $ipAddress,
                'message' => 'Order edited via governed order edit workflow',
                'metadata' => [
                    'reason' => $reason,
                    'before' => $beforeSnapshot,
                    'after' => $afterSnapshot,
                    'items_mutated' => $hasItemMutation,
                    'order_source' => $orderSource,
                    'permissions' => $adminPermissions,
                ],
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => 'Order edited successfully',
                'updated_totals' => $updatedTotals,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[OrderEditService] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Order edit failed: ' . $e->getMessage()];
        }
    }

    private function updateOrderBasics(PDO $pdo, int $orderId, string $phone, string $note, string $slotLabel): void
    {
        $digits = preg_replace('/\D+/', '', $phone);
        $phoneE164 = null;
        if ($digits !== null && strlen($digits) >= 10) {
            $last10 = substr($digits, -10);
            $phoneE164 = '+91' . $last10;
        }

        $stmt = $pdo->prepare(
            'UPDATE orders
             SET customer_phone = :phone,
                 customer_phone_e164 = :phone_e164,
                 admin_note = :note,
                 scheduled_slot_label = :slot_label,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'phone' => $phone,
            'phone_e164' => $phoneE164,
            'note' => $note,
            'slot_label' => $slotLabel,
            'id' => $orderId,
        ]);
    }

    /**
     * @param array<mixed> $items
     * @param array<mixed> $itemsNew
     * @param array<mixed> $deleteItemIds
     */
    private function applyItemMutations(PDO $pdo, int $orderId, array $items, array $itemsNew, array $deleteItemIds, bool $isManual, bool $isByoc): void
    {
        $existing = $this->fetchOrderItems($pdo, $orderId);
        $existingById = [];
        foreach ($existing as $row) {
            $existingById[(int)$row['id']] = $row;
        }

        $deleteSet = [];
        foreach ($deleteItemIds as $id) {
            $iid = (int)$id;
            if ($iid > 0) {
                $deleteSet[$iid] = true;
            }
        }

        if (!empty($deleteSet) && !$isManual) {
            throw new \RuntimeException('Item deletion is allowed only for manual orders');
        }

        foreach ($items as $itemRaw) {
            if (!is_array($itemRaw)) {
                continue;
            }
            $itemId = (int)($itemRaw['item_id'] ?? 0);
            if ($itemId <= 0 || !isset($existingById[$itemId])) {
                continue;
            }

            if (isset($deleteSet[$itemId])) {
                continue;
            }

            $current = $existingById[$itemId];

            if ($isByoc) {
                $this->updateByocItem($pdo, $itemId, $itemRaw, $current);
                continue;
            }

            $this->updateManualItem($pdo, $itemId, $itemRaw, $current);
        }

        if (!empty($deleteSet)) {
            $currentCount = count($existingById);
            if ($currentCount - count(array_intersect_key($existingById, $deleteSet)) < 1) {
                throw new \RuntimeException('At least one item must remain on an order');
            }

            $in = implode(',', array_map('intval', array_keys($deleteSet)));
            if ($in !== '') {
                $pdo->exec('DELETE FROM order_items WHERE order_id = ' . (int)$orderId . ' AND id IN (' . $in . ')');
            }
        }

        if ($isManual && !empty($itemsNew)) {
            $fallbackProductId = $this->fallbackProductId($pdo);
            foreach ($itemsNew as $newItemRaw) {
                if (!is_array($newItemRaw)) {
                    continue;
                }
                $name = $this->truncate((string)($newItemRaw['name'] ?? ''), 180);
                if ($name === '') {
                    continue;
                }
                $qty = max(1, (int)($newItemRaw['quantity'] ?? 1));
                $unit = max(0.0, (float)($newItemRaw['unit_price'] ?? 0));
                $line = round($qty * $unit, 2);
                $cakeMessage = $this->truncate((string)($newItemRaw['cake_message'] ?? ''), 280);

                $hasCakeMessage = $this->hasColumn($pdo, 'order_items', 'cake_message');
                if ($hasCakeMessage) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO order_items
                            (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note, cake_message)
                         VALUES
                            (:order_id, :product_id, NULL, :name, NULL, :unit_price, :qty, :line_total, NULL, :cake_message)'
                    );
                    $stmt->execute([
                        'order_id' => $orderId,
                        'product_id' => $fallbackProductId,
                        'name' => $name,
                        'unit_price' => $unit,
                        'qty' => $qty,
                        'line_total' => $line,
                        'cake_message' => $cakeMessage !== '' ? $cakeMessage : null,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO order_items
                            (order_id, product_id, variant_id, product_name_snapshot, variant_snapshot, unit_price, quantity, line_total, customisation_note)
                         VALUES
                            (:order_id, :product_id, NULL, :name, NULL, :unit_price, :qty, :line_total, NULL)'
                    );
                    $stmt->execute([
                        'order_id' => $orderId,
                        'product_id' => $fallbackProductId,
                        'name' => $name,
                        'unit_price' => $unit,
                        'qty' => $qty,
                        'line_total' => $line,
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $itemRaw
     * @param array<string,mixed> $current
     */
    private function updateManualItem(PDO $pdo, int $itemId, array $itemRaw, array $current): void
    {
        $name = $this->truncate((string)($itemRaw['name'] ?? (string)($current['product_name_snapshot'] ?? '')), 180);
        $qty = max(1, (int)($itemRaw['quantity'] ?? (int)($current['quantity'] ?? 1)));
        $unit = max(0.0, (float)($itemRaw['unit_price'] ?? (float)($current['unit_price'] ?? 0)));
        $line = round($qty * $unit, 2);
        $cakeMessage = $this->truncate((string)($itemRaw['cake_message'] ?? (string)($current['cake_message'] ?? '')), 280);

        $set = [
            'product_name_snapshot = :name',
            'quantity = :qty',
            'unit_price = :unit_price',
            'line_total = :line_total',
        ];
        $params = [
            'id' => $itemId,
            'name' => $name,
            'qty' => $qty,
            'unit_price' => $unit,
            'line_total' => $line,
        ];

        if ($this->hasColumn($pdo, 'order_items', 'cake_message')) {
            $set[] = 'cake_message = :cake_message';
            $params['cake_message'] = $cakeMessage !== '' ? $cakeMessage : null;
        }

        $sql = 'UPDATE order_items SET ' . implode(', ', $set) . ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array<string,mixed> $itemRaw
     * @param array<string,mixed> $current
     */
    private function updateByocItem(PDO $pdo, int $itemId, array $itemRaw, array $current): void
    {
        $set = [];
        $params = ['id' => $itemId];

        if ($this->hasColumn($pdo, 'order_items', 'cake_message')) {
            $cakeMessage = $this->truncate((string)($itemRaw['cake_message'] ?? (string)($current['cake_message'] ?? '')), 280);
            $set[] = 'cake_message = :cake_message';
            $params['cake_message'] = $cakeMessage !== '' ? $cakeMessage : null;
        }

        if (empty($set)) {
            return;
        }

        $sql = 'UPDATE order_items SET ' . implode(', ', $set) . ' WHERE id = :id LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,float>
     */
    private function recalculateOrderTotals(PDO $pdo, int $orderId, array $overrides): array
    {
        $totalsStmt = $pdo->prepare('SELECT COALESCE(SUM(line_total), 0) AS subtotal FROM order_items WHERE order_id = :order_id');
        $totalsStmt->execute(['order_id' => $orderId]);
        $subtotal = (float)($totalsStmt->fetchColumn() ?: 0.0);

        $orderStmt = $pdo->prepare('SELECT discount_total, tax_total, delivery_fee FROM orders WHERE id = :id LIMIT 1');
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: ['discount_total' => 0, 'tax_total' => 0, 'delivery_fee' => 0];

        $taxTotal = (float)($order['tax_total'] ?? 0);
        $deliveryFee = array_key_exists('delivery_fee_override', $overrides) && $overrides['delivery_fee_override'] !== null && $overrides['delivery_fee_override'] !== ''
            ? max(0.0, (float)$overrides['delivery_fee_override'])
            : (float)($order['delivery_fee'] ?? 0);

        $couponDiscount = $this->recalculateCouponDiscount($pdo, $orderId, $subtotal);
        if ($couponDiscount !== null) {
            $discountTotal = $couponDiscount;
        } else {
            $discountTotal = array_key_exists('discount_override', $overrides) && $overrides['discount_override'] !== null && $overrides['discount_override'] !== ''
                ? max(0.0, min($subtotal, (float)$overrides['discount_override']))
                : (float)($order['discount_total'] ?? 0);
        }

        $grandTotal = max(0.0, round($subtotal - $discountTotal + $taxTotal + $deliveryFee, 2));

        $update = $pdo->prepare(
            'UPDATE orders
             SET subtotal = :subtotal,
                 discount_total = :discount_total,
                 tax_total = :tax_total,
                 delivery_fee = :delivery_fee,
                 grand_total = :grand_total,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'id' => $orderId,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'grand_total' => $grandTotal,
        ]);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'grand_total' => $grandTotal,
        ];
    }

    private function recalculateCouponDiscount(PDO $pdo, int $orderId, float $subtotal): ?float
    {
        $stmt = $pdo->prepare(
            'SELECT cr.id AS redemption_id, cr.coupon_id,
                    c.discount_type, c.discount_value, c.max_discount, c.min_order_amount
             FROM coupon_redemptions cr
             JOIN coupons c ON c.id = cr.coupon_id
             WHERE cr.order_id = :order_id
             LIMIT 1'
        );
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $minOrder = (float)($row['min_order_amount'] ?? 0);
        $discount = 0.0;
        if ($subtotal >= $minOrder) {
            $type = (string)($row['discount_type'] ?? 'flat');
            $value = (float)($row['discount_value'] ?? 0);
            if ($type === 'percentage') {
                $discount = ($subtotal * $value) / 100;
                $maxDiscount = (float)($row['max_discount'] ?? 0);
                if ($maxDiscount > 0) {
                    $discount = min($discount, $maxDiscount);
                }
            } else {
                $discount = $value;
            }
        }
        $discount = round(max(0.0, min($subtotal, $discount)), 2);

        $updateRedemption = $pdo->prepare('UPDATE coupon_redemptions SET discount_total = :discount_total WHERE id = :id');
        $updateRedemption->execute([
            'discount_total' => $discount,
            'id' => (int)$row['redemption_id'],
        ]);

        return $discount;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchOrderItems(PDO $pdo, int $orderId): array
    {
        $cols = ['id', 'order_id', 'product_id', 'variant_id', 'product_name_snapshot', 'variant_snapshot', 'unit_price', 'quantity', 'line_total'];
        if ($this->hasColumn($pdo, 'order_items', 'cake_message')) {
            $cols[] = 'cake_message';
        }
        if ($this->hasColumn($pdo, 'order_items', 'topper_id')) {
            $cols[] = 'topper_id';
        }
        if ($this->hasColumn($pdo, 'order_items', 'topper_name_snapshot')) {
            $cols[] = 'topper_name_snapshot';
        }
        if ($this->hasColumn($pdo, 'order_items', 'topper_price_snapshot')) {
            $cols[] = 'topper_price_snapshot';
        }

        $sql = 'SELECT ' . implode(', ', $cols) . ' FROM order_items WHERE order_id = :order_id ORDER BY id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fallbackProductId(PDO $pdo): int
    {
        $id = (int)($pdo->query('SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new \RuntimeException('No fallback product exists for manual item insertion');
        }
        return $id;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        if (!isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = [];
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $field = strtolower((string)($row['Field'] ?? ''));
                if ($field !== '') {
                    self::$columnCache[$table][$field] = true;
                }
            }
        }
        return !empty(self::$columnCache[$table][strtolower($column)]);
    }

    private function isManualEditCapableSource(PDO $pdo, string $orderSource): bool
    {
        if ($orderSource === 'manual') {
            return true;
        }

        if ($orderSource === 'retail' && !$this->orderSourceSupportsManual($pdo)) {
            return true;
        }

        return false;
    }

    private function orderSourceSupportsManual(PDO $pdo): bool
    {
        if (self::$manualSourceSupported !== null) {
            return self::$manualSourceSupported;
        }

        try {
            $row = $pdo->query("SHOW COLUMNS FROM orders LIKE 'order_source'")->fetch(PDO::FETCH_ASSOC);
            $type = strtolower((string)($row['Type'] ?? ''));
            self::$manualSourceSupported = $type !== '' && strpos($type, "'manual'") !== false;
        } catch (\Throwable $e) {
            self::$manualSourceSupported = true;
        }

        return self::$manualSourceSupported;
    }

    private function sanitizePhone(string $value): string
    {
        $value = preg_replace('/[^0-9 +\-()]/', '', $value);
        return $this->truncate((string)$value, 20);
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }
        return substr($value, 0, $max);
    }
}
