<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\WordPress;

use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Presentation\API\EntityController;
use SagaManager\Presentation\Admin\AdminMenuManager;

/**
 * Main Plugin Class
 *
 * Orchestrates plugin initialization and dependency injection
 */
class Plugin
{
    private ?MariaDBEntityRepository $entityRepository = null;
    private ?EntityController $entityController = null;
    private ?AdminMenuManager $adminMenuManager = null;

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
        $controller = $this->getEntityController();
        $controller->registerRoutes();
    }

    private function initAdmin(): void
    {
        $adminMenuManager = $this->getAdminMenuManager();
        $adminMenuManager->register();
    }

    /**
     * Get entity repository instance (lazy initialization)
     */
    public function getEntityRepository(): MariaDBEntityRepository
    {
        if ($this->entityRepository === null) {
            $this->entityRepository = new MariaDBEntityRepository();
        }

        return $this->entityRepository;
    }

    /**
     * Get entity controller instance (lazy initialization)
     */
    private function getEntityController(): EntityController
    {
        if ($this->entityController === null) {
            $this->entityController = new EntityController(
                $this->getEntityRepository()
            );
        }

        return $this->entityController;
    }

    /**
     * Get admin menu manager instance (lazy initialization)
     */
    private function getAdminMenuManager(): AdminMenuManager
    {
        if ($this->adminMenuManager === null) {
            $this->adminMenuManager = new AdminMenuManager(
                $this->getEntityRepository()
            );
        }

        return $this->adminMenuManager;
    }
}
