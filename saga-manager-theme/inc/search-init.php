<?php
/**
 * Semantic Search Initialization
 *
 * Initialize and configure the semantic search system.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Search;

/**
 * Initialize semantic search functionality
 */
function init_semantic_search(): void {
	// Require search components
	require_once get_template_directory() . '/inc/search/semantic-scorer.php';
	require_once get_template_directory() . '/inc/ajax/search-handler.php';
	require_once get_template_directory() . '/inc/widgets/search-widget.php';
	require_once get_template_directory() . '/inc/shortcodes/search-shortcode.php';

	// Register assets
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_search_assets' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_search_assets' );

	// Localize scripts
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\localize_search_scripts' );

	// Add search page to WordPress
	add_action( 'init', __NAMESPACE__ . '\\register_search_page' );

	// Modify main query for semantic search
	add_action( 'pre_get_posts', __NAMESPACE__ . '\\modify_search_query' );
}

/**
 * Enqueue search assets
 */
function enqueue_search_assets(): void {
	// CSS
	wp_register_style(
		'saga-semantic-search',
		get_template_directory_uri() . '/assets/css/semantic-search.css',
		array(),
		'1.3.0'
	);

	// JavaScript
	wp_register_script(
		'saga-semantic-search',
		get_template_directory_uri() . '/assets/js/semantic-search.js',
		array( 'jquery' ),
		'1.3.0',
		true
	);

	wp_register_script(
		'saga-search-autocomplete',
		get_template_directory_uri() . '/assets/js/search-autocomplete.js',
		array( 'jquery', 'saga-semantic-search' ),
		'1.3.0',
		true
	);

	// Enqueue on search pages and where shortcode/widget is used
	if ( is_search() || is_page_template( 'page-templates/search-page.php' ) || is_active_widget( false, false, 'saga_search_widget' ) ) {
		wp_enqueue_style( 'saga-semantic-search' );
		wp_enqueue_script( 'saga-semantic-search' );
		wp_enqueue_script( 'saga-search-autocomplete' );
	}
}

/**
 * Enqueue admin search assets
 */
function enqueue_admin_search_assets( string $hook ): void {
	// Enqueue on post edit screens for quick search
	if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
		wp_enqueue_style( 'saga-semantic-search' );
		wp_enqueue_script( 'saga-semantic-search' );
		wp_enqueue_script( 'saga-search-autocomplete' );
	}
}

/**
 * Localize search scripts with configuration
 */
function localize_search_scripts(): void {
	if ( ! wp_script_is( 'saga-semantic-search', 'enqueued' ) ) {
		return;
	}

	wp_localize_script(
		'saga-semantic-search',
		'sagaSearchData',
		array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'saga_search_nonce' ),
			'searchPageUrl'  => get_search_page_url(),
			'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'trackAnalytics' => apply_filters( 'saga_search_track_analytics', true ),
			'strings'        => array(
				'searching' => __( 'Searching...', 'saga-manager' ),
				'noResults' => __( 'No results found', 'saga-manager' ),
				'error'     => __( 'An error occurred', 'saga-manager' ),
				'loadMore'  => __( 'Load More', 'saga-manager' ),
				'loading'   => __( 'Loading...', 'saga-manager' ),
			),
		)
	);
}

/**
 * Get search page URL
 */
function get_search_page_url(): string {
	// Check if custom search page exists
	$search_page = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'page-templates/search-page.php',
			'number'     => 1,
		)
	);

	if ( ! empty( $search_page ) ) {
		return get_permalink( $search_page[0] );
	}

	return home_url( '/?s=' );
}

/**
 * Register search page programmatically if needed
 */
function register_search_page(): void {
	// Check if search page exists
	$search_page = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'page-templates/search-page.php',
			'number'     => 1,
		)
	);

	// Create if doesn't exist (only on theme activation)
	if ( empty( $search_page ) && get_option( 'saga_search_page_created' ) !== 'yes' ) {
		$page_id = wp_insert_post(
			array(
				'post_title'    => __( 'Search Entities', 'saga-manager' ),
				'post_content'  => __( 'Search through saga entities using our advanced semantic search.', 'saga-manager' ),
				'post_status'   => 'publish',
				'post_type'     => 'page',
				'post_author'   => 1,
				'page_template' => 'page-templates/search-page.php',
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'page-templates/search-page.php' );
			update_option( 'saga_search_page_created', 'yes' );
		}
	}
}

/**
 * Modify main WordPress search query for better results
 */
function modify_search_query( \WP_Query $query ): void {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		// Prioritize saga entities in search
		$post_type = $query->get( 'post_type' );

		if ( empty( $post_type ) ) {
			$query->set( 'post_type', array( 'post', 'page', 'saga_entity' ) );
		}

		// Order by relevance (will be overridden by semantic scorer in AJAX)
		$query->set( 'orderby', 'relevance' );

		// Increase posts per page
		$query->set( 'posts_per_page', 50 );
	}
}

