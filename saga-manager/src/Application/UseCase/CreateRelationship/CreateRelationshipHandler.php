<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateRelationship;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Create Relationship Handler
 *
 * @implements CommandHandlerInterface<CreateRelationshipCommand, RelationshipId>
 */
final readonly class CreateRelationshipHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository,
        private RelationshipRepositoryInterface $relationshipRepository
    ) {}

    public function handle(CommandInterface $command): RelationshipId
    {
        if (!$command instanceof CreateRelationshipCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', CreateRelationshipCommand::class, get_class($command))
            );
        }

        $sourceId = new EntityId($command->sourceEntityId);
        $targetId = new EntityId($command->targetEntityId);

        // Verify both entities exist
        $this->entityRepository->findById($sourceId);
        $this->entityRepository->findById($targetId);

        // Check for duplicate relationship
        if ($this->relationshipRepository->existsBetween($sourceId, $targetId, $command->relationshipType)) {
            throw new DuplicateEntityException(
                sprintf(
                    'Relationship of type "%s" already exists between entities %d and %d',
                    $command->relationshipType,
                    $sourceId->value(),
                    $targetId->value()
                )
            );
        }

        $relationship = new Relationship(
            sourceEntityId: $sourceId,
            targetEntityId: $targetId,
            relationshipType: $command->relationshipType,
            strength: $command->strength !== null
                ? new RelationshipStrength($command->strength)
                : null,
            validFrom: $command->validFrom !== null
                ? new \DateTimeImmutable($command->validFrom)
                : null,
            validUntil: $command->validUntil !== null
                ? new \DateTimeImmutable($command->validUntil)
                : null,
            metadata: $command->metadata
        );

        try {
            $this->relationshipRepository->save($relationship);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to save relationship: %s', $e->getMessage()),
                0,
                $e
            );
        }

        $id = $relationship->getId();
        if ($id === null) {
            throw new DatabaseException('Relationship ID not set after save');
        }

        return $id;
    }
}
