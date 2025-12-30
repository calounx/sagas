<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Security;

/**
 * Rate Limiter Service
 *
 * Implements transient-based rate limiting for WordPress.
 * Supports both user-based and IP-based rate limiting with configurable limits per action.
 *
 * Architecture: Infrastructure layer component (uses WordPress transients)
 */
class RateLimiter
{
    private const TRANSIENT_PREFIX = 'saga_rate_';

    /**
     * Default rate limits (requests per minute)
     */
    private const DEFAULT_LIMITS = [
        'entity_create' => 10,
        'entity_update' => 20,
        'entity_delete' => 5,
        'entity_search' => 30,
        'default' => 15,
    ];

    /**
     * @param array<string, int> $customLimits Custom rate limits per action
     */
    public function __construct(
        private readonly array $customLimits = []
    ) {
    }

    /**
     * Check if a request should be rate limited
     *
     * @param string $action Action identifier (e.g., 'entity_create')
     * @param int|null $userId WordPress user ID (null for IP-based limiting)
     * @param string|null $ipAddress IP address for IP-based limiting
     * @return RateLimitResult Result object with exceeded flag and metadata
     */
    public function checkLimit(
        string $action,
        ?int $userId = null,
        ?string $ipAddress = null
    ): RateLimitResult {
        // Check if rate limiting is globally disabled
        if (!RateLimitConfig::isEnabled()) {
            return new RateLimitResult(
                exceeded: false,
                limit: PHP_INT_MAX,
                remaining: PHP_INT_MAX,
                resetAt: time() + MINUTE_IN_SECONDS
            );
        }

        // Check if this action should bypass rate limiting
        if (RateLimitConfig::shouldBypass($action)) {
            return new RateLimitResult(
                exceeded: false,
                limit: PHP_INT_MAX,
                remaining: PHP_INT_MAX,
                resetAt: time() + MINUTE_IN_SECONDS
            );
        }

        // Check if user is whitelisted
        if ($userId !== null && RateLimitConfig::isUserWhitelisted($userId)) {
            return new RateLimitResult(
                exceeded: false,
                limit: PHP_INT_MAX,
                remaining: PHP_INT_MAX,
                resetAt: time() + MINUTE_IN_SECONDS
            );
        }

        // Check if IP is whitelisted
        if ($ipAddress !== null && RateLimitConfig::isIPWhitelisted($ipAddress)) {
            return new RateLimitResult(
                exceeded: false,
                limit: PHP_INT_MAX,
                remaining: PHP_INT_MAX,
                resetAt: time() + MINUTE_IN_SECONDS
            );
        }

        // Determine the identifier (user ID or IP address)
        $identifier = $this->getIdentifier($userId, $ipAddress);

        if ($identifier === null) {
            error_log('[SAGA][WARN] Rate limiter: No valid identifier (user_id or IP)');
            // Allow request if we can't identify the source
            return new RateLimitResult(
                exceeded: false,
                limit: $this->getLimit($action),
                remaining: $this->getLimit($action),
                resetAt: time() + MINUTE_IN_SECONDS
            );
        }

        // Get the rate limit for this action
        $limit = $this->getLimit($action);

        // Build transient key
        $key = $this->buildTransientKey($action, $identifier);

        // Get current count from transient
        $count = get_transient($key);

        // First request in this time window
        if ($count === false) {
            set_transient($key, 1, MINUTE_IN_SECONDS);

            $this->logRateLimitCheck($action, $identifier, 1, $limit, false);

            return new RateLimitResult(
                exceeded: false,
                limit: $limit,
                remaining: $limit - 1,
                resetAt: time() + MINUTE_IN_SECONDS,
                currentCount: 1
            );
        }

        $count = (int) $count;

        // Rate limit exceeded
        if ($count >= $limit) {
            $this->logRateLimitViolation($action, $identifier, $count, $limit);

            // Get TTL for retry-after calculation
            $ttl = $this->getTransientTTL($key);

            return new RateLimitResult(
                exceeded: true,
                limit: $limit,
                remaining: 0,
                resetAt: time() + $ttl,
                currentCount: $count,
                retryAfter: $ttl
            );
        }

        // Increment counter
        $newCount = $count + 1;
        set_transient($key, $newCount, MINUTE_IN_SECONDS);

        $this->logRateLimitCheck($action, $identifier, $newCount, $limit, false);

        return new RateLimitResult(
            exceeded: false,
            limit: $limit,
            remaining: $limit - $newCount,
            resetAt: time() + MINUTE_IN_SECONDS,
            currentCount: $newCount
        );
    }

