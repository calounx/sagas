<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Service;

/**
 * Embedding Service Configuration
 *
 * Configuration for the external embedding API.
 */
final readonly class EmbeddingServiceConfig
{
    private const DEFAULT_ENDPOINT = 'http://localhost:8000/embed';
    private const DEFAULT_MODEL = 'all-MiniLM-L6-v2';
    private const DEFAULT_DIMENSIONS = 384;
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_BATCH_SIZE = 32;

    public function __construct(
        public string $endpoint = self::DEFAULT_ENDPOINT,
        public string $modelName = self::DEFAULT_MODEL,
        public int $dimensions = self::DEFAULT_DIMENSIONS,
        public int $timeout = self::DEFAULT_TIMEOUT,
        public int $batchSize = self::DEFAULT_BATCH_SIZE,
        public ?string $apiKey = null
    ) {}

    /**
     * Create config from WordPress options
     */
    public static function fromWordPressOptions(): self
    {
        return new self(
            endpoint: get_option('saga_embedding_endpoint', self::DEFAULT_ENDPOINT),
            modelName: get_option('saga_embedding_model', self::DEFAULT_MODEL),
            dimensions: (int) get_option('saga_embedding_dimensions', self::DEFAULT_DIMENSIONS),
            timeout: (int) get_option('saga_embedding_timeout', self::DEFAULT_TIMEOUT),
            batchSize: (int) get_option('saga_embedding_batch_size', self::DEFAULT_BATCH_SIZE),
            apiKey: get_option('saga_embedding_api_key', null)
        );
    }

    /**
     * Save config to WordPress options
     */
    public function saveToWordPressOptions(): void
    {
        update_option('saga_embedding_endpoint', $this->endpoint);
        update_option('saga_embedding_model', $this->modelName);
        update_option('saga_embedding_dimensions', $this->dimensions);
        update_option('saga_embedding_timeout', $this->timeout);
        update_option('saga_embedding_batch_size', $this->batchSize);

        if ($this->apiKey !== null) {
            update_option('saga_embedding_api_key', $this->apiKey);
        }
    }
}
