<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Security;

/**
 * Rate Limit Configuration
 *
 * Centralizes rate limit configuration and provides WordPress filter integration.
 * Allows customization via WordPress filters without modifying code.
 */
class RateLimitConfig
{
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
     * Get rate limits configuration
     *
     * Applies WordPress filters to allow customization:
     * - saga_rate_limits: Filter all rate limits at once
     * - saga_rate_limit_{action}: Filter individual action limit
     *
     * @return array<string, int>
     */
    public static function getLimits(): array
    {
        $limits = self::DEFAULT_LIMITS;

        /**
         * Filter all rate limits at once
         *
         * @param array<string, int> $limits Rate limits (requests per minute)
         */
        $limits = apply_filters('saga_rate_limits', $limits);

        // Apply individual action filters
        foreach ($limits as $action => $limit) {
            /**
             * Filter rate limit for a specific action
             *
             * @param int $limit The rate limit (requests per minute)
             * @param string $action The action identifier
             */
            $limits[$action] = (int) apply_filters("saga_rate_limit_{$action}", $limit, $action);
        }

        return $limits;
    }

    /**
     * Get rate limit for a specific action
     */
    public static function getLimit(string $action): int
    {
        $limits = self::getLimits();

        return $limits[$action] ?? $limits['default'];
    }

    /**
     * Check if rate limiting is enabled
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        /**
         * Filter to enable/disable rate limiting globally
         *
         * @param bool $enabled Whether rate limiting is enabled
         */
        return (bool) apply_filters('saga_rate_limiting_enabled', true);
    }

    /**
     * Get actions that should bypass rate limiting
     *
     * @return array<string>
     */
    public static function getBypassedActions(): array
    {
        /**
         * Filter actions that should bypass rate limiting
         *
         * @param array<string> $actions Array of action identifiers to bypass
         */
        return (array) apply_filters('saga_rate_limit_bypass_actions', []);
    }

    /**
     * Check if an action should bypass rate limiting
     */
    public static function shouldBypass(string $action): bool
    {
        return in_array($action, self::getBypassedActions(), true);
    }

    /**
     * Get whitelisted user IDs that bypass rate limiting
     *
     * @return array<int>
     */
    public static function getWhitelistedUsers(): array
    {
        /**
         * Filter user IDs that should bypass rate limiting
         *
         * @param array<int> $userIds Array of user IDs to whitelist
         */
        return (array) apply_filters('saga_rate_limit_whitelist_users', []);
    }

    /**
     * Check if a user should bypass rate limiting
     */
    public static function isUserWhitelisted(int $userId): bool
    {
        return in_array($userId, self::getWhitelistedUsers(), true);
    }

    /**
     * Get whitelisted IP addresses that bypass rate limiting
     *
     * @return array<string>
     */
    public static function getWhitelistedIPs(): array
    {
        /**
         * Filter IP addresses that should bypass rate limiting
         *
         * @param array<string> $ips Array of IP addresses to whitelist
         */
        return (array) apply_filters('saga_rate_limit_whitelist_ips', []);
    }

    /**
     * Check if an IP address should bypass rate limiting
     */
    public static function isIPWhitelisted(string $ipAddress): bool
    {
        return in_array($ipAddress, self::getWhitelistedIPs(), true);
    }
}
