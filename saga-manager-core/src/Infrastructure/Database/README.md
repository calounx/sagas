# Database Abstraction Layer

A comprehensive database abstraction layer for Saga Manager Core, designed for maximum flexibility and future-proofing.

## Architecture Overview

```
+------------------------------------------------------------------+
|                        DOMAIN LAYER                               |
|  (Pure PHP - no database dependencies)                           |
|  +------------------------+  +----------------------------+       |
|  |   SagaEntity           |  |  EntityRepositoryInterface |       |
|  |   EntityId             |  |  (Port)                    |       |
|  |   SagaId               |  +----------------------------+       |
|  +------------------------+               |                       |
+------------------------------------------------------------------+
                                            |
                                            v
+------------------------------------------------------------------+
|                     APPLICATION LAYER                             |
|  +---------------------------+  +----------------------------+    |
|  |   CreateEntityHandler     |  |   SearchEntitiesHandler    |    |
|  |   UpdateEntityHandler     |  |   DeleteEntityHandler      |    |
|  +---------------------------+  +----------------------------+    |
+------------------------------------------------------------------+
                                            |
                                            v
+------------------------------------------------------------------+
|              DATABASE PORT INTERFACES (Contracts)                 |
|  +---------------------------+  +----------------------------+    |
|  | DatabaseConnectionInterface|  | QueryBuilderInterface      |    |
|  | TransactionManagerInterface|  | ResultSetInterface         |    |
|  | SchemaManagerInterface     |  | MigrationRunnerInterface   |    |
|  +---------------------------+  +----------------------------+    |
+------------------------------------------------------------------+
                                            |
                 +-------------------------+|+------------------------+
                 |                          |                         |
                 v                          v                         v
+------------------+  +--------------------+  +----------------------+
|  WordPress       |  |   PDO Adapter      |  |   InMemory Adapter   |
|  Adapter         |  |   (Future/Alt)     |  |   (Testing)          |
|  (Current)       |  |                    |  |                      |
+------------------+  +--------------------+  +----------------------+
         |                     |                        |
         v                     v                        v
+------------------+  +--------------------+  +----------------------+
|  WordPress $wpdb |  |   PDO Connection   |  |   PHP Arrays         |
|  MariaDB 11.4    |  |   MySQL/PostgreSQL |  |   (No real DB)       |
+------------------+  +--------------------+  +----------------------+
```

## Core Interfaces

### DatabaseConnectionInterface

The main entry point for database operations.

```php
interface DatabaseConnectionInterface
{
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function ping(): bool;

    // Get specialized components
    public function query(): QueryBuilderInterface;
    public function transaction(): TransactionManagerInterface;
    public function schema(): SchemaManagerInterface;

    // Table prefix handling
    public function getTablePrefix(): string;
    public function getSagaTablePrefix(): string;
    public function getFullTableName(string $tableName): string;

    // Raw SQL execution
    public function raw(string $sql, array $bindings = []): ResultSetInterface;

    // Query logging
    public function enableQueryLog(): void;
    public function getQueryLog(): array;
}
```

### QueryBuilderInterface

Fluent interface for building SQL queries.

```php
// SELECT example
$entities = $connection->query()
    ->select(['id', 'canonical_name', 'entity_type'])
    ->from('entities')
    ->where('saga_id', '=', 1)
    ->whereIn('entity_type', ['character', 'location'])
    ->orderBy('importance_score', 'DESC')
    ->limit(20)
    ->get();

// INSERT example
$result = $connection->query()
    ->table('entities')
    ->insert([
        'saga_id' => 1,
        'entity_type' => 'character',
        'canonical_name' => 'Luke Skywalker',
        'slug' => 'luke-skywalker',
        'importance_score' => 95,
    ]);

$newId = $result->lastInsertId();

// UPDATE example
$connection->query()
    ->table('entities')
    ->where('id', '=', 123)
    ->update([
        'importance_score' => 100,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

// DELETE example
$connection->query()
    ->table('entities')
    ->where('saga_id', '=', 1)
    ->where('importance_score', '<', 10)
    ->delete();
```

### TransactionManagerInterface

ACID transaction support with nested transactions.

```php
// Simple transaction
$connection->transaction()->run(function($tx) use ($entity) {
    $this->entityRepository->save($entity);
    $this->relationshipRepository->save($relationship);
});

// Manual control
$tx = $connection->transaction();
$tx->begin();
try {
    // operations
    $tx->commit();
} catch (\Exception $e) {
    $tx->rollback();
    throw $e;
}

// With retry on deadlock
$connection->transaction()->runWithRetry(function($tx) {
    // Critical operation
}, maxAttempts: 3, retryDelayMs: 100);

// Deferred callbacks
$tx->afterCommit(function() {
    wp_cache_delete('entity_123', 'saga_core');
});
```

## Available Adapters

### 1. WordPress Adapter (Primary)

Uses WordPress `$wpdb` global. This is the current production adapter.

```php
use SagaManagerCore\Infrastructure\Database\Adapter\WordPress\WordPressConnection;

$connection = new WordPressConnection();
$connection->connect();

// All operations use $wpdb->prepare() for SQL injection prevention
$entities = $connection->query()
    ->from('entities')
    ->where('saga_id', '=', $sagaId)
    ->get();
```

### 2. PDO Adapter (Future/Alternative)

For standalone PHP applications or migration away from WordPress.

```php
use SagaManagerCore\Infrastructure\Database\Adapter\PDO\PDOConnection;

// MySQL connection
$connection = PDOConnection::mysql(
    host: 'localhost',
    database: 'saga_manager',
    username: 'user',
    password: 'password',
    tablePrefix: ''
);

// SQLite connection (useful for development)
$connection = PDOConnection::sqlite('/path/to/database.db');
```

