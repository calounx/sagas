<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateTimelineEvent;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\CanonDate;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;

/**
 * Update Timeline Event Handler
 *
 * @implements CommandHandlerInterface<UpdateTimelineEventCommand, TimelineEvent>
 */
final readonly class UpdateTimelineEventHandler implements CommandHandlerInterface
{
    public function __construct(
        private TimelineEventRepositoryInterface $repository
    ) {}

    public function handle(CommandInterface $command): TimelineEvent
    {
        if (!$command instanceof UpdateTimelineEventCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', UpdateTimelineEventCommand::class, get_class($command))
            );
        }

        $event = $this->repository->findById(new TimelineEventId($command->id));

        if ($command->title !== null) {
            $event->updateTitle($command->title);
        }

        if ($command->canonDate !== null && $command->normalizedTimestamp !== null) {
            $event->updateDate(
                new CanonDate($command->canonDate),
                new NormalizedTimestamp($command->normalizedTimestamp)
            );
        }

        if ($command->description !== null) {
            $event->setDescription($command->description);
        }

        if ($command->participants !== null) {
            $event->setParticipants($command->participants);
        }

        if ($command->locations !== null) {
            $event->setLocations($command->locations);
        }

        if ($command->clearEventEntityId) {
            $event->setEventEntityId(null);
        } elseif ($command->eventEntityId !== null) {
            $event->setEventEntityId(new EntityId($command->eventEntityId));
        }

        $this->repository->save($event);

        return $event;
    }
}
