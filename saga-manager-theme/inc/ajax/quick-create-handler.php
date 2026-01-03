<?php
declare(strict_types=1);

namespace SagaManager\Ajax;

/**
 * Quick Create AJAX Handler
 *
 * Handles all AJAX requests for quick entity creation.
 * Separated from main class for better organization.
 *
 * @package SagaManager
 * @since 1.3.0
 */
class QuickCreateHandler {

	/**
	 * Register AJAX handlers
	 */
	public function register(): void {
		// Main create action
		add_action( 'wp_ajax_saga_quick_create', array( $this, 'handle_create' ) );

		// Template fetching
		add_action( 'wp_ajax_saga_get_entity_templates', array( $this, 'handle_get_templates' ) );

		// Duplicate checking
		add_action( 'wp_ajax_saga_check_duplicate', array( $this, 'handle_check_duplicate' ) );

		// Entity search for relationships
		add_action( 'wp_ajax_saga_search_entities', array( $this, 'handle_search_entities' ) );

		// Attribute definitions
		add_action( 'wp_ajax_saga_get_attributes', array( $this, 'handle_get_attributes' ) );
	}

	/**
	 * Handle entity creation
	 */
	public function handle_create(): void {
		// Security checks
		check_ajax_referer( 'saga_quick_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Insufficient permissions.', 'saga-manager' ),
				),
				403
			);
		}

		global $wpdb;

		// Sanitize and validate input
		$data = $this->sanitize_input( $_POST );

		$validation = $this->validate_input( $data );
		if ( is_wp_error( $validation ) ) {
			wp_send_json_error(
				array(
					'message' => $validation->get_error_message(),
					'errors'  => $validation->get_error_data(),
				),
				400
			);
		}

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create WordPress post
			$post_id = $this->create_post( $data );
			if ( is_wp_error( $post_id ) ) {
				throw new \Exception( $post_id->get_error_message() );
			}

			// Create database entity
			$entity_id = $this->create_entity( $post_id, $data );
			if ( ! $entity_id ) {
				throw new \Exception( 'Failed to create entity record' );
			}

			// Create attributes if provided
			if ( ! empty( $data['attributes'] ) ) {
				$this->create_attributes( $entity_id, $data['attributes'] );
			}

			// Create relationships if provided
			if ( ! empty( $data['relationships'] ) ) {
				$this->create_relationships( $entity_id, $data['relationships'] );
			}

			// Generate content fragment for search
			if ( ! empty( $data['description'] ) ) {
				$this->create_content_fragment( $entity_id, $data['description'] );
			}

			// Update quality metrics
			$this->update_quality_metrics( $entity_id );

			// Commit transaction
			$wpdb->query( 'COMMIT' );

			// Clear caches
			$this->clear_caches( $entity_id, $post_id );

			// Log success
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						'[SAGA][INFO] Quick create success - Entity ID: %d, Post ID: %d, Type: %s',
						$entity_id,
						$post_id,
						$data['entity_type']
					)
				);
			}

			// Return success response
			wp_send_json_success(
				array(
					'message'   => __( 'Entity created successfully!', 'saga-manager' ),
					'entity_id' => $entity_id,
					'post_id'   => $post_id,
					'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
					'view_url'  => get_permalink( $post_id ),
				)
			);

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			error_log(
				sprintf(
					'[SAGA][ERROR] Quick create failed: %s',
					$e->getMessage()
				)
			);

			wp_send_json_error(
				array(
					'message' => __( 'Failed to create entity. Please try again.', 'saga-manager' ),
				),
				500
			);
		}
	}

	/**
	 * Handle get templates request
	 */
	public function handle_get_templates(): void {
		check_ajax_referer( 'saga_quick_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		$entity_type = sanitize_key( $_POST['entity_type'] ?? '' );

		if ( empty( $entity_type ) ) {
			wp_send_json_error( array( 'message' => 'Entity type required' ), 400 );
		}

		require_once get_template_directory() . '/inc/admin/entity-templates.php';
		$templates = new \SagaManager\Admin\EntityTemplates();

		wp_send_json_success(
			array(
				'templates' => $templates->get_templates_for_type( $entity_type ),
			)
		);
	}

	/**
	 * Handle duplicate check
	 */
	public function handle_check_duplicate(): void {
		check_ajax_referer( 'saga_quick_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		global $wpdb;

		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$saga_id = absint( $_POST['saga_id'] ?? 1 );

		if ( empty( $name ) ) {
			wp_send_json_success( array( 'is_duplicate' => false ) );
		}

		$table = $wpdb->prefix . 'saga_entities';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE canonical_name = %s AND saga_id = %d",
				$name,
				$saga_id
			)
		);

		wp_send_json_success(
			array(
				'is_duplicate' => (int) $exists > 0,
			)
		);
	}

	/**
	 * Handle entity search for relationships
	 */
	public function handle_search_entities(): void {
		check_ajax_referer( 'saga_quick_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		global $wpdb;

		$query   = sanitize_text_field( $_POST['query'] ?? '' );
		$saga_id = absint( $_POST['saga_id'] ?? 1 );

		if ( empty( $query ) || strlen( $query ) < 2 ) {
			wp_send_json_success( array( 'entities' => array() ) );
		}

		$table = $wpdb->prefix . 'saga_entities';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, canonical_name, entity_type, importance_score
             FROM {$table}
             WHERE saga_id = %d
             AND canonical_name LIKE %s
             ORDER BY importance_score DESC, canonical_name ASC
             LIMIT 10",
				$saga_id,
				'%' . $wpdb->esc_like( $query ) . '%'
			)
		);

		$entities = array_map(
			function ( $entity ) {
				return array(
					'id'         => (int) $entity->id,
					'name'       => $entity->canonical_name,
					'type'       => $entity->entity_type,
					'importance' => (int) $entity->importance_score,
				);
			},
			$results
		);

		wp_send_json_success( array( 'entities' => $entities ) );
	}

	/**
	 * Handle get attributes for entity type
	 */
	public function handle_get_attributes(): void {
		check_ajax_referer( 'saga_quick_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		global $wpdb;

		$entity_type = sanitize_key( $_POST['entity_type'] ?? '' );

		if ( empty( $entity_type ) ) {
			wp_send_json_error( array( 'message' => 'Entity type required' ), 400 );
		}

		$table = $wpdb->prefix . 'saga_attribute_definitions';

		$attributes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attribute_key, display_name, data_type, is_required, validation_rule
             FROM {$table}
             WHERE entity_type = %s
             ORDER BY is_required DESC, display_name ASC",
				$entity_type
			)
		);

		wp_send_json_success( array( 'attributes' => $attributes ) );
	}

	/**
	 * Sanitize input data
	 *
	 * @param array $raw_data
	 * @return array
	 */
	private function sanitize_input( array $raw_data ): array {
		return array(
			'name'           => sanitize_text_field( $raw_data['name'] ?? '' ),
			'entity_type'    => sanitize_key( $raw_data['entity_type'] ?? 'character' ),
			'description'    => wp_kses_post( $raw_data['description'] ?? '' ),
			'importance'     => min( 100, max( 0, absint( $raw_data['importance'] ?? 50 ) ) ),
			'saga_id'        => absint( $raw_data['saga_id'] ?? 1 ),
			'status'         => in_array( $raw_data['status'] ?? 'draft', array( 'draft', 'publish' ) )
				? $raw_data['status']
				: 'draft',
			'featured_image' => absint( $raw_data['featured_image'] ?? 0 ),
			'attributes'     => is_array( $raw_data['attributes'] ?? null )
				? $raw_data['attributes']
				: array(),
			'relationships'  => is_array( $raw_data['relationships'] ?? null )
				? array_map( 'absint', $raw_data['relationships'] )
				: array(),
		);
	}

	/**
	 * Validate input data
	 *
	 * @param array $data
	 * @return true|\WP_Error
	 */
	private function validate_input( array $data ) {
		$errors = array();

		// Name is required
		if ( empty( $data['name'] ) ) {
			$errors['name'] = __( 'Name is required.', 'saga-manager' );
		} elseif ( strlen( $data['name'] ) > 255 ) {
			$errors['name'] = __( 'Name is too long (max 255 characters).', 'saga-manager' );
		}

		// Valid entity type
		$valid_types = array( 'character', 'location', 'event', 'faction', 'artifact', 'concept' );
		if ( ! in_array( $data['entity_type'], $valid_types, true ) ) {
			$errors['entity_type'] = __( 'Invalid entity type.', 'saga-manager' );
		}

		// Importance range
		if ( $data['importance'] < 0 || $data['importance'] > 100 ) {
			$errors['importance'] = __( 'Importance must be between 0 and 100.', 'saga-manager' );
		}

		// Saga exists
		global $wpdb;
		$saga_table  = $wpdb->prefix . 'saga_sagas';
		$saga_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$saga_table} WHERE id = %d",
				$data['saga_id']
			)
		);

		if ( ! (int) $saga_exists ) {
			$errors['saga_id'] = __( 'Invalid saga.', 'saga-manager' );
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_failed', __( 'Validation failed.', 'saga-manager' ), $errors );
		}

		return true;
	}

	/**
	 * Create WordPress post
	 *
	 * @param array $data
	 * @return int|\WP_Error
	 */
	private function create_post( array $data ) {
		$post_data = array(
			'post_title'   => $data['name'],
			'post_content' => $data['description'],
			'post_status'  => $data['status'],
			'post_type'    => 'saga_entity',
			'post_author'  => get_current_user_id(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( ! is_wp_error( $post_id ) ) {
			// Set featured image
			if ( $data['featured_image'] ) {
				set_post_thumbnail( $post_id, $data['featured_image'] );
			}

			// Add entity type as post meta
			update_post_meta( $post_id, '_saga_entity_type', $data['entity_type'] );
			update_post_meta( $post_id, '_saga_importance', $data['importance'] );
		}

		return $post_id;
	}

	/**
	 * Create database entity record
	 *
	 * @param int   $post_id
	 * @param array $data
	 * @return int|false
	 */
	private function create_entity( int $post_id, array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entities';

		$result = $wpdb->insert(
			$table,
			array(
				'saga_id'          => $data['saga_id'],
				'entity_type'      => $data['entity_type'],
				'canonical_name'   => $data['name'],
				'slug'             => sanitize_title( $data['name'] ),
				'importance_score' => $data['importance'],
				'wp_post_id'       => $post_id,
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Create entity attributes
	 *
	 * @param int   $entity_id
	 * @param array $attributes
	 */
	private function create_attributes( int $entity_id, array $attributes ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_attribute_values';

		foreach ( $attributes as $attr_id => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'entity_id'    => $entity_id,
					'attribute_id' => absint( $attr_id ),
					'value_string' => sanitize_text_field( $value ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Create entity relationships
	 *
	 * @param int   $entity_id
	 * @param array $relationships
	 */
	private function create_relationships( int $entity_id, array $relationships ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_entity_relationships';

		foreach ( $relationships as $target_id ) {
			// Avoid self-references
			if ( $target_id === $entity_id ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'source_entity_id'  => $entity_id,
					'target_entity_id'  => $target_id,
					'relationship_type' => 'related',
					'strength'          => 50,
					'created_at'        => current_time( 'mysql' ),
					'updated_at'        => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Create content fragment for semantic search
	 *
	 * @param int    $entity_id
	 * @param string $content
	 */
	private function create_content_fragment( int $entity_id, string $content ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'saga_content_fragments';

		// Strip HTML and get plain text
		$text = wp_strip_all_tags( $content );

		// Approximate token count (rough estimate)
		$token_count = str_word_count( $text );

		$wpdb->insert(
			$table,
			array(
				'entity_id'     => $entity_id,
				'fragment_text' => $text,
				'token_count'   => $token_count,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Update quality metrics for entity
	 *
	 * @param int $entity_id
	 */
	private function update_quality_metrics( int $entity_id ): void {
		global $wpdb;
		$metrics_table = $wpdb->prefix . 'saga_quality_metrics';
		$attr_table    = $wpdb->prefix . 'saga_attribute_values';
		$def_table     = $wpdb->prefix . 'saga_attribute_definitions';

		// Count required vs filled attributes
		$required_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$def_table} d
             JOIN {$wpdb->prefix}saga_entities e ON d.entity_type = e.entity_type
             WHERE e.id = %d AND d.is_required = 1",
				$entity_id
			)
		);

		$filled_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$attr_table} av
             JOIN {$def_table} d ON av.attribute_id = d.id
             WHERE av.entity_id = %d AND d.is_required = 1
             AND (av.value_string IS NOT NULL OR av.value_text IS NOT NULL)",
				$entity_id
			)
		);

		$completeness = $required_count > 0
			? (int) ( ( $filled_count / $required_count ) * 100 )
			: 100;

		$wpdb->insert(
			$metrics_table,
			array(
				'entity_id'          => $entity_id,
				'completeness_score' => $completeness,
				'consistency_score'  => 100, // Default for new entities
				'last_verified'      => current_time( 'mysql' ),
				'issues'             => json_encode( array() ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Clear relevant caches
	 *
	 * @param int $entity_id
	 * @param int $post_id
	 */
	private function clear_caches( int $entity_id, int $post_id ): void {
		// Clear entity cache
		wp_cache_delete( "saga_entity_{$entity_id}", 'saga' );

		// Clear post cache
		clean_post_cache( $post_id );

		// Clear any query caches that might include this entity
		wp_cache_delete( 'saga_recent_entities', 'saga' );
	}
}
