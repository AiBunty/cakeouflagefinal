<?php
if ((\['key'] ?? '') !== '5477f162-2f35-4f74-9e58-fd6b2a5e2c9c') die('Unauthorized');
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';
header('Content-Type: application/json');
try {
    \ = \App\Core\Database::getConnection();
    \ = \->query("SELECT id, is_active FROM communication_templates WHERE channel='email' AND event_key='password_reset'")->fetchAll(PDO::FETCH_ASSOC);
    \->exec("UPDATE communication_templates SET is_active = 0 WHERE channel='email' AND event_key='password_reset'");

    \->prepare("INSERT INTO communication_logs (recipient, channel, event_key, status, payload_json) VALUES (?, ?, ?, ?, ?)")
         ->execute(['test@example.com', 'email', 'password_reset', 'queued', json_encode(['name'=>'Tester'])]);
    \ = \->lastInsertId();

    \->prepare("INSERT INTO communication_queue (communication_log_id) VALUES (?)")->execute([\]);
    \ = \->lastInsertId();

    \->prepare("INSERT INTO queue_jobs (job_type, payload_json, status, attempts) VALUES (?, ?, ?, 0)")
         ->execute(['send_communication', json_encode(['log_id' => \]), 'queued']);
    \ = \->lastInsertId();

    \App\Core\QueueWorker::process(\, 1);

    \ = \->query("SELECT status, last_error, attempts FROM queue_jobs WHERE id = \")->fetch(PDO::FETCH_ASSOC);
    \ = \->query("SELECT status, error_message, payload_json FROM communication_logs WHERE id = \")->fetch(PDO::FETCH_ASSOC);

    foreach (\ as \) {
        \->prepare("UPDATE communication_templates SET is_active = ? WHERE id = ?")->execute([\['is_active'], \['id']]);
    }
    \->prepare("DELETE FROM queue_jobs WHERE id = ?")->execute([\]);
    \->prepare("DELETE FROM communication_queue WHERE communication_log_id = ?")->execute([\]);
    \->prepare("DELETE FROM communication_logs WHERE id = ?")->execute([\]);

    echo json_encode(['job' => \, 'log' => \]);
} catch (Exception \) {
    echo json_encode(['error' => \->getMessage()]);
}