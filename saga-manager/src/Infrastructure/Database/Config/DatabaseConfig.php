<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Config;

/**
 * Database Configuration Value Object
 *
 * Immutable configuration for database connections. Supports multiple drivers
 * with driver-specific configuration options.
 *
 * @example WordPress configuration:
 *   $config = DatabaseConfig::wordpress('saga_');
 *
 * @example PDO MySQL configuration:
 *   $config = DatabaseConfig::pdoMysql(
 *       host: 'localhost',
 *       database: 'saga_manager',
 *       username: 'root',
 *       password: 'secret',
 *       tablePrefix: 'wp_saga_'
 *   );
 *
 * @example In-memory for testing:
 *   $config = DatabaseConfig::memory('test_saga_');
 */
final readonly class DatabaseConfig
{
    /**
     * @param string $driver Driver type (wordpress, pdo, memory)
     * @param string $tablePrefix Table prefix
     * @param string|null $host Database host
     * @param int|null $port Database port
     * @param string|null $database Database name
     * @param string|null $username Database username
     * @param string|null $password Database password
     * @param string $charset Character set
     * @param string $collation Collation
     * @param string|null $socket Unix socket path
     * @param array<string, mixed> $options Additional driver options
     */
    public function __construct(
        public string $driver,
        public string $tablePrefix = '',
        public ?string $host = null,
        public ?int $port = null,
        public ?string $database = null,
        public ?string $username = null,
        public ?string $password = null,
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
        public ?string $socket = null,
        public array $options = [],
    ) {}

    /**
     * Create WordPress configuration
     *
     * Uses the global $wpdb connection. Table prefix is automatically
     * retrieved from WordPress if not specified.
     *
     * @param string|null $tablePrefix Additional prefix after wp_ (e.g., 'saga_')
     * @param array<string, mixed> $options Additional options
     * @return self
     */
    public static function wordpress(?string $tablePrefix = null, array $options = []): self
    {
        return new self(
            driver: 'wordpress',
            tablePrefix: $tablePrefix ?? '',
            options: $options,
        );
    }

    /**
     * Create PDO MySQL/MariaDB configuration
     *
     * @param string $host Database host
     * @param string $database Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param int $port Database port
     * @param string $tablePrefix Table prefix
     * @param string $charset Character set
     * @param string $collation Collation
     * @param array<string, mixed> $options PDO options
     * @return self
     */
    public static function pdoMysql(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        string $tablePrefix = '',
        string $charset = 'utf8mb4',
        string $collation = 'utf8mb4_unicode_ci',
        array $options = [],
    ): self {
        return new self(
            driver: 'pdo',
            tablePrefix: $tablePrefix,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            collation: $collation,
            options: array_merge([
                'pdo_driver' => 'mysql',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$collation}",
            ], $options),
        );
    }

    /**
     * Create PDO PostgreSQL configuration
     *
     * @param string $host Database host
     * @param string $database Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param int $port Database port
     * @param string $tablePrefix Table prefix
     * @param string $charset Character set
     * @param array<string, mixed> $options PDO options
     * @return self
     */
    public static function pdoPostgres(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 5432,
        string $tablePrefix = '',
        string $charset = 'UTF8',
        array $options = [],
    ): self {
        return new self(
            driver: 'pdo',
            tablePrefix: $tablePrefix,
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            charset: $charset,
            options: array_merge([
                'pdo_driver' => 'pgsql',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ], $options),
        );
    }

    /**
     * Create PDO SQLite configuration
     *
     * @param string $path Path to SQLite database file (or ':memory:')
     * @param string $tablePrefix Table prefix
     * @param array<string, mixed> $options PDO options
     * @return self
     */
    public static function pdoSqlite(
        string $path = ':memory:',
        string $tablePrefix = '',
        array $options = [],
    ): self {
        return new self(
            driver: 'pdo',
            tablePrefix: $tablePrefix,
            database: $path,
            options: array_merge([
                'pdo_driver' => 'sqlite',
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ], $options),
        );
    }

    /**
     * Create in-memory configuration for testing
     *
     * @param string $tablePrefix Table prefix
     * @param array<string, mixed> $options Additional options
     * @return self
     */
    public static function memory(string $tablePrefix = '', array $options = []): self
    {
        return new self(
            driver: 'memory',
            tablePrefix: $tablePrefix,
            options: $options,
        );
    }

    /**
     * Create configuration from array
     *
     * @param array<string, mixed> $config Configuration array
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self(
            driver: $config['driver'] ?? 'memory',
            tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? '',
            host: $config['host'] ?? null,
            port: isset($config['port']) ? (int) $config['port'] : null,
            database: $config['database'] ?? $config['dbname'] ?? null,
            username: $config['username'] ?? $config['user'] ?? null,
            password: $config['password'] ?? $config['pass'] ?? null,
            charset: $config['charset'] ?? 'utf8mb4',
            collation: $config['collation'] ?? 'utf8mb4_unicode_ci',
            socket: $config['socket'] ?? null,
            options: $config['options'] ?? [],
        );
    }

    /**
     * Get the PDO DSN string
     *
     * @return string
     * @throws \InvalidArgumentException If driver is not PDO
     */
    public function getDsn(): string
    {
        if ($this->driver !== 'pdo') {
            throw new \InvalidArgumentException(
                'DSN is only available for PDO driver'
            );
        }

        $pdoDriver = $this->options['pdo_driver'] ?? 'mysql';

        return match ($pdoDriver) {
            'mysql' => $this->getMysqlDsn(),
            'pgsql' => $this->getPostgresDsn(),
            'sqlite' => $this->getSqliteDsn(),
            default => throw new \InvalidArgumentException(
                "Unsupported PDO driver: {$pdoDriver}"
            ),
        };
    }

    /**
     * Get PDO options for connection
     *
     * @return array<int, mixed>
     */
    public function getPdoOptions(): array
    {
        $options = [];

        foreach ($this->options as $key => $value) {
            if (is_int($key)) {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Get a specific option value
     *
     * @template T
     * @param string $key Option key
     * @param T $default Default value
     * @return T|mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Check if an option exists
     *
     * @param string $key Option key
     * @return bool
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Create a new instance with modified options
     *
     * @param array<string, mixed> $options Options to merge
     * @return self
     */
    public function withOptions(array $options): self
    {
        return new self(
            driver: $this->driver,
            tablePrefix: $this->tablePrefix,
            host: $this->host,
            port: $this->port,
            database: $this->database,
            username: $this->username,
            password: $this->password,
            charset: $this->charset,
            collation: $this->collation,
            socket: $this->socket,
            options: array_merge($this->options, $options),
        );
    }

    /**
     * Create a new instance with a different table prefix
     *
     * @param string $tablePrefix New table prefix
     * @return self
     */
    public function withTablePrefix(string $tablePrefix): self
    {
        return new self(
            driver: $this->driver,
            tablePrefix: $tablePrefix,
            host: $this->host,
            port: $this->port,
            database: $this->database,
            username: $this->username,
            password: $this->password,
            charset: $this->charset,
            collation: $this->collation,
            socket: $this->socket,
            options: $this->options,
        );
    }

    /**
     * Export configuration as array (passwords excluded)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'table_prefix' => $this->tablePrefix,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'socket' => $this->socket,
            // Password intentionally excluded for security
        ];
    }

    private function getMysqlDsn(): string
    {
        $dsn = "mysql:";
        $parts = [];

        if ($this->socket !== null) {
            $parts[] = "unix_socket={$this->socket}";
        } else {
            if ($this->host !== null) {
                $parts[] = "host={$this->host}";
            }
            if ($this->port !== null) {
                $parts[] = "port={$this->port}";
            }
        }

        if ($this->database !== null) {
            $parts[] = "dbname={$this->database}";
        }

        $parts[] = "charset={$this->charset}";

        return $dsn . implode(';', $parts);
    }

    private function getPostgresDsn(): string
    {
        $dsn = "pgsql:";
        $parts = [];

        if ($this->host !== null) {
            $parts[] = "host={$this->host}";
        }
        if ($this->port !== null) {
            $parts[] = "port={$this->port}";
        }
        if ($this->database !== null) {
            $parts[] = "dbname={$this->database}";
        }

        return $dsn . implode(';', $parts);
    }

    private function getSqliteDsn(): string
    {
        return "sqlite:{$this->database}";
    }
}
