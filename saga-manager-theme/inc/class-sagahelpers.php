<?php
declare(strict_types=1);

namespace SagaTheme;

/**
 * Helper Service for Saga Manager Theme
 *
 * Provides utility methods for formatting and displaying saga data
 * All methods are type-safe and follow WordPress best practices
 *
 * @package SagaTheme
 */
class SagaHelpers {

	private SagaQueries $queries;

	/**
	 * Constructor
	 *
	 * @param SagaQueries $queries Query service instance
	 */
	public function __construct( SagaQueries $queries ) {
		$this->queries = $queries;
	}

	/**
	 * Get entity by WordPress post ID
	 *
	 * @param int $postId WordPress post ID
	 * @return object|null Entity object or null
	 */
	public function getEntityByPostId( int $postId ): ?object {
		return $this->queries->getEntityByPostId( $postId );
	}

	/**
	 * Get entity relationships with formatted data
	 *
	 * @param int    $entityId Entity ID
	 * @param string $direction Relationship direction: 'outgoing', 'incoming', 'both'
	 * @return array Array of formatted relationship objects
	 */
	public function getEntityRelationships( int $entityId, string $direction = 'both' ): array {
		return $this->queries->getRelatedEntities( $entityId, $direction );
	}

	/**
	 * Get entity timeline events (placeholder for future implementation)
	 *
	 * @param int $entityId Entity ID
	 * @return array Array of timeline event objects
	 */
	public function getEntityTimeline( int $entityId ): array {
		// TODO: Implement timeline query once timeline events are available
		return array();
	}

	/**
	 * Format importance score for display
	 *
	 * @param int $score Importance score (0-100)
	 * @return string Formatted score with label
	 */
	public function formatImportanceScore( int $score ): string {
		$label = match ( true ) {
			$score >= 90 => 'Critical',
			$score >= 70 => 'Major',
			$score >= 50 => 'Important',
			$score >= 30 => 'Minor',
			default => 'Trivial',
		};

		return sprintf( '%s (%d/100)', $label, $score );
	}

	/**
	 * Format entity type for display
	 *
	 * @param string $type Entity type
	 * @return string Formatted entity type
	 */
	public function formatEntityType( string $type ): string {
		return ucfirst( $type );
	}

	/**
	 * Get entity type badge HTML
	 *
	 * @param string $type Entity type
	 * @return string HTML badge element
	 */
	public function getEntityTypeBadge( string $type ): string {
		$sanitizedType = sanitize_key( $type );
		$displayType   = $this->formatEntityType( $type );

		return sprintf(
			'<span class="saga-badge saga-badge--%s">%s</span>',
			esc_attr( $sanitizedType ),
			esc_html( $displayType )
		);
	}

