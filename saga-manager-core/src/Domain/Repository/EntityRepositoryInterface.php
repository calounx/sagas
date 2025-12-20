<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Repository;

use SagaManagerCore\Domain\Entity\EntityId;
use SagaManagerCore\Domain\Entity\SagaEntity;
use SagaManagerCore\Domain\Entity\SagaId;
use SagaManagerCore\Domain\Entity\EntityType;

/**
 * Port for entity persistence operations
 */
interface EntityRepositoryInterface
{
    /**
     * Find entity by ID
     *
     * @throws \SagaManagerCore\Domain\Exception\EntityNotFoundException
     */
    public function findById(EntityId $id): SagaEntity;

    /**
     * Find entity by ID or return null
     */
    public function findByIdOrNull(EntityId $id): ?SagaEntity;

    /**
     * Find all entities for a saga
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
     * Find entity by WordPress post ID
     */
    public function findByWpPostId(int $postId): ?SagaEntity;

    /**
     * Save entity (insert or update)
     */
    public function save(SagaEntity $entity): void;

    /**
     * Delete entity by ID
     */
    public function delete(EntityId $id): void;

    /**
     * Count entities for a saga
     */
    public function countBySaga(SagaId $sagaId): int;

    /**
     * Check if entity exists
     */
    public function exists(EntityId $id): bool;
}
