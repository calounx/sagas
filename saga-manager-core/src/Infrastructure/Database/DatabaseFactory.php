<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database;

use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Adapter\WordPress\WordPressConnection;
use SagaManagerCore\Infrastructure\Database\Adapter\PDO\PDOConnection;
use SagaManagerCore\Infrastructure\Database\Adapter\InMemory\InMemoryConnection;

/**
 * Database Factory
 *
 * Creates database connections based on configuration or environment.
 * Supports WordPress, PDO, and InMemory adapters.
 *
 * Usage:
 * ```php
 * // Auto-detect (WordPress if available, otherwise PDO)
 * $connection = DatabaseFactory::create();
 *
 * // Explicit WordPress
 * $connection = DatabaseFactory::wordpress();
 *
 * // PDO MySQL
 * $connection = DatabaseFactory::mysql('localhost', 'saga_db', 'user', 'pass');
 *
 * // InMemory for testing
 * $connection = DatabaseFactory::memory();
 * ```
 *
 * @package SagaManagerCore\Infrastructure\Database
 */
final class DatabaseFactory
{
    /**
     * Create a database connection based on environment
     *
     * Automatically detects WordPress environment and uses appropriate adapter.
     */
    public static function create(): DatabaseConnectionInterface
    {
        // Check if running in WordPress environment
        if (defined('ABSPATH') && function_exists('get_option')) {
            return self::wordpress();
        }

        // Check for PDO MySQL configuration in environment
        $host = getenv('SAGA_DB_HOST') ?: getenv('DB_HOST');
        $database = getenv('SAGA_DB_NAME') ?: getenv('DB_NAME');
        $username = getenv('SAGA_DB_USER') ?: getenv('DB_USER');
        $password = getenv('SAGA_DB_PASSWORD') ?: getenv('DB_PASSWORD');

        if ($host && $database && $username) {
            return self::mysql($host, $database, $username, $password ?: '');
        }

        // Fallback to in-memory for testing
        return self::memory();
    }

    /**
     * Create a WordPress database connection
     *
     * Uses the global $wpdb object for all operations.
     */
    public static function wordpress(): WordPressConnection
    {
        $connection = new WordPressConnection();
        $connection->connect();
        return $connection;
    }

    /**
     * Create a MySQL/MariaDB PDO connection
     */
    public static function mysql(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        string $charset = 'utf8mb4',
        string $tablePrefix = ''
    ): PDOConnection {
        $connection = PDOConnection::mysql(
            $host,
            $database,
            $username,
            $password,
            $port,
            $charset,
            $tablePrefix
        );
        $connection->connect();
        return $connection;
    }

    /**
     * Create a SQLite PDO connection
     *
     * @param string $path Path to SQLite file, or ':memory:' for in-memory
     */
    public static function sqlite(string $path, string $tablePrefix = ''): PDOConnection
    {
        $connection = PDOConnection::sqlite($path, $tablePrefix);
        $connection->connect();
        return $connection;
    }

    /**
     * Create an in-memory connection for testing
     *
     * No actual database is used - all data is stored in PHP arrays.
     */
    public static function memory(string $tablePrefix = 'test_'): InMemoryConnection
    {
        $connection = new InMemoryConnection($tablePrefix);
        $connection->connect();
        return $connection;
    }

    /**
     * Create a connection from configuration array
     *
     * @param array{
     *     driver: string,
     *     host?: string,
     *     database?: string,
     *     username?: string,
     *     password?: string,
     *     port?: int,
     *     charset?: string,
     *     prefix?: string,
     *     path?: string
     * } $config
     */
    public static function fromConfig(array $config): DatabaseConnectionInterface
    {
        $driver = $config['driver'] ?? 'wordpress';

        return match ($driver) {
            'wordpress', 'wpdb' => self::wordpress(),
            'mysql', 'mariadb' => self::mysql(
                $config['host'] ?? 'localhost',
                $config['database'] ?? '',
                $config['username'] ?? '',
                $config['password'] ?? '',
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4',
                $config['prefix'] ?? ''
            ),
            'sqlite' => self::sqlite(
                $config['path'] ?? ':memory:',
                $config['prefix'] ?? ''
            ),
            'memory', 'inmemory', 'test' => self::memory(
                $config['prefix'] ?? 'test_'
            ),
            default => throw new \InvalidArgumentException("Unknown database driver: {$driver}"),
        };
    }

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
