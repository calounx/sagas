<?php
/**
 * Reading Mode Helper Functions
 *
 * Provides helper functions for integrating reading mode functionality
 * into saga entity templates
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
 * Display reading mode button
 *
 * Renders a button to enter reading mode for the current entity
 *
 * @param array $args Optional arguments for button customization
 * @return void
 */
function saga_reading_mode_button(array $args = []): void
{
    $defaults = [
        'text' => __('Reading Mode', 'saga-manager-theme'),
        'icon' => true,
        'class' => 'saga-reading-mode-button',
        'show_on' => ['saga_entity'], // Post types to show on
    ];

    $args = wp_parse_args($args, $defaults);

    // Check if we should show the button
    if (!saga_should_show_reading_mode_button($args['show_on'])) {
        return;
    }

    $classes = esc_attr($args['class']);
    $text = esc_html($args['text']);

    $icon_svg = '';
    if ($args['icon']) {
        $icon_svg = saga_reading_mode_icon();
    }

    printf(
        '<button class="%s" type="button" aria-label="%s">%s%s</button>',
        $classes,
        esc_attr__('Enter reading mode', 'saga-manager-theme'),
        $icon_svg,
        $text
    );
}

/**
 * Check if reading mode button should be displayed
 *
 * @param array $show_on Post types to show on
 * @return bool True if button should be displayed
 */
function saga_should_show_reading_mode_button(array $show_on): bool
{
    // Check if we're on a singular post
    if (!is_singular()) {
        return false;
    }

    // Check if current post type is in allowed list
    $current_post_type = get_post_type();
    if (!in_array($current_post_type, $show_on, true)) {
        return false;
    }

    // Check if there's content to read
    $post = get_post();
    if (!$post || empty($post->post_content)) {
        return false;
    }

    return apply_filters('saga_show_reading_mode_button', true, $post);
}

/**
 * Get reading mode icon SVG
 *
 * @return string SVG markup for reading mode icon
 */
function saga_reading_mode_icon(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
    </svg>';
}

/**
 * Enqueue reading mode assets
 *
 * @return void
 */
