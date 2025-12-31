<?php
/**
 * AJAX endpoint for infinite scroll load more functionality
 *
 * Handles paginated entity loading for masonry grid with infinite scroll
 *
 * @package SagaTheme
 * @version 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for loading more entities (infinite scroll)
 *
 * @return void
 */
function saga_ajax_load_more_entities(): void
{
    // Verify nonce for security
    check_ajax_referer('saga_load_more_nonce', 'nonce');

    // Sanitize input parameters
    $page = absint($_POST['page'] ?? 1);
    $per_page = absint($_POST['per_page'] ?? 12);
    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;
    $entity_type = isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'importance';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Build WP_Query arguments
    $args = [
        'post_type' => 'saga_entity',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'no_found_rows' => false, // Need total count
    ];

    // Add meta query for saga ID and other filters
    $meta_query = ['relation' => 'AND'];

    if ($saga_id > 0) {
        $meta_query[] = [
            'key' => '_saga_id',
            'value' => $saga_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ];
    }

    if (!empty($meta_query) && count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // Add taxonomy filter for entity type
    if (!empty($entity_type)) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'saga_type',
                'field' => 'slug',
                'terms' => $entity_type,
            ],
        ];
    }

    // Add search query
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Handle ordering
    switch ($orderby) {
        case 'importance':
            $args['meta_key'] = '_saga_importance_score';
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'DESC';
            break;
        case 'title':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'date':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
    }

    // Execute query
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        wp_send_json_success([
            'html' => '',
            'page' => $page,
            'has_more' => false,
            'total_pages' => 0,
            'found_posts' => 0,
        ]);
        return;
    }

    // Render entity cards
    ob_start();
    while ($query->have_posts()) {
        $query->the_post();
        get_template_part('template-parts/entity-card-masonry', null, [
            'entity_id' => get_the_ID(),
        ]);
    }
    $html = ob_get_clean();

    // Reset post data
    wp_reset_postdata();

    // Calculate if there are more pages
    $has_more = $page < $query->max_num_pages;

    // Send JSON response
    wp_send_json_success([
        'html' => $html,
        'page' => $page,
        'has_more' => $has_more,
        'total_pages' => $query->max_num_pages,
        'found_posts' => $query->found_posts,
    ]);
}

// Register AJAX handlers
add_action('wp_ajax_saga_load_more', 'saga_ajax_load_more_entities');
add_action('wp_ajax_nopriv_saga_load_more', 'saga_ajax_load_more_entities');

/**
 * Helper function to get entity card data for AJAX response
 *
 * @param int $entity_id Entity post ID
 * @return array Entity data
 */
function saga_get_entity_card_data(int $entity_id): array
{
    $entity = saga_get_entity($entity_id);

    if (!$entity) {
        return [];
    }

    $thumbnail_url = get_the_post_thumbnail_url($entity_id, 'large');
    $entity_type = $entity->entity_type ?? '';
    $importance = $entity->importance_score ?? 50;

    return [
        'id' => $entity_id,
        'title' => get_the_title($entity_id),
        'permalink' => get_permalink($entity_id),
        'excerpt' => get_the_excerpt($entity_id),
        'thumbnail' => $thumbnail_url ?: SAGA_THEME_URI . '/assets/images/placeholder.jpg',
        'entity_type' => $entity_type,
        'importance' => $importance,
    ];
}
