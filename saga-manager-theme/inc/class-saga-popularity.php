<?php
declare(strict_types=1);

/**
 * Popularity Score Calculator
 *
 * Weighted algorithm for entity popularity
 *
 * @package Saga_Manager_Theme
 */

class Saga_Popularity {

    /**
     * Scoring weights
     */
    private const WEIGHTS = [
        'views'         => 1.0,
        'unique_views'  => 2.0,
        'bookmarks'     => 5.0,
        'annotations'   => 3.0,
        'avg_time'      => 0.1,  // per second
        'recency'       => 2.0,  // boost for recent views
    ];

    /**
     * Popularity thresholds
     */
    private const THRESHOLDS = [
        'trending'  => 100.0,  // Trending if score > 100 in last 7 days
        'popular'   => 50.0,   // Popular if score > 50
        'rising'    => 20.0,   // Rising if score > 20 and recent growth
    ];

    /**
     * Calculate popularity score for entity
     *
     * @param int $entity_id Entity post ID
     * @return float Popularity score
     */
    public static function calculate_score(int $entity_id): float {
        $stats = Saga_Analytics::get_entity_stats($entity_id);

        if (!$stats) {
            return 0.0;
        }

        $score = 0.0;

        // Base metrics
        $score += (int) $stats['total_views'] * self::WEIGHTS['views'];
        $score += (int) $stats['unique_views'] * self::WEIGHTS['unique_views'];
        $score += (int) $stats['bookmark_count'] * self::WEIGHTS['bookmarks'];
        $score += (int) $stats['annotation_count'] * self::WEIGHTS['annotations'];

        // Time engagement (convert seconds to minutes)
        $avg_time_minutes = (int) $stats['avg_time_on_page'] / 60;
        $score += $avg_time_minutes * self::WEIGHTS['avg_time'];

        // Recency boost (last 7 days)
        if (!empty($stats['last_viewed'])) {
            $last_viewed = strtotime($stats['last_viewed']);
            $days_since = (time() - $last_viewed) / 86400;

            if ($days_since <= 7) {
                $recency_factor = (7 - $days_since) / 7; // 1.0 to 0.0
                $score += $recency_factor * self::WEIGHTS['recency'] * 100;
            }
        }

        return round($score, 2);
    }

    /**
     * Update popularity score for entity
     *
     * @param int $entity_id Entity post ID
     * @return float Updated score
     */
    public static function update_score(int $entity_id): float {
        global $wpdb;

        $score = self::calculate_score($entity_id);

        $table = $wpdb->prefix . 'saga_entity_stats';

        $wpdb->update(
            $table,
            ['popularity_score' => $score],
            ['entity_id' => $entity_id],
            ['%f'],
            ['%d']
        );

        wp_cache_delete("entity_stats_{$entity_id}", 'saga_analytics');

        return $score;
    }

