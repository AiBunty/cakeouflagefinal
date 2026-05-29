<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class IngredientService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listActive(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ingredients WHERE is_active = 1 ORDER BY ingredient_name ASC'
        );
    }

    /**
     * @param array{ingredient_code:string,ingredient_name:string,unit?:string,reorder_level?:float} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO ingredients (ingredient_code, ingredient_name, unit, reorder_level, created_at, updated_at)
             VALUES (:code, :name, :unit, :reorder_level, NOW(), NOW())',
            [
                'code' => strtoupper(trim((string)$data['ingredient_code'])),
                'name' => trim((string)$data['ingredient_name']),
                'unit' => trim((string)($data['unit'] ?? 'kg')),
                'reorder_level' => round((float)($data['reorder_level'] ?? 0), 3),
            ]
        );
    }

    public function adjustStock(int $ingredientId, float $quantityChange, float $unitCost, string $entryType, string $note = '', ?int $adminId = null): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->insert(
                'INSERT INTO stock_ledger
                    (ingredient_id, entry_type, quantity_change, unit_cost, note, created_by_admin_id, created_at)
                 VALUES
                    (:ingredient_id, :entry_type, :quantity_change, :unit_cost, :note, :admin_id, NOW())',
                [
                    'ingredient_id' => $ingredientId,
                    'entry_type' => $entryType,
                    'quantity_change' => round($quantityChange, 3),
                    'unit_cost' => round($unitCost, 2),
                    'note' => $note !== '' ? $note : null,
                    'admin_id' => $adminId,
                ]
            );

            $this->db->execute(
                'UPDATE ingredients
                 SET current_stock = GREATEST(current_stock + :quantity_change, 0),
                     average_unit_cost = CASE WHEN :unit_cost > 0 THEN :unit_cost ELSE average_unit_cost END,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'quantity_change' => round($quantityChange, 3),
                    'unit_cost' => round($unitCost, 2),
                    'id' => $ingredientId,
                ]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
