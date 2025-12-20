<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database;

use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Infrastructure\Database\Adapter\WordPress\WordPressDatabaseAdapter;
use SagaManager\Infrastructure\Database\Adapter\Pdo\PdoDatabaseAdapter;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryDatabaseAdapter;

/**
 * Database Factory
 *
 * Creates the appropriate database adapter based on configuration.
 * Supports WordPress, PDO (MySQL/MariaDB, PostgreSQL, SQLite), and In-Memory adapters.
 *
 * @example WordPress (default in WordPress context):
 *   $db = DatabaseFactory::create('wordpress');
 *   // or with saga prefix
 *   $db = DatabaseFactory::create('wordpress', ['table_prefix' => 'saga_']);
 *
 * @example PDO MySQL:
 *   $db = DatabaseFactory::create('pdo', [
 *       'pdo_driver' => 'mysql',
 *       'host' => 'localhost',
 *       'database' => 'saga_manager',
 *       'username' => 'root',
 *       'password' => 'secret',
 *       'table_prefix' => 'wp_saga_',
 *   ]);
 *
 * @example In-Memory (for testing):
 *   $db = DatabaseFactory::create('memory');
 *
 * @example From environment:
 *   $db = DatabaseFactory::createFromEnvironment();
 */
final class DatabaseFactory
{
    /**
     * Create a database adapter
     *
     * @param string $driver Driver type: 'wordpress', 'pdo', 'memory'
     * @param array<string, mixed> $config Configuration options
     * @return DatabaseInterface
     *
     * @throws \InvalidArgumentException If driver is unknown
     */
    public static function create(string $driver, array $config = []): DatabaseInterface
    {
        return match ($driver) {
            'wordpress' => self::createWordPress($config),
            'pdo' => self::createPdo($config),
            'memory', 'inmemory', 'in_memory' => self::createInMemory($config),
            default => throw new \InvalidArgumentException("Unknown database driver: {$driver}"),
        };
    }

    /**
     * Create adapter from DatabaseConfig
     *
     * @param DatabaseConfig $config
     * @return DatabaseInterface
     */
    public static function createFromConfig(DatabaseConfig $config): DatabaseInterface
    {
        return match ($config->driver) {
            'wordpress' => new WordPressDatabaseAdapter($config),
            'pdo' => new PdoDatabaseAdapter($config),
            'memory' => new InMemoryDatabaseAdapter($config),
            default => throw new \InvalidArgumentException("Unknown driver: {$config->driver}"),
        };
    }

    /**
     * Create adapter from environment variables
     *
     * Supports the following environment variables:
     * - SAGA_DB_DRIVER: 'wordpress', 'pdo', 'memory' (default: 'wordpress')
     * - SAGA_DB_HOST: Database host
     * - SAGA_DB_PORT: Database port
     * - SAGA_DB_NAME: Database name
     * - SAGA_DB_USER: Database username
     * - SAGA_DB_PASS: Database password
     * - SAGA_DB_PREFIX: Table prefix (e.g., 'saga_')
     * - SAGA_DB_CHARSET: Character set (default: 'utf8mb4')
     *
     * @return DatabaseInterface
     */
    public static function createFromEnvironment(): DatabaseInterface
    {
        $driver = getenv('SAGA_DB_DRIVER') ?: 'wordpress';

        $config = [
            'host' => getenv('SAGA_DB_HOST') ?: null,
            'port' => getenv('SAGA_DB_PORT') ? (int) getenv('SAGA_DB_PORT') : null,
            'database' => getenv('SAGA_DB_NAME') ?: null,
            'username' => getenv('SAGA_DB_USER') ?: null,
            'password' => getenv('SAGA_DB_PASS') ?: null,
            'table_prefix' => getenv('SAGA_DB_PREFIX') ?: '',
            'charset' => getenv('SAGA_DB_CHARSET') ?: 'utf8mb4',
        ];

        // Filter out null values
        $config = array_filter($config, fn($v) => $v !== null);

        return self::create($driver, $config);
    }

    /**
     * Create WordPress database adapter
     *
     * @param array<string, mixed> $config
     * @return WordPressDatabaseAdapter
     */
    public static function createWordPress(array $config = []): WordPressDatabaseAdapter
    {
        $dbConfig = DatabaseConfig::wordpress(
            tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? null,
            options: $config['options'] ?? [],
        );

        return new WordPressDatabaseAdapter($dbConfig);
    }

    /**
     * Create PDO database adapter
     *
     * @param array<string, mixed> $config
     * @return PdoDatabaseAdapter
     */
    public static function createPdo(array $config): PdoDatabaseAdapter
    {
        $pdoDriver = $config['pdo_driver'] ?? $config['driver'] ?? 'mysql';

        $dbConfig = match ($pdoDriver) {
            'mysql', 'mariadb' => DatabaseConfig::pdoMysql(
                host: $config['host'] ?? 'localhost',
                database: $config['database'] ?? $config['dbname'] ?? '',
                username: $config['username'] ?? $config['user'] ?? 'root',
                password: $config['password'] ?? $config['pass'] ?? '',
                port: (int) ($config['port'] ?? 3306),
                tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? '',
                charset: $config['charset'] ?? 'utf8mb4',
                collation: $config['collation'] ?? 'utf8mb4_unicode_ci',
                options: $config['options'] ?? [],
            ),
            'pgsql', 'postgres', 'postgresql' => DatabaseConfig::pdoPostgres(
                host: $config['host'] ?? 'localhost',
                database: $config['database'] ?? $config['dbname'] ?? '',
                username: $config['username'] ?? $config['user'] ?? 'postgres',
                password: $config['password'] ?? $config['pass'] ?? '',
                port: (int) ($config['port'] ?? 5432),
                tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? '',
                charset: $config['charset'] ?? 'UTF8',
                options: $config['options'] ?? [],
            ),
            'sqlite' => DatabaseConfig::pdoSqlite(
                path: $config['path'] ?? $config['database'] ?? ':memory:',
                tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? '',
                options: $config['options'] ?? [],
            ),
            default => throw new \InvalidArgumentException("Unknown PDO driver: {$pdoDriver}"),
        };

        return new PdoDatabaseAdapter($dbConfig);
    }

