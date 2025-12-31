<?php
/**
 * Example: Collapsible Sections Usage
 *
 * This file demonstrates how to use the collapsible sections feature
 * in entity templates (character, location, event, etc.)
 *
 * @package SagaManager
 * @since 1.0.0
 */

declare(strict_types=1);

// Example 1: Basic usage in a character template
// ----------------------------------------------

get_header();

while (have_posts()) :
    the_post();
    $post_id = get_the_ID();
    ?>

    <article id="post-<?php echo $post_id; ?>" <?php post_class('saga-entity saga-entity--character'); ?>>

        <!-- Hero Section (always visible) -->
        <header class="saga-entity__hero">
            <h1 class="saga-entity__title"><?php the_title(); ?></h1>
            <?php if (has_excerpt()) : ?>
                <div class="saga-entity__excerpt"><?php the_excerpt(); ?></div>
            <?php endif; ?>
        </header>

        <div class="container">
            <!-- Expand/Collapse All Controls -->
            <?php saga_collapsible_controls(['position' => 'top']); ?>

            <!-- Biography Section (expanded by default) -->
            <?php
            $biography = get_post_meta($post_id, '_saga_character_biography', true);
            if (!empty($biography)) {
                saga_collapsible_section([
                    'id' => 'biography',
                    'title' => __('Biography', 'saga-manager'),
                    'content' => wp_kses_post($biography),
                    'expanded' => true,
                    'icon' => 'user',
                ]);
            }
            ?>

            <!-- Attributes Section -->
            <?php
            $attributes = saga_get_entity_attributes($post_id);
            if (!empty($attributes)) {
                $attributes_html = '<dl class="saga-attributes">';
                foreach ($attributes as $attr) {
                    $attributes_html .= sprintf(
                        '<dt>%s</dt><dd>%s</dd>',
                        esc_html($attr['label']),
                        esc_html($attr['value'])
                    );
                }
                $attributes_html .= '</dl>';

                saga_collapsible_section([
                    'id' => 'attributes',
                    'title' => __('Attributes', 'saga-manager'),
                    'content' => $attributes_html,
                    'expanded' => true,
                    'icon' => 'list',
                ]);
            }
            ?>

            <!-- Relationships Section (collapsed by default) -->
            <?php
            $relationships = saga_get_related_entities($post_id, 'relationship');
            if (!empty($relationships)) {
                ob_start();
                ?>
                <div class="saga-relationships">
                    <?php foreach ($relationships as $rel) : ?>
                        <div class="saga-relationship-item">
                            <span class="relationship-type"><?php echo esc_html($rel['type']); ?>:</span>
                            <a href="<?php echo esc_url(get_permalink($rel['id'])); ?>">
                                <?php echo esc_html($rel['title']); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $relationships_html = ob_get_clean();

                saga_collapsible_section([
                    'id' => 'relationships',
                    'title' => __('Relationships', 'saga-manager'),
                    'content' => $relationships_html,
                    'expanded' => false,
                    'icon' => 'users',
                ]);
            }
            ?>

            <!-- Timeline Section (collapsed by default) -->
            <?php
            $timeline = saga_get_entity_timeline($post_id);
            if (!empty($timeline)) {
                ob_start();
                ?>
                <div class="saga-timeline">
                    <?php foreach ($timeline as $event) : ?>
                        <div class="saga-timeline-event">
                            <time class="timeline-date"><?php echo esc_html($event['date']); ?></time>
                            <div class="timeline-content">
                                <h4><?php echo esc_html($event['title']); ?></h4>
                                <p><?php echo esc_html($event['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $timeline_html = ob_get_clean();

                saga_collapsible_section([
                    'id' => 'timeline',
                    'title' => __('Timeline', 'saga-manager'),
                    'content' => $timeline_html,
                    'expanded' => false,
                    'icon' => 'clock',
                ]);
            }
            ?>

            <!-- Quotes Section (collapsed by default) -->
            <?php
            $quotes = get_post_meta($post_id, '_saga_character_quotes', true);
            if (!empty($quotes)) {
                $quotes_array = explode("\n", $quotes);
                $quotes_html = '<div class="saga-quotes">';
                foreach ($quotes_array as $quote) {
                    $quote = trim($quote);
                    if (!empty($quote)) {
                        $quotes_html .= sprintf('<blockquote>%s</blockquote>', esc_html($quote));
                    }
                }
                $quotes_html .= '</div>';

                saga_collapsible_section([
                    'id' => 'quotes',
                    'title' => __('Notable Quotes', 'saga-manager'),
                    'content' => $quotes_html,
                    'expanded' => false,
                    'icon' => 'quote',
                ]);
            }
            ?>

            <!-- Bottom Controls (optional) -->
            <?php saga_collapsible_controls(['position' => 'bottom']); ?>

        </div><!-- .container -->

    </article>

<?php
endwhile;

get_footer();

// Example 2: Programmatic section configuration
// ----------------------------------------------
/**
 * Get all character sections with their content
 */
function example_get_character_sections($post_id) {
    $sections = [];

    // Biography
    $biography = get_post_meta($post_id, '_saga_character_biography', true);
    if (!empty($biography)) {
        $sections[] = [
            'id' => 'biography',
            'title' => __('Biography', 'saga-manager'),
            'content' => wp_kses_post($biography),
            'expanded' => true,
            'icon' => 'user',
        ];
    }

    // Attributes
    $attributes = saga_get_entity_attributes($post_id);
    if (!empty($attributes)) {
        $content = '<dl class="saga-attributes">';
        foreach ($attributes as $attr) {
            $content .= sprintf(
                '<dt>%s</dt><dd>%s</dd>',
                esc_html($attr['label']),
                esc_html($attr['value'])
            );
        }
        $content .= '</dl>';

        $sections[] = [
            'id' => 'attributes',
            'title' => __('Attributes', 'saga-manager'),
            'content' => $content,
            'expanded' => true,
            'icon' => 'list',
        ];
    }

    // Add more sections...

    return $sections;
}

/**
 * Render all character sections
 */
function example_render_character_sections($post_id) {
    $sections = example_get_character_sections($post_id);

    // Controls at top
    saga_collapsible_controls();

    // Render each section
    foreach ($sections as $section) {
        saga_collapsible_section($section);
    }
}

// Example 3: Using section configs from helper
// ----------------------------------------------
function example_render_entity_sections($post_id, $entity_type) {
    // Get section definitions for entity type
    $section_defs = saga_get_entity_sections($entity_type);

    // Controls
    saga_collapsible_controls();

    // Render sections dynamically
    foreach ($section_defs as $section_id => $section_def) {
        // Get content based on section ID
        $content = example_get_section_content($post_id, $section_id, $entity_type);

        if (!empty($content)) {
            saga_collapsible_section([
                'id' => $section_id,
                'title' => $section_def['title'],
                'content' => $content,
                'expanded' => $section_def['expanded'],
                'icon' => $section_def['icon'],
            ]);
        }
    }
}

/**
 * Get content for a specific section
 */
function example_get_section_content($post_id, $section_id, $entity_type) {
    // Map section IDs to meta keys or functions
    $content_map = [
        'biography' => fn() => get_post_meta($post_id, '_saga_character_biography', true),
        'description' => fn() => get_the_content(null, false, $post_id),
        'attributes' => fn() => example_render_attributes($post_id),
        'relationships' => fn() => example_render_relationships($post_id),
        'timeline' => fn() => example_render_timeline($post_id),
        'quotes' => fn() => example_render_quotes($post_id),
    ];

    if (isset($content_map[$section_id]) && is_callable($content_map[$section_id])) {
        return $content_map[$section_id]();
    }

    return '';
}

// Example 4: JavaScript API usage
// ----------------------------------------------
?>
<script>
// Wait for sections to initialize
document.addEventListener('DOMContentLoaded', function() {
    // Expand a specific section
    window.sagaCollapsibleAPI.expand('biography');

    // Collapse a specific section
    window.sagaCollapsibleAPI.collapse('timeline');

    // Toggle a section
    window.sagaCollapsibleAPI.toggle('relationships');

    // Expand all sections
    document.querySelector('.custom-expand-all-btn')?.addEventListener('click', function() {
        window.sagaCollapsibleAPI.expandAll();
    });

    // Get current states
    const states = window.sagaCollapsibleAPI.getStates();
    console.log('Section states:', states);

    // Reset to defaults
    window.sagaCollapsibleAPI.reset();

    // Listen for section toggle events
    document.addEventListener('saga:section:toggle', function(event) {
        console.log('Section toggled:', event.detail);
        // { sectionId: 'biography', expanded: true }
    });
});
</script>

<?php
// Example 5: Deep linking usage
// ----------------------------------------------
?>
<!-- Link to specific section -->
<a href="<?php echo esc_url(get_permalink($post_id) . '#biography'); ?>">
    View Biography
</a>

<!-- Section will auto-expand and scroll into view -->

<?php
// Example 6: Custom section classes and heading levels
// ----------------------------------------------
saga_collapsible_section([
    'id' => 'custom-section',
    'title' => __('Custom Section', 'saga-manager'),
    'content' => '<p>Custom content here</p>',
    'expanded' => false,
    'icon' => 'star',
    'heading_level' => 'h2', // Use h2 instead of default h3
    'classes' => ['custom-class', 'highlighted'], // Additional CSS classes
]);

// Example 7: Filter sections
// ----------------------------------------------
add_filter('saga_entity_sections', function($sections, $entity_type) {
    // Add custom section for characters
    if ($entity_type === 'character') {
        $sections['combat_stats'] = [
            'title' => __('Combat Statistics', 'saga-manager'),
            'icon' => 'sword',
            'expanded' => false,
        ];
    }

    // Remove quotes section
    unset($sections['quotes']);

    return $sections;
}, 10, 2);