/**
 * Add search form to header
 */
function add_header_search_form(): void {
	?>
	<div class="saga-header-search">
		<?php
		get_template_part(
			'template-parts/search-form',
			null,
			array(
				'placeholder'  => __( 'Quick search...', 'saga-manager' ),
				'show_filters' => false,
				'show_voice'   => true,
				'show_results' => true,
				'compact'      => true,
			)
		);
		?>
	</div>
	<?php
}

/**
 * Get search analytics dashboard widget
 */
function search_analytics_dashboard_widget(): void {
	$analytics = \SagaManager\Ajax\SearchHandler::get_search_analytics();
	?>
	<div class="saga-search-analytics">
		<div class="saga-analytics-stats">
			<div class="saga-stat">
				<div class="saga-stat-value"><?php echo number_format_i18n( $analytics['total_searches'] ); ?></div>
				<div class="saga-stat-label"><?php esc_html_e( 'Total Searches', 'saga-manager' ); ?></div>
			</div>

			<div class="saga-stat">
				<div class="saga-stat-value"><?php echo number_format_i18n( $analytics['total_clicks'] ); ?></div>
				<div class="saga-stat-label"><?php esc_html_e( 'Result Clicks', 'saga-manager' ); ?></div>
			</div>

			<div class="saga-stat">
				<div class="saga-stat-value"><?php echo $analytics['click_through_rate']; ?>%</div>
				<div class="saga-stat-label"><?php esc_html_e( 'Click-Through Rate', 'saga-manager' ); ?></div>
			</div>
		</div>

		<?php if ( ! empty( $analytics['popular_searches'] ) ) : ?>
			<div class="saga-analytics-popular">
				<h4><?php esc_html_e( 'Popular Searches', 'saga-manager' ); ?></h4>
				<ol>
					<?php foreach ( $analytics['popular_searches'] as $search ) : ?>
						<li><?php echo esc_html( $search ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $analytics['recent_searches'] ) ) : ?>
			<div class="saga-analytics-recent">
				<h4><?php esc_html_e( 'Recent Searches', 'saga-manager' ); ?></h4>
				<ul>
					<?php foreach ( array_slice( $analytics['recent_searches'], 0, 10 ) as $search ) : ?>
						<li>
							<strong><?php echo esc_html( $search['query'] ); ?></strong>
							<span class="saga-search-meta">
								<?php
								printf(
									/* translators: %d: number of results */
									_n( '%d result', '%d results', $search['results'], 'saga-manager' ),
									$search['results']
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Add dashboard widget
 */
function add_search_analytics_widget(): void {
	wp_add_dashboard_widget(
		'saga_search_analytics',
		__( 'Saga Search Analytics', 'saga-manager' ),
		__NAMESPACE__ . '\\search_analytics_dashboard_widget'
	);
}

add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\\add_search_analytics_widget' );

/**
 * Clear search cache on entity save
 */
function clear_search_cache_on_save( int $post_id ): void {
	if ( get_post_type( $post_id ) === 'saga_entity' ) {
		\SagaManager\Ajax\SearchHandler::clear_cache();
	}
}

add_action( 'save_post', __NAMESPACE__ . '\\clear_search_cache_on_save' );

/**
 * Add search meta box to posts
 */
function add_entity_search_meta_box(): void {
	add_meta_box(
		'saga_entity_search',
		__( 'Quick Entity Search', 'saga-manager' ),
		__NAMESPACE__ . '\\render_entity_search_meta_box',
		array( 'post', 'page' ),
		'side',
		'default'
	);
}

add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_entity_search_meta_box' );

/**
 * Render entity search meta box
 */
function render_entity_search_meta_box( \WP_Post $post ): void {
	?>
	<div class="saga-meta-box-search">
		<p><?php esc_html_e( 'Quickly search for entities to link in your content.', 'saga-manager' ); ?></p>
		<?php
		get_template_part(
			'template-parts/search-form',
			null,
			array(
				'placeholder'  => __( 'Search entities...', 'saga-manager' ),
				'show_filters' => false,
				'show_voice'   => false,
				'show_results' => true,
				'compact'      => true,
				'max_results'  => 5,
			)
		);
		?>
	</div>
	<style>
		.saga-meta-box-search .saga-search-input {
			width: 100%;
		}
		.saga-meta-box-search .saga-search-results {
			max-height: 300px;
			overflow-y: auto;
		}
	</style>
	<?php
}

// Initialize
add_action( 'after_setup_theme', __NAMESPACE__ . '\\init_semantic_search' );
