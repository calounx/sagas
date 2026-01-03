<?php
/**
 * Secure Rate Limiting Helper
 *
 * Database-backed rate limiting to prevent transient bypass attacks.
 * Implements automatic cleanup and flexible limits.
 *
 * @package SagaManager
 * @subpackage Security
 * @since 1.5.1
 * @security OWASP Rate Limiting Best Practices
 */

declare(strict_types=1);

namespace SagaManager\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Secure Rate Limiter
 *
 * Uses database table for rate limit tracking to prevent bypass via transient manipulation.
 * Automatically cleans up old entries.
 */
class RateLimiter
{
    /**
     * @var string Rate limit table name
     */
    private string $table_name;

    /**
     * @var \wpdb WordPress database instance
     */
    private \wpdb $wpdb;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'saga_rate_limits';
    }

    /**
     * Check if action is rate limited
     *
     * @param string $action_key Unique action identifier (e.g., 'summary_generation')
     * @param int    $user_id    User ID (0 for IP-based limiting)
     * @param int    $limit      Maximum attempts allowed
     * @param int    $window     Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public function check(string $action_key, int $user_id, int $limit, int $window = 3600): bool
    {
        // Create identifier (user-based or IP-based)
        $identifier = $user_id > 0
            ? "user_{$user_id}"
            : $this->get_client_ip();

        // Clean up old entries first (5% probability for performance)
        if (rand(1, 100) <= 5) {
            $this->cleanup();
        }

        // Count recent attempts
        $cutoff_time = current_time('mysql', true);
        $cutoff_timestamp = strtotime($cutoff_time) - $window;
        $cutoff_date = gmdate('Y-m-d H:i:s', $cutoff_timestamp);

        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
            WHERE action_key = %s
            AND identifier = %s
            AND attempted_at >= %s",
            $action_key,
            $identifier,
            $cutoff_date
        ));

        return (int) $count < $limit;
    }

    /**
     * Record an attempt
     *
     * @param string $action_key Unique action identifier
     * @param int    $user_id    User ID (0 for IP-based limiting)
     * @param bool   $success    Whether the action succeeded
     * @return bool True on success, false on failure
     */
    public function record(string $action_key, int $user_id, bool $success = true): bool
    {
        $identifier = $user_id > 0
            ? "user_{$user_id}"
            : $this->get_client_ip();

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'action_key' => $action_key,
                'identifier' => $identifier,
                'user_id' => $user_id,
                'success' => $success ? 1 : 0,
                'attempted_at' => current_time('mysql', true),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->get_user_agent(),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Get remaining attempts
     *
     * @param string $action_key Unique action identifier
     * @param int    $user_id    User ID
     * @param int    $limit      Maximum attempts allowed
     * @param int    $window     Time window in seconds
     * @return int Number of remaining attempts
     */
    public function get_remaining(string $action_key, int $user_id, int $limit, int $window = 3600): int
    {
        $identifier = $user_id > 0
            ? "user_{$user_id}"
            : $this->get_client_ip();

        $cutoff_time = current_time('mysql', true);
        $cutoff_timestamp = strtotime($cutoff_time) - $window;
        $cutoff_date = gmdate('Y-m-d H:i:s', $cutoff_timestamp);

        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name}
            WHERE action_key = %s
            AND identifier = %s
            AND attempted_at >= %s",
            $action_key,
            $identifier,
            $cutoff_date
        ));

        return max(0, $limit - (int) $count);
    }

    /**
     * Get time until rate limit resets
     *
     * @param string $action_key Unique action identifier
     * @param int    $user_id    User ID
     * @param int    $window     Time window in seconds
     * @return int Seconds until reset (0 if not limited)
     */
    public function get_reset_time(string $action_key, int $user_id, int $window = 3600): int
    {
        $identifier = $user_id > 0
            ? "user_{$user_id}"
            : $this->get_client_ip();

        $oldest_attempt = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT attempted_at FROM {$this->table_name}
            WHERE action_key = %s
            AND identifier = %s
            ORDER BY attempted_at ASC
            LIMIT 1",
            $action_key,
            $identifier
        ));

        if (!$oldest_attempt) {
            return 0;
        }

        $oldest_timestamp = strtotime($oldest_attempt);
        $reset_timestamp = $oldest_timestamp + $window;
        $current_timestamp = strtotime(current_time('mysql', true));

        return max(0, $reset_timestamp - $current_timestamp);
    }

    /**
     * Clear rate limits for a specific action and user
     *
     * @param string $action_key Unique action identifier
     * @param int    $user_id    User ID
     * @return bool True on success
     */
    public function clear(string $action_key, int $user_id): bool
    {
        $identifier = $user_id > 0
            ? "user_{$user_id}"
            : $this->get_client_ip();

        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'action_key' => $action_key,
                'identifier' => $identifier,
            ],
            ['%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Clean up old rate limit entries
     *
     * Removes entries older than 24 hours
     *
     * @return int Number of deleted rows
     */
    public function cleanup(): int
    {
        $cutoff_time = current_time('mysql', true);
        $cutoff_timestamp = strtotime($cutoff_time) - DAY_IN_SECONDS;
        $cutoff_date = gmdate('Y-m-d H:i:s', $cutoff_timestamp);

        $deleted = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE attempted_at < %s",
            $cutoff_date
        ));

        return (int) $deleted;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip(): string
    {
        // Check for common proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));

                // Handle comma-separated IPs (take first)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get user agent
     *
     * @return string User agent (truncated to 255 chars)
     */
    private function get_user_agent(): string
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return 'unknown';
        }

        $user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        return substr($user_agent, 0, 255);
    }

    /**
     * Create rate limits table
     *
     * Should be called on plugin activation
     *
     * @return bool True on success
     */
    public static function create_table(): bool
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'saga_rate_limits';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_key VARCHAR(100) NOT NULL,
            identifier VARCHAR(100) NOT NULL COMMENT 'User ID or IP address',
            user_id BIGINT UNSIGNED DEFAULT 0,
            success TINYINT(1) DEFAULT 1,
            attempted_at DATETIME NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) DEFAULT '',
            INDEX idx_action_identifier (action_key, identifier),
            INDEX idx_attempted_at (attempted_at),
            INDEX idx_user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        return true;
    }
}
