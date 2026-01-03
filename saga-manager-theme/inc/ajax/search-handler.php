<?php
/**
 * AJAX Search Handler
 *
 * Handles AJAX requests for semantic search, autocomplete,
 * and search analytics tracking.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Ajax;

use SagaManager\Search\SemanticScorer;

class SearchHandler {

	/**
	 * Semantic scorer instance
	 */
	private SemanticScorer $scorer;

	/**
	 * Cache group name
	 */
	private const CACHE_GROUP = 'saga_search';

	/**
	 * Cache expiration time (5 minutes)
	 */
	private const CACHE_EXPIRATION = 300;

	/**
	 * Initialize handler
	 */
	public function __construct() {
		$this->scorer = new SemanticScorer();
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks(): void {
		add_action( 'wp_ajax_saga_semantic_search', array( $this, 'handle_search' ) );
		add_action( 'wp_ajax_nopriv_saga_semantic_search', array( $this, 'handle_search' ) );

		add_action( 'wp_ajax_saga_autocomplete', array( $this, 'handle_autocomplete' ) );
		add_action( 'wp_ajax_nopriv_saga_autocomplete', array( $this, 'handle_autocomplete' ) );

		add_action( 'wp_ajax_saga_track_search_click', array( $this, 'handle_track_click' ) );
		add_action( 'wp_ajax_nopriv_saga_track_search_click', array( $this, 'handle_track_click' ) );
	}

	/**
	 * Handle semantic search request
	 */
	public function handle_search(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'saga_search_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		}

		// Get and validate parameters
		$query   = sanitize_text_field( $_POST['query'] ?? '' );
		$parsed  = $_POST['parsed'] ?? array();
		$filters = $_POST['filters'] ?? array();
		$sort    = sanitize_text_field( $_POST['sort'] ?? 'relevance' );
		$limit   = absint( $_POST['limit'] ?? 50 );
		$offset  = absint( $_POST['offset'] ?? 0 );

		if ( empty( $query ) || strlen( $query ) < 2 ) {
			wp_send_json_error( array( 'message' => 'Query too short' ), 400 );
		}

		// Check cache
		$cache_key = $this->get_cache_key( $query, $filters, $sort, $limit, $offset );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $cached !== false ) {
			wp_send_json_success( $cached );
		}

		// Start timing
		$start_time = microtime( true );

		try {
			// Perform search
			$results = $this->search_entities( $query, $parsed, $filters, $sort, $limit, $offset );

			// Calculate query time
			$query_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

			// Generate suggestions
			$suggestions = $this->scorer->generateSuggestions( $query, $results['items'] );

			$response_data = array(
				'query'       => $query,
				'results'     => $results['items'],
				'total'       => $results['total'],
				'query_time'  => $query_time,
				'suggestions' => $suggestions,
				'grouped'     => ! empty( $filters['types'] ),
			);

			// Cache the response
			wp_cache_set( $cache_key, $response_data, self::CACHE_GROUP, self::CACHE_EXPIRATION );

			// Track search query
			$this->track_search( $query, $results['total'] );

			wp_send_json_success( $response_data );

		} catch ( \Exception $e ) {
			error_log( '[SAGA] Search error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Search failed' ), 500 );
		}
	}

	/**
	 * Search entities in database
	 */
	private function search_entities(
		string $query,
		array $parsed,
		array $filters,
		string $sort,
		int $limit,
		int $offset
	): array {
		global $wpdb;

		$table_entities   = $wpdb->prefix . 'saga_entities';
		$table_attributes = $wpdb->prefix . 'saga_attribute_values';

		// Build WHERE clause
		$where_clauses = array( '1=1' );
		$query_params  = array();

		// Full-text search on name and description
		$search_term     = '%' . $wpdb->esc_like( $query ) . '%';
		$where_clauses[] = $wpdb->prepare(
			'(e.canonical_name LIKE %s OR e.slug LIKE %s)',
			$search_term,
			$search_term
		);

		// Apply filters
		if ( ! empty( $filters['types'] ) ) {
			$types_placeholders = implode( ',', array_fill( 0, count( $filters['types'] ), '%s' ) );
			$where_clauses[]    = $wpdb->prepare(
				"e.entity_type IN ({$types_placeholders})",
				...$filters['types']
			);
		}

		if ( ! empty( $filters['sagaId'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'e.saga_id = %d', absint( $filters['sagaId'] ) );
		}

		if ( ! empty( $filters['importance'] ) ) {
			$min             = absint( $filters['importance']['min'] ?? 0 );
			$max             = absint( $filters['importance']['max'] ?? 100 );
			$where_clauses[] = $wpdb->prepare(
				'e.importance_score BETWEEN %d AND %d',
				$min,
				$max
			);
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$table_entities} e WHERE {$where_sql}";
		$total       = (int) $wpdb->get_var( $count_query );

		// Build main query
		$select_fields = 'e.id, e.saga_id, e.entity_type, e.canonical_name,
                         e.slug, e.importance_score, e.wp_post_id, e.updated_at';

		$query_sql = "SELECT {$select_fields} FROM {$table_entities} e WHERE {$where_sql}";

		// Apply sorting
		$query_sql .= $this->get_order_clause( $sort );

		// Apply pagination
		$query_sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );

		// Execute query
		$entities = $wpdb->get_results( $query_sql, ARRAY_A );

		if ( empty( $entities ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}

		// Enrich results with additional data
		$enriched_results = $this->enrich_results( $entities, $query );

		// Apply semantic scoring and sort
		if ( $sort === 'relevance' ) {
			$enriched_results = $this->scorer->sortByRelevance(
				$enriched_results,
				$query,
				$parsed
			);
		}

		return array(
			'items' => $enriched_results,
			'total' => $total,
		);
	}

	/**
	 * Enrich results with additional data
	 */
	private function enrich_results( array $entities, string $query ): array {
		global $wpdb;

		$enriched = array();

		foreach ( $entities as $entity ) {
			$enriched_entity = $entity;

			// Get saga name
			if ( $entity['saga_id'] ) {
				$saga_table = $wpdb->prefix . 'saga_sagas';
				$saga       = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT name FROM {$saga_table} WHERE id = %d",
						$entity['saga_id']
					),
					ARRAY_A
				);

				$enriched_entity['saga_name'] = $saga['name'] ?? '';
			}

			// Get snippet from WordPress post
			if ( $entity['wp_post_id'] ) {
				$post = get_post( $entity['wp_post_id'] );
				if ( $post ) {
					$content                    = $post->post_content;
					$enriched_entity['snippet'] = $this->generate_snippet( $content, $query, 150 );
					$enriched_entity['url']     = get_permalink( $post );
					$enriched_entity['title']   = $post->post_title;
				} else {
					$enriched_entity['snippet'] = '';
					$enriched_entity['url']     = '#';
					$enriched_entity['title']   = $entity['canonical_name'];
				}
			} else {
				$enriched_entity['snippet'] = '';
				$enriched_entity['url']     = '#';
				$enriched_entity['title']   = $entity['canonical_name'];
			}

			// Get thumbnail
			if ( $entity['wp_post_id'] ) {
				$enriched_entity['thumbnail'] = get_the_post_thumbnail_url( $entity['wp_post_id'], 'thumbnail' );
			}

			$enriched[] = $enriched_entity;
		}

		return $enriched;
	}

	/**
	 * Generate snippet with highlighted query terms
	 */
	private function generate_snippet( string $content, string $query, int $max_length = 150 ): string {
		// Strip HTML tags
		$content = wp_strip_all_tags( $content );

		// Find position of query in content
		$query_pos = stripos( $content, $query );

		if ( $query_pos !== false ) {
			// Extract snippet around query
			$start   = max( 0, $query_pos - 50 );
			$snippet = substr( $content, $start, $max_length );

			// Clean up
			if ( $start > 0 ) {
				$snippet = '...' . $snippet;
			}
			if ( strlen( $content ) > $start + $max_length ) {
				$snippet .= '...';
			}
		} else {
			// Take first N characters
			$snippet = substr( $content, 0, $max_length );
			if ( strlen( $content ) > $max_length ) {
				$snippet .= '...';
			}
		}

		return trim( $snippet );
	}

	/**
	 * Get SQL ORDER clause based on sort option
	 */
	private function get_order_clause( string $sort ): string {
		switch ( $sort ) {
			case 'name':
				return ' ORDER BY e.canonical_name ASC';

			case 'date':
				return ' ORDER BY e.updated_at DESC';

			case 'importance':
				return ' ORDER BY e.importance_score DESC';

			case 'relevance':
			default:
				// Will be sorted by semantic scorer
				return ' ORDER BY e.importance_score DESC';
		}
	}

	/**
	 * Handle autocomplete request
	 */
	public function handle_autocomplete(): void {
		// Verify nonce
		if ( ! check_ajax_referer( 'saga_search_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		}

		$query            = sanitize_text_field( $_POST['query'] ?? '' );
		$max_suggestions  = absint( $_POST['max_suggestions'] ?? 10 );
		$include_entities = filter_var( $_POST['include_entities'] ?? true, FILTER_VALIDATE_BOOLEAN );

		if ( empty( $query ) || strlen( $query ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Check cache
		$cache_key = 'autocomplete_' . md5( $query . $max_suggestions . (int) $include_entities );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( $cached !== false ) {
			wp_send_json_success( $cached );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'saga_entities';

		$suggestions = array();

		// Get exact and partial matches
		$search_term = $wpdb->esc_like( $query ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, canonical_name, entity_type, importance_score, wp_post_id
             FROM {$table}
             WHERE canonical_name LIKE %s
             ORDER BY importance_score DESC, canonical_name ASC
             LIMIT %d",
				$search_term,
				$max_suggestions
			),
			ARRAY_A
		);

		foreach ( $results as $result ) {
			$suggestion = array(
				'type'  => 'entity',
				'text'  => $result['canonical_name'],
				'value' => $result['canonical_name'],
				'icon'  => $result['entity_type'],
				'meta'  => ucfirst( $result['entity_type'] ),
			);

			// Include entity preview if requested
			if ( $include_entities && $result['wp_post_id'] ) {
				$post = get_post( $result['wp_post_id'] );
				if ( $post ) {
					$suggestion['preview'] = array(
						'snippet' => wp_trim_words( $post->post_content, 15 ),
						'image'   => get_the_post_thumbnail_url( $post, 'thumbnail' ),
					);
				}
			}

			$suggestions[] = $suggestion;
		}

		// Cache suggestions
		wp_cache_set( $cache_key, $suggestions, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		wp_send_json_success( $suggestions );
	}

	/**
	 * Handle search click tracking
	 */
	public function handle_track_click(): void {
		if ( ! check_ajax_referer( 'saga_search_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		}

		$query     = sanitize_text_field( $_POST['query'] ?? '' );
		$entity_id = absint( $_POST['entity_id'] ?? 0 );

		if ( empty( $query ) || ! $entity_id ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
		}

		// Track click in transient (for analytics)
		$clicks   = get_transient( 'saga_search_clicks' ) ?: array();
		$clicks[] = array(
			'query'     => $query,
			'entity_id' => $entity_id,
			'timestamp' => time(),
			'user_id'   => get_current_user_id(),
		);

		// Keep only last 1000 clicks
		$clicks = array_slice( $clicks, -1000 );

		set_transient( 'saga_search_clicks', $clicks, WEEK_IN_SECONDS );

		wp_send_json_success( array( 'tracked' => true ) );
	}

	/**
	 * Track search query
	 */
	private function track_search( string $query, int $results_count ): void {
		$searches = get_transient( 'saga_search_queries' ) ?: array();

		$searches[] = array(
			'query'     => $query,
			'results'   => $results_count,
			'timestamp' => time(),
			'user_id'   => get_current_user_id(),
		);

		// Keep only last 1000 searches
		$searches = array_slice( $searches, -1000 );

		set_transient( 'saga_search_queries', $searches, WEEK_IN_SECONDS );

		// Update popular searches counter
		$popular           = get_option( 'saga_popular_searches', array() );
		$popular[ $query ] = ( $popular[ $query ] ?? 0 ) + 1;

		// Keep only top 100
		arsort( $popular );
		$popular = array_slice( $popular, 0, 100, true );

		update_option( 'saga_popular_searches', $popular, false );
	}

	/**
	 * Generate cache key
	 */
	private function get_cache_key(
		string $query,
		array $filters,
		string $sort,
		int $limit,
		int $offset
	): string {
		return 'search_' . md5(
			serialize(
				array(
					'query'   => $query,
					'filters' => $filters,
					'sort'    => $sort,
					'limit'   => $limit,
					'offset'  => $offset,
				)
			)
		);
	}

	/**
	 * Clear search cache
	 */
	public static function clear_cache(): void {
		wp_cache_flush_group( self::CACHE_GROUP );
	}

	/**
	 * Get popular searches
	 */
	public static function get_popular_searches( int $limit = 10 ): array {
		$popular = get_option( 'saga_popular_searches', array() );
		arsort( $popular );

		return array_slice( array_keys( $popular ), 0, $limit );
	}

	/**
	 * Get search analytics
	 */
	public static function get_search_analytics(): array {
		$searches = get_transient( 'saga_search_queries' ) ?: array();
		$clicks   = get_transient( 'saga_search_clicks' ) ?: array();

		return array(
			'total_searches'     => count( $searches ),
			'total_clicks'       => count( $clicks ),
			'recent_searches'    => array_slice( $searches, -10 ),
			'popular_searches'   => self::get_popular_searches( 10 ),
			'click_through_rate' => count( $searches ) > 0
				? round( ( count( $clicks ) / count( $searches ) ) * 100, 2 )
				: 0,
		);
	}
}

// Initialize handler
new SearchHandler();
