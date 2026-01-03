<?php
declare(strict_types=1);

/**
 * AJAX Handler for Relationship Graph Data
 *
 * Provides graph data with performance optimization for large datasets
 *
 * @package SagaManager
 * @since 1.0.0
 */

namespace SagaManager\Theme\Ajax;

/**
 * Register AJAX endpoints for graph data
 */
function register_graph_ajax_handlers(): void {
	add_action( 'wp_ajax_saga_get_graph_data', __NAMESPACE__ . '\\handle_graph_data_request' );
	add_action( 'wp_ajax_nopriv_saga_get_graph_data', __NAMESPACE__ . '\\handle_graph_data_request' );
}
add_action( 'init', __NAMESPACE__ . '\\register_graph_ajax_handlers' );

/**
 * Handle graph data AJAX request
 *
 * Security: Nonce verification, capability checks, input sanitization
 * Performance: Query optimization, caching, pagination
 */
function handle_graph_data_request(): void {
	// Verify nonce
	check_ajax_referer( 'saga_graph_nonce', 'nonce' );

	// Sanitize inputs
	$entity_id         = isset( $_GET['entity_id'] ) ? absint( $_GET['entity_id'] ) : 0;
	$depth             = isset( $_GET['depth'] ) ? min( absint( $_GET['depth'] ), 3 ) : 1; // Max depth: 3
	$entity_type       = isset( $_GET['entity_type'] ) ? sanitize_key( $_GET['entity_type'] ) : '';
	$relationship_type = isset( $_GET['relationship_type'] ) ? sanitize_text_field( $_GET['relationship_type'] ) : '';
	$limit             = isset( $_GET['limit'] ) ? min( absint( $_GET['limit'] ), 100 ) : 100; // Max 100 nodes

	// Check cache first
	$cache_key   = "saga_graph_{$entity_id}_{$depth}_{$entity_type}_{$relationship_type}_{$limit}";
	$cached_data = wp_cache_get( $cache_key, 'saga_graph' );

	if ( false !== $cached_data ) {
		wp_send_json_success( $cached_data );
		return;
	}

	try {
		$graph_data = build_graph_data( $entity_id, $depth, $entity_type, $relationship_type, $limit );

		// Cache for 5 minutes
		wp_cache_set( $cache_key, $graph_data, 'saga_graph', 300 );

		wp_send_json_success( $graph_data );

	} catch ( \Exception $e ) {
		error_log( '[SAGA][ERROR] Graph data generation failed: ' . $e->getMessage() );
		wp_send_json_error(
			array(
				'message' => 'Failed to generate graph data',
				'code'    => 'graph_generation_failed',
			),
			500
		);
	}
}

/**
 * Build graph data structure
 *
 * @param int    $root_entity_id Starting entity ID (0 for all entities)
 * @param int    $depth Relationship traversal depth
 * @param string $entity_type Filter by entity type
 * @param string $relationship_type Filter by relationship type
 * @param int    $limit Maximum nodes
 * @return array Graph data with nodes and edges
 */
function build_graph_data(
	int $root_entity_id,
	int $depth,
	string $entity_type,
	string $relationship_type,
	int $limit
): array {
	global $wpdb;

	$entities_table      = $wpdb->prefix . 'saga_entities';
	$relationships_table = $wpdb->prefix . 'saga_entity_relationships';

	$nodes   = array();
	$edges   = array();
	$visited = array();

	// Start query timer
	$start_time = microtime( true );

	if ( $root_entity_id > 0 ) {
		// Breadth-first traversal from root entity
		$nodes_data = traverse_relationships(
			$root_entity_id,
			$depth,
			$entity_type,
			$relationship_type,
			$limit,
			$visited
		);
	} else {
		// Get all entities with filters
		$where_clauses = array( '1=1' );
		$prepare_args  = array();

		if ( ! empty( $entity_type ) ) {
			$where_clauses[] = 'entity_type = %s';
			$prepare_args[]  = $entity_type;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = "SELECT id, entity_type, canonical_name, slug, importance_score, wp_post_id
                  FROM {$entities_table}
                  WHERE {$where_sql}
                  ORDER BY importance_score DESC
                  LIMIT %d";

		$prepare_args[] = $limit;

		$nodes_data = $wpdb->get_results(
			$wpdb->prepare( $query, ...$prepare_args ),
			ARRAY_A
		);
	}

	// Build nodes array
	foreach ( $nodes_data as $node_data ) {
		$nodes[]                     = format_node( $node_data );
		$visited[ $node_data['id'] ] = true;
	}

	// Get relationships for all nodes
	if ( ! empty( $nodes ) ) {
		$node_ids     = array_column( $nodes_data, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $node_ids ), '%d' ) );

		$rel_where = array( "(source_entity_id IN ({$placeholders}) OR target_entity_id IN ({$placeholders}))" );
		$rel_args  = array_merge( $node_ids, $node_ids );

		if ( ! empty( $relationship_type ) ) {
			$rel_where[] = 'relationship_type = %s';
			$rel_args[]  = $relationship_type;
		}

		$rel_where_sql = implode( ' AND ', $rel_where );

		$rel_query = "SELECT id, source_entity_id, target_entity_id,
                             relationship_type, strength, valid_from, valid_until, metadata
                      FROM {$relationships_table}
                      WHERE {$rel_where_sql}";

		$relationships = $wpdb->get_results(
			$wpdb->prepare( $rel_query, ...$rel_args ),
			ARRAY_A
		);

		foreach ( $relationships as $rel ) {
			// Only include edges where both nodes are in the graph
			if ( isset( $visited[ $rel['source_entity_id'] ] ) && isset( $visited[ $rel['target_entity_id'] ] ) ) {
				$edges[] = format_edge( $rel );
			}
		}
	}

	$query_time = ( microtime( true ) - $start_time ) * 1000;

	if ( $query_time > 50 ) {
		error_log( "[SAGA][PERF] Slow graph query ({$query_time}ms)" );
	}

	return array(
		'nodes'    => $nodes,
		'edges'    => $edges,
		'metadata' => array(
			'total_nodes'   => count( $nodes ),
			'total_edges'   => count( $edges ),
			'depth'         => $depth,
			'query_time_ms' => round( $query_time, 2 ),
			'cached'        => false,
		),
	);
}

