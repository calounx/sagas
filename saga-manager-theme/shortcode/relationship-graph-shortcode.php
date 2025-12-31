<?php
declare(strict_types=1);

/**
 * Relationship Graph Shortcode
 *
 * Provides [saga_relationship_graph] shortcode for embedding interactive graphs
 *
 * @package SagaManager
 * @since 1.0.0
 */

namespace SagaManager\Theme\Shortcode;

/**
 * Register relationship graph shortcode
 */
function register_relationship_graph_shortcode(): void {
    add_shortcode('saga_relationship_graph', __NAMESPACE__ . '\\render_relationship_graph_shortcode');
}
add_action('init', __NAMESPACE__ . '\\register_relationship_graph_shortcode');

/**
 * Render relationship graph shortcode
 *
 * Usage:
 * [saga_relationship_graph entity_id="123" depth="2"]
 * [saga_relationship_graph entity_type="character" limit="50"]
 * [saga_relationship_graph relationship_type="ally" height="800"]
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function render_relationship_graph_shortcode(array $atts): string {
    // Parse attributes with defaults
    $atts = shortcode_atts([
        'entity_id' => 0,
        'depth' => 1,
        'entity_type' => '',
        'relationship_type' => '',
        'limit' => 100,
        'height' => 600,
        'show_filters' => 'true',
        'show_legend' => 'true',
        'show_table' => 'true',
    ], $atts, 'saga_relationship_graph');

    // Sanitize attributes
    $entity_id = absint($atts['entity_id']);
    $depth = min(absint($atts['depth']), 3);
    $entity_type = sanitize_key($atts['entity_type']);
    $relationship_type = sanitize_text_field($atts['relationship_type']);
    $limit = min(absint($atts['limit']), 100);
    $height = absint($atts['height']);
    $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
    $show_legend = filter_var($atts['show_legend'], FILTER_VALIDATE_BOOLEAN);
    $show_table = filter_var($atts['show_table'], FILTER_VALIDATE_BOOLEAN);

    // Validate entity type
    $valid_types = ['character', 'location', 'event', 'faction', 'artifact', 'concept'];
    if (!empty($entity_type) && !in_array($entity_type, $valid_types, true)) {
        return '<div class="saga-graph-error" role="alert">Invalid entity type specified.</div>';
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

    // Enqueue assets (if not already enqueued)
    enqueue_graph_assets();

    // Start output buffering
    ob_start();

    // Include template with variables
    include locate_template('template-parts/relationship-graph.php');

    return ob_get_clean();
}

/**
 * Enqueue graph assets
 *
 * Called by shortcode to ensure assets are loaded
 */
function enqueue_graph_assets(): void {
    // D3.js v7 from CDN
    if (!wp_script_is('d3', 'enqueued')) {
        wp_enqueue_script(
            'd3',
            'https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js',
            [],
            '7.8.5',
            true
        );
    }

    // Relationship graph script
    if (!wp_script_is('saga-relationship-graph', 'enqueued')) {
        wp_enqueue_script(
            'saga-relationship-graph',
            get_template_directory_uri() . '/assets/js/relationship-graph.js',
            ['d3'],
            filemtime(get_template_directory() . '/assets/js/relationship-graph.js'),
            true
        );

        // Localize script
        wp_localize_script('saga-relationship-graph', 'sagaGraphData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saga_graph_nonce'),
            'restUrl' => rest_url('saga/v1'),
            'restNonce' => wp_create_nonce('wp_rest')
        ]);
    }

    // Relationship graph styles
    if (!wp_style_is('saga-relationship-graph', 'enqueued')) {
        wp_enqueue_style(
            'saga-relationship-graph',
            get_template_directory_uri() . '/assets/css/relationship-graph.css',
            [],
            filemtime(get_template_directory() . '/assets/css/relationship-graph.css')
        );
    }
}

/**
 * Auto-embed graph on single entity pages
 *
 * Automatically adds relationship graph to entity single pages
 */
