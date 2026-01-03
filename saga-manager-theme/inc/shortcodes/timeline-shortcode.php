<?php
/**
 * Timeline Shortcode Handler
 * Registers and handles the [saga_timeline] shortcode
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Shortcodes;

class TimelineShortcode {

	/**
	 * Register shortcode
	 */
	public static function register(): void {
		add_shortcode( 'saga_timeline', array( self::class, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueueAssets' ) );
	}

	/**
	 * Enqueue timeline assets
	 */
	public static function enqueueAssets(): void {
		// Only enqueue if shortcode is present
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'saga_timeline' ) ) {
			return;
		}

		$theme_uri     = get_template_directory_uri();
		$theme_version = wp_get_theme()->get( 'Version' );

		// Enqueue WebGPU timeline script
		wp_enqueue_script(
			'saga-webgpu-timeline',
			$theme_uri . '/assets/js/webgpu-timeline.js',
			array(),
			$theme_version,
			true
		);

		// Enqueue timeline controls
		wp_enqueue_script(
			'saga-timeline-controls',
			$theme_uri . '/assets/js/timeline-controls.js',
			array( 'saga-webgpu-timeline' ),
			$theme_version,
			true
		);

		// Enqueue timeline styles
		wp_enqueue_style(
			'saga-timeline-styles',
			$theme_uri . '/assets/css/webgpu-timeline.css',
			array(),
			$theme_version
		);

		// Localize script with AJAX data
		wp_localize_script(
			'saga-webgpu-timeline',
			'sagaTimelineAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'saga_timeline_nonce' ),
			)
		);
	}

	/**
	 * Render timeline shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render( array $atts = array() ): string {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'saga_id'       => '',
				'width'         => '100%',
				'height'        => '600px',
				'theme'         => 'dark',
				'show_controls' => 'true',
				'show_minimap'  => 'true',
				'initial_zoom'  => '1',
				'min_zoom'      => '0.0001',
				'max_zoom'      => '1000',
			),
			$atts,
			'saga_timeline'
		);

		// Validate saga ID
		if ( empty( $atts['saga_id'] ) ) {
			return '<div class="saga-timeline-error">Error: saga_id is required</div>';
		}

		$saga_id = absint( $atts['saga_id'] );

		// Verify saga exists
		global $wpdb;
		$saga_table = $wpdb->prefix . 'saga_sagas';
		$saga       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, universe, calendar_type, calendar_config FROM {$saga_table} WHERE id = %d",
				$saga_id
			)
		);

		if ( ! $saga ) {
			return '<div class="saga-timeline-error">Error: Saga not found</div>';
		}

		// Generate unique ID for this timeline instance
		$timeline_id = 'saga-timeline-' . uniqid();

		// Prepare JavaScript configuration
		$config = array(
			'sagaId'         => $saga_id,
			'width'          => self::parseDimension( $atts['width'] ),
			'height'         => self::parseDimension( $atts['height'] ),
			'theme'          => sanitize_key( $atts['theme'] ),
			'showControls'   => filter_var( $atts['show_controls'], FILTER_VALIDATE_BOOLEAN ),
			'showMinimap'    => filter_var( $atts['show_minimap'], FILTER_VALIDATE_BOOLEAN ),
			'initialZoom'    => floatval( $atts['initial_zoom'] ),
			'minZoom'        => floatval( $atts['min_zoom'] ),
			'maxZoom'        => floatval( $atts['max_zoom'] ),
			'calendarType'   => $saga->calendar_type,
			'calendarConfig' => json_decode( $saga->calendar_config, true ),
		);

		// Apply theme colors
		if ( $atts['theme'] === 'light' ) {
			$config['backgroundColor'] = '#f5f5f5';
			$config['gridColor']       = '#e0e0e0';
			$config['eventColor']      = '#2196f3';
			$config['accentColor']     = '#f44336';
		}

		// Build HTML output
		ob_start();
		?>
		<div id="<?php echo esc_attr( $timeline_id ); ?>"
			class="saga-timeline-container saga-timeline-theme-<?php echo esc_attr( $atts['theme'] ); ?>"
			style="width: <?php echo esc_attr( $atts['width'] ); ?>; height: <?php echo esc_attr( $atts['height'] ); ?>;"
			data-saga-id="<?php echo esc_attr( $saga_id ); ?>"
			role="region"
			aria-label="Interactive timeline for <?php echo esc_attr( $saga->name ); ?>">

			<div class="saga-timeline-header">
				<h3 class="saga-timeline-title"><?php echo esc_html( $saga->name ); ?> Timeline</h3>
				<div class="saga-timeline-subtitle"><?php echo esc_html( $saga->universe ); ?></div>
			</div>

			<div class="saga-timeline-viewport" style="position: relative;">
				<!-- Timeline canvas will be inserted here by JavaScript -->
			</div>

			<div class="saga-timeline-loading" aria-live="polite">
				<div class="loading-spinner"></div>
				<div class="loading-text">Loading timeline data...</div>
			</div>

		</div>

		<script>
		(function() {
			'use strict';

			// Wait for DOM ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initTimeline);
			} else {
				initTimeline();
			}

			function initTimeline() {
				const container = document.getElementById('<?php echo esc_js( $timeline_id ); ?>');
				if (!container) return;

				const viewport = container.querySelector('.saga-timeline-viewport');
				const loading = container.querySelector('.saga-timeline-loading');

				const config = <?php echo wp_json_encode( $config ); ?>;

				// Initialize timeline
				try {
					const timeline = new WebGPUTimeline(viewport, config);

					// Initialize controls if enabled
					if (config.showControls) {
						const controls = new TimelineControls(timeline, container);
					}

					// Hide loading indicator when timeline is ready
					setTimeout(() => {
						loading.style.display = 'none';
					}, 1000);

					// Store timeline instance for external access
					container.timelineInstance = timeline;

				} catch (error) {
					console.error('Failed to initialize timeline:', error);
					loading.innerHTML = '<div class="saga-timeline-error">Failed to initialize timeline. Your browser may not support the required features.</div>';
				}
			}
		})();
		</script>
		<?php

		return ob_get_clean();
	}

	/**
	 * Parse dimension string (e.g., "100%", "600px", "600")
	 *
	 * @param string $dimension Dimension string
	 * @return string Normalized dimension
	 */
	private static function parseDimension( string $dimension ): string {
		$dimension = trim( $dimension );

		// If already has unit, return as-is
		if ( preg_match( '/^[\d.]+(%|px|em|rem|vh|vw)$/', $dimension ) ) {
			return $dimension;
		}

		// If numeric only, add px
		if ( is_numeric( $dimension ) ) {
			return $dimension . 'px';
		}

		// Default fallback
		return '100%';
	}
}

// Register shortcode
TimelineShortcode::register();
