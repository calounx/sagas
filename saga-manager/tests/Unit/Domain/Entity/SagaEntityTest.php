<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\ValidationException;

/**
 * Unit tests for SagaEntity domain model
 *
 * @covers \SagaManager\Domain\Entity\SagaEntity
 */
final class SagaEntityTest extends TestCase
{
    private SagaId $sagaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sagaId = new SagaId(1);
    }

    public function test_constructs_entity_with_required_fields(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Luke Skywalker',
            slug: 'luke-skywalker'
        );

        $this->assertNull($entity->getId());
        $this->assertTrue($this->sagaId->equals($entity->getSagaId()));
        $this->assertSame(EntityType::CHARACTER, $entity->getType());
        $this->assertSame('Luke Skywalker', $entity->getCanonicalName());
        $this->assertSame('luke-skywalker', $entity->getSlug());
        $this->assertSame(50, $entity->getImportanceScore()->value()); // Default
        $this->assertNull($entity->getEmbeddingHash());
        $this->assertNull($entity->getWpPostId());
    }

    public function test_constructs_entity_with_all_fields(): void
    {
        $entityId = new EntityId(123);
        $importanceScore = new ImportanceScore(85);
        $createdAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 12:00:00');

        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::LOCATION,
            canonicalName: 'Tatooine',
            slug: 'tatooine',
            importanceScore: $importanceScore,
            id: $entityId,
            embeddingHash: 'abc123',
            wpPostId: 456,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $this->assertTrue($entityId->equals($entity->getId()));
        $this->assertSame('Tatooine', $entity->getCanonicalName());
        $this->assertSame(85, $entity->getImportanceScore()->value());
        $this->assertSame('abc123', $entity->getEmbeddingHash());
        $this->assertSame(456, $entity->getWpPostId());
        $this->assertSame($createdAt, $entity->getCreatedAt());
        $this->assertSame($updatedAt, $entity->getUpdatedAt());
    }

    public function test_throws_exception_for_empty_canonical_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Canonical name cannot be empty');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: '',
            slug: 'test'
        );
    }

    public function test_throws_exception_for_whitespace_only_canonical_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Canonical name cannot be empty');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: '   ',
            slug: 'test'
        );
    }

    public function test_throws_exception_for_canonical_name_exceeding_255_chars(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Canonical name cannot exceed 255 characters');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: str_repeat('a', 256),
            slug: 'test'
        );
    }

    public function test_throws_exception_for_empty_slug(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Slug cannot be empty');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: ''
        );
    }

    public function test_throws_exception_for_slug_exceeding_255_chars(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Slug cannot exceed 255 characters');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: str_repeat('a', 256)
        );
    }

    public function test_throws_exception_for_invalid_slug_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Slug must contain only lowercase letters, numbers, and hyphens');

        new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'Invalid Slug!'
        );
    }

    public function test_accepts_valid_slug_formats(): void
    {
        $validSlugs = [
            'simple',
            'with-hyphens',
            'with123numbers',
            'mixed-123-test',
            'a',
            '123',
        ];

        foreach ($validSlugs as $slug) {
            $entity = new SagaEntity(
                sagaId: $this->sagaId,
                type: EntityType::CHARACTER,
                canonicalName: 'Test',
                slug: $slug
            );
            $this->assertSame($slug, $entity->getSlug());
        }
    }

    public function test_set_id_assigns_id_when_null(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $this->assertNull($entity->getId());

        $entityId = new EntityId(100);
        $entity->setId($entityId);

        $this->assertTrue($entityId->equals($entity->getId()));
    }

    public function test_set_id_throws_exception_when_already_set(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test',
            id: new EntityId(50)
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot change entity ID once set');

        $entity->setId(new EntityId(100));
    }

    public function test_update_canonical_name(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Original Name',
            slug: 'test'
        );

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000); // Ensure timestamp difference

        $entity->updateCanonicalName('New Name');

        $this->assertSame('New Name', $entity->getCanonicalName());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_update_canonical_name_validates_input(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $this->expectException(ValidationException::class);

        $entity->updateCanonicalName('');
    }

    public function test_update_slug(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'original-slug'
        );

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000);

        $entity->updateSlug('new-slug');

        $this->assertSame('new-slug', $entity->getSlug());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_update_slug_validates_input(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $this->expectException(ValidationException::class);

        $entity->updateSlug('Invalid Slug');
    }

    public function test_set_importance_score(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000);

        $newScore = new ImportanceScore(90);
        $entity->setImportanceScore($newScore);

        $this->assertSame(90, $entity->getImportanceScore()->value());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_set_embedding_hash(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000);

        $entity->setEmbeddingHash('xyz789');

        $this->assertSame('xyz789', $entity->getEmbeddingHash());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_link_to_wp_post(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $this->assertNull($entity->getWpPostId());

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000);

        $entity->linkToWpPost(999);

        $this->assertSame(999, $entity->getWpPostId());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_unlink_from_wp_post(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test',
            wpPostId: 888
        );

        $this->assertSame(888, $entity->getWpPostId());

        $originalUpdatedAt = $entity->getUpdatedAt();
        usleep(1000);

        $entity->unlinkFromWpPost();

        $this->assertNull($entity->getWpPostId());
        $this->assertGreaterThan($originalUpdatedAt, $entity->getUpdatedAt());
    }

    public function test_all_entity_types(): void
    {
        $types = [
            EntityType::CHARACTER,
            EntityType::LOCATION,
            EntityType::EVENT,
            EntityType::FACTION,
            EntityType::ARTIFACT,
            EntityType::CONCEPT,
        ];

        foreach ($types as $type) {
            $entity = new SagaEntity(
                sagaId: $this->sagaId,
                type: $type,
                canonicalName: 'Test',
                slug: 'test'
            );

            $this->assertSame($type, $entity->getType());
        }
    }

    public function test_created_at_defaults_to_now(): void
    {
        $before = new \DateTimeImmutable();

        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $entity->getCreatedAt());
        $this->assertLessThanOrEqual($after, $entity->getCreatedAt());
    }

    public function test_updated_at_is_updated_on_mutations(): void
    {
        $entity = new SagaEntity(
            sagaId: $this->sagaId,
            type: EntityType::CHARACTER,
            canonicalName: 'Test',
            slug: 'test'
        );

        $originalUpdatedAt = $entity->getUpdatedAt();

        usleep(1000);
        $entity->updateCanonicalName('Changed');
        $afterNameChange = $entity->getUpdatedAt();
        $this->assertGreaterThan($originalUpdatedAt, $afterNameChange);

        usleep(1000);
        $entity->updateSlug('changed');
        $afterSlugChange = $entity->getUpdatedAt();
        $this->assertGreaterThan($afterNameChange, $afterSlugChange);

        usleep(1000);
        $entity->setImportanceScore(new ImportanceScore(80));
        $afterScoreChange = $entity->getUpdatedAt();
        $this->assertGreaterThan($afterSlugChange, $afterScoreChange);
    }
}
