<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateTimelineEvent;

use SagaManager\Application\Command\CommandInterface;

/**
 * Update Timeline Event Command
 */
final readonly class UpdateTimelineEventCommand implements CommandInterface
{
    /**
     * @param int[]|null $participants
     * @param int[]|null $locations
     */
    public function __construct(
        public int $id,
        public ?string $canonDate = null,
        public ?int $normalizedTimestamp = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?array $participants = null,
        public ?array $locations = null,
        public ?int $eventEntityId = null,
        public bool $clearEventEntityId = false
    ) {}
}
