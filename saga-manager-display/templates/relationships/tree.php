<?php
/**
 * Template: Relationships Tree
 *
 * @package SagaManagerDisplay
 * @var array $source_entity Source entity data
 * @var array $nodes All nodes in the graph
 * @var array $edges All edges in the graph
 * @var array $options Display options
 */

defined('ABSPATH') || exit;

/**
 * Build tree structure from edges
 */
function saga_build_relationship_tree($source_id, $nodes, $edges, $depth = 0, $max_depth = 3, &$visited = []) {
    if ($depth >= $max_depth || in_array($source_id, $visited)) {
        return [];
    }

    $visited[] = $source_id;
    $children = [];

    foreach ($edges as $edge) {
        $connected_id = null;

        if ($edge['source'] == $source_id) {
            $connected_id = $edge['target'];
        } elseif ($edge['target'] == $source_id) {
            $connected_id = $edge['source'];
        }

        if (!$connected_id || in_array($connected_id, $visited)) {
            continue;
        }

        // Find connected node
        $connected_node = null;
        foreach ($nodes as $node) {
            if ($node['id'] == $connected_id) {
                $connected_node = $node;
                break;
            }
        }

        if ($connected_node) {
            $children[] = [
                'node' => $connected_node,
                'edge' => $edge,
                'children' => saga_build_relationship_tree($connected_id, $nodes, $edges, $depth + 1, $max_depth, $visited)
            ];
        }
    }

    return $children;
}

$tree = saga_build_relationship_tree($source_entity['id'], $nodes, $edges, 0, $options['depth'] ?? 3);
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

<ul class="saga-relationships__tree">
    <?php
    /**
     * Render tree node recursively
     */
    function saga_render_tree_node($item, $options) {
        $node = $item['node'];
        $edge = $item['edge'];
        $children = $item['children'];

        $entity_type = $node['entity_type'] ?? 'entity';
        $entity_name = $node['canonical_name'] ?? '';
        $entity_url = $node['url'] ?? '';
        $rel_type = $edge['type'] ?? 'related';
        ?>
        <li class="saga-relationships__tree-item">
            <div class="saga-relationships__item">
                <span class="saga-relationships__item-name">
                    <?php if ($entity_url): ?>
                        <a href="<?php echo esc_url($entity_url); ?>">
                            <?php echo esc_html($entity_name); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($entity_name); ?>
                    <?php endif; ?>
                </span>
                <span class="saga-badge saga-badge--<?php echo esc_attr($entity_type); ?>">
                    <?php echo esc_html(ucfirst($entity_type)); ?>
                </span>
                <span class="saga-relationships__item-relation">
                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $rel_type))); ?>
                </span>
            </div>

            <?php if (!empty($children)): ?>
                <ul class="saga-relationships__tree-children">
                    <?php foreach ($children as $child): ?>
                        <?php saga_render_tree_node($child, $options); ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }

    foreach ($tree as $item) {
        saga_render_tree_node($item, $options);
    }
    ?>
</ul>

<?php if (empty($tree)): ?>
    <p class="saga-relationships__empty">
        <?php esc_html_e('No relationships found.', 'saga-manager-display'); ?>
    </p>
<?php endif; ?>
