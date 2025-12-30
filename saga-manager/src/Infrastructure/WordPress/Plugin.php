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
use SagaManager\Domain\Entity\SagaEntity;
use SagaManager\Infrastructure\WordPress\SagaEntityPostType;
use SagaManager\Infrastructure\WordPress\SagaEntityMetaBox;
use SagaManager\Infrastructure\WordPress\SagaTypeTaxonomy;

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

        // Register custom post type and taxonomy
        add_action('init', [$this, 'registerPostTypeAndTaxonomy']);

        // Register bidirectional sync hooks
        $this->registerSyncHooks();

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

    /**
     * Register custom post type and taxonomy
     */
    public function registerPostTypeAndTaxonomy(): void
    {
        // Register taxonomy first (before post type)
        $taxonomy = $this->container->get(SagaTypeTaxonomy::class);
        $taxonomy->register();

        // Register custom post type
        $postType = $this->container->get(SagaEntityPostType::class);
        $postType->register();
    }

    /**
     * Register bidirectional sync hooks
     */
    private function registerSyncHooks(): void
    {
        $postType = $this->container->get(SagaEntityPostType::class);

        // WordPress → Saga Entities (save_post hook)
        add_action('save_post_saga_entity', [$postType, 'syncToDatabase'], 10, 2);

        // Saga Entities → WordPress (custom action hook)
        add_action('saga_entity_saved', function (SagaEntity $entity) use ($postType) {
            $postType->syncFromEntity($entity);
        }, 10, 1);
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

        // Register meta box
        $metaBox = $this->container->get(SagaEntityMetaBox::class);
        $metaBox->register();
    }

    /**
     * Get the service container
     */
    public function getContainer(): ServiceContainer
    {
        return $this->container;
    }
}
