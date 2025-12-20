<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Repository;

use SagaManagerCore\Domain\Entity\EntityId;
use SagaManagerCore\Domain\Entity\SagaEntity;
use SagaManagerCore\Domain\Entity\SagaId;
use SagaManagerCore\Domain\Entity\EntityType;
use SagaManagerCore\Domain\Entity\ImportanceScore;
use SagaManagerCore\Domain\Repository\EntityRepositoryInterface;
use SagaManagerCore\Domain\Exception\EntityNotFoundException;
use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;

/**
 * Database Entity Repository
 *
 * Repository implementation using the database abstraction layer.
 * Works with any adapter (WordPress, PDO, InMemory).
 *
 * This is the preferred repository for new code. The old MariaDBEntityRepository
 * is deprecated and should be migrated to use this implementation.
 *
 * @package SagaManagerCore\Infrastructure\Repository
 */
class DatabaseEntityRepository extends AbstractDatabaseRepository implements EntityRepositoryInterface
{
    private const CACHE_PREFIX = 'entity';

    public function __construct(DatabaseConnectionInterface $connection)
    {
        parent::__construct($connection);
    }

    protected function getTableName(): string
    {
        return 'entities';
    }

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
        $cacheKey = $this->getCacheKey(self::CACHE_PREFIX, $id->value());
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $row = $this->query()
            ->where('id', '=', $id->value())
            ->first();

        if ($row === null) {
            return null;
        }

        $entity = $this->hydrate($row);
        $this->setInCache($cacheKey, $entity);

        return $entity;
    }

    public function findBySaga(SagaId $sagaId, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->query()
            ->where('saga_id', '=', $sagaId->value())
            ->orderBy('importance_score', 'DESC')
            ->orderBy('canonical_name', 'ASC');

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $rows = $query->get();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndType(
        SagaId $sagaId,
        EntityType $type,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $query = $this->query()
            ->where('saga_id', '=', $sagaId->value())
            ->where('entity_type', '=', $type->value)
            ->orderBy('importance_score', 'DESC')
            ->orderBy('canonical_name', 'ASC');

        if ($limit !== null) {
            $query->limit($limit)->offset($offset);
        }

        $rows = $query->get();

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndName(SagaId $sagaId, string $canonicalName): ?SagaEntity
    {
        $row = $this->query()
            ->where('saga_id', '=', $sagaId->value())
            ->where('canonical_name', '=', $canonicalName)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?SagaEntity
    {
        $row = $this->query()
            ->where('slug', '=', $slug)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByWpPostId(int $postId): ?SagaEntity
    {
        $row = $this->query()
            ->where('wp_post_id', '=', $postId)
            ->first();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(SagaEntity $entity): void
    {
        $this->runInTransaction(function () use ($entity) {
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

            if ($entity->getId() === null) {
                // Insert new entity
                $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');

                $result = $this->connection->query()
                    ->table($this->getTableName())
                    ->insert($data);

                $entity->setId(new EntityId($result->lastInsertId()));
            } else {
                // Update existing entity
                $this->connection->query()
                    ->table($this->getTableName())
                    ->where('id', '=', $entity->getId()->value())
                    ->update($data);
            }

            // Invalidate cache
            $this->deleteFromCache(
                $this->getCacheKey(self::CACHE_PREFIX, $entity->getId()->value())
            );
        });
    }

    public function delete(EntityId $id): void
    {
        $this->runInTransaction(function () use ($id) {
            $this->connection->query()
                ->table($this->getTableName())
                ->where('id', '=', $id->value())
                ->delete();

            $this->deleteFromCache($this->getCacheKey(self::CACHE_PREFIX, $id->value()));
        });
    }

    public function countBySaga(SagaId $sagaId): int
    {
        return $this->query()
            ->where('saga_id', '=', $sagaId->value())
            ->count();
    }

    public function exists(EntityId $id): bool
    {
        return $this->query()
            ->where('id', '=', $id->value())
            ->exists();
    }

    /**
     * Search entities by name pattern
     *
     * @return SagaEntity[]
     */
    public function searchByName(SagaId $sagaId, string $pattern, int $limit = 20): array
    {
        $rows = $this->query()
            ->where('saga_id', '=', $sagaId->value())
            ->whereLike('canonical_name', '%' . $pattern . '%')
            ->orderBy('importance_score', 'DESC')
            ->limit($limit)
            ->get();

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Find entities by multiple IDs
     *
     * @param array<EntityId> $ids
     * @return SagaEntity[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $idValues = array_map(fn(EntityId $id) => $id->value(), $ids);

        $rows = $this->query()
            ->whereIn('id', $idValues)
            ->get();

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Update importance scores in bulk
     *
     * @param array<int, int> $scores [entity_id => score]
     */
    public function updateImportanceScores(array $scores): void
    {
        $this->runInTransaction(function () use ($scores) {
            foreach ($scores as $entityId => $score) {
                $this->connection->query()
                    ->table($this->getTableName())
                    ->where('id', '=', $entityId)
                    ->update(['importance_score' => $score]);

                $this->deleteFromCache($this->getCacheKey(self::CACHE_PREFIX, $entityId));
            }
        });
    }

    /**
     * Hydrate a database row into a SagaEntity domain model
     *
     * @param array<string, mixed> $row
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
