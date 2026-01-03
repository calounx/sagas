<?php
declare(strict_types=1);

/**
 * Admin Analytics Dashboard
 *
 * @package Saga_Manager_Theme
 */

/**
 * Add analytics menu to admin
 */
function saga_add_analytics_menu(): void {
	add_menu_page(
		__( 'Saga Analytics', 'saga-manager' ),
		__( 'Analytics', 'saga-manager' ),
		'manage_options',
		'saga-analytics',
		'saga_render_analytics_dashboard',
		'dashicons-chart-area',
		30
	);

	add_submenu_page(
		'saga-analytics',
		__( 'Entity Stats', 'saga-manager' ),
		__( 'Entity Stats', 'saga-manager' ),
		'manage_options',
		'saga-entity-stats',
		'saga_render_entity_stats_page'
	);
}
add_action( 'admin_menu', 'saga_add_analytics_menu' );

/**
 * Render analytics dashboard
 */
function saga_render_analytics_dashboard(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.', 'saga-manager' ) );
	}

	$summary  = Saga_Popularity::get_summary_stats();
	$trending = Saga_Popularity::get_trending( 10, 'weekly' );
	$popular  = Saga_Popularity::get_popular( 10 );

	?>
	<div class="wrap saga-analytics-dashboard">
		<h1><?php esc_html_e( 'Saga Analytics Dashboard', 'saga-manager' ); ?></h1>

		<div class="saga-stats-grid">
			<!-- Summary Cards -->
			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
						<circle cx="12" cy="12" r="3"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( $summary['total_views'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Total Views', 'saga-manager' ); ?></div>
				</div>
			</div>

			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
						<circle cx="9" cy="7" r="4"/>
						<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
						<path d="M16 3.13a4 4 0 0 1 0 7.75"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( $summary['total_unique_visitors'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Unique Visitors', 'saga-manager' ); ?></div>
				</div>
			</div>

			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( $summary['trending_count'] ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Trending Now', 'saga-manager' ); ?></div>
				</div>
			</div>

			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"/>
						<polyline points="12 6 12 12 16 14"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( $summary['views_last_24h'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Views (24h)', 'saga-manager' ); ?></div>
				</div>
			</div>

			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
						<circle cx="9" cy="7" r="4"/>
						<line x1="19" y1="8" x2="19" y2="14"/>
						<line x1="22" y1="11" x2="16" y2="11"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( $summary['total_entities_tracked'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Tracked Entities', 'saga-manager' ); ?></div>
				</div>
			</div>

			<div class="saga-stat-card">
				<div class="stat-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
					</svg>
				</div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( $summary['avg_popularity_score'], 2 ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Avg Popularity', 'saga-manager' ); ?></div>
				</div>
			</div>
		</div>

		<div class="saga-analytics-tables">
			<!-- Trending Entities -->
			<div class="saga-analytics-table-container">
				<h2><?php esc_html_e( 'Trending This Week', 'saga-manager' ); ?></h2>
				<?php saga_render_entities_table( $trending ); ?>
			</div>

			<!-- Popular Entities -->
			<div class="saga-analytics-table-container">
				<h2><?php esc_html_e( 'Most Popular (All-Time)', 'saga-manager' ); ?></h2>
				<?php saga_render_entities_table( $popular ); ?>
			</div>
		</div>

		<!-- Actions -->
		<div class="saga-analytics-actions">
			<h2><?php esc_html_e( 'Maintenance', 'saga-manager' ); ?></h2>
			<button type="button" class="button button-secondary" id="saga-recalculate-scores">
				<?php esc_html_e( 'Recalculate All Scores', 'saga-manager' ); ?>
			</button>
			<button type="button" class="button button-secondary" id="saga-cleanup-logs">
				<?php esc_html_e( 'Cleanup Old Logs (90+ days)', 'saga-manager' ); ?>
			</button>
			<button type="button" class="button" id="saga-export-analytics">
				<?php esc_html_e( 'Export Analytics Data', 'saga-manager' ); ?>
			</button>
			<span class="spinner"></span>
			<span class="saga-action-message"></span>
		</div>
	</div>

	<style>
		.saga-analytics-dashboard {
			max-width: 1400px;
		}

		.saga-stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 1.5rem;
			margin: 2rem 0;
		}

		.saga-stat-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 1.5rem;
			display: flex;
			align-items: center;
			gap: 1rem;
			transition: box-shadow 0.2s ease;
		}

		.saga-stat-card:hover {
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
		}

		.stat-icon {
			width: 48px;
			height: 48px;
			border-radius: 8px;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			display: flex;
			align-items: center;
			justify-content: center;
			color: #fff;
		}

		.stat-content {
			flex: 1;
		}

		.stat-value {
			font-size: 1.875rem;
			font-weight: 700;
			color: #1f2937;
			line-height: 1;
			margin-bottom: 0.25rem;
		}

		.stat-label {
			font-size: 0.875rem;
			color: #6b7280;
		}

		.saga-analytics-tables {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
			gap: 2rem;
			margin: 2rem 0;
		}

		.saga-analytics-table-container {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 1.5rem;
		}

		.saga-analytics-table-container h2 {
			margin-top: 0;
			margin-bottom: 1rem;
			font-size: 1.25rem;
		}

		.saga-entities-table {
			width: 100%;
			border-collapse: collapse;
		}

		.saga-entities-table th {
			text-align: left;
			padding: 0.75rem 0.5rem;
			border-bottom: 2px solid #e5e7eb;
			font-size: 0.875rem;
			font-weight: 600;
			color: #6b7280;
		}

		.saga-entities-table td {
			padding: 0.75rem 0.5rem;
			border-bottom: 1px solid #f3f4f6;
		}

		.saga-entities-table tr:hover {
			background: #f9fafb;
		}

		.entity-title-cell a {
			color: #2563eb;
			text-decoration: none;
			font-weight: 500;
		}

		.entity-title-cell a:hover {
			text-decoration: underline;
		}

		.score-badge {
			display: inline-block;
			padding: 0.25rem 0.5rem;
			border-radius: 4px;
			font-size: 0.75rem;
			font-weight: 600;
			background: linear-gradient(135deg, #ffd93d 0%, #f6b93b 100%);
			color: #2c3e50;
		}

		.saga-analytics-actions {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 1.5rem;
			margin: 2rem 0;
		}

		.saga-analytics-actions h2 {
			margin-top: 0;
			margin-bottom: 1rem;
		}

		.saga-analytics-actions .button {
			margin-right: 0.5rem;
		}

		.saga-action-message {
			margin-left: 1rem;
			font-weight: 500;
		}

		.saga-action-message.success {
			color: #10b981;
		}

		.saga-action-message.error {
			color: #ef4444;
		}
	</style>

	<script>
	jQuery(document).ready(function($) {
		// Recalculate scores
		$('#saga-recalculate-scores').on('click', function() {
			const $btn = $(this);
			const $spinner = $('.saga-analytics-actions .spinner');
			const $message = $('.saga-action-message');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$message.text('');

			$.post(ajaxurl, {
				action: 'saga_recalculate_scores',
				nonce: '<?php echo esc_js( wp_create_nonce( 'saga_admin_analytics' ) ); ?>'
			}, function(response) {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');

				if (response.success) {
					$message.addClass('success').text(response.data.message);
					setTimeout(() => location.reload(), 2000);
				} else {
					$message.addClass('error').text(response.data.message || 'Error occurred');
				}
			});
		});

		// Cleanup logs
		$('#saga-cleanup-logs').on('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Delete view logs older than 90 days?', 'saga-manager' ) ); ?>')) {
				return;
			}

			const $btn = $(this);
			const $spinner = $('.saga-analytics-actions .spinner');
			const $message = $('.saga-action-message');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$message.text('');

			$.post(ajaxurl, {
				action: 'saga_cleanup_logs',
				nonce: '<?php echo esc_js( wp_create_nonce( 'saga_admin_analytics' ) ); ?>'
			}, function(response) {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');

				if (response.success) {
					$message.addClass('success').text(response.data.message);
				} else {
					$message.addClass('error').text(response.data.message || 'Error occurred');
				}
			});
		});

		// Export analytics
		$('#saga-export-analytics').on('click', function() {
			window.location.href = ajaxurl + '?action=saga_export_analytics&nonce=<?php echo esc_js( wp_create_nonce( 'saga_admin_analytics' ) ); ?>';
		});
	});
	</script>
	<?php
}

/**
 * Render entities table
 *
 * @param array $entities Entities data
 */
function saga_render_entities_table( array $entities ): void {
	if ( empty( $entities ) ) {
		echo '<p>' . esc_html__( 'No data available yet.', 'saga-manager' ) . '</p>';
		return;
	}

	echo '<table class="saga-entities-table">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Entity', 'saga-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Views', 'saga-manager' ) . '</th>';
	echo '<th>' . esc_html__( 'Score', 'saga-manager' ) . '</th>';
	echo '</tr></thead>';
	echo '<tbody>';

	foreach ( $entities as $entity ) {
		$post = get_post( $entity['entity_id'] );
		if ( ! $post ) {
			continue;
		}

		$views = Saga_Popularity::get_formatted_views( $entity['entity_id'] );
		$score = number_format( (float) $entity['trend_score'], 2 );

		echo '<tr>';
		echo '<td class="entity-title-cell">';
		echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>';
		echo '</td>';
		echo '<td>' . esc_html( $views ) . '</td>';
		echo '<td><span class="score-badge">' . esc_html( $score ) . '</span></td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * Render entity stats page
 */
function saga_render_entity_stats_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.', 'saga-manager' ) );
	}

	global $wpdb;
	$stats_table = $wpdb->prefix . 'saga_entity_stats';

	// Pagination
	$per_page = 50;
	$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$offset   = ( $page - 1 ) * $per_page;

	// Get total count
	$total       = $wpdb->get_var( "SELECT COUNT(*) FROM {$stats_table}" );
	$total_pages = ceil( $total / $per_page );

	// Get entities
	$entities = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$stats_table}
        ORDER BY popularity_score DESC
        LIMIT %d OFFSET %d",
			$per_page,
			$offset
		),
		ARRAY_A
	);

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Entity Statistics', 'saga-manager' ); ?></h1>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Entity', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Total Views', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Unique Views', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Avg Time', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Bookmarks', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Annotations', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Popularity Score', 'saga-manager' ); ?></th>
					<th><?php esc_html_e( 'Last Viewed', 'saga-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $entities as $entity ) :
					$post = get_post( $entity['entity_id'] );
					if ( ! $post ) {
						continue;
					}
					?>
				<tr>
					<td>
						<strong>
							<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $post ) ); ?>
							</a>
						</strong>
					</td>
					<td><?php echo esc_html( number_format( $entity['total_views'] ) ); ?></td>
					<td><?php echo esc_html( number_format( $entity['unique_views'] ) ); ?></td>
					<td><?php echo esc_html( gmdate( 'i:s', $entity['avg_time_on_page'] ) ); ?></td>
					<td><?php echo esc_html( $entity['bookmark_count'] ); ?></td>
					<td><?php echo esc_html( $entity['annotation_count'] ); ?></td>
					<td><?php echo esc_html( number_format( $entity['popularity_score'], 2 ) ); ?></td>
					<td><?php echo esc_html( $entity['last_viewed'] ?: '—' ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo;' ),
						'next_text' => __( '&raquo;' ),
						'total'     => $total_pages,
						'current'   => $page,
					)
				);
				?>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * AJAX: Recalculate all scores
 */
