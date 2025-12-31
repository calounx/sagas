<?php
/**
 * Enhanced Graph Controls Template v2
 *
 * Advanced controls panel for relationship graph
 * Layout switcher, filters, analytics, temporal playback
 *
 * @package SagaManager
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="saga-graph-controls-panel">
    <!-- Layout Controls -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">Layout</div>
        <div class="saga-graph-button-group">
            <button
                class="saga-graph-btn-v2 layout-btn active"
                data-layout="force"
                title="Force-directed layout (F)"
                aria-label="Switch to force-directed layout">
                Force
            </button>
            <button
                class="saga-graph-btn-v2 layout-btn"
                data-layout="hierarchical"
                title="Hierarchical tree layout (H)"
                aria-label="Switch to hierarchical layout">
                Tree
            </button>
            <button
                class="saga-graph-btn-v2 layout-btn"
                data-layout="circular"
                title="Circular layout (C)"
                aria-label="Switch to circular layout">
                Circle
            </button>
            <button
                class="saga-graph-btn-v2 layout-btn"
                data-layout="radial"
                title="Radial layout (R)"
                aria-label="Switch to radial layout">
                Radial
            </button>
            <button
                class="saga-graph-btn-v2 layout-btn"
                data-layout="grid"
                title="Grid layout (G)"
                aria-label="Switch to grid layout">
                Grid
            </button>
            <button
                class="saga-graph-btn-v2 layout-btn"
                data-layout="clustered"
                title="Clustered layout (K)"
                aria-label="Switch to clustered layout">
                Cluster
            </button>
        </div>
    </div>

    <!-- View Controls -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">View</div>
        <div class="saga-graph-button-group">
            <button
                class="saga-graph-btn-v2"
                data-action="zoom-in"
                title="Zoom in"
                aria-label="Zoom in">
                &#43;
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="zoom-out"
                title="Zoom out"
                aria-label="Zoom out">
                &#8722;
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="reset-view"
                title="Reset view"
                aria-label="Reset view">
                &#8634;
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="fit-view"
                title="Fit to view"
                aria-label="Fit graph to view">
                &#x26F6;
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="fullscreen"
                title="Toggle fullscreen"
                aria-label="Toggle fullscreen">
                &#x2922;
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">Filters</div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label" for="filter-entity-type">
                Entity Type
            </label>
            <select id="filter-entity-type" class="saga-graph-select">
                <option value="">All Types</option>
                <option value="character">Character</option>
                <option value="location">Location</option>
                <option value="event">Event</option>
                <option value="faction">Faction</option>
                <option value="artifact">Artifact</option>
                <option value="concept">Concept</option>
            </select>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label" for="filter-relationship-type">
                Relationship Type
            </label>
            <select id="filter-relationship-type" class="saga-graph-select">
                <option value="">All Relationships</option>
                <option value="ally">Ally</option>
                <option value="enemy">Enemy</option>
                <option value="family">Family</option>
                <option value="member">Member</option>
                <option value="located_in">Located In</option>
                <option value="participated_in">Participated In</option>
            </select>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label" for="filter-strength">
                Min Relationship Strength: <span id="filter-strength-value">0</span>
            </label>
            <input
                type="range"
                id="filter-strength"
                class="saga-graph-slider"
                min="0"
                max="100"
                value="0"
                step="5">
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label" for="filter-importance">
                Min Importance: <span id="filter-importance-value">0</span>
            </label>
            <input
                type="range"
                id="filter-importance"
                class="saga-graph-slider"
                min="0"
                max="100"
                value="0"
                step="10">
        </div>
    </div>

    <!-- Analytics -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">Analytics</div>
        <div class="saga-graph-button-group">
            <button
                class="saga-graph-btn-v2"
                data-action="calculate-centrality"
                title="Calculate node centrality"
                aria-label="Calculate centrality">
                Centrality
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="find-communities"
                title="Find communities/clusters"
                aria-label="Find communities">
                Communities
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="shortest-path"
                title="Find shortest path"
                aria-label="Find shortest path">
                Path
            </button>
        </div>

        <div class="saga-graph-filter-control" style="margin-top: 12px;">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="show-centrality" checked>
                Visualize Centrality
            </label>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="show-communities">
                Color by Community
            </label>
        </div>
    </div>

    <!-- Export -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">Export</div>
        <div class="saga-graph-button-group">
            <button
                class="saga-graph-btn-v2"
                data-action="export-png"
                title="Export as PNG image"
                aria-label="Export as PNG">
                PNG
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="export-svg"
                title="Export as SVG vector"
                aria-label="Export as SVG">
                SVG
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="export-csv"
                title="Export data as CSV"
                aria-label="Export as CSV">
                CSV
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="save-layout"
                title="Save current layout (S)"
                aria-label="Save layout">
                Save
            </button>
        </div>
    </div>

    <!-- Advanced -->
    <div class="saga-graph-controls-section">
        <div class="saga-graph-controls-title">Advanced</div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="enable-particles" checked>
                Particle Effects
            </label>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="enable-labels" checked>
                Show Labels
            </label>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="enable-minimap" checked>
                Show Minimap
            </label>
        </div>

        <div class="saga-graph-filter-control">
            <label class="saga-graph-filter-label">
                <input type="checkbox" id="use-canvas">
                Canvas Rendering
            </label>
        </div>

        <div class="saga-graph-button-group" style="margin-top: 12px;">
            <button
                class="saga-graph-btn-v2"
                data-action="show-help"
                title="Show keyboard shortcuts"
                aria-label="Show help">
                &#63;
            </button>
            <button
                class="saga-graph-btn-v2"
                data-action="reset-filters"
                title="Reset all filters"
                aria-label="Reset filters">
                Reset
            </button>
        </div>
    </div>
</div>

<!-- Temporal Playback Controls (initially hidden) -->
<div class="saga-graph-temporal-controls" style="display: none;">
    <div class="saga-graph-controls-title">Temporal Playback</div>

    <div class="saga-graph-filter-control">
        <label class="saga-graph-filter-label">
            Timeline: <span id="temporal-time-label">0</span>
        </label>
        <input
            type="range"
            id="temporal-slider"
            class="saga-graph-slider saga-graph-timeline-slider"
            min="0"
            max="100"
            value="0"
            step="1">
    </div>

    <div class="saga-graph-playback-buttons">
        <button
            class="saga-graph-playback-btn"
            data-action="temporal-first"
            title="Go to start"
            aria-label="Go to start">
            &#x23EE;
        </button>
        <button
            class="saga-graph-playback-btn"
            data-action="temporal-prev"
            title="Previous"
            aria-label="Previous step">
            &#x23F4;
        </button>
        <button
            class="saga-graph-playback-btn"
            data-action="temporal-play"
            title="Play/Pause"
            aria-label="Play or pause">
            &#x23F5;
        </button>
        <button
            class="saga-graph-playback-btn"
            data-action="temporal-next"
            title="Next"
            aria-label="Next step">
            &#x23F5;
        </button>
        <button
            class="saga-graph-playback-btn"
            data-action="temporal-last"
            title="Go to end"
            aria-label="Go to end">
            &#x23ED;
        </button>
    </div>

    <div class="saga-graph-filter-control" style="margin-top: 12px;">
        <label class="saga-graph-filter-label" for="temporal-speed">
            Speed: <span id="temporal-speed-value">1x</span>
        </label>
        <input
            type="range"
            id="temporal-speed"
            class="saga-graph-slider"
            min="0.5"
            max="5"
            value="1"
            step="0.5">
    </div>
</div>

<!-- Keyboard Shortcuts Help (initially hidden) -->
<div class="saga-graph-shortcuts">
    <div class="saga-graph-shortcuts-title">Keyboard Shortcuts</div>

    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">F</span>
        <span class="saga-graph-shortcut-desc">Force layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">H</span>
        <span class="saga-graph-shortcut-desc">Hierarchical layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">C</span>
        <span class="saga-graph-shortcut-desc">Circular layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">R</span>
        <span class="saga-graph-shortcut-desc">Radial layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">G</span>
        <span class="saga-graph-shortcut-desc">Grid layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">K</span>
        <span class="saga-graph-shortcut-desc">Clustered layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">S</span>
        <span class="saga-graph-shortcut-desc">Save layout</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">Esc</span>
        <span class="saga-graph-shortcut-desc">Clear selection</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">Shift+Click</span>
        <span class="saga-graph-shortcut-desc">Multi-select</span>
    </div>
    <div class="saga-graph-shortcut-item">
        <span class="saga-graph-shortcut-key">Double-Click</span>
        <span class="saga-graph-shortcut-desc">Release node / Reset zoom</span>
    </div>

    <button
        class="saga-graph-btn-v2"
        data-action="close-help"
        style="margin-top: 16px; width: 100%;">
        Close
    </button>
</div>

<script>
(function() {
    'use strict';

    // Wait for graph instance
    function setupControls() {
        const graphInstances = window.sagaGraphInstances;
        if (!graphInstances) {
            setTimeout(setupControls, 100);
            return;
        }

        // Get the first graph instance (or specific one if multiple)
        const graphId = Object.keys(graphInstances)[0];
        const graph = graphInstances[graphId];

        if (!graph) {
            setTimeout(setupControls, 100);
            return;
        }

        // Layout buttons
        document.querySelectorAll('.layout-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const layout = this.dataset.layout;

                // Update active state
                document.querySelectorAll('.layout-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Switch layout
                graph.switchLayout(layout);
            });
        });

        // View controls
        document.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.action;

                switch (action) {
                    case 'zoom-in':
                        graph.zoomIn();
                        break;
                    case 'zoom-out':
                        graph.zoomOut();
                        break;
                    case 'reset-view':
                        graph.resetZoom();
                        break;
                    case 'fit-view':
                        graph.fitToView();
                        break;
                    case 'fullscreen':
                        graph.toggleFullscreen();
                        break;
                    case 'export-png':
                        graph.exportPNG();
                        break;
                    case 'export-svg':
                        graph.exportSVG();
                        break;
                    case 'export-csv':
                        window.location.href = graph.buildAPIUrl() + '&export=csv';
                        break;
                    case 'save-layout':
                        graph.saveLayout();
                        break;
                    case 'show-help':
                        document.querySelector('.saga-graph-shortcuts').classList.add('visible');
                        break;
                    case 'close-help':
                        document.querySelector('.saga-graph-shortcuts').classList.remove('visible');
                        break;
                    case 'reset-filters':
                        resetFilters();
                        break;
                    case 'calculate-centrality':
                        graph.calculateAnalytics();
                        break;
                    case 'find-communities':
                        graph.findCommunities();
                        break;
                    case 'shortest-path':
                        promptShortestPath(graph);
                        break;
                }
            });
        });

        // Filters
        const filterEntityType = document.getElementById('filter-entity-type');
        const filterRelationshipType = document.getElementById('filter-relationship-type');
        const filterStrength = document.getElementById('filter-strength');
        const filterImportance = document.getElementById('filter-importance');

        if (filterEntityType) {
            filterEntityType.addEventListener('change', function() {
                graph.filterByEntityType(this.value);
            });
        }

        if (filterRelationshipType) {
            filterRelationshipType.addEventListener('change', function() {
                graph.filterByRelationshipType(this.value);
            });
        }

        if (filterStrength) {
            filterStrength.addEventListener('input', function() {
                document.getElementById('filter-strength-value').textContent = this.value;
                graph.filterByStrength(parseInt(this.value));
            });
        }

        if (filterImportance) {
            filterImportance.addEventListener('input', function() {
                document.getElementById('filter-importance-value').textContent = this.value;
                graph.filterByImportance(parseInt(this.value));
            });
        }

        // Advanced options
        document.getElementById('enable-particles')?.addEventListener('change', function() {
            graph.toggleParticles(this.checked);
        });

        document.getElementById('enable-labels')?.addEventListener('change', function() {
            graph.toggleLabels(this.checked);
        });

        document.getElementById('enable-minimap')?.addEventListener('change', function() {
            graph.toggleMinimap(this.checked);
        });

        document.getElementById('use-canvas')?.addEventListener('change', function() {
            graph.toggleCanvas(this.checked);
        });

        // Analytics checkboxes
        document.getElementById('show-centrality')?.addEventListener('change', function() {
            graph.toggleCentralityVisualization(this.checked);
        });

        document.getElementById('show-communities')?.addEventListener('change', function() {
            graph.toggleCommunityColors(this.checked);
        });

        function resetFilters() {
            if (filterEntityType) filterEntityType.value = '';
            if (filterRelationshipType) filterRelationshipType.value = '';
            if (filterStrength) filterStrength.value = '0';
            if (filterImportance) filterImportance.value = '0';
            document.getElementById('filter-strength-value').textContent = '0';
            document.getElementById('filter-importance-value').textContent = '0';

            graph.clearFilters();
        }

        function promptShortestPath(graph) {
            const sourceId = prompt('Enter source node ID:');
            const targetId = prompt('Enter target node ID:');

            if (sourceId && targetId) {
                graph.findAndHighlightPath(parseInt(sourceId), parseInt(targetId));
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupControls);
    } else {
        setupControls();
    }
})();
</script>
