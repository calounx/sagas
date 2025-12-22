<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Attribute Repository Implementation
 *
 * Handles EAV attribute operations with optimized bulk fetching and caching.
 * Addresses the N+1 query problem by providing bulk hydration capabilities.
 */
class MariaDBAttributeRepository extends WordPressTablePrefixAware
{
    private const CACHE_GROUP = 'saga_attributes';
    private const CACHE_TTL = 3600; // 1 hour for definitions (rarely change)
    private const VALUES_CACHE_TTL = 300; // 5 minutes for values

    /**
     * Cached attribute definitions by entity type
     * @var array<string, array>
     */
    private array $definitionsCache = [];

    /**
     * Get all attribute definitions for an entity type
     *
     * Uses multi-level caching: in-memory + wp_cache
     *
     * @param EntityType $type
     * @return array<int, array{id: int, key: string, name: string, type: string, searchable: bool, required: bool}>
     */
    public function getDefinitionsForType(EntityType $type): array
    {
        $typeValue = $type->value;

        // Check in-memory cache first
        if (isset($this->definitionsCache[$typeValue])) {
            return $this->definitionsCache[$typeValue];
        }

        // Check WordPress object cache
        $cacheKey = "attr_defs_{$typeValue}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            $this->definitionsCache[$typeValue] = $cached;
            return $cached;
        }

