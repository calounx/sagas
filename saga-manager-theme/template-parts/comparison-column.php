<?php
/**
 * Template part for displaying a single entity column in comparison view
 *
 * @package Saga_Manager_Theme
 *
 * @var WP_Post $entity The entity post object
 * @var array $attributes Array of attribute definitions
 */

declare(strict_types=1);

if (!isset($entity) || !($entity instanceof WP_Post)) {
    return;
}

$entity_id = $entity->ID;
$entity_type = get_post_meta($entity_id, 'entity_type', true);
$thumbnail_url = get_the_post_thumbnail_url($entity_id, 'medium');
?>

<div class="comparison-column" data-entity-id="<?php echo esc_attr($entity_id); ?>">

    <!-- Sticky Entity Header -->
    <div class="comparison-column-header">
        <?php if ($thumbnail_url) : ?>
            <div class="column-thumbnail">
                <img
                    src="<?php echo esc_url($thumbnail_url); ?>"
                    alt="<?php echo esc_attr($entity->post_title); ?>"
                    loading="lazy"
                />
            </div>
        <?php endif; ?>

        <div class="column-header-content">
            <h3 class="column-entity-name">
                <a href="<?php echo esc_url(get_permalink($entity_id)); ?>" target="_blank">
                    <?php echo esc_html($entity->post_title); ?>
                </a>
            </h3>

            <?php if ($entity_type) : ?>
                <span class="column-entity-type">
                    <?php echo esc_html(ucfirst($entity_type)); ?>
                </span>
            <?php endif; ?>
        </div>

        <button
            type="button"
            class="btn-remove-column"
            data-entity-id="<?php echo esc_attr($entity_id); ?>"
            aria-label="<?php echo esc_attr(sprintf(__('Remove %s from comparison', 'saga-manager-theme'), $entity->post_title)); ?>"
            title="<?php esc_attr_e('Remove from comparison', 'saga-manager-theme'); ?>"
        >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <!-- Scrollable Attributes -->
    <div class="comparison-column-body">
        <?php
        $meta_fields = get_post_meta($entity_id);

        foreach ($attributes as $attr_key => $attr_label) :
            $value = null;

            // Get value from post meta
            if (isset($meta_fields[$attr_key])) {
                $value = $meta_fields[$attr_key][0] ?? null;

                // Unserialize if needed
                if (is_serialized($value)) {
                    $value = maybe_unserialize($value);
                }
            }

            $attr_type = saga_detect_attribute_type($value);
        ?>
            <div class="column-attribute-row" data-attribute="<?php echo esc_attr($attr_key); ?>">
                <div class="attribute-value">
                    <?php echo wp_kses_post(saga_format_attribute_value($value, $attr_type)); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Drag Handle (for reordering) -->
    <div class="column-drag-handle" aria-label="<?php esc_attr_e('Drag to reorder', 'saga-manager-theme'); ?>">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </div>

</div>
