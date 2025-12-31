<?php
declare(strict_types=1);

/**
 * Enhanced Relationship Graph Shortcode v2
 *
 * Provides [saga_graph_v2] shortcode with advanced features
 * D3 v7, multiple layouts, analytics, Web Worker support
 *
 * @package SagaManager
 * @since 1.3.0
 */

namespace SagaManager\Theme\Shortcode\GraphV2;

/**
 * Register enhanced graph shortcode
 */
function register_graph_v2_shortcode(): void {
    add_shortcode('saga_graph_v2', __NAMESPACE__ . '\\render_graph_v2_shortcode');
}
add_action('init', __NAMESPACE__ . '\\register_graph_v2_shortcode');

/**
 * Render enhanced graph shortcode
 *
 * Usage:
 * [saga_graph_v2 entity_id="123" layout="force"]
 * [saga_graph_v2 entity_type="character" layout="hierarchical"]
 * [saga_graph_v2 layout="radial" show_analytics="true" use_worker="true"]
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function render_graph_v2_shortcode(array $atts): string {
    // Parse attributes with defaults
    $atts = shortcode_atts([
        // Basic options (backward compatible)
        'entity_id' => 0,
        'depth' => 2,
        'entity_type' => '',
        'relationship_type' => '',
        'limit' => 100,
        'height' => 600,

        // v2 Layout options
        'layout' => 'force', // force, hierarchical, circular, radial, grid, clustered
        'hierarchical_orientation' => 'vertical', // vertical, horizontal
        'radius_step' => 100,
        'grid_columns' => 0, // auto
        'cluster_by' => 'type',

        // v2 Performance options
        'use_worker' => 'true',
        'use_canvas' => 'auto', // auto, true, false

        // v2 Feature toggles
        'show_analytics' => 'true',
        'show_temporal' => 'false',
        'show_minimap' => 'true',
        'show_legend' => 'true',
        'show_controls' => 'true',
        'enable_multi_select' => 'true',
        'enable_lasso' => 'true',

        // v2 Styling
        'animation_duration' => 750,
        'link_distance' => 100,
        'charge_strength' => -300,
        'collision_radius' => 30,

        // Advanced
        'container_id' => '',
        'custom_class' => '',
    ], $atts, 'saga_graph_v2');

    // Sanitize attributes
    $entity_id = absint($atts['entity_id']);
    $depth = min(absint($atts['depth']), 3);
    $entity_type = sanitize_key($atts['entity_type']);
    $relationship_type = sanitize_text_field($atts['relationship_type']);
    $limit = min(absint($atts['limit']), 1000);
    $height = absint($atts['height']);

    $layout = sanitize_key($atts['layout']);
    $hierarchical_orientation = sanitize_key($atts['hierarchical_orientation']);
    $radius_step = absint($atts['radius_step']);
    $grid_columns = absint($atts['grid_columns']);
    $cluster_by = sanitize_key($atts['cluster_by']);

    $use_worker = filter_var($atts['use_worker'], FILTER_VALIDATE_BOOLEAN);
    $use_canvas = $atts['use_canvas'] === 'auto' ? ($limit > 500) : filter_var($atts['use_canvas'], FILTER_VALIDATE_BOOLEAN);

    $show_analytics = filter_var($atts['show_analytics'], FILTER_VALIDATE_BOOLEAN);
    $show_temporal = filter_var($atts['show_temporal'], FILTER_VALIDATE_BOOLEAN);
    $show_minimap = filter_var($atts['show_minimap'], FILTER_VALIDATE_BOOLEAN);
    $show_legend = filter_var($atts['show_legend'], FILTER_VALIDATE_BOOLEAN);
    $show_controls = filter_var($atts['show_controls'], FILTER_VALIDATE_BOOLEAN);
    $enable_multi_select = filter_var($atts['enable_multi_select'], FILTER_VALIDATE_BOOLEAN);
    $enable_lasso = filter_var($atts['enable_lasso'], FILTER_VALIDATE_BOOLEAN);

    $animation_duration = absint($atts['animation_duration']);
    $link_distance = absint($atts['link_distance']);
    $charge_strength = intval($atts['charge_strength']);
    $collision_radius = absint($atts['collision_radius']);

    $container_id = $atts['container_id'] ?: 'saga-graph-v2-' . wp_rand(1000, 9999);
    $custom_class = sanitize_html_class($atts['custom_class']);

    // Validate entity type
    $valid_types = ['character', 'location', 'event', 'faction', 'artifact', 'concept'];
    if (!empty($entity_type) && !in_array($entity_type, $valid_types, true)) {
        return '<div class="saga-graph-error" role="alert">Invalid entity type specified.</div>';
    }

    // Validate layout
    $valid_layouts = ['force', 'hierarchical', 'circular', 'radial', 'grid', 'clustered'];
    if (!in_array($layout, $valid_layouts, true)) {
        $layout = 'force';
    }

    // If entity_id is provided, verify it exists
    if ($entity_id > 0) {
        global $wpdb;
        $entities_table = $wpdb->prefix . 'saga_entities';

        $entity_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$entities_table} WHERE id = %d",
            $entity_id
        ));

        if (!$entity_exists) {
            return '<div class="saga-graph-error" role="alert">Entity not found.</div>';
        }
    }

    // Enqueue assets
    enqueue_graph_v2_assets();

    // Build configuration object
    $config = [
        'entityId' => $entity_id,
        'depth' => $depth,
        'entityType' => $entity_type,
        'relationshipType' => $relationship_type,
        'limit' => $limit,
        'height' => $height,
        'layout' => $layout,
        'hierarchicalOrientation' => $hierarchical_orientation,
        'radiusStep' => $radius_step,
        'gridColumns' => $grid_columns,
        'clusterBy' => $cluster_by,
        'useWebWorker' => $use_worker,
        'useCanvas' => $use_canvas,
        'enableAnalytics' => $show_analytics,
        'enableTemporalPlayback' => $show_temporal,
        'animationDuration' => $animation_duration,
        'linkDistance' => $link_distance,
        'chargeStrength' => $charge_strength,
        'collisionRadius' => $collision_radius,
    ];

    // Start output buffering
    ob_start();
    ?>

    <div class="saga-graph-v2-wrapper <?php echo esc_attr($custom_class); ?>">
        <div id="<?php echo esc_attr($container_id); ?>" class="saga-graph-v2-container" style="height: <?php echo esc_attr($height); ?>px;">
            <!-- Graph will be rendered here -->
        </div>

        <?php if ($show_controls) : ?>
            <?php include locate_template('template-parts/graph-controls-v2.php'); ?>
        <?php endif; ?>

        <?php if ($show_legend) : ?>
            <div class="saga-graph-legend-v2">
                <div class="saga-graph-legend-title-v2">Entity Types</div>
                <div class="saga-graph-legend-items-v2">
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #0173B2;"></div>
                        <span class="saga-graph-legend-label-v2">Character</span>
                    </div>
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #029E73;"></div>
                        <span class="saga-graph-legend-label-v2">Location</span>
                    </div>
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #D55E00;"></div>
                        <span class="saga-graph-legend-label-v2">Event</span>
                    </div>
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #CC78BC;"></div>
                        <span class="saga-graph-legend-label-v2">Faction</span>
                    </div>
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #ECE133;"></div>
                        <span class="saga-graph-legend-label-v2">Artifact</span>
                    </div>
                    <div class="saga-graph-legend-item-v2">
                        <div class="saga-graph-legend-color-v2" style="background: #56B4E9;"></div>
                        <span class="saga-graph-legend-label-v2">Concept</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($show_analytics) : ?>
            <div class="saga-graph-analytics-panel" style="display: none;">
                <div class="saga-graph-analytics-title">Graph Analytics</div>
                <div class="saga-graph-stat">
                    <span class="saga-graph-stat-label">Nodes</span>
                    <span class="saga-graph-stat-value" id="<?php echo esc_attr($container_id); ?>-stat-nodes">0</span>
                </div>
                <div class="saga-graph-stat">
                    <span class="saga-graph-stat-label">Edges</span>
                    <span class="saga-graph-stat-value" id="<?php echo esc_attr($container_id); ?>-stat-edges">0</span>
                </div>
                <div class="saga-graph-stat">
                    <span class="saga-graph-stat-label">Density</span>
                    <span class="saga-graph-stat-value" id="<?php echo esc_attr($container_id); ?>-stat-density">0%</span>
                </div>
                <div class="saga-graph-stat">
                    <span class="saga-graph-stat-label">Avg. Connections</span>
                    <span class="saga-graph-stat-value" id="<?php echo esc_attr($container_id); ?>-stat-avg">0</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        // Wait for dependencies to load
        function initGraph() {
            if (typeof d3 === 'undefined' ||
                typeof SagaRelationshipGraphV2 === 'undefined' ||
                typeof SagaGraphLayouts === 'undefined') {
                setTimeout(initGraph, 100);
                return;
            }

            const config = <?php echo wp_json_encode($config); ?>;
            const graph = new SagaRelationshipGraphV2(
                <?php echo wp_json_encode($container_id); ?>,
                config
            );

            // Store reference for external access
            window.sagaGraphInstances = window.sagaGraphInstances || {};
            window.sagaGraphInstances[<?php echo wp_json_encode($container_id); ?>] = graph;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGraph);
        } else {
            initGraph();
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}

/**
 * Enqueue graph v2 assets
 */
