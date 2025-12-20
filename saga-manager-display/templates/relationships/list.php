<?php
/**
 * Template: Relationships List
 *
 * @package SagaManagerDisplay
 * @var array $source_entity Source entity data
 * @var array $nodes All nodes in the graph
 * @var array $edges All edges in the graph
 * @var array $options Display options
 */

defined('ABSPATH') || exit;
?>

<div class="saga-relationships__header">
    <h3>
        <?php
        printf(
            /* translators: %s: entity name */
            esc_html__('Relationships: %s', 'saga-manager-display'),
            esc_html($source_entity['canonical_name'] ?? '')
        );
        ?>
    </h3>
</div>

<div class="saga-relationships__items">
    <?php if (empty($edges)): ?>
        <p class="saga-relationships__empty">
            <?php esc_html_e('No relationships found.', 'saga-manager-display'); ?>
        </p>
    <?php else: ?>
        <?php foreach ($edges as $edge): ?>
            <?php
            // Find the connected entity
            $source_id = $source_entity['id'] ?? 0;
            $connected_id = $edge['source'] == $source_id ? $edge['target'] : $edge['source'];
            $connected = null;

            foreach ($nodes as $node) {
                if ($node['id'] == $connected_id) {
                    $connected = $node;
                    break;
                }
            }

            if (!$connected) continue;

            $entity_type = $connected['entity_type'] ?? 'entity';
            $entity_name = $connected['canonical_name'] ?? '';
            $entity_image = $connected['image'] ?? '';
            $entity_url = $connected['url'] ?? '';
            $rel_type = $edge['type'] ?? 'related';
            $strength = $edge['strength'] ?? 50;
            $direction = $edge['direction'] ?? 'both';
            ?>
            <div class="saga-relationships__item" data-entity-id="<?php echo esc_attr($connected_id); ?>">
                <div class="saga-relationships__item-entity">
                    <?php if ($entity_image): ?>
                        <img
                            src="<?php echo esc_url($entity_image); ?>"
                            alt=""
                            class="saga-relationships__item-image"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <span class="saga-relationships__item-image saga-relationships__item-image--placeholder">
                            <?php echo esc_html(mb_substr($entity_name, 0, 1)); ?>
                        </span>
                    <?php endif; ?>

                    <span class="saga-relationships__item-name">
                        <?php if ($entity_url): ?>
                            <a href="<?php echo esc_url($entity_url); ?>">
                                <?php echo esc_html($entity_name); ?>
                            </a>
                        <?php else: ?>
                            <?php echo esc_html($entity_name); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?> saga-relationships__item-type-badge">
                    <?php echo esc_html(ucfirst($entity_type)); ?>
                </span>

                <span class="saga-relationships__item-arrow" aria-hidden="true">
                    <?php if ($direction === 'outgoing'): ?>
                        &rarr;
                    <?php elseif ($direction === 'incoming'): ?>
                        &larr;
                    <?php else: ?>
                        &harr;
                    <?php endif; ?>
                </span>

                <span class="saga-relationships__item-relation">
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $rel_type))); ?>
                </span>

                <?php if ($options['show_strength']): ?>
                    <div class="saga-relationships__strength">
                        <div class="saga-relationships__strength-bar">
                            <div
                                class="saga-relationships__strength-fill"
                                style="width: <?php echo esc_attr($strength); ?>%;"
                            ></div>
                        </div>
                        <span class="saga-sr-only">
                            <?php
                            printf(
                                /* translators: %d: strength percentage */
                                esc_html__('Strength: %d%%', 'saga-manager-display'),
                                $strength
                            );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
