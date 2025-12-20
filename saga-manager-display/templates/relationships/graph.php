<?php
/**
 * Template: Relationships Graph
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
    <span class="saga-relationships__count">
        <?php
        printf(
            /* translators: %d: number of connections */
            esc_html(_n('%d connection', '%d connections', count($edges), 'saga-manager-display')),
            count($edges)
        );
        ?>
    </span>
</div>

<svg class="saga-relationships__canvas" aria-label="<?php esc_attr_e('Entity relationship graph', 'saga-manager-display'); ?>">
    <!-- Graph will be rendered by JavaScript -->
</svg>

<?php if ($options['interactive']): ?>
    <div class="saga-relationships__legend">
        <span class="saga-relationships__legend-title">
            <?php esc_html_e('Entity Types:', 'saga-manager-display'); ?>
        </span>
        <span class="saga-relationships__legend-item">
            <span class="saga-relationships__legend-color" style="background-color: #3b82f6;"></span>
            <?php esc_html_e('Character', 'saga-manager-display'); ?>
        </span>
        <span class="saga-relationships__legend-item">
            <span class="saga-relationships__legend-color" style="background-color: #22c55e;"></span>
            <?php esc_html_e('Location', 'saga-manager-display'); ?>
        </span>
        <span class="saga-relationships__legend-item">
            <span class="saga-relationships__legend-color" style="background-color: #f59e0b;"></span>
            <?php esc_html_e('Event', 'saga-manager-display'); ?>
        </span>
        <span class="saga-relationships__legend-item">
            <span class="saga-relationships__legend-color" style="background-color: #8b5cf6;"></span>
            <?php esc_html_e('Faction', 'saga-manager-display'); ?>
        </span>
    </div>
<?php endif; ?>
