<?php

declare(strict_types=1);

namespace SagaManagerDisplay\ApiClient;

use SagaManager\Contract\ApiEndpoints;
use SagaManagerDisplay\ApiClient\Endpoints\EntityEndpoint;
use SagaManagerDisplay\ApiClient\Endpoints\SagaEndpoint;
use SagaManagerDisplay\ApiClient\Endpoints\RelationshipEndpoint;
use SagaManagerDisplay\ApiClient\Endpoints\TimelineEndpoint;
use SagaManagerDisplay\ApiClient\Endpoints\SearchEndpoint;
use SagaManagerDisplay\ApiClient\Cache\TransientApiCache;

/**
 * REST API client for consuming backend plugin API
 *
 * All data access in the frontend plugin MUST go through this client.
 * NO direct database access is allowed.
 */
final class SagaApiClient
{
    private string $baseUrl;
    private TransientApiCache $cache;

    public readonly EntityEndpoint $entities;
    public readonly SagaEndpoint $sagas;
    public readonly RelationshipEndpoint $relationships;
    public readonly TimelineEndpoint $timeline;
    public readonly SearchEndpoint $search;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cache = new TransientApiCache();

        // Initialize endpoint handlers
        $this->entities = new EntityEndpoint($this);
        $this->sagas = new SagaEndpoint($this);
        $this->relationships = new RelationshipEndpoint($this);
        $this->timeline = new TimelineEndpoint($this);
        $this->search = new SearchEndpoint($this);
    }

    /**
     * Make a GET request to the API
     *
     * @param string $endpoint API endpoint path
     * @param array $params Query parameters
     * @param int $cacheSeconds Cache duration (0 to disable)
     * @return ApiResponse
     * @throws ApiException
     */
    public function get(string $endpoint, array $params = [], int $cacheSeconds = 300): ApiResponse
    {
        $url = $this->buildUrl($endpoint, $params);
        $cacheKey = 'saga_api_' . md5($url);

        // Check cache
        if ($cacheSeconds > 0) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return new ApiResponse($cached, 200, true);
            }
        }

        $response = $this->request('GET', $url);

        // Cache successful responses
        if ($response->isSuccess() && $cacheSeconds > 0) {
            $this->cache->set($cacheKey, $response->getData(), $cacheSeconds);
        }

        return $response;
    }

    /**
     * Make a POST request to the API
     */
    public function post(string $endpoint, array $data): ApiResponse
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('POST', $url, $data);
    }

    /**
     * Make a PUT request to the API
     */
    public function put(string $endpoint, array $data): ApiResponse
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('PUT', $url, $data);
    }

    /**
     * Make a PATCH request to the API
     */
    public function patch(string $endpoint, array $data): ApiResponse
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('PATCH', $url, $data);
    }

    /**
     * Make a DELETE request to the API
     */
    public function delete(string $endpoint): ApiResponse
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('DELETE', $url);
    }

    /**
     * Execute HTTP request
     */
    private function request(string $method, string $url, ?array $data = null): ApiResponse
    {
        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            ],
            'cookies' => $_COOKIE, // Pass cookies for authentication
        ];

        if ($data !== null) {
            $args['body'] = wp_json_encode($data);
        }

        $start = microtime(true);
        $response = wp_remote_request($url, $args);
        $duration = (microtime(true) - $start) * 1000;

        // Log slow requests
        if ($duration > 200) {
            error_log(sprintf(
                '[SAGA][DISPLAY][PERF] Slow API request: %s %s (%.2fms)',
                $method,
                $url,
                $duration
            ));
        }

        if (is_wp_error($response)) {
            throw new ApiException(
                'API request failed: ' . $response->get_error_message(),
                0,
                null,
                $url
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Invalid JSON response from API',
                $statusCode,
                null,
                $url
            );
        }

        return new ApiResponse($decoded, $statusCode);
    }

    /**
     * Build full URL with query parameters
     */
    private function buildUrl(string $endpoint, array $params = []): string
    {
        $url = $this->baseUrl . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Invalidate cached responses for an endpoint pattern
     */
    public function invalidateCache(string $pattern): void
    {
        $this->cache->invalidatePattern($pattern);
    }

    /**
     * Check if API is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->get('/', [], 0);
            return $response->isSuccess() && isset($response->getData()['version']);
        } catch (ApiException $e) {
            error_log('[SAGA][DISPLAY] API unavailable: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get API version
     */
    public function getVersion(): ?string
    {
        try {
            $response = $this->get('/', [], 3600);
            return $response->getData()['version'] ?? null;
        } catch (ApiException $e) {
            return null;
        }
    }
}

/**
 * API Response wrapper
 */
final class ApiResponse
{
    public function __construct(
        private readonly array $data,
        private readonly int $statusCode,
        private readonly bool $fromCache = false
    ) {}

    public function getData(): array
    {
        return $this->data['data'] ?? $this->data;
    }

    public function getMeta(): array
    {
        return $this->data['meta'] ?? [];
    }

    public function getPagination(): array
    {
        return $this->getMeta()['pagination'] ?? [];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isFromCache(): bool
    {
        return $this->fromCache;
    }

    public function hasError(): bool
    {
        return !$this->isSuccess();
    }

    public function getErrorMessage(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        return $this->data['message'] ?? 'Unknown error';
    }

    public function getErrorCode(): ?string
    {
        if ($this->isSuccess()) {
            return null;
        }

        return $this->data['code'] ?? 'unknown_error';
    }
}

/**
 * API Exception
 */
final class ApiException extends \Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $url = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
