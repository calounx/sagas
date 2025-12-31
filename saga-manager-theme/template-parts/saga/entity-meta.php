<?php
/**
 * Entity Metadata Template Part
 *
 * Display entity metadata (saga, type, importance, etc.)
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$entity = saga_get_entity_by_post_id(get_the_ID());

if (!$entity) {
    return;
}

$saga_name = saga_get_saga_name((int) $entity->saga_id);
$entity_type = saga_get_entity_type(get_the_ID());
$importance = saga_get_importance_score(get_the_ID());
$quality_metrics = saga_get_quality_metrics(get_the_ID());
?>

<div class="saga-entity-meta">
    <div class="saga-entity-meta__grid">

        <div class="saga-entity-meta__item">
            <span class="saga-entity-meta__label"><?php esc_html_e('Saga', 'saga-manager-theme'); ?></span>
            <span class="saga-entity-meta__value"><?php echo esc_html($saga_name); ?></span>
        </div>

        <?php if ($entity_type) : ?>
            <div class="saga-entity-meta__item">
                <span class="saga-entity-meta__label"><?php esc_html_e('Type', 'saga-manager-theme'); ?></span>
                <span class="saga-entity-meta__value"><?php echo saga_get_entity_type_badge($entity_type); ?></span>
            </div>
        <?php endif; ?>

        <div class="saga-entity-meta__item">
            <span class="saga-entity-meta__label"><?php esc_html_e('Importance', 'saga-manager-theme'); ?></span>
            <div class="saga-entity-meta__value">
                <?php echo saga_format_importance_score($importance); ?>
            </div>
        </div>

        <?php if ($quality_metrics) : ?>
            <div class="saga-entity-meta__item">
                <span class="saga-entity-meta__label"><?php esc_html_e('Completeness', 'saga-manager-theme'); ?></span>
                <span class="saga-entity-meta__value"><?php echo esc_html($quality_metrics->completeness_score); ?>%</span>
            </div>

            <div class="saga-entity-meta__item">
                <span class="saga-entity-meta__label"><?php esc_html_e('Consistency', 'saga-manager-theme'); ?></span>
                <span class="saga-entity-meta__value"><?php echo esc_html($quality_metrics->consistency_score); ?>%</span>
            </div>
        <?php endif; ?>

        <div class="saga-entity-meta__item">
            <span class="saga-entity-meta__label"><?php esc_html_e('Last Updated', 'saga-manager-theme'); ?></span>
            <span class="saga-entity-meta__value">
                <?php echo esc_html(get_the_modified_date()); ?>
            </span>
        </div>

    </div>
</div>
