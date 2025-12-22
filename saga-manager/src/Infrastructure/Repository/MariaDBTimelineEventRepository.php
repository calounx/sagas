<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Repository;

use SagaManager\Domain\Entity\CanonDate;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;
use SagaManager\Infrastructure\WordPress\WordPressTablePrefixAware;

/**
 * MariaDB Implementation of Timeline Event Repository
 */
class MariaDBTimelineEventRepository extends WordPressTablePrefixAware implements TimelineEventRepositoryInterface
{
    private const CACHE_GROUP = 'saga_timeline_events';
    private const CACHE_TTL = 300; // 5 minutes

    public function findById(TimelineEventId $id): TimelineEvent
    {
        $event = $this->findByIdOrNull($id);

        if ($event === null) {
            throw new EntityNotFoundException(
                sprintf('Timeline event with ID %d not found', $id->value())
            );
        }

        return $event;
    }

    public function findByIdOrNull(TimelineEventId $id): ?TimelineEvent
    {
        $cacheKey = "event_{$id->value()}";
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id->value()
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if ($row === null) {
            return null;
        }

        $event = $this->hydrate($row);
        wp_cache_set($cacheKey, $event, self::CACHE_GROUP, self::CACHE_TTL);

        return $event;
    }

    public function findBySaga(SagaId $sagaId, int $limit = 100, int $offset = 0): array
    {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE saga_id = %d
             ORDER BY normalized_timestamp ASC
             LIMIT %d OFFSET %d",
            $sagaId->value(),
            $limit,
            $offset
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByTimeRange(
        SagaId $sagaId,
        NormalizedTimestamp $start,
        NormalizedTimestamp $end
    ): array {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE saga_id = %d
               AND normalized_timestamp BETWEEN %d AND %d
             ORDER BY normalized_timestamp ASC",
            $sagaId->value(),
            $start->value(),
            $end->value()
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByEntity(EntityId $entityId): array
    {
        $table = $this->getTableName('timeline_events');
        $entityIdValue = $entityId->value();

        // Search in event_entity_id, participants JSON, and locations JSON
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE event_entity_id = %d
                OR JSON_CONTAINS(participants, %s)
                OR JSON_CONTAINS(locations, %s)
             ORDER BY normalized_timestamp ASC",
            $entityIdValue,
            json_encode($entityIdValue),
            json_encode($entityIdValue)
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByParticipant(EntityId $entityId): array
    {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE JSON_CONTAINS(participants, %s)
             ORDER BY normalized_timestamp ASC",
            json_encode($entityId->value())
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findByLocation(EntityId $entityId): array
    {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE JSON_CONTAINS(locations, %s)
             ORDER BY normalized_timestamp ASC",
            json_encode($entityId->value())
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return array_map([$this, 'hydrate'], $rows);
    }

    public function countBySaga(SagaId $sagaId): int
    {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE saga_id = %d",
            $sagaId->value()
        );

        return (int) $this->wpdb->get_var($query);
    }

    public function save(TimelineEvent $event): void
    {
        $table = $this->getTableName('timeline_events');

        $data = [
            'saga_id' => $event->getSagaId()->value(),
            'event_entity_id' => $event->getEventEntityId()?->value(),
            'canon_date' => $event->getCanonDate()->value(),
            'normalized_timestamp' => $event->getNormalizedTimestamp()->value(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'participants' => json_encode($event->getParticipants()),
            'locations' => json_encode($event->getLocations()),
            'updated_at' => $event->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];

        $this->wpdb->query('START TRANSACTION');

        try {
            if ($event->getId() === null) {
                $data['created_at'] = $event->getCreatedAt()->format('Y-m-d H:i:s');
                $this->wpdb->insert($table, $data);

                if ($this->wpdb->insert_id === 0) {
                    throw new \RuntimeException('Failed to insert timeline event');
                }

                $event->setId(new TimelineEventId($this->wpdb->insert_id));
            } else {
                $this->wpdb->update(
                    $table,
                    $data,
                    ['id' => $event->getId()->value()]
                );
            }

            $this->wpdb->query('COMMIT');
            $this->invalidateCache($event);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][ERROR] Failed to save timeline event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(TimelineEventId $id): void
    {
        $event = $this->findByIdOrNull($id);

        $table = $this->getTableName('timeline_events');
        $this->wpdb->delete($table, ['id' => $id->value()]);

        if ($event !== null) {
            $this->invalidateCache($event);
        }
    }

    public function exists(TimelineEventId $id): bool
    {
        $table = $this->getTableName('timeline_events');
        $query = $this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE id = %d LIMIT 1",
            $id->value()
        );

        return $this->wpdb->get_var($query) !== null;
    }

    private function hydrate(array $row): TimelineEvent
    {
        $participants = $row['participants'] ? json_decode($row['participants'], true) : [];
        $locations = $row['locations'] ? json_decode($row['locations'], true) : [];

        return new TimelineEvent(
            sagaId: new SagaId((int) $row['saga_id']),
            canonDate: new CanonDate($row['canon_date']),
            normalizedTimestamp: new NormalizedTimestamp((int) $row['normalized_timestamp']),
            title: $row['title'],
            description: $row['description'],
            participants: $participants,
            locations: $locations,
            eventEntityId: $row['event_entity_id'] ? new EntityId((int) $row['event_entity_id']) : null,
            id: new TimelineEventId((int) $row['id']),
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }

    private function invalidateCache(TimelineEvent $event): void
    {
        if ($event->getId() !== null) {
            wp_cache_delete("event_{$event->getId()->value()}", self::CACHE_GROUP);
        }
    }
}
