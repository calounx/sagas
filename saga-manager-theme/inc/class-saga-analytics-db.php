<?php
declare(strict_types=1);

/**
 * Analytics Database Schema
 *
 * @package Saga_Manager_Theme
 */

class Saga_Analytics_DB {

    /**
     * Create analytics tables
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Entity stats table
        $stats_table = "CREATE TABLE IF NOT EXISTS {$prefix}saga_entity_stats (
            entity_id BIGINT UNSIGNED NOT NULL,
            total_views INT UNSIGNED DEFAULT 0,
            unique_views INT UNSIGNED DEFAULT 0,
            avg_time_on_page INT UNSIGNED DEFAULT 0 COMMENT 'seconds',
            bookmark_count INT UNSIGNED DEFAULT 0,
            annotation_count INT UNSIGNED DEFAULT 0,
            popularity_score DECIMAL(7,2) DEFAULT 0.00,
            last_viewed DATETIME,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_id),
            INDEX idx_popularity (popularity_score DESC),
            INDEX idx_last_viewed (last_viewed DESC)
        ) ENGINE=InnoDB $charset_collate;";

        // View log table
        $log_table = "CREATE TABLE IF NOT EXISTS {$prefix}saga_view_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_id BIGINT UNSIGNED NOT NULL,
            visitor_id VARCHAR(64) NOT NULL COMMENT 'Cookie hash',
            ip_hash VARCHAR(64) COMMENT 'Anonymized IP',
            user_agent_hash VARCHAR(64) COMMENT 'Anonymized user agent',
            time_on_page INT UNSIGNED COMMENT 'seconds',
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity_id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_viewed_at (viewed_at),
            INDEX idx_cleanup (viewed_at, id)
        ) ENGINE=InnoDB $charset_collate;";

        // Trending cache table
        $trending_table = "CREATE TABLE IF NOT EXISTS {$prefix}saga_trending_cache (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_id BIGINT UNSIGNED NOT NULL,
            trend_score DECIMAL(7,2) NOT NULL,
            period ENUM('hourly','daily','weekly') NOT NULL,
            cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_entity_period (entity_id, period),
            INDEX idx_trend (period, trend_score DESC),
            INDEX idx_cache_time (cached_at)
        ) ENGINE=InnoDB $charset_collate;";

        dbDelta($stats_table);
        dbDelta($log_table);
        dbDelta($trending_table);

        // Set database version
        update_option('saga_analytics_db_version', '1.0.0');
    }

    /**
     * Drop analytics tables
     */
    public static function drop_tables(): void {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $wpdb->query("DROP TABLE IF EXISTS {$prefix}saga_trending_cache");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}saga_view_log");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}saga_entity_stats");

        delete_option('saga_analytics_db_version');
    }

    /**
     * Clean old view logs (GDPR compliance - 90 days retention)
     */
    public static function cleanup_old_logs(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'saga_view_log';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE viewed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            90
        ));

        // Optimize table after bulk delete
        if ($deleted > 1000) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }

        return $deleted ?: 0;
    }
}
