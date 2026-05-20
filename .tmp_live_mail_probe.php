<?php
use App\Core\Database;
use App\Core\QueueWorker;
require "app/bootstrap.php";
$pdo = Database::getInstance();
$recipient = "parin11@gmail.com";
$payload = json_encode(["to" => $recipient, "subject" => "SMTP Probe Test", "body" => "Test email from probe"]);
$stmt = $pdo->prepare("INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json, created_at) VALUES ('email', 'smtp_test', ?, 'queued', ?, NOW())");
$stmt->execute([$recipient, $payload]);
$logId = $pdo->lastInsertId();
$stmt = $pdo->prepare("INSERT INTO communication_queue (communication_log_id, channel, payload_json, created_at) VALUES (?, 'email', ?, NOW())");
$stmt->execute([$logId, $payload]);
$stmt = $pdo->prepare("INSERT INTO queue_jobs (job_type, payload_json, status, available_at, attempts, created_at) VALUES ('smtp_test_email', ?, 'queued', NOW(), 0, NOW())");
$stmt->execute([json_encode(["communication_log_id" => $logId])]);
QueueWorker::process($pdo, 20);
$stmt = $pdo->prepare("SELECT id, status, sent_at, error_message, provider_message_id FROM communication_logs WHERE id = ?");
$stmt->execute([$logId]);
$logRow = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(["ok" => true, "log_id" => $logId, "summary" => "Probe executed", "log" => $logRow]);
