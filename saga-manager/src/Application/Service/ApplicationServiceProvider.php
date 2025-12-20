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
use SagaManager\Domain\Repository\EntityRepositoryInterface;

/**
 * Application Service Provider
 *
 * Configures and registers all application services, handlers, and buses.
 * Follows Dependency Injection and Service Locator patterns.
 */
final class ApplicationServiceProvider
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;

    public function __construct(
        private readonly EntityRepositoryInterface $entityRepository
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
}
