<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Exception\RelationshipConstraintException;
use SagaManager\Domain\Exception\ValidationException;

class RelationshipTest extends TestCase
{
    private EntityId $sourceId;
    private EntityId $targetId;

    protected function setUp(): void
    {
        $this->sourceId = new EntityId(1);
        $this->targetId = new EntityId(2);
    }

    public function test_can_create_basic_relationship(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertTrue($this->sourceId->equals($relationship->getSourceEntityId()));
        $this->assertTrue($this->targetId->equals($relationship->getTargetEntityId()));
        $this->assertSame('ally_of', $relationship->getRelationshipType());
        $this->assertSame(50, $relationship->getStrength()->value());
        $this->assertNull($relationship->getId());
        $this->assertNull($relationship->getValidFrom());
        $this->assertNull($relationship->getValidUntil());
        $this->assertSame([], $relationship->getMetadata());
    }

    public function test_can_create_relationship_with_all_properties(): void
    {
        $validFrom = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2024-12-31');
        $metadata = ['note' => 'Important alliance'];

        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'parent_of',
            id: new RelationshipId(42),
            strength: new RelationshipStrength(90),
            validFrom: $validFrom,
            validUntil: $validUntil,
            metadata: $metadata
        );

        $this->assertSame(42, $relationship->getId()->value());
        $this->assertSame(90, $relationship->getStrength()->value());
        $this->assertSame($validFrom, $relationship->getValidFrom());
        $this->assertSame($validUntil, $relationship->getValidUntil());
        $this->assertSame($metadata, $relationship->getMetadata());
    }

    public function test_throws_exception_for_self_reference(): void
    {
        $this->expectException(RelationshipConstraintException::class);
        $this->expectExceptionMessage('Entity cannot have a relationship with itself');

        new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->sourceId, // Same as source
            relationshipType: 'ally_of'
        );
    }

    public function test_throws_exception_for_empty_relationship_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Relationship type cannot be empty');

        new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: ''
        );
    }

    public function test_throws_exception_for_invalid_relationship_type_format(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Relationship type must start with lowercase letter');

        new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'InvalidType'
        );
    }

    public function test_throws_exception_when_valid_until_before_valid_from(): void
    {
        $this->expectException(RelationshipConstraintException::class);
        $this->expectExceptionMessage('valid_until cannot be before valid_from');

        new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of',
            validFrom: new \DateTimeImmutable('2024-12-31'),
            validUntil: new \DateTimeImmutable('2024-01-01')
        );
    }

    public function test_set_id_updates_relationship_id(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertNull($relationship->getId());

        $relationship->setId(new RelationshipId(99));

        $this->assertSame(99, $relationship->getId()->value());
    }

    public function test_update_relationship_type(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $relationship->updateRelationshipType('enemy_of');

        $this->assertSame('enemy_of', $relationship->getRelationshipType());
    }

    public function test_set_strength(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $relationship->setStrength(new RelationshipStrength(80));

        $this->assertSame(80, $relationship->getStrength()->value());
    }

    public function test_set_validity_period(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $validFrom = new \DateTimeImmutable('2024-06-01');
        $validUntil = new \DateTimeImmutable('2024-12-31');

        $relationship->setValidityPeriod($validFrom, $validUntil);

        $this->assertSame($validFrom, $relationship->getValidFrom());
        $this->assertSame($validUntil, $relationship->getValidUntil());
    }

    public function test_set_validity_period_throws_for_invalid_range(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->expectException(RelationshipConstraintException::class);

        $relationship->setValidityPeriod(
            new \DateTimeImmutable('2024-12-31'),
            new \DateTimeImmutable('2024-01-01')
        );
    }

    public function test_set_metadata(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $metadata = ['context' => 'Battle of Endor', 'strength_reason' => 'Saved each other'];
        $relationship->setMetadata($metadata);

        $this->assertSame($metadata, $relationship->getMetadata());
    }

    public function test_is_currently_valid_returns_true_when_no_validity_period(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertTrue($relationship->isCurrentlyValid());
    }

    public function test_is_currently_valid_returns_true_within_validity_period(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of',
            validFrom: new \DateTimeImmutable('-1 month'),
            validUntil: new \DateTimeImmutable('+1 month')
        );

        $this->assertTrue($relationship->isCurrentlyValid());
    }

    public function test_is_currently_valid_returns_false_before_valid_from(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of',
            validFrom: new \DateTimeImmutable('+1 month')
        );

        $this->assertFalse($relationship->isCurrentlyValid());
    }

    public function test_is_currently_valid_returns_false_after_valid_until(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of',
            validUntil: new \DateTimeImmutable('-1 month')
        );

        $this->assertFalse($relationship->isCurrentlyValid());
    }

    public function test_involves_entity_returns_true_for_source(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertTrue($relationship->involvesEntity($this->sourceId));
    }

    public function test_involves_entity_returns_true_for_target(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertTrue($relationship->involvesEntity($this->targetId));
    }

    public function test_involves_entity_returns_false_for_unrelated_entity(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $this->assertFalse($relationship->involvesEntity(new EntityId(999)));
    }

    public function test_get_other_entity_returns_target_when_given_source(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $other = $relationship->getOtherEntity($this->sourceId);

        $this->assertTrue($this->targetId->equals($other));
    }

    public function test_get_other_entity_returns_source_when_given_target(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $other = $relationship->getOtherEntity($this->targetId);

        $this->assertTrue($this->sourceId->equals($other));
    }

    public function test_get_other_entity_returns_null_for_unrelated_entity(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $other = $relationship->getOtherEntity(new EntityId(999));

        $this->assertNull($other);
    }

    public function test_created_at_is_set_on_construction(): void
    {
        $before = new \DateTimeImmutable();
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $relationship->getCreatedAt());
        $this->assertLessThanOrEqual($after, $relationship->getCreatedAt());
    }

    public function test_updated_at_changes_on_modifications(): void
    {
        $relationship = new Relationship(
            sourceEntityId: $this->sourceId,
            targetEntityId: $this->targetId,
            relationshipType: 'ally_of'
        );

        $originalUpdatedAt = $relationship->getUpdatedAt();

        // Small delay to ensure different timestamp
        usleep(1000);

        $relationship->setStrength(new RelationshipStrength(75));

        $this->assertGreaterThan($originalUpdatedAt, $relationship->getUpdatedAt());
    }
}
