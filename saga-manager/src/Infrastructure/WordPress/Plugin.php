<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

use SagaManager\Application\Service\CommandBus;
use SagaManager\Application\Service\QueryBus;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Infrastructure\Container\ServiceContainer;
use SagaManager\Presentation\API\EntityController;
use SagaManager\Presentation\API\RelationshipController;
use SagaManager\Presentation\Admin\AdminMenuManager;

/**
 * Main Plugin Class
 *
 * Orchestrates plugin initialization and dependency injection
 * using the ServiceContainer for proper abstraction.
 */
class Plugin
{
    private ServiceContainer $container;

    public function __construct(?ServiceContainer $container = null)
    {
        $this->container = $container ?? ServiceContainer::getInstance();
    }

    public function init(): void
    {
        // Load text domain for translations
        add_action('init', [$this, 'loadTextDomain']);

        // Initialize admin interface
        if (is_admin()) {
            $this->initAdmin();
        }

        // Register REST API routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'saga-manager',
            false,
            dirname(plugin_basename(SAGA_MANAGER_PLUGIN_FILE)) . '/languages'
        );
    }

    public function registerRestRoutes(): void
    {
        // Entity controller
        $entityController = new EntityController(
            $this->container->get(CommandBus::class),
            $this->container->get(QueryBus::class)
        );
        $entityController->registerRoutes();

        // Relationship controller
        $relationshipController = new RelationshipController(
            $this->container->get(CommandBus::class),
            $this->container->get(RelationshipRepositoryInterface::class),
            $this->container->get(EntityRepositoryInterface::class)
        );
        $relationshipController->registerRoutes();
    }

    private function initAdmin(): void
    {
        $adminMenuManager = new AdminMenuManager(
            $this->container->get(EntityRepositoryInterface::class)
        );
        $adminMenuManager->register();
    }

    /**
     * Get the service container
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }
}
