<?php
/**
 * Saga Manager Query Functions
 *
 * Custom WP_Query helpers for entity retrieval
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
 * Query entities by saga ID
 *
 * @param int   $saga_id Saga ID from database
 * @param array $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_entities_by_saga( int $saga_id, array $args = array() ): WP_Query {
	$default_args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => '_saga_id',
				'value'   => $saga_id,
				'compare' => '=',
			),
		),
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Query entities by type
 *
 * @param string $type Entity type (character, location, etc.)
 * @param array  $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_entities_by_type( string $type, array $args = array() ): WP_Query {
	$default_args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'saga_type',
				'field'    => 'slug',
				'terms'    => $type,
			),
		),
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Query related entities (entities with relationships to given entity)
 *
 * @param int   $post_id WordPress post ID
 * @param array $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_related_entities( int $post_id, array $args = array() ): WP_Query {
	$relationships = saga_get_entity_relationships( $post_id );

	if ( empty( $relationships ) ) {
		return new WP_Query( array( 'post__in' => array( 0 ) ) );
	}

	$entity   = saga_get_entity_by_post_id( $post_id );
	$post_ids = array();

	foreach ( $relationships as $rel ) {
		// Determine which entity is the related one
		if ( $rel->source_entity_id === $entity->id && $rel->target_post_id ) {
			$post_ids[] = $rel->target_post_id;
		} elseif ( $rel->target_entity_id === $entity->id && $rel->source_post_id ) {
			$post_ids[] = $rel->source_post_id;
		}
	}

	$post_ids = array_unique( $post_ids );

	$default_args = array(
		'post_type'      => 'saga_entity',
		'post__in'       => ! empty( $post_ids ) ? $post_ids : array( 0 ),
		'posts_per_page' => -1,
		'orderby'        => 'post__in',
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Query recent entities
 *
 * @param int   $count Number of entities to retrieve
 * @param array $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_recent_entities( int $count = 5, array $args = array() ): WP_Query {
	$default_args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Query top importance entities
 *
 * @param int   $count Number of entities to retrieve
 * @param array $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_top_entities( int $count = 10, array $args = array() ): WP_Query {
	$default_args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => $count,
		'meta_key'       => '_saga_importance_score',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Query entities by importance range
 *
 * @param int   $min Minimum importance score
 * @param int   $max Maximum importance score
 * @param array $args Additional WP_Query arguments
 * @return WP_Query Query object
 */
function saga_query_entities_by_importance( int $min, int $max, array $args = array() ): WP_Query {
	$default_args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => '_saga_importance_score',
				'value'   => array( $min, $max ),
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			),
		),
	);

	$args = wp_parse_args( $args, $default_args );

	return new WP_Query( $args );
}

/**
 * Get adjacent entity (next/previous) within same saga
 *
 * @param int  $post_id Current post ID
 * @param bool $next True for next, false for previous
 * @return WP_Post|null Adjacent post or null
 */
function saga_get_adjacent_entity( int $post_id, bool $next = true ): ?WP_Post {
	$entity = saga_get_entity_by_post_id( $post_id );

	if ( ! $entity ) {
		return null;
	}

	$args = array(
		'post_type'      => 'saga_entity',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => '_saga_id',
				'value'   => $entity->saga_id,
				'compare' => '=',
			),
		),
		'orderby'        => 'title',
		'order'          => $next ? 'ASC' : 'DESC',
	);

	// Get current post title for comparison
	$current_post = get_post( $post_id );

	if ( $next ) {
		$args['meta_query'][] = array(
			'key'     => 'post_title',
			'value'   => $current_post->post_title,
			'compare' => '>',
		);
	} else {
		$args['meta_query'][] = array(
			'key'     => 'post_title',
			'value'   => $current_post->post_title,
			'compare' => '<',
		);
	}

	$query = new WP_Query( $args );

	return $query->have_posts() ? $query->posts[0] : null;
}
