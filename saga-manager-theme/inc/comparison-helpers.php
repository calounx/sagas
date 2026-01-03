<?php
/**
 * Entity Comparison Helper Functions
 *
 * @package Saga_Manager_Theme
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get entities for comparison by IDs or slugs
 *
 * @param array $identifiers Array of entity IDs or slugs
 * @return array Array of WP_Post objects
 */
function saga_get_comparison_entities( array $identifiers ): array {
	if ( empty( $identifiers ) ) {
		return array();
	}

	$entities = array();

	foreach ( $identifiers as $identifier ) {
		$entity = null;

		// Try as numeric ID first
		if ( is_numeric( $identifier ) ) {
			$post = get_post( (int) $identifier );
			if ( $post && $post->post_type === 'saga_entity' ) {
				$entity = $post;
			}
		}

		// Try as slug if not found
		if ( ! $entity ) {
			$query = new WP_Query(
				array(
					'post_type'      => 'saga_entity',
					'name'           => sanitize_title( $identifier ),
					'posts_per_page' => 1,
					'post_status'    => 'publish',
				)
			);

			if ( $query->have_posts() ) {
				$entity = $query->posts[0];
			}
		}

		if ( $entity ) {
			$entities[] = $entity;
		}
	}

	return array_slice( $entities, 0, 4 ); // Max 4 entities
}

/**
 * Get all attributes for multiple entities, aligned by attribute key
 *
 * @param array $entities Array of WP_Post objects
 * @return array Aligned attribute data
 */
function saga_align_entity_attributes( array $entities ): array {
	if ( empty( $entities ) ) {
		return array(
			'attributes' => array(),
			'entities'   => array(),
		);
	}

	$all_attributes = array();
	$entity_data    = array();

	// Collect all unique attributes across entities
	foreach ( $entities as $entity ) {
		$entity_id   = $entity->ID;
		$entity_type = get_post_meta( $entity_id, 'entity_type', true );

		// Get custom fields
		$meta_fields = get_post_meta( $entity_id );

		$entity_attrs = array(
			'id'         => $entity_id,
			'title'      => $entity->post_title,
			'slug'       => $entity->post_name,
			'type'       => $entity_type,
			'thumbnail'  => get_the_post_thumbnail_url( $entity_id, 'thumbnail' ),
			'permalink'  => get_permalink( $entity_id ),
			'attributes' => array(),
		);

		// Process meta fields
		foreach ( $meta_fields as $key => $values ) {
			// Skip internal WordPress meta
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$value = is_array( $values ) ? $values[0] : $values;

			// Attempt to unserialize if needed
			if ( is_serialized( $value ) ) {
				$value = maybe_unserialize( $value );
			}

			$entity_attrs['attributes'][ $key ] = $value;

			// Track all unique attribute keys
			if ( ! isset( $all_attributes[ $key ] ) ) {
				$all_attributes[ $key ] = array(
					'key'      => $key,
					'label'    => saga_format_attribute_label( $key ),
					'priority' => saga_get_attribute_priority( $key, $entity_type ),
					'type'     => saga_detect_attribute_type( $value ),
				);
			}
		}

		$entity_data[] = $entity_attrs;
	}

	// Sort attributes by priority (common/important attributes first)
	uasort(
		$all_attributes,
		function ( $a, $b ) {
			return $b['priority'] <=> $a['priority'];
		}
	);

	return array(
		'attributes' => array_values( $all_attributes ),
		'entities'   => $entity_data,
	);
}

/**
 * Get priority score for attribute (higher = more important)
 *
 * @param string $key Attribute key
 * @param string $entity_type Entity type
 * @return int Priority score (0-100)
 */
function saga_get_attribute_priority( string $key, string $entity_type ): int {
	// Core attributes (highest priority)
	$core_attributes = array(
		'entity_type' => 100,
		'species'     => 95,
		'homeworld'   => 90,
		'birth_year'  => 85,
		'affiliation' => 80,
		'faction'     => 80,
		'location'    => 75,
		'planet'      => 75,
		'date'        => 70,
		'description' => 65,
	);

	if ( isset( $core_attributes[ $key ] ) ) {
		return $core_attributes[ $key ];
	}

	// Type-specific attributes
	$type_specific = array(
		'character' => array( 'species', 'homeworld', 'birth_year', 'gender', 'height', 'mass' ),
		'location'  => array( 'planet', 'terrain', 'climate', 'population', 'government' ),
		'event'     => array( 'date', 'location', 'participants', 'outcome' ),
		'faction'   => array( 'type', 'leader', 'headquarters', 'ideology' ),
	);

	if ( isset( $type_specific[ $entity_type ] ) && in_array( $key, $type_specific[ $entity_type ] ) ) {
		return 60;
	}

	// Default priority
	return 30;
}

/**
 * Format attribute key as human-readable label
 *
 * @param string $key Attribute key
 * @return string Formatted label
 */
