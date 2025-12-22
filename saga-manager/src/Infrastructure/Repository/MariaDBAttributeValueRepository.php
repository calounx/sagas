<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\AttributeValue;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Repository\AttributeValueRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Attribute Value Repository Implementation
 *
 * Handles persistence of AttributeValue domain models using WordPress wpdb.
 * Optimized for bulk operations to avoid N+1 query problems.
 */
class MariaDBAttributeValueRepository extends WordPressTablePrefixAware implements AttributeValueRepositoryInterface
{
    private const CACHE_GROUP = 'saga_attr_vals';
    private const CACHE_TTL = 300; // 5 minutes - values change more frequently

    public function findByEntity(EntityId $entityId): array
    {
        $cacheKey = "entity_{$entityId->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $valuesTable = $this->getTableName('attribute_values');
        $defsTable = $this->getTableName('attribute_definitions');

        // Join with definitions to get data_type and attribute_key
        $query = $this->wpdb->prepare(
            "SELECT av.*, ad.attribute_key, ad.data_type
             FROM {$valuesTable} av
             JOIN {$defsTable} ad ON av.attribute_id = ad.id
             WHERE av.entity_id = %d
             ORDER BY ad.attribute_key ASC",
            $entityId->value()
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        $values = [];
        foreach ($rows as $row) {
            $value = AttributeValue::fromRow($row, $entityId);
            $values[$value->getAttributeKey()] = $value;
        }

        wp_cache_set($cacheKey, $values, self::CACHE_GROUP, self::CACHE_TTL);

        return $values;
    }

    public function findByEntityAndKey(EntityId $entityId, string $attributeKey): ?AttributeValue
    {
        $values = $this->findByEntity($entityId);
        return $values[$attributeKey] ?? null;
    }

    public function bulkFetch(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $idValues = array_map(fn(EntityId $id) => $id->value(), $entityIds);

        // Initialize result array with empty arrays for each entity
        $result = [];
        foreach ($idValues as $id) {
            $result[$id] = [];
        }

        // Check cache for each entity
        $uncachedIds = [];
        foreach ($idValues as $id) {
            $cacheKey = "entity_{$id}";
            $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

            if ($cached !== false) {
                $result[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        // If all entities were cached, return early
        if (empty($uncachedIds)) {
            return $result;
        }

        // Fetch uncached entities in a single query
        $valuesTable = $this->getTableName('attribute_values');
        $defsTable = $this->getTableName('attribute_definitions');

        $placeholders = implode(',', array_fill(0, count($uncachedIds), '%d'));

        $query = $this->wpdb->prepare(
            "SELECT av.*, ad.attribute_key, ad.data_type
             FROM {$valuesTable} av
             JOIN {$defsTable} ad ON av.attribute_id = ad.id
             WHERE av.entity_id IN ({$placeholders})
             ORDER BY av.entity_id, ad.attribute_key",
            ...$uncachedIds
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        // Group by entity and hydrate
        $entityValues = [];
        foreach ($uncachedIds as $id) {
            $entityValues[$id] = [];
        }

        foreach ($rows as $row) {
            $entityIdValue = (int) $row['entity_id'];
            $entityId = new EntityId($entityIdValue);
            $value = AttributeValue::fromRow($row, $entityId);
            $entityValues[$entityIdValue][$value->getAttributeKey()] = $value;
        }

        // Cache each entity's values and add to result
        foreach ($entityValues as $id => $values) {
            wp_cache_set("entity_{$id}", $values, self::CACHE_GROUP, self::CACHE_TTL);
            $result[$id] = $values;
        }

        return $result;
    }

    public function save(AttributeValue $value): void
    {
        $table = $this->getTableName('attribute_values');

        $data = [
            'entity_id' => $value->getEntityId()->value(),
            'attribute_id' => $value->getAttributeId()->value(),
            'updated_at' => $value->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        // Set the appropriate value column and null others
        $valueColumn = $value->getDataType()->getValueColumn();
        $allColumns = ['value_string', 'value_int', 'value_float', 'value_bool', 'value_date', 'value_text', 'value_json'];

        foreach ($allColumns as $col) {
            $data[$col] = ($col === $valueColumn) ? $value->getStorageValue() : null;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            // Use REPLACE INTO for upsert (works with composite primary key)
            $columns = implode(', ', array_keys($data));
            $placeholders = $this->buildPlaceholders($data, $value->getDataType());

            $query = $this->wpdb->prepare(
                "REPLACE INTO {$table} ({$columns}) VALUES ({$placeholders})",
                ...array_values($data)
            );

            $result = $this->wpdb->query($query);

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to save attribute value: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("entity_{$value->getEntityId()->value()}", self::CACHE_GROUP);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Attribute value save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function saveMany(array $values): void
    {
        if (empty($values)) {
            return;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            $affectedEntityIds = [];

            foreach ($values as $value) {
                if (!$value instanceof AttributeValue) {
                    throw new \InvalidArgumentException('All values must be AttributeValue instances');
                }

                $this->saveWithoutTransaction($value);
                $affectedEntityIds[$value->getEntityId()->value()] = true;
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache for all affected entities
            foreach (array_keys($affectedEntityIds) as $entityId) {
                wp_cache_delete("entity_{$entityId}", self::CACHE_GROUP);
            }

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Bulk attribute value save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(EntityId $entityId, string $attributeKey): void
    {
        $valuesTable = $this->getTableName('attribute_values');
        $defsTable = $this->getTableName('attribute_definitions');

        $this->wpdb->query('START TRANSACTION');

        try {
            // Find the attribute_id first
            $query = $this->wpdb->prepare(
                "SELECT id FROM {$defsTable} WHERE attribute_key = %s",
                $attributeKey
            );

            $attributeId = $this->wpdb->get_var($query);

            if ($attributeId === null) {
                // Attribute doesn't exist, nothing to delete
                $this->wpdb->query('COMMIT');
                return;
            }

            $result = $this->wpdb->delete(
                $valuesTable,
                [
                    'entity_id' => $entityId->value(),
                    'attribute_id' => (int) $attributeId,
                ],
                ['%d', '%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete attribute value: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("entity_{$entityId->value()}", self::CACHE_GROUP);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Attribute value deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteByEntity(EntityId $entityId): void
    {
        $table = $this->getTableName('attribute_values');

        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $this->wpdb->delete(
                $table,
                ['entity_id' => $entityId->value()],
                ['%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete attribute values for entity: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("entity_{$entityId->value()}", self::CACHE_GROUP);

            error_log(sprintf(
                '[SAGA][INFO] Deleted %d attribute values for entity %d',
                $result,
                $entityId->value()
            ));

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Entity attribute values deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteByDefinition(AttributeDefinitionId $definitionId): void
    {
        $table = $this->getTableName('attribute_values');

        // First, get all affected entity IDs for cache invalidation
        $query = $this->wpdb->prepare(
            "SELECT DISTINCT entity_id FROM {$table} WHERE attribute_id = %d",
            $definitionId->value()
        );

        $entityIds = $this->wpdb->get_col($query);

        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $this->wpdb->delete(
                $table,
                ['attribute_id' => $definitionId->value()],
                ['%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete attribute values by definition: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache for all affected entities
            foreach ($entityIds as $entityId) {
                wp_cache_delete("entity_{$entityId}", self::CACHE_GROUP);
            }

            error_log(sprintf(
                '[SAGA][INFO] Deleted %d attribute values for definition %d, cache invalidated for %d entities',
                $result,
                $definitionId->value(),
                count($entityIds)
            ));

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Definition attribute values deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function exists(EntityId $entityId, string $attributeKey): bool
    {
        return $this->findByEntityAndKey($entityId, $attributeKey) !== null;
    }

    public function countByEntity(EntityId $entityId): int
    {
        return count($this->findByEntity($entityId));
    }

    /**
     * Save a value without wrapping in transaction (for bulk operations)
     */
    private function saveWithoutTransaction(AttributeValue $value): void
    {
        $table = $this->getTableName('attribute_values');

        $data = [
            'entity_id' => $value->getEntityId()->value(),
            'attribute_id' => $value->getAttributeId()->value(),
            'updated_at' => $value->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        // Set the appropriate value column and null others
        $valueColumn = $value->getDataType()->getValueColumn();
        $allColumns = ['value_string', 'value_int', 'value_float', 'value_bool', 'value_date', 'value_text', 'value_json'];

        foreach ($allColumns as $col) {
            $data[$col] = ($col === $valueColumn) ? $value->getStorageValue() : null;
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = $this->buildPlaceholders($data, $value->getDataType());

        $query = $this->wpdb->prepare(
            "REPLACE INTO {$table} ({$columns}) VALUES ({$placeholders})",
            ...array_values($data)
        );

        $result = $this->wpdb->query($query);

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to save attribute value: ' . $this->wpdb->last_error
            );
        }
    }

    /**
     * Build placeholder string for prepared statement
     */
    private function buildPlaceholders(array $data, DataType $dataType): string
    {
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($key === 'entity_id' || $key === 'attribute_id') {
                $placeholders[] = '%d';
            } elseif ($key === 'value_int') {
                $placeholders[] = '%d';
            } elseif ($key === 'value_float') {
                $placeholders[] = '%f';
            } elseif ($key === 'value_bool') {
                $placeholders[] = '%d';
            } else {
                $placeholders[] = '%s';
            }
        }

        return implode(', ', $placeholders);
    }
}
