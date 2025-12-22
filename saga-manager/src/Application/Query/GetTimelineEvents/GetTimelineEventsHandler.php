<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetTimelineEvents;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;

/**
 * Get Timeline Events Handler
 *
 * @implements QueryHandlerInterface<GetTimelineEventsQuery, TimelineEvent[]>
 */
final readonly class GetTimelineEventsHandler implements QueryHandlerInterface
{
    public function __construct(
        private TimelineEventRepositoryInterface $repository
    ) {}

    /**
     * @return TimelineEvent[]
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof GetTimelineEventsQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', GetTimelineEventsQuery::class, get_class($query))
            );
        }

        $sagaId = new SagaId($query->sagaId);

        // If entity filter is provided
        if ($query->entityId !== null) {
            return $this->repository->findByEntity(new EntityId($query->entityId));
        }

        // If time range is provided
        if ($query->startTimestamp !== null && $query->endTimestamp !== null) {
            return $this->repository->findByTimeRange(
                $sagaId,
                new NormalizedTimestamp($query->startTimestamp),
                new NormalizedTimestamp($query->endTimestamp)
            );
        }

        // Default: get all events for saga
        return $this->repository->findBySaga($sagaId, $query->limit, $query->offset);
    }
}
