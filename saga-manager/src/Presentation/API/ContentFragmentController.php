<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBContentFragmentRepository;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;

/**
 * REST API Controller for Content Fragments
 *
 * Handles CRUD operations and search for content fragments.
 */
class ContentFragmentController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBContentFragmentRepository $repository,
        private MariaDBEntityRepository $entityRepository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Entity fragments (nested under entities)
        register_rest_route(self::NAMESPACE, '/entities/(?P<entity_id>\d+)/fragments', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listByEntity'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getListArgs(),
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getCreateArgs(),
            ],
        ]);

        // Bulk create fragments
        register_rest_route(self::NAMESPACE, '/entities/(?P<entity_id>\d+)/fragments/bulk', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulkCreate'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getBulkCreateArgs(),
            ],
        ]);

        // Single fragment operations
        register_rest_route(self::NAMESPACE, '/fragments/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete'],
                'permission_callback' => [$this, 'checkWritePermission'],
            ],
        ]);

        // Search fragments
        register_rest_route(self::NAMESPACE, '/fragments/search', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'search'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getSearchArgs(),
            ],
        ]);

        // Fragments needing embeddings
        register_rest_route(self::NAMESPACE, '/fragments/pending-embeddings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'pendingEmbeddings'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => [
                    'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
                ],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/entities/{entity_id}/fragments
     */
    public function listByEntity(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('entity_id'));
            $limit = (int) $request->get_param('limit');
            $offset = (int) $request->get_param('offset');

            // Verify entity exists
            $this->entityRepository->findById($entityId);

            $fragments = $this->repository->findByEntityPaginated($entityId, $limit, $offset);
            $total = $this->repository->countByEntity($entityId);

            $data = [
                'items' => array_map([$this, 'formatFragment'], $fragments),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ];

            return new \WP_REST_Response($data, 200);

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
     * POST /wp-json/saga/v1/entities/{entity_id}/fragments
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('entity_id'));
            $fragmentText = sanitize_textarea_field($request->get_param('fragment_text'));

            // Verify entity exists
            $this->entityRepository->findById($entityId);

            $fragment = new ContentFragment(
                entityId: $entityId,
                fragmentText: $fragmentText
            );

            $this->repository->save($fragment);

            return new \WP_REST_Response($this->formatFragment($fragment), 201);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/entities/{entity_id}/fragments/bulk
     */
    public function bulkCreate(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('entity_id'));
            $fragmentsData = $request->get_param('fragments');

            // Verify entity exists
            $this->entityRepository->findById($entityId);

            $fragments = [];
            foreach ($fragmentsData as $data) {
                $fragments[] = new ContentFragment(
                    entityId: $entityId,
                    fragmentText: sanitize_textarea_field($data['text'])
                );
            }

            $this->repository->saveMany($fragments);

            return new \WP_REST_Response([
                'created' => count($fragments),
                'items' => array_map([$this, 'formatFragment'], $fragments),
            ], 201);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/fragments/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new ContentFragmentId((int) $request->get_param('id'));
            $fragment = $this->repository->findById($id);

            return new \WP_REST_Response($this->formatFragment($fragment), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Fragment not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /wp-json/saga/v1/fragments/{id}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new ContentFragmentId((int) $request->get_param('id'));

            $this->repository->findById($id);
            $this->repository->delete($id);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Fragment not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/fragments/search
     */
    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $query = sanitize_text_field($request->get_param('q'));
            $entityId = $request->get_param('entity_id');
            $limit = (int) $request->get_param('limit');

            if (empty($query)) {
                return new \WP_REST_Response(
                    ['error' => 'Search query is required'],
                    400
                );
            }

            if ($entityId !== null) {
                $fragments = $this->repository->searchByEntity(
                    new EntityId((int) $entityId),
                    $query
                );
            } else {
                $fragments = $this->repository->search($query, $limit);
            }

            return new \WP_REST_Response([
                'query' => $query,
                'count' => count($fragments),
                'items' => array_map([$this, 'formatFragment'], $fragments),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/fragments/pending-embeddings
     */
    public function pendingEmbeddings(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $limit = (int) $request->get_param('limit');
            $fragments = $this->repository->findWithoutEmbeddings($limit);

            return new \WP_REST_Response([
                'count' => count($fragments),
                'items' => array_map([$this, 'formatFragment'], $fragments),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    public function checkReadPermission(): bool
    {
        return current_user_can('read');
    }

    public function checkWritePermission(): bool
    {
        return current_user_can('edit_posts');
    }

    private function formatFragment(ContentFragment $fragment): array
    {
        return [
            'id' => $fragment->getId()?->value(),
            'entity_id' => $fragment->getEntityId()->value(),
            'fragment_text' => $fragment->getFragmentText(),
            'preview' => $fragment->getPreview(150),
            'has_embedding' => $fragment->hasEmbedding(),
            'token_count' => $fragment->getTokenCount()->value(),
            'created_at' => $fragment->getCreatedAt()->format('c'),
        ];
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Content Fragment Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }

    private function getListArgs(): array
    {
        return [
            'limit' => ['type' => 'integer', 'default' => 50, 'maximum' => 100],
            'offset' => ['type' => 'integer', 'default' => 0],
        ];
    }

    private function getCreateArgs(): array
    {
        return [
            'fragment_text' => ['required' => true, 'type' => 'string', 'minLength' => 1],
        ];
    }

    private function getBulkCreateArgs(): array
    {
        return [
            'fragments' => [
                'required' => true,
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string', 'required' => true],
                    ],
                ],
            ],
        ];
    }

    private function getSearchArgs(): array
    {
        return [
            'q' => ['required' => true, 'type' => 'string', 'minLength' => 2],
            'entity_id' => ['type' => 'integer'],
            'limit' => ['type' => 'integer', 'default' => 50, 'maximum' => 100],
        ];
    }
}