function auto_embed_graph_on_entity_pages(string $content): string {
    // Only on single entity posts
    if (!is_singular('saga_entity')) {
        return $content;
    }

    global $post;

    // Get entity ID from post meta or database
    $entity_id = get_post_meta($post->ID, 'saga_entity_id', true);

    if (!$entity_id) {
        // Try to find entity by wp_post_id
        global $wpdb;
        $entities_table = $wpdb->prefix . 'saga_entities';

        $entity_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$entities_table} WHERE wp_post_id = %d",
            $post->ID
        ));
    }

    if (!$entity_id) {
        return $content;
    }

    // Check if graph is already in content
    if (strpos($content, 'saga_relationship_graph') !== false) {
        return $content;
    }

    // Add graph after content
    $graph_html = do_shortcode(sprintf(
        '[saga_relationship_graph entity_id="%d" depth="2" show_filters="true"]',
        absint($entity_id)
    ));

    return $content . '<div class="saga-entity-graph-section"><h2>Relationships</h2>' . $graph_html . '</div>';
}

// Optional: Auto-embed on entity pages (commented out by default)
// add_filter('the_content', __NAMESPACE__ . '\\auto_embed_graph_on_entity_pages', 20);

/**
 * Gutenberg block registration
 *
 * Register relationship graph as a Gutenberg block
 */
function register_relationship_graph_block(): void {
    // Check if Gutenberg is active
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('saga/relationship-graph', [
        'attributes' => [
            'entityId' => [
                'type' => 'number',
                'default' => 0
            ],
            'depth' => [
                'type' => 'number',
                'default' => 1
            ],
            'entityType' => [
                'type' => 'string',
                'default' => ''
            ],
            'relationshipType' => [
                'type' => 'string',
                'default' => ''
            ],
            'limit' => [
                'type' => 'number',
                'default' => 100
            ],
            'height' => [
                'type' => 'number',
                'default' => 600
            ],
            'showFilters' => [
                'type' => 'boolean',
                'default' => true
            ],
            'showLegend' => [
                'type' => 'boolean',
                'default' => true
            ],
            'showTable' => [
                'type' => 'boolean',
                'default' => true
            ]
        ],
        'render_callback' => function($attributes) {
            return render_relationship_graph_shortcode([
                'entity_id' => $attributes['entityId'] ?? 0,
                'depth' => $attributes['depth'] ?? 1,
                'entity_type' => $attributes['entityType'] ?? '',
                'relationship_type' => $attributes['relationshipType'] ?? '',
                'limit' => $attributes['limit'] ?? 100,
                'height' => $attributes['height'] ?? 600,
                'show_filters' => $attributes['showFilters'] ?? true,
                'show_legend' => $attributes['showLegend'] ?? true,
                'show_table' => $attributes['showTable'] ?? true,
            ]);
        }
    ]);
}
add_action('init', __NAMESPACE__ . '\\register_relationship_graph_block');

/**
 * Add shortcode button to classic editor
 */
function add_graph_shortcode_button(): void {
    // Only add on post edit screen
    if (!is_admin() || !current_user_can('edit_posts')) {
        return;
    }

    add_action('media_buttons', function() {
        echo '<button type="button" class="button saga-insert-graph" data-editor="content">';
        echo '<span class="dashicons dashicons-networking" style="vertical-align: middle;"></span> ';
        echo esc_html__('Insert Relationship Graph', 'saga-manager');
        echo '</button>';
    });

    // Add inline script for shortcode insertion
    add_action('admin_footer', function() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.saga-insert-graph').on('click', function(e) {
                e.preventDefault();

                const entityId = prompt('Enter Entity ID (or leave blank for all entities):');
                const depth = prompt('Enter relationship depth (1-3):', '1');

                let shortcode = '[saga_relationship_graph';

                if (entityId && entityId.trim() !== '') {
                    shortcode += ' entity_id="' + entityId + '"';
                }

                if (depth && depth.trim() !== '') {
                    shortcode += ' depth="' + depth + '"';
                }

                shortcode += ']';

                // Insert into editor
                if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                    wp.media.editor.insert(shortcode);
                } else {
                    // Fallback for classic editor
                    const editor = $(this).data('editor');
                    if (typeof tinymce !== 'undefined') {
                        tinymce.get(editor).execCommand('mceInsertContent', false, shortcode);
                    } else {
                        $('#' + editor).val($('#' + editor).val() + shortcode);
                    }
                }
            });
        });
        </script>
        <style>
        .saga-insert-graph .dashicons {
            font-size: 16px;
        }
        </style>
        <?php
    });
}
add_action('admin_init', __NAMESPACE__ . '\\add_graph_shortcode_button');

