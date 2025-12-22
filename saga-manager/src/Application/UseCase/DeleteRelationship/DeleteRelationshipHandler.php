<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteRelationship;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Delete Relationship Handler
 *
 * @implements CommandHandlerInterface<DeleteRelationshipCommand, void>
 */
final readonly class DeleteRelationshipHandler implements CommandHandlerInterface
{
    public function __construct(
        private RelationshipRepositoryInterface $relationshipRepository
    ) {}

    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof DeleteRelationshipCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', DeleteRelationshipCommand::class, get_class($command))
            );
        }

        $id = new RelationshipId($command->id);

        // Verify exists
        $this->relationshipRepository->findById($id);

        try {
            $this->relationshipRepository->delete($id);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to delete relationship: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
