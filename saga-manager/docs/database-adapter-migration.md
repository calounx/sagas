# Database Adapter Migration Guide

This guide explains how to migrate from the legacy `WordPressTablePrefixAware` base class to the new database adapter layer.

## Overview

The new database adapter layer provides:

1. **Multiple Database Backends**: WordPress, PDO (MySQL/MariaDB, PostgreSQL, SQLite), and InMemory
2. **Unified Interface**: Same API regardless of backend
3. **Testability**: InMemory adapter for fast unit tests without real database
4. **Future-Proofing**: Easy migration away from WordPress if needed

## Quick Migration

### Before (Legacy)

```php
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

class MariaDBEntityRepository extends WordPressTablePrefixAware implements EntityRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(); // Uses global $wpdb
    }

    public function findById(EntityId $id): SagaEntity
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);
        // ...
    }
}
```

### After (New)

```php
use SagaManager\Infrastructure\Database\AbstractDatabaseRepository;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;

class DatabaseEntityRepository extends AbstractDatabaseRepository implements EntityRepositoryInterface
{
    protected string $table = 'entities';

    public function __construct(DatabaseInterface $database)
    {
        parent::__construct($database);
    }

    public function findById(EntityId $id): SagaEntity
    {
        $row = $this->findRow($id->value());

        if (!$row) {
            throw new EntityNotFoundException();
        }

        return $this->hydrate($row);
    }
}
```

## Usage Examples

### WordPress (Production)

```php
use SagaManager\Infrastructure\Database\DatabaseFactory;
use SagaManager\Infrastructure\Repository\DatabaseEntityRepository;

// Create WordPress adapter with saga prefix
$db = DatabaseFactory::createWordPress(['table_prefix' => 'saga_']);
// Tables will be: wp_saga_entities, wp_saga_sagas, etc.

$repository = new DatabaseEntityRepository($db);
$entity = $repository->findById(new EntityId(1));
```

### InMemory (Unit Tests)

```php
use SagaManager\Infrastructure\Database\DatabaseFactory;
use SagaManager\Infrastructure\Repository\DatabaseEntityRepository;

// Create in-memory database with saga schema
$db = DatabaseFactory::createForSagaTesting('saga_');

// Seed test data
$db->insert('sagas', ['id' => 1, 'name' => 'Star Wars', 'universe' => 'Star Wars']);
$db->insert('entities', [
    'saga_id' => 1,
    'entity_type' => 'character',
    'canonical_name' => 'Luke Skywalker',
    'slug' => 'luke-skywalker',
]);

// Use repository
$repository = new DatabaseEntityRepository($db);
$entity = $repository->findById(new EntityId(1));
```

### PDO MySQL (Standalone)

```php
use SagaManager\Infrastructure\Database\DatabaseFactory;

$db = DatabaseFactory::createPdo([
    'pdo_driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'saga_manager',
    'username' => 'root',
    'password' => 'secret',
    'table_prefix' => 'saga_',
]);

$repository = new DatabaseEntityRepository($db);
```

### PDO SQLite (Development/Testing)

```php
$db = DatabaseFactory::createPdo([
    'pdo_driver' => 'sqlite',
    'path' => '/tmp/saga-test.db',
    'table_prefix' => 'saga_',
]);
```

## Query Builder

The new query builder provides a fluent interface that works with all adapters:

```php
// Complex query
$result = $db->query()
    ->select('e.id', 'e.canonical_name', 's.name AS saga_name')
    ->from('entities', 'e')
    ->join('sagas', 's', 'e.saga_id = s.id')
    ->where('e.entity_type', '=', 'character')
    ->where('e.importance_score', '>=', 80)
    ->whereNotNull('e.embedding_hash')
    ->orderBy('e.importance_score', 'DESC')
    ->limit(10)
    ->execute();

// Get results
foreach ($result as $row) {
    echo $row['canonical_name'];
}

// Result set methods
$names = $result->column('canonical_name');
$map = $result->pluck('id', 'canonical_name');
$grouped = $result->groupBy('saga_name');
```

## Transactions

```php
// Manual transaction
$db->transaction()->begin();
try {
    $id = $db->insert('entities', $entityData);
    $db->insert('quality_metrics', ['entity_id' => $id, 'completeness_score' => 0]);
    $db->transaction()->commit();
} catch (Exception $e) {
    $db->transaction()->rollback();
    throw $e;
}

// Callback-based transaction (recommended)
$entityId = $db->transaction()->run(function($db) use ($entityData) {
    $id = $db->insert('entities', $entityData);
    $db->insert('quality_metrics', ['entity_id' => $id, 'completeness_score' => 0]);
    return $id;
});

// Savepoints
$db->transaction()->begin();
$db->insert('entities', $entity1);
$db->transaction()->savepoint('after_first');

try {
    $db->insert('entities', $entity2);
} catch (Exception $e) {
    $db->transaction()->rollbackTo('after_first');
}

$db->transaction()->commit();
```

## Schema Management

```php
// Create table
$db->schema()->createTable('entities', [
    'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
    'saga_id' => ['type' => 'int', 'unsigned' => true, 'nullable' => false],
    'canonical_name' => ['type' => 'varchar', 'length' => 255, 'nullable' => false],
    'importance_score' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 50],
    'created_at' => ['type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP'],
], [
    'primary' => ['id'],
    'unique' => ['uk_saga_name' => ['saga_id', 'canonical_name']],
    'indexes' => ['idx_importance' => ['importance_score']],
]);

// Modify table
$db->schema()->addColumn('entities', 'deleted_at', [
    'type' => 'timestamp',
    'nullable' => true,
]);

// Check existence
if ($db->schema()->tableExists('entities')) {
    // ...
}
```

