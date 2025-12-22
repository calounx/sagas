<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Exception\RelationshipConstraintException;

/**
 * Relationship Entity
 *
 * Represents a directed, typed relationship between two saga entities.
 * Supports temporal validity (valid_from/valid_until) and metadata.
 */
class Relationship
{
    private ?RelationshipId $id;
    private EntityId $sourceEntityId;
    private EntityId $targetEntityId;
    private string $relationshipType;
    private RelationshipStrength $strength;
    private ?\DateTimeImmutable $validFrom;
    private ?\DateTimeImmutable $validUntil;
    private ?array $metadata;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        EntityId $sourceEntityId,
        EntityId $targetEntityId,
        string $relationshipType,
        ?RelationshipStrength $strength = null,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
        ?array $metadata = null,
        ?RelationshipId $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->validateNotSelfReference($sourceEntityId, $targetEntityId);
        $this->validateRelationshipType($relationshipType);
        $this->validateDateRange($validFrom, $validUntil);

        $this->id = $id;
        $this->sourceEntityId = $sourceEntityId;
        $this->targetEntityId = $targetEntityId;
        $this->relationshipType = $relationshipType;
        $this->strength = $strength ?? RelationshipStrength::default();
        $this->validFrom = $validFrom;
        $this->validUntil = $validUntil;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?RelationshipId
    {
        return $this->id;
    }

    public function setId(RelationshipId $id): void
    {
        if ($this->id !== null) {
            throw new ValidationException('Cannot change relationship ID once set');
        }
        $this->id = $id;
    }

    public function getSourceEntityId(): EntityId
    {
        return $this->sourceEntityId;
    }

    public function getTargetEntityId(): EntityId
    {
        return $this->targetEntityId;
    }

    public function getRelationshipType(): string
    {
        return $this->relationshipType;
    }

    public function updateRelationshipType(string $type): void
    {
        $this->validateRelationshipType($type);
        $this->relationshipType = $type;
        $this->touch();
    }

    public function getStrength(): RelationshipStrength
    {
        return $this->strength;
    }

    public function setStrength(RelationshipStrength $strength): void
    {
        $this->strength = $strength;
        $this->touch();
    }

    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    /**
     * Set temporal validity range
     */
    public function setValidityPeriod(?\DateTimeImmutable $from, ?\DateTimeImmutable $until): void
    {
        $this->validateDateRange($from, $until);
        $this->validFrom = $from;
        $this->validUntil = $until;
        $this->touch();
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
        $this->touch();
    }

    /**
     * Get a specific metadata value
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set a specific metadata value
     */
    public function setMetadataValue(string $key, mixed $value): void
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if relationship is currently valid
     */
    public function isCurrentlyValid(?\DateTimeImmutable $asOf = null): bool
    {
        $asOf = $asOf ?? new \DateTimeImmutable();

        if ($this->validFrom !== null && $asOf < $this->validFrom) {
            return false;
        }

        if ($this->validUntil !== null && $asOf > $this->validUntil) {
            return false;
        }

        return true;
    }

    /**
     * Check if relationship has temporal bounds
     */
    public function hasTemporalBounds(): bool
    {
        return $this->validFrom !== null || $this->validUntil !== null;
    }

    /**
     * Check if this relationship involves a specific entity (as source or target)
     */
    public function involvesEntity(EntityId $entityId): bool
    {
        return $this->sourceEntityId->equals($entityId) || $this->targetEntityId->equals($entityId);
    }

    /**
     * Get the "other" entity in the relationship given one entity
     */
    public function getOtherEntity(EntityId $entityId): ?EntityId
    {
        if ($this->sourceEntityId->equals($entityId)) {
            return $this->targetEntityId;
        }
        if ($this->targetEntityId->equals($entityId)) {
            return $this->sourceEntityId;
        }
        return null;
    }

    /**
     * Check if this relationship connects the same two entities as another
     * (regardless of direction)
     */
    public function connectsSameEntities(Relationship $other): bool
    {
        return ($this->sourceEntityId->equals($other->sourceEntityId) && $this->targetEntityId->equals($other->targetEntityId))
            || ($this->sourceEntityId->equals($other->targetEntityId) && $this->targetEntityId->equals($other->sourceEntityId));
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function validateNotSelfReference(EntityId $source, EntityId $target): void
    {
        if ($source->equals($target)) {
            throw new RelationshipConstraintException(
                'Entity cannot have a relationship with itself'
            );
        }
    }

    private function validateRelationshipType(string $type): void
    {
        $type = trim($type);

        if (empty($type)) {
            throw new ValidationException('Relationship type cannot be empty');
        }

        if (strlen($type) > 50) {
            throw new ValidationException('Relationship type cannot exceed 50 characters');
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $type)) {
            throw new ValidationException(
                'Relationship type must start with lowercase letter and contain only lowercase letters, numbers, and underscores'
            );
        }
    }

    private function validateDateRange(?\DateTimeImmutable $from, ?\DateTimeImmutable $until): void
    {
        if ($from !== null && $until !== null && $until < $from) {
            throw new RelationshipConstraintException(
                'valid_until date cannot be before valid_from date'
            );
        }
    }
}
