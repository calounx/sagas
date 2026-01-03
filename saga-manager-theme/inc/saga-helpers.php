<?php
/**
 * Saga Manager Helper Functions
 *
 * Utility functions for retrieving and formatting saga entity data
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get entity data from database by post ID
 *
 * @param int $post_id WordPress post ID
 * @return object|null Entity data object or null if not found
 */
function saga_get_entity_by_post_id( int $post_id ): ?object {
	global $wpdb;

	$entity = wp_cache_get( "saga_entity_{$post_id}", 'saga' );

	if ( false === $entity ) {
		$table = $wpdb->prefix . 'saga_entities';

		$entity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE wp_post_id = %d",
				$post_id
			)
		);

		if ( $entity ) {
			wp_cache_set( "saga_entity_{$post_id}", $entity, 'saga', 300 );
		}
	}

	return $entity ?: null;
}

/**
 * Get entity type from post meta
 *
 * @param int $post_id WordPress post ID
 * @return string|null Entity type (character, location, event, etc.) or null
 */
function saga_get_entity_type( int $post_id ): ?string {
	$entity = saga_get_entity_by_post_id( $post_id );

	if ( $entity && isset( $entity->entity_type ) ) {
		return $entity->entity_type;
	}

	// Fallback to post meta
	$type = get_post_meta( $post_id, '_saga_entity_type', true );
	return ! empty( $type ) ? $type : null;
}

/**
 * Get entity importance score
 *
 * @param int $post_id WordPress post ID
 * @return int Importance score (0-100)
 */
function saga_get_importance_score( int $post_id ): int {
	$entity = saga_get_entity_by_post_id( $post_id );

	if ( $entity && isset( $entity->importance_score ) ) {
		return (int) $entity->importance_score;
	}

	// Fallback to post meta
	$score = get_post_meta( $post_id, '_saga_importance_score', true );
	return ! empty( $score ) ? (int) $score : 50;
}

/**
 * Get entity relationships from database
 *
 * @param int    $post_id WordPress post ID
 * @param string $type Optional relationship type filter
 * @return array Array of relationship objects
 */
function saga_get_entity_relationships( int $post_id, string $type = '' ): array {
	global $wpdb;

	$entity = saga_get_entity_by_post_id( $post_id );

	if ( ! $entity ) {
		return array();
	}

	$cache_key     = "saga_relationships_{$entity->id}" . ( $type ? "_{$type}" : '' );
	$relationships = wp_cache_get( $cache_key, 'saga' );

	if ( false === $relationships ) {
		$rel_table    = $wpdb->prefix . 'saga_entity_relationships';
		$entity_table = $wpdb->prefix . 'saga_entities';

		$sql = "SELECT r.*, 
                    se.canonical_name as source_name,
                    se.wp_post_id as source_post_id,
                    te.canonical_name as target_name,
                    te.wp_post_id as target_post_id,
                    te.entity_type as target_type
                FROM {$rel_table} r
                LEFT JOIN {$entity_table} se ON r.source_entity_id = se.id
                LEFT JOIN {$entity_table} te ON r.target_entity_id = te.id
                WHERE (r.source_entity_id = %d OR r.target_entity_id = %d)";

		$params = array( $entity->id, $entity->id );

		if ( ! empty( $type ) ) {
			$sql     .= ' AND r.relationship_type = %s';
			$params[] = $type;
		}

		$sql .= ' ORDER BY r.strength DESC, r.created_at DESC';

		$relationships = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );

		wp_cache_set( $cache_key, $relationships, 'saga', 300 );
	}

	return $relationships ?: array();
}

/**
 * Get timeline events for an entity
 *
 * @param int $post_id WordPress post ID
 * @param int $limit Maximum number of events to retrieve
 * @return array Array of timeline event objects
 */
function saga_get_entity_timeline_events( int $post_id, int $limit = 10 ): array {
	global $wpdb;

	$entity = saga_get_entity_by_post_id( $post_id );

	if ( ! $entity ) {
		return array();
	}

	$cache_key = "saga_timeline_{$entity->id}_{$limit}";
	$events    = wp_cache_get( $cache_key, 'saga' );

	if ( false === $events ) {
		$table = $wpdb->prefix . 'saga_timeline_events';

		// Get events where this entity participated or is the event itself
		$sql = "SELECT * FROM {$table} 
                WHERE event_entity_id = %d 
                   OR JSON_CONTAINS(participants, %s, '$')
                ORDER BY normalized_timestamp ASC
                LIMIT %d";

		$events = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$entity->id,
				json_encode( $entity->id ),
				$limit
			)
		);

		wp_cache_set( $cache_key, $events, 'saga', 300 );
	}

	return $events ?: array();
}

/**
 * Get saga name by ID
 *
 * @param int $saga_id Saga ID from database
 * @return string Saga name
 */
function saga_get_saga_name( int $saga_id ): string {
	global $wpdb;

	$cache_key = "saga_name_{$saga_id}";
	$name      = wp_cache_get( $cache_key, 'saga' );

	if ( false === $name ) {
		$table = $wpdb->prefix . 'saga_sagas';

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$table} WHERE id = %d",
				$saga_id
			)
		);

		if ( $name ) {
			wp_cache_set( $cache_key, $name, 'saga', 600 );
		}
	}

	return $name ?: __( 'Unknown Saga', 'saga-manager-theme' );
}

