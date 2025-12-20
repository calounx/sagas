<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

/**
 * Connection Pool Manager
 *
 * Manages database connections for WordPress/MariaDB.
 * Note: WordPress uses a single persistent connection via $wpdb.
 * This class provides connection state tracking, health monitoring,
 * and connection lifecycle management.
 *
 * For true connection pooling with multiple connections, consider
 * using external solutions like ProxySQL or MariaDB MaxScale.
 */
final class ConnectionPool
{
    private const MAX_CONNECTIONS = 10;
    private const IDLE_TIMEOUT_SECONDS = 300; // 5 minutes
    private const HEALTH_CHECK_INTERVAL = 60; // 1 minute

    /** @var array<string, ConnectionState> Tracked connections */
    private array $connections = [];

    /** @var array<string, float> Last activity timestamp per connection */
    private array $lastActivity = [];

    /** @var array<string, int> Query count per connection */
    private array $queryCount = [];

    /** @var int Total connections created */
    private int $connectionsCreated = 0;

    /** @var int Total connections closed */
    private int $connectionsClosed = 0;

    private \wpdb $wpdb;
    private float $lastHealthCheck = 0;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->registerPrimaryConnection();
    }

    /**
     * Get a connection for query execution
     *
     * WordPress uses a single connection, so this returns the primary connection
     * after validating it's healthy.
     *
     * @return \wpdb Database connection
     * @throws \RuntimeException If connection is unhealthy
     */
    public function acquire(): \wpdb
    {
        $this->ensureHealthy();
        $this->recordActivity('primary');

        return $this->wpdb;
    }

    /**
     * Release a connection back to the pool
     *
     * For WordPress, this is a no-op but tracks connection usage.
     *
     * @param \wpdb $connection The connection to release
     */
    public function release(\wpdb $connection): void
    {
        // Track that the connection completed its work
        $this->recordActivity('primary');
    }

    /**
     * Execute a callback with a managed connection
     *
     * Automatically acquires and releases connection.
     *
     * @param callable $callback Function receiving \wpdb
     * @return mixed Callback result
     */
    public function withConnection(callable $callback): mixed
    {
        $connection = $this->acquire();

        try {
            return $callback($connection);
        } finally {
            $this->release($connection);
        }
    }

    /**
     * Get connection pool statistics
     *
     * @return array{
     *     active_connections: int,
     *     total_created: int,
     *     total_closed: int,
     *     queries_executed: int,
     *     connection_health: string,
     *     uptime_seconds: float
     * }
     */
    public function getStats(): array
    {
        $primaryState = $this->connections['primary'] ?? null;

        return [
            'active_connections' => count($this->connections),
            'total_created' => $this->connectionsCreated,
            'total_closed' => $this->connectionsClosed,
            'queries_executed' => array_sum($this->queryCount),
            'connection_health' => $this->isHealthy() ? 'healthy' : 'unhealthy',
            'uptime_seconds' => $primaryState
                ? microtime(true) - $primaryState->createdAt
                : 0.0,
            'last_activity' => $this->lastActivity['primary'] ?? 0,
            'idle_seconds' => isset($this->lastActivity['primary'])
                ? microtime(true) - $this->lastActivity['primary']
                : 0,
        ];
    }

    /**
     * Check if the connection pool is healthy
     *
     * @return bool True if connections are healthy
     */
    public function isHealthy(): bool
    {
        // Throttle health checks
        $now = microtime(true);
        if ($now - $this->lastHealthCheck < self::HEALTH_CHECK_INTERVAL) {
            return $this->connections['primary']->isHealthy ?? true;
        }

        $this->lastHealthCheck = $now;

        try {
            // Simple ping query
            $result = $this->wpdb->get_var('SELECT 1');
            $isHealthy = $result === '1';

            if (isset($this->connections['primary'])) {
                $this->connections['primary']->isHealthy = $isHealthy;
            }

            return $isHealthy;
        } catch (\Throwable $e) {
            error_log('[SAGA][CONNECTION] Health check failed: ' . $e->getMessage());

            if (isset($this->connections['primary'])) {
                $this->connections['primary']->isHealthy = false;
            }

            return false;
        }
    }

    /**
     * Attempt to reconnect if connection is lost
     *
     * @return bool True if reconnection successful
     */
    public function reconnect(): bool
    {
        try {
            // WordPress db_connect method
            $this->wpdb->db_connect(false);

            if (isset($this->connections['primary'])) {
                $this->connections['primary']->isHealthy = true;
                $this->connections['primary']->reconnectCount++;
            }

            error_log('[SAGA][CONNECTION] Reconnected successfully');
            return true;
        } catch (\Throwable $e) {
            error_log('[SAGA][CONNECTION] Reconnection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Close idle connections
     *
     * For WordPress single connection, this is a no-op but can be extended
     * for multi-connection scenarios.
     *
     * @return int Number of connections closed
     */
    public function cleanupIdle(): int
    {
        $closed = 0;
        $now = microtime(true);

        foreach ($this->lastActivity as $id => $lastActive) {
            if ($id === 'primary') {
                continue; // Never close primary WordPress connection
            }

            if ($now - $lastActive > self::IDLE_TIMEOUT_SECONDS) {
                $this->closeConnection($id);
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * Get current connection count
     *
     * @return int Number of active connections
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get query count for monitoring
     *
     * @return int Total queries executed
     */
    public function getTotalQueryCount(): int
    {
        return array_sum($this->queryCount);
    }

    /**
     * Increment query counter
     *
     * Call this after each query execution for accurate metrics.
     */
    public function recordQuery(): void
    {
        $this->queryCount['primary'] = ($this->queryCount['primary'] ?? 0) + 1;
        $this->recordActivity('primary');
    }

    /**
     * Get connection configuration
     *
     * @return array<string, mixed> Connection settings
     */
    public function getConfiguration(): array
    {
        return [
            'max_connections' => self::MAX_CONNECTIONS,
            'idle_timeout' => self::IDLE_TIMEOUT_SECONDS,
            'health_check_interval' => self::HEALTH_CHECK_INTERVAL,
            'db_host' => DB_HOST,
            'db_name' => DB_NAME,
            'db_charset' => DB_CHARSET,
        ];
    }

    /**
     * Register the primary WordPress connection
     */
    private function registerPrimaryConnection(): void
    {
        $this->connections['primary'] = new ConnectionState(
            id: 'primary',
            createdAt: microtime(true),
            isHealthy: true,
            reconnectCount: 0
        );

        $this->lastActivity['primary'] = microtime(true);
        $this->queryCount['primary'] = 0;
        $this->connectionsCreated++;
    }

    /**
     * Record connection activity
     */
    private function recordActivity(string $connectionId): void
    {
        $this->lastActivity[$connectionId] = microtime(true);
    }

    /**
     * Ensure the connection is healthy before use
     *
     * @throws \RuntimeException If connection cannot be established
     */
    private function ensureHealthy(): void
    {
        if (!$this->isHealthy()) {
            if (!$this->reconnect()) {
                throw new \RuntimeException(
                    'Database connection is unhealthy and reconnection failed'
                );
            }
        }
    }

    /**
     * Close a specific connection
     */
    private function closeConnection(string $connectionId): void
    {
        unset(
            $this->connections[$connectionId],
            $this->lastActivity[$connectionId],
            $this->queryCount[$connectionId]
        );

        $this->connectionsClosed++;
    }
}

/**
 * Connection State Value Object
 */
final class ConnectionState
{
    public function __construct(
        public readonly string $id,
        public readonly float $createdAt,
        public bool $isHealthy,
        public int $reconnectCount
    ) {}
}