/**
 * Add entity selector meta box
 *
 * Adds a meta box to posts for inserting relationship graphs
 */
function add_entity_graph_meta_box(): void {
    add_meta_box(
        'saga-relationship-graph',
        __('Relationship Graph', 'saga-manager'),
        __NAMESPACE__ . '\\render_entity_graph_meta_box',
        ['post', 'page', 'saga_entity'],
        'side',
        'default'
    );
}
add_action('add_meta_boxes', __NAMESPACE__ . '\\add_entity_graph_meta_box');

/**
 * Render entity graph meta box
 */
function render_entity_graph_meta_box(\WP_Post $post): void {
    global $wpdb;
    $entities_table = $wpdb->prefix . 'saga_entities';

    // Get entities for dropdown
    $entities = $wpdb->get_results(
        "SELECT id, canonical_name, entity_type
         FROM {$entities_table}
         ORDER BY importance_score DESC, canonical_name ASC
         LIMIT 100",
        ARRAY_A
    );

    ?>
    <div class="saga-graph-meta-box">
        <p>
            <label for="saga-graph-entity-select">
                <?php esc_html_e('Select Entity:', 'saga-manager'); ?>
            </label>
            <select id="saga-graph-entity-select" style="width: 100%; margin-top: 8px;">
                <option value="">All Entities</option>
                <?php foreach ($entities as $entity) : ?>
                    <option value="<?php echo esc_attr($entity['id']); ?>">
                        <?php echo esc_html($entity['canonical_name']); ?>
                        (<?php echo esc_html($entity['entity_type']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="saga-graph-depth">
                <?php esc_html_e('Depth:', 'saga-manager'); ?>
            </label>
            <select id="saga-graph-depth" style="width: 100%; margin-top: 8px;">
                <option value="1">1 Level</option>
                <option value="2" selected>2 Levels</option>
                <option value="3">3 Levels</option>
            </select>
        </p>

        <p>
            <button type="button" class="button button-secondary" id="saga-insert-graph-shortcode" style="width: 100%;">
                <?php esc_html_e('Insert Graph Shortcode', 'saga-manager'); ?>
            </button>
        </p>

        <p class="description">
            <?php esc_html_e('Click to insert a relationship graph shortcode into your content.', 'saga-manager'); ?>
        </p>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#saga-insert-graph-shortcode').on('click', function() {
            const entityId = $('#saga-graph-entity-select').val();
            const depth = $('#saga-graph-depth').val();

            let shortcode = '[saga_relationship_graph';

            if (entityId) {
                shortcode += ' entity_id="' + entityId + '"';
            }

            if (depth) {
                shortcode += ' depth="' + depth + '"';
            }

            shortcode += ']';

            // Insert into editor
            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                wp.media.editor.insert(shortcode);
            } else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').execCommand('mceInsertContent', false, shortcode);
            } else {
                $('#content').val($('#content').val() + '\n\n' + shortcode);
            }

            // Show success message
            $(this).text('✓ Shortcode Inserted!').addClass('button-primary');
            setTimeout(() => {
                $(this).text('Insert Graph Shortcode').removeClass('button-primary');
            }, 2000);
        });
    });
    </script>
    <?php
}
