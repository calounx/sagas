<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\PDO;

use PDO;
use PDOStatement;
use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\QueryBuilderInterface;
use SagaManagerCore\Infrastructure\Database\Port\TransactionManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Port\ResultSetInterface;
use SagaManagerCore\Infrastructure\Database\ResultSet;
use SagaManagerCore\Infrastructure\Database\Exception\ConnectionException;
use SagaManagerCore\Infrastructure\Database\Exception\QueryException;

/**
 * PDO Database Connection Implementation
 *
 * Provides a PDO-based database adapter for future migration away from WordPress
 * or for standalone use cases. Supports MySQL/MariaDB, PostgreSQL, and SQLite.
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\PDO
 */
class PDOConnection implements DatabaseConnectionInterface
{
    private const DRIVER_NAME = 'pdo';
    private const SAGA_PREFIX = 'saga_';

    private ?PDO $pdo = null;
    private string $dsn;
    private ?string $username;
    private ?string $password;
    /** @var array<int, mixed> */
    private array $options;
    private string $tablePrefix;
    private string $databaseName;

    private bool $queryLogEnabled = false;
    /** @var array<array{query: string, bindings: array<mixed>, time: float}> */
    private array $queryLog = [];

    private ?PDOTransactionManager $transactionManager = null;
    private ?PDOSchemaManager $schemaManager = null;

    /**
     * @param string $dsn PDO Data Source Name
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param array<int, mixed> $options PDO options
     * @param string $tablePrefix Table prefix (e.g., 'wp_')
     */
    public function __construct(
        string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
        string $tablePrefix = ''
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->tablePrefix = $tablePrefix;

        // Parse database name from DSN
        $this->databaseName = $this->parseDatabaseName($dsn);

        // Default options for reliability
        $this->options = array_replace([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ], $options);
    }

    /**
     * Create a MySQL/MariaDB connection
     */
    public static function mysql(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        string $charset = 'utf8mb4',
        string $tablePrefix = ''
    ): self {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        return new self($dsn, $username, $password, [], $tablePrefix);
    }

    /**
     * Create a SQLite connection (useful for testing)
     */
    public static function sqlite(string $path, string $tablePrefix = ''): self
    {
        $dsn = $path === ':memory:' ? 'sqlite::memory:' : "sqlite:{$path}";
        return new self($dsn, null, null, [], $tablePrefix);
    }

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = new PDO(
                $this->dsn,
                $this->username,
                $this->password,
                $this->options
            );
        } catch (\PDOException $e) {
            throw ConnectionException::connectionFailed(
                self::DRIVER_NAME,
                $e->getMessage(),
                $e
            );
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->transactionManager = null;
        $this->schemaManager = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    public function ping(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function query(): QueryBuilderInterface
    {
        return new PDOQueryBuilder($this);
    }

    public function transaction(): TransactionManagerInterface
    {
        if ($this->transactionManager === null) {
            $this->transactionManager = new PDOTransactionManager($this);
        }
        return $this->transactionManager;
    }

    public function schema(): SchemaManagerInterface
    {
        if ($this->schemaManager === null) {
            $this->schemaManager = new PDOSchemaManager($this);
        }
        return $this->schemaManager;
    }

    public function getDriverName(): string
    {
        return self::DRIVER_NAME . '_' . $this->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getSagaTablePrefix(): string
    {
        return $this->tablePrefix . self::SAGA_PREFIX;
    }

    public function getFullTableName(string $tableName): string
    {
        return $this->getSagaTablePrefix() . $tableName;
    }

    public function raw(string $sql, array $bindings = []): ResultSetInterface
    {
        $pdo = $this->getPdo();
        $startTime = microtime(true);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $duration);

            // Determine if this is a SELECT query
            if ($this->isSelectQuery($sql)) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return ResultSet::fromRows($rows);
            }

            return ResultSet::fromWrite(
                $stmt->rowCount(),
                (int) $pdo->lastInsertId()
            );
        } catch (\PDOException $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logQuery($sql, $bindings, $duration);

            throw QueryException::queryFailed(
                $sql,
                $e->getMessage(),
                $bindings,
                $e->getCode()
            );
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->getPdo()->lastInsertId();
    }

    public function affectedRows(): int
    {
        // PDO doesn't store affected rows globally; use statement-level tracking
        return 0;
    }

    public function getLastError(): ?string
    {
        $errorInfo = $this->getPdo()->errorInfo();
        return $errorInfo[2] ?? null;
    }

    public function enableQueryLog(): void
    {
        $this->queryLogEnabled = true;
    }

    public function disableQueryLog(): void
    {
        $this->queryLogEnabled = false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    // =========================================================================
    // Internal Methods
    // =========================================================================

    /**
     * Get the PDO instance
     *
     * @internal Used by other PDO adapter components
     * @throws ConnectionException
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Prepare and execute a statement
     *
     * @internal
     * @param array<mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    private function parseDatabaseName(string $dsn): string
    {
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }
        if (preg_match('/sqlite:(.+)/', $dsn, $matches)) {
            return basename($matches[1], '.db');
        }
        return 'unknown';
    }

    private function isSelectQuery(string $sql): bool
    {
        $sql = ltrim($sql);
        $firstWord = strtoupper(substr($sql, 0, strpos($sql, ' ') ?: strlen($sql)));
        return in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true);
    }

    /**
     * @param array<mixed> $bindings
     */
    private function logQuery(string $sql, array $bindings, float $timeMs): void
    {
        if ($this->queryLogEnabled) {
            $this->queryLog[] = [
                'query' => $sql,
                'bindings' => $bindings,
                'time' => $timeMs,
            ];
        }

        // Performance warning for slow queries (>50ms target)
        if ($timeMs > 50.0) {
            error_log(sprintf(
                '[SAGA][PERF] Slow query (%.2fms): %s',
                $timeMs,
                substr($sql, 0, 200)
            ));
        }
    }
}
