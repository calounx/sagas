<?php
/**
 * 3D Semantic Galaxy Shortcode
 *
 * Renders the 3D galaxy visualization via [saga_galaxy] shortcode.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register galaxy shortcode
 */
function saga_register_galaxy_shortcode() {
	add_shortcode( 'saga_galaxy', 'saga_galaxy_shortcode_handler' );
}
add_action( 'init', 'saga_register_galaxy_shortcode' );

/**
 * Galaxy shortcode handler
 *
 * Usage: [saga_galaxy saga_id="1" height="600" auto_rotate="false"]
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function saga_galaxy_shortcode_handler( $atts ) {
	// Parse attributes with defaults
	$atts = shortcode_atts(
		array(
			'saga_id'        => 1,
			'height'         => 600,
			'auto_rotate'    => 'false',
			'show_controls'  => 'true',
			'show_minimap'   => 'true',
			'theme'          => 'auto', // auto, dark, light
			'particle_count' => 1000,
			'node_min_size'  => 2,
			'node_max_size'  => 15,
			'link_opacity'   => 0.4,
			'force_strength' => 0.02,
		),
		$atts,
		'saga_galaxy'
	);

	// Sanitize inputs
	$saga_id        = absint( $atts['saga_id'] );
	$height         = absint( $atts['height'] );
	$auto_rotate    = filter_var( $atts['auto_rotate'], FILTER_VALIDATE_BOOLEAN );
	$show_controls  = filter_var( $atts['show_controls'], FILTER_VALIDATE_BOOLEAN );
	$show_minimap   = filter_var( $atts['show_minimap'], FILTER_VALIDATE_BOOLEAN );
	$theme          = sanitize_key( $atts['theme'] );
	$particle_count = absint( $atts['particle_count'] );
	$node_min_size  = floatval( $atts['node_min_size'] );
	$node_max_size  = floatval( $atts['node_max_size'] );
	$link_opacity   = floatval( $atts['link_opacity'] );
	$force_strength = floatval( $atts['force_strength'] );

	// Validate saga exists
	$saga = get_post( $saga_id );
	if ( ! $saga || $saga->post_type !== 'saga' ) {
		return '<div class="saga-galaxy-error">Invalid saga ID.</div>';
	}

	// Generate unique ID for this instance
	$instance_id = 'saga-galaxy-' . uniqid();

	// Enqueue assets
	saga_enqueue_galaxy_assets();

	// Build data attributes
	$data_attrs = array(
		'data-saga-id'        => $saga_id,
		'data-height'         => $height,
		'data-auto-rotate'    => $auto_rotate ? 'true' : 'false',
		'data-particle-count' => $particle_count,
		'data-node-min-size'  => $node_min_size,
		'data-node-max-size'  => $node_max_size,
		'data-link-opacity'   => $link_opacity,
		'data-force-strength' => $force_strength,
	);

	$data_attrs_str = implode(
		' ',
		array_map(
			function ( $key, $value ) {
				return sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			},
			array_keys( $data_attrs ),
			$data_attrs
		)
	);

	// Determine theme class
	$theme_class = '';
	if ( $theme === 'dark' ) {
		$theme_class = ' data-theme="dark"';
	} elseif ( $theme === 'light' ) {
		$theme_class = ' data-theme="light"';
	}

	// Start output buffering
	ob_start();
	?>

	<div class="saga-galaxy-wrapper" id="<?php echo esc_attr( $instance_id ); ?>"<?php echo $theme_class; ?>>

		<!-- Loading State -->
		<div class="saga-galaxy-loading">
			<div class="saga-galaxy-spinner"></div>
			<p>Loading galaxy visualization...</p>
		</div>

		<!-- Main Canvas Container -->
		<div class="saga-galaxy-container"
			<?php echo $data_attrs_str; ?>
			role="application"
			aria-label="3D Semantic Galaxy Visualization"
			tabindex="0">
		</div>

		<?php if ( $show_controls ) : ?>
			<!-- Controls Panel -->
			<?php echo saga_galaxy_get_controls_template( $saga_id ); ?>
		<?php endif; ?>

		<?php if ( $show_minimap ) : ?>
			<!-- Minimap -->
			<div class="saga-galaxy-minimap" aria-hidden="true">
				<canvas></canvas>
			</div>
		<?php endif; ?>

		<!-- Details Panel (initially hidden) -->
		<div class="saga-galaxy-details" role="region" aria-live="polite" aria-label="Selected entity details">
			<div class="saga-galaxy-details-header">
				<div>
					<h4 class="saga-galaxy-details-title"></h4>
					<span class="saga-galaxy-details-type"></span>
				</div>
				<button class="saga-galaxy-details-close"
						aria-label="Close details"
						title="Close (Esc)">×</button>
			</div>
			<div class="saga-galaxy-details-content"></div>
			<div class="saga-galaxy-details-meta"></div>
			<div class="saga-galaxy-details-actions"></div>
		</div>

		<!-- Tooltip -->
		<div class="saga-galaxy-tooltip" role="tooltip" aria-hidden="true"></div>

		<!-- Performance Monitor (hidden by default) -->
		<div class="saga-galaxy-perf" aria-live="polite" aria-atomic="true">
			FPS: <span class="saga-galaxy-perf-fps">0</span> |
			Nodes: <span class="saga-galaxy-perf-nodes">0</span> |
			Links: <span class="saga-galaxy-perf-links">0</span>
		</div>

		<!-- Keyboard Shortcuts Help -->
		<div class="saga-galaxy-shortcuts" role="dialog" aria-label="Keyboard shortcuts">
			<h4>Keyboard Shortcuts</h4>
			<ul class="saga-galaxy-shortcut-list">
				<li class="saga-galaxy-shortcut-item">
					<span>Reset view</span>
					<kbd class="saga-galaxy-shortcut-key">R</kbd>
				</li>
				<li class="saga-galaxy-shortcut-item">
					<span>Toggle auto-rotate</span>
					<kbd class="saga-galaxy-shortcut-key">A</kbd>
				</li>
				<li class="saga-galaxy-shortcut-item">
					<span>Deselect node</span>
					<kbd class="saga-galaxy-shortcut-key">Esc</kbd>
				</li>
				<li class="saga-galaxy-shortcut-item">
					<span>Show shortcuts</span>
					<kbd class="saga-galaxy-shortcut-key">?</kbd>
				</li>
			</ul>
		</div>

		<!-- Screen reader announcements -->
		<div class="saga-galaxy-sr-only" role="status" aria-live="polite" aria-atomic="true"></div>

	</div>

	<script>
	(function() {
		// Initialize galaxy when Three.js is loaded
		function initGalaxy() {
			if (typeof THREE === 'undefined' || typeof SemanticGalaxy === 'undefined') {
				setTimeout(initGalaxy, 100);
				return;
			}

			const container = document.querySelector('#<?php echo esc_js( $instance_id ); ?> .saga-galaxy-container');
			const loadingEl = document.querySelector('#<?php echo esc_js( $instance_id ); ?> .saga-galaxy-loading');

			if (!container) return;

			// Hide loading state
			if (loadingEl) {
				loadingEl.style.display = 'none';
			}

			// Initialize galaxy
			const galaxy = new SemanticGalaxy(container, {
				sagaId: <?php echo absint( $saga_id ); ?>,
				height: <?php echo absint( $height ); ?>,
				autoRotate: <?php echo $auto_rotate ? 'true' : 'false'; ?>,
				particleCount: <?php echo absint( $particle_count ); ?>,
				nodeMinSize: <?php echo floatval( $node_min_size ); ?>,
				nodeMaxSize: <?php echo floatval( $node_max_size ); ?>,
				linkOpacity: <?php echo floatval( $link_opacity ); ?>,
				forceStrength: <?php echo floatval( $force_strength ); ?>
			});

			// Event handlers for UI
			setupGalaxyUI(galaxy, '<?php echo esc_js( $instance_id ); ?>');
		}

		// Setup UI event handlers
		function setupGalaxyUI(galaxy, wrapperId) {
			const wrapper = document.getElementById(wrapperId);
			if (!wrapper) return;

			const detailsPanel = wrapper.querySelector('.saga-galaxy-details');
			const perfMonitor = wrapper.querySelector('.saga-galaxy-perf');

			// Node selection event
			wrapper.addEventListener('galaxy:nodeSelect', function(e) {
				const node = e.detail.node;
				showNodeDetails(node, detailsPanel);
			});

			// Search functionality
			const searchInput = wrapper.querySelector('.saga-galaxy-search input');
			if (searchInput) {
				let searchTimeout;
				searchInput.addEventListener('input', function() {
					clearTimeout(searchTimeout);
					searchTimeout = setTimeout(() => {
						const query = this.value.trim();
						if (query) {
							galaxy.searchEntities(query);
						} else {
							galaxy.clearSearch();
						}
					}, 300);
				});
			}

			// Clear search
			const clearBtn = wrapper.querySelector('.saga-galaxy-search-clear');
			if (clearBtn && searchInput) {
				clearBtn.addEventListener('click', function() {
					searchInput.value = '';
					galaxy.clearSearch();
				});
			}

			// Filter buttons
			const filterBtns = wrapper.querySelectorAll('.saga-galaxy-filter-btn');
			filterBtns.forEach(btn => {
				btn.addEventListener('click', function() {
					this.classList.toggle('active');

					const activeTypes = Array.from(wrapper.querySelectorAll('.saga-galaxy-filter-btn.active'))
						.map(b => b.dataset.type);

					galaxy.filterByType(activeTypes);
				});
			});

			// Action buttons
			const resetBtn = wrapper.querySelector('[data-action="reset"]');
			if (resetBtn) {
				resetBtn.addEventListener('click', () => galaxy.resetView());
			}

			const autoRotateBtn = wrapper.querySelector('[data-action="auto-rotate"]');
			if (autoRotateBtn) {
				autoRotateBtn.addEventListener('click', function() {
					galaxy.controls.autoRotate = !galaxy.controls.autoRotate;
					this.classList.toggle('active');
				});
			}

			const perfToggleBtn = wrapper.querySelector('[data-action="toggle-perf"]');
			if (perfToggleBtn && perfMonitor) {
				perfToggleBtn.addEventListener('click', function() {
					perfMonitor.classList.toggle('visible');
				});
			}

			// Update performance stats
			if (perfMonitor) {
				setInterval(() => {
					const stats = galaxy.getStats();
					wrapper.querySelector('.saga-galaxy-perf-fps').textContent = Math.round(stats.fps);
					wrapper.querySelector('.saga-galaxy-perf-nodes').textContent = stats.nodeCount;
					wrapper.querySelector('.saga-galaxy-perf-links').textContent = stats.linkCount;
				}, 1000);
			}

			// Close details panel
			const closeBtn = wrapper.querySelector('.saga-galaxy-details-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function() {
					detailsPanel.classList.remove('visible');
					galaxy.deselectNode();
				});
			}
		}

		// Show node details
		function showNodeDetails(node, panel) {
			if (!panel) return;

			panel.querySelector('.saga-galaxy-details-title').textContent = node.name;
			panel.querySelector('.saga-galaxy-details-type').textContent = node.type;
			panel.querySelector('.saga-galaxy-details-content').textContent =
				node.description || 'No description available.';

			// Meta information
			const metaHTML = `
				<div class="saga-galaxy-details-meta-item">
					<span class="saga-galaxy-details-meta-label">Importance</span>
					<span class="saga-galaxy-details-meta-value">${node.importance || 50}/100</span>
				</div>
				<div class="saga-galaxy-details-meta-item">
					<span class="saga-galaxy-details-meta-label">Connections</span>
					<span class="saga-galaxy-details-meta-value">${node.connections || 0}</span>
				</div>
			`;
			panel.querySelector('.saga-galaxy-details-meta').innerHTML = metaHTML;

			// Action buttons
			const actionsHTML = `
				<button class="saga-galaxy-details-btn" onclick="window.location.href='${node.url || '#'}'">
					View Details
				</button>
			`;
			panel.querySelector('.saga-galaxy-details-actions').innerHTML = actionsHTML;

			panel.classList.add('visible');
		}

		// Start initialization
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initGalaxy);
		} else {
			initGalaxy();
		}
	})();
	</script>

	<?php
	return ob_get_clean();
}

/**
 * Enqueue galaxy assets
 */
