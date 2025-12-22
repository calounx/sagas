<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\EntityType;

/**
 * Attribute Definition Repository Interface (Port)
 *
 * Defines the contract for attribute definition persistence
 */
interface AttributeDefinitionRepositoryInterface
{
    /**
     * Find definition by ID
     *
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException
     */
    public function findById(AttributeDefinitionId $id): AttributeDefinition;

    /**
     * Find definition by ID, returning null if not found
     */
    public function findByIdOrNull(AttributeDefinitionId $id): ?AttributeDefinition;

    /**
     * Find all definitions for an entity type
     *
     * @return AttributeDefinition[]
     */
    public function findByEntityType(EntityType $type): array;

    /**
     * Find definition by entity type and attribute key
     */
    public function findByTypeAndKey(EntityType $type, string $key): ?AttributeDefinition;

    /**
     * Find all required definitions for an entity type
     *
     * @return AttributeDefinition[]
     */
    public function findRequiredByEntityType(EntityType $type): array;

    /**
     * Find all searchable definitions for an entity type
     *
     * @return AttributeDefinition[]
     */
    public function findSearchableByEntityType(EntityType $type): array;

    /**
     * Save definition (create or update)
     */
    public function save(AttributeDefinition $definition): void;

    /**
     * Delete definition and all associated values (cascade)
     */
    public function delete(AttributeDefinitionId $id): void;

    /**
     * Check if definition exists
     */
    public function exists(AttributeDefinitionId $id): bool;

    /**
     * Check if attribute key already exists for entity type
     */
    public function keyExists(EntityType $type, string $key): bool;

    /**
     * Count definitions for an entity type
     */
    public function countByEntityType(EntityType $type): int;
}
