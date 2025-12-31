<?php
/**
 * Example Usage: 3D Semantic Galaxy Visualization
 *
 * This file demonstrates various ways to use the 3D galaxy visualization
 * feature in the Saga Manager theme.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * EXAMPLE 1: Basic Usage
 * =============================================================================
 *
 * Simple shortcode with minimal configuration.
 */
?>

<!-- Basic Galaxy Visualization -->
<div class="saga-example">
    <h2>Star Wars Galaxy</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="1"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 2: Custom Sized Galaxy
 * =============================================================================
 *
 * Adjust height and display options.
 */
?>

<!-- Larger Galaxy with Custom Height -->
<div class="saga-example">
    <h2>Lord of the Rings - Full Screen</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="2" height="800"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 3: Auto-Rotating Galaxy
 * =============================================================================
 *
 * Enable automatic rotation for presentations.
 */
?>

<!-- Auto-Rotating Galaxy -->
<div class="saga-example">
    <h2>Dune Universe - Auto Rotating</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="3" auto_rotate="true"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 4: Minimal UI
 * =============================================================================
 *
 * Hide controls and minimap for cleaner presentation.
 */
?>

<!-- Minimal Interface -->
<div class="saga-example">
    <h2>Foundation Series - Minimal UI</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="4" show_controls="false" show_minimap="false"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 5: Light Theme
 * =============================================================================
 *
 * Use light theme for better visibility on light backgrounds.
 */
?>

<!-- Light Theme Galaxy -->
<div class="saga-example light-background">
    <h2>Marvel Cinematic Universe - Light Theme</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="5" theme="light"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 6: Performance Optimized
 * =============================================================================
 *
 * Reduce visual complexity for better performance.
 */
?>

<!-- Performance Optimized -->
<div class="saga-example">
    <h2>Wheel of Time - Performance Mode</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="6" particle_count="500" force_strength="0.01"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 7: Dense Universe
 * =============================================================================
 *
 * Optimize for sagas with many entities.
 */
?>

<!-- Dense Entity Network -->
<div class="saga-example">
    <h2>Cosmere - Dense Network</h2>
    <?php
    echo do_shortcode('[saga_galaxy
        saga_id="7"
        node_min_size="1"
        node_max_size="8"
        link_opacity="0.2"
    ]');
    ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 8: Sparse Universe
 * =============================================================================
 *
 * Optimize for sagas with fewer entities.
 */
?>

<!-- Sparse Entity Network -->
<div class="saga-example">
    <h2>Blade Runner - Sparse Network</h2>
    <?php
    echo do_shortcode('[saga_galaxy
        saga_id="8"
        node_min_size="5"
        node_max_size="20"
        link_opacity="0.8"
        particle_count="2000"
    ]');
    ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 9: PHP Template Integration
 * =============================================================================
 *
 * Programmatic usage in theme templates.
 */

// Get current saga ID
$current_saga_id = get_the_ID();

// Only show galaxy if saga has entities
$entity_count = get_post_meta($current_saga_id, 'entity_count', true);

if ($entity_count > 10) : ?>
    <div class="saga-galaxy-section">
        <h2><?php esc_html_e('Explore the Galaxy', 'saga-manager-theme'); ?></h2>
        <p><?php esc_html_e('Interactive 3D visualization of entity relationships.', 'saga-manager-theme'); ?></p>

        <?php
        echo do_shortcode(sprintf(
            '[saga_galaxy saga_id="%d" height="700" auto_rotate="false"]',
            $current_saga_id
        ));
        ?>
    </div>
<?php endif; ?>

<?php
/**
 * =============================================================================
 * EXAMPLE 10: Dynamic Configuration
 * =============================================================================
 *
 * Adjust settings based on saga metadata.
 */

// Get saga settings
$saga_size = get_post_meta($current_saga_id, 'saga_size', true); // 'small', 'medium', 'large'

// Configure based on size
$config = [
    'small' => [
        'height' => 500,
        'particle_count' => 500,
        'node_max_size' => 20,
    ],
    'medium' => [
        'height' => 600,
        'particle_count' => 1000,
        'node_max_size' => 15,
    ],
    'large' => [
        'height' => 800,
        'particle_count' => 1500,
        'node_max_size' => 10,
    ],
];

