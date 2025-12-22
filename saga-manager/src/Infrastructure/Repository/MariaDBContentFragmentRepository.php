<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\TokenCount;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Implementation of Content Fragment Repository
 */
class MariaDBContentFragmentRepository extends WordPressTablePrefixAware implements ContentFragmentRepositoryInterface
{
    private const CACHE_GROUP = 'saga_content_fragments';
    private const CACHE_TTL = 300; // 5 minutes

    public function findById(ContentFragmentId $id): ContentFragment
    {
        $fragment = $this->findByIdOrNull($id);

        if ($fragment === null) {
            throw new EntityNotFoundException(
                sprintf('Content fragment with ID %d not found', $id->value())
            );
        }

        return $fragment;
    }

    public function findByIdOrNull(ContentFragmentId $id): ?ContentFragment
    {
        $cacheKey = "fragment_{$id->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if ($row === null) {
            return null;
        }

        $fragment = $this->hydrate($row);
        wp_cache_set($cacheKey, $fragment, self::CACHE_GROUP, self::CACHE_TTL);

        return $fragment;
    }

    public function findByEntity(EntityId $entityId): array
    {
        $cacheKey = "entity_{$entityId->value()}_fragments";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_id = %d ORDER BY created_at DESC",
            $entityId->value()
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);
        $fragments = array_map([$this, 'hydrate'], $rows);

        wp_cache_set($cacheKey, $fragments, self::CACHE_GROUP, self::CACHE_TTL);

        return $fragments;
    }

    public function findByEntityPaginated(EntityId $entityId, int $limit = 50, int $offset = 0): array
    {
        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $entityId->value(),
            $limit,
            $offset
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countByEntity(EntityId $entityId): int
    {
        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE entity_id = %d",
            $entityId->value()
        );

        return (int) $this->wpdb->get_var($query);
    }

    public function search(string $query, ?int $limit = 50): array
    {
        $table = $this->getTableName('content_fragments');
        $searchQuery = $this->wpdb->prepare(
            "SELECT *, MATCH(fragment_text) AGAINST(%s IN BOOLEAN MODE) AS relevance
             FROM {$table}
             WHERE MATCH(fragment_text) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC
             LIMIT %d",
            $query,
            $query,
            $limit
        );

        $rows = $this->wpdb->get_results($searchQuery, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function searchByEntity(EntityId $entityId, string $query): array
    {
        $table = $this->getTableName('content_fragments');
        $searchQuery = $this->wpdb->prepare(
            "SELECT *, MATCH(fragment_text) AGAINST(%s IN BOOLEAN MODE) AS relevance
             FROM {$table}
             WHERE entity_id = %d AND MATCH(fragment_text) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC",
            $query,
            $entityId->value(),
            $query
        );

        $rows = $this->wpdb->get_results($searchQuery, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findWithoutEmbeddings(int $limit = 100): array
    {
        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE embedding IS NULL ORDER BY created_at ASC LIMIT %d",
            $limit
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function save(ContentFragment $fragment): void
    {
        $table = $this->getTableName('content_fragments');

        $data = [
            'entity_id' => $fragment->getEntityId()->value(),
            'fragment_text' => $fragment->getFragmentText(),
            'embedding' => $fragment->getEmbedding(),
            'token_count' => $fragment->getTokenCount()->value(),
        ];

        $this->wpdb->query('START TRANSACTION');

        try {
            if ($fragment->getId() === null) {
                $this->wpdb->insert($table, $data);

                if ($this->wpdb->insert_id === 0) {
                    throw new \RuntimeException('Failed to insert content fragment');
                }

                $fragment->setId(new ContentFragmentId($this->wpdb->insert_id));
            } else {
                $this->wpdb->update(
                    $table,
                    $data,
                    ['id' => $fragment->getId()->value()]
                );
            }

            $this->wpdb->query('COMMIT');
            $this->invalidateCache($fragment);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Failed to save content fragment: ' . $e->getMessage());
            throw $e;
        }
    }

    public function saveMany(array $fragments): void
    {
        if (empty($fragments)) {
            return;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($fragments as $fragment) {
                $this->save($fragment);
            }

            $this->wpdb->query('COMMIT');

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Failed to save content fragments batch: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(ContentFragmentId $id): void
    {
        $fragment = $this->findByIdOrNull($id);

        $table = $this->getTableName('content_fragments');
        $this->wpdb->delete($table, ['id' => $id->value()]);

        if ($fragment !== null) {
            $this->invalidateCache($fragment);
        }
    }

    public function deleteByEntity(EntityId $entityId): int
    {
        $table = $this->getTableName('content_fragments');

        $count = $this->countByEntity($entityId);
        $this->wpdb->delete($table, ['entity_id' => $entityId->value()]);

        // Invalidate entity fragments cache
        wp_cache_delete("entity_{$entityId->value()}_fragments", self::CACHE_GROUP);

        return $count;
    }

    public function exists(ContentFragmentId $id): bool
    {
        $table = $this->getTableName('content_fragments');
        $query = $this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE id = %d LIMIT 1",
            $id->value()
        );

        return $this->wpdb->get_var($query) !== null;
    }

    private function hydrate(array $row): ContentFragment
    {
        return new ContentFragment(
            entityId: new EntityId((int) $row['entity_id']),
            fragmentText: $row['fragment_text'],
            tokenCount: new TokenCount((int) $row['token_count']),
            embedding: $row['embedding'],
            id: new ContentFragmentId((int) $row['id']),
            createdAt: new \DateTimeImmutable($row['created_at'])
        );
    }

    private function invalidateCache(ContentFragment $fragment): void
    {
        if ($fragment->getId() !== null) {
            wp_cache_delete("fragment_{$fragment->getId()->value()}", self::CACHE_GROUP);
        }

        wp_cache_delete("entity_{$fragment->getEntityId()->value()}_fragments", self::CACHE_GROUP);
    }
}