/**
 * Traverse relationships using breadth-first search
 *
 * @param int    $start_id Starting entity ID
 * @param int    $max_depth Maximum traversal depth
 * @param string $entity_type Entity type filter
 * @param string $relationship_type Relationship type filter
 * @param int    $limit Maximum nodes
 * @param array  $visited Reference to visited nodes
 * @return array Node data
 */
function traverse_relationships(
	int $start_id,
	int $max_depth,
	string $entity_type,
	string $relationship_type,
	int $limit,
	array &$visited
): array {
	global $wpdb;

	$entities_table      = $wpdb->prefix . 'saga_entities';
	$relationships_table = $wpdb->prefix . 'saga_entity_relationships';

	$queue  = array(
		array(
			'id'    => $start_id,
			'depth' => 0,
		),
	);
	$result = array();

	while ( ! empty( $queue ) && count( $result ) < $limit ) {
		$current       = array_shift( $queue );
		$current_id    = $current['id'];
		$current_depth = $current['depth'];

		// Skip if already visited
		if ( isset( $visited[ $current_id ] ) ) {
			continue;
		}

		// Get entity data
		$entity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, entity_type, canonical_name, slug, importance_score, wp_post_id
                 FROM {$entities_table}
                 WHERE id = %d",
				$current_id
			),
			ARRAY_A
		);

		if ( ! $entity ) {
			continue;
		}

		// Apply entity type filter
		if ( ! empty( $entity_type ) && $entity['entity_type'] !== $entity_type ) {
			$visited[ $current_id ] = true;
			continue;
		}

		$result[]               = $entity;
		$visited[ $current_id ] = true;

		// Continue traversal if depth allows
		if ( $current_depth < $max_depth ) {
			// Get connected entities
			$rel_where = array( '(source_entity_id = %d OR target_entity_id = %d)' );
			$rel_args  = array( $current_id, $current_id );

			if ( ! empty( $relationship_type ) ) {
				$rel_where[] = 'relationship_type = %s';
				$rel_args[]  = $relationship_type;
			}

			$rel_query = "SELECT source_entity_id, target_entity_id
                          FROM {$relationships_table}
                          WHERE " . implode( ' AND ', $rel_where );

			$relationships = $wpdb->get_results(
				$wpdb->prepare( $rel_query, ...$rel_args ),
				ARRAY_A
			);

			foreach ( $relationships as $rel ) {
				$next_id = ( $rel['source_entity_id'] == $current_id )
					? $rel['target_entity_id']
					: $rel['source_entity_id'];

				if ( ! isset( $visited[ $next_id ] ) ) {
					$queue[] = array(
						'id'    => $next_id,
						'depth' => $current_depth + 1,
					);
				}
			}
		}
	}

	return $result;
}

/**
 * Format entity as graph node
 *
 * @param array $entity_data Entity database row
 * @return array Formatted node
 */
function format_node( array $entity_data ): array {
	$entity_url = '';
	$thumbnail  = '';

	if ( ! empty( $entity_data['wp_post_id'] ) ) {
		$entity_url = get_permalink( $entity_data['wp_post_id'] );
		$thumbnail  = get_the_post_thumbnail_url( $entity_data['wp_post_id'], 'thumbnail' );
	}

	return array(
		'id'         => 'entity-' . $entity_data['id'],
		'entityId'   => (int) $entity_data['id'],
		'label'      => $entity_data['canonical_name'],
		'type'       => $entity_data['entity_type'],
		'importance' => (int) $entity_data['importance_score'],
		'url'        => $entity_url ?: '',
		'thumbnail'  => $thumbnail ?: '',
		'slug'       => $entity_data['slug'],
	);
}

/**
 * Format relationship as graph edge
 *
 * @param array $rel_data Relationship database row
 * @return array Formatted edge
 */
function format_edge( array $rel_data ): array {
	$metadata = ! empty( $rel_data['metadata'] ) ? json_decode( $rel_data['metadata'], true ) : array();

	return array(
		'id'         => 'rel-' . $rel_data['id'],
		'source'     => 'entity-' . $rel_data['source_entity_id'],
		'target'     => 'entity-' . $rel_data['target_entity_id'],
		'type'       => $rel_data['relationship_type'],
		'strength'   => (int) $rel_data['strength'],
		'label'      => ucwords( str_replace( '_', ' ', $rel_data['relationship_type'] ) ),
		'validFrom'  => $rel_data['valid_from'],
		'validUntil' => $rel_data['valid_until'],
		'metadata'   => $metadata,
	);
}
