<?php
/**
 * Template: Entity List
 *
 * @package SagaManagerDisplay
 * @var array $entities Array of entities
 * @var array $options Display options
 */

defined('ABSPATH') || exit;

$columns = $options['columns'] ?? 1;
$layout_class = 'saga-entity-list';

if ($columns > 1) {
    $layout_class .= ' saga-entity-list--grid';
    $layout_class .= ' saga-entity-list--' . $columns . '-col';
}
?>

<div class="<?php echo esc_attr($layout_class); ?>">
    <?php foreach ($entities as $entity): ?>
        <?php
        $entity_type = $entity['entity_type'] ?? 'entity';
        $entity_url = $entity['url'] ?? '';
        $entity_image = $entity['image'] ?? '';
        $entity_name = $entity['canonical_name'] ?? '';
        ?>
        <article class="saga-entity saga-entity--compact" data-entity-id="<?php echo esc_attr($entity['id'] ?? ''); ?>">
            <?php if (!empty($entity_image)): ?>
                <div class="saga-entity__image">
                    <img
                        src="<?php echo esc_url($entity_image); ?>"
                        alt="<?php echo esc_attr($entity_name); ?>"
                        loading="lazy"
                    >
                </div>
            <?php endif; ?>

            <div class="saga-entity__content">
                <h4 class="saga-entity__name">
                    <?php if ($entity_url): ?>
                        <a href="<?php echo esc_url($entity_url); ?>">
                            <?php echo esc_html($entity_name); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($entity_name); ?>
                    <?php endif; ?>
                </h4>

                <?php if ($options['show_type']): ?>
                    <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
                        <?php echo esc_html(ucfirst($entity_type)); ?>
                    </span>
                <?php endif; ?>

                <?php if ($options['show_importance'] && isset($entity['importance_score'])): ?>
                    <div class="saga-entity__importance">
                        <div class="saga-entity__importance-bar">
                            <div
                                class="saga-entity__importance-fill"
                                style="width: <?php echo esc_attr($entity['importance_score']); ?>%;"
                            ></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