function saga_enqueue_galaxy_assets() {
	static $enqueued = false;

	if ( $enqueued ) {
		return;
	}

	// Three.js library (CDN)
	wp_enqueue_script(
		'threejs',
		'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js',
		array(),
		'0.160.0',
		true
	);

	// Three.js OrbitControls
	wp_enqueue_script(
		'threejs-orbit-controls',
		'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/js/controls/OrbitControls.js',
		array( 'threejs' ),
		'0.160.0',
		true
	);

	// Galaxy visualization script
	wp_enqueue_script(
		'saga-galaxy',
		get_template_directory_uri() . '/assets/js/3d-galaxy.js',
		array( 'jquery', 'threejs', 'threejs-orbit-controls' ),
		'1.3.0',
		true
	);

	// Galaxy styles
	wp_enqueue_style(
		'saga-galaxy',
		get_template_directory_uri() . '/assets/css/3d-galaxy.css',
		array(),
		'1.3.0'
	);

	// Localize script with AJAX data
	wp_localize_script(
		'saga-galaxy',
		'sagaGalaxy',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'saga_galaxy_nonce' ),
			'i18n'    => array(
				'loading'           => __( 'Loading galaxy data...', 'saga-manager-theme' ),
				'error'             => __( 'Failed to load galaxy data.', 'saga-manager-theme' ),
				'noData'            => __( 'No entities found.', 'saga-manager-theme' ),
				'searchPlaceholder' => __( 'Search entities...', 'saga-manager-theme' ),
			),
		)
	);

	$enqueued = true;
}

