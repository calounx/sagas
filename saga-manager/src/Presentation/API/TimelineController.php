<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\CanonDate;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBTimelineEventRepository;

/**
 * REST API Controller for Timeline Events
 *
 * Handles CRUD operations for timeline events.
 */
class TimelineController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBTimelineEventRepository $repository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Saga timeline
        register_rest_route(self::NAMESPACE, '/sagas/(?P<saga_id>\d+)/timeline', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listBySaga'],
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

        // Single timeline event
        register_rest_route(self::NAMESPACE, '/timeline/(?P<id>\d+)', [
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

        // Entity timeline
        register_rest_route(self::NAMESPACE, '/entities/(?P<entity_id>\d+)/timeline', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listByEntity'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/sagas/{saga_id}/timeline
     */
    public function listBySaga(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = new SagaId((int) $request->get_param('saga_id'));
            $limit = (int) $request->get_param('limit');
            $offset = (int) $request->get_param('offset');
            $startTimestamp = $request->get_param('start_timestamp');
            $endTimestamp = $request->get_param('end_timestamp');

            if ($startTimestamp !== null && $endTimestamp !== null) {
                $events = $this->repository->findByTimeRange(
                    $sagaId,
                    new NormalizedTimestamp((int) $startTimestamp),
                    new NormalizedTimestamp((int) $endTimestamp)
                );
            } else {
                $events = $this->repository->findBySaga($sagaId, $limit, $offset);
            }

            $total = $this->repository->countBySaga($sagaId);

            return new \WP_REST_Response([
                'items' => array_map([$this, 'formatEvent'], $events),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/sagas/{saga_id}/timeline
     */
    public function create(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = new SagaId((int) $request->get_param('saga_id'));
            $canonDate = sanitize_text_field($request->get_param('canon_date'));
            $normalizedTimestamp = (int) $request->get_param('normalized_timestamp');
            $title = sanitize_text_field($request->get_param('title'));
            $description = $request->get_param('description');
            $participants = $request->get_param('participants') ?? [];
            $locations = $request->get_param('locations') ?? [];
            $eventEntityId = $request->get_param('event_entity_id');

            $event = new TimelineEvent(
                sagaId: $sagaId,
                canonDate: new CanonDate($canonDate),
                normalizedTimestamp: new NormalizedTimestamp($normalizedTimestamp),
                title: $title,
                description: $description ? sanitize_textarea_field($description) : null,
                participants: array_map('intval', $participants),
                locations: array_map('intval', $locations),
                eventEntityId: $eventEntityId !== null ? new EntityId((int) $eventEntityId) : null
            );

            $this->repository->save($event);

            return new \WP_REST_Response($this->formatEvent($event), 201);

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
     * GET /wp-json/saga/v1/timeline/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new TimelineEventId((int) $request->get_param('id'));
            $event = $this->repository->findById($id);

            return new \WP_REST_Response($this->formatEvent($event), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Timeline event not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * PUT /wp-json/saga/v1/timeline/{id}
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new TimelineEventId((int) $request->get_param('id'));
            $event = $this->repository->findById($id);

            if ($request->has_param('title')) {
                $event->updateTitle(sanitize_text_field($request->get_param('title')));
            }

            if ($request->has_param('canon_date') && $request->has_param('normalized_timestamp')) {
                $event->updateDate(
                    new CanonDate(sanitize_text_field($request->get_param('canon_date'))),
                    new NormalizedTimestamp((int) $request->get_param('normalized_timestamp'))
                );
            }

            if ($request->has_param('description')) {
                $event->setDescription(sanitize_textarea_field($request->get_param('description')));
            }

            if ($request->has_param('participants')) {
                $event->setParticipants(array_map('intval', $request->get_param('participants')));
            }

            if ($request->has_param('locations')) {
                $event->setLocations(array_map('intval', $request->get_param('locations')));
            }

            if ($request->has_param('event_entity_id')) {
                $entityId = $request->get_param('event_entity_id');
                $event->setEventEntityId($entityId !== null ? new EntityId((int) $entityId) : null);
            }

            $this->repository->save($event);

            return new \WP_REST_Response($this->formatEvent($event), 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Timeline event not found', 'message' => $e->getMessage()],
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
     * DELETE /wp-json/saga/v1/timeline/{id}
     */
    public function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new TimelineEventId((int) $request->get_param('id'));

            $this->repository->findById($id);
            $this->repository->delete($id);

            return new \WP_REST_Response(null, 204);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Timeline event not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/entities/{entity_id}/timeline
     */
    public function listByEntity(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('entity_id'));
            $events = $this->repository->findByEntity($entityId);

            return new \WP_REST_Response([
                'entity_id' => $entityId->value(),
                'count' => count($events),
                'items' => array_map([$this, 'formatEvent'], $events),
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

    private function formatEvent(TimelineEvent $event): array
    {
        return [
            'id' => $event->getId()?->value(),
            'saga_id' => $event->getSagaId()->value(),
            'event_entity_id' => $event->getEventEntityId()?->value(),
            'canon_date' => $event->getCanonDate()->value(),
            'normalized_timestamp' => $event->getNormalizedTimestamp()->value(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'participants' => $event->getParticipants(),
            'locations' => $event->getLocations(),
            'created_at' => $event->getCreatedAt()->format('c'),
            'updated_at' => $event->getUpdatedAt()->format('c'),
        ];
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Timeline Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }

    private function getListArgs(): array
    {
        return [
            'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
            'offset' => ['type' => 'integer', 'default' => 0],
            'start_timestamp' => ['type' => 'integer'],
            'end_timestamp' => ['type' => 'integer'],
        ];
    }

    private function getCreateArgs(): array
    {
        return [
            'canon_date' => ['required' => true, 'type' => 'string'],
            'normalized_timestamp' => ['required' => true, 'type' => 'integer'],
            'title' => ['required' => true, 'type' => 'string', 'minLength' => 1],
            'description' => ['type' => 'string'],
            'participants' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'locations' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'event_entity_id' => ['type' => 'integer'],
        ];
    }

    private function getUpdateArgs(): array
    {
        return [
            'canon_date' => ['type' => 'string'],
            'normalized_timestamp' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'minLength' => 1],
            'description' => ['type' => 'string'],
            'participants' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'locations' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'event_entity_id' => ['type' => 'integer'],
        ];
    }
}
