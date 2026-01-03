<?php
declare(strict_types=1);

/**
 * Analytics Helper Functions
 *
 * Utility functions for integrating analytics throughout the theme
 *
 * @package Saga_Manager_Theme
 */

/**
 * Display popularity badge for entity
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @param bool     $compact Compact display mode
 * @return void
 */
function saga_the_popularity_badge( ?int $entity_id = null, bool $compact = false ): void {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return;
	}

	$class = $compact ? 'saga-popularity-indicators saga-popularity-indicators--compact' : 'saga-popularity-indicators';

	echo '<div class="' . esc_attr( $class ) . '">';
	include get_template_directory() . '/template-parts/popularity-badge.php';
	echo '</div>';
}

/**
 * Get popularity badge HTML
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @param bool     $compact Compact display mode
 * @return string HTML output
 */
function saga_get_popularity_badge( ?int $entity_id = null, bool $compact = false ): string {
	ob_start();
	saga_the_popularity_badge( $entity_id, $compact );
	return ob_get_clean();
}

/**
 * Check if entity is trending
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @return bool Is trending
 */
function saga_is_trending( ?int $entity_id = null ): bool {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return false;
	}

	return Saga_Popularity::is_trending( $entity_id );
}

/**
 * Check if entity is popular
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @return bool Is popular
 */
function saga_is_popular( ?int $entity_id = null ): bool {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return false;
	}

	return Saga_Popularity::is_popular( $entity_id );
}

/**
 * Get formatted view count
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @return string Formatted count
 */
function saga_get_view_count( ?int $entity_id = null ): string {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return '0';
	}

	return Saga_Popularity::get_formatted_views( $entity_id );
}

/**
 * Get popularity score
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @return float Popularity score
 */
function saga_get_popularity_score( ?int $entity_id = null ): float {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return 0.0;
	}

	$stats = Saga_Analytics::get_entity_stats( $entity_id );
	return $stats ? (float) $stats['popularity_score'] : 0.0;
}

/**
 * Display view count
 *
 * @param int|null $entity_id Entity post ID (null = current post)
 * @param bool     $show_icon Show eye icon
 * @return void
 */
function saga_the_view_count( ?int $entity_id = null, bool $show_icon = true ): void {
	if ( ! $entity_id ) {
		$entity_id = get_the_ID();
	}

	if ( ! $entity_id ) {
		return;
	}

	$count = saga_get_view_count( $entity_id );

	if ( $show_icon ) {
		?>
		<span class="saga-view-count">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
				<circle cx="12" cy="12" r="3"/>
			</svg>
			<span><?php echo esc_html( $count ); ?></span>
		</span>
		<?php
	} else {
		echo esc_html( $count );
	}
}

/**
 * Add trending class to post classes
 *
 * @param array $classes Post classes
 * @param array $class Additional classes
 * @param int   $post_id Post ID
 * @return array Modified classes
 */
function saga_add_popularity_classes( array $classes, $class, int $post_id ): array {
	if ( get_post_type( $post_id ) !== 'saga_entity' ) {
		return $classes;
	}

	$badge_type = Saga_Popularity::get_badge_type( $post_id );

	if ( $badge_type ) {
		$classes[] = 'has-popularity-badge';
		$classes[] = 'is-' . $badge_type;
	}

	return $classes;
}
add_filter( 'post_class', 'saga_add_popularity_classes', 10, 3 );

/**
 * Add popularity data to entity query
 *
 * Join stats table for sorting by popularity
 *
 * @param string   $join SQL JOIN clause
 * @param WP_Query $query Query object
 * @return string Modified JOIN clause
 */
function saga_join_popularity_stats( string $join, WP_Query $query ): string {
	if ( ! $query->get( 'orderby_popularity' ) ) {
		return $join;
	}

	global $wpdb;
	$stats_table = $wpdb->prefix . 'saga_entity_stats';

	$join .= " LEFT JOIN {$stats_table} AS stats ON {$wpdb->posts}.ID = stats.entity_id";

	return $join;
}
add_filter( 'posts_join', 'saga_join_popularity_stats', 10, 2 );

