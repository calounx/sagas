<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ValidationRule;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Attribute Definition Repository Implementation
 *
 * Handles persistence of AttributeDefinition domain models using WordPress wpdb.
 * Implements multi-level caching for optimal performance.
 */
class MariaDBAttributeDefinitionRepository extends WordPressTablePrefixAware implements AttributeDefinitionRepositoryInterface
{
    private const CACHE_GROUP = 'saga_attr_defs';
    private const CACHE_TTL = 3600; // 1 hour - definitions rarely change

    /** @var array<string, AttributeDefinition[]> In-memory cache by entity type */
    private array $typeCache = [];

    public function findById(AttributeDefinitionId $id): AttributeDefinition
    {
        $definition = $this->findByIdOrNull($id);

        if ($definition === null) {
            throw new EntityNotFoundException(
                sprintf('Attribute definition with ID %d not found', $id->value())
            );
        }

        return $definition;
    }

    public function findByIdOrNull(AttributeDefinitionId $id): ?AttributeDefinition
    {
        $cacheKey = "def_{$id->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('attribute_definitions');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return null;
        }

        $definition = $this->hydrate($row);
        wp_cache_set($cacheKey, $definition, self::CACHE_GROUP, self::CACHE_TTL);

        return $definition;
    }

    public function findByEntityType(EntityType $type): array
    {
        $typeValue = $type->value;

        // Check in-memory cache first
        if (isset($this->typeCache[$typeValue])) {
            return $this->typeCache[$typeValue];
        }

        // Check WordPress object cache
        $cacheKey = "type_{$typeValue}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            $this->typeCache[$typeValue] = $cached;
            return $cached;
        }

        $table = $this->getTableName('attribute_definitions');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY attribute_key ASC",
            $typeValue
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        $definitions = array_map([$this, 'hydrate'], $rows);

        // Store in both caches
        $this->typeCache[$typeValue] = $definitions;
        wp_cache_set($cacheKey, $definitions, self::CACHE_GROUP, self::CACHE_TTL);

        return $definitions;
    }

    public function findByTypeAndKey(EntityType $type, string $key): ?AttributeDefinition
    {
        // Leverage the type cache - single query for all definitions of type
        $definitions = $this->findByEntityType($type);

        foreach ($definitions as $definition) {
            if ($definition->getAttributeKey() === $key) {
                return $definition;
            }
        }

        return null;
    }

    public function findRequiredByEntityType(EntityType $type): array
    {
        return array_filter(
            $this->findByEntityType($type),
            fn(AttributeDefinition $d) => $d->isRequired()
        );
    }

    public function findSearchableByEntityType(EntityType $type): array
    {
        return array_filter(
            $this->findByEntityType($type),
            fn(AttributeDefinition $d) => $d->isSearchable()
        );
    }

    public function save(AttributeDefinition $definition): void
    {
        $table = $this->getTableName('attribute_definitions');

        $data = [
            'entity_type' => $definition->getEntityType()->value,
            'attribute_key' => $definition->getAttributeKey(),
            'display_name' => $definition->getDisplayName(),
            'data_type' => $definition->getDataType()->value,
            'is_searchable' => $definition->isSearchable() ? 1 : 0,
            'is_required' => $definition->isRequired() ? 1 : 0,
            'validation_rule' => $definition->getValidationRule()?->toJson(),
            'default_value' => $definition->getDefaultValue(),
        ];

        $this->wpdb->query('START TRANSACTION');

        try {
            if ($definition->getId() === null) {
                // Insert new definition
                $data['created_at'] = $definition->getCreatedAt()->format('Y-m-d H:i:s');

                $result = $this->wpdb->insert($table, $data);

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to insert attribute definition: ' . $this->wpdb->last_error
                    );
                }

                $definition->setId(new AttributeDefinitionId($this->wpdb->insert_id));
            } else {
                // Update existing definition
                $result = $this->wpdb->update(
                    $table,
                    $data,
                    ['id' => $definition->getId()->value()]
                );

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to update attribute definition: ' . $this->wpdb->last_error
                    );
                }
            }

            $this->wpdb->query('COMMIT');

            // Invalidate caches
            $this->invalidateCache($definition->getEntityType(), $definition->getId());

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Attribute definition save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(AttributeDefinitionId $id): void
    {
        // Get the definition first for cache invalidation
        $definition = $this->findById($id);

        $table = $this->getTableName('attribute_definitions');

        $this->wpdb->query('START TRANSACTION');

        try {
            // Foreign key cascade will delete associated values
            $result = $this->wpdb->delete(
                $table,
                ['id' => $id->value()],
                ['%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete attribute definition: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate caches
            $this->invalidateCache($definition->getEntityType(), $id);

            error_log(sprintf(
                '[SAGA][INFO] Deleted attribute definition %d (%s) for entity type %s',
                $id->value(),
                $definition->getAttributeKey(),
                $definition->getEntityType()->value
            ));

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Attribute definition deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function exists(AttributeDefinitionId $id): bool
    {
        return $this->findByIdOrNull($id) !== null;
    }

    public function keyExists(EntityType $type, string $key): bool
    {
        return $this->findByTypeAndKey($type, $key) !== null;
    }

    public function countByEntityType(EntityType $type): int
    {
        return count($this->findByEntityType($type));
    }

    /**
     * Hydrate a database row into an AttributeDefinition domain model
     */
    private function hydrate(array $row): AttributeDefinition
    {
        $validationConfig = $row['validation_rule']
            ? json_decode($row['validation_rule'], true)
            : null;

        return new AttributeDefinition(
            entityType: EntityType::from($row['entity_type']),
            attributeKey: $row['attribute_key'],
            displayName: $row['display_name'],
            dataType: DataType::from($row['data_type']),
            isSearchable: (bool) $row['is_searchable'],
            isRequired: (bool) $row['is_required'],
            validationRule: ValidationRule::fromArray($validationConfig),
            defaultValue: $row['default_value'],
            id: new AttributeDefinitionId((int) $row['id']),
            createdAt: new \DateTimeImmutable($row['created_at'])
        );
    }

    /**
     * Invalidate all caches for a definition
     */
    private function invalidateCache(EntityType $type, ?AttributeDefinitionId $id): void
    {
        // Clear in-memory cache
        unset($this->typeCache[$type->value]);

        // Clear WordPress object cache
        wp_cache_delete("type_{$type->value}", self::CACHE_GROUP);

        if ($id !== null) {
            wp_cache_delete("def_{$id->value()}", self::CACHE_GROUP);
        }
    }
}
