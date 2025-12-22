<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Relationship Repository Implementation
 *
 * Handles persistence of Relationship domain models using WordPress wpdb.
 * Supports temporal queries and relationship graph traversal.
 */
class MariaDBRelationshipRepository extends WordPressTablePrefixAware implements RelationshipRepositoryInterface
{
    private const CACHE_GROUP = 'saga_relationships';
    private const CACHE_TTL = 300; // 5 minutes

    public function findById(RelationshipId $id): Relationship
    {
        $relationship = $this->findByIdOrNull($id);

        if ($relationship === null) {
            throw new EntityNotFoundException(
                sprintf('Relationship with ID %d not found', $id->value())
            );
        }

        return $relationship;
    }

    public function findByIdOrNull(RelationshipId $id): ?Relationship
    {
        $cacheKey = "rel_{$id->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('entity_relationships');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return null;
        }

        $relationship = $this->hydrate($row);
        wp_cache_set($cacheKey, $relationship, self::CACHE_GROUP, self::CACHE_TTL);

        return $relationship;
    }

    public function findBySource(EntityId $entityId, ?string $type = null): array
    {
        $table = $this->getTableName('entity_relationships');

        if ($type !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_entity_id = %d AND relationship_type = %s ORDER BY strength DESC",
                $entityId->value(),
                $type
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE source_entity_id = %d ORDER BY relationship_type, strength DESC",
                $entityId->value()
            );
        }

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByTarget(EntityId $entityId, ?string $type = null): array
    {
        $table = $this->getTableName('entity_relationships');

        if ($type !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE target_entity_id = %d AND relationship_type = %s ORDER BY strength DESC",
                $entityId->value(),
                $type
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE target_entity_id = %d ORDER BY relationship_type, strength DESC",
                $entityId->value()
            );
        }

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByEntity(EntityId $entityId, ?string $type = null): array
    {
        $table = $this->getTableName('entity_relationships');

        if ($type !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE (source_entity_id = %d OR target_entity_id = %d)
                 AND relationship_type = %s
                 ORDER BY strength DESC",
                $entityId->value(),
                $entityId->value(),
                $type
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE source_entity_id = %d OR target_entity_id = %d
                 ORDER BY relationship_type, strength DESC",
                $entityId->value(),
                $entityId->value()
            );
        }

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBetween(EntityId $sourceId, EntityId $targetId, ?string $type = null): ?Relationship
    {
        $table = $this->getTableName('entity_relationships');

        if ($type !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE source_entity_id = %d AND target_entity_id = %d AND relationship_type = %s",
                $sourceId->value(),
                $targetId->value(),
                $type
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE source_entity_id = %d AND target_entity_id = %d",
                $sourceId->value(),
                $targetId->value()
            );
        }

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByType(string $type, ?int $limit = null, int $offset = 0): array
    {
        $table = $this->getTableName('entity_relationships');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE relationship_type = %s ORDER BY strength DESC",
            $type
        );

        if ($limit !== null) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findCurrentByEntity(EntityId $entityId, ?\DateTimeImmutable $asOf = null): array
    {
        $asOf = $asOf ?? new \DateTimeImmutable();
        $asOfStr = $asOf->format('Y-m-d');

        $table = $this->getTableName('entity_relationships');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE (source_entity_id = %d OR target_entity_id = %d)
             AND (valid_from IS NULL OR valid_from <= %s)
             AND (valid_until IS NULL OR valid_until >= %s)
             ORDER BY strength DESC",
            $entityId->value(),
            $entityId->value(),
            $asOfStr,
            $asOfStr
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByTimePeriod(
        EntityId $entityId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until
    ): array {
        $table = $this->getTableName('entity_relationships');

        // Find relationships that overlap with the given period
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE (source_entity_id = %d OR target_entity_id = %d)
             AND (valid_from IS NULL OR valid_from <= %s)
             AND (valid_until IS NULL OR valid_until >= %s)
             ORDER BY valid_from, strength DESC",
            $entityId->value(),
            $entityId->value(),
            $until->format('Y-m-d'),
            $from->format('Y-m-d')
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(Relationship $relationship): void
    {
        $table = $this->getTableName('entity_relationships');

        $data = [
            'source_entity_id' => $relationship->getSourceEntityId()->value(),
            'target_entity_id' => $relationship->getTargetEntityId()->value(),
            'relationship_type' => $relationship->getRelationshipType(),
            'strength' => $relationship->getStrength()->value(),
            'valid_from' => $relationship->getValidFrom()?->format('Y-m-d'),
            'valid_until' => $relationship->getValidUntil()?->format('Y-m-d'),
            'metadata' => $relationship->getMetadata() !== null
                ? json_encode($relationship->getMetadata())
                : null,
            'updated_at' => $relationship->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->query('START TRANSACTION');

        try {
            if ($relationship->getId() === null) {
                // Insert new relationship
                $data['created_at'] = $relationship->getCreatedAt()->format('Y-m-d H:i:s');

                $result = $this->wpdb->insert($table, $data);

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to insert relationship: ' . $this->wpdb->last_error
                    );
                }

                $relationship->setId(new RelationshipId($this->wpdb->insert_id));
            } else {
                // Update existing relationship
                $result = $this->wpdb->update(
                    $table,
                    $data,
                    ['id' => $relationship->getId()->value()]
                );

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to update relationship: ' . $this->wpdb->last_error
                    );
                }
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            $this->invalidateCache($relationship);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Relationship save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(RelationshipId $id): void
    {
        $relationship = $this->findById($id);

        $table = $this->getTableName('entity_relationships');

        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $this->wpdb->delete(
                $table,
                ['id' => $id->value()],
                ['%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete relationship: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            $this->invalidateCache($relationship);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Relationship deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteByEntity(EntityId $entityId): int
    {
        $table = $this->getTableName('entity_relationships');

        // Get affected relationship IDs for cache invalidation
        $query = $this->wpdb->prepare(
            "SELECT id FROM {$table} WHERE source_entity_id = %d OR target_entity_id = %d",
            $entityId->value(),
            $entityId->value()
        );

        $relationshipIds = $this->wpdb->get_col($query);

        if (empty($relationshipIds)) {
            return 0;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            $query = $this->wpdb->prepare(
                "DELETE FROM {$table} WHERE source_entity_id = %d OR target_entity_id = %d",
                $entityId->value(),
                $entityId->value()
            );

            $result = $this->wpdb->query($query);

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete relationships: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache for all deleted relationships
            foreach ($relationshipIds as $relId) {
                wp_cache_delete("rel_{$relId}", self::CACHE_GROUP);
            }

            error_log(sprintf(
                '[SAGA][INFO] Deleted %d relationships for entity %d',
                $result,
                $entityId->value()
            ));

            return (int) $result;

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Entity relationships deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function exists(RelationshipId $id): bool
    {
        return $this->findByIdOrNull($id) !== null;
    }

    public function existsBetween(EntityId $sourceId, EntityId $targetId, ?string $type = null): bool
    {
        return $this->findBetween($sourceId, $targetId, $type) !== null;
    }

    public function countByEntity(EntityId $entityId): int
    {
        $table = $this->getTableName('entity_relationships');

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE source_entity_id = %d OR target_entity_id = %d",
            $entityId->value(),
            $entityId->value()
        );

        return (int) $this->wpdb->get_var($query);
    }

    public function getDistinctTypes(): array
    {
        $cacheKey = 'distinct_types';
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('entity_relationships');

        $types = $this->wpdb->get_col(
            "SELECT DISTINCT relationship_type FROM {$table} ORDER BY relationship_type"
        );

        wp_cache_set($cacheKey, $types, self::CACHE_GROUP, self::CACHE_TTL);

        return $types;
    }

    /**
     * Hydrate a database row into a Relationship domain model
     */
    private function hydrate(array $row): Relationship
    {
        $metadata = $row['metadata']
            ? json_decode($row['metadata'], true)
            : null;

        return new Relationship(
            sourceEntityId: new EntityId((int) $row['source_entity_id']),
            targetEntityId: new EntityId((int) $row['target_entity_id']),
            relationshipType: $row['relationship_type'],
            strength: new RelationshipStrength((int) $row['strength']),
            validFrom: $row['valid_from'] ? new \DateTimeImmutable($row['valid_from']) : null,
            validUntil: $row['valid_until'] ? new \DateTimeImmutable($row['valid_until']) : null,
            metadata: $metadata,
            id: new RelationshipId((int) $row['id']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }

    /**
     * Invalidate caches for a relationship
     */
    private function invalidateCache(Relationship $relationship): void
    {
        if ($relationship->getId() !== null) {
            wp_cache_delete("rel_{$relationship->getId()->value()}", self::CACHE_GROUP);
        }

        // Invalidate the distinct types cache as it might have changed
        wp_cache_delete('distinct_types', self::CACHE_GROUP);
    }
}
