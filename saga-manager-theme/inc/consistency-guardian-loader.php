<?php
/**
 * AI Consistency Guardian Loader
 *
 * Main loader file that initializes all consistency guardian components
 * Should be included in functions.php
 *
 * @package SagaManager
 * @version 1.4.0
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Consistency Guardian Components
 */
function saga_load_consistency_guardian(): void {
	$themeDir = get_template_directory();

	// Load backend components (AI, Repository, etc.)
	require_once $themeDir . '/inc/ai/entities/ConsistencyIssue.php';
	require_once $themeDir . '/inc/ai/ConsistencyRepository.php';
	require_once $themeDir . '/inc/ai/ConsistencyRuleEngine.php';
	require_once $themeDir . '/inc/ai/AIClient.php';
	require_once $themeDir . '/inc/ai/ConsistencyAnalyzer.php';

	// Load AJAX handlers
	require_once $themeDir . '/inc/ajax/consistency-ajax.php';

	// Load admin components (only in admin)
	if ( is_admin() ) {
		require_once $themeDir . '/inc/admin/consistency-dashboard-widget.php';
		require_once $themeDir . '/inc/admin/consistency-admin-init.php';
	}

	// Initialize database migration if needed
	saga_consistency_check_database();
}
add_action( 'after_setup_theme', 'saga_load_consistency_guardian' );

/**
 * Check and create database tables if needed
 */
function saga_consistency_check_database(): void {
	$versionKey       = 'saga_consistency_db_version';
	$currentVersion   = '1.4.0';
	$installedVersion = get_option( $versionKey );

	if ( $installedVersion !== $currentVersion ) {
		saga_consistency_create_tables();
		update_option( $versionKey, $currentVersion );

		error_log( '[SAGA][AI] Consistency Guardian database tables created/updated to version ' . $currentVersion );
	}
}

/**
 * Create database tables
 */
function saga_consistency_create_tables(): void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$tableName       = $wpdb->prefix . 'saga_consistency_issues';

	$sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        saga_id INT UNSIGNED NOT NULL,
        issue_type VARCHAR(50) NOT NULL,
        severity ENUM('critical', 'high', 'medium', 'low', 'info') NOT NULL,
        entity_id BIGINT UNSIGNED NULL,
        related_entity_id BIGINT UNSIGNED NULL,
        description TEXT NOT NULL,
        context JSON NULL,
        suggested_fix TEXT NULL,
        status ENUM('open', 'resolved', 'dismissed', 'false_positive') NOT NULL DEFAULT 'open',
        detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        resolved_by BIGINT UNSIGNED NULL,
        ai_confidence DECIMAL(3,2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_saga_status (saga_id, status),
        INDEX idx_severity (severity),
        INDEX idx_issue_type (issue_type),
        INDEX idx_entity (entity_id),
        INDEX idx_detected (detected_at)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Admin toolbar quick link
 */
function saga_consistency_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Get open issues count
	global $wpdb;
	$tableName = $wpdb->prefix . 'saga_consistency_issues';

	$openCount = wp_cache_get( 'saga_open_issues_count', 'saga' );

	if ( $openCount === false ) {
		$openCount = (int) $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$tableName}
            WHERE status = 'open'
        "
		);
		wp_cache_set( 'saga_open_issues_count', $openCount, 'saga', 300 );
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'saga-consistency',
			'title' => sprintf(
				'<span class="ab-icon dashicons dashicons-shield-alt"></span> <span class="ab-label">%s</span>',
				$openCount > 0 ? $openCount : ''
			),
			'href'  => admin_url( 'admin.php?page=saga-consistency-guardian' ),
			'meta'  => array(
				'title' => __( 'AI Consistency Guardian', 'saga-manager-theme' ),
			),
		)
	);

	if ( $openCount > 0 ) {
		$wp_admin_bar->add_node(
			array(
				'id'     => 'saga-consistency-issues',
				'parent' => 'saga-consistency',
				'title'  => sprintf(
					__( '%d open issues', 'saga-manager-theme' ),
					$openCount
				),
				'href'   => admin_url( 'admin.php?page=saga-consistency-guardian' ),
			)
		);
	}
}
add_action( 'admin_bar_menu', 'saga_consistency_admin_bar_menu', 100 );

