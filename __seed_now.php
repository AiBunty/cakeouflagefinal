<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
use App\Core\Database;

$key = isset($_GET['k']) ? (string)$_GET['k'] : '';
if ($key !== 'CAKEO-RESEED-20260402') {
    http_response_code(403);
    echo 'forbidden';
    exit;
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';
        if ($ch === "'" && !$inDouble && $prev !== '\\') {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && $prev !== '\\') {
            $inDouble = !$inDouble;
        }
        if ($ch === ';' && !$inSingle && !$inDouble) {
            $trimmed = trim($buffer);
            if ($trimmed !== '' && strpos($trimmed, '--') !== 0) {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    $tail = trim($buffer);
    if ($tail !== '' && strpos($tail, '--') !== 0) {
        $statements[] = $tail;
    }
    return $statements;
}

$schema = file_get_contents(__DIR__ . '/database/schema.sql');
$seed = file_get_contents(__DIR__ . '/database/seed.sql');
if ($schema === false || $seed === false) {
    http_response_code(500);
    echo 'missing_sql';
    exit;
}

$pdo = Database::getConnection();
foreach (splitSqlStatements($schema) as $stmt) {
    if (trim($stmt) !== '') { $pdo->exec($stmt); }
}
foreach (splitSqlStatements($seed) as $stmt) {
    if (trim($stmt) !== '') { $pdo->exec($stmt); }
}

$counts = [
    'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'events' => (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
];
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'counts' => $counts], JSON_UNESCAPED_SLASHES);
