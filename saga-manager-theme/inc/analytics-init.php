<?php
declare(strict_types=1);

/**
 * Analytics System Initialization
 *
 * @package Saga_Manager_Theme
 */

// Load analytics classes
require_once get_template_directory() . '/inc/class-saga-analytics-db.php';
require_once get_template_directory() . '/inc/class-saga-analytics.php';
require_once get_template_directory() . '/inc/class-saga-popularity.php';
require_once get_template_directory() . '/inc/ajax-handlers.php';
require_once get_template_directory() . '/inc/admin-analytics-dashboard.php';
require_once get_template_directory() . '/inc/cron-jobs.php';
require_once get_template_directory() . '/inc/analytics-helpers.php';
require_once get_template_directory() . '/widgets/class-popular-entities-widget.php';

/**
 * Initialize analytics on theme activation
 */
function saga_analytics_activate(): void {
	// Create database tables
	Saga_Analytics_DB::create_tables();

	// Schedule cron jobs
	saga_schedule_analytics_cron();

	// Flush rewrite rules
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'saga_analytics_activate' );

/**
 * Cleanup on theme deactivation
 */
function saga_analytics_deactivate(): void {
	// Unschedule cron jobs
	saga_unschedule_analytics_cron();

	// Note: We don't drop tables on deactivation
	// Use uninstall.php for complete removal if needed
}
add_action( 'switch_theme', 'saga_analytics_deactivate' );

/**
 * Enqueue analytics styles
 */