	/**
	 * Get relationship strength badge HTML
	 *
	 * @param int $strength Relationship strength (0-100)
	 * @return string HTML badge element
	 */
	public function getRelationshipStrengthBadge( int $strength ): string {
		$class = match ( true ) {
			$strength >= 70 => 'high',
			$strength >= 31 => 'medium',
			default => 'low',
		};

		$label = match ( true ) {
			$strength >= 70 => 'Strong',
			$strength >= 31 => 'Moderate',
			default => 'Weak',
		};

		return sprintf(
			'<span class="saga-relationships__strength saga-relationships__strength--%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Format relationship type for display
	 *
	 * @param string $type Relationship type (e.g., "parent_of", "allied_with")
	 * @return string Formatted relationship type
	 */
	public function formatRelationshipType( string $type ): string {
		// Convert snake_case to Title Case
		$formatted = str_replace( '_', ' ', $type );
		return ucwords( $formatted );
	}

	/**
	 * Get importance score progress bar HTML
	 *
	 * @param int $score Importance score (0-100)
	 * @return string HTML progress bar element
	 */
	public function getImportanceScoreBar( int $score ): string {
		$score = max( 0, min( 100, $score ) ); // Clamp to 0-100

		return sprintf(
			'<div class="saga-importance-score">
                <div class="saga-importance-score__bar">
                    <div class="saga-importance-score__fill" style="width: %d%%"></div>
                </div>
                <span class="saga-importance-score__value">%d</span>
            </div>',
			$score,
			$score
		);
	}

	/**
	 * Get entity permalink
	 *
	 * @param object $entity Entity object with wp_post_id
	 * @return string|null Post permalink or null
	 */
	public function getEntityPermalink( object $entity ): ?string {
		if ( ! isset( $entity->wp_post_id ) || $entity->wp_post_id === null ) {
			return null;
		}

		$permalink = get_permalink( (int) $entity->wp_post_id );

		return $permalink !== false ? $permalink : null;
	}

	/**
	 * Get entity thumbnail HTML
	 *
	 * @param object $entity Entity object with wp_post_id
	 * @param string $size Thumbnail size (default: 'medium')
	 * @return string HTML img element or empty string
	 */
	public function getEntityThumbnail( object $entity, string $size = 'medium' ): string {
		if ( ! isset( $entity->wp_post_id ) || $entity->wp_post_id === null ) {
			return '';
		}

		$thumbnail = get_the_post_thumbnail( (int) $entity->wp_post_id, $size );

		return $thumbnail !== '' ? $thumbnail : '';
	}

	/**
	 * Group relationships by type
	 *
	 * @param array $relationships Array of relationship objects
	 * @return array Grouped relationships [type => [relationships]]
	 */
	public function groupRelationshipsByType( array $relationships ): array {
		$grouped = array();

		foreach ( $relationships as $rel ) {
			$type = $rel->relationship_type ?? 'unknown';

			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = array();
			}

			$grouped[ $type ][] = $rel;
		}

		return $grouped;
	}

	/**
	 * Get attribute value display
	 *
	 * @param object $attribute Attribute object with data_type and value
	 * @return string Formatted attribute value
	 */
	public function formatAttributeValue( object $attribute ): string {
		if ( ! isset( $attribute->value ) ) {
			return '';
		}

		return match ( $attribute->data_type ) {
			'bool' => $attribute->value ? 'Yes' : 'No',
			'date' => $this->formatDate( $attribute->value ),
			'json' => $this->formatJson( $attribute->value ),
			'int', 'float' => number_format( (float) $attribute->value ),
			default => (string) $attribute->value,
		};
	}

	/**
	 * Format date for display
	 *
	 * @param string $date Date string
	 * @return string Formatted date
	 */
	private function formatDate( string $date ): string {
		$timestamp = strtotime( $date );

		if ( $timestamp === false ) {
			return $date;
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Format JSON for display
	 *
	 * @param mixed $json JSON value (object or string)
	 * @return string Formatted JSON string
	 */
	private function formatJson( mixed $json ): string {
		if ( is_object( $json ) || is_array( $json ) ) {
			return wp_json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		}

		return (string) $json;
	}

	/**
	 * Check if Saga Manager plugin is active
	 *
	 * @return bool True if plugin is active
	 */
	public function isSagaManagerActive(): bool {
		return class_exists( 'SagaManager\Infrastructure\WordPress\Plugin' );
	}

	/**
	 * Get entity excerpt
	 *
	 * @param object $entity Entity object with wp_post_id
	 * @param int    $length Excerpt length in words
	 * @return string Entity excerpt
	 */
	public function getEntityExcerpt( object $entity, int $length = 30 ): string {
		if ( ! isset( $entity->wp_post_id ) || $entity->wp_post_id === null ) {
			return '';
		}

		$post = get_post( (int) $entity->wp_post_id );

		if ( ! $post ) {
			return '';
		}

		// Use post excerpt if available
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_trim_words( $post->post_excerpt, $length, '...' );
		}

		// Fall back to content
		$content = strip_shortcodes( $post->post_content );
		$content = wp_strip_all_tags( $content );

		return wp_trim_words( $content, $length, '...' );
	}

	/**
	 * Get entity type icon (placeholder for future icon system)
	 *
	 * @param string $type Entity type
	 * @return string Icon HTML or empty string
	 */
	public function getEntityTypeIcon( string $type ): string {
		// Placeholder for icon system (e.g., dashicons, Font Awesome)
		$icons = array(
			'character' => 'dashicons-admin-users',
			'location'  => 'dashicons-location',
			'event'     => 'dashicons-calendar',
			'faction'   => 'dashicons-groups',
			'artifact'  => 'dashicons-star-filled',
			'concept'   => 'dashicons-lightbulb',
		);

		$iconClass = $icons[ $type ] ?? 'dashicons-admin-generic';

		return sprintf(
			'<span class="dashicons %s" aria-hidden="true"></span>',
			esc_attr( $iconClass )
		);
	}
}
