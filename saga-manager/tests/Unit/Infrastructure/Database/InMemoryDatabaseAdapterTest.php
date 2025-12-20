<?php

declare(strict_types=1);

namespace SagaManager\Tests\Unit\Infrastructure\Database;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryDatabaseAdapter;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryDataStore;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryQueryBuilder;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryResultSet;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemorySchemaManager;
use SagaManager\Infrastructure\Database\Adapter\InMemory\InMemoryTransactionManager;
use SagaManager\Infrastructure\Database\Config\DatabaseConfig;
use SagaManager\Infrastructure\Database\DatabaseFactory;
use SagaManager\Domain\Exception\DatabaseException;

#[CoversClass(InMemoryDatabaseAdapter::class)]
#[CoversClass(InMemoryDataStore::class)]
#[CoversClass(InMemoryQueryBuilder::class)]
#[CoversClass(InMemoryResultSet::class)]
#[CoversClass(InMemorySchemaManager::class)]
#[CoversClass(InMemoryTransactionManager::class)]
final class InMemoryDatabaseAdapterTest extends TestCase
{
    private InMemoryDatabaseAdapter $db;

    protected function setUp(): void
    {
        $this->db = new InMemoryDatabaseAdapter(DatabaseConfig::memory('test_'));
    }

    // ============================================================
    // CRUD Operations Tests
    // ============================================================

