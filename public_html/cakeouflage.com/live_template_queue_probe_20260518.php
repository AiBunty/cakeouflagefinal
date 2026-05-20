<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\QueueWorker;

$recipient = 'parin11@gmail.com';

try {
    $pdo = Database::getConnection();

    $templateStmt = $pdo->query("SELECT event_key, subject FROM communication_templates WHERE channel = 'email' AND is_active = 1 ORDER BY event_key ASC");
    $templates = $templateStmt ? $templateStmt->fetchAll(PDO::FETCH_ASSOC) : array();

    $inserted = array();
    foreach ($templates as $template) {
        $eventKey = (string)$template['event_key'];
        $payload = array(
            'recipient' => $recipient,
            'template_key' => $eventKey,
            'template_subject' => (string)($template['subject'] ?? ''),
            'context' => array(
                'full_name' => 'Parin Daulat',
                'customer_name' => 'Parin Daulat',
                'first_name' => 'Parin',
                'email' => $recipient,
                'customer_email' => $recipient,
                'phone' => '',
                'customer_phone' => '',
                'order_number' => 'TEST-' . date('YmdHis'),
                'order_id' => 0,
                'amount' => '0.00',
                'otp' => '123456',
                'coupon_code' => 'PARIN10',
                'support_email' => 'hello@cakeouflage.com',
                'support_phone' => '+91 99999 99999',
                'website' => 'https://cakeouflage.com'
            )
        );

        $logStmt = $pdo->prepare("INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json) VALUES ('email', :event_key, :recipient, 'queued', :payload_json)");
        $logStmt->execute(array(
            'event_key' => $eventKey,
            'recipient' => $recipient,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ));
        $logId = (int)$pdo->lastInsertId();

        $queueStmt = $pdo->prepare("INSERT INTO communication_queue (communication_log_id, channel, payload_json) VALUES (:log_id, 'email', :payload_json)");
        $queueStmt->execute(array(
            'log_id' => $logId,
            'payload_json' => json_encode(array('log_id' => $logId), JSON_UNESCAPED_SLASHES),
        ));

        $jobStmt = $pdo->prepare("INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts) VALUES ('send_communication', :payload_json, 'queued', NOW(), 0)");
        $jobStmt->execute(array(
            'payload_json' => json_encode(array('log_id' => $logId), JSON_UNESCAPED_SLASHES),
        ));

        $inserted[] = array(
            'log_id' => $logId,
            'event_key' => $eventKey,
        );
    }

    $workerReport = QueueWorker::process($pdo, 200);

    $rows = array();
    $sent = 0;
    $failed = 0;
    $queued = 0;
    foreach ($inserted as $item) {
        $verifyStmt = $pdo->prepare('SELECT id, event_key, status, sent_at, error_message, provider_message_id FROM communication_logs WHERE id = :id LIMIT 1');
        $verifyStmt->execute(array('id' => (int)$item['log_id']));
        $row = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        $rows[] = $row;
        if ($row['status'] === 'sent') {
            $sent++;
        } elseif ($row['status'] === 'failed') {
            $failed++;
        } else {
            $queued++;
        }
    }

    echo json_encode(array(
        'ok' => true,
        'summary' => array(
            'sent' => $sent,
            'failed' => $failed,
            'queued' => $queued,
            'processed_keys' => array_map(function ($item) {
                return $item['event_key'];
            }, $inserted),
            'worker_report' => $workerReport,
        ),
        'data' => $rows,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
