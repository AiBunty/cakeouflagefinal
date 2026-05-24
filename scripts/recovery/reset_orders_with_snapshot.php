<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Database;

function cliOut(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute([':table_name' => $table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function executeStatement(PDO $pdo, string $sql): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
}

function dumpQueryToNdjson(PDO $pdo, string $sql, string $filePath): int
{
    $stmt = $pdo->query($sql);
    if (!$stmt) {
        return 0;
    }

    $fh = fopen($filePath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('Unable to open snapshot file: ' . $filePath);
    }

    $count = 0;
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        $count++;
    }

    fclose($fh);
    return $count;
}

function ensureDir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create directory: ' . $path);
    }
}

$options = getopt('', ['confirm', 'snapshot-only']);
$confirm = array_key_exists('confirm', $options);
$snapshotOnly = array_key_exists('snapshot-only', $options);

if (!$confirm) {
    cliOut('Refusing to run without --confirm.');
    cliOut('Usage: php scripts/recovery/reset_orders_with_snapshot.php --confirm [--snapshot-only]');
    exit(1);
}

$pdo = Database::getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$timestamp = date('Ymd_His');
$snapshotDir = __DIR__ . '/../../storage/recovery/order-reset-' . $timestamp;
ensureDir($snapshotDir);

$tablesToSnapshot = [
    'orders' => 'SELECT o.* FROM orders o INNER JOIN tmp_reset_order_ids t ON t.id = o.id ORDER BY o.id',
    'order_items' => 'SELECT oi.* FROM order_items oi INNER JOIN tmp_reset_order_ids t ON t.id = oi.order_id ORDER BY oi.id',
    'slot_reservations' => 'SELECT sr.* FROM slot_reservations sr INNER JOIN tmp_reset_order_ids t ON t.id = sr.order_id ORDER BY sr.id',
    'slot_booking_logs' => 'SELECT sbl.* FROM slot_booking_logs sbl INNER JOIN tmp_reset_order_ids t ON t.id = sbl.order_id ORDER BY sbl.id',
    'coupon_redemptions' => 'SELECT cr.* FROM coupon_redemptions cr INNER JOIN tmp_reset_order_ids t ON t.id = cr.order_id ORDER BY cr.id',
    'order_status_history' => 'SELECT osh.* FROM order_status_history osh INNER JOIN tmp_reset_order_ids t ON t.id = osh.order_id ORDER BY osh.id',
    'order_audit_logs' => 'SELECT oal.* FROM order_audit_logs oal INNER JOIN tmp_reset_order_ids t ON t.id = oal.order_id ORDER BY oal.id',
    'order_destructive_logs' => 'SELECT odl.* FROM order_destructive_logs odl WHERE odl.order_id IN (SELECT id FROM tmp_reset_order_ids) ORDER BY odl.id',
    'refund_transactions' => 'SELECT rt.* FROM refund_transactions rt INNER JOIN tmp_reset_order_ids t ON t.id = rt.order_id ORDER BY rt.id',
    'refund_approval_logs' => 'SELECT ral.* FROM refund_approval_logs ral INNER JOIN tmp_refund_ids r ON r.id = ral.refund_transaction_id ORDER BY ral.id',
    'invoices' => 'SELECT i.* FROM invoices i INNER JOIN tmp_reset_order_ids t ON t.id = i.order_id ORDER BY i.id',
    'invoice_items' => 'SELECT ii.* FROM invoice_items ii INNER JOIN tmp_invoice_ids i ON i.id = ii.invoice_id ORDER BY ii.id',
    'payments' => 'SELECT p.* FROM payments p INNER JOIN tmp_invoice_ids i ON i.id = p.invoice_id ORDER BY p.id',
    'payment_proofs' => 'SELECT pp.* FROM payment_proofs pp INNER JOIN tmp_payment_ids p ON p.id = pp.payment_id ORDER BY pp.id',
    'payment_status_history' => 'SELECT psh.* FROM payment_status_history psh INNER JOIN tmp_invoice_ids i ON i.id = psh.invoice_id ORDER BY psh.id',
    'bank_alert_utrs' => 'SELECT bau.* FROM bank_alert_utrs bau WHERE bau.order_id IN (SELECT id FROM tmp_reset_order_ids) OR bau.invoice_id IN (SELECT id FROM tmp_invoice_ids) OR bau.payment_id IN (SELECT id FROM tmp_payment_ids) ORDER BY bau.id',
    'communication_logs' => 'SELECT cl.* FROM communication_logs cl WHERE cl.order_id IN (SELECT id FROM tmp_reset_order_ids) OR cl.invoice_id IN (SELECT id FROM tmp_invoice_ids) ORDER BY cl.id',
    'communication_queue' => 'SELECT cq.* FROM communication_queue cq INNER JOIN tmp_comm_log_ids cl ON cl.id = cq.communication_log_id ORDER BY cq.id',
    'crm_push_logs' => 'SELECT * FROM crm_push_logs ORDER BY id',
    'collection_followup_logs' => 'SELECT cfl.* FROM collection_followup_logs cfl INNER JOIN tmp_reset_order_ids t ON t.id = cfl.order_id ORDER BY cfl.id',
    'financial_transactions' => 'SELECT ft.* FROM financial_transactions ft WHERE (ft.reference_type = "order" AND ft.reference_id IN (SELECT id FROM tmp_reset_order_ids)) OR (ft.reference_type = "invoice" AND ft.reference_id IN (SELECT id FROM tmp_invoice_ids)) ORDER BY ft.id',
    'transaction_batches' => 'SELECT tb.* FROM transaction_batches tb INNER JOIN tmp_fin_tx_ids ft ON ft.id = tb.financial_transaction_id ORDER BY tb.id',
    'general_ledger_entries' => 'SELECT gle.* FROM general_ledger_entries gle WHERE gle.financial_transaction_id IN (SELECT id FROM tmp_fin_tx_ids) OR (gle.reference_type = "order" AND gle.reference_id IN (SELECT id FROM tmp_reset_order_ids)) OR (gle.reference_type = "invoice" AND gle.reference_id IN (SELECT id FROM tmp_invoice_ids)) ORDER BY gle.id',
    'financial_audit_logs' => 'SELECT fal.* FROM financial_audit_logs fal WHERE fal.financial_transaction_id IN (SELECT id FROM tmp_fin_tx_ids) OR fal.batch_id IN (SELECT id FROM tmp_batch_ids) ORDER BY fal.id',
    'byoc_quotes' => 'SELECT bq.* FROM byoc_quotes bq INNER JOIN tmp_byoc_quote_ids b ON b.id = bq.id ORDER BY bq.id',
    'byoc_quote_links' => 'SELECT bql.* FROM byoc_quote_links bql INNER JOIN tmp_byoc_quote_ids b ON b.id = bql.byoc_quote_id ORDER BY bql.id',
    'inquiries' => 'SELECT i.* FROM inquiries i INNER JOIN tmp_inquiry_ids ti ON ti.id = i.id ORDER BY i.id',
    'admin_action_logs' => 'SELECT aal.* FROM admin_action_logs aal WHERE aal.target_type = "order" AND aal.target_id IN (SELECT id FROM tmp_reset_order_ids) ORDER BY aal.id',
];

