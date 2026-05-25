<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

final class FinanceReportService
{
    private const COLLECTION_FOLLOWUP_STATUS_OPTIONS = [
        'all',
        'no_reminder',
        'reminder_sent',
        'customer_responded',
        'payment_promised',
        'escalated',
        'settled',
    ];

    private const COLLECTION_PRIORITY_OPTIONS = [
        'all',
        'normal',
        'high',
    ];

    private const COLLECTION_ACTION_DUE_OPTIONS = [
        'all',
        'today',
        'next_24h',
        'overdue',
    ];

    private const PAYMENT_SCOPE_OPTIONS = [
        'finance_safe',
        'realized_only',
        'pending_collection',
        'due_today',
        'due_tomorrow',
        'overdue',
        'refunds',
        'exceptions',
        'all',
        'paid',
        'pending',
        'credit',
        'under_review',
        'refund_pending',
        'partially_refunded',
        'refunded',
        'failed',
        'rejected',
    ];

    private const ORDER_STATUS_OPTIONS = [
        'all',
        'pending_payment',
        'payment_under_review',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
        'rejected',
        'refund_requested',
        'partially_refunded',
        'fully_refunded',
        'refunded',
    ];

    private const DATE_PRESETS = [
        'today',
        'yesterday',
        'this_week',
        'this_month',
        'last_month',
        'custom',
    ];

    private Database $db;
    private ?bool $ledgerAdapterEnabled = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function normalizeFilters(array $input): array
    {
        $view = strtolower(trim((string)($input['view'] ?? 'sales')));
        if (!in_array($view, ['sales', 'collection'], true)) {
            $view = 'sales';
        }

        $datePreset = strtolower(trim((string)($input['date_preset'] ?? 'this_month')));
        if (!in_array($datePreset, self::DATE_PRESETS, true)) {
            $datePreset = 'this_month';
        }

        $dateBasis = strtolower(trim((string)($input['date_basis'] ?? 'payment')));
        if (!in_array($dateBasis, ['booking', 'payment', 'fulfilment'], true)) {
            $dateBasis = 'payment';
        }

        [$fromDate, $toDate] = $this->resolveDateRange(
            $datePreset,
            (string)($input['from_date'] ?? ''),
            (string)($input['to_date'] ?? '')
        );

        $paymentScope = strtolower(trim((string)($input['payment_status'] ?? 'finance_safe')));
        if (!in_array($paymentScope, self::PAYMENT_SCOPE_OPTIONS, true)) {
            $paymentScope = 'finance_safe';
        }

        $orderStatus = strtolower(trim((string)($input['order_status'] ?? 'all')));
        if (!in_array($orderStatus, self::ORDER_STATUS_OPTIONS, true)) {
            $orderStatus = 'all';
        }

        $paymentMethod = strtolower(trim((string)($input['payment_method'] ?? 'all')));
        if (!in_array($paymentMethod, ['all', 'cod', 'bank', 'credit'], true)) {
            $paymentMethod = 'all';
        }

        return [
            'view' => $view,
            'date_preset' => $datePreset,
            'date_basis' => $dateBasis,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'order_no' => trim((string)($input['order_no'] ?? '')),
            'item' => trim((string)($input['item'] ?? '')),
            'mobile' => trim((string)($input['mobile'] ?? '')),
            'payment_status' => $paymentScope,
            'order_status' => $orderStatus,
            'payment_method' => $paymentMethod,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, mixed>
     */
    public function getRegister(array $filters, int $perPage, int $page): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params, $orderBy] = $this->buildOuterWhere($filters);

        $countRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total_rows FROM ({$baseSql}) financial_rows WHERE {$whereSql}",
            $params
        ) ?? ['total_rows' => 0];

