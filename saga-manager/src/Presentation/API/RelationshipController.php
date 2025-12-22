<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\Relationship;
use SagaManager\Domain\Entity\RelationshipId;
use SagaManager\Domain\Entity\RelationshipStrength;
use SagaManager\Domain\Exception\DuplicateEntityException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\RelationshipConstraintException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Infrastructure\Repository\MariaDBRelationshipRepository;

/**
 * REST API Controller for Entity Relationships
 *
 * Handles CRUD operations for relationships between saga entities.
 */
class RelationshipController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBRelationshipRepository $repository,
        private MariaDBEntityRepository $entityRepository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Relationship CRUD
        register_rest_route(self::NAMESPACE, '/relationships', [
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

        register_rest_route(self::NAMESPACE, '/relationships/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkReadPermission'],
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
            ],
        ]);

        // Entity relationships (nested under entities)
        register_rest_route(self::NAMESPACE, '/entities/(?P<id>\d+)/relationships', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'entityRelationships'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'type' => ['type' => 'string'],
                    'direction' => [
                        'type' => 'string',
                        'enum' => ['outgoing', 'incoming', 'both'],
                        'default' => 'both',
                    ],
                    'current_only' => ['type' => 'boolean', 'default' => false],
                ],
            ],
        ]);

        // Relationship types endpoint
        register_rest_route(self::NAMESPACE, '/relationship-types', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'types'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/relationships
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = $request->get_param('entity_id');
            $type = $request->get_param('type');
            $currentOnly = (bool) $request->get_param('current_only');

            if ($entityId !== null) {
                $id = new EntityId((int) $entityId);
                $relationships = $currentOnly
                    ? $this->repository->findCurrentByEntity($id)
                    : $this->repository->findByEntity($id, $type);
            } elseif ($type !== null) {
                $relationships = $this->repository->findByType($type);
            } else {
                return new \WP_REST_Response(
                    ['error' => 'Either entity_id or type parameter is required'],
                    400
                );
            }

            $data = array_map([$this, 'formatRelationship'], $relationships);

            return new \WP_REST_Response($data, 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/relationships/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new RelationshipId((int) $request->get_param('id'));
            $relationship = $this->repository->findById($id);

            return new \WP_REST_Response($this->formatRelationship($relationship), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Relationship not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/relationships
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sourceId = new EntityId((int) $request->get_param('source_entity_id'));
            $targetId = new EntityId((int) $request->get_param('target_entity_id'));
            $type = sanitize_key($request->get_param('relationship_type'));

            // Verify entities exist
            $this->entityRepository->findById($sourceId);
            $this->entityRepository->findById($targetId);

            // Check for duplicates
            if ($this->repository->existsBetween($sourceId, $targetId, $type)) {
                throw new DuplicateEntityException(
                    sprintf('Relationship of type "%s" already exists between these entities', $type)
                );
            }

            $strength = $request->get_param('strength');
            $validFrom = $request->get_param('valid_from');
            $validUntil = $request->get_param('valid_until');
            $metadata = $request->get_param('metadata');

            $relationship = new Relationship(
                sourceEntityId: $sourceId,
                targetEntityId: $targetId,
                relationshipType: $type,
                strength: $strength !== null ? new RelationshipStrength((int) $strength) : null,
                validFrom: $validFrom ? new \DateTimeImmutable($validFrom) : null,
                validUntil: $validUntil ? new \DateTimeImmutable($validUntil) : null,
                metadata: $metadata
            );

            $this->repository->save($relationship);

            return new \WP_REST_Response($this->formatRelationship($relationship), 201);

        } catch (DuplicateEntityException $e) {
            return new \WP_REST_Response(
                ['error' => 'Duplicate relationship', 'message' => $e->getMessage()],
                409
            );
        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Entity not found', 'message' => $e->getMessage()],
                404
            );
        } catch (RelationshipConstraintException | ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/relationships/{id}
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new RelationshipId((int) $request->get_param('id'));
            $relationship = $this->repository->findById($id);

            if ($request->has_param('relationship_type')) {
                $relationship->updateRelationshipType(sanitize_key($request->get_param('relationship_type')));
            }

            if ($request->has_param('strength')) {
                $relationship->setStrength(new RelationshipStrength((int) $request->get_param('strength')));
            }

            if ($request->has_param('valid_from') || $request->has_param('valid_until')) {
                $validFrom = $request->get_param('valid_from');
                $validUntil = $request->get_param('valid_until');

                $relationship->setValidityPeriod(
                    $validFrom ? new \DateTimeImmutable($validFrom) : $relationship->getValidFrom(),
                    $validUntil ? new \DateTimeImmutable($validUntil) : $relationship->getValidUntil()
                );
            }

            if ($request->has_param('metadata')) {
                $relationship->setMetadata($request->get_param('metadata'));
            }

            $this->repository->save($relationship);

            return new \WP_REST_Response($this->formatRelationship($relationship), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Relationship not found', 'message' => $e->getMessage()],
                404
            );
        } catch (RelationshipConstraintException | ValidationException $e) {
            return new \WP_REST_Response(
                ['error' => 'Validation failed', 'message' => $e->getMessage()],
                400
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * DELETE /wp-json/saga/v1/relationships/{id}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new RelationshipId((int) $request->get_param('id'));

            $this->repository->findById($id);
            $this->repository->delete($id);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Relationship not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/{id}/relationships
     */
    public function entityRelationships(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('id'));
            $type = $request->get_param('type');
            $direction = $request->get_param('direction');
            $currentOnly = (bool) $request->get_param('current_only');

            // Verify entity exists
            $this->entityRepository->findById($entityId);

            if ($currentOnly) {
                $relationships = $this->repository->findCurrentByEntity($entityId);
            } else {
                $relationships = match ($direction) {
                    'outgoing' => $this->repository->findBySource($entityId, $type),
                    'incoming' => $this->repository->findByTarget($entityId, $type),
                    default => $this->repository->findByEntity($entityId, $type),
                };
            }

            $data = array_map([$this, 'formatRelationship'], $relationships);

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
     * GET /wp-json/saga/v1/relationship-types
     */
    public function types(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $types = $this->repository->getDistinctTypes();

            return new \WP_REST_Response($types, 200);

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

    private function formatRelationship(Relationship $relationship): array
    {
        return [
            'id' => $relationship->getId()?->value(),
            'source_entity_id' => $relationship->getSourceEntityId()->value(),
            'target_entity_id' => $relationship->getTargetEntityId()->value(),
            'relationship_type' => $relationship->getRelationshipType(),
            'strength' => $relationship->getStrength()->value(),
            'strength_label' => $relationship->getStrength()->label(),
            'valid_from' => $relationship->getValidFrom()?->format('Y-m-d'),
            'valid_until' => $relationship->getValidUntil()?->format('Y-m-d'),
            'is_currently_valid' => $relationship->isCurrentlyValid(),
            'metadata' => $relationship->getMetadata(),
            'created_at' => $relationship->getCreatedAt()->format('c'),
            'updated_at' => $relationship->getUpdatedAt()->format('c'),
        ];
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }

    private function getIndexArgs(): array
    {
        return [
            'entity_id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'current_only' => ['type' => 'boolean', 'default' => false],
        ];
    }

    private function getCreateArgs(): array
    {
        return [
            'source_entity_id' => ['required' => true, 'type' => 'integer'],
            'target_entity_id' => ['required' => true, 'type' => 'integer'],
            'relationship_type' => [
                'required' => true,
                'type' => 'string',
                'pattern' => '^[a-z][a-z0-9_]*$',
            ],
            'strength' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'valid_from' => ['type' => 'string', 'format' => 'date'],
            'valid_until' => ['type' => 'string', 'format' => 'date'],
            'metadata' => ['type' => 'object'],
        ];
    }

    private function getUpdateArgs(): array
    {
        return [
            'relationship_type' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]*$'],
            'strength' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'valid_from' => ['type' => 'string', 'format' => 'date'],
            'valid_until' => ['type' => 'string', 'format' => 'date'],
            'metadata' => ['type' => 'object'],
        ];
    }
}
