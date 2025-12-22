<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\DeleteAttributeDefinition;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Delete Attribute Definition Handler
 *
 * Orchestrates deletion of attribute definitions.
 * Associated attribute values are cascade deleted by foreign key constraint.
 *
 * @implements CommandHandlerInterface<DeleteAttributeDefinitionCommand, void>
 */
final readonly class DeleteAttributeDefinitionHandler implements CommandHandlerInterface
{
    public function __construct(
        private AttributeDefinitionRepositoryInterface $definitionRepository
    ) {}

    /**
     * Handle the delete attribute definition command
     *
     * @param DeleteAttributeDefinitionCommand $command
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException When definition not found
     * @throws DatabaseException When deletion fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof DeleteAttributeDefinitionCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', DeleteAttributeDefinitionCommand::class, get_class($command))
            );
        }

        $id = new AttributeDefinitionId($command->id);

        // Verify exists (throws EntityNotFoundException if not)
        $this->definitionRepository->findById($id);

        try {
            $this->definitionRepository->delete($id);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to delete attribute definition: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
