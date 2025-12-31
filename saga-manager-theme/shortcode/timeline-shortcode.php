<?php
/**
 * Timeline Shortcode
 *
 * Registers [saga_timeline] shortcode for embedding interactive timelines.
 *
 * Usage:
 * [saga_timeline saga_id="1"]
 * [saga_timeline saga_id="1" type="grouped" height="800"]
 * [saga_timeline saga_id="1" entity_id="123" type="linear"]
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register timeline shortcode
 */
function saga_register_timeline_shortcode() {
    add_shortcode('saga_timeline', 'saga_timeline_shortcode_handler');
}
add_action('init', 'saga_register_timeline_shortcode');

/**
 * Timeline shortcode handler
 *
 * @param array $atts Shortcode attributes
 * @param string $content Shortcode content (unused)
 * @return string Timeline HTML output
 */
function saga_timeline_shortcode_handler($atts, $content = null) {
    // Parse shortcode attributes with defaults
    $atts = shortcode_atts([
        'saga_id' => 0,
        'entity_id' => null,
        'type' => 'linear', // linear, grouped, stacked
        'height' => 600,
        'calendar_type' => null,
        'show_controls' => true,
        'show_filters' => true,
        'date_from' => null,
        'date_to' => null,
        'class' => '',
    ], $atts, 'saga_timeline');

    // Validate saga_id
    $saga_id = absint($atts['saga_id']);
    if (!$saga_id) {
        return '<div class="timeline-error active">' .
               esc_html__('Error: saga_id attribute is required.', 'saga-manager') .
               '</div>';
    }

    // Verify saga exists
    global $wpdb;
    $saga_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saga_sagas WHERE id = %d",
        $saga_id
    ));

    if (!$saga_exists) {
        return '<div class="timeline-error active">' .
               sprintf(
                   esc_html__('Error: Saga #%d not found.', 'saga-manager'),
                   $saga_id
               ) .
               '</div>';
    }

    // Sanitize and validate attributes
    $entity_id = !empty($atts['entity_id']) ? absint($atts['entity_id']) : null;
    $timeline_type = in_array($atts['type'], ['linear', 'grouped', 'stacked'])
        ? $atts['type']
        : 'linear';
    $height = absint($atts['height']);
    $height = $height >= 300 && $height <= 2000 ? $height : 600;
    $calendar_type = !empty($atts['calendar_type']) ? sanitize_key($atts['calendar_type']) : null;
    $show_controls = filter_var($atts['show_controls'], FILTER_VALIDATE_BOOLEAN);
    $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
    $custom_class = sanitize_html_class($atts['class']);

    // Build date range if provided
    $date_range = null;
    if (!empty($atts['date_from']) || !empty($atts['date_to'])) {
        $date_range = [
            'start' => !empty($atts['date_from']) ? sanitize_text_field($atts['date_from']) : null,
            'end' => !empty($atts['date_to']) ? sanitize_text_field($atts['date_to']) : null,
        ];
    }

    // Enqueue timeline assets
    saga_enqueue_timeline_assets();

    // Prepare template arguments
    $template_args = [
        'saga_id' => $saga_id,
        'entity_id' => $entity_id,
        'type' => $timeline_type,
        'height' => $height,
        'calendar_type' => $calendar_type,
        'date_range' => $date_range,
        'show_controls' => $show_controls,
        'show_filters' => $show_filters,
    ];

    // Add custom class wrapper if provided
    $wrapper_class = $custom_class ? ' ' . $custom_class : '';

    // Start output buffering
    ob_start();

    // Wrapper div with custom class
    if ($wrapper_class) {
        echo '<div class="saga-timeline-shortcode' . esc_attr($wrapper_class) . '">';
    }

    // Load template part
    get_template_part('template-parts/timeline-viewer', null, $template_args);

    // Close wrapper
    if ($wrapper_class) {
        echo '</div>';
    }

    // Return buffered output
    return ob_get_clean();
}

/**
 * Enqueue timeline-specific assets
 *
 * Ensures timeline assets are loaded when shortcode is used.
 * Called by shortcode handler.
 */
