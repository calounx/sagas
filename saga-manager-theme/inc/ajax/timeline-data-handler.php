<?php
/**
 * Timeline AJAX Data Handler
 * Handles AJAX requests for timeline event data
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Ajax;

class TimelineDataHandler {

	/**
	 * Register AJAX handlers
	 */
	public static function register(): void {
		// Public and private AJAX actions
		add_action( 'wp_ajax_get_timeline_events', array( self::class, 'getTimelineEvents' ) );
		add_action( 'wp_ajax_nopriv_get_timeline_events', array( self::class, 'getTimelineEvents' ) );

		add_action( 'wp_ajax_get_timeline_event_details', array( self::class, 'getEventDetails' ) );
		add_action( 'wp_ajax_nopriv_get_timeline_event_details', array( self::class, 'getEventDetails' ) );
	}

	/**
	 * Get timeline events for a saga
	 */
	public static function getTimelineEvents(): void {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'saga_timeline_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
			return;
		}

		// Get saga ID
		$saga_id = isset( $_GET['saga_id'] ) ? absint( $_GET['saga_id'] ) : 0;

		if ( ! $saga_id ) {
			wp_send_json_error( array( 'message' => 'Saga ID is required' ), 400 );
			return;
		}

		global $wpdb;

		// Get saga calendar configuration
		$saga_table = $wpdb->prefix . 'saga_sagas';
		$saga       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, calendar_type, calendar_config FROM {$saga_table} WHERE id = %d",
				$saga_id
			)
		);

		if ( ! $saga ) {
			wp_send_json_error( array( 'message' => 'Saga not found' ), 404 );
			return;
		}

		// Get timeline events
		$events_table   = $wpdb->prefix . 'saga_timeline_events';
		$entities_table = $wpdb->prefix . 'saga_entities';

		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
                te.id,
                te.event_entity_id,
                te.canon_date,
                te.normalized_timestamp,
                te.title,
                te.description,
                te.participants,
                te.locations,
                e.entity_type,
                e.canonical_name as entity_name,
                e.importance_score
            FROM {$events_table} te
            LEFT JOIN {$entities_table} e ON te.event_entity_id = e.id
            WHERE te.saga_id = %d
            ORDER BY te.normalized_timestamp ASC",
				$saga_id
			),
			ARRAY_A
		);

		if ( $events === null ) {
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ), 500 );
			return;
		}

		// Process events
		$processed_events = array_map(
			function ( $event ) use ( $wpdb ) {
				// Decode JSON fields
				$event['participants'] = json_decode( $event['participants'] ?? '[]', true );
				$event['locations']    = json_decode( $event['locations'] ?? '[]', true );

				// Convert to frontend format
				return array(
					'id'              => (int) $event['id'],
					'title'           => $event['title'],
					'description'     => $event['description'],
					'timestamp'       => (float) $event['normalized_timestamp'],
					'canonDate'       => $event['canon_date'],
					'entityType'      => $event['entity_type'] ?? 'event',
					'entityName'      => $event['entity_name'],
					'importanceScore' => (int) ( $event['importance_score'] ?? 50 ),
					'participants'    => $event['participants'],
					'locations'       => $event['locations'],
					'track'           => self::assignTrack( $event ), // Multi-track support
					'relatedEvents'   => self::getRelatedEvents( (int) $event['event_entity_id'], $wpdb ),
				);
			},
			$events
		);

		wp_send_json_success(
			array(
				'events' => $processed_events,
				'saga'   => array(
					'id'             => (int) $saga->id,
					'name'           => $saga->name,
					'calendarType'   => $saga->calendar_type,
					'calendarConfig' => json_decode( $saga->calendar_config, true ),
				),
				'stats'  => array(
					'totalEvents' => count( $processed_events ),
					'timeRange'   => self::calculateTimeRange( $processed_events ),
				),
			)
		);
	}

	/**
	 * Get detailed event information
	 */
	public static function getEventDetails(): void {
		// Verify nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'saga_timeline_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
			return;
		}

		$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => 'Event ID is required' ), 400 );
			return;
		}

		global $wpdb;
		$events_table   = $wpdb->prefix . 'saga_timeline_events';
		$entities_table = $wpdb->prefix . 'saga_entities';

		$event = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
                te.*,
                e.entity_type,
                e.canonical_name as entity_name,
                e.importance_score,
                e.wp_post_id
            FROM {$events_table} te
            LEFT JOIN {$entities_table} e ON te.event_entity_id = e.id
            WHERE te.id = %d",
				$event_id
			),
			ARRAY_A
		);

		if ( ! $event ) {
			wp_send_json_error( array( 'message' => 'Event not found' ), 404 );
			return;
		}

		// Get participants details
		$participant_ids = json_decode( $event['participants'] ?? '[]', true );
		$participants    = array();

		if ( ! empty( $participant_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $participant_ids ), '%d' ) );

			$participants = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, canonical_name, entity_type, importance_score
                FROM {$entities_table}
                WHERE id IN ({$placeholders})",
					...$participant_ids
				),
				ARRAY_A
			);
		}

		// Get locations details
		$location_ids = json_decode( $event['locations'] ?? '[]', true );
		$locations    = array();

		if ( ! empty( $location_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $location_ids ), '%d' ) );

			$locations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, canonical_name, entity_type
                FROM {$entities_table}
                WHERE id IN ({$placeholders})",
					...$location_ids
				),
				ARRAY_A
			);
		}

		// Get post content if linked to WordPress post
		$post_content = '';
		if ( $event['wp_post_id'] ) {
			$post = get_post( (int) $event['wp_post_id'] );
			if ( $post ) {
				$post_content = apply_filters( 'the_content', $post->post_content );
			}
		}

		wp_send_json_success(
			array(
				'event'        => array(
					'id'              => (int) $event['id'],
					'title'           => $event['title'],
					'description'     => $event['description'],
					'content'         => $post_content,
					'timestamp'       => (float) $event['normalized_timestamp'],
					'canonDate'       => $event['canon_date'],
					'entityType'      => $event['entity_type'] ?? 'event',
					'entityName'      => $event['entity_name'],
					'importanceScore' => (int) ( $event['importance_score'] ?? 50 ),
				),
				'participants' => $participants,
				'locations'    => $locations,
				'related'      => self::getRelatedEvents( (int) $event['event_entity_id'], $wpdb ),
			)
		);
	}

	/**
	 * Assign vertical track for multi-track timeline display
	 *
	 * @param array $event Event data
	 * @return int Track number
	 */
	private static function assignTrack( array $event ): int {
		// Simple hashing to distribute events across tracks
		// This can be improved with conflict detection
		$importance = (int) ( $event['importance_score'] ?? 50 );

		if ( $importance >= 80 ) {
			return 0; // Main track for important events
		} elseif ( $importance >= 50 ) {
			return 1; // Secondary track
		} else {
			return 2; // Tertiary track
		}
	}

	/**
	 * Get related events through entity relationships
	 *
	 * @param int   $entity_id Entity ID
	 * @param \wpdb $wpdb WordPress database object
	 * @return array Related event IDs
	 */
	private static function getRelatedEvents( int $entity_id, $wpdb ): array {
		if ( ! $entity_id ) {
			return array();
		}

		$relationships_table = $wpdb->prefix . 'saga_entity_relationships';
		$events_table        = $wpdb->prefix . 'saga_timeline_events';

		// Get related entities
		$related_entity_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT target_entity_id
            FROM {$relationships_table}
            WHERE source_entity_id = %d
            UNION
            SELECT DISTINCT source_entity_id
            FROM {$relationships_table}
            WHERE target_entity_id = %d",
				$entity_id,
				$entity_id
			)
		);

		if ( empty( $related_entity_ids ) ) {
			return array();
		}

		// Get events for related entities
		$placeholders = implode( ',', array_fill( 0, count( $related_entity_ids ), '%d' ) );

		$related_event_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$events_table} WHERE event_entity_id IN ({$placeholders})",
				...$related_entity_ids
			)
		);

		return array_map( 'intval', $related_event_ids );
	}

	/**
	 * Calculate time range from events
	 *
	 * @param array $events Event array
	 * @return array Min and max timestamps
	 */
	private static function calculateTimeRange( array $events ): array {
		if ( empty( $events ) ) {
			return array(
				'min'  => 0,
				'max'  => 0,
				'span' => 0,
			);
		}

		$timestamps = array_column( $events, 'timestamp' );
		$min        = min( $timestamps );
		$max        = max( $timestamps );

		return array(
			'min'  => $min,
			'max'  => $max,
			'span' => $max - $min,
		);
	}
}

// Register AJAX handlers
TimelineDataHandler::register();
