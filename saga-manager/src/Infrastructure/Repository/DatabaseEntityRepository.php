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
use SagaManager\Infrastructure\Database\AbstractDatabaseRepository;
use SagaManager\Infrastructure\Database\Contract\DatabaseInterface;

/**
 * Database Entity Repository
 *
 * Implementation of EntityRepositoryInterface using the database adapter layer.
 * Works with any database adapter (WordPress, PDO, InMemory).
 *
 * @example With WordPress adapter:
 *   $db = DatabaseFactory::createWordPress(['table_prefix' => 'saga_']);
 *   $repository = new DatabaseEntityRepository($db);
 *
 * @example With InMemory adapter for testing:
 *   $db = DatabaseFactory::createForSagaTesting();
 *   $repository = new DatabaseEntityRepository($db);
 *
 * @example Migration from MariaDBEntityRepository:
 *   // Old:
 *   $repository = new MariaDBEntityRepository();
 *
 *   // New:
 *   $db = DatabaseFactory::createWordPress(['table_prefix' => 'saga_']);
 *   $repository = new DatabaseEntityRepository($db);
 */
class DatabaseEntityRepository extends AbstractDatabaseRepository implements EntityRepositoryInterface
{
    protected string $table = 'entities';
    protected string $cacheGroup = 'saga';
    protected int $cacheTtl = 300;

    public function __construct(DatabaseInterface $database)
    {
        parent::__construct($database);
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
        $cacheKey = $this->buildCacheKey($id->value());

        return $this->cached($cacheKey, function () use ($id) {
            $row = $this->findRow($id->value());
            return $row ? $this->hydrate($row) : null;
        });
    }

    public function findBySaga(SagaId $sagaId, ?int $limit = null, int $offset = 0): array
    {
        $rows = $this->findRows(
            ['saga_id' => $sagaId->value()],
            ['importance_score' => 'DESC', 'canonical_name' => 'ASC'],
            $limit,
            $offset
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndType(
        SagaId $sagaId,
        EntityType $type,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $rows = $this->findRows(
            ['saga_id' => $sagaId->value(), 'entity_type' => $type->value],
            ['importance_score' => 'DESC', 'canonical_name' => 'ASC'],
            $limit,
            $offset
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findBySagaAndName(SagaId $sagaId, string $canonicalName): ?SagaEntity
    {
        $result = $this->db->select(
            $this->table,
            ['saga_id' => $sagaId->value(), 'canonical_name' => $canonicalName],
            [],
            [],
            1
        );

        $row = $result->first();
        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?SagaEntity
    {
        $result = $this->db->select(
            $this->table,
            ['slug' => $slug],
            [],
            [],
            1
        );

        $row = $result->first();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByWpPostId(int $postId): ?SagaEntity
    {
        $result = $this->db->select(
            $this->table,
            ['wp_post_id' => $postId],
            [],
            [],
            1
        );

        $row = $result->first();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(SagaEntity $entity): void
    {
        $data = $this->dehydrate($entity);

        $this->transaction(function () use ($entity, $data) {
            if ($entity->getId() === null) {
                // Insert new entity
                $id = $this->insertRow($data);
                $entity->setId(new EntityId($id));
            } else {
                // Update existing entity
                $this->updateRow($data, ['id' => $entity->getId()->value()]);
            }

            // Invalidate cache
            $this->invalidateCache($this->buildCacheKey($entity->getId()->value()));

            return true;
        });
    }

    public function delete(EntityId $id): void
    {
        $this->transaction(function () use ($id) {
            $this->deleteRow(['id' => $id->value()]);
            $this->invalidateCache($this->buildCacheKey($id->value()));
            return true;
        });
    }

    public function countBySaga(SagaId $sagaId): int
    {
        return $this->countRows(['saga_id' => $sagaId->value()]);
    }

    public function exists(EntityId $id): bool
    {
        return $this->existsRow(['id' => $id->value()]);
    }

    /**
     * Search entities by name
     *
     * @param SagaId $sagaId
     * @param string $query
     * @param int|null $limit
     * @return SagaEntity[]
     */
    public function searchByName(SagaId $sagaId, string $query, ?int $limit = null): array
    {
        $result = $this->query()
            ->select('*')
            ->where('saga_id', '=', $sagaId->value())
            ->where('canonical_name', 'LIKE', "%{$query}%")
            ->orderBy('importance_score', 'DESC')
            ->orderBy('canonical_name', 'ASC');

        if ($limit !== null) {
            $result->limit($limit);
        }

        return array_map([$this, 'hydrate'], $result->execute()->toArray());
    }

    /**
     * Find entities by importance range
     *
     * @param SagaId $sagaId
     * @param int $minScore
     * @param int $maxScore
     * @param int|null $limit
     * @return SagaEntity[]
     */
    public function findByImportanceRange(
        SagaId $sagaId,
        int $minScore,
        int $maxScore,
        ?int $limit = null
    ): array {
        $result = $this->query()
            ->select('*')
            ->where('saga_id', '=', $sagaId->value())
            ->whereBetween('importance_score', $minScore, $maxScore)
            ->orderBy('importance_score', 'DESC');

        if ($limit !== null) {
            $result->limit($limit);
        }

        return array_map([$this, 'hydrate'], $result->execute()->toArray());
    }

    /**
     * Hydrate a database row into a SagaEntity domain model
     *
     * @param array<string, mixed> $row
     * @return SagaEntity
     */
    protected function hydrate(array $row): SagaEntity
    {
        return new SagaEntity(
            sagaId: new SagaId((int) $row['saga_id']),
            type: EntityType::from($row['entity_type']),
            canonicalName: $row['canonical_name'],
            slug: $row['slug'],
            importanceScore: new ImportanceScore((int) $row['importance_score']),
            id: isset($row['id']) ? new EntityId((int) $row['id']) : null,
            embeddingHash: $row['embedding_hash'] ?? null,
            wpPostId: isset($row['wp_post_id']) ? (int) $row['wp_post_id'] : null,
            createdAt: isset($row['created_at']) ? new \DateTimeImmutable($row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new \DateTimeImmutable($row['updated_at']) : null
        );
    }

    /**
     * Dehydrate a SagaEntity domain model into database row
     *
     * @param SagaEntity $entity
     * @return array<string, mixed>
     */
    protected function dehydrate(SagaEntity $entity): array
    {
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

        // Only include created_at for new entities
        if ($entity->getId() === null) {
            $data['created_at'] = $entity->getCreatedAt()->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
