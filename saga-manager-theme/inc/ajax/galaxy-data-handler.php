<?php
/**
 * Galaxy Data AJAX Handler
 *
 * Provides entity and relationship data for 3D galaxy visualization.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register AJAX handlers
 */
function saga_register_galaxy_ajax_handlers() {
	add_action( 'wp_ajax_saga_galaxy_data', 'saga_handle_galaxy_data_request' );
	add_action( 'wp_ajax_nopriv_saga_galaxy_data', 'saga_handle_galaxy_data_request' );
}
add_action( 'init', 'saga_register_galaxy_ajax_handlers' );

/**
 * Handle galaxy data AJAX request
 */
function saga_handle_galaxy_data_request() {
	// Verify nonce
	if ( ! check_ajax_referer( 'saga_galaxy_nonce', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid nonce', 403 );
	}

	// Capability check - SECURITY: Prevent unauthorized access
	if ( ! current_user_can( 'read' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
	}

	// Get saga ID
	$saga_id = isset( $_POST['saga_id'] ) ? absint( $_POST['saga_id'] ) : 0;

	if ( ! $saga_id ) {
		wp_send_json_error( 'Invalid saga ID', 400 );
	}

	// Check if saga exists
	$saga = get_post( $saga_id );
	if ( ! $saga ) {
		wp_send_json_error( 'Saga not found', 404 );
	}

	try {
		// Get nodes (entities)
		$nodes = saga_get_galaxy_nodes( $saga_id );

		// Get links (relationships)
		$links = saga_get_galaxy_links( $saga_id );

		// Success response
		wp_send_json_success(
			array(
				'nodes' => $nodes,
				'links' => $links,
				'saga'  => array(
					'id'   => $saga_id,
					'name' => get_the_title( $saga_id ),
					'url'  => get_permalink( $saga_id ),
				),
				'stats' => array(
					'nodeCount' => count( $nodes ),
					'linkCount' => count( $links ),
				),
			)
		);

	} catch ( Exception $e ) {
		error_log( '[SAGA][GALAXY] Error fetching data: ' . $e->getMessage() );
		wp_send_json_error( 'Failed to fetch galaxy data', 500 );
	}
}

/**
 * Get galaxy nodes (entities) for a saga
 *
 * @param int $saga_id Saga post ID
 * @return array Array of node objects
 */
function saga_get_galaxy_nodes( $saga_id ) {
	global $wpdb;

	// Try to get from cache first
	$cache_key = 'saga_galaxy_nodes_' . $saga_id;
	$cached    = wp_cache_get( $cache_key, 'saga' );

	if ( false !== $cached ) {
		return $cached;
	}

	$nodes = array();

	// Query entities from custom table (if using plugin architecture)
	$table_name = $wpdb->prefix . 'saga_entities';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
		// Using custom table
		$nodes = saga_get_nodes_from_custom_table( $saga_id, $table_name );
	} else {
		// Fallback to WordPress posts
		$nodes = saga_get_nodes_from_posts( $saga_id );
	}

	// Cache for 5 minutes
	wp_cache_set( $cache_key, $nodes, 'saga', 300 );

	return $nodes;
}

/**
 * Get nodes from custom saga_entities table
 *
 * @param int    $saga_id Saga ID
 * @param string $table_name Table name
 * @return array
 */
function saga_get_nodes_from_custom_table( $saga_id, $table_name ) {
	global $wpdb;

	$query = $wpdb->prepare(
		"SELECT
            e.id,
            e.canonical_name AS name,
            e.entity_type AS type,
            e.importance_score AS importance,
            e.slug,
            p.post_content AS description,
            p.guid AS url
        FROM {$table_name} e
        LEFT JOIN {$wpdb->posts} p ON e.wp_post_id = p.ID
        WHERE e.saga_id = %d
        ORDER BY e.importance_score DESC
        LIMIT 1000",
		$saga_id
	);

	$results = $wpdb->get_results( $query, ARRAY_A );

	if ( $wpdb->last_error ) {
		error_log( '[SAGA][GALAXY] Database error: ' . $wpdb->last_error );
		return array();
	}

	return array_map(
		function ( $row ) {
			return array(
				'id'          => absint( $row['id'] ),
				'name'        => sanitize_text_field( $row['name'] ),
				'type'        => sanitize_key( $row['type'] ),
				'importance'  => absint( $row['importance'] ?? 50 ),
				'slug'        => sanitize_title( $row['slug'] ),
				'description' => wp_trim_words( strip_tags( $row['description'] ?? '' ), 30 ),
				'url'         => esc_url( $row['url'] ?? '' ),
				'connections' => 0, // Will be calculated from links
			);
		},
		$results
	);
}

/**
 * Get nodes from WordPress posts (fallback)
 *
 * @param int $saga_id Saga ID
 * @return array
 */
function saga_get_nodes_from_posts( $saga_id ) {
	$args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => 1000,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => 'saga_id',
				'value'   => $saga_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		),
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'importance_score',
		'order'          => 'DESC',
	);

	$query = new WP_Query( $args );
	$nodes = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();

			$entity_type = get_post_meta( get_the_ID(), 'entity_type', true );
			$importance  = get_post_meta( get_the_ID(), 'importance_score', true );

			$nodes[] = array(
				'id'          => get_the_ID(),
				'name'        => get_the_title(),
				'type'        => $entity_type ?: 'concept',
				'importance'  => absint( $importance ?: 50 ),
				'slug'        => get_post_field( 'post_name', get_the_ID() ),
				'description' => wp_trim_words( get_the_excerpt(), 30 ),
				'url'         => get_permalink(),
				'connections' => 0,
			);
		}
		wp_reset_postdata();
	}

	return $nodes;
}

