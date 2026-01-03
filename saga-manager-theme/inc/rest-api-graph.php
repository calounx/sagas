<?php
declare(strict_types=1);

/**
 * REST API Endpoints for Relationship Graph
 *
 * Provides RESTful access to entity relationship data
 *
 * @package SagaManager
 * @since 1.0.0
 */

namespace SagaManager\Theme\RestAPI;

/**
 * Register REST API routes for graph data
 */
function register_graph_rest_routes(): void {
	register_rest_route(
		'saga/v1',
		'/entities/(?P<id>\d+)/relationships',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\get_entity_relationships',
			'permission_callback' => '__return_true', // Public endpoint
			'args'                => array(
				'id'                => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0;
					},
				),
				'depth'             => array(
					'required'          => false,
					'default'           => 1,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param >= 1 && $param <= 3;
					},
					'sanitize_callback' => 'absint',
				),
				'entity_type'       => array(
					'required'          => false,
					'default'           => '',
					'validate_callback' => function ( $param ) {
						$valid_types = array( 'character', 'location', 'event', 'faction', 'artifact', 'concept' );
						return empty( $param ) || in_array( $param, $valid_types, true );
					},
					'sanitize_callback' => 'sanitize_key',
				),
				'relationship_type' => array(
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'limit'             => array(
					'required'          => false,
					'default'           => 100,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0 && $param <= 100;
					},
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	register_rest_route(
		'saga/v1',
		'/graph/all',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\get_all_entities_graph',
			'permission_callback' => '__return_true',
			'args'                => array(
				'entity_type'       => array(
					'required'          => false,
					'default'           => '',
					'validate_callback' => function ( $param ) {
						$valid_types = array( 'character', 'location', 'event', 'faction', 'artifact', 'concept' );
						return empty( $param ) || in_array( $param, $valid_types, true );
					},
					'sanitize_callback' => 'sanitize_key',
				),
				'relationship_type' => array(
					'required'          => false,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'limit'             => array(
					'required'          => false,
					'default'           => 100,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && $param > 0 && $param <= 100;
					},
					'sanitize_callback' => 'absint',
				),
			),
		)
	);

	register_rest_route(
		'saga/v1',
		'/graph/types',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\get_relationship_types',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_graph_rest_routes' );

/**
 * Get entity relationships
 *
 * @param \WP_REST_Request $request Request object
 * @return \WP_REST_Response Response object
 */
function get_entity_relationships( \WP_REST_Request $request ): \WP_REST_Response {
	$entity_id         = (int) $request->get_param( 'id' );
	$depth             = (int) $request->get_param( 'depth' );
	$entity_type       = $request->get_param( 'entity_type' );
	$relationship_type = $request->get_param( 'relationship_type' );
	$limit             = (int) $request->get_param( 'limit' );

	// Check if entity exists
	global $wpdb;
	$entities_table = $wpdb->prefix . 'saga_entities';

	$entity_exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$entities_table} WHERE id = %d",
			$entity_id
		)
	);

	if ( ! $entity_exists ) {
		return new \WP_REST_Response(
			array(
				'code'    => 'entity_not_found',
				'message' => 'Entity not found',
				'data'    => array( 'status' => 404 ),
			),
			404
		);
	}

	// Check cache
	$cache_key   = "saga_rest_graph_{$entity_id}_{$depth}_{$entity_type}_{$relationship_type}_{$limit}";
	$cached_data = wp_cache_get( $cache_key, 'saga_rest' );

	if ( false !== $cached_data ) {
		return new \WP_REST_Response( $cached_data, 200 );
	}

	try {
		require_once get_template_directory() . '/inc/ajax-graph-data.php';

		$graph_data = \SagaManager\Theme\Ajax\build_graph_data(
			$entity_id,
			$depth,
			$entity_type,
			$relationship_type,
			$limit
		);

		// Add cache indicator
		$graph_data['metadata']['cached'] = false;

		// Cache for 5 minutes
		wp_cache_set( $cache_key, $graph_data, 'saga_rest', 300 );

		return new \WP_REST_Response( $graph_data, 200 );

	} catch ( \Exception $e ) {
		error_log( '[SAGA][ERROR] REST graph endpoint failed: ' . $e->getMessage() );

		return new \WP_REST_Response(
			array(
				'code'    => 'graph_generation_failed',
				'message' => 'Failed to generate graph data',
				'data'    => array( 'status' => 500 ),
			),
			500
		);
	}
}

/**
 * Get all entities graph (no specific root)
 *
 * @param \WP_REST_Request $request Request object
 * @return \WP_REST_Response Response object
 */
function get_all_entities_graph( \WP_REST_Request $request ): \WP_REST_Response {
	$entity_type       = $request->get_param( 'entity_type' );
	$relationship_type = $request->get_param( 'relationship_type' );
	$limit             = (int) $request->get_param( 'limit' );

	// Check cache
	$cache_key   = "saga_rest_graph_all_{$entity_type}_{$relationship_type}_{$limit}";
	$cached_data = wp_cache_get( $cache_key, 'saga_rest' );

	if ( false !== $cached_data ) {
		return new \WP_REST_Response( $cached_data, 200 );
	}

	try {
		require_once get_template_directory() . '/inc/ajax-graph-data.php';

		$graph_data = \SagaManager\Theme\Ajax\build_graph_data(
			0, // No root entity
			1, // Depth 1 (all direct relationships)
			$entity_type,
			$relationship_type,
			$limit
		);

		// Cache for 10 minutes
		wp_cache_set( $cache_key, $graph_data, 'saga_rest', 600 );

		return new \WP_REST_Response( $graph_data, 200 );

	} catch ( \Exception $e ) {
		error_log( '[SAGA][ERROR] REST graph all endpoint failed: ' . $e->getMessage() );

		return new \WP_REST_Response(
			array(
				'code'    => 'graph_generation_failed',
				'message' => 'Failed to generate graph data',
				'data'    => array( 'status' => 500 ),
			),
			500
		);
	}
}

/**
 * Get available relationship types
 *
 * @param \WP_REST_Request $request Request object
 * @return \WP_REST_Response Response object
 */
function get_relationship_types( \WP_REST_Request $request ): \WP_REST_Response {
	global $wpdb;

	// Check cache
	$cache_key   = 'saga_rest_relationship_types';
	$cached_data = wp_cache_get( $cache_key, 'saga_rest' );

	if ( false !== $cached_data ) {
		return new \WP_REST_Response( $cached_data, 200 );
	}

	$relationships_table = $wpdb->prefix . 'saga_entity_relationships';

	$types = $wpdb->get_col(
		"SELECT DISTINCT relationship_type
         FROM {$relationships_table}
         ORDER BY relationship_type ASC"
	);

	$formatted_types = array_map(
		function ( $type ) {
			return array(
				'value' => $type,
				'label' => ucwords( str_replace( '_', ' ', $type ) ),
			);
		},
		$types
	);

	$result = array(
		'types' => $formatted_types,
		'total' => count( $formatted_types ),
	);

	// Cache for 1 hour
	wp_cache_set( $cache_key, $result, 'saga_rest', 3600 );

	return new \WP_REST_Response( $result, 200 );
}
