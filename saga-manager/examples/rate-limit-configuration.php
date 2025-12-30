<?php
/**
 * Example: Rate Limit Configuration
 *
 * This file demonstrates how to customize rate limiting behavior
 * using WordPress filters. Add these snippets to your theme's
 * functions.php or a custom plugin.
 */

// Example 1: Customize rate limits for specific actions
add_filter('saga_rate_limit_entity_create', function(int $limit, string $action): int {
    // Allow more creates for power users
    if (current_user_can('manage_options')) {
        return 50; // Admins can create 50 entities per minute
    }

    return $limit; // Keep default for other users
}, 10, 2);

// Example 2: Customize all rate limits at once
add_filter('saga_rate_limits', function(array $limits): array {
    // Increase limits for development environment
    if (wp_get_environment_type() === 'development') {
        return array_map(fn($limit) => $limit * 10, $limits);
    }

    // Stricter limits for production
    if (wp_get_environment_type() === 'production') {
        $limits['entity_create'] = 5;
        $limits['entity_delete'] = 2;
    }

    return $limits;
});

// Example 3: Disable rate limiting in specific environments
add_filter('saga_rate_limiting_enabled', function(bool $enabled): bool {
    // Disable in local development
    if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
        return false;
    }

    // Disable for automated testing
    if (defined('WP_TESTS_DOMAIN')) {
        return false;
    }

    return $enabled;
});

// Example 4: Bypass rate limiting for specific actions
add_filter('saga_rate_limit_bypass_actions', function(array $actions): array {
    // Don't rate limit search operations
    $actions[] = 'entity_search';

    return $actions;
});

// Example 5: Whitelist specific users
add_filter('saga_rate_limit_whitelist_users', function(array $userIds): array {
    // Whitelist admins
    $admins = get_users(['role' => 'administrator', 'fields' => 'ID']);
    $userIds = array_merge($userIds, $admins);

    // Whitelist specific service account
    $userIds[] = 123; // Service account user ID

    return $userIds;
});

// Example 6: Whitelist specific IP addresses
add_filter('saga_rate_limit_whitelist_ips', function(array $ips): array {
    // Whitelist internal API servers
    $ips[] = '10.0.1.100';
    $ips[] = '10.0.1.101';

    // Whitelist office network
    $ips[] = '203.0.113.0'; // Example IP

    return $ips;
});

// Example 7: Dynamic rate limits based on user role
add_filter('saga_rate_limit_entity_create', function(int $limit, string $action): int {
    $userId = get_current_user_id();

    if ($userId === 0) {
        return 5; // Anonymous users: very strict
    }

    if (user_can($userId, 'manage_options')) {
        return 100; // Administrators: generous
    }

    if (user_can($userId, 'edit_others_posts')) {
        return 50; // Editors: moderate
    }

    return $limit; // Default for other roles
}, 10, 2);

// Example 8: Time-based rate limits
add_filter('saga_rate_limits', function(array $limits): array {
    $currentHour = (int) current_time('G');

    // Stricter limits during peak hours (9am - 5pm)
    if ($currentHour >= 9 && $currentHour < 17) {
        $limits['entity_create'] = 5;
        $limits['entity_update'] = 10;
    }
    // More relaxed during off-peak hours
    else {
        $limits['entity_create'] = 20;
        $limits['entity_update'] = 40;
    }

    return $limits;
});

// Example 9: Progressive rate limiting (stricter for repeat offenders)
add_filter('saga_rate_limit_entity_create', function(int $limit, string $action): int {
    $userId = get_current_user_id();

    if ($userId === 0) {
        return $limit;
    }

    // Check how many rate limit violations this user has had today
    $violations = (int) get_user_meta($userId, 'saga_rate_limit_violations_today', true);

    if ($violations > 5) {
        return max(1, (int) ($limit / 2)); // Reduce limit by 50%
    }

    return $limit;
}, 10, 2);

// Example 10: Monitoring and alerts
add_action('saga_rate_limit_exceeded', function(string $action, $userId, ?string $ip) {
    // Log to external monitoring service
    error_log(sprintf(
        '[SAGA][ALERT] Rate limit exceeded: action=%s, user=%s, ip=%s',
        $action,
        $userId ?? 'anonymous',
        $ip ?? 'unknown'
    ));

    // Send alert for suspicious activity
    if ($action === 'entity_delete' && $userId !== null) {
        $count = get_user_meta($userId, 'saga_rate_limit_violations_today', true) ?: 0;
        update_user_meta($userId, 'saga_rate_limit_violations_today', $count + 1);

        if ($count > 10) {
            // Notify admin about potential abuse
            wp_mail(
                get_option('admin_email'),
                'Saga Manager: Potential API Abuse',
                sprintf('User ID %d has exceeded rate limits %d times today.', $userId, $count)
            );
        }
    }
}, 10, 3);
