<?php
declare(strict_types=1);

namespace SagaManager\Domain\Service;

use SagaManager\Domain\Entity\EmbeddingVector;

/**
 * Embedding Service Interface (Port)
 *
 * Contract for generating text embeddings via external service.
 */
interface EmbeddingServiceInterface
{
    /**
     * Generate embedding for a single text
     *
     * @throws \SagaManager\Domain\Exception\EmbeddingServiceException
     */
    public function embed(string $text): EmbeddingVector;

    /**
     * Generate embeddings for multiple texts
     *
     * @param string[] $texts
     * @return EmbeddingVector[]
     * @throws \SagaManager\Domain\Exception\EmbeddingServiceException
     */
    public function embedBatch(array $texts): array;

    /**
     * Check if the embedding service is available
     */
    public function isAvailable(): bool;

    /**
     * Get the model name/identifier
     */
    public function getModelName(): string;

    /**
     * Get the expected vector dimensions
     */
    public function getDimensions(): int;
}
