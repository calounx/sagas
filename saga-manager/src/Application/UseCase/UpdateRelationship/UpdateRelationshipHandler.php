<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateRelationship;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Update Relationship Handler
 *
 * @implements CommandHandlerInterface<UpdateRelationshipCommand, void>
 */
final readonly class UpdateRelationshipHandler implements CommandHandlerInterface
{
    public function __construct(
        private RelationshipRepositoryInterface $relationshipRepository
    ) {}

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof UpdateRelationshipCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', UpdateRelationshipCommand::class, get_class($command))
            );
        }

        $id = new RelationshipId($command->id);
        $relationship = $this->relationshipRepository->findById($id);

        // Apply updates
        if ($command->relationshipType !== null) {
            $relationship->updateRelationshipType($command->relationshipType);
        }

        if ($command->strength !== null) {
            $relationship->setStrength(new RelationshipStrength($command->strength));
        }

        // Handle validity period
        $validFrom = $relationship->getValidFrom();
        $validUntil = $relationship->getValidUntil();

        if ($command->clearValidFrom) {
            $validFrom = null;
        } elseif ($command->validFrom !== null) {
            $validFrom = new \DateTimeImmutable($command->validFrom);
        }

        if ($command->clearValidUntil) {
            $validUntil = null;
        } elseif ($command->validUntil !== null) {
            $validUntil = new \DateTimeImmutable($command->validUntil);
        }

        $relationship->setValidityPeriod($validFrom, $validUntil);

        // Handle metadata
        if ($command->clearMetadata) {
            $relationship->setMetadata(null);
        } elseif ($command->metadata !== null) {
            $relationship->setMetadata($command->metadata);
        }

        try {
            $this->relationshipRepository->save($relationship);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to update relationship: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