function saga_enqueue_timeline_assets() {
    // Only enqueue once per page
    static $enqueued = false;
    if ($enqueued) {
        return;
    }

    // vis-timeline library (CDN)
    wp_enqueue_script(
        'vis-timeline',
        'https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js',
        [],
        '7.7.3',
        true
    );

    wp_enqueue_style(
        'vis-timeline',
        'https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css',
        [],
        '7.7.3'
    );

    // html2canvas for image export (optional)
    wp_enqueue_script(
        'html2canvas',
        'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
        [],
        '1.4.1',
        true
    );

    // Timeline visualization scripts (already enqueued in functions.php)
    // But ensure they're loaded with dependencies
    if (wp_script_is('saga-timeline-visualization', 'registered')) {
        wp_enqueue_script('saga-timeline-visualization');
    }

    if (wp_style_is('saga-timeline-visualization', 'registered')) {
        wp_enqueue_style('saga-timeline-visualization');
    }

    // Localize script with AJAX data
    wp_localize_script('saga-timeline-visualization', 'sagaTimelineData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('saga_timeline_nonce'),
        'i18n' => [
            'loading' => __('Loading timeline...', 'saga-manager'),
            'error' => __('Failed to load timeline data.', 'saga-manager'),
            'no_events' => __('No events found.', 'saga-manager'),
            'filter_applied' => __('Filters applied.', 'saga-manager'),
            'zoom_in' => __('Zoom in', 'saga-manager'),
            'zoom_out' => __('Zoom out', 'saga-manager'),
            'fit' => __('Fit to window', 'saga-manager'),
            'export_success' => __('Export successful.', 'saga-manager'),
            'export_error' => __('Export failed.', 'saga-manager'),
        ]
    ]);

    $enqueued = true;
}

/**
 * Add timeline shortcode button to editor
 *
 * Adds a button to the WordPress editor toolbar for inserting timeline shortcodes.
 */
function saga_add_timeline_shortcode_button() {
    // Only add button if user can edit posts
    if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
        return;
    }

    // Only add in rich editing mode
    if (get_user_option('rich_editing') !== 'true') {
        return;
    }

    // Add TinyMCE plugin
    add_filter('mce_external_plugins', 'saga_add_timeline_tinymce_plugin');
    add_filter('mce_buttons', 'saga_register_timeline_button');
}
add_action('admin_head', 'saga_add_timeline_shortcode_button');

/**
 * Register TinyMCE plugin
 *
 * @param array $plugin_array Existing plugins
 * @return array Modified plugins
 */
function saga_add_timeline_tinymce_plugin($plugin_array) {
    $plugin_array['saga_timeline'] = get_template_directory_uri() . '/assets/js/timeline-tinymce-plugin.js';
    return $plugin_array;
}

/**
 * Register timeline button
 *
 * @param array $buttons Existing buttons
 * @return array Modified buttons
 */
function saga_register_timeline_button($buttons) {
    array_push($buttons, 'saga_timeline');
    return $buttons;
}

/**
 * Timeline shortcode generator modal
 *
 * Outputs a modal for generating timeline shortcodes in the admin.
 */
