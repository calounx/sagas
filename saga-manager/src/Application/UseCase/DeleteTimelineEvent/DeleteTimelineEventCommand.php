<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteTimelineEvent;

use SagaManager\Application\Command\CommandInterface;

/**
 * Delete Timeline Event Command
 */
final readonly class DeleteTimelineEventCommand implements CommandInterface
{
    public function __construct(
        public int $id
    ) {}
}
