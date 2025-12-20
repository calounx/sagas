<?php
declare(strict_types=1);

namespace SagaManager\Tests\Integration\Infrastructure\Repository;

use WP_UnitTestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Tests\Fixtures\SagaFixtures;

/**
 * Integration tests for MariaDBEntityRepository
 *
 * Tests actual database operations with WordPress test suite.
 * Verifies table prefix handling, transactions, and cache integration.
 *
 * @covers \SagaManager\Infrastructure\Repository\MariaDBEntityRepository
 */
final class MariaDBEntityRepositoryTest extends WP_UnitTestCase
{
    private MariaDBEntityRepository $repository;
    private int $sagaId;

    public function set_up(): void
    {
        parent::set_up();

        $this->repository = new MariaDBEntityRepository();

        // Load test fixtures
        $this->sagaId = SagaFixtures::loadStarWarsSaga();

        // Clear cache before each test
        wp_cache_flush();
    }

    public function tear_down(): void
    {
        SagaFixtures::cleanup();
        wp_cache_flush();

        parent::tear_down();
    }

    public function test_find_by_id_returns_entity(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');
        $this->assertNotNull($lukeId);

        $entity = $this->repository->findById(new EntityId($lukeId));

        $this->assertInstanceOf(SagaEntity::class, $entity);
        $this->assertSame($lukeId, $entity->getId()->value());
        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
        $this->assertSame('luke-skywalker', $entity->getSlug());
        $this->assertSame(EntityType::CHARACTER, $entity->getType());
        $this->assertSame(95, $entity->getImportanceScore()->value());
    }

    public function test_find_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Entity with ID 999999 not found');

