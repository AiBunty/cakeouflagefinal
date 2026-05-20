<?php
/**
 * One-time production migration runner — 2026-05-20
 * Catalog versioning tables + schema/data patches.
 *
 * USAGE:
 *   https://cakeouflage.com/__deploy_migrate_20260520.php?token=<DEPLOY_TOKEN>
 *
 * SECURITY:
 *   - Token is compared using hash_equals() to prevent timing attacks.
 *   - DELETE this file from the server after a successful run.
 *   - Set DEPLOY_MIGRATE_TOKEN in .env before uploading.
 *
 * .env key required:
 *   DEPLOY_MIGRATE_TOKEN=<long-random-secret>
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(120);

// ── Load .env ────────────────────────────────────────────────────────────────
$envPath = __DIR__ . '/.env';
if (!is_file($envPath)) {
    http_response_code(500);
    exit("ERROR: .env not found.\n");
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') continue;
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $env[trim($k)] = trim($v);
}

// ── Token check ──────────────────────────────────────────────────────────────
$expectedToken = $env['DEPLOY_MIGRATE_TOKEN'] ?? '';
$providedToken = (string)($_GET['token'] ?? '');

if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit("403 Forbidden\n");
}

// ── DB connection ────────────────────────────────────────────────────────────
$host   = $env['DB_HOST']     ?? '';
$port   = $env['DB_PORT']     ?? '3306';
$user   = $env['DB_USER']     ?? $env['DB_USERNAME'] ?? '';
$pass   = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_NAME']     ?? $env['DB_DATABASE'] ?? '';

if ($host === '' || $user === '' || $dbName === '') {
    http_response_code(500);
    exit("ERROR: DB credentials missing in .env\n");
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit("ERROR: DB connection failed — " . $e->getMessage() . "\n");
}

// ── Load migration SQL ───────────────────────────────────────────────────────
$sqlFile = __DIR__ . '/database/migrations/2026-05-20-catalog-versioning-deploy.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    exit("ERROR: Migration file not found: database/migrations/2026-05-20-catalog-versioning-deploy.sql\n");
}

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') {
    http_response_code(500);
    exit("ERROR: Migration file is empty or unreadable.\n");
}

// ── Split and execute statements ─────────────────────────────────────────────
// PDO::MYSQL_ATTR_MULTI_STATEMENTS is enabled; use exec() for the full script.
// We split on ';' newline boundaries for readable per-statement reporting.

echo "=== Cakeouflage Production Migration — 2026-05-20 ===\n\n";
echo "Database : {$dbName}\n";
echo "Host     : {$host}\n";
echo "Started  : " . date('Y-m-d H:i:s') . "\n\n";

// Split by statement (naive but works for this file style)
$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql)),
    fn($s) => $s !== '' && !preg_match('/^--/', $s)
);

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($statements as $i => $stmt) {
    // Collapse statement to a one-line label
    $label = preg_replace('/\s+/', ' ', substr(trim($stmt), 0, 80));
    echo "  [{$i}] {$label}...\n";

    try {
        $affected = $pdo->exec($stmt);
        echo "       OK" . ($affected !== false ? " (rows affected: {$affected})" : "") . "\n";
        $passed++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Duplicate column / table already exists — treat as non-fatal skip
        if (
            str_contains($msg, 'Duplicate column name') ||
            str_contains($msg, "already exists") ||
            str_contains($msg, "1060") ||
            str_contains($msg, "1050")
        ) {
            echo "       SKIPPED (already applied: {$msg})\n";
            $skipped++;
        } else {
            echo "       FAILED — {$msg}\n";
            $failed++;
        }
    }
}

echo "\n=== Summary ===\n";
echo "Passed  : {$passed}\n";
echo "Skipped : {$skipped} (already applied)\n";
echo "Failed  : {$failed}\n\n";

if ($failed === 0) {
    echo "✓ Migration complete. DELETE this file from the server now.\n";
} else {
    echo "✗ Some statements failed — review output above before deleting this file.\n";
}

echo "\nFinished : " . date('Y-m-d H:i:s') . "\n";
