<?php
declare(strict_types=1);

/**
 * WordPress Admin Integration for Relationship Suggestions
 *
 * Handles menu registration, asset enqueuing, and dashboard widgets.
 */

use SagaManager\AI\SuggestionBackgroundProcessor;
use SagaManager\AI\Services\RelationshipPredictionService;
use SagaManager\AI\Services\SuggestionRepository;
use SagaManager\AI\Services\FeatureExtractionService;

/**
 * Add admin menu for suggestions
 */
function saga_add_suggestions_menu(): void {
	add_submenu_page(
		'edit.php?post_type=saga_entity',  // Parent menu
		'Relationship Suggestions',         // Page title
		'AI Suggestions',                   // Menu title
		'edit_posts',                       // Capability
		'saga-suggestions',                 // Menu slug
		'saga_render_suggestions_page'     // Callback
	);
}
add_action( 'admin_menu', 'saga_add_suggestions_menu' );

/**
 * Render suggestions admin page
 */
function saga_render_suggestions_page(): void {
	// Load template
	include get_template_directory() . '/page-templates/admin-suggestions-page.php';
}

/**
 * Enqueue admin assets only on suggestions page
 */
function saga_enqueue_suggestions_assets( $hook ): void {
	// Only load on our suggestions page
	if ( $hook !== 'saga_entity_page_saga-suggestions' ) {
		return;
	}

	// Enqueue CSS
	wp_enqueue_style(
		'saga-suggestions-dashboard',
		get_template_directory_uri() . '/assets/css/suggestions-dashboard.css',
		array(),
		filemtime( get_template_directory() . '/assets/css/suggestions-dashboard.css' )
	);

	// Enqueue Chart.js for learning dashboard
	wp_enqueue_script(
		'chartjs',
		'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
		array(),
		'4.4.0',
		true
	);

	// Enqueue JavaScript
	wp_enqueue_script(
		'saga-suggestions-dashboard',
		get_template_directory_uri() . '/assets/js/suggestions-dashboard.js',
		array( 'jquery', 'chartjs' ),
		filemtime( get_template_directory() . '/assets/js/suggestions-dashboard.js' ),
		true
	);

	// Localize script with AJAX settings
	wp_localize_script(
		'saga-suggestions-dashboard',
		'sagaSuggestions',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'saga_suggestions_nonce' ),
			'strings'  => array(
				'confirmBulkAccept'         => __( 'Accept %d selected suggestions?', 'saga-manager' ),
				'confirmBulkReject'         => __( 'Reject %d selected suggestions?', 'saga-manager' ),
				'confirmResetLearning'      => __( 'Reset all learning weights? This cannot be undone.', 'saga-manager' ),
				'confirmCreateRelationship' => __( 'Create actual relationship from this suggestion?', 'saga-manager' ),
				'errorGeneric'              => __( 'An error occurred. Please try again.', 'saga-manager' ),
				'successAccept'             => __( 'Suggestion accepted', 'saga-manager' ),
				'successReject'             => __( 'Suggestion rejected', 'saga-manager' ),
				'successModify'             => __( 'Suggestion modified', 'saga-manager' ),
				'successGenerate'           => __( 'Generation started', 'saga-manager' ),
			),
			'settings' => array(
				'pollInterval'  => 2000,  // 2 seconds
				'debounceDelay' => 1000, // 1 second
				'perPage'       => 25,
			),
		)
	);

	// Enqueue WordPress admin styles
	wp_enqueue_style( 'dashicons' );
}
add_action( 'admin_enqueue_scripts', 'saga_enqueue_suggestions_assets' );

/**
 * Add dashboard widget for recent suggestions
 */
function saga_add_suggestions_dashboard_widget(): void {
	wp_add_dashboard_widget(
		'saga_suggestions_widget',
		'Recent AI Relationship Suggestions',
		'saga_render_suggestions_widget'
	);
}
add_action( 'wp_dashboard_setup', 'saga_add_suggestions_dashboard_widget' );