/**
 * Order by popularity score
 *
 * @param string   $orderby SQL ORDER BY clause
 * @param WP_Query $query Query object
 * @return string Modified ORDER BY clause
 */
function saga_orderby_popularity( string $orderby, WP_Query $query ): string {
	if ( ! $query->get( 'orderby_popularity' ) ) {
		return $orderby;
	}

	return "stats.popularity_score DESC, {$orderby}";
}
add_filter( 'posts_orderby', 'saga_orderby_popularity', 10, 2 );

/**
 * Query entities by popularity
 *
 * Usage:
 * $query = new WP_Query([
 *     'post_type' => 'saga_entity',
 *     'orderby_popularity' => true,
 *     'posts_per_page' => 10,
 * ]);
 *
 * @param array $args Query arguments
 * @return WP_Query Query object
 */
function saga_query_popular_entities( array $args = array() ): WP_Query {
	$defaults = array(
		'post_type'          => 'saga_entity',
		'orderby_popularity' => true,
		'posts_per_page'     => 10,
		'post_status'        => 'publish',
	);

	return new WP_Query( array_merge( $defaults, $args ) );
}

/**
 * Get trending entities as WP_Query
 *
 * @param int $limit Number of entities
 * @return WP_Query Query object
 */
function saga_get_trending_query( int $limit = 10 ): WP_Query {
	$trending   = Saga_Popularity::get_trending( $limit, 'weekly' );
	$entity_ids = array_column( $trending, 'entity_id' );

	if ( empty( $entity_ids ) ) {
		return new WP_Query( array( 'post__in' => array( 0 ) ) ); // Empty query
	}

	return new WP_Query(
		array(
			'post_type'      => 'saga_entity',
			'post__in'       => $entity_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => $limit,
		)
	);
}

/**
 * Track bookmark action
 *
 * Call this when user bookmarks an entity
 *
 * @param int $entity_id Entity post ID
 * @param int $user_id User ID
 * @return void
 */
function saga_track_bookmark_added( int $entity_id, int $user_id ): void {
	Saga_Analytics::update_bookmark_count( $entity_id, 1 );

	// Update score immediately for bookmarks (high value action)
	Saga_Popularity::update_score( $entity_id );
}

/**
 * Track bookmark removal
 *
 * @param int $entity_id Entity post ID
 * @param int $user_id User ID
 * @return void
 */
function saga_track_bookmark_removed( int $entity_id, int $user_id ): void {
	Saga_Analytics::update_bookmark_count( $entity_id, -1 );
	Saga_Popularity::update_score( $entity_id );
}

/**
 * Track annotation action
 *
 * Call this when user adds an annotation
 *
 * @param int $entity_id Entity post ID
 * @param int $user_id User ID
 * @return void
 */
function saga_track_annotation_added( int $entity_id, int $user_id ): void {
	Saga_Analytics::update_annotation_count( $entity_id, 1 );
	Saga_Popularity::update_score( $entity_id );
}

/**
 * Track annotation removal
 *
 * @param int $entity_id Entity post ID
 * @param int $user_id User ID
 * @return void
 */
function saga_track_annotation_removed( int $entity_id, int $user_id ): void {
	Saga_Analytics::update_annotation_count( $entity_id, -1 );
	Saga_Popularity::update_score( $entity_id );
}

/**
 * Get analytics summary for dashboard
 *
 * @return array Summary statistics
 */
function saga_get_analytics_summary(): array {
	return Saga_Popularity::get_summary_stats();
}

/**
 * Export entity analytics to CSV
 *
 * @param int $entity_id Entity post ID
 * @return string CSV data
 */
