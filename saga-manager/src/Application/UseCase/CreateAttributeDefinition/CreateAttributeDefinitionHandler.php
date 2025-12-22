<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateAttributeDefinition;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\AttributeDefinition;
use SagaManager\Domain\Entity\AttributeDefinitionId;
use SagaManager\Domain\Entity\DataType;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ValidationRule;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Create Attribute Definition Handler
 *
 * Orchestrates the creation of new attribute definitions.
 *
 * @implements CommandHandlerInterface<CreateAttributeDefinitionCommand, AttributeDefinitionId>
 */
final readonly class CreateAttributeDefinitionHandler implements CommandHandlerInterface
{
    public function __construct(
        private AttributeDefinitionRepositoryInterface $definitionRepository
    ) {}

    /**
     * Handle the create attribute definition command
     *
     * @param CreateAttributeDefinitionCommand $command
     * @return AttributeDefinitionId The ID of the created definition
     * @throws DuplicateEntityException When attribute key already exists for entity type
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): AttributeDefinitionId
    {
        if (!$command instanceof CreateAttributeDefinitionCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', CreateAttributeDefinitionCommand::class, get_class($command))
            );
        }

        $entityType = EntityType::from($command->entityType);
        $dataType = DataType::from($command->dataType);

        // Check for duplicate key
        if ($this->definitionRepository->keyExists($entityType, $command->attributeKey)) {
            throw new DuplicateEntityException(
                sprintf(
                    'Attribute key "%s" already exists for entity type "%s"',
                    $command->attributeKey,
                    $entityType->value
                )
            );
        }

        // Create domain entity
        $definition = new AttributeDefinition(
            entityType: $entityType,
            attributeKey: $command->attributeKey,
            displayName: $command->displayName,
            dataType: $dataType,
            isSearchable: $command->isSearchable,
            isRequired: $command->isRequired,
            validationRule: ValidationRule::fromArray($command->validationRule),
            defaultValue: $command->defaultValue
        );

        // Persist
        try {
            $this->definitionRepository->save($definition);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to save attribute definition: %s', $e->getMessage()),
                0,
                $e
            );
        }

        $id = $definition->getId();
        if ($id === null) {
            throw new DatabaseException('Attribute definition ID not set after save');
        }

        return $id;
    }
}
