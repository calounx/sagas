<?php
declare(strict_types=1);

namespace SagaManager\Application\Query\SemanticSearch;

use SagaManager\Application\Query\QueryHandlerInterface;
use SagaManager\Application\Query\QueryInterface;
use SagaManager\Domain\Entity\ContentFragment;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\EmbeddingVector;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Service\EmbeddingServiceInterface;

/**
 * Semantic Search Handler
 *
 * @implements QueryHandlerInterface<SemanticSearchQuery, array>
 */
final readonly class SemanticSearchHandler implements QueryHandlerInterface
{
    public function __construct(
        private ContentFragmentRepositoryInterface $fragmentRepository,
        private EmbeddingServiceInterface $embeddingService
    ) {}

    /**
     * @return array<array{fragment: ContentFragment, similarity: float}>
     */
    public function handle(QueryInterface $query): array
    {
        if (!$query instanceof SemanticSearchQuery) {
            throw new \InvalidArgumentException(
                sprintf('Expected %s, got %s', SemanticSearchQuery::class, get_class($query))
            );
        }

        // Generate embedding for query text
        $queryVector = $this->embeddingService->embed($query->queryText);

        // Get all fragments with embeddings
        if ($query->entityId !== null) {
            $fragments = $this->fragmentRepository->findByEntity(new EntityId($query->entityId));
        } else {
            // For now, search across all fragments with embeddings
            // In production, this should use a vector index
            $fragments = $this->getAllFragmentsWithEmbeddings($query->limit * 10);
        }

        // Calculate similarities
        $results = [];
        foreach ($fragments as $fragment) {
            if (!$fragment->hasEmbedding()) {
                continue;
            }

            try {
                $fragmentVector = new EmbeddingVector($fragment->getEmbedding());
                $similarity = $queryVector->cosineSimilarity($fragmentVector);

                if ($similarity >= $query->minSimilarity) {
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

        // Return top results
        return array_slice($results, 0, $query->limit);
    }

    /**
     * Get fragments with embeddings for search
     * In production, use a proper vector index
     *
     * @return ContentFragment[]
     */
    private function getAllFragmentsWithEmbeddings(int $limit): array
    {
        // This is a simplified implementation
        // A real implementation would use a vector database or index
        global $wpdb;

        $table = $wpdb->prefix . 'saga_content_fragments';
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE embedding IS NOT NULL LIMIT %d",
            $limit
        );

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
}
