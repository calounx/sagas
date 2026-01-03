<?php
/**
 * AI Consistency Guardian Dashboard Widget
 *
 * Displays summary of consistency issues on WordPress dashboard
 *
 * @package SagaManager\Admin
 * @version 1.4.0
 */

declare(strict_types=1);

use SagaManager\AI\ConsistencyRepository;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register dashboard widget
 */
function saga_register_consistency_widget(): void {
	wp_add_dashboard_widget(
		'saga_consistency_widget',
		__( 'AI Consistency Guardian', 'saga-manager-theme' ),
		'saga_render_consistency_widget'
	);
}
add_action( 'wp_dashboard_setup', 'saga_register_consistency_widget' );

/**
 * Render dashboard widget
 */
function saga_render_consistency_widget(): void {
	// Get current saga from user meta or default
	$currentSagaId = (int) get_user_meta( get_current_user_id(), 'saga_current_saga_id', true );

	if ( $currentSagaId === 0 ) {
		// Get first saga
		global $wpdb;
		$currentSagaId = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}saga_sagas ORDER BY id ASC LIMIT 1" );
	}

	if ( $currentSagaId === 0 ) {
		echo '<p>' . esc_html__( 'No sagas found. Create a saga to start using AI Consistency Guardian.', 'saga-manager-theme' ) . '</p>';
		return;
	}

	$repository = new ConsistencyRepository();

	// Get statistics with caching
	$cacheKey = 'saga_widget_stats_' . $currentSagaId;
	$stats    = wp_cache_get( $cacheKey, 'saga' );

	if ( $stats === false ) {
		$stats = $repository->getStatistics( $currentSagaId );
		wp_cache_set( $cacheKey, $stats, 'saga', 300 ); // 5 minutes
	}

	// Get recent issues
	$recentIssues = $repository->getRecentIssues( $currentSagaId, 5 );

	?>
	<div class="saga-consistency-widget">
		<!-- Stats Grid -->
		<div class="saga-widget-stats">
			<div class="stat-item stat-critical">
				<span class="stat-count"><?php echo esc_html( $stats['critical_count'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Critical', 'saga-manager-theme' ); ?></span>
			</div>
			<div class="stat-item stat-high">
				<span class="stat-count"><?php echo esc_html( $stats['high_count'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'High', 'saga-manager-theme' ); ?></span>
			</div>
			<div class="stat-item stat-medium">
				<span class="stat-count"><?php echo esc_html( $stats['medium_count'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Medium', 'saga-manager-theme' ); ?></span>
			</div>
			<div class="stat-item stat-low">
				<span class="stat-count"><?php echo esc_html( $stats['low_count'] ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Low', 'saga-manager-theme' ); ?></span>
			</div>
		</div>

		<!-- Summary -->
		<div class="saga-widget-summary">
			<p>
				<strong><?php echo esc_html( $stats['open_issues'] ); ?></strong>
				<?php esc_html_e( 'open issues', 'saga-manager-theme' ); ?>
				&middot;
				<strong><?php echo esc_html( $stats['resolved_issues'] ); ?></strong>
				<?php esc_html_e( 'resolved', 'saga-manager-theme' ); ?>
			</p>
		</div>

		<!-- Recent Issues -->
		<?php if ( ! empty( $recentIssues ) ) : ?>
			<div class="saga-widget-recent">
				<h4><?php esc_html_e( 'Recent Issues', 'saga-manager-theme' ); ?></h4>
				<ul>
					<?php foreach ( $recentIssues as $issue ) : ?>
						<li class="issue-item severity-<?php echo esc_attr( $issue->severity ); ?>">
							<span class="severity-badge <?php echo esc_attr( $issue->severity ); ?>">
								<?php echo esc_html( $issue->getSeverityLabel() ); ?>
							</span>
							<span class="issue-description">
								<?php echo esc_html( wp_trim_words( $issue->description, 10 ) ); ?>
							</span>
							<span class="issue-time">
								<?php echo esc_html( human_time_diff( strtotime( $issue->detectedAt ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'saga-manager-theme' ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php else : ?>
			<div class="saga-widget-empty">
				<span class="dashicons dashicons-yes-alt"></span>
				<p><?php esc_html_e( 'No consistency issues found! Your saga looks great.', 'saga-manager-theme' ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Action Links -->
		<div class="saga-widget-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=saga-consistency-guardian' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'View All Issues', 'saga-manager-theme' ); ?>
			</a>
			<a href="#" class="button saga-run-quick-scan" data-saga-id="<?php echo esc_attr( $currentSagaId ); ?>">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Run Quick Scan', 'saga-manager-theme' ); ?>
			</a>
		</div>
	</div>

	<style>
		.saga-consistency-widget {
			padding: 0;
		}

		.saga-widget-stats {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 10px;
			margin-bottom: 15px;
		}

		.stat-item {
			text-align: center;
			padding: 10px;
			border-radius: 4px;
			background: #f6f7f7;
		}

		.stat-item.stat-critical {
			background: #fee;
			border-left: 3px solid #dc2626;
		}

		.stat-item.stat-high {
			background: #ffedd5;
			border-left: 3px solid #ea580c;
		}

		.stat-item.stat-medium {
			background: #fef9c3;
			border-left: 3px solid #ca8a04;
		}

		.stat-item.stat-low {
			background: #dbeafe;
			border-left: 3px solid #2563eb;
		}

		.stat-count {
			display: block;
			font-size: 24px;
			font-weight: 600;
			line-height: 1;
			margin-bottom: 5px;
		}

		.stat-label {
			display: block;
			font-size: 11px;
			text-transform: uppercase;
			color: #646970;
		}

		.saga-widget-summary {
			padding: 10px 0;
			border-top: 1px solid #dcdcde;
			border-bottom: 1px solid #dcdcde;
			margin-bottom: 15px;
			text-align: center;
		}

		.saga-widget-summary p {
			margin: 0;
		}

		.saga-widget-recent h4 {
			margin: 0 0 10px 0;
			font-size: 13px;
			font-weight: 600;
		}

		.saga-widget-recent ul {
			margin: 0;
			padding: 0;
			list-style: none;
		}

		.issue-item {
			padding: 8px 0;
			border-bottom: 1px solid #f0f0f1;
			display: flex;
			align-items: flex-start;
			gap: 8px;
			font-size: 12px;
		}

		.issue-item:last-child {
			border-bottom: none;
		}

		.severity-badge {
			display: inline-block;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 10px;
			font-weight: 600;
			text-transform: uppercase;
			flex-shrink: 0;
		}

		.severity-badge.critical {
			background: #dc2626;
			color: white;
		}

		.severity-badge.high {
			background: #ea580c;
			color: white;
		}

		.severity-badge.medium {
			background: #ca8a04;
			color: white;
		}

		.severity-badge.low {
			background: #2563eb;
			color: white;
		}

		.severity-badge.info {
			background: #6b7280;
			color: white;
		}

		.issue-description {
			flex: 1;
			line-height: 1.4;
		}

		.issue-time {
			color: #646970;
			font-size: 11px;
			white-space: nowrap;
		}

		.saga-widget-empty {
			text-align: center;
			padding: 30px 0;
			color: #50575e;
		}

		.saga-widget-empty .dashicons {
			font-size: 48px;
			width: 48px;
			height: 48px;
			color: #00a32a;
		}

		.saga-widget-actions {
			margin-top: 15px;
			padding-top: 15px;
			border-top: 1px solid #dcdcde;
			display: flex;
			gap: 10px;
		}

		.saga-widget-actions .button {
			flex: 1;
		}

		.saga-run-quick-scan .dashicons {
			margin-top: 3px;
		}
	</style>

	<script>
	jQuery(document).ready(function($) {
		$('.saga-run-quick-scan').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var sagaId = $btn.data('saga-id');

			if ($btn.hasClass('running')) {
				return;
			}

			$btn.addClass('running').prop('disabled', true);
			$btn.find('.dashicons').addClass('dashicons-update-alt');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'saga_run_consistency_scan',
					saga_id: sagaId,
					use_ai: false,
					nonce: '<?php echo esc_js( wp_create_nonce( 'saga_consistency_nonce' ) ); ?>'
				},
				success: function(response) {
					if (response.success) {
						// Reload dashboard widget
						location.reload();
					} else {
						alert(response.data.message || 'Scan failed');
					}
				},
				error: function() {
					alert('<?php esc_html_e( 'Network error. Please try again.', 'saga-manager-theme' ); ?>');
				},
				complete: function() {
					$btn.removeClass('running').prop('disabled', false);
					$btn.find('.dashicons').removeClass('dashicons-update-alt');
				}
			});
		});
	});
	</script>
	<?php
}