function enqueue_graph_v2_assets(): void {
    // D3.js v7
    if (!wp_script_is('d3', 'enqueued')) {
        wp_enqueue_script(
            'd3',
            'https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js',
            [],
            '7.8.5',
            true
        );
    }

    // Graph layouts
    wp_enqueue_script(
        'saga-graph-layouts',
        get_template_directory_uri() . '/assets/js/graph-layouts.js',
        ['d3'],
        filemtime(get_template_directory() . '/assets/js/graph-layouts.js'),
        true
    );

    // Enhanced graph script
    wp_enqueue_script(
        'saga-relationship-graph-v2',
        get_template_directory_uri() . '/assets/js/relationship-graph-v2.js',
        ['d3', 'saga-graph-layouts'],
        filemtime(get_template_directory() . '/assets/js/relationship-graph-v2.js'),
        true
    );

    // Localize script
    wp_localize_script('saga-relationship-graph-v2', 'sagaGraphData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('saga_graph_nonce'),
        'restUrl' => rest_url('saga/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'workerUrl' => get_template_directory_uri() . '/assets/js/graph-worker.js',
    ]);

    // Enhanced graph styles
    wp_enqueue_style(
        'saga-relationship-graph-v2',
        get_template_directory_uri() . '/assets/css/relationship-graph-v2.css',
        [],
        filemtime(get_template_directory() . '/assets/css/relationship-graph-v2.css')
    );
}