$deleteStatements = [
    'refund_approval_logs' => 'DELETE ral FROM refund_approval_logs ral INNER JOIN tmp_refund_ids r ON r.id = ral.refund_transaction_id',
    'refund_transactions' => 'DELETE rt FROM refund_transactions rt INNER JOIN tmp_reset_order_ids t ON t.id = rt.order_id',
    'payment_proofs' => 'DELETE pp FROM payment_proofs pp INNER JOIN tmp_payment_ids p ON p.id = pp.payment_id',
    'payments' => 'DELETE p FROM payments p INNER JOIN tmp_invoice_ids i ON i.id = p.invoice_id',
    'payment_status_history' => 'DELETE psh FROM payment_status_history psh INNER JOIN tmp_invoice_ids i ON i.id = psh.invoice_id',
    'invoice_items' => 'DELETE ii FROM invoice_items ii INNER JOIN tmp_invoice_ids i ON i.id = ii.invoice_id',
    'bank_alert_utrs' => 'DELETE FROM bank_alert_utrs WHERE order_id IN (SELECT id FROM tmp_reset_order_ids) OR invoice_id IN (SELECT id FROM tmp_invoice_ids) OR payment_id IN (SELECT id FROM tmp_payment_ids)',
    'communication_queue' => 'DELETE cq FROM communication_queue cq INNER JOIN tmp_comm_log_ids cl ON cl.id = cq.communication_log_id',
    'communication_logs' => 'DELETE FROM communication_logs WHERE order_id IN (SELECT id FROM tmp_reset_order_ids) OR invoice_id IN (SELECT id FROM tmp_invoice_ids)',
    'crm_push_logs' => 'DELETE FROM crm_push_logs',
    'slot_reservations' => 'DELETE sr FROM slot_reservations sr INNER JOIN tmp_reset_order_ids t ON t.id = sr.order_id',
    'slot_booking_logs' => 'DELETE sbl FROM slot_booking_logs sbl INNER JOIN tmp_reset_order_ids t ON t.id = sbl.order_id',
    'coupon_redemptions' => 'DELETE cr FROM coupon_redemptions cr INNER JOIN tmp_reset_order_ids t ON t.id = cr.order_id',
    'order_status_history' => 'DELETE osh FROM order_status_history osh INNER JOIN tmp_reset_order_ids t ON t.id = osh.order_id',
    'order_audit_logs' => 'DELETE oal FROM order_audit_logs oal INNER JOIN tmp_reset_order_ids t ON t.id = oal.order_id',
    'order_destructive_logs' => 'DELETE FROM order_destructive_logs WHERE order_id IN (SELECT id FROM tmp_reset_order_ids)',
    'collection_followup_logs' => 'DELETE cfl FROM collection_followup_logs cfl INNER JOIN tmp_reset_order_ids t ON t.id = cfl.order_id',
    'financial_audit_logs' => 'DELETE FROM financial_audit_logs WHERE financial_transaction_id IN (SELECT id FROM tmp_fin_tx_ids) OR batch_id IN (SELECT id FROM tmp_batch_ids)',
    'general_ledger_entries' => 'DELETE FROM general_ledger_entries WHERE financial_transaction_id IN (SELECT id FROM tmp_fin_tx_ids) OR (reference_type = "order" AND reference_id IN (SELECT id FROM tmp_reset_order_ids)) OR (reference_type = "invoice" AND reference_id IN (SELECT id FROM tmp_invoice_ids))',
    'transaction_batches' => 'DELETE FROM transaction_batches WHERE financial_transaction_id IN (SELECT id FROM tmp_fin_tx_ids)',
    'financial_transactions' => 'DELETE FROM financial_transactions WHERE (reference_type = "order" AND reference_id IN (SELECT id FROM tmp_reset_order_ids)) OR (reference_type = "invoice" AND reference_id IN (SELECT id FROM tmp_invoice_ids))',
    'admin_action_logs' => 'DELETE FROM admin_action_logs WHERE target_type = "order" AND target_id IN (SELECT id FROM tmp_reset_order_ids)',
    'order_items' => 'DELETE oi FROM order_items oi INNER JOIN tmp_reset_order_ids t ON t.id = oi.order_id',
    'invoices' => 'DELETE i FROM invoices i INNER JOIN tmp_invoice_ids ti ON ti.id = i.id',
    'orders' => 'DELETE o FROM orders o INNER JOIN tmp_reset_order_ids t ON t.id = o.id',
    'byoc_quote_links' => 'DELETE bql FROM byoc_quote_links bql INNER JOIN tmp_byoc_quote_ids b ON b.id = bql.byoc_quote_id',
    'byoc_quotes' => 'DELETE bq FROM byoc_quotes bq INNER JOIN tmp_byoc_quote_ids b ON b.id = bq.id',
    'inquiries' => 'DELETE i FROM inquiries i INNER JOIN tmp_inquiry_ids ti ON ti.id = i.id',
];

