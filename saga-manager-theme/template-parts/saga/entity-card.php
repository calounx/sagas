<?php
/**
 * Entity Card Template Part
 *
 * Reusable entity card for archives
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$entity_type = saga_get_entity_type(get_the_ID());
$importance = saga_get_importance_score(get_the_ID());
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('saga-entity-card'); ?>>

    <?php if (has_post_thumbnail()) : ?>
        <div class="saga-entity-card__thumbnail">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('saga-entity-card'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="saga-entity-card__header">
        <h2 class="saga-entity-card__title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>

        <?php if ($entity_type && saga_get_option('saga_show_type_badge', true)) : ?>
            <?php echo saga_get_entity_type_badge($entity_type); ?>
        <?php endif; ?>
    </div>

    <?php if (has_excerpt()) : ?>
        <div class="saga-entity-card__excerpt">
            <?php the_excerpt(); ?>
        </div>
    <?php endif; ?>

    <div class="saga-entity-card__footer">
        <a href="<?php the_permalink(); ?>" class="saga-entity-card__link">
            <?php esc_html_e('View Details', 'saga-manager-theme'); ?> &rarr;
        </a>

        <?php if (saga_get_option('saga_show_importance', true)) : ?>
            <?php echo saga_format_importance_score($importance, false); ?>
        <?php endif; ?>
    </div>

</article>
