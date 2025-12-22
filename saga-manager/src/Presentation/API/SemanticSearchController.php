<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EmbeddingVector;
use SagaManager\Domain\Exception\EmbeddingServiceException;
use SagaManager\Domain\Service\EmbeddingServiceInterface;
use SagaManager\Infrastructure\Repository\MariaDBContentFragmentRepository;

/**
 * REST API Controller for Semantic Search
 *
 * Handles vector similarity search for content fragments.
 */
class SemanticSearchController
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
        // Semantic search
        register_rest_route(self::NAMESPACE, '/search/semantic', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'search'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => $this->getSearchArgs(),
            ],
        ]);

        // Find similar fragments
        register_rest_route(self::NAMESPACE, '/fragments/(?P<id>\d+)/similar', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'findSimilar'],
                'permission_callback' => [$this, 'checkReadPermission'],
                'args' => [
                    'limit' => ['type' => 'integer', 'default' => 10, 'maximum' => 50],
                    'min_similarity' => ['type' => 'number', 'default' => 0.5, 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        ]);
    }

    /**
     * POST /wp-json/saga/v1/search/semantic
     */
    public function search(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $queryText = sanitize_text_field($request->get_param('query'));
            $limit = (int) $request->get_param('limit');
            $minSimilarity = (float) $request->get_param('min_similarity');
            $entityId = $request->get_param('entity_id');

            if (empty($queryText)) {
                return new \WP_REST_Response(
                    ['error' => 'Query text is required'],
                    400
                );
            }

            // Generate embedding for query
            $queryVector = $this->embeddingService->embed($queryText);

            // Get fragments to search
            $fragments = $this->getSearchableFragments($entityId, $limit * 10);

            // Calculate similarities
            $results = $this->calculateSimilarities($fragments, $queryVector, $minSimilarity, $limit);

            return new \WP_REST_Response([
                'query' => $queryText,
                'count' => count($results),
                'results' => array_map([$this, 'formatResult'], $results),
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
     * GET /wp-json/saga/v1/fragments/{id}/similar
     */
    public function findSimilar(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $id = (int) $request->get_param('id');
            $limit = (int) $request->get_param('limit');
            $minSimilarity = (float) $request->get_param('min_similarity');

            $fragment = $this->fragmentRepository->findById(
                new \SagaManager\Domain\Entity\ContentFragmentId($id)
            );

            if (!$fragment->hasEmbedding()) {
                return new \WP_REST_Response(
                    ['error' => 'Fragment does not have an embedding'],
                    400
                );
            }

            $queryVector = new EmbeddingVector($fragment->getEmbedding());

            // Get other fragments
            $fragments = $this->getSearchableFragments(null, $limit * 10);

            // Filter out the source fragment
            $fragments = array_filter($fragments, fn($f) => $f->getId()->value() !== $id);

            // Calculate similarities
            $results = $this->calculateSimilarities($fragments, $queryVector, $minSimilarity, $limit);

            return new \WP_REST_Response([
                'source_id' => $id,
                'count' => count($results),
                'results' => array_map([$this, 'formatResult'], $results),
            ], 200);

        } catch (\SagaManager\Domain\Exception\EntityNotFoundException $e) {
            return new \WP_REST_Response(
                ['error' => 'Fragment not found', 'message' => $e->getMessage()],
                404
            );
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get fragments with embeddings for search
     *
     * @return ContentFragment[]
     */
    private function getSearchableFragments(?int $entityId, int $limit): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'saga_content_fragments';

        if ($entityId !== null) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_id = %d AND embedding IS NOT NULL LIMIT %d",
                $entityId,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE embedding IS NOT NULL LIMIT %d",
                $limit
            );
        }

        $rows = $wpdb->get_results($query, ARRAY_A);

        return array_map(function ($row) {
            return new ContentFragment(
                entityId: new EntityId((int) $row['entity_id']),
                fragmentText: $row['fragment_text'],
                embedding: $row['embedding'],
                id: new \SagaManager\Domain\Entity\ContentFragmentId((int) $row['id']),
                createdAt: new \DateTimeImmutable($row['created_at'])
            );
        }, $rows);
    }

    /**
     * Calculate similarities and return top results
     *
     * @param ContentFragment[] $fragments
     * @return array<array{fragment: ContentFragment, similarity: float}>
     */
    private function calculateSimilarities(
        array $fragments,
        EmbeddingVector $queryVector,
        float $minSimilarity,
        int $limit
    ): array {
        $results = [];

        foreach ($fragments as $fragment) {
            if (!$fragment->hasEmbedding()) {
                continue;
            }

            try {
                $fragmentVector = new EmbeddingVector($fragment->getEmbedding());
                $similarity = $queryVector->cosineSimilarity($fragmentVector);

                if ($similarity >= $minSimilarity) {
                    $results[] = [
                        'fragment' => $fragment,
                        'similarity' => $similarity,
                    ];
                }
            } catch (\Exception $e) {
                // Skip fragments with invalid embeddings
                continue;
            }
        }

        // Sort by similarity descending
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Format search result for API response
     */
    private function formatResult(array $result): array
    {
        $fragment = $result['fragment'];

        return [
            'id' => $fragment->getId()->value(),
            'entity_id' => $fragment->getEntityId()->value(),
            'fragment_text' => $fragment->getFragmentText(),
            'preview' => $fragment->getPreview(200),
            'similarity' => round($result['similarity'], 4),
            'token_count' => $fragment->getTokenCount()->value(),
        ];
    }

    public function checkReadPermission(): bool
    {
        return current_user_can('read');
    }

    private function handleException(\Exception $e): \WP_REST_Response
    {
        error_log('[SAGA][API] Semantic Search Error: ' . $e->getMessage());

        return new \WP_REST_Response(
            ['error' => 'Internal server error', 'message' => $e->getMessage()],
            500
        );
    }

    private function getSearchArgs(): array
    {
        return [
            'query' => ['required' => true, 'type' => 'string', 'minLength' => 1],
            'limit' => ['type' => 'integer', 'default' => 10, 'maximum' => 50],
            'min_similarity' => ['type' => 'number', 'default' => 0.5, 'minimum' => 0, 'maximum' => 1],
            'entity_id' => ['type' => 'integer'],
        ];
    }
}
