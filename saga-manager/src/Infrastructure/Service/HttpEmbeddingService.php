<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Service;

use SagaManager\Domain\Entity\EmbeddingVector;
use SagaManager\Domain\Exception\EmbeddingServiceException;
use SagaManager\Domain\Service\EmbeddingServiceInterface;

/**
 * HTTP Embedding Service
 *
 * Calls external FastAPI embedding service via HTTP.
 */
class HttpEmbeddingService implements EmbeddingServiceInterface
{
    private const CACHE_GROUP = 'saga_embeddings';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private EmbeddingServiceConfig $config
    ) {}

    public function embed(string $text): EmbeddingVector
    {
        // Check cache first
        $cacheKey = 'embed_' . md5($text);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $embeddings = $this->callApi([$text]);

        if (empty($embeddings)) {
            throw new EmbeddingServiceException('No embedding returned for text');
        }

        $vector = EmbeddingVector::fromArray($embeddings[0]);

        // Cache the result
        wp_cache_set($cacheKey, $vector, self::CACHE_GROUP, self::CACHE_TTL);

        return $vector;
    }

    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        // Check cache for already embedded texts
        $results = [];
        $uncachedTexts = [];
        $uncachedIndices = [];

        foreach ($texts as $index => $text) {
            $cacheKey = 'embed_' . md5($text);
            $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

            if ($cached !== false) {
                $results[$index] = $cached;
            } else {
                $uncachedTexts[] = $text;
                $uncachedIndices[] = $index;
            }
        }

        // Process uncached texts in batches
        if (!empty($uncachedTexts)) {
            $batches = array_chunk($uncachedTexts, $this->config->batchSize);
            $batchIndices = array_chunk($uncachedIndices, $this->config->batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $embeddings = $this->callApi($batch);

                foreach ($embeddings as $i => $embedding) {
                    $vector = EmbeddingVector::fromArray($embedding);
                    $originalIndex = $batchIndices[$batchIndex][$i];
                    $results[$originalIndex] = $vector;

                    // Cache the result
                    $cacheKey = 'embed_' . md5($batch[$i]);
                    wp_cache_set($cacheKey, $vector, self::CACHE_GROUP, self::CACHE_TTL);
                }
            }
        }

        // Sort by original index
        ksort($results);

        return array_values($results);
    }

    public function isAvailable(): bool
    {
        try {
            $response = wp_remote_get($this->config->endpoint, [
                'timeout' => 5,
            ]);

            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 500;
        } catch (\Exception $e) {
            error_log('[SAGA][EMBEDDING] Service availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getModelName(): string
    {
        return $this->config->modelName;
    }

    public function getDimensions(): int
    {
        return $this->config->dimensions;
    }

    /**
     * @param string[] $texts
     * @return array<array<float>>
     * @throws EmbeddingServiceException
     */
    private function callApi(array $texts): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($this->config->apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->config->apiKey;
        }

        $startTime = microtime(true);

        $response = wp_remote_post($this->config->endpoint, [
            'headers' => $headers,
            'body' => json_encode(['texts' => $texts]),
            'timeout' => $this->config->timeout,
        ]);

        $duration = (microtime(true) - $startTime) * 1000;

        if (is_wp_error($response)) {
            error_log('[SAGA][EMBEDDING] API error: ' . $response->get_error_message());
            throw new EmbeddingServiceException(
                'Embedding service unavailable: ' . $response->get_error_message()
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log("[SAGA][EMBEDDING] API returned status {$statusCode}: {$body}");
            throw new EmbeddingServiceException(
                sprintf('Embedding service returned status %d', $statusCode)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data === null || !isset($data['embeddings'])) {
            error_log('[SAGA][EMBEDDING] Invalid API response: ' . $body);
            throw new EmbeddingServiceException('Invalid response from embedding service');
        }

        if ($duration > 5000) {
            error_log(sprintf('[SAGA][EMBEDDING][PERF] Slow embedding request: %.2fms for %d texts', $duration, count($texts)));
        }

        return $data['embeddings'];
    }
}