$summary = [
    'timestamp' => date('c'),
    'snapshot_dir' => $snapshotDir,
    'target_order_count' => 0,
    'snapshots' => [],
    'deleted' => [],
];

try {
    $pdo->beginTransaction();

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_reset_order_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_reset_order_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');

    $orderSourceFilter = 'order_source IN ("retail", "byoc_quote", "manual") OR order_source IS NULL';
    if (!columnExists($pdo, 'orders', 'order_source')) {
        $orderSourceFilter = '1=1';
    }

    executeStatement(
        $pdo,
        'INSERT INTO tmp_reset_order_ids (id)
         SELECT id
         FROM orders
         WHERE ' . $orderSourceFilter
    );

    $summary['target_order_count'] = (int)$pdo->query('SELECT COUNT(*) FROM tmp_reset_order_ids')->fetchColumn();

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_invoice_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_invoice_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'invoices')) {
        executeStatement($pdo, 'INSERT INTO tmp_invoice_ids (id) SELECT DISTINCT i.id FROM invoices i INNER JOIN tmp_reset_order_ids t ON t.id = i.order_id');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_payment_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_payment_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'payments')) {
        executeStatement($pdo, 'INSERT INTO tmp_payment_ids (id) SELECT DISTINCT p.id FROM payments p INNER JOIN tmp_invoice_ids i ON i.id = p.invoice_id');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_refund_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_refund_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'refund_transactions')) {
        executeStatement($pdo, 'INSERT INTO tmp_refund_ids (id) SELECT DISTINCT rt.id FROM refund_transactions rt INNER JOIN tmp_reset_order_ids t ON t.id = rt.order_id');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_comm_log_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_comm_log_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'communication_logs')) {
        executeStatement($pdo, 'INSERT INTO tmp_comm_log_ids (id) SELECT DISTINCT cl.id FROM communication_logs cl WHERE cl.order_id IN (SELECT id FROM tmp_reset_order_ids) OR cl.invoice_id IN (SELECT id FROM tmp_invoice_ids)');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_fin_tx_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_fin_tx_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'financial_transactions')) {
        executeStatement($pdo, 'INSERT INTO tmp_fin_tx_ids (id) SELECT DISTINCT ft.id FROM financial_transactions ft WHERE (ft.reference_type = "order" AND ft.reference_id IN (SELECT id FROM tmp_reset_order_ids)) OR (ft.reference_type = "invoice" AND ft.reference_id IN (SELECT id FROM tmp_invoice_ids))');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_batch_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_batch_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'transaction_batches')) {
        executeStatement($pdo, 'INSERT INTO tmp_batch_ids (id) SELECT DISTINCT tb.id FROM transaction_batches tb INNER JOIN tmp_fin_tx_ids ft ON ft.id = tb.financial_transaction_id');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_byoc_quote_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_byoc_quote_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'byoc_quotes') && columnExists($pdo, 'byoc_quotes', 'order_id')) {
        executeStatement($pdo, 'INSERT INTO tmp_byoc_quote_ids (id) SELECT DISTINCT bq.id FROM byoc_quotes bq INNER JOIN tmp_reset_order_ids t ON t.id = bq.order_id');
    }

    executeStatement($pdo, 'DROP TEMPORARY TABLE IF EXISTS tmp_inquiry_ids');
    executeStatement($pdo, 'CREATE TEMPORARY TABLE tmp_inquiry_ids (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=Memory');
    if (tableExists($pdo, 'byoc_quotes') && tableExists($pdo, 'inquiries') && columnExists($pdo, 'byoc_quotes', 'inquiry_id')) {
        executeStatement($pdo, 'INSERT INTO tmp_inquiry_ids (id) SELECT DISTINCT bq.inquiry_id FROM byoc_quotes bq INNER JOIN tmp_byoc_quote_ids tq ON tq.id = bq.id WHERE bq.inquiry_id IS NOT NULL');
    }

    foreach ($tablesToSnapshot as $table => $sql) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $file = $snapshotDir . '/' . $table . '.ndjson';
        $rows = dumpQueryToNdjson($pdo, $sql, $file);
        $summary['snapshots'][$table] = $rows;
    }

    if ($snapshotOnly) {
        $pdo->rollBack();
        $summary['snapshot_only'] = true;
        file_put_contents($snapshotDir . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        cliOut('Snapshot complete (snapshot-only mode). No data deleted.');
        cliOut('Snapshot dir: ' . $snapshotDir);
        exit(0);
    }

    foreach ($deleteStatements as $table => $sql) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $summary['deleted'][$table] = executeStatement($pdo, $sql);
    }

    $pdo->commit();

    file_put_contents($snapshotDir . '/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    cliOut('Order reset completed.');
    cliOut('Target orders: ' . $summary['target_order_count']);
    cliOut('Snapshot dir: ' . $snapshotDir);
    cliOut('Summary file: ' . $snapshotDir . '/summary.json');
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $summary['error'] = $e->getMessage();
    file_put_contents($snapshotDir . '/summary.error.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    cliOut('Reset failed: ' . $e->getMessage());
    cliOut('Error summary: ' . $snapshotDir . '/summary.error.json');
    exit(1);
}