/**
 * Render dashboard widget content
 */
function saga_render_suggestions_widget(): void {
	global $wpdb;

	$suggestions_table = $wpdb->prefix . 'saga_relationship_suggestions';

	// Get recent pending suggestions with high confidence
	$suggestions = $wpdb->get_results(
		"SELECT s.*,
                e1.canonical_name as source_name,
                e2.canonical_name as target_name,
                sg.name as saga_name
        FROM {$suggestions_table} s
        JOIN {$wpdb->prefix}saga_entities e1 ON s.source_entity_id = e1.id
        JOIN {$wpdb->prefix}saga_entities e2 ON s.target_entity_id = e2.id
        JOIN {$wpdb->prefix}saga_sagas sg ON s.saga_id = sg.id
        WHERE s.status = 'pending'
        AND s.confidence_score >= 0.7
        ORDER BY s.priority_score DESC, s.created_at DESC
        LIMIT 5",
		ARRAY_A
	);

	if ( empty( $suggestions ) ) {
		echo '<p>No pending high-confidence suggestions.</p>';
		echo '<p><a href="' . admin_url( 'edit.php?post_type=saga_entity&page=saga-suggestions' ) . '">View All Suggestions</a></p>';
		return;
	}

	echo '<ul class="saga-suggestions-widget-list">';

	foreach ( $suggestions as $suggestion ) {
		$confidence_pct   = round( $suggestion['confidence_score'] * 100 );
		$confidence_class = $confidence_pct >= 80 ? 'high' : ( $confidence_pct >= 60 ? 'medium' : 'low' );

		echo '<li>';
		echo '<strong>' . esc_html( $suggestion['source_name'] ) . '</strong> → ';
		echo '<strong>' . esc_html( $suggestion['target_name'] ) . '</strong>';
		echo '<br>';
		echo '<span class="relationship-type">' . esc_html( $suggestion['suggested_type'] ) . '</span> ';
		echo '<span class="confidence-badge ' . $confidence_class . '">' . $confidence_pct . '%</span>';
		echo '<br>';
		echo '<small>Saga: ' . esc_html( $suggestion['saga_name'] ) . '</small>';
		echo '</li>';
	}

	echo '</ul>';

	echo '<p><a href="' . admin_url( 'edit.php?post_type=saga_entity&page=saga-suggestions' ) . '" class="button button-primary">Review All Suggestions</a></p>';

	// Add widget-specific CSS
	echo '<style>
        .saga-suggestions-widget-list { margin: 0; padding: 0; list-style: none; }
        .saga-suggestions-widget-list li { padding: 10px 0; border-bottom: 1px solid #eee; }
        .saga-suggestions-widget-list li:last-child { border-bottom: none; }
        .relationship-type {
            display: inline-block;
            padding: 2px 8px;
            background: #f0f0f1;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .confidence-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        .confidence-badge.high { background: #00a32a; }
        .confidence-badge.medium { background: #dba617; }
        .confidence-badge.low { background: #d63638; }
    </style>';
}

/**
 * Initialize background processor with WordPress Cron
 */
function saga_init_suggestions_background_processor(): void {
	global $wpdb;

	$repository          = new SuggestionRepository( $wpdb );
	$featureService      = new FeatureExtractionService( $wpdb );
	$predictionService   = new RelationshipPredictionService( $featureService, $repository );
	$backgroundProcessor = new SuggestionBackgroundProcessor( $predictionService, $repository );

	// Initialize cron hooks
	$backgroundProcessor->init();
}
add_action( 'init', 'saga_init_suggestions_background_processor' );

/**
 * Add admin notice for pending suggestions
 */
function saga_suggestions_admin_notice(): void {
	// Only show on admin pages
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'saga_entity', 'saga' ) ) ) {
		return;
	}

	global $wpdb;

	$pending_count = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}saga_relationship_suggestions
        WHERE status = 'pending' AND confidence_score >= 0.7"
	);

	if ( $pending_count > 0 ) {
		echo '<div class="notice notice-info is-dismissible">';
		echo '<p>';
		printf(
			__( 'You have %d high-confidence relationship suggestions waiting for review. ', 'saga-manager' ),
			$pending_count
		);
		echo '<a href="' . admin_url( 'edit.php?post_type=saga_entity&page=saga-suggestions' ) . '">';
		echo __( 'Review suggestions', 'saga-manager' );
		echo '</a>';
		echo '</p>';
		echo '</div>';
	}
}
add_action( 'admin_notices', 'saga_suggestions_admin_notice' );