## Testing Best Practices

### Unit Tests

```php
class EntityRepositoryTest extends TestCase
{
    private DatabaseInterface $db;
    private DatabaseEntityRepository $repository;

    protected function setUp(): void
    {
        // Fast in-memory database
        $this->db = DatabaseFactory::createForSagaTesting();
        $this->repository = new DatabaseEntityRepository($this->db);

        // Seed test data
        $this->db->insert('sagas', [
            'id' => 1,
            'name' => 'Test Saga',
            'universe' => 'Test',
            'calendar_type' => 'absolute',
            'calendar_config' => '{}',
        ]);
    }

    public function testFindById(): void
    {
        $this->db->insert('entities', [
            'saga_id' => 1,
            'entity_type' => 'character',
            'canonical_name' => 'Luke Skywalker',
            'slug' => 'luke-skywalker',
            'importance_score' => 100,
        ]);

        $entity = $this->repository->findById(new EntityId(1));

        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
    }

    public function testSaveCreatesNewEntity(): void
    {
        $entity = new SagaEntity(
            sagaId: new SagaId(1),
            type: EntityType::CHARACTER,
            canonicalName: 'Leia Organa',
            slug: 'leia-organa',
            importanceScore: new ImportanceScore(95),
        );

        $this->repository->save($entity);

        $this->assertNotNull($entity->getId());
        $this->assertSame(1, $this->db->count('entities'));
    }
}
```

### Integration Tests (WordPress)

```php
class EntityRepositoryIntegrationTest extends WP_UnitTestCase
{
    private DatabaseInterface $db;
    private DatabaseEntityRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        // Real WordPress database
        $this->db = DatabaseFactory::createWordPress(['table_prefix' => 'saga_']);
        $this->repository = new DatabaseEntityRepository($this->db);
    }

    public function test_finds_entity_in_real_database(): void
    {
        // Insert using wpdb for setup
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'saga_entities', [
            'saga_id' => 1,
            'entity_type' => 'character',
            'canonical_name' => 'Luke Skywalker',
            'slug' => 'luke-skywalker',
        ]);

        $entity = $this->repository->findById(new EntityId($wpdb->insert_id));

        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
    }
}
```

## Dependency Injection Setup

### WordPress Plugin

```php
// In plugin initialization
add_action('plugins_loaded', function() {
    $container = new Container();

    // Register database
    $container->singleton(DatabaseInterface::class, function() {
        return DatabaseFactory::createWordPress(['table_prefix' => 'saga_']);
    });

    // Register repositories
    $container->singleton(EntityRepositoryInterface::class, function($c) {
        return new DatabaseEntityRepository($c->get(DatabaseInterface::class));
    });
});
```

### PHPUnit

```php
// tests/bootstrap.php
use SagaManager\Infrastructure\Database\DatabaseFactory;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;

// Register test database
$testDatabase = DatabaseFactory::createForSagaTesting();

// Make available to tests
$GLOBALS['test_database'] = $testDatabase;
```

## Comparison Table

| Feature | Legacy (wpdb) | New Adapters |
|---------|---------------|--------------|
| WordPress | Yes | Yes |
| PDO MySQL | No | Yes |
| PostgreSQL | No | Yes |
| SQLite | No | Yes |
| In-Memory Testing | No | Yes |
| Query Builder | No | Yes |
| Transactions | Manual | Yes (with callbacks) |
| Savepoints | Manual | Yes |
| Schema Management | Manual SQL | Yes |
| Result Set Methods | No | Yes |

## File Locations

```
src/Infrastructure/Database/
├── Contract/
│   ├── DatabaseInterface.php      # Main interface
│   ├── QueryBuilderInterface.php  # Query builder interface
│   ├── ResultSetInterface.php     # Result set interface
│   ├── TransactionInterface.php   # Transaction interface
│   ├── SchemaManagerInterface.php # Schema management interface
│   └── ConnectionInterface.php    # Connection interface
├── Config/
│   └── DatabaseConfig.php         # Configuration value object
├── Adapter/
│   ├── WordPress/
│   │   ├── WordPressDatabaseAdapter.php
│   │   ├── WordPressQueryBuilder.php
│   │   ├── WordPressResultSet.php
│   │   ├── WordPressTransactionManager.php
│   │   └── WordPressSchemaManager.php
│   ├── Pdo/
│   │   ├── PdoDatabaseAdapter.php
│   │   ├── PdoConnection.php
│   │   ├── PdoQueryBuilder.php
│   │   ├── PdoResultSet.php
│   │   ├── PdoTransactionManager.php
│   │   └── PdoSchemaManager.php
│   └── InMemory/
│       ├── InMemoryDatabaseAdapter.php
│       ├── InMemoryDataStore.php
│       ├── InMemoryQueryBuilder.php
│       ├── InMemoryResultSet.php
│       ├── InMemoryTransactionManager.php
│       └── InMemorySchemaManager.php
├── AbstractResultSet.php          # Shared result set implementation
├── AbstractDatabaseRepository.php # Base repository class
└── DatabaseFactory.php            # Factory for creating adapters
```
