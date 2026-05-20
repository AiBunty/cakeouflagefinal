<?php
require_once __DIR__ . '/app/bootstrap.php';
use App\Core\Database;
use App\Core\QueueWorker;
header('Content-Type: application/json');
if ((\['k'] ?? '') !== 'd4afdc4724424a6e9b5de96e1c4b9a12') { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
\ = [];
try {
  \ = Database::getConnection();
  \ = \->prepare('SELECT id, is_active FROM communication_templates WHERE channel = "email" AND event_key = :event_key');
  \->execute(['event_key' => 'password_reset']);
  \ = \->fetchAll(PDO::FETCH_ASSOC);

  \ = \->prepare('UPDATE communication_templates SET is_active = 0 WHERE channel = "email" AND event_key = :event_key');
  \->execute(['event_key' => 'password_reset']);

  \ = json_encode(['customer_name' => 'Ops Test'], JSON_UNESCAPED_SLASHES);
  \ = \->prepare('INSERT INTO communication_logs (channel, event_key, recipient, status, payload_json) VALUES ("email", :event_key, :recipient, "queued", :payload_json)');
  \->execute(['event_key' => 'password_reset', 'recipient' => 'ops-test@cakeouflage.com', 'payload_json' => \]);
  \ = (int)\->lastInsertId();

  \ = \->prepare('INSERT INTO communication_queue (communication_log_id, channel, queue_status, payload_json) VALUES (:log_id, "email", "queued", :payload_json)');
  \->execute(['log_id' => \, 'payload_json' => json_encode(['log_id' => \], JSON_UNESCAPED_SLASHES)]);

  \ = \->prepare('INSERT INTO queue_jobs (job_type, payload_json, status, attempts) VALUES ("send_communication", :payload_json, "queued", 0)');
  \->execute(['payload_json' => json_encode(['log_id' => \], JSON_UNESCAPED_SLASHES)]);
  \ = (int)\->lastInsertId();

  \ = QueueWorker::process(\, 1);

  \ = \->prepare('SELECT status, last_error, attempts FROM queue_jobs WHERE id = :id');
  \->execute(['id' => \]);
  \ = \->fetch(PDO::FETCH_ASSOC) ?: [];

  \ = \->prepare('SELECT status, error_message, payload_json FROM communication_logs WHERE id = :id');
  \->execute(['id' => \]);
  \ = \->fetch(PDO::FETCH_ASSOC) ?: [];

  foreach (\ as \) {
    \ = \->prepare('UPDATE communication_templates SET is_active = :is_active WHERE id = :id');
    \->execute(['is_active' => (int)\['is_active'], 'id' => (int)\['id']]);
  }

  \->prepare('DELETE FROM queue_jobs WHERE id = :id')->execute(['id' => \]);
  \->prepare('DELETE FROM communication_queue WHERE communication_log_id = :id')->execute(['id' => \]);
  \->prepare('DELETE FROM communication_logs WHERE id = :id')->execute(['id' => \]);

  \ = ['queue_result' => \, 'job' => \, 'log' => \];
} catch (Throwable \) {
  \ = ['error' => \->getMessage()];
}
echo json_encode(\, JSON_UNESCAPED_SLASHES);
