<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Entity\RelationshipId;

/**
 * Relationship Repository Interface (Port)
 *
 * Defines the contract for relationship persistence
 */
interface RelationshipRepositoryInterface
{
    /**
     * Find relationship by ID
     *
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException
     */
    public function findById(RelationshipId $id): Relationship;

    /**
     * Find relationship by ID, returning null if not found
     */
    public function findByIdOrNull(RelationshipId $id): ?Relationship;

    /**
     * Find all relationships where entity is the source
     *
     * @return Relationship[]
     */
    public function findBySource(EntityId $entityId, ?string $type = null): array;

    /**
     * Find all relationships where entity is the target
     *
     * @return Relationship[]
     */
    public function findByTarget(EntityId $entityId, ?string $type = null): array;

    /**
     * Find all relationships involving an entity (as source or target)
     *
     * @return Relationship[]
     */
    public function findByEntity(EntityId $entityId, ?string $type = null): array;

    /**
     * Find relationship between two specific entities
     */
    public function findBetween(EntityId $sourceId, EntityId $targetId, ?string $type = null): ?Relationship;

    /**
     * Find all relationships of a specific type
     *
     * @return Relationship[]
     */
    public function findByType(string $type, ?int $limit = null, int $offset = 0): array;

    /**
     * Find currently valid relationships for an entity
     *
     * @return Relationship[]
     */
    public function findCurrentByEntity(EntityId $entityId, ?\DateTimeImmutable $asOf = null): array;

    /**
     * Find relationships active during a time period
     *
     * @return Relationship[]
     */
    public function findByTimePeriod(
        EntityId $entityId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): array;

    /**
     * Save relationship (create or update)
     */
    public function save(Relationship $relationship): void;

    /**
     * Delete relationship
     */
    public function delete(RelationshipId $id): void;

    /**
     * Delete all relationships for an entity
     *
     * @return int Number of deleted relationships
     */
    public function deleteByEntity(EntityId $entityId): int;

    /**
     * Check if relationship exists
     */
    public function exists(RelationshipId $id): bool;

    /**
     * Check if a relationship exists between two entities
     */
    public function existsBetween(EntityId $sourceId, EntityId $targetId, ?string $type = null): bool;

    /**
     * Count relationships for an entity
     */
    public function countByEntity(EntityId $entityId): int;

    /**
     * Get distinct relationship types used in the system
     *
     * @return string[]
     */
    public function getDistinctTypes(): array;
}
