<?php
/**
 * Entity Navigation Template Part
 *
 * Previous/Next navigation for saga entities
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get adjacent entities within same saga
$entity = saga_get_entity_by_post_id(get_the_ID());

if (!$entity) {
    return;
}

// Get previous and next posts within same saga
$prev_post = saga_get_adjacent_entity(get_the_ID(), false);
$next_post = saga_get_adjacent_entity(get_the_ID(), true);

if (!$prev_post && !$next_post) {
    return;
}
?>

<div class="saga-entity-navigation">
    
    <?php if ($prev_post) : ?>
        <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" 
           class="saga-entity-navigation__link saga-entity-navigation__link--prev">
            
            <?php if (has_post_thumbnail($prev_post->ID)) : ?>
                <div class="saga-entity-navigation__thumbnail">
                    <?php echo get_the_post_thumbnail($prev_post->ID, 'saga-entity-thumbnail'); ?>
                </div>
            <?php endif; ?>

            <div class="saga-entity-navigation__content">
                <span class="saga-entity-navigation__label">
                    <?php esc_html_e('Previous', 'saga-manager-theme'); ?>
                </span>
                <span class="saga-entity-navigation__title">
                    <?php echo esc_html(get_the_title($prev_post->ID)); ?>
                </span>
            </div>
        </a>
    <?php else : ?>
        <div class="saga-entity-navigation__link saga-entity-navigation__link--disabled"></div>
    <?php endif; ?>

    <?php if ($next_post) : ?>
        <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" 
           class="saga-entity-navigation__link saga-entity-navigation__link--next">
            
            <div class="saga-entity-navigation__content">
                <span class="saga-entity-navigation__label">
                    <?php esc_html_e('Next', 'saga-manager-theme'); ?>
                </span>
                <span class="saga-entity-navigation__title">
                    <?php echo esc_html(get_the_title($next_post->ID)); ?>
                </span>
            </div>

            <?php if (has_post_thumbnail($next_post->ID)) : ?>
                <div class="saga-entity-navigation__thumbnail">
                    <?php echo get_the_post_thumbnail($next_post->ID, 'saga-entity-thumbnail'); ?>
                </div>
            <?php endif; ?>
        </a>
    <?php else : ?>
        <div class="saga-entity-navigation__link saga-entity-navigation__link--disabled"></div>
    <?php endif; ?>

</div>
