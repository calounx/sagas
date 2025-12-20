<?php
/**
 * Template: Entity Card
 *
 * @package SagaManagerDisplay
 * @var array $entity Entity data
 * @var array $attributes Entity attributes
 * @var array $relationships Entity relationships
 * @var array $options Display options
 * @var array $esc Escaping helpers
 */

defined('ABSPATH') || exit;

$entity_type = $entity['entity_type'] ?? 'entity';
$entity_url = $entity['url'] ?? '';
$entity_image = $entity['image'] ?? '';
$entity_name = $entity['canonical_name'] ?? '';
$entity_description = $entity['description'] ?? '';
?>

<article class="saga-entity saga-entity--card" data-entity-id="<?php echo esc_attr($entity['id'] ?? ''); ?>">
    <?php if ($options['show_image'] && $entity_image): ?>
        <div class="saga-entity__image">
            <img
                src="<?php echo esc_url($entity_image); ?>"
                alt="<?php echo esc_attr($entity_name); ?>"
                loading="lazy"
            >
        </div>
    <?php endif; ?>

    <div class="saga-entity__content">
        <?php if ($options['show_type']): ?>
            <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
                <?php echo esc_html(ucfirst($entity_type)); ?>
            </span>
        <?php endif; ?>

        <h3 class="saga-entity__name">
            <?php if ($options['link'] && $entity_url): ?>
                <a href="<?php echo esc_url($entity_url); ?>">
                    <?php echo esc_html($entity_name); ?>
                </a>
            <?php else: ?>
                <?php echo esc_html($entity_name); ?>
            <?php endif; ?>
        </h3>

        <?php if ($entity_description): ?>
            <p class="saga-entity__description saga-line-clamp-2">
                <?php echo esc_html($entity_description); ?>
            </p>
        <?php endif; ?>

        <?php if ($options['show_importance'] && isset($entity['importance_score'])): ?>
            <div class="saga-entity__importance">
                <span><?php esc_html_e('Importance:', 'saga-manager-display'); ?></span>
                <div class="saga-entity__importance-bar">
                    <div
                        class="saga-entity__importance-fill"
                        style="width: <?php echo esc_attr($entity['importance_score']); ?>%;"
                    ></div>
                </div>
                <span><?php echo esc_html($entity['importance_score']); ?>%</span>
            </div>
        <?php endif; ?>
    </div>
</article>