/**
 * Add custom admin columns for entities showing suggestion count
 */
function saga_add_entity_suggestion_column( $columns ): array {
	$columns['suggestions'] = 'AI Suggestions';
	return $columns;
}
add_filter( 'manage_saga_entity_posts_columns', 'saga_add_entity_suggestion_column' );

/**
 * Populate suggestion count column
 */
function saga_populate_entity_suggestion_column( $column, $post_id ): void {
	if ( $column !== 'suggestions' ) {
		return;
	}

	global $wpdb;

	// Get entity ID from post meta
	$entity_id = get_post_meta( $post_id, 'saga_entity_id', true );

	if ( ! $entity_id ) {
		echo '--';
		return;
	}

	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}saga_relationship_suggestions
        WHERE (source_entity_id = %d OR target_entity_id = %d)
        AND status = 'pending'",
			$entity_id,
			$entity_id
		)
	);

	if ( $count > 0 ) {
		echo '<span class="pending-suggestions-badge">' . absint( $count ) . '</span>';
	} else {
		echo '--';
	}
}
add_action( 'manage_saga_entity_posts_custom_column', 'saga_populate_entity_suggestion_column', 10, 2 );

/**
 * Add help tab to suggestions page
 */
function saga_add_suggestions_help_tab(): void {
	$screen = get_current_screen();

	if ( $screen->id !== 'saga_entity_page_saga-suggestions' ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'saga_suggestions_overview',
			'title'   => 'Overview',
			'content' => '
            <h3>AI Relationship Suggestions</h3>
            <p>This page uses machine learning to suggest potential relationships between entities in your saga.</p>
            <ul>
                <li><strong>Generate Suggestions:</strong> Start a background job to analyze all entity pairs</li>
                <li><strong>Review Suggestions:</strong> Accept, reject, or modify suggested relationships</li>
                <li><strong>Learning:</strong> The AI improves over time based on your feedback</li>
            </ul>
        ',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'saga_suggestions_confidence',
			'title'   => 'Confidence Scores',
			'content' => '
            <h3>Understanding Confidence Scores</h3>
            <p>Each suggestion has a confidence score indicating the AI\'s certainty:</p>
            <ul>
                <li><strong>High (≥80%):</strong> Very likely to be accurate</li>
                <li><strong>Medium (60-80%):</strong> Moderately confident</li>
                <li><strong>Low (<60%):</strong> Uncertain, review carefully</li>
            </ul>
        ',
		)
	);

	$screen->add_help_tab(
		array(
			'id'      => 'saga_suggestions_learning',
			'title'   => 'Learning System',
			'content' => '
            <h3>How Learning Works</h3>
            <p>The AI learns from your feedback to improve future suggestions:</p>
            <ul>
                <li><strong>Accept:</strong> Positive signal - similar patterns will be ranked higher</li>
                <li><strong>Reject:</strong> Negative signal - similar patterns will be ranked lower</li>
                <li><strong>Modify:</strong> Teaches the AI the correct relationship type/strength</li>
            </ul>
            <p>Learning weights update automatically after every 10 feedback actions.</p>
        ',
		)
	);
}
add_action( 'admin_head', 'saga_add_suggestions_help_tab' );
