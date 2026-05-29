<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ExpenseService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array{expense_date:string,category_code:string,amount:float,payment_mode?:string,vendor_id?:int,note?:string,admin_id?:int} $payload
     * @return array{success:bool,message:string,expense_id?:int,expense_number?:string}
     */
    public function recordExpense(array $payload): array
    {
        $amount = round((float)($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Expense amount must be greater than zero'];
        }

        $expenseNumber = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        try {
            $expenseId = $this->db->insert(
                'INSERT INTO expenses
                    (expense_number, expense_date, category_code, amount, payment_mode, vendor_id, note, created_by_admin_id, created_at, updated_at)
                 VALUES
                    (:expense_number, :expense_date, :category_code, :amount, :payment_mode, :vendor_id, :note, :admin_id, NOW(), NOW())',
                [
                    'expense_number' => $expenseNumber,
                    'expense_date' => (string)($payload['expense_date'] ?? date('Y-m-d')),
                    'category_code' => strtoupper(trim((string)($payload['category_code'] ?? 'OPERATING'))),
                    'amount' => $amount,
                    'payment_mode' => (string)($payload['payment_mode'] ?? 'cash'),
                    'vendor_id' => isset($payload['vendor_id']) ? (int)$payload['vendor_id'] : null,
                    'note' => (string)($payload['note'] ?? ''),
                    'admin_id' => isset($payload['admin_id']) ? (int)$payload['admin_id'] : null,
                ]
            );

            return [
                'success' => true,
                'message' => 'Expense recorded',
                'expense_id' => $expenseId,
                'expense_number' => $expenseNumber,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Expense recording failed: ' . $e->getMessage()];
        }
    }
}
