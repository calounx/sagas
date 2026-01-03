<?php
declare(strict_types=1);

/**
 * Saga Collections Manager
 *
 * Handles user bookmarks and collections for saga entities.
 * Supports both logged-in users (user meta) and guests (localStorage fallback).
 *
 * @package Saga_Manager_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Saga_Collections {

	/**
	 * User meta key for collections
	 */
	const META_KEY = 'saga_collections';

	/**
	 * Maximum collections per user
	 */
	const MAX_COLLECTIONS = 20;

	/**
	 * Maximum entities per collection
	 */
	const MAX_ENTITIES_PER_COLLECTION = 1000;

	/**
	 * Initialize hooks
	 */
	public function init(): void {
		add_action( 'wp_ajax_saga_add_to_collection', array( $this, 'ajax_add_to_collection' ) );
		add_action( 'wp_ajax_nopriv_saga_add_to_collection', array( $this, 'ajax_guest_response' ) );

		add_action( 'wp_ajax_saga_remove_from_collection', array( $this, 'ajax_remove_from_collection' ) );
		add_action( 'wp_ajax_nopriv_saga_remove_from_collection', array( $this, 'ajax_guest_response' ) );

		add_action( 'wp_ajax_saga_create_collection', array( $this, 'ajax_create_collection' ) );
		add_action( 'wp_ajax_nopriv_saga_create_collection', array( $this, 'ajax_guest_response' ) );

		add_action( 'wp_ajax_saga_delete_collection', array( $this, 'ajax_delete_collection' ) );
		add_action( 'wp_ajax_nopriv_saga_delete_collection', array( $this, 'ajax_guest_response' ) );

		add_action( 'wp_ajax_saga_rename_collection', array( $this, 'ajax_rename_collection' ) );
		add_action( 'wp_ajax_nopriv_saga_rename_collection', array( $this, 'ajax_guest_response' ) );

		add_action( 'wp_ajax_saga_get_collections', array( $this, 'ajax_get_collections' ) );
		add_action( 'wp_ajax_nopriv_saga_get_collections', array( $this, 'ajax_guest_response' ) );
	}

	/**
	 * Get all collections for a user
	 *
	 * @param int $user_id User ID (0 for current user)
	 * @return array Collections array
	 */
	public function get_user_collections( int $user_id = 0 ): array {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id === 0 ) {
			return array();
		}

		$collections = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $collections ) ) {
			// Initialize with default Favorites collection
			$collections = $this->initialize_default_collections( $user_id );
		}

		return $collections;
	}

	/**
	 * Initialize default collections for new users
	 *
	 * @param int $user_id User ID
	 * @return array Default collections
	 */
	private function initialize_default_collections( int $user_id ): array {
		$collections = array(
			'favorites' => array(
				'name'       => __( 'Favorites', 'saga-manager' ),
				'entity_ids' => array(),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
		);

		update_user_meta( $user_id, self::META_KEY, $collections );

		return $collections;
	}

	/**
	 * Create a new collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_name Collection name
	 * @return array|WP_Error Collection data or error
	 */
	public function create_collection( int $user_id, string $collection_name ): array|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( count( $collections ) >= self::MAX_COLLECTIONS ) {
			return new WP_Error(
				'max_collections',
				sprintf(
					__( 'Maximum %d collections allowed', 'saga-manager' ),
					self::MAX_COLLECTIONS
				)
			);
		}

		$collection_name = sanitize_text_field( $collection_name );

		if ( empty( $collection_name ) ) {
			return new WP_Error( 'invalid_name', __( 'Collection name cannot be empty', 'saga-manager' ) );
		}

		$collection_slug = sanitize_title( $collection_name );

		if ( isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'duplicate_name', __( 'A collection with this name already exists', 'saga-manager' ) );
		}

		$collections[ $collection_slug ] = array(
			'name'       => $collection_name,
			'entity_ids' => array(),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		update_user_meta( $user_id, self::META_KEY, $collections );

		return $collections[ $collection_slug ];
	}

	/**
	 * Delete a collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Collection slug
	 * @return bool|WP_Error True on success or error
	 */
	public function delete_collection( int $user_id, string $collection_slug ): bool|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'not_found', __( 'Collection not found', 'saga-manager' ) );
		}

		// Prevent deletion of favorites collection
		if ( $collection_slug === 'favorites' ) {
			return new WP_Error( 'protected_collection', __( 'Cannot delete the Favorites collection', 'saga-manager' ) );
		}

		unset( $collections[ $collection_slug ] );

		update_user_meta( $user_id, self::META_KEY, $collections );

		return true;
	}

	/**
	 * Rename a collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Current collection slug
	 * @param string $new_name        New collection name
	 * @return array|WP_Error Updated collection or error
	 */
	public function rename_collection( int $user_id, string $collection_slug, string $new_name ): array|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'not_found', __( 'Collection not found', 'saga-manager' ) );
		}

		$new_name = sanitize_text_field( $new_name );

		if ( empty( $new_name ) ) {
			return new WP_Error( 'invalid_name', __( 'Collection name cannot be empty', 'saga-manager' ) );
		}

		$new_slug = sanitize_title( $new_name );

		if ( $new_slug !== $collection_slug && isset( $collections[ $new_slug ] ) ) {
			return new WP_Error( 'duplicate_name', __( 'A collection with this name already exists', 'saga-manager' ) );
		}

		$collection_data               = $collections[ $collection_slug ];
		$collection_data['name']       = $new_name;
		$collection_data['updated_at'] = current_time( 'mysql' );

		if ( $new_slug !== $collection_slug ) {
			unset( $collections[ $collection_slug ] );
			$collections[ $new_slug ] = $collection_data;
		} else {
			$collections[ $collection_slug ] = $collection_data;
		}

		update_user_meta( $user_id, self::META_KEY, $collections );

		return $collection_data;
	}

	/**
	 * Add entity to collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Collection slug
	 * @param int    $entity_id       Entity ID
	 * @return bool|WP_Error True on success or error
	 */
	public function add_to_collection( int $user_id, string $collection_slug, int $entity_id ): bool|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		if ( $entity_id <= 0 ) {
			return new WP_Error( 'invalid_entity', __( 'Invalid entity ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'not_found', __( 'Collection not found', 'saga-manager' ) );
		}

		$entity_ids = $collections[ $collection_slug ]['entity_ids'];

		if ( in_array( $entity_id, $entity_ids, true ) ) {
			return new WP_Error( 'already_exists', __( 'Entity already in collection', 'saga-manager' ) );
		}

		if ( count( $entity_ids ) >= self::MAX_ENTITIES_PER_COLLECTION ) {
			return new WP_Error(
				'max_entities',
				sprintf(
					__( 'Maximum %d entities per collection', 'saga-manager' ),
					self::MAX_ENTITIES_PER_COLLECTION
				)
			);
		}

		$entity_ids[]                                  = $entity_id;
		$collections[ $collection_slug ]['entity_ids'] = $entity_ids;
		$collections[ $collection_slug ]['updated_at'] = current_time( 'mysql' );

		update_user_meta( $user_id, self::META_KEY, $collections );

		return true;
	}

	/**
	 * Remove entity from collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Collection slug
	 * @param int    $entity_id       Entity ID
	 * @return bool|WP_Error True on success or error
	 */
	public function remove_from_collection( int $user_id, string $collection_slug, int $entity_id ): bool|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		if ( $entity_id <= 0 ) {
			return new WP_Error( 'invalid_entity', __( 'Invalid entity ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'not_found', __( 'Collection not found', 'saga-manager' ) );
		}

		$entity_ids = $collections[ $collection_slug ]['entity_ids'];
		$key        = array_search( $entity_id, $entity_ids, true );

		if ( $key === false ) {
			return new WP_Error( 'not_in_collection', __( 'Entity not in collection', 'saga-manager' ) );
		}

		unset( $entity_ids[ $key ] );
		$collections[ $collection_slug ]['entity_ids'] = array_values( $entity_ids );
		$collections[ $collection_slug ]['updated_at'] = current_time( 'mysql' );

		update_user_meta( $user_id, self::META_KEY, $collections );

		return true;
	}

	/**
	 * Check if entity is in collection
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Collection slug
	 * @param int    $entity_id       Entity ID
	 * @return bool True if in collection
	 */
	public function is_in_collection( int $user_id, string $collection_slug, int $entity_id ): bool {
		if ( $user_id === 0 || $entity_id <= 0 ) {
			return false;
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return false;
		}

		return in_array( $entity_id, $collections[ $collection_slug ]['entity_ids'], true );
	}

	/**
	 * Export collection as JSON
	 *
	 * @param int    $user_id         User ID
	 * @param string $collection_slug Collection slug
	 * @return array|WP_Error Collection data with entity details or error
	 */
	public function export_collection( int $user_id, string $collection_slug ): array|WP_Error {
		if ( $user_id === 0 ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user ID', 'saga-manager' ) );
		}

		$collections = $this->get_user_collections( $user_id );

		if ( ! isset( $collections[ $collection_slug ] ) ) {
			return new WP_Error( 'not_found', __( 'Collection not found', 'saga-manager' ) );
		}

		$collection  = $collections[ $collection_slug ];
		$export_data = array(
			'collection_name' => $collection['name'],
			'created_at'      => $collection['created_at'],
			'updated_at'      => $collection['updated_at'],
			'entity_count'    => count( $collection['entity_ids'] ),
			'entities'        => array(),
		);

		foreach ( $collection['entity_ids'] as $entity_id ) {
			$post = get_post( $entity_id );

			if ( $post ) {
				$export_data['entities'][] = array(
					'id'    => $entity_id,
					'title' => get_the_title( $post ),
					'url'   => get_permalink( $post ),
					'type'  => get_post_meta( $entity_id, 'entity_type', true ),
				);
			}
		}

		return $export_data;
	}

	/**
	 * AJAX: Add entity to collection
	 */
	public function ajax_add_to_collection(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to use collections', 'saga-manager' ),
				),
				401
			);
		}

		$collection_slug = sanitize_key( $_POST['collection'] ?? 'favorites' );
		$entity_id       = absint( $_POST['entity_id'] ?? 0 );

		$result = $this->add_to_collection( $user_id, $collection_slug, $entity_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		$collections = $this->get_user_collections( $user_id );

		wp_send_json_success(
			array(
				'message'    => __( 'Added to collection', 'saga-manager' ),
				'collection' => $collections[ $collection_slug ] ?? null,
			)
		);
	}

	/**
	 * AJAX: Remove entity from collection
	 */
	public function ajax_remove_from_collection(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to use collections', 'saga-manager' ),
				),
				401
			);
		}

		$collection_slug = sanitize_key( $_POST['collection'] ?? 'favorites' );
		$entity_id       = absint( $_POST['entity_id'] ?? 0 );

		$result = $this->remove_from_collection( $user_id, $collection_slug, $entity_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		$collections = $this->get_user_collections( $user_id );

		wp_send_json_success(
			array(
				'message'    => __( 'Removed from collection', 'saga-manager' ),
				'collection' => $collections[ $collection_slug ] ?? null,
			)
		);
	}

	/**
	 * AJAX: Create new collection
	 */
	public function ajax_create_collection(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to create collections', 'saga-manager' ),
				),
				401
			);
		}

		$collection_name = sanitize_text_field( $_POST['name'] ?? '' );

		$result = $this->create_collection( $user_id, $collection_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Collection created', 'saga-manager' ),
				'collection' => $result,
				'slug'       => sanitize_title( $collection_name ),
			)
		);
	}

	/**
	 * AJAX: Delete collection
	 */
	public function ajax_delete_collection(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to delete collections', 'saga-manager' ),
				),
				401
			);
		}

		$collection_slug = sanitize_key( $_POST['collection'] ?? '' );

		$result = $this->delete_collection( $user_id, $collection_slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Collection deleted', 'saga-manager' ),
			)
		);
	}

	/**
	 * AJAX: Rename collection
	 */
	public function ajax_rename_collection(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to rename collections', 'saga-manager' ),
				),
				401
			);
		}

		$collection_slug = sanitize_key( $_POST['collection'] ?? '' );
		$new_name        = sanitize_text_field( $_POST['new_name'] ?? '' );

		$result = $this->rename_collection( $user_id, $collection_slug, $new_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Collection renamed', 'saga-manager' ),
				'collection' => $result,
				'new_slug'   => sanitize_title( $new_name ),
			)
		);
	}

	/**
	 * AJAX: Get all collections
	 */
	public function ajax_get_collections(): void {
		check_ajax_referer( 'saga_collections', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id === 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to view collections', 'saga-manager' ),
				),
				401
			);
		}

		$collections = $this->get_user_collections( $user_id );

		wp_send_json_success(
			array(
				'collections' => $collections,
			)
		);
	}

	/**
	 * AJAX: Response for guest users
	 */
	public function ajax_guest_response(): void {
		wp_send_json_error(
			array(
				'message'  => __( 'Please log in to use collections. Guest bookmarks are stored in your browser only.', 'saga-manager' ),
				'is_guest' => true,
			),
			401
		);
	}
}
