<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * DailyCloseService
 *
 * Soft-locks an accounting day by writing to accounting_close_log.
 * A locked day blocks FinancialTransactionEngine from posting to it.
 * Super-admin can reopen a closed day with a full audit trail.
 */
final class DailyCloseService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Close (lock) an accounting day.
     *
     * Validates that the GL is balanced for the day, computes summary totals,
     * then inserts a locked row in accounting_close_log.
     *
     * @param  string $businessDate  'Y-m-d'
     * @param  int    $adminId
     * @return array{success:bool,message:string,summary?:array<string,float>}
     */
    public function close(string $businessDate, int $adminId): array
    {
        if (!$this->isValidDate($businessDate)) {
            return ['success' => false, 'message' => 'Invalid business date: ' . $businessDate];
        }

        // Check not already closed
        $existing = $this->db->fetchOne(
            'SELECT id, is_locked FROM accounting_close_log WHERE close_date = :d LIMIT 1',
            ['d' => $businessDate]
        );
        if ($existing !== null && (int)$existing['is_locked'] === 1) {
            return ['success' => false, 'message' => 'Day ' . $businessDate . ' is already locked'];
        }

        // Validate GL balance: total debits must equal total credits for the day
        $balanceRow = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(gle.debit_amount),  0) AS total_debit,
                COALESCE(SUM(gle.credit_amount), 0) AS total_credit
             FROM general_ledger_entries gle
             INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
             WHERE ft.business_date = :d",
            ['d' => $businessDate]
        ) ?: [];

        $totalDebit  = round((float)($balanceRow['total_debit']  ?? 0), 2);
        $totalCredit = round((float)($balanceRow['total_credit'] ?? 0), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return [
                'success' => false,
                'message' => sprintf(
                    'GL is unbalanced for %s: debits=%.2f credits=%.2f (diff=%.2f)',
                    $businessDate, $totalDebit, $totalCredit, $totalDebit - $totalCredit
                ),
            ];
        }

        // Compute summary totals for the close record
        $summaryRow = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN gle.account_code = 'CASH_ON_HAND'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0) AS cash_total,
                COALESCE(SUM(CASE WHEN gle.account_code = 'BANK_CLEARING'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0) AS bank_total,
                COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_REVENUE'
                                  THEN gle.credit_amount - gle.debit_amount ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_ADJUSTMENT_REVENUE'
                                  THEN gle.credit_amount - gle.debit_amount ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_REFUNDS'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_ADJUSTMENT_EXPENSE'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0) AS net_revenue,
                COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_REFUNDS'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0) AS refunds_total,
                COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_DISCOUNT_CONTRA'
                                  THEN gle.credit_amount - gle.debit_amount ELSE 0 END), 0) AS discounts_total,
                COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_ADJUSTMENT_REVENUE'
                                  THEN gle.credit_amount - gle.debit_amount ELSE 0 END), 0) AS upgrade_revenue,
                COALESCE(SUM(CASE WHEN gle.account_code = 'SALES_ADJUSTMENT_EXPENSE'
                                  THEN gle.debit_amount - gle.credit_amount ELSE 0 END), 0) AS downgrade_expense
             FROM general_ledger_entries gle
             INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
             WHERE ft.business_date = :d",
            ['d' => $businessDate]
        ) ?: [];

        $summary = [
            'cash_total'       => round((float)($summaryRow['cash_total']       ?? 0), 2),
            'bank_total'       => round((float)($summaryRow['bank_total']       ?? 0), 2),
            'net_revenue'      => round((float)($summaryRow['net_revenue']      ?? 0), 2),
            'refunds_total'    => round((float)($summaryRow['refunds_total']    ?? 0), 2),
            'discounts_total'  => round((float)($summaryRow['discounts_total']  ?? 0), 2),
            'upgrade_revenue'  => round((float)($summaryRow['upgrade_revenue']  ?? 0), 2),
            'downgrade_expense'=> round((float)($summaryRow['downgrade_expense']?? 0), 2),
        ];

        if ($existing !== null) {
            // Re-lock a previously reopened entry
            $this->db->execute(
                'UPDATE accounting_close_log
                    SET is_locked = 1, closed_by_admin_id = :admin, closed_at = NOW(),
                        cash_total = :cash, bank_total = :bank, net_revenue = :rev,
                        refunds_total = :refunds, discounts_total = :disc,
                        upgrade_revenue = :upgr, downgrade_expense = :downgr
                  WHERE close_date = :d',
                array_merge(['admin' => $adminId, 'd' => $businessDate], $summary)
            );
        } else {
            $this->db->insert(
                'INSERT INTO accounting_close_log
                    (close_date, closed_by_admin_id, cash_total, bank_total, net_revenue,
                     refunds_total, discounts_total, upgrade_revenue, downgrade_expense,
                     is_locked, closed_at)
                 VALUES
                    (:d, :admin, :cash_total, :bank_total, :net_revenue,
                     :refunds_total, :discounts_total, :upgrade_revenue, :downgrade_expense,
                     1, NOW())',
                array_merge(['d' => $businessDate, 'admin' => $adminId], $summary)
            );
        }

        return [
            'success' => true,
            'message' => 'Day ' . $businessDate . ' has been locked',
            'summary' => $summary,
        ];
    }

    /**
     * Reopen (unlock) a previously closed day.
     * The calling controller must verify the admin has super-admin role.
     *
     * @param  string $businessDate  'Y-m-d'
     * @param  int    $adminId
     * @param  string $reason        Mandatory audit reason
     * @return array{success:bool,message:string}
     */
    public function reopen(string $businessDate, int $adminId, string $reason = ''): array
    {
        if (!$this->isValidDate($businessDate)) {
            return ['success' => false, 'message' => 'Invalid business date: ' . $businessDate];
        }

        $existing = $this->db->fetchOne(
            'SELECT id, is_locked FROM accounting_close_log WHERE close_date = :d LIMIT 1',
            ['d' => $businessDate]
        );

        if ($existing === null) {
            return ['success' => false, 'message' => 'No close record found for ' . $businessDate];
        }
        if ((int)$existing['is_locked'] === 0) {
            return ['success' => false, 'message' => 'Day ' . $businessDate . ' is not locked'];
        }

        $this->db->execute(
            'UPDATE accounting_close_log
                SET is_locked             = 0,
                    last_reopened_at      = NOW(),
                    reopened_by_admin_id  = :admin,
                    notes                 = CONCAT(COALESCE(notes, \'\'), :note)
              WHERE close_date = :d',
            [
                'admin' => $adminId,
                'd'     => $businessDate,
                'note'  => ($reason !== '' ? (' | Reopened ' . date('Y-m-d H:i') . ': ' . substr($reason, 0, 200)) : ''),
            ]
        );

        return ['success' => true, 'message' => 'Day ' . $businessDate . ' has been reopened'];
    }

    /**
     * @return list<array<string,mixed>>  Past close records, newest first
     */
    public function getCloseLog(int $limit = 90): array
    {
        return $this->db->fetchAll(
            'SELECT acl.*, a.username AS closed_by_name
               FROM accounting_close_log acl
               LEFT JOIN admins a ON a.id = acl.closed_by_admin_id
              ORDER BY acl.close_date DESC
              LIMIT :lim',
            ['lim' => $limit]
        ) ?: [];
    }

    private function isValidDate(string $date): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
