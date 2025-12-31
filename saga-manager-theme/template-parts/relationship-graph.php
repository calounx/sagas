<?php
/**
 * Template Part: Relationship Graph
 *
 * Displays an interactive relationship graph for saga entities
 *
 * @package SagaManager
 * @since 1.0.0
 *
 * Variables available:
 * @var int    $entity_id         Entity ID (0 for all entities)
 * @var int    $depth             Relationship depth (1-3)
 * @var string $entity_type       Filter by entity type
 * @var string $relationship_type Filter by relationship type
 * @var int    $limit             Maximum nodes to display
 * @var int    $height            Graph height in pixels
 * @var bool   $show_filters      Show filter controls
 * @var bool   $show_legend       Show entity type legend
 * @var bool   $show_table        Show alternative table view toggle
 */

// Set defaults
$entity_id = isset($entity_id) ? absint($entity_id) : 0;
$depth = isset($depth) ? min(absint($depth), 3) : 1;
$entity_type = isset($entity_type) ? sanitize_key($entity_type) : '';
$relationship_type = isset($relationship_type) ? sanitize_text_field($relationship_type) : '';
$limit = isset($limit) ? min(absint($limit), 100) : 100;
$height = isset($height) ? absint($height) : 600;
$show_filters = isset($show_filters) ? (bool) $show_filters : true;
$show_legend = isset($show_legend) ? (bool) $show_legend : true;
$show_table = isset($show_table) ? (bool) $show_table : true;

// Generate unique ID for this graph instance
$graph_id = 'saga-graph-' . uniqid();

// Entity types for filter
$entity_types = [
    '' => 'All Types',
    'character' => 'Characters',
    'location' => 'Locations',
    'event' => 'Events',
    'faction' => 'Factions',
    'artifact' => 'Artifacts',
    'concept' => 'Concepts'
];

// Get relationship types via REST API
$relationship_types = ['All Types'];
$rest_url = rest_url('saga/v1/graph/types');
$response = wp_remote_get($rest_url);

if (!is_wp_error($response)) {
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($data['types']) && is_array($data['types'])) {
        $relationship_types = array_merge(
            ['' => 'All Types'],
            array_column($data['types'], 'label', 'value')
        );
    }
}
?>