    #[Test]
    public function insertReturnsAutoIncrementId(): void
    {
        $this->createEntitiesTable();

        $id1 = $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $id2 = $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1]);

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    #[Test]
    public function insertWithExplicitIdUpdatesAutoIncrement(): void
    {
        $this->createEntitiesTable();

        $this->db->insert('entities', ['id' => 100, 'name' => 'Luke', 'saga_id' => 1]);
        $id = $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1]);

        $this->assertSame(101, $id);
    }

    #[Test]
    public function insertWithEmptyDataThrowsException(): void
    {
        $this->createEntitiesTable();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot insert empty data');

        $this->db->insert('entities', []);
    }

    #[Test]
    public function findReturnsRowById(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);

        $row = $this->db->find('entities', 1);

        $this->assertNotNull($row);
        $this->assertSame('Luke', $row['name']);
        $this->assertSame(1, $row['saga_id']);
    }

    #[Test]
    public function findReturnsNullForNonExistentId(): void
    {
        $this->createEntitiesTable();

        $row = $this->db->find('entities', 999);

        $this->assertNull($row);
    }

    #[Test]
    public function updateModifiesMatchingRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1, 'score' => 50]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1, 'score' => 50]);

        $affected = $this->db->update(
            'entities',
            ['score' => 100],
            ['saga_id' => 1]
        );

        $this->assertSame(2, $affected);

        $luke = $this->db->find('entities', 1);
        $this->assertSame(100, $luke['score']);
    }

    #[Test]
    public function updateWithEmptyDataThrowsException(): void
    {
        $this->createEntitiesTable();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot update with empty data');

        $this->db->update('entities', [], ['id' => 1]);
    }

    #[Test]
    public function updateWithoutWhereClauseThrowsException(): void
    {
        $this->createEntitiesTable();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot update without WHERE clause');

        $this->db->update('entities', ['name' => 'Test'], []);
    }

    #[Test]
    public function deleteRemovesMatchingRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Vader', 'saga_id' => 2]);

        $deleted = $this->db->delete('entities', ['saga_id' => 1]);

        $this->assertSame(2, $deleted);
        $this->assertSame(1, $this->db->count('entities'));
    }

    #[Test]
    public function deleteWithoutWhereClauseThrowsException(): void
    {
        $this->createEntitiesTable();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cannot delete without WHERE clause');

        $this->db->delete('entities', []);
    }

    // ============================================================
    // Select and Query Builder Tests
    // ============================================================

    #[Test]
    public function selectReturnsFilteredRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1, 'type' => 'character']);
        $this->db->insert('entities', ['name' => 'Tatooine', 'saga_id' => 1, 'type' => 'location']);
        $this->db->insert('entities', ['name' => 'Vader', 'saga_id' => 2, 'type' => 'character']);

        $result = $this->db->select('entities', ['saga_id' => 1]);

        $this->assertSame(2, $result->count());
    }

    #[Test]
    public function selectWithColumnsReturnsOnlySpecifiedColumns(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1, 'type' => 'character']);

        $result = $this->db->select('entities', [], ['name', 'type']);

        $row = $result->first();
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('type', $row);
        $this->assertArrayNotHasKey('saga_id', $row);
    }

    #[Test]
    public function selectWithOrderByOrdersResults(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Zara', 'saga_id' => 1, 'score' => 10]);
        $this->db->insert('entities', ['name' => 'Adam', 'saga_id' => 1, 'score' => 30]);
        $this->db->insert('entities', ['name' => 'Mike', 'saga_id' => 1, 'score' => 20]);

        $result = $this->db->select(
            'entities',
            [],
            [],
            ['score' => 'DESC']
        );

        $rows = $result->toArray();
        $this->assertSame('Adam', $rows[0]['name']);
        $this->assertSame('Mike', $rows[1]['name']);
        $this->assertSame('Zara', $rows[2]['name']);
    }

    #[Test]
    public function selectWithLimitAndOffsetPaginatesResults(): void
    {
        $this->createEntitiesTable();
        for ($i = 1; $i <= 10; $i++) {
            $this->db->insert('entities', ['name' => "Entity {$i}", 'saga_id' => 1]);
        }

        $result = $this->db->select(
            'entities',
            [],
            [],
            ['id' => 'ASC'],
            3,
            5
        );

        $rows = $result->toArray();
        $this->assertCount(3, $rows);
        $this->assertSame('Entity 6', $rows[0]['name']);
        $this->assertSame('Entity 7', $rows[1]['name']);
        $this->assertSame('Entity 8', $rows[2]['name']);
    }

    #[Test]
    public function queryBuilderWithWhereConditions(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'score' => 80, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'score' => 90, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Han', 'score' => 70, 'saga_id' => 1]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->where('score', '>=', 80)
            ->orderBy('score', 'DESC')
            ->execute();

        $this->assertSame(2, $result->count());
        $rows = $result->toArray();
        $this->assertSame('Leia', $rows[0]['name']);
        $this->assertSame('Luke', $rows[1]['name']);
    }

    #[Test]
    public function queryBuilderWithWhereIn(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 2]);
        $this->db->insert('entities', ['name' => 'Han', 'saga_id' => 3]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->whereIn('saga_id', [1, 3])
            ->execute();

        $this->assertSame(2, $result->count());
    }

    #[Test]
    public function queryBuilderWithWhereBetween(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'A', 'score' => 10, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'B', 'score' => 50, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'C', 'score' => 100, 'saga_id' => 1]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->whereBetween('score', 20, 80)
            ->execute();

        $this->assertSame(1, $result->count());
        $this->assertSame('B', $result->first()['name']);
    }

    #[Test]
    public function queryBuilderWithNullConditions(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'type' => 'character', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Unknown', 'type' => null, 'saga_id' => 1]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->whereNull('type')
            ->execute();

        $this->assertSame(1, $result->count());
        $this->assertSame('Unknown', $result->first()['name']);
    }

    #[Test]
    public function queryBuilderWithOrWhere(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'type' => 'character', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Tatooine', 'type' => 'location', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Empire', 'type' => 'faction', 'saga_id' => 1]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->where('type', '=', 'character')
            ->orWhere('type', '=', 'location')
            ->execute();

        $this->assertSame(2, $result->count());
    }

    #[Test]
    public function queryBuilderWithLikeOperator(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke Skywalker', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Anakin Skywalker', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Han Solo', 'saga_id' => 1]);

        $result = $this->db->query()
            ->select('*')
            ->from('entities')
            ->where('name', 'LIKE', '%Skywalker%')
            ->execute();

        $this->assertSame(2, $result->count());
    }

    // ============================================================
    // Transaction Tests
    // ============================================================

    #[Test]
    public function transactionCommitsPersistsData(): void
    {
        $this->createEntitiesTable();

        $this->db->transaction()->begin();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->transaction()->commit();

        $this->assertSame(1, $this->db->count('entities'));
    }

    #[Test]
    public function transactionRollbackDiscardsData(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Before', 'saga_id' => 1]);

        $this->db->transaction()->begin();
        $this->db->insert('entities', ['name' => 'During', 'saga_id' => 1]);
        $this->db->transaction()->rollback();

        $this->assertSame(1, $this->db->count('entities'));
        $this->assertSame('Before', $this->db->find('entities', 1)['name']);
    }

    #[Test]
    public function transactionRunCommitsOnSuccess(): void
    {
        $this->createEntitiesTable();

        $result = $this->db->transaction()->run(function ($db) {
            $db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
            return 'success';
        });

        $this->assertSame('success', $result);
        $this->assertSame(1, $this->db->count('entities'));
    }

    #[Test]
    public function transactionRunRollsBackOnException(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Before', 'saga_id' => 1]);

        try {
            $this->db->transaction()->run(function ($db) {
                $db->insert('entities', ['name' => 'During', 'saga_id' => 1]);
                throw new \RuntimeException('Test error');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame(1, $this->db->count('entities'));
    }

    #[Test]
    public function nestedTransactionsWithSavepoints(): void
    {
        $this->createEntitiesTable();

        $this->db->transaction()->begin();
        $this->db->insert('entities', ['name' => 'First', 'saga_id' => 1]);

        $this->db->transaction()->begin(); // Nested
        $this->db->insert('entities', ['name' => 'Second', 'saga_id' => 1]);
        $this->db->transaction()->rollback(); // Rollback nested

        $this->db->transaction()->commit(); // Commit outer

        $this->assertSame(1, $this->db->count('entities'));
        $this->assertSame('First', $this->db->find('entities', 1)['name']);
    }

    #[Test]
    public function savepointCreationAndRollback(): void
    {
        $this->createEntitiesTable();

        $this->db->transaction()->begin();
        $this->db->insert('entities', ['name' => 'First', 'saga_id' => 1]);

        $this->db->transaction()->savepoint('after_first');
        $this->db->insert('entities', ['name' => 'Second', 'saga_id' => 1]);

        $this->db->transaction()->rollbackTo('after_first');
        $this->db->insert('entities', ['name' => 'Third', 'saga_id' => 1]);

        $this->db->transaction()->commit();

        $entities = $this->db->getAll('entities');
        $this->assertCount(2, $entities);
        $this->assertSame('First', $entities[0]['name']);
        $this->assertSame('Third', $entities[1]['name']);
    }

    #[Test]
    public function commitWithoutTransactionThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('No active transaction to commit');

        $this->db->transaction()->commit();
    }

    #[Test]
    public function rollbackWithoutTransactionThrowsException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('No active transaction to rollback');

        $this->db->transaction()->rollback();
    }

    // ============================================================
    // Schema Manager Tests
    // ============================================================

    #[Test]
    public function createTableCreatesTableWithSchema(): void
    {
        $this->db->schema()->createTable('users', [
            'id' => ['type' => 'bigint', 'autoincrement' => true],
            'name' => ['type' => 'varchar', 'length' => 255],
            'email' => ['type' => 'varchar', 'length' => 255],
        ], [
            'primary' => ['id'],
        ]);

        $this->assertTrue($this->db->schema()->tableExists('users'));
    }

    #[Test]
    public function dropTableRemovesTable(): void
    {
        $this->createEntitiesTable();
        $this->assertTrue($this->db->schema()->tableExists('entities'));

        $this->db->schema()->dropTable('entities');

        $this->assertFalse($this->db->schema()->tableExists('entities'));
    }

    #[Test]
    public function dropTableIfExistsDoesNotThrowForNonExistentTable(): void
    {
        $this->db->schema()->dropTableIfExists('non_existent');

        // Should not throw
        $this->assertFalse($this->db->schema()->tableExists('non_existent'));
    }

    #[Test]
    public function addColumnAddsToExistingTable(): void
    {
        $this->createEntitiesTable();

        $this->db->schema()->addColumn('entities', 'status', [
            'type' => 'varchar',
            'length' => 50,
            'default' => 'active',
        ]);

        $this->assertTrue($this->db->schema()->columnExists('entities', 'status'));
    }

    #[Test]
    public function dropColumnRemovesFromTable(): void
    {
        $this->createEntitiesTable();
        $this->db->schema()->addColumn('entities', 'temp', ['type' => 'varchar']);

        $this->db->schema()->dropColumn('entities', 'temp');

        $this->assertFalse($this->db->schema()->columnExists('entities', 'temp'));
    }

    #[Test]
    public function renameTableRenamesSuccessfully(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);

        $this->db->schema()->renameTable('entities', 'saga_entities');

        $this->assertFalse($this->db->schema()->tableExists('entities'));
        $this->assertTrue($this->db->schema()->tableExists('saga_entities'));
        $this->assertSame(1, $this->db->count('saga_entities'));
    }

    #[Test]
    public function truncateClearsAllData(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1]);

        $this->db->schema()->truncate('entities');

        $this->assertSame(0, $this->db->count('entities'));
        $this->assertTrue($this->db->schema()->tableExists('entities'));
    }

    // ============================================================
    // Result Set Tests
    // ============================================================

    #[Test]
    public function resultSetColumnExtractsValues(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 1]);

        $result = $this->db->select('entities');
        $names = $result->column('name');

        $this->assertSame(['Luke', 'Leia'], $names);
    }

    #[Test]
    public function resultSetPluckCreatesKeyValueMap(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'saga_id' => 2]);

        $result = $this->db->select('entities');
        $map = $result->pluck('id', 'name');

        $this->assertSame([1 => 'Luke', 2 => 'Leia'], $map);
    }

    #[Test]
    public function resultSetGroupByGroupsRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'type' => 'character', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'type' => 'character', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Tatooine', 'type' => 'location', 'saga_id' => 1]);

        $result = $this->db->select('entities');
        $grouped = $result->groupBy('type');

        $this->assertArrayHasKey('character', $grouped);
        $this->assertArrayHasKey('location', $grouped);
        $this->assertCount(2, $grouped['character']);
        $this->assertCount(1, $grouped['location']);
    }

    #[Test]
    public function resultSetMapTransformsRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'luke', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'leia', 'saga_id' => 1]);

        $result = $this->db->select('entities');
        $uppercase = $result->map(fn($row) => strtoupper($row['name']));

        $this->assertSame(['LUKE', 'LEIA'], $uppercase);
    }

    #[Test]
    public function resultSetFilterFiltersRows(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'score' => 80, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Leia', 'score' => 90, 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Han', 'score' => 70, 'saga_id' => 1]);

        $result = $this->db->select('entities');
        $filtered = $result->filter(fn($row) => $row['score'] >= 80);

        $this->assertSame(2, $filtered->count());
    }

    #[Test]
    public function resultSetFirstWhereFindsMatch(): void
    {
        $this->createEntitiesTable();
        $this->db->insert('entities', ['name' => 'Luke', 'type' => 'character', 'saga_id' => 1]);
        $this->db->insert('entities', ['name' => 'Tatooine', 'type' => 'location', 'saga_id' => 1]);

        $result = $this->db->select('entities');
        $location = $result->firstWhere(fn($row) => $row['type'] === 'location');

        $this->assertNotNull($location);
        $this->assertSame('Tatooine', $location['name']);
    }

    // ============================================================
    // Factory and Configuration Tests
    // ============================================================

    #[Test]
    public function createWithTestDataPopulatesDatabase(): void
    {
        $db = InMemoryDatabaseAdapter::createWithTestData([
            'entities' => [
                ['id' => 1, 'name' => 'Luke', 'saga_id' => 1],
                ['id' => 2, 'name' => 'Leia', 'saga_id' => 1],
            ],
        ]);

        $this->assertSame(2, $db->count('entities'));
    }

    #[Test]
    public function factoryCreatesInMemoryAdapter(): void
    {
        $db = DatabaseFactory::create('memory', ['table_prefix' => 'test_']);

        $this->assertInstanceOf(InMemoryDatabaseAdapter::class, $db);
        $this->assertSame('test_', $db->getPrefix());
    }

    #[Test]
    public function factoryCreatesForSagaTesting(): void
    {
        $db = DatabaseFactory::createForSagaTesting('saga_');

        $this->assertTrue($db->schema()->tableExists('sagas'));
        $this->assertTrue($db->schema()->tableExists('entities'));
        $this->assertTrue($db->schema()->tableExists('entity_relationships'));
        $this->assertTrue($db->schema()->tableExists('quality_metrics'));
    }

    #[Test]
    public function tablePrefixIsAppliedToAllOperations(): void
    {
        $db = new InMemoryDatabaseAdapter(DatabaseConfig::memory('prefix_'));

        $db->schema()->createTable('entities', [
            'id' => ['type' => 'int', 'autoincrement' => true],
            'name' => ['type' => 'varchar'],
        ]);

        $db->insert('entities', ['name' => 'Test']);

        $this->assertSame('prefix_entities', $db->getTableName('entities'));
        $this->assertSame(1, $db->count('entities'));
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    private function createEntitiesTable(): void
    {
        $this->db->schema()->createTable('entities', [
            'id' => ['type' => 'bigint', 'autoincrement' => true],
            'name' => ['type' => 'varchar', 'length' => 255],
            'type' => ['type' => 'varchar', 'length' => 50, 'nullable' => true],
            'saga_id' => ['type' => 'int'],
            'score' => ['type' => 'int', 'default' => 50],
        ], [
            'primary' => ['id'],
        ]);
    }
}
