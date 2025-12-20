<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Entity Repository Implementation
 *
 * Handles persistence of SagaEntity domain models using WordPress wpdb
 */
class MariaDBEntityRepository extends WordPressTablePrefixAware implements EntityRepositoryInterface
{
    private const CACHE_GROUP = 'saga';
    private const CACHE_TTL = 300; // 5 minutes

    public function findById(EntityId $id): SagaEntity
    {
        $entity = $this->findByIdOrNull($id);

        if ($entity === null) {
            throw new EntityNotFoundException(
                sprintf('Entity with ID %d not found', $id->value())
            );
        }

        return $entity;
    }

    public function findByIdOrNull(EntityId $id): ?SagaEntity
    {
        $cache_key = "saga_entity_{$id->value()}";
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return null;
        }

        $entity = $this->hydrate($row);
        wp_cache_set($cache_key, $entity, self::CACHE_GROUP, self::CACHE_TTL);

        return $entity;
    }

    public function findBySaga(SagaId $sagaId, ?int $limit = null, int $offset = 0): array
    {
        $table = $this->getTableName('entities');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE saga_id = %d ORDER BY importance_score DESC, canonical_name ASC",
            $sagaId->value()
        );

        if ($limit !== null) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndType(
        SagaId $sagaId,
        EntityType $type,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $table = $this->getTableName('entities');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE saga_id = %d AND entity_type = %s ORDER BY importance_score DESC, canonical_name ASC",
            $sagaId->value(),
            $type->value
        );

        if ($limit !== null) {
            $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndName(SagaId $sagaId, string $canonicalName): ?SagaEntity
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE saga_id = %d AND canonical_name = %s",
            $sagaId->value(),
            $canonicalName
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?SagaEntity
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE slug = %s",
            $slug
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByWpPostId(int $postId): ?SagaEntity
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE wp_post_id = %d",
            $postId
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        return $row ? $this->hydrate($row) : null;
    }

    public function save(SagaEntity $entity): void
    {
        $table = $this->getTableName('entities');

        $data = [
            'saga_id' => $entity->getSagaId()->value(),
            'entity_type' => $entity->getType()->value,
            'canonical_name' => $entity->getCanonicalName(),
            'slug' => $entity->getSlug(),
            'importance_score' => $entity->getImportanceScore()->value(),
            'embedding_hash' => $entity->getEmbeddingHash(),
            'wp_post_id' => $entity->getWpPostId(),
            'updated_at' => $entity->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->query('START TRANSACTION');

        try {
            if ($entity->getId() === null) {
                // Insert new entity
                $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');

                $result = $this->wpdb->insert($table, $data);

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to insert entity: ' . $this->wpdb->last_error
                    );
                }

                $entity->setId(new EntityId($this->wpdb->insert_id));
            } else {
                // Update existing entity
                $result = $this->wpdb->update(
                    $table,
                    $data,
                    ['id' => $entity->getId()->value()]
                );

                if ($result === false) {
                    throw new \RuntimeException(
                        'Failed to update entity: ' . $this->wpdb->last_error
                    );
                }
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("saga_entity_{$entity->getId()->value()}", self::CACHE_GROUP);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Entity save failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(EntityId $id): void
    {
        $table = $this->getTableName('entities');

        $this->wpdb->query('START TRANSACTION');

        try {
            $result = $this->wpdb->delete(
                $table,
                ['id' => $id->value()],
                ['%d']
            );

            if ($result === false) {
                throw new \RuntimeException(
                    'Failed to delete entity: ' . $this->wpdb->last_error
                );
            }

            $this->wpdb->query('COMMIT');

            // Invalidate cache
            wp_cache_delete("saga_entity_{$id->value()}", self::CACHE_GROUP);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Entity deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function countBySaga(SagaId $sagaId): int
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE saga_id = %d",
            $sagaId->value()
        );

        return (int) $this->wpdb->get_var($query);
    }

    public function exists(EntityId $id): bool
    {
        $table = $this->getTableName('entities');

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $id->value()
        );

        return (int) $this->wpdb->get_var($query) > 0;
    }

    /**
     * Hydrate a database row into a SagaEntity domain model
     */
    private function hydrate(array $row): SagaEntity
    {
        return new SagaEntity(
            sagaId: new SagaId((int) $row['saga_id']),
            type: EntityType::from($row['entity_type']),
            canonicalName: $row['canonical_name'],
            slug: $row['slug'],
            importanceScore: new ImportanceScore((int) $row['importance_score']),
            id: new EntityId((int) $row['id']),
            embeddingHash: $row['embedding_hash'],
            wpPostId: $row['wp_post_id'] ? (int) $row['wp_post_id'] : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }
}