/**
 * Get controls template HTML
 *
 * @param int $saga_id Saga post ID
 * @return string HTML
 */
function saga_galaxy_get_controls_template( $saga_id ) {
	ob_start();

	// Load template part if exists
	$template_path = get_template_directory() . '/template-parts/galaxy-controls.php';

	if ( file_exists( $template_path ) ) {
		include $template_path;
	} else {
		// Fallback inline template
		?>
		<div class="saga-galaxy-controls" role="region" aria-label="Galaxy controls">
			<h3>Controls</h3>

			<!-- Search -->
			<div class="saga-galaxy-search">
				<input type="text"
						placeholder="Search entities..."
						aria-label="Search entities">
				<button class="saga-galaxy-search-clear"
						aria-label="Clear search"
						title="Clear">×</button>
			</div>

			<!-- Filters -->
			<div class="saga-galaxy-filters">
				<label>Entity Types</label>
				<div class="saga-galaxy-filter-buttons">
					<button class="saga-galaxy-filter-btn active" data-type="character">Character</button>
					<button class="saga-galaxy-filter-btn active" data-type="location">Location</button>
					<button class="saga-galaxy-filter-btn active" data-type="event">Event</button>
					<button class="saga-galaxy-filter-btn active" data-type="faction">Faction</button>
					<button class="saga-galaxy-filter-btn active" data-type="artifact">Artifact</button>
					<button class="saga-galaxy-filter-btn active" data-type="concept">Concept</button>
				</div>
			</div>

			<!-- Actions -->
			<div class="saga-galaxy-actions">
				<button class="saga-galaxy-btn" data-action="reset">
					<span class="saga-galaxy-btn-icon">⟲</span>
					Reset View
				</button>
				<button class="saga-galaxy-btn" data-action="auto-rotate">
					<span class="saga-galaxy-btn-icon">↻</span>
					Auto-Rotate
				</button>
				<button class="saga-galaxy-btn" data-action="toggle-perf">
					<span class="saga-galaxy-btn-icon">📊</span>
					Performance
				</button>
			</div>

			<!-- Info -->
			<div class="saga-galaxy-info">
				<div class="saga-galaxy-info-row">
					<span class="saga-galaxy-info-label">Nodes:</span>
					<span class="saga-galaxy-info-value">0</span>
				</div>
				<div class="saga-galaxy-info-row">
					<span class="saga-galaxy-info-label">Links:</span>
					<span class="saga-galaxy-info-value">0</span>
				</div>
			</div>
		</div>
		<?php
	}

	return ob_get_clean();
}
