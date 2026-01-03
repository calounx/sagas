<?php
declare(strict_types=1);

/**
 * AJAX Handlers for Analytics
 *
 * @package Saga_Manager_Theme
 */

/**
 * Track entity view
 */
function saga_ajax_track_view(): void {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_analytics', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	// Get and validate inputs
	$entity_id  = absint( $_POST['entity_id'] ?? 0 );
	$visitor_id = sanitize_text_field( $_POST['visitor_id'] ?? '' );

	if ( ! $entity_id || ! $visitor_id ) {
		wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
	}

	// Track the view
	$success = Saga_Analytics::track_view( $entity_id, $visitor_id );

	if ( $success ) {
		wp_send_json_success(
			array(
				'message'   => 'View tracked',
				'entity_id' => $entity_id,
			)
		);
	} else {
		wp_send_json_error( array( 'message' => 'View not tracked' ), 400 );
	}
}

add_action( 'wp_ajax_saga_track_view', 'saga_ajax_track_view' );
add_action( 'wp_ajax_nopriv_saga_track_view', 'saga_ajax_track_view' );

/**
 * Track time spent on page
 */
function saga_ajax_track_duration(): void {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_analytics', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	// Get and validate inputs
	$entity_id  = absint( $_POST['entity_id'] ?? 0 );
	$visitor_id = sanitize_text_field( $_POST['visitor_id'] ?? '' );
	$duration   = absint( $_POST['duration'] ?? 0 );

	if ( ! $entity_id || ! $visitor_id || ! $duration ) {
		wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
	}

	// Track the duration
	$success = Saga_Analytics::track_duration( $entity_id, $visitor_id, $duration );

	if ( $success ) {
		wp_send_json_success(
			array(
				'message'   => 'Duration tracked',
				'entity_id' => $entity_id,
				'duration'  => $duration,
			)
		);
	} else {
		wp_send_json_error( array( 'message' => 'Duration not tracked' ), 400 );
	}
}

add_action( 'wp_ajax_saga_track_duration', 'saga_ajax_track_duration' );
add_action( 'wp_ajax_nopriv_saga_track_duration', 'saga_ajax_track_duration' );

/**
 * Track custom action
 */
function saga_ajax_track_custom_action(): void {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_analytics', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	// Get and validate inputs
	$entity_id   = absint( $_POST['entity_id'] ?? 0 );
	$visitor_id  = sanitize_text_field( $_POST['visitor_id'] ?? '' );
	$action_name = sanitize_text_field( $_POST['action_name'] ?? '' );

	if ( ! $entity_id || ! $visitor_id || ! $action_name ) {
		wp_send_json_error( array( 'message' => 'Missing required parameters' ), 400 );
	}

	// Handle specific actions
	switch ( $action_name ) {
		case 'bookmark_added':
			Saga_Analytics::update_bookmark_count( $entity_id, 1 );
			wp_send_json_success( array( 'message' => 'Bookmark tracked' ) );
			break;

		case 'bookmark_removed':
			Saga_Analytics::update_bookmark_count( $entity_id, -1 );
			wp_send_json_success( array( 'message' => 'Bookmark removal tracked' ) );
			break;

		case 'annotation_added':
			Saga_Analytics::update_annotation_count( $entity_id, 1 );
			wp_send_json_success( array( 'message' => 'Annotation tracked' ) );
			break;

		case 'annotation_removed':
			Saga_Analytics::update_annotation_count( $entity_id, -1 );
			wp_send_json_success( array( 'message' => 'Annotation removal tracked' ) );
			break;

		default:
			wp_send_json_error( array( 'message' => 'Unknown action' ), 400 );
	}
}

add_action( 'wp_ajax_saga_track_custom_action', 'saga_ajax_track_custom_action' );
add_action( 'wp_ajax_nopriv_saga_track_custom_action', 'saga_ajax_track_custom_action' );

/**
 * Get entity popularity stats (for dynamic updates)
 */
function saga_ajax_get_popularity_stats(): void {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_analytics', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	$entity_id = absint( $_POST['entity_id'] ?? 0 );

	if ( ! $entity_id ) {
		wp_send_json_error( array( 'message' => 'Missing entity_id' ), 400 );
	}

	$stats = Saga_Analytics::get_entity_stats( $entity_id );

	if ( ! $stats ) {
		wp_send_json_success(
			array(
				'views'            => 0,
				'popularity_score' => 0,
				'badge_type'       => null,
			)
		);
		return;
	}

	wp_send_json_success(
		array(
			'views'            => (int) $stats['total_views'],
			'unique_views'     => (int) $stats['unique_views'],
			'popularity_score' => (float) $stats['popularity_score'],
			'badge_type'       => Saga_Popularity::get_badge_type( $entity_id ),
			'formatted_views'  => Saga_Popularity::get_formatted_views( $entity_id ),
		)
	);
}

add_action( 'wp_ajax_saga_get_popularity_stats', 'saga_ajax_get_popularity_stats' );
add_action( 'wp_ajax_nopriv_saga_get_popularity_stats', 'saga_ajax_get_popularity_stats' );

/**
 * Get trending entities
 */
function saga_ajax_get_trending(): void {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_analytics', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
	}

	$limit  = absint( $_POST['limit'] ?? 10 );
	$period = sanitize_text_field( $_POST['period'] ?? 'weekly' );

	if ( ! in_array( $period, array( 'hourly', 'daily', 'weekly' ), true ) ) {
		$period = 'weekly';
	}

	$trending = Saga_Popularity::get_trending( $limit, $period );

	// Enrich with post data
	$entities = array();
	foreach ( $trending as $item ) {
		$post = get_post( $item['entity_id'] );
		if ( $post ) {
			$entities[] = array(
				'id'    => $item['entity_id'],
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'score' => $item['trend_score'],
				'views' => Saga_Popularity::get_formatted_views( $item['entity_id'] ),
			);
		}
	}

	wp_send_json_success( array( 'entities' => $entities ) );
}

add_action( 'wp_ajax_saga_get_trending', 'saga_ajax_get_trending' );
add_action( 'wp_ajax_nopriv_saga_get_trending', 'saga_ajax_get_trending' );

/**
 * Enqueue analytics scripts
 */
function saga_enqueue_analytics_scripts(): void {
	// Only enqueue on single entity pages
	if ( ! is_singular( 'saga_entity' ) ) {
		return;
	}

	wp_enqueue_script(
		'saga-view-tracker',
		get_template_directory_uri() . '/assets/js/view-tracker.js',
		array(),
		wp_get_theme()->get( 'Version' ),
		true
	);

	// Pass data to JavaScript
	wp_localize_script(
		'saga-view-tracker',
		'sagaAnalytics',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'saga_analytics' ),
			'entityId' => get_the_ID(),
		)
	);
}

add_action( 'wp_enqueue_scripts', 'saga_enqueue_analytics_scripts' );
