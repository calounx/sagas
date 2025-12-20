<?php
/**
 * Template: Entity Full
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

<article class="saga-entity saga-entity--full" data-entity-id="<?php echo esc_attr($entity['id'] ?? ''); ?>">
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
        <header class="saga-entity__header">
            <?php if ($options['show_type']): ?>
                <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
                    <?php echo esc_html(ucfirst($entity_type)); ?>
                </span>
            <?php endif; ?>

            <h2 class="saga-entity__name">
                <?php echo esc_html($entity_name); ?>
            </h2>

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
        </header>

        <?php if ($entity_description): ?>
            <div class="saga-entity__description">
                <?php echo wp_kses_post($entity_description); ?>
            </div>
        <?php endif; ?>

        <?php if ($options['show_attributes'] && !empty($attributes)): ?>
            <dl class="saga-entity__attributes">
                <?php foreach ($attributes as $key => $value): ?>
                    <div class="saga-entity__attribute">
                        <dt><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></dt>
                        <dd>
                            <?php
                            if (is_array($value)) {
                                echo esc_html(implode(', ', $value));
                            } elseif (is_bool($value)) {
                                echo $value ? esc_html__('Yes', 'saga-manager-display') : esc_html__('No', 'saga-manager-display');
                            } else {
                                echo esc_html($value);
                            }
                            ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?php if ($options['show_relationships'] && !empty($relationships)): ?>
            <section class="saga-entity__relationships">
                <h3><?php esc_html_e('Relationships', 'saga-manager-display'); ?></h3>
                <div class="saga-relationships--list">
                    <div class="saga-relationships__items">
                        <?php foreach ($relationships as $rel): ?>
                            <?php
                            $connected = $rel['connected_entity'] ?? [];
                            $rel_type = $rel['relationship_type'] ?? 'related';
                            ?>
                            <div class="saga-relationships__item">
                                <div class="saga-relationships__item-entity">
                                    <?php if (!empty($connected['image'])): ?>
                                        <img
                                            src="<?php echo esc_url($connected['image']); ?>"
                                            alt=""
                                            class="saga-relationships__item-image"
                                        >
                                    <?php endif; ?>
                                    <span class="saga-relationships__item-name">
                                        <?php echo esc_html($connected['canonical_name'] ?? ''); ?>
                                    </span>
                                </div>
                                <span class="saga-relationships__item-relation">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $rel_type))); ?>
                                </span>
                                <?php if (isset($rel['strength'])): ?>
                                    <div class="saga-relationships__strength">
                                        <div class="saga-relationships__strength-bar">
                                            <div
                                                class="saga-relationships__strength-fill"
                                                style="width: <?php echo esc_attr($rel['strength']); ?>%;"
                                            ></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</article>
