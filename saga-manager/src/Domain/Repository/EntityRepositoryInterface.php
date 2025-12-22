<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\EntityType;

/**
 * Entity Repository Interface (Port)
 *
 * Defines the contract for entity persistence without coupling to infrastructure
 */
interface EntityRepositoryInterface
{
    /**
     * Find an entity by its ID
     *
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException
     */
    public function findById(EntityId $id): SagaEntity;

    /**
     * Find an entity by its ID, returning null if not found
     */
    public function findByIdOrNull(EntityId $id): ?SagaEntity;

    /**
     * Find all entities belonging to a saga
     *
     * @return SagaEntity[]
     */
    public function findBySaga(SagaId $sagaId, ?int $limit = null, int $offset = 0): array;

    /**
     * Find entities by saga and type
     *
     * @return SagaEntity[]
     */
    public function findBySagaAndType(
        SagaId $sagaId,
        EntityType $type,
        ?int $limit = null,
        int $offset = 0
    ): array;

    /**
     * Find entity by saga and canonical name
     */
    public function findBySagaAndName(SagaId $sagaId, string $canonicalName): ?SagaEntity;

    /**
     * Find entity by slug
     */
    public function findBySlug(string $slug): ?SagaEntity;

    /**
     * Find entity linked to a WordPress post
     */
    public function findByWpPostId(int $postId): ?SagaEntity;

    /**
     * Save an entity (create or update)
     */
    public function save(SagaEntity $entity): void;

    /**
     * Delete an entity
     */
    public function delete(EntityId $id): void;

    /**
     * Count entities in a saga
     */
    public function countBySaga(SagaId $sagaId): int;

    /**
     * Check if an entity exists
     */
    public function exists(EntityId $id): bool;

    /**
     * Delete multiple entities by their IDs
     *
     * @param EntityId[] $ids
     * @return int Number of deleted entities
     */
    public function deleteMany(array $ids): int;

    /**
     * Delete all entities belonging to a saga
     *
     * @return int Number of deleted entities
     */
    public function deleteBySaga(SagaId $sagaId): int;
}
