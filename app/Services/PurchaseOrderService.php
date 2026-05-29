<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class PurchaseOrderService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param list<array{ingredient_id:int,quantity:float,unit_cost:float}> $items
     */
    public function createPurchaseOrder(int $vendorId, array $items, ?string $expectedDeliveryDate = null, ?int $adminId = null): array
    {
        if ($vendorId <= 0 || empty($items)) {
            return ['success' => false, 'message' => 'Vendor and at least one item are required'];
        }

        $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += round((float)$item['quantity'] * (float)$item['unit_cost'], 2);
        }

        $this->db->beginTransaction();
        try {
            $poId = $this->db->insert(
                'INSERT INTO purchase_orders
                    (po_number, vendor_id, po_date, expected_delivery_date, status, subtotal, tax_amount, total_amount, created_by_admin_id, created_at, updated_at)
                 VALUES
                    (:po_number, :vendor_id, CURDATE(), :expected_delivery_date, "draft", :subtotal, 0, :total_amount, :admin_id, NOW(), NOW())',
                [
                    'po_number' => $poNumber,
                    'vendor_id' => $vendorId,
                    'expected_delivery_date' => $expectedDeliveryDate,
                    'subtotal' => round($subtotal, 2),
                    'total_amount' => round($subtotal, 2),
                    'admin_id' => $adminId,
                ]
            );

            foreach ($items as $item) {
                $qty = round((float)$item['quantity'], 3);
                $cost = round((float)$item['unit_cost'], 2);
                $lineTotal = round($qty * $cost, 2);

                $this->db->insert(
                    'INSERT INTO purchase_order_items
                        (purchase_order_id, ingredient_id, quantity, unit_cost, line_total, created_at, updated_at)
                     VALUES
                        (:po_id, :ingredient_id, :quantity, :unit_cost, :line_total, NOW(), NOW())',
                    [
                        'po_id' => $poId,
                        'ingredient_id' => (int)$item['ingredient_id'],
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'line_total' => $lineTotal,
                    ]
                );
            }

            $this->db->commit();
            return ['success' => true, 'po_id' => $poId, 'po_number' => $poNumber];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'PO create failed: ' . $e->getMessage()];
        }
    }

    public function markIssued(int $poId): bool
    {
        return $this->db->execute(
            'UPDATE purchase_orders SET status = "issued", updated_at = NOW() WHERE id = :id AND status = "draft"',
            ['id' => $poId]
        ) > 0;
    }
}