/**
 * Add custom CSS for admin bar
 */
function saga_consistency_admin_bar_css(): void {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	?>
	<style>
		#wp-admin-bar-saga-consistency .ab-icon {
			font-size: 18px !important;
			margin-top: 4px;
		}

		#wp-admin-bar-saga-consistency .ab-label {
			background: #dc2626;
			color: white;
			border-radius: 10px;
			padding: 2px 6px;
			font-size: 11px;
			font-weight: 600;
			margin-left: 5px;
			vertical-align: middle;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'saga_consistency_admin_bar_css' );
add_action( 'admin_head', 'saga_consistency_admin_bar_css' );

/**
 * Activation hook - create tables
 */
function saga_consistency_guardian_activate(): void {
	saga_consistency_create_tables();
	flush_rewrite_rules();

	error_log( '[SAGA][AI] Consistency Guardian activated' );
}
register_activation_hook( __FILE__, 'saga_consistency_guardian_activate' );

/**
 * Deactivation hook - cleanup
 */
function saga_consistency_guardian_deactivate(): void {
	// Clear all transients
	global $wpdb;

	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_saga_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_saga_%'" );

	flush_rewrite_rules();

	error_log( '[SAGA][AI] Consistency Guardian deactivated' );
}
register_deactivation_hook( __FILE__, 'saga_consistency_guardian_deactivate' );

/**
 * Uninstall hook - remove data
 */
function saga_consistency_guardian_uninstall(): void {
	global $wpdb;

	// Only remove data if user confirms
	if ( get_option( 'saga_consistency_remove_data_on_uninstall', false ) ) {
		$tableName = $wpdb->prefix . 'saga_consistency_issues';
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );

		// Remove all options
		delete_option( 'saga_ai_consistency_enabled' );
		delete_option( 'saga_ai_api_key' );
		delete_option( 'saga_ai_model' );
		delete_option( 'saga_scan_schedule' );
		delete_option( 'saga_consistency_db_version' );
		delete_option( 'saga_consistency_remove_data_on_uninstall' );

		error_log( '[SAGA][AI] Consistency Guardian uninstalled - all data removed' );
	}
}
register_uninstall_hook( __FILE__, 'saga_consistency_guardian_uninstall' );

/**
 * Enqueue editor assets for real-time consistency checking
 */
function saga_consistency_enqueue_editor_assets(): void {
	global $post;

	// Only load on entity edit screens
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'saga_entity' ), true ) ) {
		return;
	}

	$entityId = $post->ID ?? 0;
	if ( $entityId === 0 ) {
		return;
	}

	$themeUri = get_template_directory_uri();
	$version  = '1.4.0';

	// Shared CSS
	wp_enqueue_style(
		'saga-consistency-editor',
		$themeUri . '/assets/css/consistency-editor.css',
		array(),
		$version
	);

	// Shared nonce and config
	$consistencyData = array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'saga_consistency_nonce' ),
		'entityId'     => $entityId,
		'dashboardUrl' => admin_url( 'admin.php?page=saga-consistency-guardian' ),
	);

	// Gutenberg (Block Editor)
	if ( $screen->is_block_editor() ) {
		// Real-time checker
		wp_enqueue_script(
			'saga-consistency-realtime',
			$themeUri . '/assets/js/consistency-realtime-checker.js',
			array( 'jquery', 'wp-data', 'wp-element' ),
			$version,
			true
		);

		// Gutenberg panel (React)
		wp_enqueue_script(
			'saga-consistency-gutenberg-panel',
			$themeUri . '/assets/js/consistency-gutenberg-panel.jsx',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			$version,
			true
		);

		// Consistency badge block
		wp_enqueue_script(
			'saga-consistency-badge-block',
			$themeUri . '/assets/js/blocks/consistency-badge.jsx',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n' ),
			$version,
			true
		);

		wp_localize_script( 'saga-consistency-realtime', 'sagaConsistency', $consistencyData );
	}
	// Classic Editor
	else {
		// Real-time checker
		wp_enqueue_script(
			'saga-consistency-realtime',
			$themeUri . '/assets/js/consistency-realtime-checker.js',
			array( 'jquery' ),
			$version,
			true
		);

		// Classic editor integration
		wp_enqueue_script(
			'saga-consistency-classic-editor',
			$themeUri . '/assets/js/consistency-classic-editor.js',
			array( 'jquery', 'saga-consistency-realtime' ),
			$version,
			true
		);

		wp_localize_script( 'saga-consistency-realtime', 'sagaConsistency', $consistencyData );
	}
}
add_action( 'admin_enqueue_scripts', 'saga_consistency_enqueue_editor_assets' );