function saga_timeline_shortcode_generator_modal() {
    // Only show on post editor screens
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, ['post', 'page'])) {
        return;
    }

    global $wpdb;

    // Get available sagas
    $sagas = $wpdb->get_results(
        "SELECT id, name, universe FROM {$wpdb->prefix}saga_sagas ORDER BY name ASC"
    );

    if (empty($sagas)) {
        return;
    }
    ?>
    <div id="saga-timeline-shortcode-modal" style="display:none;">
        <div class="saga-shortcode-generator">
            <h2><?php _e('Insert Timeline', 'saga-manager'); ?></h2>

            <div class="form-field">
                <label for="saga-timeline-saga-id">
                    <?php _e('Select Saga', 'saga-manager'); ?>
                    <span class="required">*</span>
                </label>
                <select id="saga-timeline-saga-id" required>
                    <option value=""><?php _e('-- Select Saga --', 'saga-manager'); ?></option>
                    <?php foreach ($sagas as $saga) : ?>
                    <option value="<?php echo esc_attr($saga->id); ?>">
                        <?php echo esc_html($saga->name); ?>
                        <?php if ($saga->universe) : ?>
                            (<?php echo esc_html($saga->universe); ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="saga-timeline-type"><?php _e('Timeline Type', 'saga-manager'); ?></label>
                <select id="saga-timeline-type">
                    <option value="linear"><?php _e('Linear View', 'saga-manager'); ?></option>
                    <option value="grouped"><?php _e('Grouped by Type', 'saga-manager'); ?></option>
                    <option value="stacked"><?php _e('Stacked View', 'saga-manager'); ?></option>
                </select>
            </div>

            <div class="form-field">
                <label for="saga-timeline-height"><?php _e('Height (px)', 'saga-manager'); ?></label>
                <input type="number" id="saga-timeline-height" value="600" min="300" max="2000" step="50">
            </div>

            <div class="form-field">
                <label>
                    <input type="checkbox" id="saga-timeline-show-controls" checked>
                    <?php _e('Show Controls', 'saga-manager'); ?>
                </label>
            </div>

            <div class="form-field">
                <label>
                    <input type="checkbox" id="saga-timeline-show-filters" checked>
                    <?php _e('Show Filters', 'saga-manager'); ?>
                </label>
            </div>

            <div class="form-field">
                <label for="saga-timeline-preview"><?php _e('Shortcode Preview', 'saga-manager'); ?></label>
                <textarea id="saga-timeline-preview" readonly rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="button button-primary" id="saga-timeline-insert">
                    <?php _e('Insert Shortcode', 'saga-manager'); ?>
                </button>
                <button type="button" class="button" id="saga-timeline-cancel">
                    <?php _e('Cancel', 'saga-manager'); ?>
                </button>
            </div>
        </div>
    </div>

    <style>
        .saga-shortcode-generator {
            padding: 20px;
            max-width: 500px;
        }
        .saga-shortcode-generator h2 {
            margin: 0 0 20px 0;
        }
        .saga-shortcode-generator .form-field {
            margin-bottom: 15px;
        }
        .saga-shortcode-generator label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .saga-shortcode-generator select,
        .saga-shortcode-generator input[type="number"],
        .saga-shortcode-generator textarea {
            width: 100%;
            padding: 8px;
        }
        .saga-shortcode-generator .required {
            color: #dc3232;
        }
        .saga-shortcode-generator .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
        }
        #saga-timeline-preview {
            font-family: monospace;
            background: #f5f5f5;
            resize: vertical;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function updatePreview() {
            const sagaId = $('#saga-timeline-saga-id').val();
            const type = $('#saga-timeline-type').val();
            const height = $('#saga-timeline-height').val();
            const showControls = $('#saga-timeline-show-controls').prop('checked');
            const showFilters = $('#saga-timeline-show-filters').prop('checked');

            if (!sagaId) {
                $('#saga-timeline-preview').val('');
                return;
            }

            let shortcode = `[saga_timeline saga_id="${sagaId}"`;

            if (type !== 'linear') {
                shortcode += ` type="${type}"`;
            }

            if (height !== '600') {
                shortcode += ` height="${height}"`;
            }

            if (!showControls) {
                shortcode += ' show_controls="false"';
            }

            if (!showFilters) {
                shortcode += ' show_filters="false"';
            }

            shortcode += ']';

            $('#saga-timeline-preview').val(shortcode);
        }

        // Update preview on field changes
        $('#saga-timeline-saga-id, #saga-timeline-type, #saga-timeline-height, #saga-timeline-show-controls, #saga-timeline-show-filters')
            .on('change input', updatePreview);

        // Insert shortcode
        $('#saga-timeline-insert').on('click', function() {
            const shortcode = $('#saga-timeline-preview').val();
            if (!shortcode) {
                alert('<?php _e('Please select a saga.', 'saga-manager'); ?>');
                return;
            }

            // Insert into editor
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                tinymce.activeEditor.insertContent(shortcode);
            } else {
                // Fallback for text editor
                const editor = $('#content');
                if (editor.length) {
                    editor.val(editor.val() + shortcode);
                }
            }

            // Close modal
            tb_remove();
        });

        // Cancel button
        $('#saga-timeline-cancel').on('click', function() {
            tb_remove();
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'saga_timeline_shortcode_generator_modal');

/**
 * Validate timeline shortcode attributes
 *
 * Server-side validation for shortcode attributes.
 *
 * @param array $atts Shortcode attributes
 * @return array|WP_Error Validated attributes or error
 */
function saga_validate_timeline_shortcode_atts($atts) {
    $errors = [];

    // Required: saga_id
    if (empty($atts['saga_id'])) {
        $errors[] = __('saga_id is required', 'saga-manager');
    }

    // Validate type
    if (!empty($atts['type']) && !in_array($atts['type'], ['linear', 'grouped', 'stacked'])) {
        $errors[] = __('Invalid timeline type. Must be linear, grouped, or stacked.', 'saga-manager');
    }

    // Validate height
    if (!empty($atts['height'])) {
        $height = absint($atts['height']);
        if ($height < 300 || $height > 2000) {
            $errors[] = __('Height must be between 300 and 2000 pixels.', 'saga-manager');
        }
    }

    if (!empty($errors)) {
        return new WP_Error('invalid_shortcode', implode('. ', $errors));
    }

    return $atts;
}
