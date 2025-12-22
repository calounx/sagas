<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Exception\ValidationException;
use SagaManager\Infrastructure\Repository\MariaDBQualityMetricsRepository;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Infrastructure\Repository\MariaDBEntityRelationshipRepository;
use SagaManager\Infrastructure\Repository\MariaDBContentFragmentRepository;
use SagaManager\Infrastructure\Repository\MariaDBTimelineEventRepository;
use SagaManager\Application\UseCase\UpdateQualityMetrics\UpdateQualityMetricsCommand;
use SagaManager\Application\UseCase\UpdateQualityMetrics\UpdateQualityMetricsHandler;
use SagaManager\Application\UseCase\RecalculateQualityMetrics\RecalculateQualityMetricsCommand;
use SagaManager\Application\UseCase\RecalculateQualityMetrics\RecalculateQualityMetricsHandler;

/**
 * REST API Controller for Quality Metrics
 *
 * Handles quality metrics operations.
 */
class QualityMetricsController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBQualityMetricsRepository $repository
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Entity quality metrics
        register_rest_route(self::NAMESPACE, '/entities/(?P<entity_id>\d+)/quality', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'show'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getUpdateArgs(),
            ],
        ]);

        // Recalculate quality metrics
        register_rest_route(self::NAMESPACE, '/entities/(?P<entity_id>\d+)/quality/recalculate', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'recalculate'],
                'permission_callback' => [$this, 'checkWritePermission'],
            ],
        ]);

        // Saga quality overview
        register_rest_route(self::NAMESPACE, '/sagas/(?P<saga_id>\d+)/quality', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'sagaOverview'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
        ]);

        // Entities with issues
        register_rest_route(self::NAMESPACE, '/sagas/(?P<saga_id>\d+)/quality/issues', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listWithIssues'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getListArgs(),
            ],
        ]);

        // Entities below threshold
        register_rest_route(self::NAMESPACE, '/sagas/(?P<saga_id>\d+)/quality/below-threshold', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'listBelowThreshold'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getThresholdArgs(),
            ],
        ]);

        // Batch recalculate
        register_rest_route(self::NAMESPACE, '/sagas/(?P<saga_id>\d+)/quality/recalculate', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'batchRecalculate'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => $this->getBatchArgs(),
            ],
        ]);
    }

    /**
     * GET /wp-json/saga/v1/entities/{entity_id}/quality
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = new EntityId((int) $request->get_param('entity_id'));
            $metrics = $this->repository->findByEntityId($entityId);

            if (!$metrics) {
                return new \WP_REST_Response(
                    ['error' => 'Quality metrics not found', 'message' => 'No metrics recorded for this entity'],
                    404
                );
            }

            return new \WP_REST_Response($metrics->toArray(), 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/entities/{entity_id}/quality
     */
    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $command = new UpdateQualityMetricsCommand(
                entityId: (int) $request->get_param('entity_id'),
                completenessScore: (int) $request->get_param('completeness_score'),
                consistencyScore: (int) $request->get_param('consistency_score'),
                issueCodes: $request->get_param('issues') ?? []
            );

            $handler = new UpdateQualityMetricsHandler($this->repository);
            $metrics = $handler->handle($command);

            return new \WP_REST_Response($metrics->toArray(), 200);

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
     * POST /wp-json/saga/v1/entities/{entity_id}/quality/recalculate
     */
    public function recalculate(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $entityId = (int) $request->get_param('entity_id');

            // Get saga_id from entity
            global $wpdb;
            $table = $wpdb->prefix . 'saga_entities';
            $sagaId = $wpdb->get_var($wpdb->prepare(
                "SELECT saga_id FROM {$table} WHERE id = %d",
                $entityId
            ));

            if (!$sagaId) {
                return new \WP_REST_Response(
                    ['error' => 'Entity not found'],
                    404
                );
            }

            $handler = $this->createRecalculateHandler();
            $command = new RecalculateQualityMetricsCommand(
                sagaId: (int) $sagaId,
                entityId: $entityId
            );

            $result = $handler->handle($command);

            // Fetch updated metrics
            $metrics = $this->repository->findByEntityId(new EntityId($entityId));

            return new \WP_REST_Response([
                'result' => $result,
                'metrics' => $metrics?->toArray(),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/sagas/{saga_id}/quality
     */
    public function sagaOverview(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = new SagaId((int) $request->get_param('saga_id'));

            $averages = $this->repository->getAverageScores($sagaId);
            $grades = $this->repository->countByGrade($sagaId);

            return new \WP_REST_Response([
                'saga_id' => $sagaId->value(),
                'averages' => [
                    'completeness' => round($averages['completeness'], 1),
                    'consistency' => round($averages['consistency'], 1),
                    'overall' => round($averages['overall'], 1),
                ],
                'distribution' => $grades,
                'total' => array_sum($grades),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/sagas/{saga_id}/quality/issues
     */
    public function listWithIssues(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = new SagaId((int) $request->get_param('saga_id'));
            $limit = (int) $request->get_param('limit');
            $offset = (int) $request->get_param('offset');

            $metrics = $this->repository->findWithIssues($sagaId, $limit, $offset);

            return new \WP_REST_Response([
                'items' => array_map(fn($m) => $m->toArray(), $metrics),
                'count' => count($metrics),
                'limit' => $limit,
                'offset' => $offset,
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/sagas/{saga_id}/quality/below-threshold
     */
    public function listBelowThreshold(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = new SagaId((int) $request->get_param('saga_id'));
            $threshold = (int) $request->get_param('threshold');
            $limit = (int) $request->get_param('limit');

            $metrics = $this->repository->findBelowThreshold($sagaId, $threshold, $limit);

            return new \WP_REST_Response([
                'threshold' => $threshold,
                'items' => array_map(fn($m) => $m->toArray(), $metrics),
                'count' => count($metrics),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/sagas/{saga_id}/quality/recalculate
     */
    public function batchRecalculate(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $sagaId = (int) $request->get_param('saga_id');
            $limit = (int) $request->get_param('limit');

            $handler = $this->createRecalculateHandler();
            $command = new RecalculateQualityMetricsCommand(
                sagaId: $sagaId,
                limit: $limit
            );

            $result = $handler->handle($command);

            return new \WP_REST_Response([
                'saga_id' => $sagaId,
                'processed' => $result['processed'],
                'updated' => $result['updated'],
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

    private function createRecalculateHandler(): RecalculateQualityMetricsHandler
    {
        return new RecalculateQualityMetricsHandler(
            metricsRepository: $this->repository,
            entityRepository: new MariaDBEntityRepository(),
            relationshipRepository: new MariaDBEntityRelationshipRepository(),
            fragmentRepository: new MariaDBContentFragmentRepository(),
            timelineRepository: new MariaDBTimelineEventRepository()
        );
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Quality Metrics Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }

    private function getUpdateArgs(): array
    {
        return [
            'completeness_score' => ['required' => true, 'type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'consistency_score' => ['required' => true, 'type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];
    }

    private function getListArgs(): array
    {
        return [
            'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
            'offset' => ['type' => 'integer', 'default' => 0],
        ];
    }

    private function getThresholdArgs(): array
    {
        return [
            'threshold' => ['type' => 'integer', 'default' => 70, 'minimum' => 0, 'maximum' => 100],
            'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
        ];
    }

    private function getBatchArgs(): array
    {
        return [
            'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
        ];
    }
}
