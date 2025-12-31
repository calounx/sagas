<?php
/**
 * Masonry-optimized entity card template
 *
 * Premium card design for masonry grid layout with elegant aesthetics
 *
 * @package SagaTheme
 * @version 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get entity data
$entity_id = $args['entity_id'] ?? get_the_ID();
$entity = saga_get_entity($entity_id);

if (!$entity) {
    return;
}

// Get entity metadata
$entity_type = $entity->entity_type ?? 'unknown';
$importance = $entity->importance_score ?? 50;
$thumbnail_url = get_the_post_thumbnail_url($entity_id, 'large');
$permalink = get_permalink($entity_id);

// Generate importance class for styling
$importance_class = '';
if ($importance >= 80) {
    $importance_class = 'saga-entity-card-masonry--high-importance';
} elseif ($importance >= 50) {
    $importance_class = 'saga-entity-card-masonry--medium-importance';
} else {
    $importance_class = 'saga-entity-card-masonry--low-importance';
}

// Get entity type display name
$entity_type_display = ucfirst(str_replace('_', ' ', $entity_type));

// Generate random height variation for masonry effect
$height_variations = ['short', 'medium', 'tall'];
$height_variation = $height_variations[array_rand($height_variations)];
?>

<article
    class="saga-entity-card-masonry saga-entity-card-masonry--<?php echo esc_attr($entity_type); ?> saga-entity-card-masonry--<?php echo esc_attr($height_variation); ?> <?php echo esc_attr($importance_class); ?>"
    data-entity-id="<?php echo esc_attr($entity_id); ?>"
    data-entity-type="<?php echo esc_attr($entity_type); ?>"
>
    <div class="saga-entity-card-masonry__inner">

        <?php if ($thumbnail_url): ?>
            <div class="saga-entity-card-masonry__image">
                <a href="<?php echo esc_url($permalink); ?>" class="saga-entity-card-masonry__image-link">
                    <img
                        src="<?php echo esc_url($thumbnail_url); ?>"
                        alt="<?php echo esc_attr(get_the_title($entity_id)); ?>"
                        class="saga-entity-card-masonry__img"
                        loading="lazy"
                    >
                    <div class="saga-entity-card-masonry__image-overlay"></div>
                </a>
            </div>
        <?php endif; ?>

        <div class="saga-entity-card-masonry__content">

            <div class="saga-entity-card-masonry__header">
                <span class="saga-entity-card-masonry__type-badge saga-entity-card-masonry__type-badge--<?php echo esc_attr($entity_type); ?>">
                    <?php echo esc_html($entity_type_display); ?>
                </span>

                <div class="saga-entity-card-masonry__importance">
                    <div class="saga-entity-card-masonry__importance-bar">
                        <div
                            class="saga-entity-card-masonry__importance-fill"
                            style="width: <?php echo esc_attr($importance); ?>%;"
                            aria-label="<?php echo esc_attr(sprintf(__('Importance: %d%%', 'saga-manager-theme'), $importance)); ?>"
                        ></div>
                    </div>
                    <span class="saga-entity-card-masonry__importance-value" aria-hidden="true">
                        <?php echo esc_html($importance); ?>
                    </span>
                </div>
            </div>

            <h3 class="saga-entity-card-masonry__title">
                <a href="<?php echo esc_url($permalink); ?>" class="saga-entity-card-masonry__title-link">
                    <?php echo esc_html(get_the_title($entity_id)); ?>
                </a>
            </h3>

            <?php if (has_excerpt($entity_id)): ?>
                <div class="saga-entity-card-masonry__excerpt">
                    <?php echo wp_kses_post(get_the_excerpt($entity_id)); ?>
                </div>
            <?php endif; ?>

            <div class="saga-entity-card-masonry__footer">
                <a href="<?php echo esc_url($permalink); ?>" class="saga-entity-card-masonry__read-more">
                    <?php esc_html_e('Read More', 'saga-manager-theme'); ?>
                    <svg class="saga-entity-card-masonry__arrow" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 2L14 8L8 14M14 8H2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>

                <?php if (function_exists('saga_bookmark_button')): ?>
                    <button
                        class="saga-entity-card-masonry__bookmark"
                        data-entity-id="<?php echo esc_attr($entity_id); ?>"
                        aria-label="<?php esc_attr_e('Bookmark this entity', 'saga-manager-theme'); ?>"
                    >
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M5 2C4.44772 2 4 2.44772 4 3V18L10 14L16 18V3C16 2.44772 15.5523 2 15 2H5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>

        </div>

    </div>
</article>