### 3. InMemory Adapter (Testing)

For unit tests without any database dependency.

```php
use SagaManagerCore\Infrastructure\Database\Adapter\InMemory\InMemoryConnection;

$connection = new InMemoryConnection('test_');
$connection->connect();

// Seed test data
$connection->seed('entities', [
    ['id' => 1, 'saga_id' => 1, 'canonical_name' => 'Luke Skywalker'],
    ['id' => 2, 'saga_id' => 1, 'canonical_name' => 'Darth Vader'],
]);

// Run tests
$entities = $connection->query()
    ->from('entities')
    ->where('saga_id', '=', 1)
    ->get();

// Reset between tests
$connection->reset();
```

## Repository Pattern

Use `DatabaseEntityRepository` for domain entity persistence:

```php
use SagaManagerCore\Infrastructure\Repository\DatabaseEntityRepository;

// With any connection type
$repository = new DatabaseEntityRepository($connection);

// CRUD operations
$entity = $repository->findById(new EntityId(123));
$entities = $repository->findBySaga(new SagaId(1), limit: 20);
$repository->save($entity);
$repository->delete(new EntityId(123));
```

## Migration System

Schema migrations with version tracking:

```php
use SagaManagerCore\Infrastructure\Database\Migration\MigrationRunner;

$runner = new MigrationRunner($connection);
$runner->setMigrationsPath(__DIR__ . '/migrations');

// Register migrations
$runner->register(new CreateEntitiesTableMigration());
$runner->register(new AddImportanceIndexMigration());

// Run pending migrations
$executed = $runner->migrate();

// Rollback last batch
$rolledBack = $runner->rollback();

// Check status
$status = $runner->status();
```

## Testing Example

```php
use PHPUnit\Framework\TestCase;
use SagaManagerCore\Infrastructure\Database\Adapter\InMemory\InMemoryConnection;
use SagaManagerCore\Infrastructure\Repository\DatabaseEntityRepository;

class EntityRepositoryTest extends TestCase
{
    private InMemoryConnection $connection;
    private DatabaseEntityRepository $repository;

    protected function setUp(): void
    {
        $this->connection = new InMemoryConnection('test_');
        $this->connection->connect();
        $this->repository = new DatabaseEntityRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->reset();
    }

    public function testFindById(): void
    {
        // Seed data
        $this->connection->seed('entities', [
            [
                'id' => 1,
                'saga_id' => 1,
                'entity_type' => 'character',
                'canonical_name' => 'Luke Skywalker',
                'slug' => 'luke-skywalker',
                'importance_score' => 95,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
        ]);

        $entity = $this->repository->findById(new EntityId(1));

        $this->assertEquals('Luke Skywalker', $entity->getCanonicalName());
    }

    public function testTransactionRollback(): void
    {
        $this->connection->transaction()->begin();

        $this->connection->query()
            ->table('entities')
            ->insert(['id' => 100, 'canonical_name' => 'Test']);

        $this->connection->transaction()->rollback();

        $this->assertFalse(
            $this->connection->query()
                ->from('entities')
                ->where('id', '=', 100)
                ->exists()
        );
    }
}
```

## Performance Considerations

1. **Query Logging**: Enable only in development
   ```php
   $connection->enableQueryLog();
   // ... queries ...
   $log = $connection->getQueryLog();
   // Each entry: ['query' => '...', 'bindings' => [...], 'time' => 12.5]
   ```

2. **Slow Query Warnings**: Automatic logging for queries >50ms
   ```
   [SAGA][PERF] Slow query (125.50ms): SELECT * FROM wp_saga_entities...
   ```

3. **Caching**: Built into `AbstractDatabaseRepository`
   ```php
   protected function getFromCache(string $key): mixed;
   protected function setInCache(string $key, mixed $value, ?int $ttl = null): void;
   ```

4. **Bulk Operations**: Use `insertBatch()` for multiple inserts
   ```php
   $connection->query()
       ->table('entities')
       ->insertBatch([
           ['canonical_name' => 'Entity 1'],
           ['canonical_name' => 'Entity 2'],
       ]);
   ```

## Security

- All queries use parameterized bindings (no SQL injection)
- WordPress adapter uses `$wpdb->prepare()`
- PDO adapter uses prepared statements
- Table prefix isolation prevents conflicts

## File Structure

```
src/Infrastructure/Database/
├── Port/                           # Interfaces (contracts)
│   ├── DatabaseConnectionInterface.php
│   ├── QueryBuilderInterface.php
│   ├── TransactionManagerInterface.php
│   ├── TransactionIsolation.php
│   ├── ResultSetInterface.php
│   ├── SchemaManagerInterface.php
│   └── MigrationRunnerInterface.php
├── Adapter/                        # Implementations
│   ├── AbstractDatabaseAdapter.php
│   ├── WordPress/
│   │   ├── WordPressConnection.php
│   │   ├── WordPressQueryBuilder.php
│   │   ├── WordPressTransactionManager.php
│   │   ├── WordPressSchemaManager.php
│   │   └── WordPressResultSet.php
│   ├── PDO/
│   │   ├── PDOConnection.php
│   │   ├── PDOQueryBuilder.php
│   │   └── PDOTransactionManager.php
│   └── InMemory/
│       ├── InMemoryConnection.php
│       ├── InMemoryQueryBuilder.php
│       └── InMemoryTransactionManager.php
├── Migration/
│   ├── MigrationInterface.php
│   ├── AbstractMigration.php
│   └── MigrationRunner.php
├── Exception/
│   ├── DatabaseExceptionInterface.php
│   ├── ConnectionException.php
│   ├── QueryException.php
│   ├── TransactionException.php
│   ├── SchemaException.php
│   └── MigrationException.php
└── ResultSet.php                   # Generic result set implementation
```