    /**
     * Batch update scores for all entities
     *
     * @param int $limit Number of entities to update per batch
     * @return int Number of entities updated
     */
    public static function batch_update_scores(int $limit = 100): int {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'saga_entity_stats';

        // Get entities with outdated scores (not updated in last hour)
        $entity_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT entity_id FROM {$stats_table}
            WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY updated_at ASC
            LIMIT %d",
            $limit
        ));

        $updated = 0;

        foreach ($entity_ids as $entity_id) {
            self::update_score((int) $entity_id);
            $updated++;
        }

        return $updated;
    }

    /**
     * Get trending entities
     *
     * @param int    $limit Max entities to return
     * @param string $period Time period (hourly, daily, weekly)
     * @return array Entity IDs with scores
     */
    public static function get_trending(int $limit = 10, string $period = 'weekly'): array {
        global $wpdb;

        // Check cache first
        $cache_key = "trending_{$period}_{$limit}";
        $cached = wp_cache_get($cache_key, 'saga_analytics');

        if (false !== $cached) {
            return $cached;
        }

        $trending_table = $wpdb->prefix . 'saga_trending_cache';
        $stats_table = $wpdb->prefix . 'saga_entity_stats';

        // Get from cache table if recent
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id, trend_score FROM {$trending_table}
            WHERE period = %s
            AND cached_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ORDER BY trend_score DESC
            LIMIT %d",
            $period,
            $limit
        ), ARRAY_A);

        if (!empty($results)) {
            wp_cache_set($cache_key, $results, 'saga_analytics', 900); // 15 min TTL
            return $results;
        }

        // Calculate fresh trending data
        $interval = self::get_interval_for_period($period);

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id, popularity_score as trend_score
            FROM {$stats_table}
            WHERE last_viewed >= DATE_SUB(NOW(), INTERVAL {$interval})
            AND popularity_score >= %f
            ORDER BY popularity_score DESC
            LIMIT %d",
            self::THRESHOLDS['trending'],
            $limit
        ), ARRAY_A);

        // Update cache table
        self::cache_trending_results($results, $period);

        wp_cache_set($cache_key, $results, 'saga_analytics', 900);

        return $results;
    }

    /**
     * Get popular entities (all-time)
     *
     * @param int $limit Max entities to return
     * @return array Entity IDs with scores
     */
    public static function get_popular(int $limit = 10): array {
        global $wpdb;

        $cache_key = "popular_entities_{$limit}";
        $cached = wp_cache_get($cache_key, 'saga_analytics');

        if (false !== $cached) {
            return $cached;
        }

        $stats_table = $wpdb->prefix . 'saga_entity_stats';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT entity_id, popularity_score
            FROM {$stats_table}
            WHERE popularity_score >= %f
            ORDER BY popularity_score DESC
            LIMIT %d",
            self::THRESHOLDS['popular'],
            $limit
        ), ARRAY_A);

        wp_cache_set($cache_key, $results, 'saga_analytics', 900);

        return $results;
    }

    /**
     * Check if entity is trending
     *
     * @param int $entity_id Entity post ID
     * @return bool Is trending
     */
    public static function is_trending(int $entity_id): bool {
        $stats = Saga_Analytics::get_entity_stats($entity_id);

        if (!$stats || empty($stats['last_viewed'])) {
            return false;
        }

        $last_viewed = strtotime($stats['last_viewed']);
        $days_since = (time() - $last_viewed) / 86400;

        return $days_since <= 7 && (float) $stats['popularity_score'] >= self::THRESHOLDS['trending'];
    }

    /**
     * Check if entity is popular
     *
     * @param int $entity_id Entity post ID
     * @return bool Is popular
     */
    public static function is_popular(int $entity_id): bool {
        $stats = Saga_Analytics::get_entity_stats($entity_id);

        if (!$stats) {
            return false;
        }

        return (float) $stats['popularity_score'] >= self::THRESHOLDS['popular'];
    }

    /**
     * Get popularity badge type
     *
     * @param int $entity_id Entity post ID
     * @return string|null Badge type (trending, popular, rising) or null
     */
    public static function get_badge_type(int $entity_id): ?string {
        if (self::is_trending($entity_id)) {
            return 'trending';
        }

        if (self::is_popular($entity_id)) {
            return 'popular';
        }

        $stats = Saga_Analytics::get_entity_stats($entity_id);
        if ($stats && (float) $stats['popularity_score'] >= self::THRESHOLDS['rising']) {
            return 'rising';
        }

        return null;
    }

    /**
     * Get formatted view count
     *
     * @param int $entity_id Entity post ID
     * @return string Formatted count (e.g., "1.2k", "500")
     */
    public static function get_formatted_views(int $entity_id): string {
        $stats = Saga_Analytics::get_entity_stats($entity_id);

        if (!$stats) {
            return '0';
        }

        $views = (int) $stats['total_views'];

        if ($views >= 1000000) {
            return round($views / 1000000, 1) . 'M';
        }

        if ($views >= 1000) {
            return round($views / 1000, 1) . 'k';
        }

        return (string) $views;
    }

    /**
     * Cache trending results in database
     */
    private static function cache_trending_results(array $results, string $period): void {
        global $wpdb;

        $table = $wpdb->prefix . 'saga_trending_cache';

        // Clear old cache for this period
        $wpdb->delete($table, ['period' => $period], ['%s']);

        // Insert new cache
        foreach ($results as $result) {
            $wpdb->insert(
                $table,
                [
                    'entity_id' => $result['entity_id'],
                    'trend_score' => $result['trend_score'],
                    'period' => $period,
                ],
                ['%d', '%f', '%s']
            );
        }
    }

    /**
     * Get SQL interval for period
     */
    private static function get_interval_for_period(string $period): string {
        switch ($period) {
            case 'hourly':
                return '1 HOUR';
            case 'daily':
                return '1 DAY';
            case 'weekly':
            default:
                return '7 DAY';
        }
    }

    /**
     * Get statistics summary
     *
     * @return array Summary stats
     */
    public static function get_summary_stats(): array {
        global $wpdb;

        $stats_table = $wpdb->prefix . 'saga_entity_stats';
        $log_table = $wpdb->prefix . 'saga_view_log';

        $cache_key = 'analytics_summary';
        $cached = wp_cache_get($cache_key, 'saga_analytics');

        if (false !== $cached) {
            return $cached;
        }

        $summary = [
            'total_entities_tracked' => 0,
            'total_views' => 0,
            'total_unique_visitors' => 0,
            'avg_popularity_score' => 0.0,
            'views_last_24h' => 0,
            'trending_count' => 0,
        ];

        // Get aggregated stats
        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_entities,
                SUM(total_views) as total_views,
                AVG(popularity_score) as avg_score
            FROM {$stats_table}",
            ARRAY_A
        );

        if ($stats) {
            $summary['total_entities_tracked'] = (int) $stats['total_entities'];
            $summary['total_views'] = (int) $stats['total_views'];
            $summary['avg_popularity_score'] = round((float) $stats['avg_score'], 2);
        }

        // Unique visitors
        $summary['total_unique_visitors'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT visitor_id) FROM {$log_table}"
        );

        // Views last 24h
        $summary['views_last_24h'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$log_table}
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Trending count
        $summary['trending_count'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$stats_table}
            WHERE popularity_score >= %f
            AND last_viewed >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            self::THRESHOLDS['trending']
        ));

        wp_cache_set($cache_key, $summary, 'saga_analytics', 300);

        return $summary;
    }
}