        $totalRows = (int)($countRow['total_rows'] ?? 0);
        $totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db->fetchAll(
            "SELECT * FROM ({$baseSql}) financial_rows WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $totals = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(cash_net_amount), 0) AS cash_total,
                COALESCE(SUM(bank_net_amount), 0) AS bank_total,
                COALESCE(SUM(credit_outstanding_amount), 0) AS credit_total,
                COALESCE(SUM(net_collected_amount), 0) AS overall_total,
                COALESCE(SUM(realized_gross_amount), 0) AS gross_total,
                COALESCE(SUM(refund_amount), 0) AS refunded_total,
                COALESCE(SUM(advance_collected_amount), 0) AS advance_collected,
                COALESCE(SUM(CASE WHEN balance_due_amount > 0 THEN balance_due_amount ELSE 0 END), 0) AS balance_outstanding,
                COALESCE(SUM(CASE WHEN collection_status_label = 'Advance Paid' THEN 1 ELSE 0 END), 0) AS advance_orders,
                COALESCE(SUM(CASE WHEN collection_status_label = 'Overdue' THEN 1 ELSE 0 END), 0) AS overdue_orders,
                COALESCE(SUM(CASE WHEN balance_due_amount > 0 AND DATE(collection_due_date) = CURDATE() THEN 1 ELSE 0 END), 0) AS collections_due_today,
                COALESCE(SUM(CASE WHEN refund_amount > 0 THEN 1 ELSE 0 END), 0) AS refunded_orders,
                COALESCE(SUM(CASE WHEN net_collected_amount > 0 THEN 1 ELSE 0 END), 0) AS realized_orders
             FROM ({$baseSql}) financial_rows
             WHERE {$whereSql}",
            $params
        ) ?: [];

        $ledgerTotals = $this->getLedgerTotalsForRange($filters['from_date'] ?? '', $filters['to_date'] ?? '');
        $ledgerApplied = $this->isLedgerAdapterEnabled();
        if ($ledgerApplied) {
            $totals['cash_total'] = $ledgerTotals['cash_total'];
            $totals['bank_total'] = $ledgerTotals['bank_total'];
            $totals['overall_total'] = $ledgerTotals['overall_total'];
            $totals['refunded_total'] = $ledgerTotals['refunded_total'];
            $totals['advance_collected'] = $ledgerTotals['advance_collected'];
        }

