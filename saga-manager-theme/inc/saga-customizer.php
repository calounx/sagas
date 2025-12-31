<?php
/**
 * WordPress Customizer Additions
 *
 * Add customizer options for saga entity display
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
 * Register customizer settings
 *
 * @param WP_Customize_Manager $wp_customize Customizer object
 * @return void
 */
function saga_customize_register(WP_Customize_Manager $wp_customize): void
{
    // Add Saga Manager section
    $wp_customize->add_section('saga_manager_options', [
        'title' => __('Saga Manager Settings', 'saga-manager-theme'),
        'priority' => 130,
        'description' => __('Customize the appearance of saga entities.', 'saga-manager-theme'),
    ]);

    // Entity card layout
    $wp_customize->add_setting('saga_card_layout', [
        'default' => 'grid',
        'sanitize_callback' => 'saga_sanitize_card_layout',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_card_layout', [
        'label' => __('Entity Card Layout', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'select',
        'choices' => [
            'grid' => __('Grid', 'saga-manager-theme'),
            'list' => __('List', 'saga-manager-theme'),
            'masonry' => __('Masonry', 'saga-manager-theme'),
        ],
    ]);

    // Show importance score
    $wp_customize->add_setting('saga_show_importance', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_show_importance', [
        'label' => __('Show Importance Score', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'checkbox',
        'description' => __('Display importance score on entity cards and pages.', 'saga-manager-theme'),
    ]);

    // Show entity type badges
    $wp_customize->add_setting('saga_show_type_badge', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_show_type_badge', [
        'label' => __('Show Entity Type Badges', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'checkbox',
        'description' => __('Display entity type badges on cards and archives.', 'saga-manager-theme'),
    ]);

    // Entities per page
    $wp_customize->add_setting('saga_entities_per_page', [
        'default' => 12,
        'sanitize_callback' => 'absint',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_entities_per_page', [
        'label' => __('Entities Per Page', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'number',
        'input_attrs' => [
            'min' => 6,
            'max' => 48,
            'step' => 6,
        ],
    ]);

    // Show relationships on single
    $wp_customize->add_setting('saga_show_relationships', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_show_relationships', [
        'label' => __('Show Relationships', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'checkbox',
        'description' => __('Display entity relationships on single entity pages.', 'saga-manager-theme'),
    ]);

    // Show timeline events
    $wp_customize->add_setting('saga_show_timeline', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'transport' => 'refresh',
    ]);

    $wp_customize->add_control('saga_show_timeline', [
        'label' => __('Show Timeline Events', 'saga-manager-theme'),
        'section' => 'saga_manager_options',
        'type' => 'checkbox',
        'description' => __('Display timeline events on single entity pages.', 'saga-manager-theme'),
    ]);
}
add_action('customize_register', 'saga_customize_register');

/**
 * Sanitize card layout option
 *
 * @param string $input User input
 * @return string Sanitized value
 */
function saga_sanitize_card_layout(string $input): string
{
    $valid = ['grid', 'list', 'masonry'];
    return in_array($input, $valid, true) ? $input : 'grid';
}

/**
 * Get customizer option value
 *
 * @param string $option Option name
 * @param mixed $default Default value
 * @return mixed Option value
 */
function saga_get_option(string $option, $default = null)
{
    return get_theme_mod($option, $default);
}
