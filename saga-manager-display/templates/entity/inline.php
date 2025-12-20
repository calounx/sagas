<?php
/**
 * Template: Entity Inline
 *
 * @package SagaManagerDisplay
 * @var array $entity Entity data
 * @var array $options Display options
 */

defined('ABSPATH') || exit;

$entity_type = $entity['entity_type'] ?? 'entity';
$entity_url = $entity['url'] ?? '';
$entity_image = $entity['image'] ?? '';
$entity_name = $entity['canonical_name'] ?? '';
?>

<span class="saga-entity saga-entity--inline" data-entity-id="<?php echo esc_attr($entity['id'] ?? ''); ?>">
    <?php if ($options['show_image'] && $entity_image): ?>
        <span class="saga-entity__image">
            <img
                src="<?php echo esc_url($entity_image); ?>"
                alt=""
                loading="lazy"
            >
        </span>
    <?php endif; ?>

    <span class="saga-entity__name">
        <?php if ($options['link'] && $entity_url): ?>
            <a href="<?php echo esc_url($entity_url); ?>">
                <?php echo esc_html($entity_name); ?>
            </a>
        <?php else: ?>
            <?php echo esc_html($entity_name); ?>
        <?php endif; ?>
    </span>

    <?php if ($options['show_type']): ?>
        <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
            <?php echo esc_html(ucfirst($entity_type)); ?>
        </span>
    <?php endif; ?>
</span>