    /**
     * Create In-Memory database adapter
     *
     * @param array<string, mixed> $config
     * @return InMemoryDatabaseAdapter
     */
    public static function createInMemory(array $config = []): InMemoryDatabaseAdapter
    {
        $dbConfig = DatabaseConfig::memory(
            tablePrefix: $config['table_prefix'] ?? $config['tablePrefix'] ?? '',
            options: $config['options'] ?? [],
        );

        return new InMemoryDatabaseAdapter($dbConfig);
    }

    /**
     * Create In-Memory adapter with test data
     *
     * @param array<string, array<int, array<string, mixed>>> $tables Table name => rows
     * @param string $tablePrefix Table prefix
     * @return InMemoryDatabaseAdapter
     *
     * @example
     *   $db = DatabaseFactory::createTestDatabase([
     *       'sagas' => [
     *           ['id' => 1, 'name' => 'Star Wars', 'universe' => 'Star Wars'],
     *       ],
     *       'entities' => [
     *           ['id' => 1, 'saga_id' => 1, 'canonical_name' => 'Luke', 'entity_type' => 'character'],
     *           ['id' => 2, 'saga_id' => 1, 'canonical_name' => 'Tatooine', 'entity_type' => 'location'],
     *       ],
     *   ]);
     */
    public static function createTestDatabase(
        array $tables = [],
        string $tablePrefix = ''
    ): InMemoryDatabaseAdapter {
        return InMemoryDatabaseAdapter::createWithTestData($tables, $tablePrefix);
    }

    /**
     * Create adapter for unit testing with pre-defined saga schema
     *
     * Sets up all the required saga manager tables with proper structure.
     *
     * @param string $tablePrefix Table prefix (e.g., 'wp_saga_')
     * @return InMemoryDatabaseAdapter
     *
     * @example
     *   $db = DatabaseFactory::createForSagaTesting();
     *   $db->insert('sagas', ['name' => 'Test Saga', 'universe' => 'Test']);
     */
    public static function createForSagaTesting(string $tablePrefix = ''): InMemoryDatabaseAdapter
    {
        $db = new InMemoryDatabaseAdapter(DatabaseConfig::memory($tablePrefix));

        // Create saga tables with proper schema
        $db->schema()->createTable('sagas', [
            'id' => ['type' => 'int', 'unsigned' => true, 'autoincrement' => true],
            'name' => ['type' => 'varchar', 'length' => 100, 'nullable' => false],
            'universe' => ['type' => 'varchar', 'length' => 100, 'nullable' => false],
            'calendar_type' => ['type' => 'varchar', 'length' => 50, 'default' => 'absolute'],
            'calendar_config' => ['type' => 'json', 'nullable' => true],
            'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
        ], [
            'primary' => ['id'],
            'unique' => ['uk_name' => ['name']],
        ]);

        $db->schema()->createTable('entities', [
            'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
            'saga_id' => ['type' => 'int', 'unsigned' => true, 'nullable' => false],
            'entity_type' => ['type' => 'varchar', 'length' => 50, 'nullable' => false],
            'canonical_name' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
            'slug' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
            'importance_score' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 50],
            'embedding_hash' => ['type' => 'char', 'length' => 64, 'nullable' => true],
            'wp_post_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => true],
            'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
        ], [
            'primary' => ['id'],
            'unique' => ['uk_saga_name' => ['saga_id', 'canonical_name']],
            'indexes' => [
                'idx_saga_type' => ['saga_id', 'entity_type'],
                'idx_importance' => ['importance_score'],
                'idx_slug' => ['slug'],
            ],
        ]);

        $db->schema()->createTable('entity_relationships', [
            'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
            'source_entity_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => false],
            'target_entity_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => false],
            'relationship_type' => ['type' => 'varchar', 'length' => 50, 'nullable' => false],
            'strength' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 50],
            'valid_from' => ['type' => 'date', 'nullable' => true],
            'valid_until' => ['type' => 'date', 'nullable' => true],
            'metadata' => ['type' => 'json', 'nullable' => true],
            'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
        ], [
            'primary' => ['id'],
            'indexes' => [
                'idx_source_type' => ['source_entity_id', 'relationship_type'],
                'idx_target' => ['target_entity_id'],
            ],
        ]);

        $db->schema()->createTable('quality_metrics', [
            'entity_id' => ['type' => 'bigint', 'unsigned' => true, 'nullable' => false],
            'completeness_score' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 0],
            'consistency_score' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 100],
            'last_verified' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
            'issues' => ['type' => 'json', 'nullable' => true],
        ], [
            'primary' => ['entity_id'],
        ]);

        return $db;
    }
}
