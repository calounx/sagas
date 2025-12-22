<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Entity\EntityId;

/**
 * Content Fragment Repository Interface
 */
interface ContentFragmentRepositoryInterface
{
    /**
     * Find a content fragment by ID
     *
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException
     */
    public function findById(ContentFragmentId $id): ContentFragment;

    /**
     * Find a content fragment by ID or return null
     */
    public function findByIdOrNull(ContentFragmentId $id): ?ContentFragment;

    /**
     * Find all fragments for an entity
     *
     * @return ContentFragment[]
     */
    public function findByEntity(EntityId $entityId): array;

    /**
     * Find fragments for an entity with pagination
     *
     * @return ContentFragment[]
     */
    public function findByEntityPaginated(EntityId $entityId, int $limit = 50, int $offset = 0): array;

    /**
     * Count fragments for an entity
     */
    public function countByEntity(EntityId $entityId): int;

    /**
     * Full-text search across all fragments
     *
     * @return ContentFragment[]
     */
    public function search(string $query, ?int $limit = 50): array;

    /**
     * Full-text search within a specific entity's fragments
     *
     * @return ContentFragment[]
     */
    public function searchByEntity(EntityId $entityId, string $query): array;

    /**
     * Find fragments that need embeddings
     *
     * @return ContentFragment[]
     */
    public function findWithoutEmbeddings(int $limit = 100): array;

    /**
     * Save a content fragment (insert or update)
     */
    public function save(ContentFragment $fragment): void;

    /**
     * Save multiple fragments in a transaction
     *
     * @param ContentFragment[] $fragments
     */
    public function saveMany(array $fragments): void;

    /**
     * Delete a content fragment
     */
    public function delete(ContentFragmentId $id): void;

    /**
     * Delete all fragments for an entity
     */
    public function deleteByEntity(EntityId $entityId): int;

    /**
     * Check if a fragment exists
     */
    public function exists(ContentFragmentId $id): bool;
}
