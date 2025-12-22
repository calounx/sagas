<?php
declare(strict_types=1);

namespace SagaManager\Application\UseCase\CreateEntity;

use SagaManager\Application\Command\CommandHandlerInterface;
use SagaManager\Application\Command\CommandInterface;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Entity\ImportanceScore;
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Infrastructure\Exception\DatabaseException;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Create Entity Handler
 *
 * Application service that orchestrates entity creation.
 * Validates input, creates domain entity, persists via repository.
 *
 * @implements CommandHandlerInterface<CreateEntityCommand, EntityId>
 */
final readonly class CreateEntityHandler implements CommandHandlerInterface
{
    public function __construct(
        private EntityRepositoryInterface $entityRepository
    ) {
    }

    /**
     * Handle the create entity command
     *
     * @param CreateEntityCommand $command
     * @return EntityId The ID of the created entity
     * @throws ValidationException When input validation fails
     * @throws DuplicateEntityException When entity already exists
     * @throws DatabaseException When persistence fails
     */
    public function handle(CommandInterface $command): EntityId
    {
        if (!$command instanceof CreateEntityCommand) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', CreateEntityCommand::class, get_class($command))
            );
        }

        // Check for duplicate by saga and canonical name
        $existingEntity = $this->entityRepository->findBySagaAndName(
            new SagaId($command->sagaId),
            $command->canonicalName
        );

        if ($existingEntity !== null) {
            throw new DuplicateEntityException(
                sprintf(
                    'Entity with name "%s" already exists in saga %d',
                    $command->canonicalName,
                    $command->sagaId
                )
            );
        }

        // Check for duplicate by slug
        $existingBySlug = $this->entityRepository->findBySlug($command->slug);
        if ($existingBySlug !== null) {
            throw new DuplicateEntityException(
                sprintf('Entity with slug "%s" already exists', $command->slug)
            );
        }

        // Create domain entity
        $entity = new SagaEntity(
            sagaId: new SagaId($command->sagaId),
            type: EntityType::from($command->type),
            canonicalName: $command->canonicalName,
            slug: $command->slug,
            importanceScore: $command->importanceScore !== null
                ? new ImportanceScore($command->importanceScore)
                : null
        );

        // Link to WordPress post if provided
        if ($command->wpPostId !== null) {
            $entity->linkToWpPost($command->wpPostId);
        }

        // Persist entity
        try {
            $this->entityRepository->save($entity);
        } catch (\Exception $e) {
            throw new DatabaseException(
                sprintf('Failed to save entity: %s', $e->getMessage()),
                0,
                $e
            );
        }

        $entityId = $entity->getId();
        if ($entityId === null) {
            throw new DatabaseException('Entity ID not set after save');
        }

        return $entityId;
    }
}