    /**
     * Get rate limit for a specific action
     *
     * Filters: saga_rate_limit_{action}, saga_rate_limit_default
     */
    private function getLimit(string $action): int
    {
        // Check custom limits provided in constructor
        if (isset($this->customLimits[$action])) {
            $limit = $this->customLimits[$action];
        }
        // Check default limits
        elseif (isset(self::DEFAULT_LIMITS[$action])) {
            $limit = self::DEFAULT_LIMITS[$action];
        }
        // Fallback to default
        else {
            $limit = self::DEFAULT_LIMITS['default'];
        }

        /**
         * Filter the rate limit for a specific action
         *
         * @param int $limit The rate limit (requests per minute)
         * @param string $action The action identifier
         */
        return (int) apply_filters("saga_rate_limit_{$action}", $limit, $action);
    }

    /**
     * Build transient key for rate limiting
     */
    private function buildTransientKey(string $action, string $identifier): string
    {
        return self::TRANSIENT_PREFIX . sanitize_key($action) . '_' . sanitize_key($identifier);
    }

    /**
     * Get identifier from user ID or IP address
     */
    private function getIdentifier(?int $userId, ?string $ipAddress): ?string
    {
        if ($userId !== null && $userId > 0) {
            return "user_{$userId}";
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            // Sanitize IP address
            $sanitizedIp = filter_var($ipAddress, FILTER_VALIDATE_IP);
            if ($sanitizedIp !== false) {
                return 'ip_' . str_replace(['.', ':'], '_', $sanitizedIp);
            }
        }

        return null;
    }

    /**
     * Get TTL for a transient (time until expiration)
     */
    private function getTransientTTL(string $key): int
    {
        global $wpdb;

        // WordPress stores transients with _transient_timeout_ prefix
        $timeout_key = '_transient_timeout_' . $key;

        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $timeout_key
        ));

        if ($timeout === null) {
            return MINUTE_IN_SECONDS;
        }

        $ttl = (int) $timeout - time();
        return max(0, $ttl);
    }

    /**
     * Log rate limit check
     */
    private function logRateLimitCheck(
        string $action,
        string $identifier,
        int $count,
        int $limit,
        bool $exceeded
    ): void {
        if (!WP_DEBUG) {
            return;
        }

        $status = $exceeded ? 'EXCEEDED' : 'OK';
        error_log(
            sprintf(
                '[SAGA][DEBUG] Rate limit check: action=%s, identifier=%s, count=%d/%d, status=%s',
                $action,
                $identifier,
                $count,
                $limit,
                $status
            )
        );
    }

    /**
     * Log rate limit violation (always logged, not just in debug mode)
     */
    private function logRateLimitViolation(
        string $action,
        string $identifier,
        int $count,
        int $limit
    ): void {
        error_log(
            sprintf(
                '[SAGA][WARN] Rate limit exceeded: action=%s, identifier=%s, count=%d, limit=%d',
                $action,
                $identifier,
                $count,
                $limit
            )
        );
    }

    /**
     * Reset rate limit for a specific action and identifier
     * Useful for testing or administrative override
     */
    public function reset(string $action, ?int $userId = null, ?string $ipAddress = null): bool
    {
        $identifier = $this->getIdentifier($userId, $ipAddress);

        if ($identifier === null) {
            return false;
        }

        $key = $this->buildTransientKey($action, $identifier);
        return delete_transient($key);
    }

    /**
     * Get current count for a specific action and identifier
     * Useful for monitoring and debugging
     */
    public function getCurrentCount(string $action, ?int $userId = null, ?string $ipAddress = null): int
    {
        $identifier = $this->getIdentifier($userId, $ipAddress);

        if ($identifier === null) {
            return 0;
        }

        $key = $this->buildTransientKey($action, $identifier);
        $count = get_transient($key);

        return $count !== false ? (int) $count : 0;
    }
}