        $this->repository->findById(new EntityId(999999));
    }

    public function test_find_by_id_or_null_returns_null_when_not_found(): void
    {
        $entity = $this->repository->findByIdOrNull(new EntityId(999999));

        $this->assertNull($entity);
    }

    public function test_find_by_id_uses_cache(): void
    {
        global $wpdb;

        $lukeId = SagaFixtures::getEntityId('luke');

        // First call - should query database
        $entity1 = $this->repository->findByIdOrNull(new EntityId($lukeId));
        $queryCount1 = $wpdb->num_queries;

        // Second call - should use cache
        $entity2 = $this->repository->findByIdOrNull(new EntityId($lukeId));
        $queryCount2 = $wpdb->num_queries;

        $this->assertSame($entity1, $entity2); // Same object from cache
        $this->assertSame($queryCount1, $queryCount2); // No additional query
    }

    public function test_find_by_saga_returns_all_entities(): void
    {
        $entities = $this->repository->findBySaga(new SagaId($this->sagaId));

        $this->assertCount(5, $entities);

        // Verify ordering by importance score DESC, then name ASC
        $this->assertSame('Darth Vader', $entities[0]->getCanonicalName()); // 100
        $this->assertSame('Luke Skywalker', $entities[1]->getCanonicalName()); // 95
        $this->assertSame('Battle of Yavin', $entities[2]->getCanonicalName()); // 90
        $this->assertSame('Rebel Alliance', $entities[3]->getCanonicalName()); // 85
        $this->assertSame('Tatooine', $entities[4]->getCanonicalName()); // 70
    }

    public function test_find_by_saga_with_limit_and_offset(): void
    {
        $entities = $this->repository->findBySaga(new SagaId($this->sagaId), limit: 2, offset: 1);

        $this->assertCount(2, $entities);
        $this->assertSame('Luke Skywalker', $entities[0]->getCanonicalName());
        $this->assertSame('Battle of Yavin', $entities[1]->getCanonicalName());
    }

    public function test_find_by_saga_and_type_filters_correctly(): void
    {
        $characters = $this->repository->findBySagaAndType(
            new SagaId($this->sagaId),
            EntityType::CHARACTER
        );

        $this->assertCount(2, $characters);

        foreach ($characters as $character) {
            $this->assertSame(EntityType::CHARACTER, $character->getType());
        }
    }

    public function test_find_by_saga_and_type_with_limit(): void
    {
        $characters = $this->repository->findBySagaAndType(
            new SagaId($this->sagaId),
            EntityType::CHARACTER,
            limit: 1
        );

        $this->assertCount(1, $characters);
        $this->assertSame('Darth Vader', $characters[0]->getCanonicalName());
    }

    public function test_find_by_saga_and_name_returns_entity(): void
    {
        $entity = $this->repository->findBySagaAndName(
            new SagaId($this->sagaId),
            'Luke Skywalker'
        );

        $this->assertNotNull($entity);
        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
    }

    public function test_find_by_saga_and_name_returns_null_when_not_found(): void
    {
        $entity = $this->repository->findBySagaAndName(
            new SagaId($this->sagaId),
            'Nonexistent Character'
        );

        $this->assertNull($entity);
    }

    public function test_find_by_slug_returns_entity(): void
    {
        $entity = $this->repository->findBySlug('luke-skywalker');

        $this->assertNotNull($entity);
        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
    }

    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        $entity = $this->repository->findBySlug('nonexistent-slug');

        $this->assertNull($entity);
    }

    public function test_find_by_wp_post_id_returns_entity(): void
    {
        global $wpdb;

        $lukeId = SagaFixtures::getEntityId('luke');

        // Set a wp_post_id for Luke
        $wpdb->update(
            $wpdb->prefix . 'saga_entities',
            ['wp_post_id' => 123],
            ['id' => $lukeId],
            ['%d'],
            ['%d']
        );

        $entity = $this->repository->findByWpPostId(123);

        $this->assertNotNull($entity);
        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
        $this->assertSame(123, $entity->getWpPostId());
    }

    public function test_save_inserts_new_entity(): void
    {
        $entity = new SagaEntity(
            sagaId: new SagaId($this->sagaId),
            type: EntityType::ARTIFACT,
            canonicalName: 'Lightsaber',
            slug: 'lightsaber',
            importanceScore: new ImportanceScore(60)
        );

        $this->assertNull($entity->getId());

        $this->repository->save($entity);

        $this->assertNotNull($entity->getId());
        $this->assertGreaterThan(0, $entity->getId()->value());

        // Verify it was actually saved
        $retrieved = $this->repository->findById($entity->getId());
        $this->assertSame('Lightsaber', $retrieved->getCanonicalName());
    }

    public function test_save_updates_existing_entity(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');
        $entity = $this->repository->findById(new EntityId($lukeId));

        $originalName = $entity->getCanonicalName();
        $entity->updateCanonicalName('Luke Skywalker (Updated)');

        $this->repository->save($entity);

        // Clear cache and retrieve again
        wp_cache_flush();
        $updated = $this->repository->findById(new EntityId($lukeId));

        $this->assertSame('Luke Skywalker (Updated)', $updated->getCanonicalName());
        $this->assertNotSame($originalName, $updated->getCanonicalName());
    }

    public function test_save_invalidates_cache(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');

        // Load entity into cache
        $entity = $this->repository->findById(new EntityId($lukeId));

        // Modify and save
        $entity->updateCanonicalName('Modified Name');
        $this->repository->save($entity);

        // Retrieve again - should get fresh data, not cached
        $retrieved = $this->repository->findById(new EntityId($lukeId));
        $this->assertSame('Modified Name', $retrieved->getCanonicalName());
    }

    public function test_delete_removes_entity(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');

        $this->assertTrue($this->repository->exists(new EntityId($lukeId)));

        $this->repository->delete(new EntityId($lukeId));

        $this->assertFalse($this->repository->exists(new EntityId($lukeId)));
    }

    public function test_delete_invalidates_cache(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');

        // Load entity into cache
        $this->repository->findById(new EntityId($lukeId));

        // Delete entity
        $this->repository->delete(new EntityId($lukeId));

        // Attempt to retrieve - should not find cached version
        $entity = $this->repository->findByIdOrNull(new EntityId($lukeId));
        $this->assertNull($entity);
    }

    public function test_count_by_saga_returns_correct_count(): void
    {
        $count = $this->repository->countBySaga(new SagaId($this->sagaId));

        $this->assertSame(5, $count);
    }

    public function test_exists_returns_true_for_existing_entity(): void
    {
        $lukeId = SagaFixtures::getEntityId('luke');

        $this->assertTrue($this->repository->exists(new EntityId($lukeId)));
    }

    public function test_exists_returns_false_for_nonexistent_entity(): void
    {
        $this->assertFalse($this->repository->exists(new EntityId(999999)));
    }

    public function test_transaction_rollback_on_save_error(): void
    {
        global $wpdb;

        $entity = new SagaEntity(
            sagaId: new SagaId($this->sagaId),
            type: EntityType::CHARACTER,
            canonicalName: 'Test Character',
            slug: 'test-character'
        );

        $this->repository->save($entity);
        $entityId = $entity->getId()->value();

        // Now try to insert duplicate (same saga_id and canonical_name)
        $duplicate = new SagaEntity(
            sagaId: new SagaId($this->sagaId),
            type: EntityType::CHARACTER,
            canonicalName: 'Test Character', // Duplicate!
            slug: 'test-character-2'
        );

        try {
            $this->repository->save($duplicate);
            $this->fail('Should have thrown exception for duplicate');
        } catch (\RuntimeException $e) {
            // Expected - duplicate key constraint
        }

        // Verify original entity still exists
        $this->assertTrue($this->repository->exists(new EntityId($entityId)));
    }

    public function test_table_prefix_handling(): void
    {
        global $wpdb;

        // Verify we're using the correct table prefix
        $lukeId = SagaFixtures::getEntityId('luke');
        $entity = $this->repository->findById(new EntityId($lukeId));

        $this->assertNotNull($entity);

        // Verify the table name includes WordPress prefix
        $tableName = $wpdb->prefix . 'saga_entities';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'");

        $this->assertSame($tableName, $exists);
    }

    public function test_hydration_preserves_all_fields(): void
    {
        global $wpdb;

        $lukeId = SagaFixtures::getEntityId('luke');

        // Set all optional fields
        $wpdb->update(
            $wpdb->prefix . 'saga_entities',
            [
                'embedding_hash' => 'test_hash_123',
                'wp_post_id' => 456,
            ],
            ['id' => $lukeId],
            ['%s', '%d'],
            ['%d']
        );

        wp_cache_flush();

        $entity = $this->repository->findById(new EntityId($lukeId));

        $this->assertSame('test_hash_123', $entity->getEmbeddingHash());
        $this->assertSame(456, $entity->getWpPostId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }

    public function test_foreign_key_cascade_delete(): void
    {
        global $wpdb;

        $lukeId = SagaFixtures::getEntityId('luke');

        // Delete the saga (should cascade to entities)
        $wpdb->delete(
            $wpdb->prefix . 'saga_sagas',
            ['id' => $this->sagaId],
            ['%d']
        );

        wp_cache_flush();

        // Entity should be gone due to cascade
        $entity = $this->repository->findByIdOrNull(new EntityId($lukeId));
        $this->assertNull($entity);
    }
}