/**
 * Gutenberg block registration for v2
 */
function register_graph_v2_block(): void {
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('saga/relationship-graph-v2', [
        'attributes' => [
            'entityId' => ['type' => 'number', 'default' => 0],
            'depth' => ['type' => 'number', 'default' => 2],
            'entityType' => ['type' => 'string', 'default' => ''],
            'relationshipType' => ['type' => 'string', 'default' => ''],
            'limit' => ['type' => 'number', 'default' => 100],
            'height' => ['type' => 'number', 'default' => 600],
            'layout' => ['type' => 'string', 'default' => 'force'],
            'showAnalytics' => ['type' => 'boolean', 'default' => true],
            'showTemporal' => ['type' => 'boolean', 'default' => false],
            'showMinimap' => ['type' => 'boolean', 'default' => true],
            'showLegend' => ['type' => 'boolean', 'default' => true],
            'showControls' => ['type' => 'boolean', 'default' => true],
            'useWorker' => ['type' => 'boolean', 'default' => true],
        ],
        'render_callback' => function($attributes) {
            return render_graph_v2_shortcode([
                'entity_id' => $attributes['entityId'] ?? 0,
                'depth' => $attributes['depth'] ?? 2,
                'entity_type' => $attributes['entityType'] ?? '',
                'relationship_type' => $attributes['relationshipType'] ?? '',
                'limit' => $attributes['limit'] ?? 100,
                'height' => $attributes['height'] ?? 600,
                'layout' => $attributes['layout'] ?? 'force',
                'show_analytics' => $attributes['showAnalytics'] ?? true,
                'show_temporal' => $attributes['showTemporal'] ?? false,
                'show_minimap' => $attributes['showMinimap'] ?? true,
                'show_legend' => $attributes['showLegend'] ?? true,
                'show_controls' => $attributes['showControls'] ?? true,
                'use_worker' => $attributes['useWorker'] ?? true,
            ]);
        }
    ]);
}
add_action('init', __NAMESPACE__ . '\\register_graph_v2_block');

/**
 * Add admin notice about v2
 */
function add_graph_v2_admin_notice(): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if ($screen && in_array($screen->id, ['post', 'page', 'saga_entity'], true)) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>Enhanced Graph v2 Available!</strong>
                Use <code>[saga_graph_v2]</code> for advanced features: multiple layouts, analytics, Web Worker support.
                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-graph-docs')); ?>">View Documentation</a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', __NAMESPACE__ . '\\add_graph_v2_admin_notice');

/**
 * Export graph data as CSV (analytics)
 */
add_action('wp_ajax_saga_export_graph_csv', __NAMESPACE__ . '\\export_graph_csv');
function export_graph_csv(): void {
    check_ajax_referer('saga_graph_nonce', 'nonce');

    if (!current_user_can('read')) {
        wp_send_json_error('Unauthorized', 403);
    }

    $entity_id = absint($_GET['entity_id'] ?? 0);

    global $wpdb;
    $entities_table = $wpdb->prefix . 'saga_entities';
    $relationships_table = $wpdb->prefix . 'saga_entity_relationships';

    // Get graph data
    $nodes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, entity_type, canonical_name, importance_score
             FROM {$entities_table}
             WHERE id = %d OR id IN (
                 SELECT target_entity_id FROM {$relationships_table} WHERE source_entity_id = %d
             )
             ORDER BY importance_score DESC",
            $entity_id,
            $entity_id
        ),
        ARRAY_A
    );

    $edges = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT source_entity_id, target_entity_id, relationship_type, strength
             FROM {$relationships_table}
             WHERE source_entity_id = %d OR target_entity_id = %d",
            $entity_id,
            $entity_id
        ),
        ARRAY_A
    );

    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="saga-graph-data.csv"');

    $output = fopen('php://output', 'w');

    // Nodes
    fputcsv($output, ['Nodes']);
    fputcsv($output, ['ID', 'Type', 'Name', 'Importance']);
    foreach ($nodes as $node) {
        fputcsv($output, $node);
    }

    fputcsv($output, []);

    // Edges
    fputcsv($output, ['Relationships']);
    fputcsv($output, ['Source ID', 'Target ID', 'Type', 'Strength']);
    foreach ($edges as $edge) {
        fputcsv($output, $edge);
    }

    fclose($output);
    exit;
}
