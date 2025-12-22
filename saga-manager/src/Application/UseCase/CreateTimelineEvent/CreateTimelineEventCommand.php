<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateTimelineEvent;

use SagaManager\Application\Command\CommandInterface;

/**
 * Create Timeline Event Command
 */
final readonly class CreateTimelineEventCommand implements CommandInterface
{
    /**
     * @param int[] $participants
     * @param int[] $locations
     */
    public function __construct(
        public int $sagaId,
        public string $canonDate,
        public int $normalizedTimestamp,
        public string $title,
        public ?string $description = null,
        public array $participants = [],
        public array $locations = [],
        public ?int $eventEntityId = null
    ) {}
}