$settings = $config[$saga_size] ?? $config['medium'];
?>

<div class="saga-dynamic-galaxy">
    <?php
    echo do_shortcode(sprintf(
        '[saga_galaxy saga_id="%d" height="%d" particle_count="%d" node_max_size="%d"]',
        $current_saga_id,
        $settings['height'],
        $settings['particle_count'],
        $settings['node_max_size']
    ));
    ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 11: JavaScript Event Handling
 * =============================================================================
 *
 * Custom JavaScript to interact with the galaxy.
 */
?>

<div class="saga-interactive-example">
    <h2>Interactive Galaxy Example</h2>

    <!-- Custom controls -->
    <div class="custom-controls">
        <button id="search-characters">Find All Characters</button>
        <button id="reset-galaxy">Reset View</button>
        <button id="export-data">Export Galaxy Data</button>
    </div>

    <!-- Galaxy container -->
    <div id="my-custom-galaxy">
        <?php echo do_shortcode('[saga_galaxy saga_id="1"]'); ?>
    </div>

    <script>
    (function($) {
        $(document).ready(function() {
            const container = $('#my-custom-galaxy .saga-galaxy-container')[0];

            // Wait for galaxy initialization
            container.addEventListener('galaxy:graphCreated', function(e) {
                const galaxy = e.detail.galaxy;

                // Custom search for characters
                $('#search-characters').on('click', function() {
                    galaxy.filterByType(['character']);
                });

                // Reset view
                $('#reset-galaxy').on('click', function() {
                    galaxy.resetView();
                    galaxy.filterByType([]); // Show all
                });

                // Export data (for admins)
                $('#export-data').on('click', function() {
                    const stats = galaxy.getStats();
                    console.log('Galaxy Stats:', stats);
                    alert('Check console for galaxy data');
                });
            });

            // Listen for node selection
            container.addEventListener('galaxy:nodeSelect', function(e) {
                const node = e.detail.node;
                console.log('Selected entity:', node.name, node.type);

                // Custom action on selection
                $('#my-custom-galaxy').append(
                    '<div class="selection-notice">Selected: ' + node.name + '</div>'
                );

                setTimeout(function() {
                    $('.selection-notice').fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            });
        });
    })(jQuery);
    </script>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 12: Conditional Display
 * =============================================================================
 *
 * Show galaxy only for specific user roles or conditions.
 */

// Only show to logged-in users
if (is_user_logged_in()) :
?>
    <div class="members-only-galaxy">
        <h2><?php esc_html_e('Members-Only Galaxy View', 'saga-manager-theme'); ?></h2>
        <?php echo do_shortcode('[saga_galaxy saga_id="1"]'); ?>
    </div>
<?php
endif;

// Show premium features for subscribers
if (current_user_can('subscriber')) :
?>
    <div class="premium-galaxy">
        <h2><?php esc_html_e('Premium Galaxy Features', 'saga-manager-theme'); ?></h2>
        <?php echo do_shortcode('[saga_galaxy saga_id="1" show_minimap="true" auto_rotate="true"]'); ?>
    </div>
<?php
endif;
?>

<?php
/**
 * =============================================================================
 * EXAMPLE 13: Archive Page Integration
 * =============================================================================
 *
 * Add galaxy to saga archive pages.
 */

// In archive-saga.php template
if (is_post_type_archive('saga')) :
    $current_saga = get_queried_object();

    if ($current_saga && isset($current_saga->term_id)) :
?>
    <div class="archive-galaxy">
        <h2><?php echo esc_html($current_saga->name); ?> - Galaxy View</h2>
        <?php echo do_shortcode('[saga_galaxy saga_id="' . $current_saga->term_id . '"]'); ?>
    </div>
<?php
    endif;
endif;
?>

<?php
/**
 * =============================================================================
 * EXAMPLE 14: Custom Styling
 * =============================================================================
 *
 * Apply custom CSS classes and inline styles.
 */
?>

<style>
.custom-galaxy-wrapper {
    margin: 2rem 0;
    padding: 2rem;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.custom-galaxy-wrapper .saga-galaxy-controls {
    background: rgba(15, 15, 25, 0.95);
    border: 2px solid #4488ff;
}

.custom-galaxy-wrapper h2 {
    color: #4488ff;
    text-align: center;
    margin-bottom: 1rem;
}
</style>

<div class="custom-galaxy-wrapper">
    <h2>Custom Styled Galaxy</h2>
    <?php echo do_shortcode('[saga_galaxy saga_id="1"]'); ?>
</div>

<?php
/**
 * =============================================================================
 * EXAMPLE 15: Gutenberg Block Integration
 * =============================================================================
 *
 * Example for future Gutenberg block implementation.
 */

/**
 * Register galaxy block (future enhancement)
 */
function saga_register_galaxy_block() {
    // This is a placeholder for future Gutenberg integration

    // Example block.json
    /*
    {
        "name": "saga/galaxy",
        "title": "Saga Galaxy",
        "category": "widgets",
        "attributes": {
            "sagaId": {
                "type": "number",
                "default": 1
            },
            "height": {
                "type": "number",
                "default": 600
            }
        }
    }
    */
}
// add_action('init', 'saga_register_galaxy_block');
?>

<?php
/**
 * =============================================================================
 * TROUBLESHOOTING EXAMPLES
 * =============================================================================
 */

/**
 * Example: Check if galaxy data exists before rendering
 */
function saga_safe_galaxy_render($saga_id) {
    // Verify saga exists
    $saga = get_post($saga_id);
    if (!$saga) {
        return '<p class="saga-error">Invalid saga ID.</p>';
    }

    // Check if saga has entities
    $entities = new WP_Query([
        'post_type' => 'saga_entity',
        'meta_query' => [
            [
                'key' => 'saga_id',
                'value' => $saga_id,
            ],
        ],
        'posts_per_page' => 1,
    ]);

    if (!$entities->have_posts()) {
        return '<p class="saga-notice">No entities found for this saga. <a href="' .
               admin_url('post-new.php?post_type=saga_entity') . '">Add entities</a> to visualize.</p>';
    }

    // Render galaxy
    return do_shortcode('[saga_galaxy saga_id="' . $saga_id . '"]');
}

// Usage
// echo saga_safe_galaxy_render(get_the_ID());

/**
 * Example: Clear galaxy cache programmatically
 */
function saga_refresh_galaxy_cache($saga_id) {
    if (function_exists('saga_clear_galaxy_cache')) {
        saga_clear_galaxy_cache($saga_id);
        return true;
    }
    return false;
}

// Usage in admin action
// add_action('save_post_saga', function($post_id) {
//     saga_refresh_galaxy_cache($post_id);
// });

/**
 * Example: Get galaxy stats
 */
function saga_get_galaxy_stats($saga_id) {
    if (!function_exists('saga_get_galaxy_nodes')) {
        return null;
    }

    $nodes = saga_get_galaxy_nodes($saga_id);
    $links = saga_get_galaxy_links($saga_id);

    return [
        'node_count' => count($nodes),
        'link_count' => count($links),
        'entity_types' => array_count_values(array_column($nodes, 'type')),
    ];
}

// Usage
// $stats = saga_get_galaxy_stats(1);
// echo "Entities: " . $stats['node_count'];
?>

<!--
=============================================================================
USAGE NOTES
=============================================================================

1. Always verify saga_id exists before rendering
2. Consider performance with large datasets (1000+ entities)
3. Test on mobile devices with touch controls
4. Use theme="light" on light backgrounds
5. Enable auto_rotate sparingly (impacts performance)
6. Clear cache after bulk entity updates
7. Monitor browser console for errors
8. Use performance monitor during development

=============================================================================
BEST PRACTICES
=============================================================================

1. Default height of 600px works well for most cases
2. Disable controls only for embedded/iframe scenarios
3. Use light theme for print/PDF exports
4. Limit particle_count on mobile devices
5. Cache galaxy data appropriately
6. Handle empty state gracefully
7. Provide loading indicators
8. Test across browsers

-->
