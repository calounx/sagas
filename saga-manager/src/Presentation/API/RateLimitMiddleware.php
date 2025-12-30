<?php
declare(strict_types=1);

namespace SagaManager\Presentation\API;

use SagaManager\Infrastructure\Security\RateLimiter;
use SagaManager\Infrastructure\Security\RateLimitResult;

/**
 * Rate Limit Middleware Trait
 *
 * Provides rate limiting functionality for REST API controllers.
 * Controllers can use this trait to apply rate limiting to specific actions.
 *
 * Architecture: Presentation layer component (uses Infrastructure\Security\RateLimiter)
 *
 * @property RateLimiter|null $rateLimiter
 */
trait RateLimitMiddleware
{
    private ?RateLimiter $rateLimiter = null;

    /**
     * Set the rate limiter instance
     * Should be called during controller initialization (e.g., via DI container)
     */
    public function setRateLimiter(RateLimiter $rateLimiter): void
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Get rate limiter instance (lazy initialization)
     */
    private function getRateLimiter(): RateLimiter
    {
        if ($this->rateLimiter === null) {
            $this->rateLimiter = new RateLimiter();
        }

        return $this->rateLimiter;
    }

    /**
     * Check rate limit for a request
     *
     * @param \WP_REST_Request $request The REST request
     * @param string $action Action identifier (e.g., 'entity_create')
     * @return bool|\WP_REST_Response True if allowed, WP_REST_Response if rate limit exceeded
     */
    protected function checkRateLimit(\WP_REST_Request $request, string $action): bool|\WP_REST_Response
    {
        $rateLimiter = $this->getRateLimiter();

        // Get user ID if authenticated
        $userId = get_current_user_id();
        $userId = $userId > 0 ? $userId : null;

        // Get IP address from request
        $ipAddress = $this->getClientIpAddress($request);

        // Check rate limit
        $result = $rateLimiter->checkLimit($action, $userId, $ipAddress);

        // Rate limit not exceeded
        if (!$result->isExceeded()) {
            return true;
        }

        // Rate limit exceeded - return 429 response
        return $this->createRateLimitResponse($result);
    }

    /**
     * Create a rate limit exceeded response
     */
    private function createRateLimitResponse(RateLimitResult $result): \WP_REST_Response
    {
        $response = new \WP_REST_Response(
            [
                'error' => 'rate_limit_exceeded',
                'message' => $result->getErrorMessage(),
                'details' => [
                    'limit' => $result->limit,
                    'remaining' => $result->remaining,
                    'reset_at' => $result->resetAt,
                    'retry_after' => $result->getRetryAfter(),
                ],
            ],
            429 // Too Many Requests
        );

        // Add rate limit headers
        foreach ($result->getHttpHeaders() as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }

    /**
     * Get client IP address from request
     *
     * Handles proxies and load balancers by checking common headers.
     * Sanitizes and validates the IP address.
     */
    private function getClientIpAddress(\WP_REST_Request $request): ?string
    {
        // Check common proxy headers (in order of preference)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_CLIENT_IP',            // Alternative
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? null;

            if ($ip === null) {
                continue;
            }

            // Handle comma-separated list (X-Forwarded-For can contain multiple IPs)
            if (str_contains($ip, ',')) {
                $ips = array_map('trim', explode(',', $ip));
                $ip = $ips[0]; // Use the first (original client) IP
            }

            // Validate IP address
            $sanitizedIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($sanitizedIp !== false) {
                return $sanitizedIp;
            }
        }

        // Fallback to REMOTE_ADDR without filtering (may be local IP in development)
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($remoteAddr !== null) {
            $sanitized = filter_var($remoteAddr, FILTER_VALIDATE_IP);
            return $sanitized !== false ? $sanitized : null;
        }

        return null;
    }

    /**
     * Add rate limit headers to a successful response
     *
     * Call this method to include rate limit info even when not exceeded.
     * Useful for API clients to implement client-side rate limiting.
     */
    protected function addRateLimitHeaders(\WP_REST_Response $response, RateLimitResult $result): \WP_REST_Response
    {
        foreach ($result->getHttpHeaders() as $header => $value) {
            $response->header($header, $value);
        }

        return $response;
    }
}
