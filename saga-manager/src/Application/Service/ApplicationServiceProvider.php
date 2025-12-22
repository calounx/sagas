<?php
declare(strict_types=1);

namespace SagaManager\Application\Service;

use SagaManager\Application\UseCase\CreateEntity\CreateEntityCommand;
use SagaManager\Application\UseCase\CreateEntity\CreateEntityHandler;
use SagaManager\Application\UseCase\DeleteEntity\DeleteEntityCommand;
use SagaManager\Application\UseCase\DeleteEntity\DeleteEntityHandler;
use SagaManager\Application\UseCase\GetEntity\GetEntityHandler;
use SagaManager\Application\UseCase\GetEntity\GetEntityQuery;
use SagaManager\Application\UseCase\SearchEntities\SearchEntitiesHandler;
use SagaManager\Application\UseCase\SearchEntities\SearchEntitiesQuery;
use SagaManager\Application\UseCase\UpdateEntity\UpdateEntityCommand;
use SagaManager\Application\UseCase\UpdateEntity\UpdateEntityHandler;
use SagaManager\Application\UseCase\CreateRelationship\CreateRelationshipCommand;
use SagaManager\Application\UseCase\CreateRelationship\CreateRelationshipHandler;
use SagaManager\Application\UseCase\UpdateRelationship\UpdateRelationshipCommand;
use SagaManager\Application\UseCase\UpdateRelationship\UpdateRelationshipHandler;
use SagaManager\Application\UseCase\DeleteRelationship\DeleteRelationshipCommand;
use SagaManager\Application\UseCase\DeleteRelationship\DeleteRelationshipHandler;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;

/**
 * Application Service Provider
 *
 * Configures and registers all application services, handlers, and buses.
 * Follows Dependency Injection and Service Locator patterns.
 * Accepts repository interfaces, not implementations.
 */
final class ApplicationServiceProvider
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;

    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository,
        private readonly ?RelationshipRepositoryInterface $relationshipRepository = null
    ) {
        $this->commandBus = new CommandBus();
        $this->queryBus = new QueryBus();
        $this->registerHandlers();
    }

    public function getCommandBus(): CommandBus
    {
        return $this->commandBus;
    }

    public function getQueryBus(): QueryBus
    {
        return $this->queryBus;
    }

    /**
     * Register all command and query handlers
     */
    private function registerHandlers(): void
    {
        $this->registerEntityHandlers();

        if ($this->relationshipRepository !== null) {
            $this->registerRelationshipHandlers();
        }
    }

    /**
     * Register entity-related handlers
     */
    private function registerEntityHandlers(): void
    {
        // Command handlers
        $this->commandBus->register(
            CreateEntityCommand::class,
            new CreateEntityHandler($this->entityRepository)
        );

        $this->commandBus->register(
            UpdateEntityCommand::class,
            new UpdateEntityHandler($this->entityRepository)
        );

        $this->commandBus->register(
            DeleteEntityCommand::class,
            new DeleteEntityHandler($this->entityRepository)
        );

        // Query handlers
        $this->queryBus->register(
            GetEntityQuery::class,
            new GetEntityHandler($this->entityRepository)
        );

        $this->queryBus->register(
            SearchEntitiesQuery::class,
            new SearchEntitiesHandler($this->entityRepository)
        );
    }

    /**
     * Register relationship-related handlers
     */
    private function registerRelationshipHandlers(): void
    {
        if ($this->relationshipRepository === null) {
            return;
        }

        $this->commandBus->register(
            CreateRelationshipCommand::class,
            new CreateRelationshipHandler($this->entityRepository, $this->relationshipRepository)
        );

        $this->commandBus->register(
            UpdateRelationshipCommand::class,
            new UpdateRelationshipHandler($this->relationshipRepository)
        );

        $this->commandBus->register(
            DeleteRelationshipCommand::class,
            new DeleteRelationshipHandler($this->relationshipRepository)
        );
    }
}
