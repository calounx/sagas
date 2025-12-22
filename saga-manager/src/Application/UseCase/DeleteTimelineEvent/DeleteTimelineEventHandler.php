<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteTimelineEvent;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;

/**
 * Delete Timeline Event Handler
 *
 * @implements CommandHandlerInterface<DeleteTimelineEventCommand, void>
 */
final readonly class DeleteTimelineEventHandler implements CommandHandlerInterface
{
    public function __construct(
        private TimelineEventRepositoryInterface $repository
    ) {}

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof DeleteTimelineEventCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', DeleteTimelineEventCommand::class, get_class($command))
            );
        }

        $id = new TimelineEventId($command->id);

        // Verify exists
        $this->repository->findById($id);

        $this->repository->delete($id);
    }
}
