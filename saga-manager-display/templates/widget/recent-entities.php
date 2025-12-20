<?php
/**
 * Template: Widget - Recent Entities
 *
 * @package SagaManagerDisplay
 * @var array $entities Array of entities
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<div class="saga-widget">
    <?php if (empty($entities)): ?>
        <p class="saga-widget__empty">
            <?php esc_html_e('No entities found.', 'saga-manager-display'); ?>
        </p>
    <?php else: ?>
        <ul class="saga-widget__list">
            <?php foreach ($entities as $entity): ?>
                <?php
                $entity_type = $entity['entity_type'] ?? 'entity';
                $entity_url = $entity['url'] ?? '';
                $entity_image = $entity['image'] ?? '';
                $entity_name = $entity['canonical_name'] ?? '';
                ?>
                <li class="saga-widget__item">
                    <a href="<?php echo esc_url($entity_url); ?>" class="saga-widget__link">
                        <?php if ($options['show_image'] && $entity_image): ?>
                            <img
                                src="<?php echo esc_url($entity_image); ?>"
                                alt=""
                                class="saga-widget__image"
                                loading="lazy"
                            >
                        <?php endif; ?>

                        <span class="saga-widget__name">
                            <?php echo esc_html($entity_name); ?>
                        </span>

                        <?php if ($options['show_type']): ?>
                            <span class="saga-widget__type">
                                <?php echo esc_html(ucfirst($entity_type)); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
