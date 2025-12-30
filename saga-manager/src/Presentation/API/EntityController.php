<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Application\DTO\EntityDTO;
use SagaManager\Application\DTO\SearchEntitiesResult;
use SagaManager\Application\Service\CommandBus;
use SagaManager\Application\Service\QueryBus;
use SagaManager\Application\UseCase\CreateEntity\CreateEntityCommand;
use SagaManager\Application\UseCase\DeleteEntity\DeleteEntityCommand;
use SagaManager\Application\UseCase\GetEntity\GetEntityQuery;
use SagaManager\Application\UseCase\SearchEntities\SearchEntitiesQuery;
use SagaManager\Application\UseCase\UpdateEntity\UpdateEntityCommand;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EntityType;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;

/**
 * REST API Controller for Saga Entities
 *
 * Handles HTTP requests and delegates to Application layer via CommandBus/QueryBus.
 * Follows hexagonal architecture by not depending on infrastructure.
 */
class EntityController
{
    use RateLimitMiddleware;

    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus
    ) {
    }

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/entities', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getIndexArgs(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getCreateArgs(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/entities/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getUpdateArgs(),
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param) && $param > 0;
                        },
                    ],
                ],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/entities
     * List entities with pagination and filtering
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        // Apply rate limiting for search operations
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_search');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck;
        }

        try {
            $sagaId = (int) $request->get_param('saga_id');
            $entityType = $request->get_param('type');
            $page = max(1, (int) $request->get_param('page'));
            $perPage = min(100, max(1, (int) $request->get_param('per_page')));
            $offset = ($page - 1) * $perPage;

            $query = new SearchEntitiesQuery(
                sagaId: $sagaId,
                type: $entityType,
                limit: $perPage,
                offset: $offset
            );

            /** @var SearchEntitiesResult $result */
            $result = $this->queryBus->dispatch($query);

            $data = array_map(
                fn(EntityDTO $dto) => $dto->toArray(),
                $result->entities
            );

            $response = new \WP_REST_Response($data, 200);
            $response->header('X-WP-Total', (string) $result->total);
            $response->header('X-WP-TotalPages', (string) ceil($result->total / $perPage));

            return $response;

        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}
     * Get single entity by ID
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $query = new GetEntityQuery(
                entityId: (int) $request->get_param('id')
            );

            /** @var EntityDTO $result */
            $result = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($result->toArray(), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/entities
     * Create new entity
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        // Apply rate limiting for create operations
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_create');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck;
        }

        try {
            $canonicalName = sanitize_text_field($request->get_param('canonical_name'));
            $slug = sanitize_title($request->get_param('slug') ?: $canonicalName);

            $command = new CreateEntityCommand(
                sagaId: (int) $request->get_param('saga_id'),
                type: $request->get_param('type'),
                canonicalName: $canonicalName,
                slug: $slug,
                importanceScore: $request->get_param('importance_score')
            );

            /** @var EntityId $entityId */
            $entityId = $this->commandBus->dispatch($command);

            // Fetch the created entity to return
            $query = new GetEntityQuery(entityId: $entityId->value());
            /** @var EntityDTO $entity */
            $entity = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($entity->toArray(), 201);

        } catch (DuplicateEntityException $e) {
            return new \WP_REST_Response(
                ['error' => 'Duplicate entity', 'message' => $e->getMessage()],
                409
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/entities/{id}
     * Update existing entity
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        // Apply rate limiting for update operations
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_update');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck;
        }

        try {
            $entityId = (int) $request->get_param('id');

            $command = new UpdateEntityCommand(
                entityId: $entityId,
                canonicalName: $request->has_param('canonical_name')
                    ? sanitize_text_field($request->get_param('canonical_name'))
                    : null,
                slug: $request->has_param('slug')
                    ? sanitize_title($request->get_param('slug'))
                    : null,
                importanceScore: $request->has_param('importance_score')
                    ? (int) $request->get_param('importance_score')
                    : null
            );

            $this->commandBus->dispatch($command);

            // Fetch the updated entity to return
            $query = new GetEntityQuery(entityId: $entityId);
            /** @var EntityDTO $entity */
            $entity = $this->queryBus->dispatch($query);

            return new \WP_REST_Response($entity->toArray(), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /wp-json/saga/v1/entities/{id}
     * Delete entity
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        // Apply rate limiting for delete operations
        $rateLimitCheck = $this->checkRateLimit($request, 'entity_delete');
        if ($rateLimitCheck instanceof \WP_REST_Response) {
            return $rateLimitCheck;
        }

        try {
            $entityId = (int) $request->get_param('id');

            $command = new DeleteEntityCommand(entityId: $entityId);
            $this->commandBus->dispatch($command);

            return new \WP_REST_Response(
                ['message' => 'Entity deleted successfully', 'id' => $entityId],
                200
            );

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Permission callback for read operations
     */
    public function checkReadPermission(): bool
    {
        return current_user_can('read');
    }

    /**
     * Permission callback for write operations
     */
    public function checkWritePermission(): bool
    {
        return current_user_can('edit_posts');
    }

    /**
     * Get arguments for index endpoint
     */
    private function getIndexArgs(): array
    {
        return [
            'saga_id' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => array_map(fn($type) => $type->value, EntityType::cases()),
                'sanitize_callback' => 'sanitize_key',
            ],
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get arguments for create endpoint
     */
    private function getCreateArgs(): array
    {
        return [
            'saga_id' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'type' => [
                'required' => true,
                'type' => 'string',
                'enum' => array_map(fn($type) => $type->value, EntityType::cases()),
                'sanitize_callback' => 'sanitize_key',
            ],
            'canonical_name' => [
                'required' => true,
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'slug' => [
                'required' => false,
                'type' => 'string',
                'maxLength' => 255,
                'sanitize_callback' => 'sanitize_title',
            ],
            'importance_score' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100,
                'default' => 50,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get arguments for update endpoint
     */
    private function getUpdateArgs(): array
    {
        return [
            'id' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'canonical_name' => [
                'required' => false,
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 255,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'slug' => [
                'required' => false,
                'type' => 'string',
                'maxLength' => 255,
                'sanitize_callback' => 'sanitize_title',
            ],
            'importance_score' => [
                'required' => false,
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Handle exceptions and return appropriate error response
     */
    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][ERROR] REST API error: ' . $e->getMessage());

        return new \WP_REST_Response(
            [
                'error' => 'Internal server error',
                'message' => WP_DEBUG ? $e->getMessage() : 'An error occurred',
            ],
            500
        );
    }
}
