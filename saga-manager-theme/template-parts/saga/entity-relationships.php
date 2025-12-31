<?php
/**
 * Entity Relationships Template Part
 *
 * Display related entities grouped by relationship type
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!saga_get_option('saga_show_relationships', true)) {
    return;
}

$relationships = saga_get_entity_relationships(get_the_ID());

if (empty($relationships)) {
    return;
}

$entity = saga_get_entity_by_post_id(get_the_ID());

// Group relationships by type
$grouped = [];
foreach ($relationships as $rel) {
    $type = $rel->relationship_type;
    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }
    $grouped[$type][] = $rel;
}
?>

<div class="saga-relationships">
    <h2 class="saga-relationships__title"><?php esc_html_e('Relationships', 'saga-manager-theme'); ?></h2>

    <?php foreach ($grouped as $type => $rels) : ?>
        <div class="saga-relationships__group">
            <h3 class="saga-relationships__group-title">
                <?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?>
            </h3>

            <ul class="saga-relationships__list">
                <?php foreach ($rels as $rel) : ?>
                    <?php
                    // Determine which entity to display (the one that's not current)
                    $is_source = ($rel->source_entity_id === $entity->id);
                    $related_name = $is_source ? $rel->target_name : $rel->source_name;
                    $related_post_id = $is_source ? $rel->target_post_id : $rel->source_post_id;
                    $related_type = $is_source ? $rel->target_type : null;

                    if (!$related_post_id || !$related_name) {
                        continue;
                    }

                    $strength_label = saga_get_relationship_strength_label((int) $rel->strength);
                    $strength_class = saga_get_relationship_strength_class((int) $rel->strength);
                    ?>
                    <li class="saga-relationships__item">
                        <?php if ($related_type) : ?>
                            <?php echo saga_get_entity_type_badge($related_type); ?>
                        <?php endif; ?>

                        <a href="<?php echo esc_url(get_permalink($related_post_id)); ?>" 
                           class="saga-relationships__item-link">
                            <?php echo esc_html($related_name); ?>
                        </a>

                        <span class="saga-relationships__strength <?php echo esc_attr($strength_class); ?>">
                            <?php echo esc_html($strength_label); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
