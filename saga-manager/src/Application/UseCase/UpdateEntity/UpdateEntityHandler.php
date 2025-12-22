<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\UpdateEntity;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Infrastructure\Exception\DatabaseException;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Update Entity Handler
 *
 * Application service that orchestrates entity updates.
 * Validates input, updates domain entity, persists via repository.
 *
 * @implements CommandHandlerInterface<UpdateEntityCommand, void>
 */
final readonly class UpdateEntityHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository
    ) {
    }

    /**
     * Handle the update entity command
     *
     * @param UpdateEntityCommand $command
     * @return void
     * @throws ValidationException When input validation fails
     * @throws EntityNotFoundException When entity not found
     * @throws DuplicateEntityException When slug conflicts with another entity
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): void
    {
        if (!$command instanceof UpdateEntityCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', UpdateEntityCommand::class, get_class($command))
            );
        }

        if (!$command->hasChanges()) {
            throw new ValidationException('No changes provided');
        }

        $entityId = new EntityId($command->entityId);

        // Fetch existing entity
        $entity = $this->entityRepository->findById($entityId);

        // Check for slug conflicts if updating slug
        if ($command->slug !== null && $command->slug !== $entity->getSlug()) {
            $existingBySlug = $this->entityRepository->findBySlug($command->slug);
            if ($existingBySlug !== null && !$existingBySlug->getId()->equals($entityId)) {
                throw new DuplicateEntityException(
                    sprintf('Entity with slug "%s" already exists', $command->slug)
                );
            }
            $entity->updateSlug($command->slug);
        }

        // Apply updates
        if ($command->canonicalName !== null) {
            $entity->updateCanonicalName($command->canonicalName);
        }

        if ($command->importanceScore !== null) {
            $entity->setImportanceScore(new ImportanceScore($command->importanceScore));
        }

        // Persist changes
        try {
            $this->entityRepository->save($entity);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to update entity: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