        return [
            'filters' => $filters,
            'rows' => $rows,
            'totals' => $totals,
            'ledger_adapter' => [
                'enabled' => $ledgerApplied,
                'applied' => $ledgerApplied,
                'totals' => $ledgerTotals,
            ],
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function getRegisterExportRows(array $filters): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params, $orderBy] = $this->buildOuterWhere($filters);
        return $this->db->fetchAll(
            "SELECT * FROM ({$baseSql}) financial_rows WHERE {$whereSql} ORDER BY {$orderBy}",
            $params
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function normalizeCollectionQueueFilters(array $input): array
    {
        $followupStatus = strtolower(trim((string)($input['followup_status'] ?? 'all')));
        if (!in_array($followupStatus, self::COLLECTION_FOLLOWUP_STATUS_OPTIONS, true)) {
            $followupStatus = 'all';
        }

        $collectionPriority = strtolower(trim((string)($input['collection_priority'] ?? 'all')));
        if (!in_array($collectionPriority, self::COLLECTION_PRIORITY_OPTIONS, true)) {
            $collectionPriority = 'all';
        }

        $actionDue = strtolower(trim((string)($input['action_due'] ?? 'all')));
        if (!in_array($actionDue, self::COLLECTION_ACTION_DUE_OPTIONS, true)) {
            $actionDue = 'all';
        }

        return [
            'followup_status' => $followupStatus,
            'collection_priority' => $collectionPriority,
            'action_due' => $actionDue,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $queueFilters
     * @return array<string, mixed>
     */
    public function getCollectionsQueue(array $filters, array $queueFilters, int $perPage, int $page): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params, $orderBy] = $this->buildOuterWhere($filters);
        [$queueWhereSql, $queueParams] = $this->buildCollectionQueueWhere($queueFilters);

        $joinedWhere = '(' . $whereSql . ') AND (' . $queueWhereSql . ')';
        $allParams = array_merge($params, $queueParams);

        $countRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total_rows FROM ({$baseSql}) queue_rows WHERE {$joinedWhere}",
            $allParams
        ) ?? ['total_rows' => 0];

        $totalRows = (int)($countRow['total_rows'] ?? 0);
        $totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db->fetchAll(
            "SELECT * FROM ({$baseSql}) queue_rows WHERE {$joinedWhere} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $allParams
        );

        $totals = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN balance_due_amount > 0 THEN balance_due_amount ELSE 0 END), 0) AS total_balance_due,
                COALESCE(SUM(CASE WHEN collection_status_label = 'Overdue' THEN 1 ELSE 0 END), 0) AS overdue_orders,
                COALESCE(SUM(CASE WHEN followup_status = 'reminder_sent' THEN 1 ELSE 0 END), 0) AS reminder_sent_orders,
                COALESCE(SUM(CASE WHEN followup_status = 'payment_promised' THEN 1 ELSE 0 END), 0) AS promised_orders,
                COALESCE(SUM(CASE WHEN followup_status = 'escalated' THEN 1 ELSE 0 END), 0) AS escalated_orders
             FROM ({$baseSql}) queue_rows
             WHERE {$joinedWhere}",
            $allParams
        ) ?: [];

        return [
            'filters' => $filters,
            'queueFilters' => $queueFilters,
            'rows' => $rows,
            'totals' => $totals,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $queueFilters
     * @return list<array<string, mixed>>
     */
    public function getCollectionsQueueExportRows(array $filters, array $queueFilters): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params, $orderBy] = $this->buildOuterWhere($filters);
        [$queueWhereSql, $queueParams] = $this->buildCollectionQueueWhere($queueFilters);

        return $this->db->fetchAll(
            "SELECT * FROM ({$baseSql}) queue_rows WHERE ({$whereSql}) AND ({$queueWhereSql}) ORDER BY {$orderBy}",
            array_merge($params, $queueParams)
        );
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $queueFilters
     * @return list<array<string, mixed>>
     */
    public function getAgingBucketRows(array $filters, array $queueFilters): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params] = $this->buildOuterWhere($filters);
        [$queueWhereSql, $queueParams] = $this->buildCollectionQueueWhere($queueFilters);
        $allParams = array_merge($params, $queueParams);

        return $this->db->fetchAll(
            "SELECT
                CASE
                    WHEN DATEDIFF(CURDATE(), DATE(collection_due_date)) <= 3 THEN '0-3 days'
                    WHEN DATEDIFF(CURDATE(), DATE(collection_due_date)) BETWEEN 4 AND 7 THEN '4-7 days'
                    WHEN DATEDIFF(CURDATE(), DATE(collection_due_date)) BETWEEN 8 AND 15 THEN '8-15 days'
                    WHEN DATEDIFF(CURDATE(), DATE(collection_due_date)) BETWEEN 16 AND 30 THEN '16-30 days'
                    ELSE '31+ days'
                END AS aging_bucket,
                COUNT(*) AS order_count,
                COALESCE(SUM(balance_due_amount), 0) AS balance_due_total,
                MIN(DATE(collection_due_date)) AS earliest_due_date,
                MAX(DATE(collection_due_date)) AS latest_due_date
             FROM ({$baseSql}) aging_rows
             WHERE ({$whereSql})
               AND ({$queueWhereSql})
               AND balance_due_amount > 0
               AND DATE(collection_due_date) < CURDATE()
             GROUP BY aging_bucket
             ORDER BY CASE aging_bucket
                WHEN '0-3 days' THEN 1
                WHEN '4-7 days' THEN 2
                WHEN '8-15 days' THEN 3
                WHEN '16-30 days' THEN 4
                ELSE 5
             END",
            $allParams
        );
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $queueFilters
     * @return list<array<string, mixed>>
     */
    public function getOverdueFollowupRows(array $filters, array $queueFilters): array
    {
        $baseSql = $this->baseDatasetSql();
        [$whereSql, $params, $orderBy] = $this->buildOuterWhere($filters);
        [$queueWhereSql, $queueParams] = $this->buildCollectionQueueWhere($queueFilters);
        $allParams = array_merge($params, $queueParams);

        return $this->db->fetchAll(
            "SELECT
                overdue.*,
                logs.last_action_type,
                logs.last_action_at,
                logs.last_actor_name,
                logs.last_message
             FROM ({$baseSql}) overdue
             LEFT JOIN (
                SELECT
                    l.order_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.action_type ORDER BY l.created_at DESC SEPARATOR ','), ',', 1) AS last_action_type,
                    MAX(l.created_at) AS last_action_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(l.actor_name, '') ORDER BY l.created_at DESC SEPARATOR ','), ',', 1) AS last_actor_name,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(l.message_text, '') ORDER BY l.created_at DESC SEPARATOR '\\u001F'), '\\u001F', 1) AS last_message
                FROM collection_followup_logs l
                GROUP BY l.order_id
             ) logs ON logs.order_id = overdue.id
             WHERE ({$whereSql})
               AND ({$queueWhereSql})
               AND overdue.balance_due_amount > 0
               AND DATE(overdue.collection_due_date) < CURDATE()
             ORDER BY {$orderBy}",
            $allParams
        );
    }

    /**
     * @return array<string, float|int>
     */
    public function getDashboardSnapshot(): array
    {
        $today = $this->dateRangeFilters('today');
        $thisMonth = $this->dateRangeFilters('this_month');
        $lastMonth = $this->dateRangeFilters('last_month');
        $thisYear = $this->yearRangeFilters((int)date('Y'));
        $lastYear = $this->yearRangeFilters((int)date('Y') - 1);
        $thisMonthAr = $this->normalizeFilters([
            'date_preset' => 'this_month',
            'date_basis' => 'payment',
            'payment_status' => 'all',
        ]);

        $todayRegister = $this->getRegister($today, 1, 1);
        $thisMonthRegister = $this->getRegister($thisMonth, 1, 1);
        $lastMonthRegister = $this->getRegister($lastMonth, 1, 1);
        $thisYearRegister = $this->getRegister($thisYear, 1, 1);
        $lastYearRegister = $this->getRegister($lastYear, 1, 1);
        $thisMonthArRegister = $this->getRegister($thisMonthAr, 1, 1);

        $todayRevenue = (float)($todayRegister['totals']['overall_total'] ?? 0);
        $thisMonthRevenue = (float)($thisMonthRegister['totals']['overall_total'] ?? 0);
        $lastMonthRevenue = (float)($lastMonthRegister['totals']['overall_total'] ?? 0);
        $thisYearRevenue = (float)($thisYearRegister['totals']['overall_total'] ?? 0);
        $lastYearRevenue = (float)($lastYearRegister['totals']['overall_total'] ?? 0);

        $ledgerToday = $this->getLedgerTotalsForRange($today['from_date'] ?? '', $today['to_date'] ?? '');
        $ledgerThisMonth = $this->getLedgerTotalsForRange($thisMonth['from_date'] ?? '', $thisMonth['to_date'] ?? '');
        $ledgerLastMonth = $this->getLedgerTotalsForRange($lastMonth['from_date'] ?? '', $lastMonth['to_date'] ?? '');
        $ledgerThisYear = $this->getLedgerTotalsForRange($thisYear['from_date'] ?? '', $thisYear['to_date'] ?? '');
        $ledgerLastYear = $this->getLedgerTotalsForRange($lastYear['from_date'] ?? '', $lastYear['to_date'] ?? '');
        $ledgerArBalance = $this->getLedgerAccountBalance('ACCOUNTS_RECEIVABLE', true);
        $ledgerAdvancesBalance = $this->getLedgerAccountBalance('CUSTOMER_ADVANCES', false);

        if ($this->isLedgerAdapterEnabled()) {
            $todayRevenue = $ledgerToday['overall_total'];
            $thisMonthRevenue = $ledgerThisMonth['overall_total'];
            $lastMonthRevenue = $ledgerLastMonth['overall_total'];
            $thisYearRevenue = $ledgerThisYear['overall_total'];
            $lastYearRevenue = $ledgerLastYear['overall_total'];
        }

        $pendingCollection = (float)($thisMonthArRegister['totals']['balance_outstanding'] ?? 0);
        $advanceOrders = (int)($thisMonthArRegister['totals']['advance_orders'] ?? 0);
        $overduePayments = (int)($thisMonthArRegister['totals']['overdue_orders'] ?? 0);
        $todayCollectionsDue = (int)($thisMonthArRegister['totals']['collections_due_today'] ?? 0);
        $totalReceivables = (float)$this->db->fetchScalar(
            "SELECT COALESCE(SUM(balance_due_amount), 0)
             FROM ({$this->baseDatasetSql()}) ar
             WHERE balance_due_amount > 0"
        );

        $refunds = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(COALESCE(processed_at, created_at)) = CURDATE() AND status = 'processed' THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS refunded_today,
                COALESCE(SUM(CASE WHEN status = 'processed' THEN COALESCE(approved_amount, requested_amount, 0) ELSE 0 END), 0) AS refunded_total,
                COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END), 0) AS pending_refunds,
                COALESCE(SUM(CASE WHEN status = 'processed' AND refund_type = 'partial' THEN 1 ELSE 0 END), 0) AS partial_refunds,
                COALESCE(SUM(CASE WHEN status = 'processed' AND refund_type = 'full' THEN 1 ELSE 0 END), 0) AS full_refunds
             FROM refund_transactions"
        ) ?: [];

        return [
            'today_revenue' => $todayRevenue,
            'this_month_revenue' => $thisMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'this_year_revenue' => $thisYearRevenue,
            'last_year_revenue' => $lastYearRevenue,
            'pending_collection' => $pendingCollection,
            'advance_orders' => $advanceOrders,
            'overdue_payments' => $overduePayments,
            'today_collections_due' => $todayCollectionsDue,
            'total_receivables' => $totalReceivables,
            'collected_today' => $todayRevenue,
            'refunded_today' => (float)($refunds['refunded_today'] ?? 0),
            'refunded_total' => (float)($refunds['refunded_total'] ?? 0),
            'pending_refunds' => (int)($refunds['pending_refunds'] ?? 0),
            'partial_refunds' => (int)($refunds['partial_refunds'] ?? 0),
            'full_refunds' => (int)($refunds['full_refunds'] ?? 0),
            'ledger_adapter_enabled' => $this->isLedgerAdapterEnabled(),
            'ledger_ar_balance' => $ledgerArBalance,
            'ledger_customer_advances_balance' => $ledgerAdvancesBalance,
            'ledger_today_totals' => $ledgerToday,
            'ledger_this_month_totals' => $ledgerThisMonth,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(string $preset, string $fromDate, string $toDate): array
    {
        if ($preset === 'custom' && $this->isValidDate($fromDate) && $this->isValidDate($toDate)) {
            return [$fromDate, $toDate];
        }

        $today = new \DateTimeImmutable('today');
        switch ($preset) {
            case 'today':
                return [$today->format('Y-m-d'), $today->format('Y-m-d')];
            case 'yesterday':
                $yesterday = $today->modify('-1 day');
                return [$yesterday->format('Y-m-d'), $yesterday->format('Y-m-d')];
            case 'this_week':
                $weekStart = $today->modify('monday this week');
                return [$weekStart->format('Y-m-d'), $today->format('Y-m-d')];
            case 'last_month':
                $start = $today->modify('first day of last month');
                $end = $today->modify('last day of last month');
                return [$start->format('Y-m-d'), $end->format('Y-m-d')];
            case 'custom':
                break;
            case 'this_month':
            default:
                $start = $today->modify('first day of this month');
                return [$start->format('Y-m-d'), $today->format('Y-m-d')];
        }

        $fallbackStart = $today->modify('first day of this month')->format('Y-m-d');
        return [$fallbackStart, $today->format('Y-m-d')];
    }

    /**
     * @param array<string, string> $filters
     * @return array{0:string,1:array<string, mixed>,2:string}
     */
    private function buildOuterWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];

        $dateColumn = 'recognized_at';
        if (($filters['date_basis'] ?? 'payment') === 'booking') {
            $dateColumn = 'created_at';
        } elseif (($filters['date_basis'] ?? 'payment') === 'fulfilment') {
            $dateColumn = 'scheduled_slot';
        }

        if (($filters['from_date'] ?? '') !== '') {
            $conditions[] = "DATE(COALESCE({$dateColumn}, created_at)) >= :from_date";
            $params['from_date'] = $filters['from_date'];
        }
        if (($filters['to_date'] ?? '') !== '') {
            $conditions[] = "DATE(COALESCE({$dateColumn}, created_at)) <= :to_date";
            $params['to_date'] = $filters['to_date'];
        }

        if (($filters['order_no'] ?? '') !== '') {
            $conditions[] = 'order_number LIKE :order_no';
            $params['order_no'] = '%' . $filters['order_no'] . '%';
        }

        if (($filters['item'] ?? '') !== '') {
            $conditions[] = 'items_summary LIKE :item_query';
            $params['item_query'] = '%' . $filters['item'] . '%';
        }

        $mobileDigits = preg_replace('/\D+/', '', (string)($filters['mobile'] ?? ''));
        if ($mobileDigits !== null && $mobileDigits !== '') {
            if (strlen($mobileDigits) > 10) {
                $mobileDigits = substr($mobileDigits, -10);
            }
            $conditions[] = '(REPLACE(REPLACE(REPLACE(customer_phone, "+", ""), "-", ""), " ", "") LIKE :mobile_search OR customer_phone_e164 LIKE :mobile_e164)';
            $params['mobile_search'] = '%' . $mobileDigits . '%';
            $params['mobile_e164'] = '%' . $mobileDigits;
        }

        $paymentScope = $filters['payment_status'] ?? 'finance_safe';
        switch ($paymentScope) {
            case 'finance_safe':
                $conditions[] = '(net_collected_amount > 0 OR refund_amount > 0 OR advance_collected_amount > 0 OR balance_due_amount > 0)';
                break;
            case 'realized_only':
                $conditions[] = 'net_collected_amount > 0';
                break;
            case 'pending_collection':
                $conditions[] = 'balance_due_amount > 0';
                break;
            case 'due_today':
                $conditions[] = 'balance_due_amount > 0 AND DATE(collection_due_date) = CURDATE()';
                break;
            case 'due_tomorrow':
                $conditions[] = 'balance_due_amount > 0 AND DATE(collection_due_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
                break;
            case 'overdue':
                $conditions[] = 'balance_due_amount > 0 AND DATE(collection_due_date) < CURDATE()';
                break;
            case 'refunds':
                $conditions[] = '(refund_amount > 0 OR payment_status IN ("refund_pending", "partially_refunded", "refunded"))';
                break;
            case 'exceptions':
                $conditions[] = '(payment_status IN ("failed", "rejected", "under_review") OR order_status IN ("cancelled", "rejected"))';
                break;
            case 'all':
                break;
            default:
                $conditions[] = 'payment_status = :payment_status_exact';
                $params['payment_status_exact'] = $paymentScope;
                break;
        }

        if (($filters['order_status'] ?? 'all') !== 'all') {
            $conditions[] = 'order_status = :order_status';
            $params['order_status'] = $filters['order_status'];
        }

        if (($filters['payment_method'] ?? 'all') === 'cod') {
            $conditions[] = 'payment_method = "cod"';
        } elseif (($filters['payment_method'] ?? 'all') === 'credit') {
            $conditions[] = 'payment_method = "credit"';
        } elseif (($filters['payment_method'] ?? 'all') === 'bank') {
            $conditions[] = 'payment_method IN ("upi_manual", "gateway")';
        }

        $orderBy = 'COALESCE(' . $dateColumn . ', created_at) DESC, id DESC';
        return [implode(' AND ', $conditions), $params, $orderBy];
    }

    private function baseDatasetSql(): string
    {
        return "
            SELECT
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.customer_phone_e164,
                o.customer_email,
                o.order_status,
                o.payment_status,
                o.payment_method,
                o.fulfilment_mode,
                o.order_mode,
                o.created_at,
                o.scheduled_slot,
                DATE(COALESCE(o.scheduled_slot, o.created_at)) AS collection_due_date,
                COALESCE(o.payment_confirmed_at, inv.last_verified_at, o.created_at) AS recognized_at,
                o.subtotal,
                o.discount_total,
                o.tax_total,
                o.delivery_fee,
                o.grand_total,
                COALESCE(o.advance_amount, 0) AS advance_amount,
                COALESCE(o.followup_status, 'no_reminder') AS followup_status,
                o.last_followup_at,
                o.next_followup_at,
                COALESCE(o.followup_count, 0) AS followup_count,
                COALESCE(o.collection_priority, 'normal') AS collection_priority,
                COALESCE(o.collection_note, '') AS collection_note,
                COALESCE(items.items_summary, '') AS items_summary,
                COALESCE(inv.invoice_number, '') AS invoice_number,
                COALESCE(inv.invoice_status, '') AS invoice_status,
                COALESCE(inv.balance_due, 0) AS invoice_balance_due,
                COALESCE(inv.verified_paid_amount, 0) AS verified_paid_amount,
                COALESCE(inv.last_verified_at, o.payment_confirmed_at) AS last_verified_at,
                COALESCE(refunds.refund_amount, COALESCE(o.total_refunded, 0), 0) AS refund_amount,
                COALESCE(refunds.refund_count, 0) AS refund_count,
                refunds.last_refunded_at,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    ELSE o.grand_total
                END AS gross_amount,
                LEAST(
                    CASE
                        WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                        WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                        ELSE 0
                    END,
                    o.grand_total
                ) AS collected_before_refunds,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                    ELSE 0
                END AS realized_gross_amount,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    ELSE GREATEST(
                        LEAST(
                            CASE
                                WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                ELSE 0
                            END,
                            o.grand_total
                        ) - COALESCE(refunds.refund_amount, COALESCE(o.total_refunded, 0), 0),
                        0
                    )
                END AS net_collected_amount,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    ELSE GREATEST(
                        LEAST(
                            CASE
                                WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                ELSE 0
                            END,
                            o.grand_total
                        ) - COALESCE(refunds.refund_amount, COALESCE(o.total_refunded, 0), 0),
                        0
                    )
                END AS net_realized_amount,
                CASE
                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN LEAST(COALESCE(o.advance_amount, 0), o.grand_total)
                    ELSE 0
                END AS advance_collected_amount,
                GREATEST(
                    CASE
                        WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                        ELSE o.grand_total - LEAST(
                            CASE
                                WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                ELSE 0
                            END,
                            o.grand_total
                        )
                    END,
                    0
                ) AS balance_due_amount,
                CASE
                    WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('fully_refunded', 'partially_refunded') THEN 'Refunded'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 AND DATE(COALESCE(o.scheduled_slot, o.created_at)) < CURDATE() THEN 'Overdue'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 AND LEAST(COALESCE(o.advance_amount, 0), o.grand_total) > 0 THEN 'Advance Paid'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 THEN 'Payment Pending'
                    ELSE 'Fully Paid'
                END AS collection_status_label,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    WHEN o.payment_method = 'cod' THEN GREATEST(
                        LEAST(
                            CASE
                                WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                ELSE 0
                            END,
                            o.grand_total
                        ) - COALESCE(refunds.refund_amount, COALESCE(o.total_refunded, 0), 0),
                        0
                    )
                    ELSE 0
                END AS cash_net_amount,
                CASE
                    WHEN o.order_status IN ('cancelled', 'rejected', 'refunded', 'partially_refunded', 'fully_refunded')
                        OR o.payment_status IN ('refunded', 'partially_refunded') THEN 0
                    WHEN o.payment_method IN ('upi_manual', 'gateway') THEN GREATEST(
                        LEAST(
                            CASE
                                WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                ELSE 0
                            END,
                            o.grand_total
                        ) - COALESCE(refunds.refund_amount, COALESCE(o.total_refunded, 0), 0),
                        0
                    )
                    ELSE 0
                END AS bank_net_amount,
                CASE
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 THEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    )
                    ELSE 0
                END AS credit_outstanding_amount,
                CASE
                    WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('fully_refunded', 'partially_refunded') THEN 'Refunded'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 AND DATE(COALESCE(o.scheduled_slot, o.created_at)) < CURDATE() THEN 'Overdue'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 AND LEAST(COALESCE(o.advance_amount, 0), o.grand_total) > 0 THEN 'Advance Paid'
                    WHEN GREATEST(
                        CASE
                            WHEN o.payment_status IN ('refunded', 'partially_refunded') OR o.order_status IN ('cancelled', 'rejected', 'fully_refunded', 'partially_refunded') THEN 0
                            ELSE o.grand_total - LEAST(
                                CASE
                                    WHEN o.payment_status IN ('paid', 'partially_refunded', 'refunded') THEN o.grand_total
                                    WHEN COALESCE(o.advance_amount, 0) > 0 THEN COALESCE(o.advance_amount, 0)
                                    ELSE 0
                                END,
                                o.grand_total
                            )
                        END,
                        0
                    ) > 0 THEN 'Payment Pending'
                    ELSE 'Fully Paid'
                END AS finance_status_label
            FROM orders o
            LEFT JOIN (
                SELECT
                    i.order_id,
                    MAX(i.invoice_number) AS invoice_number,
                    MAX(i.invoice_status) AS invoice_status,
                    MAX(i.balance_due) AS balance_due,
                    COALESCE(SUM(CASE WHEN p.payment_status = 'verified' THEN p.amount ELSE 0 END), 0) AS verified_paid_amount,
                    MAX(CASE WHEN p.payment_status = 'verified' THEN p.verified_at ELSE NULL END) AS last_verified_at
                FROM invoices i
                LEFT JOIN payments p ON p.invoice_id = i.id
                GROUP BY i.order_id
            ) inv ON inv.order_id = o.id
            LEFT JOIN (
                SELECT
                    oi.order_id,
                    GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name_snapshot) ORDER BY oi.id SEPARATOR ', ') AS items_summary
                FROM order_items oi
                GROUP BY oi.order_id
            ) items ON items.order_id = o.id
            LEFT JOIN (
                SELECT
                    rt.order_id,
                    COALESCE(SUM(CASE WHEN rt.status = 'processed' THEN COALESCE(rt.approved_amount, rt.requested_amount, 0) ELSE 0 END), 0) AS refund_amount,
                    COALESCE(SUM(CASE WHEN rt.status = 'processed' THEN 1 ELSE 0 END), 0) AS refund_count,
                    MAX(CASE WHEN rt.status = 'processed' THEN COALESCE(rt.processed_at, rt.updated_at, rt.created_at) ELSE NULL END) AS last_refunded_at
                FROM refund_transactions rt
                GROUP BY rt.order_id
            ) refunds ON refunds.order_id = o.id
        ";
    }

    /**
     * @return array<string, string>
     */
    private function dateRangeFilters(string $preset): array
    {
        return $this->normalizeFilters([
            'date_preset' => $preset,
            'date_basis' => 'payment',
            'payment_status' => 'realized_only',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function yearRangeFilters(int $year): array
    {
        return $this->normalizeFilters([
            'date_preset' => 'custom',
            'date_basis' => 'payment',
            'payment_status' => 'realized_only',
            'from_date' => sprintf('%04d-01-01', $year),
            'to_date' => sprintf('%04d-12-31', $year),
        ]);
    }

    /**
     * @param array<string, string> $queueFilters
     * @return array{0:string,1:array<string, string>}
     */
    private function buildCollectionQueueWhere(array $queueFilters): array
    {
        $conditions = ['balance_due_amount > 0'];
        $params = [];

        if (($queueFilters['followup_status'] ?? 'all') !== 'all') {
            $conditions[] = 'followup_status = :queue_followup_status';
            $params['queue_followup_status'] = $queueFilters['followup_status'];
        }

        if (($queueFilters['collection_priority'] ?? 'all') !== 'all') {
            $conditions[] = 'collection_priority = :queue_collection_priority';
            $params['queue_collection_priority'] = $queueFilters['collection_priority'];
        }

        $actionDue = $queueFilters['action_due'] ?? 'all';
        if ($actionDue === 'today') {
            $conditions[] = 'DATE(COALESCE(next_followup_at, collection_due_date)) = CURDATE()';
        } elseif ($actionDue === 'next_24h') {
            $conditions[] = 'COALESCE(next_followup_at, CAST(collection_due_date AS DATETIME)) <= DATE_ADD(NOW(), INTERVAL 1 DAY)';
        } elseif ($actionDue === 'overdue') {
            $conditions[] = 'COALESCE(next_followup_at, CAST(collection_due_date AS DATETIME)) < NOW()';
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function isLedgerAdapterEnabled(): bool
    {
        if ($this->ledgerAdapterEnabled !== null) {
            return $this->ledgerAdapterEnabled;
        }

        $flag = strtolower(trim((string)Env::get('FINANCE_LEDGER_REPORTS', '0')));
        $this->ledgerAdapterEnabled = in_array($flag, ['1', 'true', 'yes', 'on'], true);
        return $this->ledgerAdapterEnabled;
    }

    /**
     * @return array{cash_total:float,bank_total:float,overall_total:float,refunded_total:float,advance_collected:float,net_revenue:float}
     */
    private function getLedgerTotalsForRange(string $fromDate, string $toDate): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($this->isValidDate($fromDate)) {
            $conditions[] = 'DATE(g.created_at) >= :ledger_from_date';
            $params['ledger_from_date'] = $fromDate;
        }
        if ($this->isValidDate($toDate)) {
            $conditions[] = 'DATE(g.created_at) <= :ledger_to_date';
            $params['ledger_to_date'] = $toDate;
        }

        $whereSql = implode(' AND ', $conditions);
        $row = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN g.account_code = 'CASH_ON_HAND' THEN g.debit_amount - g.credit_amount ELSE 0 END), 0) AS cash_total,
                COALESCE(SUM(CASE WHEN g.account_code = 'BANK_CLEARING' THEN g.debit_amount - g.credit_amount ELSE 0 END), 0) AS bank_total,
                COALESCE(SUM(CASE WHEN g.account_code = 'SALES_REFUNDS' THEN g.debit_amount - g.credit_amount ELSE 0 END), 0) AS refunded_total,
                COALESCE(SUM(CASE WHEN g.account_code = 'CUSTOMER_ADVANCES' THEN g.credit_amount - g.debit_amount ELSE 0 END), 0) AS advance_collected,
                COALESCE(SUM(CASE WHEN g.account_code = 'SALES_REVENUE' THEN g.credit_amount - g.debit_amount ELSE 0 END), 0)
                  - COALESCE(SUM(CASE WHEN g.account_code = 'SALES_REFUNDS' THEN g.debit_amount - g.credit_amount ELSE 0 END), 0) AS net_revenue
             FROM general_ledger_entries g
             WHERE {$whereSql}",
            $params
        ) ?: [];

        $cashTotal = (float)($row['cash_total'] ?? 0);
        $bankTotal = (float)($row['bank_total'] ?? 0);

        return [
            'cash_total' => $cashTotal,
            'bank_total' => $bankTotal,
            'overall_total' => $cashTotal + $bankTotal,
            'refunded_total' => (float)($row['refunded_total'] ?? 0),
            'advance_collected' => (float)($row['advance_collected'] ?? 0),
            'net_revenue' => (float)($row['net_revenue'] ?? 0),
        ];
    }

    private function getLedgerAccountBalance(string $accountCode, bool $debitNormal): float
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(debit_amount), 0) AS total_debit, COALESCE(SUM(credit_amount), 0) AS total_credit
             FROM general_ledger_entries
             WHERE account_code = :account_code',
            ['account_code' => $accountCode]
        ) ?: [];

        $totalDebit = (float)($row['total_debit'] ?? 0);
        $totalCredit = (float)($row['total_credit'] ?? 0);
        return $debitNormal
            ? round($totalDebit - $totalCredit, 2)
            : round($totalCredit - $totalDebit, 2);
    }
}