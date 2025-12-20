<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Rest;

use SagaManager\Contract\ApiEndpoints;
use SagaManagerCore\Presentation\Rest\Controller\EntityController;
use SagaManagerCore\Presentation\Rest\Controller\SagaController;
use SagaManagerCore\Presentation\Rest\Controller\RelationshipController;
use SagaManagerCore\Presentation\Rest\Controller\TimelineController;
use SagaManagerCore\Presentation\Rest\Controller\SearchController;
use SagaManagerCore\Presentation\Rest\Controller\HealthController;

/**
 * Registers all REST API routes for the backend plugin
 */
final class RestApiManager
{
    private array $controllers = [];

    public function __construct()
    {
        $this->controllers = [
            new SagaController(),
            new EntityController(),
            new RelationshipController(),
            new TimelineController(),
            new SearchController(),
            new HealthController(),
        ];
    }

    public function registerRoutes(): void
    {
        foreach ($this->controllers as $controller) {
            $controller->registerRoutes();
        }

        // Add API discovery endpoint
        $this->registerDiscoveryEndpoint();
    }

    /**
     * Discovery endpoint for frontend plugin to verify API availability
     */
    private function registerDiscoveryEndpoint(): void
    {
        register_rest_route(ApiEndpoints::NAMESPACE, '/', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleDiscovery'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleDiscovery(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'name' => 'Saga Manager API',
            'version' => ApiEndpoints::VERSION,
            'namespace' => ApiEndpoints::NAMESPACE,
            'routes' => [
                'sagas' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::SAGAS),
                'entities' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::ENTITIES),
                'relationships' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::RELATIONSHIPS),
                'timeline' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::TIMELINE),
                'search' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::SEARCH),
                'health' => rest_url(ApiEndpoints::NAMESPACE . ApiEndpoints::HEALTH),
            ],
            'authentication' => [
                'methods' => ['cookie', 'application_password', 'jwt'],
                'required_for' => ['POST', 'PUT', 'PATCH', 'DELETE'],
            ],
        ], 200);
    }
}
