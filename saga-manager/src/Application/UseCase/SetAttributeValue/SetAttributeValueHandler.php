<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\SetAttributeValue;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Domain\Repository\AttributeValueRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Set Attribute Value Handler
 *
 * Orchestrates setting a single attribute value for an entity.
 * Validates the attribute exists for the entity type and value passes validation rules.
 *
 * @implements CommandHandlerInterface<SetAttributeValueCommand, void>
 */
final readonly class SetAttributeValueHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository,
        private AttributeDefinitionRepositoryInterface $definitionRepository,
        private AttributeValueRepositoryInterface $valueRepository
    ) {}

    /**
     * Handle the set attribute value command
     *
     * @param SetAttributeValueCommand $command
     * @throws EntityNotFoundException When entity or attribute definition not found
     * @throws ValidationException When value fails validation
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof SetAttributeValueCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', SetAttributeValueCommand::class, get_class($command))
            );
        }

        $entityId = new EntityId($command->entityId);

        // Verify entity exists and get its type
        $entity = $this->entityRepository->findById($entityId);

        // Find attribute definition for this entity type
        $definition = $this->definitionRepository->findByTypeAndKey(
            $entity->getType(),
            $command->attributeKey
        );

        if ($definition === null) {
            throw new EntityNotFoundException(
                sprintf(
                    'Attribute "%s" not defined for entity type "%s"',
                    $command->attributeKey,
                    $entity->getType()->value
                )
            );
        }

        // Validate value
        if (!$definition->validateValue($command->value)) {
            $error = $definition->getValidationError($command->value);
            throw new ValidationException(
                $error ?? sprintf('Value for attribute "%s" failed validation', $command->attributeKey)
            );
        }

        // Create and save value
        $attributeValue = $definition->createValue($entityId, $command->value);

        try {
            $this->valueRepository->save($attributeValue);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to save attribute value: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