function saga_export_entity_analytics( int $entity_id ): string {
	global $wpdb;
	$log_table = $wpdb->prefix . 'saga_view_log';

	$logs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$log_table} WHERE entity_id = %d ORDER BY viewed_at DESC",
			$entity_id
		),
		ARRAY_A
	);

	$csv = "Timestamp,Visitor ID,Time on Page,IP Hash\n";

	foreach ( $logs as $log ) {
		$csv .= sprintf(
			"%s,%s,%s,%s\n",
			$log['viewed_at'],
			substr( $log['visitor_id'], 0, 8 ) . '...',
			$log['time_on_page'] ?? 'N/A',
			substr( $log['ip_hash'] ?? '', 0, 8 ) . '...'
		);
	}

	return $csv;
}

/**
 * Clear analytics for entity
 *
 * Use with caution - deletes all analytics data
 *
 * @param int $entity_id Entity post ID
 * @return bool Success
 */
function saga_clear_entity_analytics( int $entity_id ): bool {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}

	global $wpdb;

	// Delete from stats
	$wpdb->delete(
		$wpdb->prefix . 'saga_entity_stats',
		array( 'entity_id' => $entity_id ),
		array( '%d' )
	);

	// Delete from logs
	$wpdb->delete(
		$wpdb->prefix . 'saga_view_log',
		array( 'entity_id' => $entity_id ),
		array( '%d' )
	);

	// Delete from cache
	$wpdb->delete(
		$wpdb->prefix . 'saga_trending_cache',
		array( 'entity_id' => $entity_id ),
		array( '%d' )
	);

	// Clear object cache
	wp_cache_delete( "entity_stats_{$entity_id}", 'saga_analytics' );

	return true;
}

/**
 * Get entity rank by popularity
 *
 * @param int $entity_id Entity post ID
 * @return int Rank (1-based)
 */
function saga_get_entity_rank( int $entity_id ): int {
	global $wpdb;
	$stats_table = $wpdb->prefix . 'saga_entity_stats';

	$rank = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) + 1 FROM {$stats_table}
        WHERE popularity_score > (
            SELECT popularity_score FROM {$stats_table} WHERE entity_id = %d
        )",
			$entity_id
		)
	);

	return (int) ( $rank ?: 0 );
}

/**
 * Get top entities by metric
 *
 * @param string $metric Metric name (views, bookmarks, annotations, avg_time)
 * @param int    $limit Number of entities
 * @return array Entity IDs with values
 */
function saga_get_top_entities_by_metric( string $metric, int $limit = 10 ): array {
	global $wpdb;
	$stats_table = $wpdb->prefix . 'saga_entity_stats';

	$valid_metrics = array( 'total_views', 'unique_views', 'bookmark_count', 'annotation_count', 'avg_time_on_page' );

	if ( ! in_array( $metric, $valid_metrics, true ) ) {
		return array();
	}

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT entity_id, {$metric} as value
        FROM {$stats_table}
        ORDER BY {$metric} DESC
        LIMIT %d",
			$limit
		),
		ARRAY_A
	);

	return $results ?: array();
}

/**
 * Check if analytics is enabled
 *
 * @return bool Analytics enabled
 */
function saga_is_analytics_enabled(): bool {
	return get_option( 'saga_analytics_enabled', true );
}

/**
 * Enable/disable analytics
 *
 * @param bool $enabled Enable analytics
 * @return void
 */
function saga_set_analytics_enabled( bool $enabled ): void {
	update_option( 'saga_analytics_enabled', $enabled );
}

/**
 * Get analytics settings
 *
 * @return array Settings
 */
function saga_get_analytics_settings(): array {
	return array(
		'enabled'         => saga_is_analytics_enabled(),
		'retention_days'  => (int) get_option( 'saga_analytics_retention_days', 90 ),
		'track_logged_in' => (bool) get_option( 'saga_analytics_track_logged_in', true ),
		'track_admin'     => (bool) get_option( 'saga_analytics_track_admin', false ),
	);
}
