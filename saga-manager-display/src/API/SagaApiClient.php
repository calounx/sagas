<?php
/**
 * REST API client for Saga Manager backend.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\API;

use WP_Error;

/**
 * API client with caching and error handling.
 */
class SagaApiClient
{
    private const CACHE_GROUP = 'saga_api';
    private const DEFAULT_CACHE_TTL = 300; // 5 minutes
    private const LONG_CACHE_TTL = 3600; // 1 hour
    private const REQUEST_TIMEOUT = 10;

    private string $baseUrl;
    private bool $cacheEnabled;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->baseUrl = rest_url(SAGA_DISPLAY_API_NAMESPACE);
        $this->cacheEnabled = apply_filters('saga_display_cache_enabled', true);
    }

    /**
     * Get a single entity by ID.
     *
     * @param int $entityId Entity ID.
     * @param array $args Optional arguments.
     * @return array|WP_Error Entity data or error.
     */
    public function getEntity(int $entityId, array $args = []): array|WP_Error
    {
        $cacheKey = "entity_{$entityId}_" . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request("entities/{$entityId}", 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::DEFAULT_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get multiple entities by IDs.
     *
     * @param array $entityIds Array of entity IDs.
     * @param array $args Optional arguments.
     * @return array|WP_Error Entities data or error.
     */
    public function getEntities(array $entityIds, array $args = []): array|WP_Error
    {
        $args['ids'] = implode(',', array_map('intval', $entityIds));

        $cacheKey = 'entities_batch_' . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('entities', 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::DEFAULT_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Search entities.
     *
     * @param string $query Search query.
     * @param array $filters Optional filters.
     * @return array|WP_Error Search results or error.
     */
    public function searchEntities(string $query, array $filters = []): array|WP_Error
    {
        $args = array_merge($filters, ['q' => $query]);

        // Shorter cache for search results
        $cacheKey = 'search_' . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('entities/search', 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, 60); // 1 minute cache for search
        }

        return $response;
    }

    /**
     * Get timeline events for a saga.
     *
     * @param string $sagaSlug Saga slug.
     * @param array $args Optional arguments (date range, limit, etc.).
     * @return array|WP_Error Timeline data or error.
     */
    public function getTimeline(string $sagaSlug, array $args = []): array|WP_Error
    {
        $args['saga'] = $sagaSlug;

        $cacheKey = "timeline_{$sagaSlug}_" . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('timeline', 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::LONG_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get relationships for an entity.
     *
     * @param int $entityId Entity ID.
     * @param array $args Optional arguments.
     * @return array|WP_Error Relationships data or error.
     */
    public function getRelationships(int $entityId, array $args = []): array|WP_Error
    {
        $cacheKey = "relationships_{$entityId}_" . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request("entities/{$entityId}/relationships", 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::DEFAULT_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get recent entities.
     *
     * @param int $limit Number of entities to return.
     * @param string|null $entityType Optional entity type filter.
     * @return array|WP_Error Recent entities or error.
     */
    public function getRecentEntities(int $limit = 10, ?string $entityType = null): array|WP_Error
    {
        $args = [
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'desc',
        ];

        if ($entityType !== null) {
            $args['type'] = $entityType;
        }

        $cacheKey = 'recent_entities_' . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('entities', 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::DEFAULT_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get all sagas.
     *
     * @return array|WP_Error Sagas list or error.
     */
    public function getSagas(): array|WP_Error
    {
        $cacheKey = 'sagas_list';

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('sagas', 'GET');

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::LONG_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get a single saga by slug.
     *
     * @param string $slug Saga slug.
     * @return array|WP_Error Saga data or error.
     */
    public function getSaga(string $slug): array|WP_Error
    {
        $cacheKey = "saga_{$slug}";

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request("sagas/{$slug}", 'GET');

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::LONG_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Get entity types with counts.
     *
     * @param int|null $sagaId Optional saga ID filter.
     * @return array|WP_Error Entity types or error.
     */
    public function getEntityTypes(?int $sagaId = null): array|WP_Error
    {
        $args = [];
        if ($sagaId !== null) {
            $args['saga_id'] = $sagaId;
        }

        $cacheKey = 'entity_types_' . md5(serialize($args));

        $cached = $this->getCache($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('entities/types', 'GET', $args);

        if (!is_wp_error($response)) {
            $this->setCache($cacheKey, $response, self::LONG_CACHE_TTL);
        }

        return $response;
    }

    /**
     * Perform semantic search.
     *
     * @param string $query Search query.
     * @param array $args Optional arguments.
     * @return array|WP_Error Search results or error.
     */
    public function semanticSearch(string $query, array $args = []): array|WP_Error
    {
        $args['q'] = $query;
        $args['semantic'] = true;

        // No caching for semantic search - results can vary
        return $this->request('entities/search', 'GET', $args);
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint API endpoint.
     * @param string $method HTTP method.
     * @param array $args Request arguments.
     * @return array|WP_Error Response data or error.
     */
    private function request(string $endpoint, string $method = 'GET', array $args = []): array|WP_Error
    {
        $url = trailingslashit($this->baseUrl) . $endpoint;

        $requestArgs = [
            'method' => $method,
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            ],
        ];

        if ($method === 'GET' && !empty($args)) {
            $url = add_query_arg($args, $url);
        } elseif (!empty($args)) {
            $requestArgs['body'] = wp_json_encode($args);
        }

        // Allow filtering request args
        $requestArgs = apply_filters('saga_display_api_request_args', $requestArgs, $endpoint, $method);

        $response = wp_remote_request($url, $requestArgs);

        if (is_wp_error($response)) {
            $this->logError('API request failed', [
                'endpoint' => $endpoint,
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode >= 400) {
            $errorMessage = $this->parseErrorMessage($body, $statusCode);

            $this->logError('API error response', [
                'endpoint' => $endpoint,
                'status' => $statusCode,
                'message' => $errorMessage,
            ]);

            return new WP_Error(
                'saga_api_error',
                $errorMessage,
                ['status' => $statusCode]
            );
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'saga_api_parse_error',
                __('Failed to parse API response', 'saga-manager-display')
            );
        }

        return $data;
    }

    /**
     * Parse error message from response.
     *
     * @param string $body Response body.
     * @param int $statusCode HTTP status code.
     * @return string Error message.
     */
    private function parseErrorMessage(string $body, int $statusCode): string
    {
        $data = json_decode($body, true);

        if (isset($data['message'])) {
            return $data['message'];
        }

        if (isset($data['error'])) {
            return $data['error'];
        }

        return match ($statusCode) {
            400 => __('Bad request', 'saga-manager-display'),
            401 => __('Unauthorized', 'saga-manager-display'),
            403 => __('Forbidden', 'saga-manager-display'),
            404 => __('Not found', 'saga-manager-display'),
            500 => __('Internal server error', 'saga-manager-display'),
            default => sprintf(__('HTTP error %d', 'saga-manager-display'), $statusCode),
        };
    }

    /**
     * Get cached data.
     *
     * @param string $key Cache key.
     * @return mixed Cached data or false.
     */
    private function getCache(string $key): mixed
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        // Try object cache first
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached;
        }

        // Fall back to transients
        $transientKey = 'saga_' . md5($key);
        return get_transient($transientKey);
    }

    /**
     * Set cache data.
     *
     * @param string $key Cache key.
     * @param mixed $data Data to cache.
     * @param int $ttl Time to live in seconds.
     */
    private function setCache(string $key, mixed $data, int $ttl): void
    {
        if (!$this->cacheEnabled) {
            return;
        }

        // Set in object cache
        wp_cache_set($key, $data, self::CACHE_GROUP, $ttl);

        // Also set in transients for persistence
        $transientKey = 'saga_' . md5($key);
        set_transient($transientKey, $data, $ttl);
    }

    /**
     * Clear cache for a specific key or all.
     *
     * @param string|null $key Optional specific key to clear.
     */
    public function clearCache(?string $key = null): void
    {
        if ($key !== null) {
            wp_cache_delete($key, self::CACHE_GROUP);
            delete_transient('saga_' . md5($key));
            return;
        }

        // Clear all saga-related transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_saga_') . '%',
                $wpdb->esc_like('_transient_timeout_saga_') . '%'
            )
        );

        // Flush object cache group if possible
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
    }

    /**
     * Log an error.
     *
     * @param string $message Error message.
     * @param array $context Additional context.
     */
    private function logError(string $message, array $context = []): void
    {
        if (!WP_DEBUG) {
            return;
        }

        $logMessage = sprintf(
            '[SAGA_DISPLAY][ERROR] %s: %s',
            $message,
            wp_json_encode($context)
        );

        error_log($logMessage);
    }

    /**
     * Check if API is available.
     *
     * @return bool True if API is available.
     */
    public function isAvailable(): bool
    {
        $status = get_transient('saga_display_api_status');
        return $status !== 'unavailable';
    }

    /**
     * Get API base URL.
     *
     * @return string Base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
