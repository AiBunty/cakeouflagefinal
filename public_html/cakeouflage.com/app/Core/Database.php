<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database — singleton PDO wrapper with convenience query helpers.
 * Use Database::getInstance() to get the shared instance, then call
 * fetchAll(), fetchOne(), execute(), or query() on it.
 * Use Database::getConnection() to get the raw PDO when needed.
 */
final class Database
{
    /** @var PDO|null */
    private static $pdo = null;
    /** @var self|null */
    private static $instance = null;

    private function __construct() {}

    // ------------------------------------------------------------------
    // Singleton access
    // ------------------------------------------------------------------

    /** Return the shared Database wrapper instance. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::getConnection(); // ensure PDO is initialised
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Return the raw PDO connection (creates it if not yet open). */
    public static function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host     = Env::get('DB_HOST',     'localhost');
        $port     = Env::get('DB_PORT',     '3306');
        $dbName   = Env::get('DB_NAME',     '') ?: Env::get('DB_DATABASE', 'cakeouflage');
        $user     = Env::get('DB_USER',     '') ?: Env::get('DB_USERNAME', 'root');
        $password = Env::get('DB_PASSWORD', '');
        $charset  = Env::get('DB_CHARSET',  'utf8mb4');
        $connectTimeout = max(2, (int)Env::get('DB_CONNECT_TIMEOUT', '5'));

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => $connectTimeout,
            ]);
        } catch (PDOException $e) {
            // Throw so callers can handle gracefully — do not leak credentials in message
            $msg = (defined('APP_DEBUG') && APP_DEBUG)
                ? $e->getMessage()
                : 'Database connection failed.';
            throw new \RuntimeException($msg, (int)$e->getCode(), $e);
        }

        return self::$pdo;
    }

    // ------------------------------------------------------------------
    // Query helpers
    // ------------------------------------------------------------------

    /**
     * Run a SELECT and return all matching rows as associative arrays.
     *
     * @param  array<int|string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->run($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Run a SELECT and return the first row, or null if no rows matched.
     *
     * @param  array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->run($sql, $params);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Run a SELECT and return a single scalar value from the first column of
     * the first row, or null.
     *
     * @param  array<int|string,mixed> $params
     */
    public function fetchScalar(string $sql, array $params = [])
    {
        $stmt = $this->run($sql, $params);
        $val  = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }

    /**
     * Execute an INSERT / UPDATE / DELETE and return the number of affected rows.
     *
     * @param  array<int|string,mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Execute an INSERT and return the last insert ID as an integer.
     *
     * @param  array<int|string,mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) self::$pdo->lastInsertId();
    }

    /**
     * Convenience alias for CategoryService / legacy code that calls $db->query().
     * Returns all rows as associative arrays.
     *
     * @param  array<int|string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->fetchAll($sql, $params);
    }

    // ------------------------------------------------------------------
    // Transaction helpers
    // ------------------------------------------------------------------

    public function beginTransaction(): void   { self::$pdo->beginTransaction(); }
    public function commit(): void             { self::$pdo->commit(); }
    public function rollback(): void           { self::$pdo->rollBack(); }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /** @param array<int|string,mixed> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        $pdo  = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
