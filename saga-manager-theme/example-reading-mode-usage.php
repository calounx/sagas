<?php
/**
 * Reading Mode - Usage Examples
 *
 * This file demonstrates various ways to integrate and customize
 * the reading mode feature in your Saga Manager theme templates
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * =============================================================================
 * BASIC USAGE EXAMPLES
 * =============================================================================
 */

/**
 * Example 1: Basic Reading Mode Button
 *
 * Display reading mode button with default settings
 */
function example_basic_button(): void
{
    ?>
    <div class="content-actions">
        <?php saga_reading_mode_button(); ?>
    </div>
    <?php
}

/**
 * Example 2: Custom Button Text and Styling
 *
 * Customize button appearance and text
 */
function example_custom_button(): void
{
    ?>
    <div class="content-actions">
        <?php
        saga_reading_mode_button([
            'text' => 'Focus Mode',
            'icon' => true,
            'class' => 'saga-reading-mode-button custom-button',
        ]);
        ?>
    </div>
    <?php
}

/**
 * Example 3: Button Without Icon
 *
 * Display text-only button
 */
function example_button_no_icon(): void
{
    ?>
    <?php
    saga_reading_mode_button([
        'text' => 'Read Article',
        'icon' => false,
    ]);
    ?>
    <?php
}

/**
 * =============================================================================
 * CONDITIONAL DISPLAY EXAMPLES
 * =============================================================================
 */

/**
 * Example 4: Show Only for Long Content
 *
 * Display reading mode button only for posts with 500+ words
 */
function example_conditional_long_content(): void
{
    $content = get_the_content();
    $word_count = str_word_count(wp_strip_all_tags($content));

    if ($word_count >= 500) {
        saga_reading_mode_button([
            'text' => sprintf(
                __('%d min read', 'saga-manager-theme'),
                saga_calculate_reading_time($content)
            ),
        ]);
    }
}

/**
 * Example 5: Show Only for Specific Entity Types
 *
 * Display reading mode button only for character entities
 */
function example_conditional_entity_type(): void
{
    if (!is_singular('saga_entity')) {
        return;
    }

    $entity_type = saga_get_entity_type(get_the_ID());

    if ($entity_type === 'character') {
        saga_reading_mode_button([
            'text' => __('Read Character Bio', 'saga-manager-theme'),
        ]);
    }
}

/**
 * Example 6: Show Based on Custom Field
 *
 * Display reading mode button only if enabled via custom field
 */
function example_conditional_custom_field(): void
{
    $enable_reading_mode = get_post_meta(get_the_ID(), '_enable_reading_mode', true);

    if ($enable_reading_mode) {
        saga_reading_mode_button();
    }
}

/**
 * =============================================================================
 * INTEGRATION WITH META INFORMATION
 * =============================================================================
 */

/**
 * Example 7: Display Reading Time with Button
 *
 * Show estimated reading time alongside button
 */
function example_with_reading_time(): void
{
    $meta = saga_get_reading_mode_meta();
    ?>
    <div class="content-actions">
        <div class="reading-time">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
            <span><?php printf(__('%d min read', 'saga-manager-theme'), $meta['reading_time']); ?></span>
        </div>
        <?php saga_reading_mode_button(); ?>
    </div>
    <?php
}

/**
 * Example 8: Display Entity Type Badge with Button
 *
 * Combine entity type information with reading mode button
 */
function example_with_entity_badge(): void
{
    $meta = saga_get_reading_mode_meta();
    ?>
    <div class="entity-actions">
        <?php if (isset($meta['entity_type'])): ?>
            <span class="entity-type-badge">
                <?php echo esc_html(ucfirst($meta['entity_type'])); ?>
            </span>
        <?php endif; ?>
        <?php saga_reading_mode_button(); ?>
    </div>
    <?php
}

/**
 * =============================================================================
 * TEMPLATE INTEGRATION EXAMPLES
 * =============================================================================
 */

/**
 * Example 9: Add to Article Header
 *
 * Integrate reading mode button into article header
 */
function example_article_header_integration(): void
{
    ?>
    <article <?php post_class('saga-entity-article'); ?>>
        <header class="entry-header">
            <div class="entry-header__top">
                <h1 class="entry-title"><?php the_title(); ?></h1>
                <div class="entry-actions">
                    <?php saga_reading_mode_button(); ?>
                    <?php saga_bookmark_button(); ?>
                </div>
            </div>

            <?php if (has_post_thumbnail()): ?>
                <div class="entry-thumbnail">
                    <?php the_post_thumbnail('large'); ?>
                </div>
            <?php endif; ?>
        </header>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
}

/**
 * Example 10: Sticky Action Bar
 *
 * Create sticky action bar with reading mode button
 */