/**
 * Format importance score with HTML markup
 *
 * @param int  $score Importance score (0-100)
 * @param bool $show_bar Whether to show visual bar
 * @return string HTML markup for importance score
 */
function saga_format_importance_score( int $score, bool $show_bar = true ): string {
	$score = max( 0, min( 100, $score ) ); // Clamp between 0-100

	$output = '<div class="saga-importance-score">';

	if ( $show_bar ) {
		$output .= sprintf(
			'<div class="saga-importance-score__bar">
                <div class="saga-importance-score__fill" style="width: %d%%"></div>
            </div>',
			$score
		);
	}

	$output .= sprintf(
		'<span class="saga-importance-score__value">%d</span>',
		$score
	);

	$output .= '</div>';

	return $output;
}

/**
 * Get entity type badge HTML
 *
 * @param string $type Entity type
 * @return string HTML markup for entity type badge
 */
function saga_get_entity_type_badge( string $type ): string {
	$types = array(
		'character' => __( 'Character', 'saga-manager-theme' ),
		'location'  => __( 'Location', 'saga-manager-theme' ),
		'event'     => __( 'Event', 'saga-manager-theme' ),
		'faction'   => __( 'Faction', 'saga-manager-theme' ),
		'artifact'  => __( 'Artifact', 'saga-manager-theme' ),
		'concept'   => __( 'Concept', 'saga-manager-theme' ),
	);

	$label = $types[ $type ] ?? ucfirst( $type );

	return sprintf(
		'<span class="saga-badge saga-badge--%s">%s</span>',
		esc_attr( $type ),
		esc_html( $label )
	);
}

/**
 * Get relationship strength label
 *
 * @param int $strength Relationship strength (0-100)
 * @return string Strength label (High, Medium, Low)
 */
function saga_get_relationship_strength_label( int $strength ): string {
	if ( $strength >= 70 ) {
		return __( 'High', 'saga-manager-theme' );
	} elseif ( $strength >= 40 ) {
		return __( 'Medium', 'saga-manager-theme' );
	} else {
		return __( 'Low', 'saga-manager-theme' );
	}
}

/**
 * Get relationship strength class
 *
 * @param int $strength Relationship strength (0-100)
 * @return string CSS class name
 */
function saga_get_relationship_strength_class( int $strength ): string {
	if ( $strength >= 70 ) {
		return 'saga-relationships__strength--high';
	} elseif ( $strength >= 40 ) {
		return 'saga-relationships__strength--medium';
	} else {
		return 'saga-relationships__strength--low';
	}
}

/**
 * Get all available sagas for filters
 *
 * @return array Array of saga objects (id, name)
 */
function saga_get_all_sagas(): array {
	global $wpdb;

	$cache_key = 'saga_all_sagas';
	$sagas     = wp_cache_get( $cache_key, 'saga' );

	if ( false === $sagas ) {
		$table = $wpdb->prefix . 'saga_sagas';

		$sagas = $wpdb->get_results(
			"SELECT id, name FROM {$table} ORDER BY name ASC"
		);

		wp_cache_set( $cache_key, $sagas, 'saga', 600 );
	}

	return $sagas ?: array();
}

/**
 * Get entity quality metrics
 *
 * @param int $post_id WordPress post ID
 * @return object|null Quality metrics object or null
 */
function saga_get_quality_metrics( int $post_id ): ?object {
	global $wpdb;

	$entity = saga_get_entity_by_post_id( $post_id );

	if ( ! $entity ) {
		return null;
	}

	$cache_key = "saga_quality_{$entity->id}";
	$metrics   = wp_cache_get( $cache_key, 'saga' );

	if ( false === $metrics ) {
		$table = $wpdb->prefix . 'saga_quality_metrics';

		$metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_id = %d",
				$entity->id
			)
		);

		if ( $metrics ) {
			wp_cache_set( $cache_key, $metrics, 'saga', 600 );
		}
	}

	return $metrics ?: null;
}

/**
 * Format canon date for display
 *
 * @param string $canon_date Canon date from database
 * @return string Formatted date
 */
function saga_format_canon_date( string $canon_date ): string {
	// Simply return the canon date as is (e.g., "10191 AG", "4 ABY")
	return esc_html( $canon_date );
}

/**
 * Check if entity has relationships
 *
 * @param int $post_id WordPress post ID
 * @return bool True if entity has relationships
 */
function saga_entity_has_relationships( int $post_id ): bool {
	$relationships = saga_get_entity_relationships( $post_id );
	return ! empty( $relationships );
}

/**
 * Check if entity has timeline events
 *
 * @param int $post_id WordPress post ID
 * @return bool True if entity has timeline events
 */
function saga_entity_has_timeline_events( int $post_id ): bool {
	$events = saga_get_entity_timeline_events( $post_id, 1 );
	return ! empty( $events );
}

/**
 * Get entity URL by database ID
 *
 * @param int $entity_id Entity ID from database
 * @return string|null Post permalink or null
 */
function saga_get_entity_url_by_id( int $entity_id ): ?string {
	global $wpdb;

	$table = $wpdb->prefix . 'saga_entities';

	$post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT wp_post_id FROM {$table} WHERE id = %d",
			$entity_id
		)
	);

	if ( $post_id ) {
		return get_permalink( $post_id );
	}

	return null;
}
