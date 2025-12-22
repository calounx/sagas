<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateTimelineEvent;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\CanonDate;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;

/**
 * Create Timeline Event Handler
 *
 * @implements CommandHandlerInterface<CreateTimelineEventCommand, TimelineEvent>
 */
final readonly class CreateTimelineEventHandler implements CommandHandlerInterface
{
    public function __construct(
        private TimelineEventRepositoryInterface $repository
    ) {}

    public function handle(CommandInterface $command): TimelineEvent
    {
        if (!$command instanceof CreateTimelineEventCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', CreateTimelineEventCommand::class, get_class($command))
            );
        }

        $event = new TimelineEvent(
            sagaId: new SagaId($command->sagaId),
            canonDate: new CanonDate($command->canonDate),
            normalizedTimestamp: new NormalizedTimestamp($command->normalizedTimestamp),
            title: $command->title,
            description: $command->description,
            participants: $command->participants,
            locations: $command->locations,
            eventEntityId: $command->eventEntityId !== null
                ? new EntityId($command->eventEntityId)
                : null
        );

        $this->repository->save($event);

        return $event;
    }
}