/**
 * Register Gutenberg block category
 */
function saga_consistency_block_categories( $categories ): array {
	return array_merge(
		$categories,
		array(
			array(
				'slug'  => 'saga-manager',
				'title' => __( 'Saga Manager', 'saga-manager-theme' ),
				'icon'  => 'shield-alt',
			),
		)
	);
}
add_filter( 'block_categories_all', 'saga_consistency_block_categories', 10, 1 );

/**
 * Add REST API endpoints
 */
function saga_consistency_register_rest_routes(): void {
	register_rest_route(
		'saga/v1',
		'/consistency/scan',
		array(
			'methods'             => 'POST',
			'callback'            => 'saga_consistency_rest_scan',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'saga/v1',
		'/consistency/issues',
		array(
			'methods'             => 'GET',
			'callback'            => 'saga_consistency_rest_get_issues',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_rest_route(
		'saga/v1',
		'/consistency/issues/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'saga_consistency_rest_get_issue',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'rest_api_init', 'saga_consistency_register_rest_routes' );

/**
 * REST: Scan endpoint
 */
function saga_consistency_rest_scan( WP_REST_Request $request ): WP_REST_Response {
	$sagaId = absint( $request->get_param( 'saga_id' ) );

	if ( $sagaId === 0 ) {
		return new WP_REST_Response( array( 'error' => 'Invalid saga ID' ), 400 );
	}

	try {
		$repository = new SagaManager\AI\ConsistencyRepository();
		$ruleEngine = new SagaManager\AI\ConsistencyRuleEngine();
		$aiClient   = new SagaManager\AI\AIClient();
		$analyzer   = new SagaManager\AI\ConsistencyAnalyzer( $ruleEngine, $aiClient, $repository );

		$issues = $analyzer->analyze( $sagaId );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'issues_found' => count( $issues ),
			),
			200
		);

	} catch ( Exception $e ) {
		return new WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
	}
}

/**
 * REST: Get issues endpoint
 */
function saga_consistency_rest_get_issues( WP_REST_Request $request ): WP_REST_Response {
	$sagaId = absint( $request->get_param( 'saga_id' ) );
	$status = sanitize_key( $request->get_param( 'status' ) ?: 'open' );

	if ( $sagaId === 0 ) {
		return new WP_REST_Response( array( 'error' => 'Invalid saga ID' ), 400 );
	}

	$repository = new SagaManager\AI\ConsistencyRepository();
	$issues     = $repository->findBySaga( $sagaId, $status );

	$issueData = array_map(
		fn( $issue ) => array(
			'id'          => $issue->id,
			'type'        => $issue->issueType,
			'severity'    => $issue->severity,
			'description' => $issue->description,
			'status'      => $issue->status,
			'detected_at' => $issue->detectedAt,
		),
		$issues
	);

	return new WP_REST_Response(
		array(
			'issues' => $issueData,
			'total'  => count( $issueData ),
		),
		200
	);
}

/**
 * REST: Get single issue endpoint
 */
function saga_consistency_rest_get_issue( WP_REST_Request $request ): WP_REST_Response {
	$issueId = absint( $request->get_param( 'id' ) );

	$repository = new SagaManager\AI\ConsistencyRepository();
	$issue      = $repository->findById( $issueId );

	if ( $issue === null ) {
		return new WP_REST_Response( array( 'error' => 'Issue not found' ), 404 );
	}

	return new WP_REST_Response(
		array(
			'issue' => array(
				'id'            => $issue->id,
				'type'          => $issue->issueType,
				'severity'      => $issue->severity,
				'description'   => $issue->description,
				'suggested_fix' => $issue->suggestedFix,
				'status'        => $issue->status,
				'detected_at'   => $issue->detectedAt,
				'ai_confidence' => $issue->aiConfidence,
			),
		),
		200
	);
}
