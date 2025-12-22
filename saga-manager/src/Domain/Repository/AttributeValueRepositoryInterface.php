<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\AttributeValue;
use SagaManager\Domain\Entity\EntityId;

/**
 * Attribute Value Repository Interface (Port)
 *
 * Defines the contract for attribute value persistence
 */
interface AttributeValueRepositoryInterface
{
    /**
     * Get all attribute values for an entity
     *
     * @return array<string, AttributeValue> Keyed by attribute_key
     */
    public function findByEntity(EntityId $entityId): array;

    /**
     * Get a single attribute value by entity and key
     */
    public function findByEntityAndKey(EntityId $entityId, string $attributeKey): ?AttributeValue;

    /**
     * Bulk fetch values for multiple entities (solves N+1 problem)
     *
     * @param EntityId[] $entityIds
     * @return array<int, array<string, AttributeValue>> Map of entity_id => [key => AttributeValue]
     */
    public function bulkFetch(array $entityIds): array;

    /**
     * Save a single attribute value (upsert)
     */
    public function save(AttributeValue $value): void;

    /**
     * Save multiple attribute values for an entity in a transaction
     *
     * @param AttributeValue[] $values
     */
    public function saveMany(array $values): void;

    /**
     * Delete a single attribute value by entity and key
     */
    public function delete(EntityId $entityId, string $attributeKey): void;

    /**
     * Delete all attribute values for an entity
     */
    public function deleteByEntity(EntityId $entityId): void;

    /**
     * Delete all values for a specific attribute definition
     * (Used when deleting an attribute definition)
     */
    public function deleteByDefinition(AttributeDefinitionId $definitionId): void;

    /**
     * Check if an attribute value exists for entity and key
     */
    public function exists(EntityId $entityId, string $attributeKey): bool;

    /**
     * Count attribute values for an entity
     */
    public function countByEntity(EntityId $entityId): int;
}
