<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;
use App\Services\FinanceReportService;

$db = Database::getInstance();
$finance = new FinanceReportService($db);

$ids = [];
$latestUatFile = __DIR__ . '/phase1_uat/phase1_uat_results.json';
if (is_file($latestUatFile)) {
    $decoded = json_decode((string)file_get_contents($latestUatFile), true);
    if (is_array($decoded) && isset($decoded['dataset']) && is_array($decoded['dataset'])) {
        foreach ($decoded['dataset'] as $row) {
            if (is_array($row) && isset($row['id'])) {
                $ids[] = (int)$row['id'];
            }
        }
    }
}

if ($ids === []) {
    $fallback = $db->fetchAll("SELECT id FROM orders WHERE order_number LIKE 'UAT-%' ORDER BY id DESC LIMIT 50");
    foreach ($fallback as $row) {
        $ids[] = (int)($row['id'] ?? 0);
    }
}
$ids = array_values(array_filter(array_unique($ids), static fn(int $id): bool => $id > 0));

if ($ids === []) {
    $outPath = __DIR__ . '/financial_integrity_report.json';
    $empty = [
        'generated_at' => date('c'),
        'scope' => 'latest_uat_dataset',
        'overall_pass' => false,
        'error' => 'No UAT order IDs available for integrity check',
    ];
    file_put_contents($outPath, json_encode($empty, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo json_encode(['ok' => false, 'report' => 'storage/recovery/financial_integrity_report.json', 'overall_pass' => false], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$idsCsv = implode(',', array_map('intval', $ids));

$totalDebit = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(gle.debit_amount),0)
     FROM general_ledger_entries gle
     INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
     WHERE ft.reference_type = 'order' AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);
$totalCredit = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(gle.credit_amount),0)
     FROM general_ledger_entries gle
     INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
     WHERE ft.reference_type = 'order' AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$salesLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(credit_amount - debit_amount),0)
    FROM general_ledger_entries gle
    INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
     WHERE account_code IN ('SALES_REVENUE','SALES_ADJUSTMENT_REVENUE')"
) ?? 0.0);
$salesLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(gle.credit_amount - gle.debit_amount),0)
    FROM general_ledger_entries gle
    INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
    WHERE gle.account_code IN ('SALES_REVENUE','SALES_ADJUSTMENT_REVENUE')
      AND ft.reference_type = 'order'
      AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$receivableLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(debit_amount - credit_amount),0)
    FROM general_ledger_entries gle
    INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
    WHERE gle.account_code = 'ACCOUNTS_RECEIVABLE'
      AND ft.reference_type = 'order'
      AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$refundLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(debit_amount - credit_amount),0)
    FROM general_ledger_entries gle
    INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
    WHERE gle.account_code = 'SALES_REFUNDS'
      AND ft.reference_type = 'order'
      AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$cashLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(debit_amount - credit_amount),0)
    FROM general_ledger_entries gle
    INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
    WHERE gle.account_code = 'CASH_ON_HAND'
      AND ft.reference_type = 'order'
      AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$bankLedger = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(debit_amount - credit_amount),0)
     FROM general_ledger_entries gle
     INNER JOIN financial_transactions ft ON ft.id = gle.financial_transaction_id
     WHERE gle.account_code = 'BANK_CLEARING'
       AND ft.reference_type = 'order'
       AND ft.reference_id IN ({$idsCsv})"
) ?? 0.0);

$salesReport = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(
            CASE
                WHEN order_status IN ('cancelled','rejected') THEN 0
                WHEN payment_status NOT IN ('paid','credit','partially_refunded','refunded') THEN 0
                ELSE GREATEST(COALESCE(revised_grand_total, grand_total) - COALESCE(total_refunded, 0), 0)
            END
        ),0)
     FROM orders
     WHERE id IN ({$idsCsv})"
) ?? 0.0);

$outstandingReport = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(balance_due_amount),0)
     FROM orders
     WHERE id IN ({$idsCsv})"
) ?? 0.0);

$refundReport = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(CASE WHEN status = 'processed' THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END),0)
     FROM refund_transactions
     WHERE order_id IN ({$idsCsv})"
) ?? 0.0);

$cashReport = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(amount),0)
     FROM payment_transactions
     WHERE status = 'verified'
       AND payment_method = 'cash'
       AND order_id IN ({$idsCsv})"
) ?? 0.0);

$bankReport = (float)($db->fetchScalar(
    "SELECT COALESCE(SUM(amount),0)
     FROM payment_transactions
     WHERE status = 'verified'
       AND payment_method IN ('upi','bank_transfer','pos_card','payment_link')
       AND order_id IN ({$idsCsv})"
) ?? 0.0);

$report = [
    'generated_at' => date('c'),
    'scope' => 'latest_uat_dataset',
    'order_ids' => $ids,
    'checks' => [
        'debits_equal_credits' => [
            'debit_total' => round($totalDebit, 2),
            'credit_total' => round($totalCredit, 2),
            'pass' => abs($totalDebit - $totalCredit) < 0.01,
        ],
        'sales_ledger_equals_sales_report' => [
            'ledger' => round($salesLedger, 2),
            'report' => round($salesReport, 2),
            'pass' => abs($salesLedger - $salesReport) < 0.01,
        ],
        'receivable_ledger_equals_outstanding_report' => [
            'ledger' => round($receivableLedger, 2),
            'report' => round($outstandingReport, 2),
            'pass' => abs($receivableLedger - $outstandingReport) < 0.01,
        ],
        'refund_ledger_equals_refund_report' => [
            'ledger' => round($refundLedger, 2),
            'report' => round($refundReport, 2),
            'pass' => abs($refundLedger - $refundReport) < 0.01,
        ],
        'cash_ledger_equals_cash_report' => [
            'ledger' => round($cashLedger, 2),
            'report' => round($cashReport, 2),
            'pass' => abs($cashLedger - $cashReport) < 0.01,
        ],
        'bank_ledger_equals_bank_report' => [
            'ledger' => round($bankLedger, 2),
            'report' => round($bankReport, 2),
            'pass' => abs($bankLedger - $bankReport) < 0.01,
        ],
    ],
];

$allPass = true;
foreach ($report['checks'] as $check) {
    if (empty($check['pass'])) {
        $allPass = false;
        break;
    }
}
$report['overall_pass'] = $allPass;

$outPath = __DIR__ . '/financial_integrity_report.json';
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(['ok' => true, 'report' => 'storage/recovery/financial_integrity_report.json', 'overall_pass' => $allPass], JSON_UNESCAPED_SLASHES) . PHP_EOL;