/**
 * Get galaxy links (relationships) for a saga
 *
 * @param int $saga_id Saga post ID
 * @return array Array of link objects
 */
function saga_get_galaxy_links( $saga_id ) {
	global $wpdb;

	// Try to get from cache first
	$cache_key = 'saga_galaxy_links_' . $saga_id;
	$cached    = wp_cache_get( $cache_key, 'saga' );

	if ( false !== $cached ) {
		return $cached;
	}

	$links = array();

	// Query relationships from custom table (if using plugin architecture)
	$entities_table      = $wpdb->prefix . 'saga_entities';
	$relationships_table = $wpdb->prefix . 'saga_entity_relationships';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $relationships_table ) ) === $relationships_table ) {
		// Using custom table
		$links = saga_get_links_from_custom_table( $saga_id, $entities_table, $relationships_table );
	} else {
		// Fallback to post meta
		$links = saga_get_links_from_meta( $saga_id );
	}

	// Cache for 5 minutes
	wp_cache_set( $cache_key, $links, 'saga', 300 );

	return $links;
}

/**
 * Get links from custom relationships table
 *
 * @param int    $saga_id Saga ID
 * @param string $entities_table Entities table name
 * @param string $relationships_table Relationships table name
 * @return array
 */
function saga_get_links_from_custom_table( $saga_id, $entities_table, $relationships_table ) {
	global $wpdb;

	$query = $wpdb->prepare(
		"SELECT
            r.id,
            r.source_entity_id AS source,
            r.target_entity_id AS target,
            r.relationship_type AS type,
            r.strength
        FROM {$relationships_table} r
        INNER JOIN {$entities_table} e1 ON r.source_entity_id = e1.id
        INNER JOIN {$entities_table} e2 ON r.target_entity_id = e2.id
        WHERE e1.saga_id = %d AND e2.saga_id = %d
        LIMIT 5000",
		$saga_id,
		$saga_id
	);

	$results = $wpdb->get_results( $query, ARRAY_A );

	if ( $wpdb->last_error ) {
		error_log( '[SAGA][GALAXY] Database error: ' . $wpdb->last_error );
		return array();
	}

	return array_map(
		function ( $row ) {
			return array(
				'id'       => absint( $row['id'] ),
				'source'   => absint( $row['source'] ),
				'target'   => absint( $row['target'] ),
				'type'     => sanitize_key( $row['type'] ),
				'strength' => absint( $row['strength'] ?? 50 ),
			);
		},
		$results
	);
}

/**
 * Get links from post meta (fallback)
 *
 * @param int $saga_id Saga ID
 * @return array
 */
function saga_get_links_from_meta( $saga_id ) {
	global $wpdb;

	$links = array();

	// Get all entities for this saga
	$entities_query = $wpdb->prepare(
		"SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'saga_entity'
        AND p.post_status = 'publish'
        AND pm.meta_key = 'saga_id'
        AND pm.meta_value = %d",
		$saga_id
	);

	$entity_ids = $wpdb->get_col( $entities_query );

	if ( empty( $entity_ids ) ) {
		return array();
	}

	// Get relationships from meta
	$placeholders = implode( ',', array_fill( 0, count( $entity_ids ), '%d' ) );

	$relationships_query = $wpdb->prepare(
		"SELECT
            pm.post_id AS source,
            pm.meta_value AS target,
            pm2.meta_value AS type,
            pm3.meta_value AS strength
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
            AND pm2.meta_key = 'relationship_type'
        LEFT JOIN {$wpdb->postmeta} pm3 ON pm.post_id = pm3.post_id
            AND pm3.meta_key = 'relationship_strength'
        WHERE pm.meta_key = 'related_entity'
        AND pm.post_id IN ({$placeholders})
        LIMIT 5000",
		...$entity_ids
	);

	$results = $wpdb->get_results( $relationships_query, ARRAY_A );

	$link_id = 0;
	foreach ( $results as $row ) {
		$target_id = absint( $row['target'] );

		// Validate target exists in saga
		if ( in_array( $target_id, $entity_ids ) ) {
			$links[] = array(
				'id'       => ++$link_id,
				'source'   => absint( $row['source'] ),
				'target'   => $target_id,
				'type'     => sanitize_key( $row['type'] ?: 'related' ),
				'strength' => absint( $row['strength'] ?: 50 ),
			);
		}
	}

	return $links;
}