function saga_enqueue_reading_mode_assets(): void
{
    // Only load on singular posts where button might appear
    if (!is_singular()) {
        return;
    }

    // Enqueue reading mode CSS
    wp_enqueue_style(
        'saga-reading-mode',
        SAGA_THEME_URI . '/assets/css/reading-mode.css',
        [],
        SAGA_THEME_VERSION,
        'all'
    );

    // Enqueue reading mode JavaScript
    wp_enqueue_script(
        'saga-reading-mode',
        SAGA_THEME_URI . '/assets/js/reading-mode.js',
        [],
        SAGA_THEME_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_reading_mode_assets');

/**
 * Add reading mode button to entity content
 *
 * Automatically adds reading mode button before entity content
 *
 * @param string $content Post content
 * @return string Modified content with reading mode button
 */
function saga_add_reading_mode_button_to_content(string $content): string
{
    // Only on singular saga entities
    if (!is_singular('saga_entity') || !is_main_query() || !in_the_loop()) {
        return $content;
    }

    // Check if auto-insert is enabled
    if (!apply_filters('saga_auto_insert_reading_mode_button', true)) {
        return $content;
    }

    // Build button HTML
    ob_start();
    echo '<div class="saga-reading-mode-button-wrapper" style="margin-bottom: 2rem;">';
    saga_reading_mode_button();
    echo '</div>';
    $button_html = ob_get_clean();

    return $button_html . $content;
}
add_filter('the_content', 'saga_add_reading_mode_button_to_content', 5);

/**
 * Get reading mode styles for customization
 *
 * Allows theme customizer or settings to override reading mode styles
 *
 * @return array Associative array of CSS custom properties
 */
function saga_get_reading_mode_custom_styles(): array
{
    $defaults = [
        '--rm-max-width' => '680px',
        '--rm-font-family-serif' => 'Georgia, "Times New Roman", serif',
        '--rm-font-family-sans' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        '--rm-progress-color-start' => '#3b82f6',
        '--rm-progress-color-end' => '#8b5cf6',
    ];

    return apply_filters('saga_reading_mode_custom_styles', $defaults);
}

/**
 * Output custom reading mode styles
 *
 * @return void
 */
function saga_output_reading_mode_custom_styles(): void
{
    if (!is_singular()) {
        return;
    }

    $custom_styles = saga_get_reading_mode_custom_styles();

    if (empty($custom_styles)) {
        return;
    }

    echo '<style id="saga-reading-mode-custom-styles">';
    echo '.reading-mode {';

    foreach ($custom_styles as $property => $value) {
        printf(
            '%s: %s;',
            esc_html($property),
            esc_html($value)
        );
    }

    echo '}';
    echo '</style>';
}
add_action('wp_head', 'saga_output_reading_mode_custom_styles', 100);

/**
 * Add body class when reading mode is available
 *
 * @param array $classes Existing body classes
 * @return array Modified body classes
 */
function saga_reading_mode_body_class(array $classes): array
{
    if (saga_should_show_reading_mode_button(['saga_entity'])) {
        $classes[] = 'has-reading-mode';
    }

    return $classes;
}
add_filter('body_class', 'saga_reading_mode_body_class');

/**
 * Calculate estimated reading time for content
 *
 * @param string $content Content to analyze
 * @param int $words_per_minute Average reading speed (default: 200)
 * @return int Estimated reading time in minutes
 */
function saga_calculate_reading_time(string $content, int $words_per_minute = 200): int
{
    // Strip HTML tags and shortcodes
    $text = wp_strip_all_tags(strip_shortcodes($content));

    // Count words
    $word_count = str_word_count($text);

    // Calculate minutes (minimum 1 minute)
    $minutes = max(1, (int) ceil($word_count / $words_per_minute));

    return $minutes;
}

/**
 * Get reading mode meta information
 *
 * Returns metadata useful for displaying in reading mode
 *
 * @param int|null $post_id Post ID (default: current post)
 * @return array Meta information
 */
function saga_get_reading_mode_meta(?int $post_id = null): array
{
    if ($post_id === null) {
        $post_id = get_the_ID();
    }

    $post = get_post($post_id);

    if (!$post) {
        return [];
    }

    $meta = [
        'title' => get_the_title($post_id),
        'reading_time' => saga_calculate_reading_time($post->post_content),
        'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
        'published_date' => get_the_date('', $post_id),
        'modified_date' => get_the_modified_date('', $post_id),
    ];

    // Add entity-specific meta
    if (get_post_type($post_id) === 'saga_entity') {
        $entity_type = saga_get_entity_type($post_id);
        if ($entity_type) {
            $meta['entity_type'] = $entity_type;
        }

        $importance = get_post_meta($post_id, '_saga_importance_score', true);
        if ($importance !== '') {
            $meta['importance_score'] = (int) $importance;
        }
    }

    return apply_filters('saga_reading_mode_meta', $meta, $post_id);
}

/**
 * Add reading mode support to custom post types
 *
 * @param array $post_types Post types to add reading mode support to
 * @return void
 */
function saga_add_reading_mode_support(array $post_types): void
{
    foreach ($post_types as $post_type) {
        add_post_type_support($post_type, 'saga-reading-mode');
    }
}

/**
 * Check if post type supports reading mode
 *
 * @param string $post_type Post type to check
 * @return bool True if supported
 */
function saga_post_type_supports_reading_mode(string $post_type): bool
{
    return post_type_supports($post_type, 'saga-reading-mode');
}

/**
 * Register reading mode support for saga entities
 *
 * @return void
 */
function saga_register_reading_mode_support(): void
{
    saga_add_reading_mode_support(['saga_entity', 'post', 'page']);
}
add_action('init', 'saga_register_reading_mode_support');

/**
 * Add screen reader only class for accessibility
 *
 * @return void
 */
function saga_add_sr_only_styles(): void
{
    echo '<style>
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border-width: 0;
    }
    </style>';
}
add_action('wp_head', 'saga_add_sr_only_styles', 1);

/**
 * Add reading mode settings to theme customizer
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager
 * @return void
 */
function saga_reading_mode_customizer_settings(WP_Customize_Manager $wp_customize): void
{
    // Add reading mode section
    $wp_customize->add_section('saga_reading_mode', [
        'title' => __('Reading Mode', 'saga-manager-theme'),
        'description' => __('Customize the reading mode experience', 'saga-manager-theme'),
        'priority' => 160,
    ]);

    // Auto-insert button setting
    $wp_customize->add_setting('saga_reading_mode_auto_insert', [
        'default' => true,
        'sanitize_callback' => 'wp_validate_boolean',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_reading_mode_auto_insert', [
        'label' => __('Auto-insert Reading Mode Button', 'saga-manager-theme'),
        'description' => __('Automatically add reading mode button before content', 'saga-manager-theme'),
        'section' => 'saga_reading_mode',
        'type' => 'checkbox',
    ]);

    // Default theme setting
    $wp_customize->add_setting('saga_reading_mode_default_theme', [
        'default' => 'sepia',
        'sanitize_callback' => 'sanitize_text_field',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_reading_mode_default_theme', [
        'label' => __('Default Theme', 'saga-manager-theme'),
        'section' => 'saga_reading_mode',
        'type' => 'select',
        'choices' => [
            'light' => __('Light', 'saga-manager-theme'),
            'sepia' => __('Sepia', 'saga-manager-theme'),
            'dark' => __('Dark', 'saga-manager-theme'),
            'black' => __('Black', 'saga-manager-theme'),
        ],
    ]);
}
add_action('customize_register', 'saga_reading_mode_customizer_settings');

/**
 * Get theme customizer option
 *
 * @param string $option_name Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
function saga_get_option(string $option_name, $default = null)
{
    return get_theme_mod($option_name, $default);
}
