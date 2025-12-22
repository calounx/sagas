<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\GetTimelineEvents;

use SagaManager\Application\Query\QueryInterface;

/**
 * Get Timeline Events Query
 */
final readonly class GetTimelineEventsQuery implements QueryInterface
{
    public function __construct(
        public int $sagaId,
        public int $limit = 100,
        public int $offset = 0,
        public ?int $startTimestamp = null,
        public ?int $endTimestamp = null,
        public ?int $entityId = null
    ) {}
}
