<?php
/**
 * Template Loader for Entity Type-Specific Templates
 *
 * Handles dynamic template selection based on entity type taxonomy
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SagaManager\Theme;

/**
 * Load entity type-specific template
 *
 * @param string $template Default template path
 * @return string Modified template path
 */
function saga_get_entity_template( string $template ): string {
	if ( ! is_singular( 'saga_entity' ) ) {
		return $template;
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return $template;
	}

	// Get entity type from meta or taxonomy
	$entity_type = saga_get_entity_type( $post_id );

	if ( ! $entity_type ) {
		return $template;
	}

	// Look for type-specific template
	$custom_template = locate_template( "single-saga_entity-{$entity_type}.php" );

	if ( $custom_template ) {
		return $custom_template;
	}

	return $template;
}
add_filter( 'template_include', __NAMESPACE__ . '\saga_get_entity_template', 99 );

/**
 * Get entity type from post meta or taxonomy
 *
 * @param int $post_id Post ID
 * @return string|null Entity type or null if not found
 */
function saga_get_entity_type( int $post_id ): ?string {
	// Try meta field first (direct from database)
	$entity_type = get_post_meta( $post_id, '_saga_entity_type', true );

	if ( ! empty( $entity_type ) ) {
		return sanitize_key( $entity_type );
	}

	// Fallback to taxonomy
	$terms = get_the_terms( $post_id, 'saga_type' );

	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$term = reset( $terms );
		return sanitize_key( $term->slug );
	}

	return null;
}

/**
 * Get entity type display name
 *
 * @param string $entity_type Entity type slug
 * @return string Display name
 */
function saga_get_entity_type_label( string $entity_type ): string {
	$labels = array(
		'character' => __( 'Character', 'saga-manager' ),
		'location'  => __( 'Location', 'saga-manager' ),
		'event'     => __( 'Event', 'saga-manager' ),
		'faction'   => __( 'Faction', 'saga-manager' ),
		'artifact'  => __( 'Artifact', 'saga-manager' ),
		'concept'   => __( 'Concept', 'saga-manager' ),
	);

	return $labels[ $entity_type ] ?? ucfirst( $entity_type );
}

/**
 * Get Schema.org type for entity
 *
 * @param string $entity_type Entity type slug
 * @return string Schema.org type
 */
function saga_get_schema_type( string $entity_type ): string {
	$schema_types = array(
		'character' => 'Person',
		'location'  => 'Place',
		'event'     => 'Event',
		'faction'   => 'Organization',
		'artifact'  => 'Thing',
		'concept'   => 'Thing',
	);

	return $schema_types[ $entity_type ] ?? 'Thing';
}

/**
 * Output Schema.org structured data for entity
 *
 * @param int    $post_id Post ID
 * @param string $entity_type Entity type
 * @return void
 */
function saga_output_schema_markup( int $post_id, string $entity_type ): void {
	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => saga_get_schema_type( $entity_type ),
		'name'     => get_the_title( $post_id ),
		'url'      => get_permalink( $post_id ),
	);

	// Add description if available
	$excerpt = get_the_excerpt( $post_id );
	if ( ! empty( $excerpt ) ) {
		$schema['description'] = $excerpt;
	}

	// Add image if available
	if ( has_post_thumbnail( $post_id ) ) {
		$image_url       = get_the_post_thumbnail_url( $post_id, 'large' );
		$schema['image'] = $image_url;
	}

	// Type-specific additions
	switch ( $entity_type ) {
		case 'character':
			$aliases = get_post_meta( $post_id, '_saga_character_aliases', true );
			if ( ! empty( $aliases ) ) {
				$schema['alternateName'] = $aliases;
			}
			$affiliation = get_post_meta( $post_id, '_saga_character_affiliation', true );
			if ( ! empty( $affiliation ) ) {
				$schema['affiliation'] = $affiliation;
			}
			break;

		case 'location':
			$coordinates = get_post_meta( $post_id, '_saga_location_coordinates', true );
			if ( ! empty( $coordinates ) ) {
				$schema['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $coordinates['lat'] ?? null,
					'longitude' => $coordinates['lng'] ?? null,
				);
			}
			break;

		case 'event':
			$event_date = get_post_meta( $post_id, '_saga_event_date', true );
			if ( ! empty( $event_date ) ) {
				$schema['startDate'] = $event_date;
			}
			$location_id = get_post_meta( $post_id, '_saga_event_location', true );
			if ( ! empty( $location_id ) ) {
				$schema['location'] = array(
					'@type' => 'Place',
					'name'  => get_the_title( $location_id ),
					'url'   => get_permalink( $location_id ),
				);
			}
			break;

		case 'faction':
			$leader = get_post_meta( $post_id, '_saga_faction_leader', true );
			if ( ! empty( $leader ) ) {
				$schema['leader'] = $leader;
			}
			break;
	}

	// Output JSON-LD
	echo '<script type="application/ld+json">';
	echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	echo '</script>' . "\n";
}