/**
 * Calculate node connection counts
 *
 * Updates the 'connections' field in nodes based on links.
 *
 * @param array &$nodes Nodes array (passed by reference)
 * @param array $links Links array
 */
function saga_calculate_node_connections( &$nodes, $links ) {
	$connection_counts = array();

	// Count connections
	foreach ( $links as $link ) {
		$source = $link['source'];
		$target = $link['target'];

		$connection_counts[ $source ] = ( $connection_counts[ $source ] ?? 0 ) + 1;
		$connection_counts[ $target ] = ( $connection_counts[ $target ] ?? 0 ) + 1;
	}

	// Update nodes
	foreach ( $nodes as &$node ) {
		$node['connections'] = $connection_counts[ $node['id'] ] ?? 0;
	}
}

/**
 * Filter galaxy data based on user preferences
 *
 * @param array $nodes Nodes array
 * @param array $links Links array
 * @param array $filters Filter parameters
 * @return array Filtered data
 */
function saga_filter_galaxy_data( $nodes, $links, $filters = array() ) {
	// Filter by entity types
	if ( ! empty( $filters['types'] ) ) {
		$allowed_types = array_map( 'sanitize_key', $filters['types'] );
		$nodes         = array_filter(
			$nodes,
			function ( $node ) use ( $allowed_types ) {
				return in_array( $node['type'], $allowed_types );
			}
		);

		// Get remaining node IDs
		$node_ids = array_column( $nodes, 'id' );

		// Filter links to only include remaining nodes
		$links = array_filter(
			$links,
			function ( $link ) use ( $node_ids ) {
				return in_array( $link['source'], $node_ids ) && in_array( $link['target'], $node_ids );
			}
		);
	}

	// Filter by minimum importance
	if ( ! empty( $filters['min_importance'] ) ) {
		$min_importance = absint( $filters['min_importance'] );
		$nodes          = array_filter(
			$nodes,
			function ( $node ) use ( $min_importance ) {
				return $node['importance'] >= $min_importance;
			}
		);

		// Update links again
		$node_ids = array_column( $nodes, 'id' );
		$links    = array_filter(
			$links,
			function ( $link ) use ( $node_ids ) {
				return in_array( $link['source'], $node_ids ) && in_array( $link['target'], $node_ids );
			}
		);
	}

	// Filter by minimum connections (requires pre-calculation)
	if ( ! empty( $filters['min_connections'] ) ) {
		$min_connections = absint( $filters['min_connections'] );
		$nodes           = array_filter(
			$nodes,
			function ( $node ) use ( $min_connections ) {
				return $node['connections'] >= $min_connections;
			}
		);
	}

	return array(
		'nodes' => array_values( $nodes ), // Re-index
		'links' => array_values( $links ), // Re-index
	);
}

/**
 * Clear galaxy cache for a saga
 *
 * Call this when entities or relationships are updated.
 *
 * @param int $saga_id Saga ID
 */
function saga_clear_galaxy_cache( $saga_id ) {
	wp_cache_delete( 'saga_galaxy_nodes_' . $saga_id, 'saga' );
	wp_cache_delete( 'saga_galaxy_links_' . $saga_id, 'saga' );

	do_action( 'saga_galaxy_cache_cleared', $saga_id );
}

/**
 * Clear galaxy cache on entity save
 */
function saga_clear_galaxy_cache_on_save( $post_id, $post, $update ) {
	if ( $post->post_type !== 'saga_entity' ) {
		return;
	}

	$saga_id = get_post_meta( $post_id, 'saga_id', true );
	if ( $saga_id ) {
		saga_clear_galaxy_cache( $saga_id );
	}
}
add_action( 'save_post', 'saga_clear_galaxy_cache_on_save', 10, 3 );

/**
 * Export galaxy data as JSON
 *
 * Useful for debugging or external visualization tools.
 *
 * @param int  $saga_id Saga ID
 * @param bool $download Whether to force download
 */
function saga_export_galaxy_data( $saga_id, $download = false ) {
	$nodes = saga_get_galaxy_nodes( $saga_id );
	$links = saga_get_galaxy_links( $saga_id );

	saga_calculate_node_connections( $nodes, $links );

	$data = array(
		'saga_id'     => $saga_id,
		'saga_name'   => get_the_title( $saga_id ),
		'exported_at' => current_time( 'mysql' ),
		'nodes'       => $nodes,
		'links'       => $links,
		'stats'       => array(
			'nodeCount' => count( $nodes ),
			'linkCount' => count( $links ),
		),
	);

	if ( $download ) {
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="saga-galaxy-' . $saga_id . '.json"' );
		echo json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	return $data;
}