function example_sticky_action_bar(): void
{
    ?>
    <div class="saga-sticky-actions" style="position: sticky; top: 0; background: white; padding: 1rem; z-index: 100; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="post-title-compact">
                <?php echo esc_html(wp_trim_words(get_the_title(), 8)); ?>
            </div>
            <div class="actions" style="display: flex; gap: 0.5rem;">
                <?php saga_reading_mode_button(['text' => 'Read', 'icon' => true]); ?>
                <button class="share-button">Share</button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * =============================================================================
 * CUSTOMIZATION VIA FILTERS
 * =============================================================================
 */

/**
 * Example 11: Disable Auto-Insert
 *
 * Prevent automatic button insertion before content
 */
function example_disable_auto_insert(): void
{
    add_filter('saga_auto_insert_reading_mode_button', '__return_false');
}
// add_action('init', 'example_disable_auto_insert');

/**
 * Example 12: Custom Default Theme
 *
 * Set dark theme as default for reading mode
 */
function example_custom_default_theme(string $theme): string
{
    return 'dark'; // light, sepia, dark, or black
}
// add_filter('saga_reading_mode_default_theme', 'example_custom_default_theme');

/**
 * Example 13: Customize Reading Mode Meta
 *
 * Add custom metadata to reading mode display
 */
function example_custom_meta(array $meta, int $post_id): array
{
    // Add author information
    $meta['author'] = get_the_author_meta('display_name', get_post_field('post_author', $post_id));

    // Add custom taxonomy
    $terms = get_the_terms($post_id, 'saga_type');
    if ($terms && !is_wp_error($terms)) {
        $meta['saga_type'] = $terms[0]->name;
    }

    return $meta;
}
// add_filter('saga_reading_mode_meta', 'example_custom_meta', 10, 2);

/**
 * Example 14: Modify Custom Styles
 *
 * Customize reading mode CSS custom properties
 */
function example_custom_styles(array $styles): array
{
    // Wider reading area
    $styles['--rm-max-width'] = '800px';

    // Custom font family
    $styles['--rm-font-family-serif'] = 'Merriweather, Georgia, serif';

    // Custom progress bar gradient
    $styles['--rm-progress-color-start'] = '#f59e0b';
    $styles['--rm-progress-color-end'] = '#ef4444';

    return $styles;
}
// add_filter('saga_reading_mode_custom_styles', 'example_custom_styles');

/**
 * Example 15: Conditional Button Display
 *
 * Show button only for content longer than threshold
 */
function example_conditional_display(bool $show, WP_Post $post): bool
{
    // Only show for posts with 1000+ words
    $word_count = str_word_count(wp_strip_all_tags($post->post_content));

    if ($word_count < 1000) {
        return false;
    }

    // Don't show for specific categories
    if (has_category('news', $post)) {
        return false;
    }

    return $show;
}
// add_filter('saga_show_reading_mode_button', 'example_conditional_display', 10, 2);

/**
 * =============================================================================
 * JAVASCRIPT INTEGRATION EXAMPLES
 * =============================================================================
 */

/**
 * Example 16: Auto-Enter Reading Mode from URL
 *
 * Automatically enter reading mode if URL parameter is present
 */
function example_auto_enter_from_url(): void
{
    if (!is_singular('saga_entity')) {
        return;
    }
    ?>
    <script>
    // Auto-enter reading mode if ?reading-mode=1 is in URL
    if (window.location.search.includes('reading-mode=1')) {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.sagaReadingMode) {
                window.sagaReadingMode.enter();
            }
        });
    }
    </script>
    <?php
}
// add_action('wp_footer', 'example_auto_enter_from_url');

/**
 * Example 17: Custom Keyboard Shortcut
 *
 * Add custom keyboard shortcut to enter reading mode
 */
function example_custom_keyboard_shortcut(): void
{
    ?>
    <script>
    document.addEventListener('keydown', function(e) {
        // Press 'R' to enter reading mode
        if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (document.activeElement.tagName !== 'INPUT' &&
                document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                if (window.sagaReadingMode) {
                    window.sagaReadingMode.enter();
                }
            }
        }
    });
    </script>
    <?php
}
// add_action('wp_footer', 'example_custom_keyboard_shortcut');

/**
 * Example 18: Track Reading Mode Usage
 *
 * Send analytics when user enters reading mode
 */
function example_track_analytics(): void
{
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.saga-reading-mode-button');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                // Track with Google Analytics
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'reading_mode_entered', {
                        'event_category': 'engagement',
                        'event_label': document.title,
                    });
                }

                // Or track with custom analytics
                console.log('Reading mode entered:', {
                    post_id: <?php echo get_the_ID(); ?>,
                    post_title: <?php echo json_encode(get_the_title()); ?>,
                    timestamp: new Date().toISOString()
                });
            });
        });
    });
    </script>
    <?php
}
// add_action('wp_footer', 'example_track_analytics');

