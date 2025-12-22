<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\BulkSetAttributeValues;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\AttributeValue;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Domain\Repository\AttributeValueRepositoryInterface;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Infrastructure\Exception\DatabaseException;

/**
 * Bulk Set Attribute Values Handler
 *
 * Orchestrates setting multiple attribute values for an entity in a single transaction.
 * Validates all attributes before saving any, ensuring atomic updates.
 *
 * @implements CommandHandlerInterface<BulkSetAttributeValuesCommand, void>
 */
final readonly class BulkSetAttributeValuesHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository,
        private AttributeDefinitionRepositoryInterface $definitionRepository,
        private AttributeValueRepositoryInterface $valueRepository
    ) {}

    /**
     * Handle the bulk set attribute values command
     *
     * @param BulkSetAttributeValuesCommand $command
     * @throws EntityNotFoundException When entity or attribute definition not found
     * @throws ValidationException When any value fails validation
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof BulkSetAttributeValuesCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', BulkSetAttributeValuesCommand::class, get_class($command))
            );
        }

        if (empty($command->attributes)) {
            return;
        }

        $entityId = new EntityId($command->entityId);

        // Verify entity exists and get its type
        $entity = $this->entityRepository->findById($entityId);

        // Get all definitions for this entity type
        $definitions = $this->definitionRepository->findByEntityType($entity->getType());
        $definitionsByKey = [];
        foreach ($definitions as $def) {
            $definitionsByKey[$def->getAttributeKey()] = $def;
        }

        // Validate all values first (fail-fast before any persistence)
        $valuesToSave = [];
        $validationErrors = [];

        foreach ($command->attributes as $key => $value) {
            if (!isset($definitionsByKey[$key])) {
                $validationErrors[] = sprintf(
                    'Attribute "%s" not defined for entity type "%s"',
                    $key,
                    $entity->getType()->value
                );
                continue;
            }

            $definition = $definitionsByKey[$key];

            if (!$definition->validateValue($value)) {
                $error = $definition->getValidationError($value);
                $validationErrors[] = $error ?? sprintf('Value for attribute "%s" failed validation', $key);
                continue;
            }

            $valuesToSave[] = $definition->createValue($entityId, $value);
        }

        // If any validation errors, throw with all errors
        if (!empty($validationErrors)) {
            throw new ValidationException(
                'Bulk attribute validation failed: ' . implode('; ', $validationErrors)
            );
        }

        // Save all values in a single transaction
        try {
            $this->valueRepository->saveMany($valuesToSave);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to save attribute values: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
