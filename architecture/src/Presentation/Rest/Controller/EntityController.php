<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Rest\Controller;

use SagaManager\Contract\ApiEndpoints;
use SagaManager\Contract\QueryParams;
use SagaManager\Contract\ResponseFormat;
use SagaManager\Contract\EntityTypes;
use SagaManagerCore\Application\Service\EntityService;
use SagaManagerCore\Application\DTO\EntityDTO;
use SagaManagerCore\Domain\Exception\EntityNotFoundException;
use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * REST API controller for entity operations
 */
final class EntityController
{
    private EntityService $entityService;

    public function __construct()
    {
        // Get from container
        $this->entityService = $GLOBALS['saga_container']->get(EntityService::class);
    }

    public function registerRoutes(): void
    {
        // List entities
        register_rest_route(ApiEndpoints::NAMESPACE, ApiEndpoints::ENTITIES, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'list'],
                'permission_callback' => '__return_true',
                'args' => $this->getListArgs(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'canCreate'],
                'args' => $this->getCreateArgs(),
            ],
        ]);

        // Single entity
        register_rest_route(ApiEndpoints::NAMESPACE, ApiEndpoints::ENTITY_SINGLE, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get'],
                'permission_callback' => '__return_true',
                'args' => [
                    'id' => [
                        'required' => true,
                        'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'canEdit'],
                'args' => $this->getUpdateArgs(),
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'canDelete'],
            ],
        ]);

        // Entity attributes
        register_rest_route(ApiEndpoints::NAMESPACE, ApiEndpoints::ENTITY_ATTRIBUTES, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getAttributes'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateAttributes'],
                'permission_callback' => [$this, 'canEdit'],
            ],
        ]);

        // Entity relationships
        register_rest_route(ApiEndpoints::NAMESPACE, ApiEndpoints::ENTITY_RELATIONSHIPS, [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getRelationships'],
            'permission_callback' => '__return_true',
            'args' => [
                'direction' => [
                    'default' => 'both',
                    'enum' => ['outgoing', 'incoming', 'both'],
                ],
                'type' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    /**
     * List entities with filtering and pagination
     */
    public function list(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $params = QueryParams::sanitize($request);

        // Validate entity type if provided
        if ($params['entity_type'] && !EntityTypes::isValid($params['entity_type'])) {
            return ResponseFormat::error(
                'invalid_entity_type',
                'Invalid entity type provided',
                400
            );
        }

        try {
            $result = $this->entityService->listEntities(
                sagaId: $params['saga_id'] ?: null,
                type: $params['entity_type'] ?: null,
                search: $params['search'] ?: null,
                page: $params['page'],
                perPage: $params['per_page'],
                orderBy: $params['orderby'],
                order: $params['order']
            );

            return new \WP_REST_Response(
                ResponseFormat::paginated(
                    array_map(fn(EntityDTO $e) => $e->toArray(), $result['items']),
                    $result['total'],
                    $params['page'],
                    $params['per_page']
                ),
                200
            );
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity list failed: ' . $e->getMessage());
            return ResponseFormat::error('server_error', 'Failed to retrieve entities', 500);
        }
    }

    /**
     * Get single entity by ID
     */
    public function get(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        try {
            $entity = $this->entityService->getEntity($id);

            // Include related data if requested
            $include = $request->get_param('include') ?? [];
            if (is_string($include)) {
                $include = explode(',', $include);
            }

            $data = $entity->toArray();

            if (in_array('attributes', $include, true)) {
                $data['attributes'] = $this->entityService->getEntityAttributes($id);
            }

            if (in_array('relationships', $include, true)) {
                $data['relationships'] = $this->entityService->getEntityRelationships($id);
            }

            return new \WP_REST_Response(ResponseFormat::success($data), 200);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity get failed: ' . $e->getMessage());
            return ResponseFormat::error('server_error', 'Failed to retrieve entity', 500);
        }
    }

    /**
     * Create new entity
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $data = [
                'saga_id' => absint($request->get_param('saga_id')),
                'entity_type' => sanitize_key($request->get_param('entity_type')),
                'canonical_name' => sanitize_text_field($request->get_param('canonical_name')),
                'slug' => sanitize_title($request->get_param('slug') ?: $request->get_param('canonical_name')),
                'importance_score' => min(100, max(0, absint($request->get_param('importance_score') ?? 50))),
                'attributes' => $request->get_param('attributes') ?? [],
            ];

            $entity = $this->entityService->createEntity($data);

            return new \WP_REST_Response(
                ResponseFormat::success($entity->toArray()),
                201
            );

        } catch (ValidationException $e) {
            return ResponseFormat::error('validation_error', $e->getMessage(), 400, $e->getErrors());
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity creation failed: ' . $e->getMessage());
            return ResponseFormat::error('server_error', 'Failed to create entity', 500);
        }
    }

    /**
     * Update existing entity
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        try {
            $data = array_filter([
                'canonical_name' => $request->get_param('canonical_name')
                    ? sanitize_text_field($request->get_param('canonical_name'))
                    : null,
                'slug' => $request->get_param('slug')
                    ? sanitize_title($request->get_param('slug'))
                    : null,
                'importance_score' => $request->get_param('importance_score') !== null
                    ? min(100, max(0, absint($request->get_param('importance_score'))))
                    : null,
            ], fn($v) => $v !== null);

            $entity = $this->entityService->updateEntity($id, $data);

            // Update attributes if provided
            if ($request->get_param('attributes')) {
                $this->entityService->updateEntityAttributes($id, $request->get_param('attributes'));
            }

            return new \WP_REST_Response(ResponseFormat::success($entity->toArray()), 200);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        } catch (ValidationException $e) {
            return ResponseFormat::error('validation_error', $e->getMessage(), 400, $e->getErrors());
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity update failed: ' . $e->getMessage());
            return ResponseFormat::error('server_error', 'Failed to update entity', 500);
        }
    }

    /**
     * Delete entity
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        try {
            $this->entityService->deleteEntity($id);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        } catch (\Exception $e) {
            error_log('[SAGA][ERROR] Entity deletion failed: ' . $e->getMessage());
            return ResponseFormat::error('server_error', 'Failed to delete entity', 500);
        }
    }

    /**
     * Get entity attributes
     */
    public function getAttributes(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');

        try {
            $attributes = $this->entityService->getEntityAttributes($id);
            return new \WP_REST_Response(ResponseFormat::success($attributes), 200);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        }
    }

    /**
     * Update entity attributes
     */
    public function updateAttributes(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $attributes = $request->get_json_params();

        try {
            $updated = $this->entityService->updateEntityAttributes($id, $attributes);
            return new \WP_REST_Response(ResponseFormat::success($updated), 200);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        } catch (ValidationException $e) {
            return ResponseFormat::error('validation_error', $e->getMessage(), 400, $e->getErrors());
        }
    }

    /**
     * Get entity relationships
     */
    public function getRelationships(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $direction = $request->get_param('direction');
        $type = $request->get_param('type');

        try {
            $relationships = $this->entityService->getEntityRelationships($id, $direction, $type);
            return new \WP_REST_Response(ResponseFormat::success($relationships), 200);

        } catch (EntityNotFoundException $e) {
            return ResponseFormat::error('entity_not_found', $e->getMessage(), 404);
        }
    }

    // Permission callbacks
    public function canCreate(\WP_REST_Request $request): bool
    {
        return current_user_can('edit_posts');
    }

    public function canEdit(\WP_REST_Request $request): bool
    {
        return current_user_can('edit_posts');
    }

    public function canDelete(\WP_REST_Request $request): bool
    {
        return current_user_can('delete_posts');
    }

    // Argument definitions
    private function getListArgs(): array
    {
        return [
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => QueryParams::DEFAULT_PER_PAGE,
                'sanitize_callback' => 'absint',
            ],
            'saga_id' => [
                'default' => 0,
                'sanitize_callback' => 'absint',
            ],
            'entity_type' => [
                'default' => '',
                'sanitize_callback' => 'sanitize_key',
            ],
            'search' => [
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby' => [
                'default' => 'canonical_name',
                'enum' => ['id', 'canonical_name', 'importance_score', 'created_at', 'updated_at'],
            ],
            'order' => [
                'default' => 'ASC',
                'enum' => ['ASC', 'DESC'],
            ],
            'include' => [
                'default' => [],
                'type' => 'array',
            ],
        ];
    }

    private function getCreateArgs(): array
    {
        return [
            'saga_id' => [
                'required' => true,
                'validate_callback' => fn($param) => is_numeric($param) && $param > 0,
            ],
            'entity_type' => [
                'required' => true,
                'validate_callback' => fn($param) => EntityTypes::isValid($param),
            ],
            'canonical_name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => fn($param) => strlen($param) >= 1 && strlen($param) <= 255,
            ],
            'slug' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
            'importance_score' => [
                'required' => false,
                'default' => 50,
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 0 && $param <= 100,
            ],
            'attributes' => [
                'required' => false,
                'default' => [],
                'type' => 'object',
            ],
        ];
    }

    private function getUpdateArgs(): array
    {
        return [
            'canonical_name' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'slug' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_title',
            ],
            'importance_score' => [
                'required' => false,
                'validate_callback' => fn($param) => is_numeric($param) && $param >= 0 && $param <= 100,
            ],
            'attributes' => [
                'required' => false,
                'type' => 'object',
            ],
        ];
    }
}
