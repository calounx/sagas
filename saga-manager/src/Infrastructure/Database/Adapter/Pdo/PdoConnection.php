<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\Pdo;

use SagaManager\Infrastructure\Database\Contract\ConnectionInterface;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * PDO Connection
 *
 * Manages PDO database connections with lazy initialization and reconnection support.
 *
 * @example
 *   $config = DatabaseConfig::pdoMysql('localhost', 'mydb', 'user', 'pass');
 *   $connection = new PdoConnection($config);
 *   $connection->connect();
 *   $pdo = $connection->getNativeConnection();
 */
final class PdoConnection implements ConnectionInterface
{
    private ?\PDO $pdo = null;
    private ?\DateTimeImmutable $connectedAt = null;
    private int $queriesExecuted = 0;
    private float $totalTime = 0.0;

    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}

    public function getNativeConnection(): \PDO
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->pdo;
    }

    public function isConnected(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = new \PDO(
                $this->config->getDsn(),
                $this->config->username,
                $this->config->password,
                $this->config->getPdoOptions()
            );

            $this->connectedAt = new \DateTimeImmutable();

        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to connect to database: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connectedAt = null;
    }

    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect();
    }

    public function getDriverName(): string
    {
        if ($this->pdo === null) {
            return $this->config->options['pdo_driver'] ?? 'unknown';
        }

        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function getServerVersion(): string
    {
        if (!$this->isConnected()) {
            return 'not connected';
        }

        return $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    public function quote(string $value): string
    {
        return $this->getNativeConnection()->quote($value);
    }

    public function quoteIdentifier(string $identifier): string
    {
        $driver = $this->getDriverName();

        // PostgreSQL uses double quotes
        if ($driver === 'pgsql') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        // MySQL/MariaDB use backticks
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function getStats(): array
    {
        return [
            'queries_executed' => $this->queriesExecuted,
            'total_time' => $this->totalTime,
            'connected_at' => $this->connectedAt,
        ];
    }

    public function ping(): bool
    {
        try {
            if ($this->pdo === null) {
                return false;
            }

            $this->pdo->query('SELECT 1');
            return true;

        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Increment query counter and add execution time
     *
     * @param float $time Execution time in seconds
     * @internal
     */
    public function recordQuery(float $time): void
    {
        $this->queriesExecuted++;
        $this->totalTime += $time;
    }

    /**
     * Prepare a statement
     *
     * @param string $sql SQL query
     * @return \PDOStatement
     * @throws DatabaseException On prepare failure
     */
    public function prepare(string $sql): \PDOStatement
    {
        try {
            $stmt = $this->getNativeConnection()->prepare($sql);

            if ($stmt === false) {
                throw new DatabaseException(
                    'Failed to prepare statement: ' . implode(' ', $this->pdo->errorInfo())
                );
            }

            return $stmt;

        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to prepare statement: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute a query and return the statement
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $params Parameters
     * @return \PDOStatement
     * @throws DatabaseException On execution failure
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);

        try {
            $stmt = $this->prepare($sql);

            if (!empty($params)) {
                $this->bindParams($stmt, $params);
            }

            $stmt->execute();

            $this->recordQuery(microtime(true) - $start);

            return $stmt;

        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Query execution failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bind parameters to a statement
     *
     * @param \PDOStatement $stmt
     * @param array<int|string, mixed> $params
     */
    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $paramKey = is_int($key) ? $key + 1 : $key;
            $type = $this->getPdoType($value);
            $stmt->bindValue($paramKey, $value, $type);
        }
    }

    /**
     * Get PDO type for a value
     *
     * @param mixed $value
     * @return int PDO::PARAM_* constant
     */
    private function getPdoType(mixed $value): int
    {
        return match (true) {
            is_null($value) => \PDO::PARAM_NULL,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_int($value) => \PDO::PARAM_INT,
            default => \PDO::PARAM_STR,
        };
    }
}
