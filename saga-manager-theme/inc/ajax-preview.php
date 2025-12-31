<?php
declare(strict_types=1);

/**
 * AJAX Preview Handler
 *
 * Provides REST API endpoint for entity preview data
 *
 * @package SagaManagerTheme
 */

namespace SagaManagerTheme\AjaxPreview;

/**
 * Register REST API endpoints for entity previews
 */
function register_preview_endpoints(): void
{
    register_rest_route('saga/v1', '/entities/(?P<id>\d+)/preview', [
        'methods' => 'GET',
        'callback' => __NAMESPACE__ . '\\get_entity_preview',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => [
            'id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
}
add_action('rest_api_init', __NAMESPACE__ . '\\register_preview_endpoints');

/**
 * Get entity preview data
 *
 * @param \WP_REST_Request $request Request object
 * @return \WP_REST_Response|\WP_Error
 */
function get_entity_preview(\WP_REST_Request $request)
{
    $entity_id = absint($request['id']);

    // Check cache first
    $cache_key = "saga_preview_{$entity_id}";
    $cached = wp_cache_get($cache_key, 'saga_previews');

    if (false !== $cached) {
        return rest_ensure_response($cached);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'saga_entities';

    // Get entity data
    $entity = $wpdb->get_row($wpdb->prepare(
        "SELECT
            e.id,
            e.canonical_name,
            e.entity_type,
            e.slug,
            e.importance_score,
            e.wp_post_id,
            p.post_excerpt,
            p.post_content
        FROM {$table} e
        LEFT JOIN {$wpdb->posts} p ON e.wp_post_id = p.ID
        WHERE e.id = %d
        LIMIT 1",
        $entity_id
    ));

    if (!$entity) {
        return new \WP_Error(
            'entity_not_found',
            'Entity not found',
            ['status' => 404]
        );
    }

    // Get top attributes
    $attributes = get_entity_preview_attributes($entity_id, $entity->entity_type);

    // Get thumbnail
    $thumbnail_url = null;
    if ($entity->wp_post_id) {
        $thumbnail_id = get_post_thumbnail_id($entity->wp_post_id);
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, 'thumbnail');
        }
    }

    // Generate excerpt
    $excerpt = '';
    if (!empty($entity->post_excerpt)) {
        $excerpt = wp_trim_words($entity->post_excerpt, 20, '...');
    } elseif (!empty($entity->post_content)) {
        $excerpt = wp_trim_words(wp_strip_all_tags($entity->post_content), 20, '...');
    }

    // Build preview data
    $preview_data = [
        'id' => $entity->id,
        'title' => $entity->canonical_name,
        'type' => $entity->entity_type,
        'excerpt' => $excerpt,
        'thumbnail' => $thumbnail_url,
        'url' => $entity->wp_post_id ? get_permalink($entity->wp_post_id) : null,
        'importance' => $entity->importance_score,
        'attributes' => $attributes,
    ];

    // Cache for 1 hour
    wp_cache_set($cache_key, $preview_data, 'saga_previews', HOUR_IN_SECONDS);

    return rest_ensure_response($preview_data);
}

/**
 * Get top 3 preview-worthy attributes for an entity
 *
 * @param int $entity_id Entity ID
 * @param string $entity_type Entity type
 * @return array Array of attributes
 */
function get_entity_preview_attributes(int $entity_id, string $entity_type): array
{
    global $wpdb;

    $def_table = $wpdb->prefix . 'saga_attribute_definitions';
    $val_table = $wpdb->prefix . 'saga_attribute_values';

    // Priority attributes by entity type
    $priority_attrs = [
        'character' => ['species', 'affiliation', 'homeworld', 'birth_date'],
        'location' => ['type', 'planet', 'region', 'population'],
        'event' => ['date', 'location', 'participants', 'outcome'],
        'faction' => ['type', 'leader', 'founded', 'allegiance'],
        'artifact' => ['type', 'creator', 'power', 'location'],
        'concept' => ['category', 'origin', 'significance'],
    ];

    $priority = $priority_attrs[$entity_type] ?? [];

    if (empty($priority)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($priority), '%s'));

    $query = $wpdb->prepare(
        "SELECT
            d.display_name,
            d.attribute_key,
            d.data_type,
            av.value_string,
            av.value_int,
            av.value_float,
            av.value_bool,
            av.value_date
        FROM {$val_table} av
        JOIN {$def_table} d ON av.attribute_id = d.id
        WHERE av.entity_id = %d
        AND d.attribute_key IN ({$placeholders})
        ORDER BY FIELD(d.attribute_key, " . $placeholders . ")
        LIMIT 3",
        array_merge(
            [$entity_id],
            $priority,
            $priority
        )
    );

    $results = $wpdb->get_results($query, ARRAY_A);

    if (!$results) {
        return [];
    }

    $attributes = [];
    foreach ($results as $row) {
        $value = get_attribute_value($row);
        if ($value !== null && $value !== '') {
            $attributes[] = [
                'label' => $row['display_name'],
                'value' => $value,
            ];
        }
    }

    return $attributes;
}

/**
 * Extract attribute value from row
 *
 * @param array $row Database row
 * @return string|null Formatted value
 */
function get_attribute_value(array $row): ?string
{
    switch ($row['data_type']) {
        case 'string':
            return $row['value_string'];
        case 'int':
            return (string) $row['value_int'];
        case 'float':
            return (string) $row['value_float'];
        case 'bool':
            return $row['value_bool'] ? 'Yes' : 'No';
        case 'date':
            return $row['value_date'] ? date_i18n('M j, Y', strtotime($row['value_date'])) : null;
        default:
            return null;
    }
}

/**
 * Clear preview cache when entity is updated
 *
 * @param int $entity_id Entity ID
 */
function clear_preview_cache(int $entity_id): void
{
    $cache_key = "saga_preview_{$entity_id}";
    wp_cache_delete($cache_key, 'saga_previews');
}