<div class="saga-graph-wrapper">
    <?php if ($show_filters) : ?>
        <div class="saga-graph-filter-bar" role="search" aria-label="Graph filters">
            <div class="saga-graph-filter">
                <label for="<?php echo esc_attr($graph_id); ?>-entity-type">
                    Entity Type
                </label>
                <select
                    id="<?php echo esc_attr($graph_id); ?>-entity-type"
                    class="saga-graph-filter-select"
                    data-filter="entity_type"
                    aria-label="Filter by entity type"
                >
                    <?php foreach ($entity_types as $value => $label) : ?>
                        <option
                            value="<?php echo esc_attr($value); ?>"
                            <?php selected($entity_type, $value); ?>
                        >
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="saga-graph-filter">
                <label for="<?php echo esc_attr($graph_id); ?>-relationship-type">
                    Relationship Type
                </label>
                <select
                    id="<?php echo esc_attr($graph_id); ?>-relationship-type"
                    class="saga-graph-filter-select"
                    data-filter="relationship_type"
                    aria-label="Filter by relationship type"
                >
                    <?php foreach ($relationship_types as $value => $label) : ?>
                        <option
                            value="<?php echo esc_attr($value); ?>"
                            <?php selected($relationship_type, $value); ?>
                        >
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="saga-graph-filter">
                <label for="<?php echo esc_attr($graph_id); ?>-depth">
                    Depth
                </label>
                <select
                    id="<?php echo esc_attr($graph_id); ?>-depth"
                    class="saga-graph-filter-select"
                    data-filter="depth"
                    aria-label="Set relationship depth"
                >
                    <option value="1" <?php selected($depth, 1); ?>>1 Level</option>
                    <option value="2" <?php selected($depth, 2); ?>>2 Levels</option>
                    <option value="3" <?php selected($depth, 3); ?>>3 Levels</option>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <div
        id="<?php echo esc_attr($graph_id); ?>"
        class="saga-graph-container"
        role="region"
        aria-label="Entity relationship graph"
        style="height: <?php echo esc_attr($height); ?>px;"
        data-entity-id="<?php echo esc_attr($entity_id); ?>"
        data-depth="<?php echo esc_attr($depth); ?>"
        data-entity-type="<?php echo esc_attr($entity_type); ?>"
        data-relationship-type="<?php echo esc_attr($relationship_type); ?>"
        data-limit="<?php echo esc_attr($limit); ?>"
    >
        <!-- Graph will be rendered here by JavaScript -->
    </div>

    <?php if ($show_legend) : ?>
        <div class="saga-graph-legend" role="complementary" aria-label="Entity type legend">
            <div class="saga-graph-legend-title">Entity Types</div>
            <div class="saga-graph-legend-items">
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #0173B2;"></span>
                    <span class="saga-graph-legend-label">Character</span>
                </div>
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #029E73;"></span>
                    <span class="saga-graph-legend-label">Location</span>
                </div>
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #D55E00;"></span>
                    <span class="saga-graph-legend-label">Event</span>
                </div>
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #CC78BC;"></span>
                    <span class="saga-graph-legend-label">Faction</span>
                </div>
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #ECE133;"></span>
                    <span class="saga-graph-legend-label">Artifact</span>
                </div>
                <div class="saga-graph-legend-item">
                    <span class="saga-graph-legend-color" style="background: #56B4E9;"></span>
                    <span class="saga-graph-legend-label">Concept</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($show_table) : ?>
        <div class="saga-graph-table-toggle">
            <button
                type="button"
                class="saga-graph-btn"
                id="<?php echo esc_attr($graph_id); ?>-table-toggle"
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($graph_id); ?>-table"
            >
                View as Table
            </button>
        </div>

        <div
            id="<?php echo esc_attr($graph_id); ?>-table"
            class="saga-graph-table-view"
            role="region"
            aria-label="Relationship data table"
            hidden
        >
            <table class="saga-graph-table">
                <thead>
                    <tr>
                        <th scope="col">Entity</th>
                        <th scope="col">Type</th>
                        <th scope="col">Importance</th>
                        <th scope="col">Relationships</th>
                    </tr>
                </thead>
                <tbody id="<?php echo esc_attr($graph_id); ?>-table-body">
                    <!-- Table rows will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';

    // Wait for DOM and D3.js to be ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check if D3.js is loaded
        if (typeof d3 === 'undefined') {
            console.error('D3.js is not loaded. Cannot initialize relationship graph.');
            return;
        }

        // Check if SagaRelationshipGraph is loaded
        if (typeof window.SagaRelationshipGraph === 'undefined') {
            console.error('SagaRelationshipGraph is not loaded.');
            return;
        }

        const graphId = <?php echo wp_json_encode($graph_id); ?>;
        const container = document.getElementById(graphId);

        if (!container) {
            console.error('Graph container not found:', graphId);
            return;
        }

        // Get configuration from data attributes
        const config = {
            entityId: parseInt(container.dataset.entityId || '0', 10),
            depth: parseInt(container.dataset.depth || '1', 10),
            entityType: container.dataset.entityType || '',
            relationshipType: container.dataset.relationshipType || '',
            limit: parseInt(container.dataset.limit || '100', 10),
            height: <?php echo absint($height); ?>,
            useRestAPI: <?php echo wp_json_encode(rest_url() !== false); ?>
        };

        // Initialize graph
        const graph = new window.SagaRelationshipGraph(graphId, config);

        // Handle filter changes
        const filters = document.querySelectorAll(`#${graphId}-entity-type, #${graphId}-relationship-type, #${graphId}-depth`);

        filters.forEach(function(filter) {
            filter.addEventListener('change', function() {
                const newConfig = {
                    ...config,
                    entityType: document.getElementById(`${graphId}-entity-type`)?.value || '',
                    relationshipType: document.getElementById(`${graphId}-relationship-type`)?.value || '',
                    depth: parseInt(document.getElementById(`${graphId}-depth`)?.value || '1', 10)
                };

                // Destroy old graph and create new one
                graph.destroy();
                const newGraph = new window.SagaRelationshipGraph(graphId, newConfig);

                // Update table if visible
                updateTable(newGraph);
            });
        });

        // Handle table toggle
        const tableToggle = document.getElementById(`${graphId}-table-toggle`);
        const tableView = document.getElementById(`${graphId}-table`);

        if (tableToggle && tableView) {
            tableToggle.addEventListener('click', function() {
                const isExpanded = tableToggle.getAttribute('aria-expanded') === 'true';

                tableToggle.setAttribute('aria-expanded', String(!isExpanded));
                tableView.hidden = isExpanded;

                if (!isExpanded) {
                    updateTable(graph);
                    tableToggle.textContent = 'View as Graph';
                } else {
                    tableToggle.textContent = 'View as Table';
                }
            });
        }

        /**
         * Update table view with graph data
         */
        function updateTable(graphInstance) {
            const tableBody = document.getElementById(`${graphId}-table-body`);

            if (!tableBody || !graphInstance.data) {
                return;
            }

            tableBody.innerHTML = '';

            graphInstance.data.nodes.forEach(function(node) {
                const connections = graphInstance.data.edges.filter(function(edge) {
                    return edge.source.id === node.id || edge.target.id === node.id;
                }).length;

                const row = document.createElement('tr');

                row.innerHTML = `
                    <td>
                        ${node.url
                            ? `<a href="${node.url}" target="_blank">${node.label}</a>`
                            : node.label
                        }
                    </td>
                    <td>
                        <span class="saga-graph-table-type ${node.type}">
                            ${node.type}
                        </span>
                    </td>
                    <td>${node.importance}</td>
                    <td>${connections}</td>
                `;

                tableBody.appendChild(row);
            });
        }

        // Store graph instance globally for debugging
        window[`sagaGraph_${graphId}`] = graph;
    });
})();
</script>