function saga_ajax_recalculate_scores(): void {
	check_ajax_referer( 'saga_admin_analytics', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
	}

	$updated = Saga_Popularity::batch_update_scores( 500 );

	wp_send_json_success(
		array(
			'message' => sprintf( __( '%d entity scores recalculated.', 'saga-manager' ), $updated ),
		)
	);
}
add_action( 'wp_ajax_saga_recalculate_scores', 'saga_ajax_recalculate_scores' );

/**
 * AJAX: Cleanup old logs
 */
function saga_ajax_cleanup_logs(): void {
	check_ajax_referer( 'saga_admin_analytics', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
	}

	$deleted = Saga_Analytics_DB::cleanup_old_logs();

	wp_send_json_success(
		array(
			'message' => sprintf( __( '%d old log entries deleted.', 'saga-manager' ), $deleted ),
		)
	);
}
add_action( 'wp_ajax_saga_cleanup_logs', 'saga_ajax_cleanup_logs' );

/**
 * Export analytics data
 */
function saga_export_analytics_data(): void {
	check_ajax_referer( 'saga_admin_analytics', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	global $wpdb;
	$stats_table = $wpdb->prefix . 'saga_entity_stats';

	$entities = $wpdb->get_results(
		"SELECT * FROM {$stats_table} ORDER BY popularity_score DESC",
		ARRAY_A
	);

	// Generate CSV
	header( 'Content-Type: text/csv' );
	header( 'Content-Disposition: attachment; filename="saga-analytics-' . date( 'Y-m-d' ) . '.csv"' );

	$output = fopen( 'php://output', 'w' );

	// Headers
	fputcsv(
		$output,
		array(
			'Entity ID',
			'Entity Title',
			'Total Views',
			'Unique Views',
			'Avg Time (seconds)',
			'Bookmarks',
			'Annotations',
			'Popularity Score',
			'Last Viewed',
		)
	);

	// Data
	foreach ( $entities as $entity ) {
		$post = get_post( $entity['entity_id'] );
		fputcsv(
			$output,
			array(
				$entity['entity_id'],
				$post ? get_the_title( $post ) : 'Unknown',
				$entity['total_views'],
				$entity['unique_views'],
				$entity['avg_time_on_page'],
				$entity['bookmark_count'],
				$entity['annotation_count'],
				$entity['popularity_score'],
				$entity['last_viewed'],
			)
		);
	}

	fclose( $output );
	exit;
}
add_action( 'wp_ajax_saga_export_analytics', 'saga_export_analytics_data' );
