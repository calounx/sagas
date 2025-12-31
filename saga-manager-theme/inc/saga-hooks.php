<?php
/**
 * GeneratePress Hook Integrations
 *
 * Customize GeneratePress layouts and functionality for saga entities
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
 * Customize sidebar layout for saga entities
 *
 * @param string $layout Current layout
 * @return string Modified layout
 */
function saga_customize_sidebar_layout(string $layout): string
{
    if (is_singular('saga_entity')) {
        return 'right-sidebar'; // Force sidebar on entity pages
    }

    if (is_post_type_archive('saga_entity') || is_tax('saga_type')) {
        return 'no-sidebar'; // Full width for archives
    }

    return $layout;
}
add_filter('generate_sidebar_layout', 'saga_customize_sidebar_layout');

/**
 * Add custom content before entity content
 *
 * @return void
 */
function saga_before_entity_content(): void
{
    if (!is_singular('saga_entity')) {
        return;
    }

    // Display entity metadata before content
    get_template_part('template-parts/saga/entity', 'meta');
}
add_action('generate_before_content', 'saga_before_entity_content');

/**
 * Add custom content after entity content
 *
 * @return void
 */
function saga_after_entity_content(): void
{
    if (!is_singular('saga_entity')) {
        return;
    }

    // Display relationships after content
    if (saga_entity_has_relationships(get_the_ID())) {
        get_template_part('template-parts/saga/entity', 'relationships');
    }

    // Display timeline events after content
    if (saga_entity_has_timeline_events(get_the_ID())) {
        get_template_part('template-parts/saga/entity', 'timeline');
    }

    // Display navigation
    get_template_part('template-parts/saga/entity', 'navigation');
}
add_action('generate_after_entry_content', 'saga_after_entity_content');

/**
 * Customize archive header for entity archives
 *
 * @return void
 */
function saga_customize_archive_header(): void
{
    if (!is_post_type_archive('saga_entity') && !is_tax('saga_type')) {
        return;
    }

    // Remove default archive title
    remove_action('generate_archive_title', 'generate_archive_title');

    // Add custom archive header
    echo '<div class="saga-archive-header">';

    if (is_tax('saga_type')) {
        $term = get_queried_object();
        echo '<h1 class="saga-archive-header__title">' . esc_html($term->name) . '</h1>';
        if ($term->description) {
            echo '<p class="saga-archive-header__description">' . esc_html($term->description) . '</p>';
        }
    } else {
        echo '<h1 class="saga-archive-header__title">' . esc_html__('All Entities', 'saga-manager-theme') . '</h1>';
        echo '<p class="saga-archive-header__description">' . 
             esc_html__('Browse all saga entities across different universes and types.', 'saga-manager-theme') . 
             '</p>';
    }

    // Display count and sort options
    global $wp_query;
    echo '<div class="saga-archive-header__meta">';
    echo '<span class="saga-archive-header__count">' . 
         sprintf(
             esc_html(_n('%s entity found', '%s entities found', $wp_query->found_posts, 'saga-manager-theme')),
             number_format_i18n($wp_query->found_posts)
         ) . 
         '</span>';

    // Sort dropdown
    $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'importance';
    echo '<div class="saga-archive-header__sort">';
    echo '<label for="saga-sort">' . esc_html__('Sort by:', 'saga-manager-theme') . '</label>';
    echo '<select id="saga-sort" name="orderby" onchange="location = this.value;">';
    $options = [
        'importance' => __('Importance', 'saga-manager-theme'),
        'title' => __('Name', 'saga-manager-theme'),
        'date' => __('Date Added', 'saga-manager-theme'),
    ];
    foreach ($options as $value => $label) {
        $url = add_query_arg('orderby', $value);
        printf(
            '<option value="%s"%s>%s</option>',
            esc_url($url),
            selected($current_orderby, $value, false),
            esc_html($label)
        );
    }
    echo '</select>';
    echo '</div>';

    echo '</div>'; // .saga-archive-header__meta
    echo '</div>'; // .saga-archive-header
}
add_action('generate_before_main_content', 'saga_customize_archive_header');

/**
 * Add search filters before archive loop
 *
 * @return void
 */
function saga_add_archive_filters(): void
{
    if (!is_post_type_archive('saga_entity') && !is_tax('saga_type')) {
        return;
    }

    get_template_part('template-parts/saga/entity-search', 'filters');
}
add_action('generate_before_main_content', 'saga_add_archive_filters', 15);

/**
 * Modify post classes for entity cards
 *
 * @param array $classes Existing post classes
 * @return array Modified post classes
 */
function saga_post_classes(array $classes): array
{
    if (is_post_type_archive('saga_entity') || is_tax('saga_type')) {
        $classes[] = 'saga-entity-card-wrapper';
    }

    return $classes;
}
add_filter('post_class', 'saga_post_classes');

/**
 * Customize breadcrumbs for saga entities
 *
 * @param array $breadcrumbs Existing breadcrumbs
 * @return array Modified breadcrumbs
 */
function saga_customize_breadcrumbs(array $breadcrumbs): array
{
    if (!is_singular('saga_entity')) {
        return $breadcrumbs;
    }

    $entity_type = saga_get_entity_type(get_the_ID());

    if ($entity_type) {
        // Add entity type to breadcrumbs
        $term = get_term_by('slug', $entity_type, 'saga_type');
        if ($term) {
            $breadcrumbs[] = [
                'url' => get_term_link($term),
                'text' => $term->name,
            ];
        }
    }

    return $breadcrumbs;
}
add_filter('generate_breadcrumbs', 'saga_customize_breadcrumbs');

/**
 * Use custom sidebar for entity pages
 *
 * @param string $sidebar Current sidebar ID
 * @return string Modified sidebar ID
 */
function saga_custom_sidebar(string $sidebar): string
{
    if (is_singular('saga_entity')) {
        return 'saga-entity-sidebar';
    }

    if (is_post_type_archive('saga_entity') || is_tax('saga_type')) {
        return 'saga-archive-sidebar';
    }

    return $sidebar;
}
add_filter('generate_sidebar', 'saga_custom_sidebar');