function saga_enqueue_analytics_styles(): void {
	// Enqueue on all pages where popularity badges might appear
	wp_enqueue_style(
		'saga-popularity-indicators',
		get_template_directory_uri() . '/assets/css/popularity-indicators.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'saga_enqueue_analytics_styles' );

/**
 * Add popularity badges to entity cards
 *
 * Hook into entity card rendering
 */
function saga_add_popularity_to_card( int $entity_id ): void {
	include get_template_directory() . '/template-parts/popularity-badge.php';
}

/**
 * Add popularity data to REST API response
 */
function saga_add_popularity_to_rest_api(): void {
	register_rest_field(
		'saga_entity',
		'popularity',
		array(
			'get_callback' => function ( $post ) {
				$stats = Saga_Analytics::get_entity_stats( $post['id'] );
				if ( ! $stats ) {
					return null;
				}

				return array(
					'total_views'      => (int) $stats['total_views'],
					'unique_views'     => (int) $stats['unique_views'],
					'popularity_score' => (float) $stats['popularity_score'],
					'badge_type'       => Saga_Popularity::get_badge_type( $post['id'] ),
					'formatted_views'  => Saga_Popularity::get_formatted_views( $post['id'] ),
					'is_trending'      => Saga_Popularity::is_trending( $post['id'] ),
					'is_popular'       => Saga_Popularity::is_popular( $post['id'] ),
				);
			},
			'schema'       => array(
				'description' => __( 'Popularity statistics', 'saga-manager' ),
				'type'        => 'object',
			),
		)
	);
}
add_action( 'rest_api_init', 'saga_add_popularity_to_rest_api' );

/**
 * Add meta box to entity edit screen
 */
function saga_add_analytics_meta_box(): void {
	add_meta_box(
		'saga_analytics_stats',
		__( 'Analytics', 'saga-manager' ),
		'saga_render_analytics_meta_box',
		'saga_entity',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'saga_add_analytics_meta_box' );

/**
 * Render analytics meta box
 */
function saga_render_analytics_meta_box( $post ): void {
	$stats = Saga_Analytics::get_entity_stats( $post->ID );

	if ( ! $stats ) {
		echo '<p>' . esc_html__( 'No analytics data yet.', 'saga-manager' ) . '</p>';
		return;
	}

	$badge_type = Saga_Popularity::get_badge_type( $post->ID );
	?>
	<div class="saga-analytics-meta-box">
		<?php if ( $badge_type ) : ?>
		<div class="analytics-badge analytics-badge--<?php echo esc_attr( $badge_type ); ?>">
			<?php echo esc_html( ucfirst( $badge_type ) ); ?>
		</div>
		<?php endif; ?>

		<table class="widefat">
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'Total Views:', 'saga-manager' ); ?></strong></td>
					<td><?php echo esc_html( number_format( $stats['total_views'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Unique Visitors:', 'saga-manager' ); ?></strong></td>
					<td><?php echo esc_html( number_format( $stats['unique_views'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Avg Time:', 'saga-manager' ); ?></strong></td>
					<td><?php echo esc_html( gmdate( 'i:s', $stats['avg_time_on_page'] ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Bookmarks:', 'saga-manager' ); ?></strong></td>
					<td><?php echo esc_html( $stats['bookmark_count'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Annotations:', 'saga-manager' ); ?></strong></td>
					<td><?php echo esc_html( $stats['annotation_count'] ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Popularity Score:', 'saga-manager' ); ?></strong></td>
					<td><strong><?php echo esc_html( number_format( $stats['popularity_score'], 2 ) ); ?></strong></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Last Viewed:', 'saga-manager' ); ?></strong></td>
					<td>
						<?php
						if ( $stats['last_viewed'] ) {
							echo esc_html( human_time_diff( strtotime( $stats['last_viewed'] ) ) ) . ' ' . esc_html__( 'ago', 'saga-manager' );
						} else {
							esc_html_e( 'Never', 'saga-manager' );
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<p style="margin-top: 1rem;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=saga-analytics' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View All Analytics', 'saga-manager' ); ?>
			</a>
		</p>
	</div>

	<style>
		.saga-analytics-meta-box {
			padding: 0;
		}

		.analytics-badge {
			padding: 0.5rem;
			margin-bottom: 1rem;
			border-radius: 4px;
			text-align: center;
			font-weight: 600;
			color: white;
		}

		.analytics-badge--trending {
			background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
		}

		.analytics-badge--popular {
			background: linear-gradient(135deg, #ffd93d 0%, #f6b93b 100%);
			color: #2c3e50;
		}

		.analytics-badge--rising {
			background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
		}

		.saga-analytics-meta-box table {
			margin: 0;
		}

		.saga-analytics-meta-box td {
			padding: 0.5rem;
		}

		.saga-analytics-meta-box tr:nth-child(even) {
			background: #f9f9f9;
		}
	</style>
	<?php
}

/**
 * Update popularity score when post is updated
 */
function saga_update_score_on_save( $post_id ): void {
	// Skip autosaves and revisions
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Only for saga_entity post type
	if ( get_post_type( $post_id ) !== 'saga_entity' ) {
		return;
	}

	// Update score asynchronously (don't slow down saves)
	wp_schedule_single_event( time() + 10, 'saga_async_score_update', array( $post_id ) );
}
add_action( 'save_post', 'saga_update_score_on_save' );

/**
 * Async score update
 */
function saga_async_score_update( int $entity_id ): void {
	Saga_Popularity::update_score( $entity_id );
}
add_action( 'saga_async_score_update', 'saga_async_score_update' );

/**
 * Add analytics to admin bar
 */
function saga_add_analytics_to_admin_bar( $wp_admin_bar ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Only on single entity pages
	if ( ! is_singular( 'saga_entity' ) ) {
		return;
	}

	$entity_id = get_the_ID();
	$stats     = Saga_Analytics::get_entity_stats( $entity_id );

	if ( ! $stats ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'saga-analytics',
			'title' => sprintf(
				'<span class="ab-icon dashicons-chart-area"></span> %s views',
				Saga_Popularity::get_formatted_views( $entity_id )
			),
			'href'  => admin_url( 'admin.php?page=saga-analytics' ),
			'meta'  => array(
				'title' => sprintf(
					__( 'Popularity Score: %s', 'saga-manager' ),
					number_format( (float) $stats['popularity_score'], 2 )
				),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'saga_add_analytics_to_admin_bar', 100 );

/**
 * Register shortcode for trending entities
 */
function saga_trending_entities_shortcode( $atts ): string {
	$atts = shortcode_atts(
		array(
			'count'       => 5,
			'period'      => 'weekly',
			'show_views'  => 'yes',
			'show_badges' => 'yes',
		),
		$atts
	);

	$trending = Saga_Popularity::get_trending( (int) $atts['count'], $atts['period'] );

	if ( empty( $trending ) ) {
		return '<p>' . esc_html__( 'No trending entities yet.', 'saga-manager' ) . '</p>';
	}

	ob_start();
	?>
	<div class="saga-trending-shortcode">
		<ul class="trending-entities-list">
			<?php
			foreach ( $trending as $entity ) :
				$post = get_post( $entity['entity_id'] );
				if ( ! $post ) {
					continue;
				}
				?>
			<li class="trending-entity-item">
				<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
					<?php echo esc_html( get_the_title( $post ) ); ?>
				</a>
				<?php if ( $atts['show_views'] === 'yes' ) : ?>
					<span class="views">
						(<?php echo esc_html( Saga_Popularity::get_formatted_views( $entity['entity_id'] ) ); ?> views)
					</span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'saga_trending', 'saga_trending_entities_shortcode' );

/**
 * Log analytics errors
 */
function saga_analytics_error_handler( $errno, $errstr, $errfile, $errline ): bool {
	if ( ! ( error_reporting() & $errno ) ) {
		return false;
	}

	// Only log analytics-related errors
	if ( strpos( $errfile, 'saga-analytics' ) === false && strpos( $errfile, 'saga-popularity' ) === false ) {
		return false;
	}

	error_log(
		sprintf(
			'[SAGA][ANALYTICS][ERROR] %s in %s on line %d',
			$errstr,
			basename( $errfile ),
			$errline
		)
	);

	return true;
}

// Only enable in debug mode
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	set_error_handler( 'saga_analytics_error_handler' );
}
