<?php
declare(strict_types=1);

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$decoded = json_decode((string)$rawBody, true);

$contactName = '';
if (is_array($decoded)) {
    $contactName = trim((string)($decoded['contact_name'] ?? ''));
}

if ($contactName === '') {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => 'contact_name is required',
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'CRM mock accepted',
    'echo' => [
        'contact_name' => $contactName,
    ],
]);
