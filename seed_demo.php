<?php
declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/app/bootstrap.php';

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

header('Content-Type: text/html; charset=utf-8');

$token = Env::get('SEED_WEB_TOKEN', Env::get('QUEUE_CRON_TOKEN', ''));
$provided = $_GET['token'] ?? '';

if ($token === '' || !hash_equals($token, (string) $provided)) {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Invalid or missing token.</p>';
    exit;
}

$run = (($_GET['run'] ?? '0') === '1');
$schemaPath = __DIR__ . '/database/schema.sql';
$seedPath = __DIR__ . '/database/seed.sql';

if (!is_file($schemaPath) || !is_file($seedPath)) {
    http_response_code(500);
    echo '<h1>Seeder Error</h1><p>Missing schema.sql or seed.sql in database folder.</p>';
    exit;
}

if (!$run) {
    echo '<h1>Cakeouflage Web Seeder</h1>';
    echo '<p>This will rebuild schema and load demo data in the configured DB.</p>';
    echo '<p><strong>Database:</strong> ' . htmlspecialchars((string) Env::get('DB_NAME', Env::get('DB_DATABASE', 'unknown'))) . '</p>';
    echo '<p><a href="?token=' . urlencode((string)$provided) . '&run=1" style="padding:10px 14px;background:#80001F;color:#fff;text-decoration:none;border-radius:8px;">Run Seeder Now</a></p>';
    exit;
}

try {
    $pdo = Database::getConnection();
    $schemaSql = file_get_contents($schemaPath);
    $seedSql = file_get_contents($seedPath);

    if ($schemaSql === false || $seedSql === false) {
        throw new RuntimeException('Unable to read SQL files.');
    }

    foreach (splitSqlStatements($schemaSql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }

    foreach (splitSqlStatements($seedSql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }

    $counts = [
        'categories' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'products' => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'product_variants' => (int)$pdo->query('SELECT COUNT(*) FROM product_variants')->fetchColumn(),
        'product_images' => (int)$pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn(),
        'courses' => (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    ];

    echo '<h1>Seeder Completed</h1>';
    echo '<ul>';
    foreach ($counts as $name => $count) {
        echo '<li>' . htmlspecialchars($name) . ': <strong>' . $count . '</strong></li>';
    }
    echo '</ul>';
    echo '<p>Remove this file after successful seeding for security.</p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Seeder Failed</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}

/**
 * @return list<string>
 */
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
            if ($trimmed !== '' && !str_starts_with($trimmed, '--')) {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $tail = trim($buffer);
    if ($tail !== '' && !str_starts_with($tail, '--')) {
        $statements[] = $tail;
    }

    return $statements;
}
