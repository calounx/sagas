<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\ContentFragmentId;
use SagaManager\Domain\Exception\EmbeddingServiceException;
use SagaManager\Domain\Exception\EntityNotFoundException;
use SagaManager\Domain\Service\EmbeddingServiceInterface;
use SagaManager\Infrastructure\Repository\MariaDBContentFragmentRepository;

/**
 * REST API Controller for Embeddings
 *
 * Handles embedding generation for content fragments.
 */
class EmbeddingController
{
    private const NAMESPACE = 'saga/v1';

    public function __construct(
        private MariaDBContentFragmentRepository $fragmentRepository,
        private EmbeddingServiceInterface $embeddingService
    ) {}

    /**
     * Register all REST API routes
     */
    public function registerRoutes(): void
    {
        // Generate embedding for single fragment
        register_rest_route(self::NAMESPACE, '/fragments/(?P<id>\d+)/embed', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generateEmbedding'],
                'permission_callback' => [$this, 'checkWritePermission'],
            ],
        ]);

        // Bulk generate embeddings
        register_rest_route(self::NAMESPACE, '/embeddings/generate-batch', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulkGenerateEmbeddings'],
                'permission_callback' => [$this, 'checkWritePermission'],
                'args' => [
                    'fragment_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'limit' => ['type' => 'integer', 'default' => 100, 'maximum' => 500],
                ],
            ],
        ]);

        // Check embedding service status
        register_rest_route(self::NAMESPACE, '/embeddings/status', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'status'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
        ]);
    }

    /**
     * POST /wp-json/saga/v1/fragments/{id}/embed
     */
    public function generateEmbedding(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = new ContentFragmentId((int) $request->get_param('id'));
            $fragment = $this->fragmentRepository->findById($id);

            // Generate embedding
            $vector = $this->embeddingService->embed($fragment->getFragmentText());

            // Store embedding
            $fragment->setEmbedding($vector->toBinary());
            $this->fragmentRepository->save($fragment);

            return new \WP_REST_Response([
                'id' => $fragment->getId()->value(),
                'has_embedding' => true,
                'dimensions' => $this->embeddingService->getDimensions(),
                'model' => $this->embeddingService->getModelName(),
            ], 200);

        } catch (EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Fragment not found', 'message' => $e->getMessage()],
                404
            );
        } catch (EmbeddingServiceException $e) {
            return new \WP_REST_Response(
                ['error' => 'Embedding service error', 'message' => $e->getMessage()],
                503
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /wp-json/saga/v1/embeddings/generate-batch
     */
    public function bulkGenerateEmbeddings(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $fragmentIds = $request->get_param('fragment_ids');
            $limit = (int) $request->get_param('limit');

            // Get fragments to process
            if (!empty($fragmentIds)) {
                $fragments = [];
                foreach ($fragmentIds as $id) {
                    $fragments[] = $this->fragmentRepository->findById(new ContentFragmentId($id));
                }
            } else {
                $fragments = $this->fragmentRepository->findWithoutEmbeddings($limit);
            }

            if (empty($fragments)) {
                return new \WP_REST_Response([
                    'processed' => 0,
                    'failed' => 0,
                    'message' => 'No fragments to process',
                ], 200);
            }

            // Extract texts for batch processing
            $texts = array_map(fn($f) => $f->getFragmentText(), $fragments);

            // Generate embeddings in batch
            $vectors = $this->embeddingService->embedBatch($texts);

            $processed = 0;
            $failed = 0;

            foreach ($fragments as $index => $fragment) {
                try {
                    if (isset($vectors[$index])) {
                        $fragment->setEmbedding($vectors[$index]->toBinary());
                        $this->fragmentRepository->save($fragment);
                        $processed++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    error_log('[SAGA][EMBEDDING] Failed to save embedding: ' . $e->getMessage());
                    $failed++;
                }
            }

            return new \WP_REST_Response([
                'processed' => $processed,
                'failed' => $failed,
                'model' => $this->embeddingService->getModelName(),
            ], 200);

        } catch (EmbeddingServiceException $e) {
            return new \WP_REST_Response(
                ['error' => 'Embedding service error', 'message' => $e->getMessage()],
                503
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /wp-json/saga/v1/embeddings/status
     */
    public function status(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $available = $this->embeddingService->isAvailable();
            $pendingCount = count($this->fragmentRepository->findWithoutEmbeddings(1000));

            return new \WP_REST_Response([
                'available' => $available,
                'model' => $this->embeddingService->getModelName(),
                'dimensions' => $this->embeddingService->getDimensions(),
                'pending_fragments' => $pendingCount,
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'available' => false,
                'error' => $e->getMessage(),
            ], 200);
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

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Embedding Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }
}