/**
 * =============================================================================
 * ADVANCED INTEGRATION EXAMPLES
 * =============================================================================
 */

/**
 * Example 19: Add Print Button to Reading Mode
 *
 * Extend reading mode controls with custom print button
 */
function example_add_print_button(): void
{
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for reading mode to be initialized
        const originalEnter = window.sagaReadingMode?.enter;

        if (originalEnter) {
            window.sagaReadingMode.enter = function() {
                originalEnter.call(this);

                // Add print button after controls are created
                setTimeout(() => {
                    const controls = document.querySelector('.reading-mode-controls__container');
                    if (controls) {
                        const printBtn = document.createElement('button');
                        printBtn.className = 'rm-print-btn';
                        printBtn.textContent = '🖨️';
                        printBtn.title = 'Print';
                        printBtn.onclick = () => window.print();
                        controls.appendChild(printBtn);
                    }
                }, 100);
            };
        }
    });
    </script>
    <?php
}
// add_action('wp_footer', 'example_add_print_button');

/**
 * Example 20: Save Reading Position
 *
 * Remember scroll position when exiting reading mode
 */
function example_save_reading_position(): void
{
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const STORAGE_KEY = 'saga_reading_position_' + <?php echo get_the_ID(); ?>;

        // Restore position when entering reading mode
        const originalEnter = window.sagaReadingMode?.enter;
        if (originalEnter) {
            window.sagaReadingMode.enter = function() {
                originalEnter.call(this);

                setTimeout(() => {
                    const savedPosition = localStorage.getItem(STORAGE_KEY);
                    if (savedPosition) {
                        const container = document.querySelector('.reading-mode');
                        if (container) {
                            container.scrollTop = parseInt(savedPosition);
                        }
                    }
                }, 100);
            };
        }

        // Save position when exiting
        const originalExit = window.sagaReadingMode?.exit;
        if (originalExit) {
            window.sagaReadingMode.exit = function() {
                const container = document.querySelector('.reading-mode');
                if (container) {
                    localStorage.setItem(STORAGE_KEY, container.scrollTop);
                }
                originalExit.call(this);
            };
        }
    });
    </script>
    <?php
}
// add_action('wp_footer', 'example_save_reading_position');

/**
 * =============================================================================
 * UTILITY EXAMPLES
 * =============================================================================
 */

/**
 * Example 21: Create Custom Reading Mode Widget
 *
 * Display reading statistics in a widget
 */
function example_reading_stats_widget(): void
{
    $meta = saga_get_reading_mode_meta();
    ?>
    <div class="widget saga-reading-stats">
        <h3 class="widget-title"><?php esc_html_e('Reading Info', 'saga-manager-theme'); ?></h3>
        <div class="widget-content">
            <ul class="reading-stats-list">
                <li>
                    <strong><?php esc_html_e('Reading Time:', 'saga-manager-theme'); ?></strong>
                    <?php printf(__('%d minutes', 'saga-manager-theme'), $meta['reading_time']); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Word Count:', 'saga-manager-theme'); ?></strong>
                    <?php echo number_format($meta['word_count']); ?>
                </li>
                <?php if (isset($meta['entity_type'])): ?>
                <li>
                    <strong><?php esc_html_e('Type:', 'saga-manager-theme'); ?></strong>
                    <?php echo esc_html(ucfirst($meta['entity_type'])); ?>
                </li>
                <?php endif; ?>
            </ul>
            <div class="widget-actions">
                <?php saga_reading_mode_button(['text' => __('Start Reading', 'saga-manager-theme')]); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Example 22: Share Reading Mode Link
 *
 * Generate shareable link that opens in reading mode
 */
function example_generate_reading_mode_link(int $post_id): string
{
    return add_query_arg('reading-mode', '1', get_permalink($post_id));
}

/**
 * Usage:
 * $reading_link = example_generate_reading_mode_link(get_the_ID());
 * echo '<a href="' . esc_url($reading_link) . '">Read in Reading Mode</a>';
 */

/**
 * =============================================================================
 * SHORTCODE EXAMPLES
 * =============================================================================
 */

/**
 * Example 23: Reading Mode Button Shortcode
 *
 * Create shortcode for reading mode button
 */
function example_reading_mode_shortcode(array $atts): string
{
    $atts = shortcode_atts([
        'text' => __('Reading Mode', 'saga-manager-theme'),
        'icon' => 'true',
    ], $atts);

    ob_start();
    saga_reading_mode_button([
        'text' => $atts['text'],
        'icon' => $atts['icon'] === 'true',
    ]);
    return ob_get_clean();
}
// add_shortcode('reading_mode', 'example_reading_mode_shortcode');

/**
 * Usage in content:
 * [reading_mode text="Focus Mode" icon="true"]
 */
