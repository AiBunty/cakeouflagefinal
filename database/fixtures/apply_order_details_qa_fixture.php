<?php
declare(strict_types=1);

// Apply the QA order-details fixture from inside the web container.
// Usage:
//   docker exec cakeouflage-web php /var/www/html/database/fixtures/apply_order_details_qa_fixture.php

require __DIR__ . '/../../admin/includes/db.php';

$fixturePath = __DIR__ . '/order_details_qa_fixture.sql';
if (!is_file($fixturePath)) {
    fwrite(STDERR, "Fixture file not found: {$fixturePath}" . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($fixturePath);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Fixture SQL is empty or unreadable." . PHP_EOL);
    exit(1);
}

if (!$conn->multi_query($sql)) {
    fwrite(STDERR, "Failed to execute fixture: " . $conn->error . PHP_EOL);
    exit(1);
}

while ($conn->more_results()) {
    if (!$conn->next_result()) {
        fwrite(STDERR, "Fixture execution error: " . $conn->error . PHP_EOL);
        exit(1);
    }
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

$summary = [];
$result = $conn->query(
    "SELECT id, order_number, order_status, payment_status\n"
    . "FROM orders\n"
    . "WHERE order_number LIKE 'QA-DETAIL-%'\n"
    . "ORDER BY id ASC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $summary[] = sprintf(
            "%d\t%s\t%s\t%s",
            (int)$row['id'],
            (string)$row['order_number'],
            (string)$row['order_status'],
            (string)$row['payment_status']
        );
    }
    $result->free();
}

echo "QA fixture applied successfully." . PHP_EOL;
echo "Rows:" . PHP_EOL;
echo implode(PHP_EOL, $summary) . PHP_EOL;
