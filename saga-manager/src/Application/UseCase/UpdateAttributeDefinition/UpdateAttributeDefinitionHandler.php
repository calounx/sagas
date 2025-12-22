<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateAttributeDefinition;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\ValidationRule;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Update Attribute Definition Handler
 *
 * Orchestrates updates to existing attribute definitions.
 *
 * @implements CommandHandlerInterface<UpdateAttributeDefinitionCommand, void>
 */
final readonly class UpdateAttributeDefinitionHandler implements CommandHandlerInterface
{
    public function __construct(
        private AttributeDefinitionRepositoryInterface $definitionRepository
    ) {}

    /**
     * Handle the update attribute definition command
     *
     * @param UpdateAttributeDefinitionCommand $command
     * @throws \SagaManager\Domain\Exception\EntityNotFoundException When definition not found
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof UpdateAttributeDefinitionCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', UpdateAttributeDefinitionCommand::class, get_class($command))
            );
        }

        $id = new AttributeDefinitionId($command->id);
        $definition = $this->definitionRepository->findById($id);

        // Apply updates
        if ($command->displayName !== null) {
            $definition->updateDisplayName($command->displayName);
        }

        if ($command->isSearchable !== null) {
            $definition->setSearchable($command->isSearchable);
        }

        if ($command->isRequired !== null) {
            $definition->setRequired($command->isRequired);
        }

        if ($command->validationRule !== null) {
            $definition->updateValidationRule(
                empty($command->validationRule) ? null : ValidationRule::fromArray($command->validationRule)
            );
        }

        if ($command->defaultValue !== null) {
            $definition->updateDefaultValue(
                $command->defaultValue === '' ? null : $command->defaultValue
            );
        }

        // Persist
        try {
            $this->definitionRepository->save($definition);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to update attribute definition: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
