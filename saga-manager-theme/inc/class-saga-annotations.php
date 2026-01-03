<?php
/**
 * Saga Annotations Manager
 *
 * Handles user annotations for saga entities including creation, retrieval,
 * updating, deletion, and export functionality.
 *
 * @package Saga_Manager_Theme
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Saga_Annotations
 *
 * Manages user annotations with support for rich text, tagging, privacy controls,
 * text highlighting, and export functionality.
 */
class Saga_Annotations {

	/**
	 * User meta key for storing annotations
	 *
	 * @var string
	 */
	const META_KEY = 'saga_annotations';

	/**
	 * Maximum annotations per user
	 *
	 * @var int
	 */
	const MAX_ANNOTATIONS = 100;

	/**
	 * Maximum tags per annotation
	 *
	 * @var int
	 */
	const MAX_TAGS = 5;

	/**
	 * Maximum annotation content length
	 *
	 * @var int
	 */
	const MAX_CONTENT_LENGTH = 5000;

	/**
	 * Initialize hooks
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_saga_save_annotation', array( __CLASS__, 'ajax_save_annotation' ) );
		add_action( 'wp_ajax_saga_delete_annotation', array( __CLASS__, 'ajax_delete_annotation' ) );
		add_action( 'wp_ajax_saga_search_annotations', array( __CLASS__, 'ajax_search_annotations' ) );
		add_action( 'wp_ajax_saga_export_annotations', array( __CLASS__, 'ajax_export_annotations' ) );
		add_action( 'wp_ajax_saga_get_annotations', array( __CLASS__, 'ajax_get_annotations' ) );
		add_action( 'wp_ajax_saga_get_user_tags', array( __CLASS__, 'ajax_get_user_tags' ) );
	}

	/**
	 * Enqueue JavaScript and CSS assets
	 */
	public static function enqueue_assets(): void {
		if ( ! is_singular( 'saga_entity' ) && ! is_page_template( 'page-templates/my-annotations.php' ) ) {
			return;
		}

		wp_enqueue_editor();

		wp_enqueue_style(
			'saga-annotations',
			get_template_directory_uri() . '/assets/css/annotations.css',
			array(),
			SAGA_THEME_VERSION
		);

		wp_enqueue_script(
			'saga-annotations',
			get_template_directory_uri() . '/assets/js/annotations.js',
			array( 'jquery', 'wp-util' ),
			SAGA_THEME_VERSION,
			true
		);

		wp_localize_script(
			'saga-annotations',
			'sagaAnnotations',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'saga_annotations_nonce' ),
				'maxAnnotations'   => self::MAX_ANNOTATIONS,
				'maxTags'          => self::MAX_TAGS,
				'maxContentLength' => self::MAX_CONTENT_LENGTH,
				'strings'          => array(
					'saveSuccess'           => __( 'Annotation saved successfully.', 'saga-manager-theme' ),
					'deleteSuccess'         => __( 'Annotation deleted successfully.', 'saga-manager-theme' ),
					'deleteConfirm'         => __( 'Are you sure you want to delete this annotation?', 'saga-manager-theme' ),
					'error'                 => __( 'An error occurred. Please try again.', 'saga-manager-theme' ),
					'maxAnnotationsReached' => __( 'You have reached the maximum number of annotations.', 'saga-manager-theme' ),
					'loginRequired'         => __( 'You must be logged in to create annotations.', 'saga-manager-theme' ),
					'contentTooLong'        => __( 'Annotation content is too long.', 'saga-manager-theme' ),
					'tooManyTags'           => __( 'Maximum of 5 tags allowed.', 'saga-manager-theme' ),
				),
			)
		);
	}

	/**
	 * Get all annotations for a user
	 *
	 * @param int $user_id User ID (defaults to current user)
	 * @return array Array of annotations
	 */
	public static function get_user_annotations( int $user_id = 0 ): array {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array();
		}

		$annotations = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $annotations ) ) {
			return array();
		}

		return $annotations;
	}

	/**
	 * Get annotations for a specific entity
	 *
	 * @param int $entity_id Entity post ID
	 * @param int $user_id User ID (defaults to current user)
	 * @return array Array of annotations for the entity
	 */
	public static function get_entity_annotations( int $entity_id, int $user_id = 0 ): array {
		$all_annotations = self::get_user_annotations( $user_id );

		return array_filter(
			$all_annotations,
			function ( $annotation ) use ( $entity_id ) {
				return isset( $annotation['entity_id'] ) && (int) $annotation['entity_id'] === $entity_id;
			}
		);
	}

	/**
	 * Get a single annotation by ID
	 *
	 * @param string $annotation_id Annotation ID
	 * @param int    $user_id User ID (defaults to current user)
	 * @return array|null Annotation data or null if not found
	 */
	public static function get_annotation( string $annotation_id, int $user_id = 0 ): ?array {
		$annotations = self::get_user_annotations( $user_id );

		foreach ( $annotations as $annotation ) {
			if ( isset( $annotation['id'] ) && $annotation['id'] === $annotation_id ) {
				return $annotation;
			}
		}

		return null;
	}

	/**
	 * Save (create or update) an annotation
	 *
	 * @param array $data Annotation data
	 * @param int   $user_id User ID (defaults to current user)
	 * @return array|WP_Error Saved annotation data or WP_Error on failure
	 */
	public static function save_annotation( array $data, int $user_id = 0 ): array|WP_Error {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to save annotations.', 'saga-manager-theme' ) );
		}

		// Validate required fields
		if ( empty( $data['entity_id'] ) || ! is_numeric( $data['entity_id'] ) ) {
			return new WP_Error( 'invalid_entity', __( 'Invalid entity ID.', 'saga-manager-theme' ) );
		}

		if ( empty( $data['content'] ) ) {
			return new WP_Error( 'empty_content', __( 'Annotation content cannot be empty.', 'saga-manager-theme' ) );
		}

		// Validate content length
		if ( strlen( $data['content'] ) > self::MAX_CONTENT_LENGTH ) {
			return new WP_Error( 'content_too_long', __( 'Annotation content is too long.', 'saga-manager-theme' ) );
		}

		// Validate tags
		$tags = isset( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : array();
		if ( count( $tags ) > self::MAX_TAGS ) {
			return new WP_Error( 'too_many_tags', __( 'Maximum of 5 tags allowed.', 'saga-manager-theme' ) );
		}

		$annotations = self::get_user_annotations( $user_id );

		// Check if updating existing annotation
		$is_update     = ! empty( $data['id'] );
		$annotation_id = $is_update ? sanitize_text_field( $data['id'] ) : 'ann_' . wp_generate_password( 12, false );

		// Check annotation limit for new annotations
		if ( ! $is_update && count( $annotations ) >= self::MAX_ANNOTATIONS ) {
			return new WP_Error( 'limit_reached', __( 'Maximum annotation limit reached.', 'saga-manager-theme' ) );
		}

		// Sanitize and prepare annotation data
		$annotation = array(
			'id'         => $annotation_id,
			'entity_id'  => (int) $data['entity_id'],
			'section'    => isset( $data['section'] ) ? sanitize_text_field( $data['section'] ) : '',
			'quote'      => isset( $data['quote'] ) ? wp_kses_post( $data['quote'] ) : '',
			'content'    => wp_kses_post( $data['content'] ),
			'tags'       => array_map( 'sanitize_text_field', $tags ),
			'visibility' => isset( $data['visibility'] ) && $data['visibility'] === 'public' ? 'public' : 'private',
			'created_at' => $is_update ? ( $data['created_at'] ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Update or add annotation
		if ( $is_update ) {
			$found = false;
			foreach ( $annotations as $key => $existing ) {
				if ( isset( $existing['id'] ) && $existing['id'] === $annotation_id ) {
					$annotations[ $key ] = $annotation;
					$found               = true;
					break;
				}
			}

			if ( ! $found ) {
				return new WP_Error( 'not_found', __( 'Annotation not found.', 'saga-manager-theme' ) );
			}
		} else {
			$annotations[] = $annotation;
		}

		// Save to user meta
		$updated = update_user_meta( $user_id, self::META_KEY, $annotations );

		if ( $updated === false ) {
			return new WP_Error( 'save_failed', __( 'Failed to save annotation.', 'saga-manager-theme' ) );
		}

		return $annotation;
	}

	/**
	 * Delete an annotation
	 *
	 * @param string $annotation_id Annotation ID
	 * @param int    $user_id User ID (defaults to current user)
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function delete_annotation( string $annotation_id, int $user_id = 0 ): bool|WP_Error {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to delete annotations.', 'saga-manager-theme' ) );
		}

		$annotations = self::get_user_annotations( $user_id );
		$found       = false;

		foreach ( $annotations as $key => $annotation ) {
			if ( isset( $annotation['id'] ) && $annotation['id'] === $annotation_id ) {
				unset( $annotations[ $key ] );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return new WP_Error( 'not_found', __( 'Annotation not found.', 'saga-manager-theme' ) );
		}

		// Re-index array
		$annotations = array_values( $annotations );

		update_user_meta( $user_id, self::META_KEY, $annotations );

		return true;
	}

	/**
	 * Search annotations
	 *
	 * @param string $query Search query
	 * @param int    $user_id User ID (defaults to current user)
	 * @return array Matching annotations
	 */
	public static function search_annotations( string $query, int $user_id = 0 ): array {
		$annotations = self::get_user_annotations( $user_id );

		if ( empty( $query ) ) {
			return $annotations;
		}

		$query = strtolower( $query );

		return array_filter(
			$annotations,
			function ( $annotation ) use ( $query ) {
				$searchable = strtolower(
					( $annotation['content'] ?? '' ) . ' ' .
					( $annotation['quote'] ?? '' ) . ' ' .
					implode( ' ', $annotation['tags'] ?? array() )
				);

				return strpos( $searchable, $query ) !== false;
			}
		);
	}

	/**
	 * Get all unique tags for a user
	 *
	 * @param int $user_id User ID (defaults to current user)
	 * @return array Array of unique tags
	 */
	public static function get_user_tags( int $user_id = 0 ): array {
		$annotations = self::get_user_annotations( $user_id );
		$tags        = array();

		foreach ( $annotations as $annotation ) {
			if ( isset( $annotation['tags'] ) && is_array( $annotation['tags'] ) ) {
				$tags = array_merge( $tags, $annotation['tags'] );
			}
		}

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Export annotations
	 *
	 * @param string $format Export format (markdown, json)
	 * @param int    $user_id User ID (defaults to current user)
	 * @return string|WP_Error Exported data or WP_Error
	 */
	public static function export_annotations( string $format = 'markdown', int $user_id = 0 ): string|WP_Error {
		$annotations = self::get_user_annotations( $user_id );

		if ( empty( $annotations ) ) {
			return new WP_Error( 'no_annotations', __( 'No annotations to export.', 'saga-manager-theme' ) );
		}

		switch ( $format ) {
			case 'json':
				return wp_json_encode( $annotations, JSON_PRETTY_PRINT );

			case 'markdown':
				return self::export_to_markdown( $annotations );

			default:
				return new WP_Error( 'invalid_format', __( 'Invalid export format.', 'saga-manager-theme' ) );
		}
	}

	/**
	 * Convert annotations to markdown format
	 *
	 * @param array $annotations Annotations to convert
	 * @return string Markdown formatted annotations
	 */
	private static function export_to_markdown( array $annotations ): string {
		$markdown  = "# My Saga Annotations\n\n";
		$markdown .= 'Exported: ' . current_time( 'Y-m-d H:i:s' ) . "\n\n";
		$markdown .= "---\n\n";

		foreach ( $annotations as $annotation ) {
			$entity_id    = $annotation['entity_id'] ?? 0;
			$entity_title = get_the_title( $entity_id );

			$markdown .= "## {$entity_title}\n\n";

			if ( ! empty( $annotation['quote'] ) ) {
				$markdown .= '> ' . wp_strip_all_tags( $annotation['quote'] ) . "\n\n";
			}

			$markdown .= wp_strip_all_tags( $annotation['content'] ) . "\n\n";

			if ( ! empty( $annotation['tags'] ) ) {
				$markdown .= '**Tags:** ' . implode( ', ', $annotation['tags'] ) . "\n\n";
			}

			$markdown .= '*Created: ' . ( $annotation['created_at'] ?? 'Unknown' ) . "*\n\n";
			$markdown .= "---\n\n";
		}

		return $markdown;
	}

	/**
	 * AJAX handler: Save annotation
	 */
	public static function ajax_save_annotation(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$data = array(
			'id'         => isset( $_POST['annotation_id'] ) ? sanitize_text_field( $_POST['annotation_id'] ) : '',
			'entity_id'  => isset( $_POST['entity_id'] ) ? absint( $_POST['entity_id'] ) : 0,
			'section'    => isset( $_POST['section'] ) ? sanitize_text_field( $_POST['section'] ) : '',
			'quote'      => isset( $_POST['quote'] ) ? wp_kses_post( $_POST['quote'] ) : '',
			'content'    => isset( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '',
			'tags'       => isset( $_POST['tags'] ) && is_array( $_POST['tags'] ) ? $_POST['tags'] : array(),
			'visibility' => isset( $_POST['visibility'] ) ? sanitize_text_field( $_POST['visibility'] ) : 'private',
		);

		$result = self::save_annotation( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'annotation' => $result,
				'message'    => __( 'Annotation saved successfully.', 'saga-manager-theme' ),
			)
		);
	}

	/**
	 * AJAX handler: Delete annotation
	 */
	public static function ajax_delete_annotation(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$annotation_id = isset( $_POST['annotation_id'] ) ? sanitize_text_field( $_POST['annotation_id'] ) : '';

		if ( empty( $annotation_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid annotation ID.', 'saga-manager-theme' ) ), 400 );
		}

		$result = self::delete_annotation( $annotation_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Annotation deleted successfully.', 'saga-manager-theme' ) ) );
	}

	/**
	 * AJAX handler: Search annotations
	 */
	public static function ajax_search_annotations(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$query       = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
		$annotations = self::search_annotations( $query );

		wp_send_json_success( array( 'annotations' => array_values( $annotations ) ) );
	}

	/**
	 * AJAX handler: Export annotations
	 */
	public static function ajax_export_annotations(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$format = isset( $_GET['format'] ) ? sanitize_text_field( $_GET['format'] ) : 'markdown';
		$result = self::export_annotations( $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'data'   => $result,
				'format' => $format,
			)
		);
	}

	/**
	 * AJAX handler: Get annotations for entity
	 */
	public static function ajax_get_annotations(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$entity_id = isset( $_GET['entity_id'] ) ? absint( $_GET['entity_id'] ) : 0;

		if ( ! $entity_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entity ID.', 'saga-manager-theme' ) ), 400 );
		}

		$annotations = self::get_entity_annotations( $entity_id );

		wp_send_json_success( array( 'annotations' => array_values( $annotations ) ) );
	}

	/**
	 * AJAX handler: Get user tags
	 */
	public static function ajax_get_user_tags(): void {
		check_ajax_referer( 'saga_annotations_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'saga-manager-theme' ) ), 401 );
		}

		$tags = self::get_user_tags();

		wp_send_json_success( array( 'tags' => $tags ) );
	}
}

// Initialize
Saga_Annotations::init();
