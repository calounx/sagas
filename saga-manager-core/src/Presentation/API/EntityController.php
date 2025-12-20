<?php
declare(strict_types=1);

namespace SagaManagerCore\Presentation\API;

use SagaManagerCore\Domain\Entity\EntityId;
use SagaManagerCore\Domain\Entity\SagaId;
use SagaManagerCore\Domain\Entity\EntityType;
use SagaManagerCore\Domain\Entity\ImportanceScore;
use SagaManagerCore\Domain\Entity\SagaEntity;
use SagaManagerCore\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManagerCore\Domain\Exception\EntityNotFoundException;
use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * REST API Controller for Saga Entities
 *
 * Handles HTTP requests and converts to domain operations
 */
class EntityController
{
    private const NAMESPACE = 'saga/v1';

    private MariaDBEntityRepository $repository;

    public function __construct(MariaDBEntityRepository $repository)
    {
        $this->repository = $repository;
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

        register_rest_route(self::NAMESPACE, '/entities/search', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => $this->getSearchArgs(),
        ]);

        register_rest_route(self::NAMESPACE, '/entities/by-slug/(?P<slug>[a-z0-9-]+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'showBySlug'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_title',
                ],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/entities
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $saga_id = $request->get_param('saga_id');
            $entity_type = $request->get_param('type');
            $page = max(1, (int) $request->get_param('page'));
            $per_page = min(100, max(1, (int) $request->get_param('per_page')));
            $offset = ($page - 1) * $per_page;

            $saga_id_obj = new SagaId($saga_id);

            if ($entity_type) {
                $type_obj = EntityType::from($entity_type);
                $entities = $this->repository->findBySagaAndType(
                    $saga_id_obj,
                    $type_obj,
                    $per_page,
                    $offset
                );
            } else {
                $entities = $this->repository->findBySaga(
                    $saga_id_obj,
                    $per_page,
                    $offset
                );
            }

            $total = $this->repository->countBySaga($saga_id_obj);

            $data = array_map(fn(SagaEntity $e) => $e->toArray(), $entities);

            $response = new \WP_REST_Response($data, 200);
            $response->header('X-WP-Total', (string) $total);
            $response->header('X-WP-TotalPages', (string) ceil($total / $per_page));

            return $response;

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new EntityId((int) $request->get_param('id'));
            $entity = $this->repository->findById($id);

            return new \WP_REST_Response($entity->toArray(), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'entity_not_found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/by-slug/{slug}
     */
    public function showBySlug(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $slug = $request->get_param('slug');
            $entity = $this->repository->findBySlug($slug);

            if ($entity === null) {
                return new \WP_REST_Response(
                    ['error' => 'entity_not_found', 'message' => 'Entity not found'],
                    404
                );
            }

            return new \WP_REST_Response($entity->toArray(), 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/search
     */
    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $saga_id = new SagaId($request->get_param('saga_id'));
            $query = $request->get_param('q');
            $limit = min(50, max(1, (int) $request->get_param('limit')));

            $entities = $this->repository->searchByName($saga_id, $query, $limit);
            $data = array_map(fn(SagaEntity $e) => $e->toArray(), $entities);

            return new \WP_REST_Response($data, 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/entities
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $saga_id = new SagaId($request->get_param('saga_id'));
            $type = EntityType::from($request->get_param('type'));
            $canonical_name = sanitize_text_field($request->get_param('canonical_name'));
            $slug = sanitize_title($request->get_param('slug') ?: $canonical_name);
            $importance_score = new ImportanceScore(
                $request->get_param('importance_score') ?? 50
            );

            // Check for duplicate
            $existing = $this->repository->findBySagaAndName($saga_id, $canonical_name);
            if ($existing) {
                return new \WP_REST_Response(
                    ['error' => 'duplicate_entity', 'message' => 'Entity with this name already exists'],
                    409
                );
            }

            $entity = new SagaEntity(
                sagaId: $saga_id,
                type: $type,
                canonicalName: $canonical_name,
                slug: $slug,
                importanceScore: $importance_score
            );

            $this->repository->save($entity);

            // Allow other plugins to react to entity creation
            do_action('saga_manager_core_entity_created', $entity);

            return new \WP_REST_Response($entity->toArray(), 201);

        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'validation_error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/entities/{id}
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new EntityId((int) $request->get_param('id'));
            $entity = $this->repository->findById($id);

            if ($request->has_param('canonical_name')) {
                $entity->updateCanonicalName(
                    sanitize_text_field($request->get_param('canonical_name'))
                );
            }

            if ($request->has_param('slug')) {
                $entity->updateSlug(
                    sanitize_title($request->get_param('slug'))
                );
            }

            if ($request->has_param('importance_score')) {
                $entity->setImportanceScore(
                    new ImportanceScore($request->get_param('importance_score'))
                );
            }

            $this->repository->save($entity);

            // Allow other plugins to react to entity update
            do_action('saga_manager_core_entity_updated', $entity);

            return new \WP_REST_Response($entity->toArray(), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'entity_not_found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'validation_error', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /wp-json/saga/v1/entities/{id}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new EntityId((int) $request->get_param('id'));
            $entity = $this->repository->findById($id);

            // Allow other plugins to react before deletion
            do_action('saga_manager_core_entity_before_delete', $entity);

            $this->repository->delete($id);

            // Allow other plugins to react after deletion
            do_action('saga_manager_core_entity_deleted', $id->value());

            return new \WP_REST_Response(
                ['message' => 'Entity deleted successfully', 'id' => $id->value()],
                200
            );

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'entity_not_found', 'message' => $e->getMessage()],
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

    private function getSearchArgs(): array
    {
        return [
            'saga_id' => [
                'required' => true,
                'type' => 'integer',
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'q' => [
                'required' => true,
                'type' => 'string',
                'minLength' => 1,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 50,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][ERROR] REST API error: ' . $e->getMessage());

        return new \WP_REST_Response(
            [
                'error' => 'internal_server_error',
                'message' => WP_DEBUG ? $e->getMessage() : 'An error occurred',
            ],
            500
        );
    }
}