function saga_format_attribute_label( string $key ): string {
	// Custom labels
	$labels = array(
		'entity_type' => 'Type',
		'birth_year'  => 'Born',
		'homeworld'   => 'Homeworld',
	);

	if ( isset( $labels[ $key ] ) ) {
		return $labels[ $key ];
	}

	// Convert snake_case to Title Case
	return ucwords( str_replace( '_', ' ', $key ) );
}

/**
 * Detect attribute value type
 *
 * @param mixed $value Attribute value
 * @return string Type (string, number, date, array, boolean)
 */
function saga_detect_attribute_type( $value ): string {
	if ( is_array( $value ) ) {
		return 'array';
	}

	if ( is_bool( $value ) ) {
		return 'boolean';
	}

	if ( is_numeric( $value ) ) {
		return 'number';
	}

	// Check if it's a date
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $value ) ) {
		return 'date';
	}

	return 'string';
}

/**
 * Check if attribute values differ across entities
 *
 * @param array $values Array of values from different entities
 * @return bool True if values differ
 */
function saga_has_attribute_differences( array $values ): bool {
	// Remove null/empty values for comparison
	$filtered = array_filter(
		$values,
		function ( $v ) {
			return $v !== null && $v !== '' && $v !== 'N/A';
		}
	);

	if ( count( $filtered ) <= 1 ) {
		return false;
	}

	// Normalize values for comparison
	$normalized = array_map(
		function ( $v ) {
			if ( is_array( $v ) ) {
				return json_encode( $v );
			}
			return (string) $v;
		},
		$filtered
	);

	return count( array_unique( $normalized ) ) > 1;
}

/**
 * Format attribute value for display
 *
 * @param mixed  $value Attribute value
 * @param string $type Attribute type
 * @return string Formatted value
 */
function saga_format_attribute_value( $value, string $type = 'string' ): string {
	if ( $value === null || $value === '' ) {
		return '<span class="comparison-na">N/A</span>';
	}

	if ( is_array( $value ) ) {
		if ( empty( $value ) ) {
			return '<span class="comparison-na">N/A</span>';
		}
		return '<ul class="comparison-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $value ) ) . '</li></ul>';
	}

	if ( $type === 'boolean' ) {
		return $value ? '<span class="comparison-yes">Yes</span>' : '<span class="comparison-no">No</span>';
	}

	if ( $type === 'date' ) {
		return date_i18n( get_option( 'date_format' ), strtotime( (string) $value ) );
	}

	return esc_html( (string) $value );
}

/**
 * Generate shareable comparison URL
 *
 * @param array $entity_ids Array of entity IDs
 * @return string Comparison URL
 */
function saga_get_comparison_url( array $entity_ids ): string {
	$base_url       = home_url( '/compare/' );
	$entities_param = implode( ',', array_map( 'intval', $entity_ids ) );

	return add_query_arg( 'entities', $entities_param, $base_url );
}

/**
 * Get comparison data for JSON export
 *
 * @param array $entities Array of WP_Post objects
 * @return array Comparison data
 */
function saga_get_comparison_export_data( array $entities ): array {
	$aligned = saga_align_entity_attributes( $entities );

	$export = array(
		'timestamp'  => current_time( 'mysql' ),
		'entities'   => array(),
		'attributes' => array(),
	);

	foreach ( $aligned['entities'] as $entity ) {
		$export['entities'][] = array(
			'id'        => $entity['id'],
			'title'     => $entity['title'],
			'type'      => $entity['type'],
			'permalink' => $entity['permalink'],
		);
	}

	foreach ( $aligned['attributes'] as $attr ) {
		$row = array(
			'attribute' => $attr['label'],
			'values'    => array(),
		);

		foreach ( $aligned['entities'] as $entity ) {
			$value           = $entity['attributes'][ $attr['key'] ] ?? null;
			$row['values'][] = $value;
		}

		$export['attributes'][] = $row;
	}

	return $export;
}

/**
 * Enqueue comparison assets
 */
function saga_enqueue_comparison_assets(): void {
	if ( ! is_page_template( 'page-templates/compare-entities.php' ) ) {
		return;
	}

	wp_enqueue_style(
		'saga-comparison',
		get_template_directory_uri() . '/assets/css/entity-comparison.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);

	wp_enqueue_script(
		'saga-comparison',
		get_template_directory_uri() . '/assets/js/entity-comparison.js',
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ),
		true
	);

	wp_localize_script(
		'saga-comparison',
		'sagaComparison',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'saga_comparison' ),
			'maxEntities' => 4,
			'i18n'        => array(
				'addEntity'        => __( 'Add Entity', 'saga-manager-theme' ),
				'removeEntity'     => __( 'Remove', 'saga-manager-theme' ),
				'exportPdf'        => __( 'Export as PDF', 'saga-manager-theme' ),
				'shareUrl'         => __( 'Share URL', 'saga-manager-theme' ),
				'copySuccess'      => __( 'URL copied to clipboard!', 'saga-manager-theme' ),
				'maxEntitiesError' => __( 'Maximum 4 entities allowed', 'saga-manager-theme' ),
				'noResults'        => __( 'No entities found', 'saga-manager-theme' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'saga_enqueue_comparison_assets' );
