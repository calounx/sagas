<?php
/**
 * Entity Extraction Admin Initialization
 *
 * Registers admin menu, enqueues assets, and sets up admin dashboard widgets.
 *
 * @package SagaManager
 * @subpackage Admin\Extraction
 * @since 1.4.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menu for Entity Extractor
 */
add_action(
	'admin_menu',
	function () {
		// Create main Saga Manager menu if it doesn't exist
		if ( ! menu_page_url( 'saga-manager', false ) ) {
			add_menu_page(
				__( 'Saga Manager', 'saga-manager-theme' ),
				__( 'Saga Manager', 'saga-manager-theme' ),
				'edit_posts',
				'saga-manager',
				'__return_null', // No callback for parent
				'dashicons-book-alt',
				25
			);
		}

		// Add Entity Extractor submenu
		add_submenu_page(
			'saga-manager',
			__( 'Entity Extractor', 'saga-manager-theme' ),
			__( 'Entity Extractor', 'saga-manager-theme' ),
			'edit_posts',
			'saga-entity-extractor',
			'saga_render_extraction_page',
			30
		);
	},
	20
);

/**
 * Render extraction admin page
 */
function saga_render_extraction_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Unauthorized' );
	}

	// Load template
	$template_path = get_template_directory() . '/page-templates/admin-extraction-page.php';

	if ( file_exists( $template_path ) ) {
		require $template_path;
	} else {
		echo '<div class="wrap"><h1>Entity Extractor</h1><p>Template not found.</p></div>';
	}
}

/**
 * Enqueue admin assets for extraction page
 */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only load on extractor page
		if ( $hook !== 'saga-manager_page_saga-entity-extractor' ) {
			return;
		}

		$theme_uri = get_template_directory_uri();
		$theme_dir = get_template_directory();

		// Enqueue CSS
		$css_path = '/assets/css/extraction-dashboard.css';
		if ( file_exists( $theme_dir . $css_path ) ) {
			wp_enqueue_style(
				'saga-extraction-dashboard',
				$theme_uri . $css_path,
				array(),
				filemtime( $theme_dir . $css_path )
			);
		}

		// Enqueue JS
		$js_path = '/assets/js/extraction-dashboard.js';
		if ( file_exists( $theme_dir . $js_path ) ) {
			wp_enqueue_script(
				'saga-extraction-dashboard',
				$theme_uri . $js_path,
				array( 'jquery' ),
				filemtime( $theme_dir . $js_path ),
				true
			);

			// Localize script with settings
			wp_localize_script(
				'saga-extraction-dashboard',
				'sagaExtraction',
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'saga_extraction_nonce' ),
					'settings' => array(
						'maxTextLength'   => 100000,
						'pollInterval'    => 2000, // 2 seconds
						'entitiesPerPage' => 25,
						'debounceDelay'   => 1000, // 1 second
					),
					'i18n'     => array(
						'confirmCancel'      => __( 'Are you sure you want to cancel this extraction job?', 'saga-manager' ),
						'confirmReject'      => __( 'Reject this entity?', 'saga-manager' ),
						'confirmBulkApprove' => __( 'Approve selected entities?', 'saga-manager' ),
						'confirmBulkReject'  => __( 'Reject selected entities?', 'saga-manager' ),
						'confirmCreate'      => __( 'Create approved entities as permanent saga entities?', 'saga-manager' ),
						'noEntitiesSelected' => __( 'Please select at least one entity', 'saga-manager' ),
						'extractionStarted'  => __( 'Extraction started', 'saga-manager' ),
						'extractionFailed'   => __( 'Extraction failed', 'saga-manager' ),
						'entityApproved'     => __( 'Entity approved', 'saga-manager' ),
						'entityRejected'     => __( 'Entity rejected', 'saga-manager' ),
						'entitiesCreated'    => __( 'Entities created successfully', 'saga-manager' ),
						'jobCancelled'       => __( 'Job cancelled', 'saga-manager' ),
						'duplicateResolved'  => __( 'Duplicate resolved', 'saga-manager' ),
						'error'              => __( 'Error', 'saga-manager' ),
						'loading'            => __( 'Loading...', 'saga-manager' ),
					),
				)
			);
		}
	}
);

/**
 * Add dashboard widget for recent extraction jobs
 */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'saga_extraction_recent_jobs',
			'Entity Extraction - Recent Jobs',
			'saga_render_extraction_dashboard_widget'
		);
	}
);

/**
 * Render dashboard widget
 */
function saga_render_extraction_dashboard_widget() {
	try {
		$repository = new \SagaManager\AI\EntityExtractor\ExtractionRepository();

		// Get recent jobs (last 5)
		global $wpdb;
		$jobs_table = $wpdb->prefix . 'saga_extraction_jobs';

		$query = "SELECT * FROM {$jobs_table} ORDER BY created_at DESC LIMIT 5";
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $rows ) ) {
			echo '<p>No extraction jobs yet.</p>';
			echo '<p><a href="' . admin_url( 'admin.php?page=saga-entity-extractor' ) . '" class="button">Start Extraction</a></p>';
			return;
		}

		echo '<table class="widefat" style="margin-top: 10px;">';
		echo '<thead><tr>';
		echo '<th>Job ID</th>';
		echo '<th>Status</th>';
		echo '<th>Entities</th>';
		echo '<th>Created</th>';
		echo '<th>Date</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $rows as $row ) {
			$job = \SagaManager\AI\EntityExtractor\Entities\ExtractionJob::fromArray( $row );

			$status_colors = array(
				'pending'    => '#999',
				'processing' => '#0073aa',
				'completed'  => '#46b450',
				'failed'     => '#dc3232',
				'cancelled'  => '#999',
			);

			$status_color = $status_colors[ $job->status->value ] ?? '#999';

			echo '<tr>';
			echo '<td><strong>#' . esc_html( $job->id ) . '</strong></td>';
			echo '<td><span style="color: ' . esc_attr( $status_color ) . ';">' . esc_html( $job->status->value ) . '</span></td>';
			echo '<td>' . esc_html( $job->total_entities_found ) . '</td>';
			echo '<td>' . esc_html( $job->entities_created ) . '</td>';
			echo '<td>' . esc_html( human_time_diff( $job->created_at, time() ) ) . ' ago</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		echo '<p style="margin-top: 10px;">';
		echo '<a href="' . admin_url( 'admin.php?page=saga-entity-extractor' ) . '" class="button">View All Jobs</a>';
		echo '</p>';

	} catch ( \Exception $e ) {
		echo '<p style="color: #dc3232;">Error loading extraction jobs: ' . esc_html( $e->getMessage() ) . '</p>';
	}
}

/**
 * Add admin notice if extraction tables don't exist
 */
add_action(
	'admin_notices',
	function () {
		global $wpdb;

		$jobs_table = $wpdb->prefix . 'saga_extraction_jobs';

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$jobs_table}'" ) === $jobs_table;

		if ( ! $table_exists && current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-warning">';
			echo '<p><strong>Saga Manager:</strong> Entity Extraction tables not found. ';
			echo 'Please activate the Entity Extraction database schema.</p>';
			echo '</div>';
		}
	}
);

/**
 * Register AJAX handlers
 */
require_once get_template_directory() . '/inc/ajax/extraction-ajax.php';