/**
 * Add Open Graph meta tags
 *
 * @param int    $post_id Post ID
 * @param string $entity_type Entity type
 * @return void
 */
function saga_output_og_tags( int $post_id, string $entity_type ): void {
	$title   = get_the_title( $post_id );
	$excerpt = get_the_excerpt( $post_id );
	$url     = get_permalink( $post_id );

	echo '<meta property="og:type" content="article" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";

	if ( ! empty( $excerpt ) ) {
		echo '<meta property="og:description" content="' . esc_attr( $excerpt ) . '" />' . "\n";
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$image_url = get_the_post_thumbnail_url( $post_id, 'large' );
		echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
	}

	// Twitter Card
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
}

/**
 * Get related entities by relationship type
 *
 * @param int    $post_id Post ID
 * @param string $relationship_type Relationship type (e.g., 'ally', 'enemy', 'member')
 * @param int    $limit Number of results to return
 * @return array Array of post objects
 */
function saga_get_related_entities( int $post_id, string $relationship_type = '', int $limit = 10 ): array {
	global $wpdb;

	$table = $wpdb->prefix . 'saga_entity_relationships';

	// Check if table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
		return array();
	}

	$where = $wpdb->prepare( 'source_entity_id = %d', $post_id );

	if ( ! empty( $relationship_type ) ) {
		$where .= $wpdb->prepare( ' AND relationship_type = %s', $relationship_type );
	}

	$query = $wpdb->prepare(
		"SELECT target_entity_id, relationship_type, strength
         FROM {$table}
         WHERE {$where}
         ORDER BY strength DESC
         LIMIT %d",
		$limit
	);

	$relationships = $wpdb->get_results( $query );

	if ( ! $relationships ) {
		return array();
	}

	$related_posts = array();

	foreach ( $relationships as $rel ) {
		$post = get_post( $rel->target_entity_id );
		if ( $post && $post->post_status === 'publish' ) {
			$post->relationship_type     = $rel->relationship_type;
			$post->relationship_strength = $rel->strength;
			$related_posts[]             = $post;
		}
	}

	return $related_posts;
}

/**
 * Get entity timeline events
 *
 * @param int $post_id Post ID
 * @param int $limit Number of events to return
 * @return array Array of timeline events
 */
function saga_get_entity_timeline( int $post_id, int $limit = 20 ): array {
	global $wpdb;

	$table = $wpdb->prefix . 'saga_timeline_events';

	// Check if table exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
		return array();
	}

	$query = $wpdb->prepare(
		"SELECT * FROM {$table}
         WHERE JSON_CONTAINS(participants, %s, '$')
         OR event_entity_id = %d
         ORDER BY normalized_timestamp ASC
         LIMIT %d",
		json_encode( $post_id ),
		$post_id,
		$limit
	);

	$events = $wpdb->get_results( $query );

	return $events ?: array();
}

/**
 * Get entity attributes from EAV system
 *
 * @param int $entity_id Entity ID
 * @return array Associative array of attributes
 */
function saga_get_entity_attributes( int $entity_id ): array {
	global $wpdb;

	$values_table = $wpdb->prefix . 'saga_attribute_values';
	$defs_table   = $wpdb->prefix . 'saga_attribute_definitions';

	// Check if tables exist
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$values_table}'" ) !== $values_table ) {
		return array();
	}

	$query = $wpdb->prepare(
		"SELECT
            ad.attribute_key,
            ad.display_name,
            ad.data_type,
            av.value_string,
            av.value_int,
            av.value_float,
            av.value_bool,
            av.value_date,
            av.value_text,
            av.value_json
         FROM {$values_table} av
         JOIN {$defs_table} ad ON av.attribute_id = ad.id
         WHERE av.entity_id = %d",
		$entity_id
	);

	$rows = $wpdb->get_results( $query );

	if ( ! $rows ) {
		return array();
	}

	$attributes = array();

	foreach ( $rows as $row ) {
		// Get value based on data type
		$value = match ( $row->data_type ) {
			'string' => $row->value_string,
			'int' => $row->value_int,
			'float' => $row->value_float,
			'bool' => $row->value_bool,
			'date' => $row->value_date,
			'text' => $row->value_text,
			'json' => json_decode( $row->value_json, true ),
			default => null,
		};

		$attributes[ $row->attribute_key ] = array(
			'label' => $row->display_name,
			'value' => $value,
			'type'  => $row->data_type,
		);
	}

	return $attributes;
}
