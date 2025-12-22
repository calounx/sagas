<?php
declare(strict_types=1);

namespace SagaManager\Domain\Repository;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Entity\TimelineEventId;

/**
 * Timeline Event Repository Interface
 */
interface TimelineEventRepositoryInterface
{
    /**
     * Find a timeline event by ID
     *
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException
     */
    public function findById(TimelineEventId $id): TimelineEvent;

    /**
     * Find a timeline event by ID or return null
     */
    public function findByIdOrNull(TimelineEventId $id): ?TimelineEvent;

    /**
     * Find all events for a saga, ordered by normalized timestamp
     *
     * @return TimelineEvent[]
     */
    public function findBySaga(SagaId $sagaId, int $limit = 100, int $offset = 0): array;

    /**
     * Find events in a time range
     *
     * @return TimelineEvent[]
     */
    public function findByTimeRange(
        SagaId $sagaId,
        NormalizedTimestamp $start,
        NormalizedTimestamp $end
    ): array;

    /**
     * Find events involving an entity (as participant, location, or event entity)
     *
     * @return TimelineEvent[]
     */
    public function findByEntity(EntityId $entityId): array;

    /**
     * Find events by participant
     *
     * @return TimelineEvent[]
     */
    public function findByParticipant(EntityId $entityId): array;

    /**
     * Find events by location
     *
     * @return TimelineEvent[]
     */
    public function findByLocation(EntityId $entityId): array;

    /**
     * Count events for a saga
     */
    public function countBySaga(SagaId $sagaId): int;

    /**
     * Save a timeline event (insert or update)
     */
    public function save(TimelineEvent $event): void;

    /**
     * Delete a timeline event
     */
    public function delete(TimelineEventId $id): void;

    /**
     * Check if a timeline event exists
     */
    public function exists(TimelineEventId $id): bool;
}
