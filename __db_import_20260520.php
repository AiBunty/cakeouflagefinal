<?php
/**
 * __db_import_20260520.php
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE-TIME: Token-secured full database import runner.
 * Reads cakeouflage_deploy.sql from the same directory, drops all existing
 * tables and reimports the entire local dataset (schema + data).
 *
 * USAGE:
 *   https://cakeouflage.com/__db_import_20260520.php?token=<DEPLOY_MIGRATE_TOKEN>
 *
 * SECURITY:
 *   - Requires DEPLOY_MIGRATE_TOKEN in .env (64-char hex)
 *   - Uses hash_equals() for timing-safe comparison
 *   - DELETE THIS FILE from the server immediately after successful import
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('display_errors', '0');

header('Content-Type: text/plain; charset=UTF-8');

// ─── Load .env manually (no framework bootstrap) ──────────────────────────

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Strip surrounding quotes
        if (strlen($value) >= 2 && (
            ($value[0] === '"'  && $value[-1] === '"')  ||
            ($value[0] === "'"  && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        $vars[$key] = $value;
    }
    return $vars;
}

$envPath = __DIR__ . '/.env';
$env     = loadEnvFile($envPath);

// ─── Token validation ──────────────────────────────────────────────────────

$expectedToken = $env['DEPLOY_MIGRATE_TOKEN'] ?? '';
$givenToken    = (string)($_GET['token'] ?? '');

if ($expectedToken === '' || $givenToken === '' || !hash_equals($expectedToken, $givenToken)) {
    http_response_code(403);
    echo "403 Forbidden — invalid or missing token.\n";
    exit(1);
}

// ─── Locate SQL dump ──────────────────────────────────────────────────────

$sqlFile = __DIR__ . '/cakeouflage_deploy.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    echo "ERROR: cakeouflage_deploy.sql not found in " . __DIR__ . "\n";
    echo "Upload the dump file first, then retry.\n";
    exit(1);
}

$sqlSize = number_format(filesize($sqlFile) / 1024 / 1024, 2);
echo "=== Cakeouflage DB Import Runner — " . date('Y-m-d H:i:s') . " ===\n\n";
echo "SQL file : cakeouflage_deploy.sql ({$sqlSize} MB)\n\n";
flush();

// ─── DB connection ─────────────────────────────────────────────────────────

$dbHost = $env['DB_HOST'] ?? '';
$dbPort = (int)($env['DB_PORT'] ?? 3306);
$dbUser = $env['DB_USER'] ?? $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_NAME'] ?? $env['DB_DATABASE'] ?? '';

if ($dbHost === '' || $dbUser === '' || $dbName === '') {
    http_response_code(500);
    echo "ERROR: DB credentials missing from .env (need DB_HOST, DB_USER, DB_PASSWORD, DB_NAME)\n";
    exit(1);
}

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    // Select the target DB
    $pdo->exec("USE `{$dbName}`");
    echo "DB       : connected to {$dbHost} / {$dbName}\n\n";
    flush();
} catch (\PDOException $e) {
    http_response_code(500);
    echo "ERROR: DB connection failed — " . $e->getMessage() . "\n";
    exit(1);
}

// ─── SQL parser: split dump into individual statements ─────────────────────
// Handles semicolons inside string literals, backtick identifiers, and comments.

function parseSqlStatements(string $sql): array
{
    $statements = [];
    $current    = '';
    $inString   = false;
    $stringChar = '';
    $len        = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];

        if ($inString) {
            if ($c === '\\') {
                // Escape sequence — consume next char verbatim
                $current .= $c . ($sql[++$i] ?? '');
                continue;
            }
            if ($c === $stringChar) {
                $inString = false;
            }
            $current .= $c;
            continue;
        }

        // Outside a string
        if ($c === "'" || $c === '"' || $c === '`') {
            $inString   = true;
            $stringChar = $c;
            $current   .= $c;
            continue;
        }

        // `--` line comment
        if ($c === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $current .= "\n";
            continue;
        }

        // `#` line comment
        if ($c === '#') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $current .= "\n";
            continue;
        }

        // `/* ... */` block comment — skip, BUT preserve `/*!...*/` versioned comments
        // (mysqldump uses /*!40101 SET FOREIGN_KEY_CHECKS=0 */; etc. — MySQL executes these)
        if ($c === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
            if (isset($sql[$i + 2]) && $sql[$i + 2] === '!') {
                // Versioned conditional comment — pass through as regular SQL
                $current .= $c;
                continue;
            }
            // Regular block comment — skip entirely
            $i += 2;
            while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++; // skip closing /
            continue;
        }

        // Statement terminator
        if ($c === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            continue;
        }

        $current .= $c;
    }

    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

// ─── Execute ───────────────────────────────────────────────────────────────

echo "Reading SQL file…\n";
flush();

$sqlContent = file_get_contents($sqlFile);
if ($sqlContent === false) {
    http_response_code(500);
    echo "ERROR: Could not read cakeouflage_deploy.sql\n";
    exit(1);
}

echo "Parsing statements…\n";
flush();

$statements = parseSqlStatements($sqlContent);
$total      = count($statements);
echo "Found {$total} statements. Executing…\n\n";
flush();

$executed = 0;
$skipped  = 0;
$failed   = 0;
$errors   = [];

foreach ($statements as $idx => $stmt) {
    $upper = strtoupper(ltrim($stmt));

    // Skip empty / pure-comment artefacts
    if ($stmt === '' || $stmt === ';') {
        $skipped++;
        continue;
    }

    // Skip SET statements that conflict with our session settings
    // (e.g. SET @@SESSION.SQL_MODE — leave FK checks to the dump itself)

    try {
        $pdo->exec($stmt);
        $executed++;

        // Progress every 50 statements
        if ($executed % 50 === 0) {
            $pct = round(($idx + 1) / $total * 100);
            echo "  [{$pct}%] {$executed} executed, {$failed} failed…\n";
            flush();
        }
    } catch (\PDOException $e) {
        $msg    = $e->getMessage();
        $failed++;

        // Non-fatal: table/column already exists
        $isKnownSkippable = (
            str_contains($msg, 'already exists') ||
            str_contains($msg, 'Duplicate entry') ||
            str_contains($msg, 'Unknown system variable')
        );

        $prefix  = $isKnownSkippable ? '  [WARN ] ' : '  [ERROR] ';
        $preview = substr($stmt, 0, 80);
        $line    = "{$prefix}{$msg} | SQL: {$preview}…";
        $errors[] = $line;
        echo $line . "\n";
        flush();

        if (!$isKnownSkippable) {
            // Fatal — stop import
            echo "\nFATAL error encountered. Import aborted after {$executed} statements.\n";
            echo "Fix the issue and retry.\n";
            exit(1);
        }
        // Known-skippable: continue
        $failed--; // don't count warns as failures
        $skipped++;
    }
}

// ─── Summary ──────────────────────────────────────────────────────────────

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo " IMPORT COMPLETE\n";
echo "═══════════════════════════════════════════════\n";
echo " Executed : {$executed}\n";
echo " Skipped  : {$skipped}\n";
echo " Failed   : {$failed}\n";
echo "═══════════════════════════════════════════════\n\n";

if ($failed === 0) {
    echo "✓ All statements executed successfully.\n\n";
} else {
    echo "✗ {$failed} statement(s) failed — review errors above.\n\n";
}

echo "⚠  DELETE THIS FILE AND cakeouflage_deploy.sql FROM THE SERVER NOW.\n";
echo "   They expose your database and credentials.\n";