        // Query database
        $table = $this->getTableName('attribute_definitions');

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, attribute_key, display_name, data_type, is_searchable, is_required, validation_rule, default_value
             FROM {$table}
             WHERE entity_type = %s
             ORDER BY attribute_key",
            $typeValue
        ), ARRAY_A);

        $definitions = [];
        foreach ($rows as $row) {
            $definitions[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'key' => $row['attribute_key'],
                'name' => $row['display_name'],
                'type' => $row['data_type'],
                'searchable' => (bool) $row['is_searchable'],
                'required' => (bool) $row['is_required'],
                'validation' => $row['validation_rule'] ? json_decode($row['validation_rule'], true) : null,
                'default' => $row['default_value'],
            ];
        }

        // Store in both caches
        $this->definitionsCache[$typeValue] = $definitions;
        wp_cache_set($cacheKey, $definitions, self::CACHE_GROUP, self::CACHE_TTL);

        return $definitions;
    }

    /**
     * Bulk fetch attribute values for multiple entities
     *
     * This is the key optimization for EAV - instead of N+1 queries,
     * we fetch all attributes for all entities in a single query.
     *
     * @param EntityId[] $entityIds
     * @return array<int, array<string, mixed>> Map of entity_id => [attr_key => value]
     */
    public function bulkFetchValues(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $idValues = array_map(fn(EntityId $id) => $id->value(), $entityIds);
        $placeholders = implode(',', array_fill(0, count($idValues), '%d'));

        $valuesTable = $this->getTableName('attribute_values');
        $defsTable = $this->getTableName('attribute_definitions');

        // Single query to get all attributes for all entities with definition info
        $query = $this->wpdb->prepare(
            "SELECT av.entity_id, ad.attribute_key, ad.data_type,
                    av.value_string, av.value_int, av.value_float,
                    av.value_bool, av.value_date, av.value_text, av.value_json
             FROM {$valuesTable} av
             JOIN {$defsTable} ad ON av.attribute_id = ad.id
             WHERE av.entity_id IN ({$placeholders})
             ORDER BY av.entity_id, ad.attribute_key",
            ...$idValues
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        // Organize by entity ID
        $result = [];
        foreach ($idValues as $id) {
            $result[$id] = [];
        }

        foreach ($rows as $row) {
            $entityId = (int) $row['entity_id'];
            $attrKey = $row['attribute_key'];
            $value = $this->extractValue($row);

            $result[$entityId][$attrKey] = $value;
        }

        return $result;
    }

    /**
     * Get attribute values for a single entity (with caching)
     *
     * @param EntityId $entityId
     * @return array<string, mixed>
     */
    public function getValuesForEntity(EntityId $entityId): array
    {
        $cacheKey = "attr_vals_{$entityId->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $result = $this->bulkFetchValues([$entityId]);
        $values = $result[$entityId->value()] ?? [];

        wp_cache_set($cacheKey, $values, self::CACHE_GROUP, self::VALUES_CACHE_TTL);

        return $values;
    }

    /**
     * Set attribute value for an entity
     *
     * @param EntityId $entityId
     * @param int $attributeId
     * @param mixed $value
     * @param string $dataType
     */
    public function setValue(EntityId $entityId, int $attributeId, mixed $value, string $dataType): void
    {
        $table = $this->getTableName('attribute_values');

        $data = [
            'entity_id' => $entityId->value(),
            'attribute_id' => $attributeId,
            'updated_at' => current_time('mysql'),
        ];

        // Set the appropriate value column based on data type
        $valueColumn = $this->getValueColumn($dataType);
        $data[$valueColumn] = $this->formatValue($value, $dataType);

        // Clear other value columns
        foreach (['value_string', 'value_int', 'value_float', 'value_bool', 'value_date', 'value_text', 'value_json'] as $col) {
            if ($col !== $valueColumn) {
                $data[$col] = null;
            }
        }

        // Upsert (INSERT ... ON DUPLICATE KEY UPDATE)
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '%s'));
        $updates = implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($data)));

        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE {$updates}",
            ...array_values($data)
        ));

        // Invalidate cache
        wp_cache_delete("attr_vals_{$entityId->value()}", self::CACHE_GROUP);
    }

    /**
     * Bulk set attribute values for an entity
     *
     * @param EntityId $entityId
     * @param array<string, mixed> $attributes Key => value pairs
     * @param EntityType $entityType
     */
    public function setValues(EntityId $entityId, array $attributes, EntityType $entityType): void
    {
        if (empty($attributes)) {
            return;
        }

        $definitions = $this->getDefinitionsForType($entityType);
        $defsByKey = [];
        foreach ($definitions as $def) {
            $defsByKey[$def['key']] = $def;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($attributes as $key => $value) {
                if (!isset($defsByKey[$key])) {
                    continue; // Skip unknown attributes
                }

                $def = $defsByKey[$key];
                $this->setValue($entityId, $def['id'], $value, $def['type']);
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("attr_vals_{$entityId->value()}", self::CACHE_GROUP);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Delete all attribute values for an entity
     *
     * @param EntityId $entityId
     */
    public function deleteValuesForEntity(EntityId $entityId): void
    {
        $table = $this->getTableName('attribute_values');

        $this->wpdb->delete(
            $table,
            ['entity_id' => $entityId->value()],
            ['%d']
        );

        wp_cache_delete("attr_vals_{$entityId->value()}", self::CACHE_GROUP);
    }

    /**
     * Invalidate definition cache for a type
     *
     * @param EntityType $type
     */
    public function invalidateDefinitionCache(EntityType $type): void
    {
        $typeValue = $type->value;
        unset($this->definitionsCache[$typeValue]);
        wp_cache_delete("attr_defs_{$typeValue}", self::CACHE_GROUP);
    }

    /**
     * Extract the correct value from a row based on data type
     */
    private function extractValue(array $row): mixed
    {
        $dataType = $row['data_type'];

        return match ($dataType) {
            'string' => $row['value_string'],
            'int' => $row['value_int'] !== null ? (int) $row['value_int'] : null,
            'float' => $row['value_float'] !== null ? (float) $row['value_float'] : null,
            'bool' => $row['value_bool'] !== null ? (bool) $row['value_bool'] : null,
            'date' => $row['value_date'],
            'text' => $row['value_text'],
            'json' => $row['value_json'] !== null ? json_decode($row['value_json'], true) : null,
            default => $row['value_string'],
        };
    }

    /**
     * Get the appropriate column name for a data type
     */
    private function getValueColumn(string $dataType): string
    {
        return match ($dataType) {
            'int' => 'value_int',
            'float' => 'value_float',
            'bool' => 'value_bool',
            'date' => 'value_date',
            'text' => 'value_text',
            'json' => 'value_json',
            default => 'value_string',
        };
    }

    /**
     * Format value for storage
     */
    private function formatValue(mixed $value, string $dataType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($dataType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $value ? 1 : 0,
            'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}
