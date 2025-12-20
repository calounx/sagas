<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteEntity;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\DatabaseException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Delete Entity Handler
 *
 * Application service that orchestrates entity deletion.
 * Verifies entity exists before deletion.
 *
 * @implements CommandHandlerInterface<DeleteEntityCommand, void>
 */
final readonly class DeleteEntityHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository
    ) {
    }

    /**
     * Handle the delete entity command
     *
     * @param DeleteEntityCommand $command
     * @return void
     * @throws ValidationException When entity ID is invalid
     * @throws EntityNotFoundException When entity not found
     * @throws DatabaseException When deletion fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof DeleteEntityCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', DeleteEntityCommand::class, get_class($command))
            );
        }

        if ($command->entityId <= 0) {
            throw new ValidationException('Entity ID must be positive');
        }

        $entityId = new EntityId($command->entityId);

        // Verify entity exists before deletion
        if (!$this->entityRepository->exists($entityId)) {
            throw new EntityNotFoundException(
                sprintf('Entity with ID %d not found', $command->entityId)
            );
        }

        // Perform deletion
        try {
            $this->entityRepository->delete($entityId);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to delete entity: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
