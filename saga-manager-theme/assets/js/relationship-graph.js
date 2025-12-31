/**
 * Relationship Graph Visualization
 *
 * Interactive force-directed graph using D3.js v7
 * Optimized for performance with 100+ nodes
 *
 * @package SagaManager
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Color palette for entity types (color-blind friendly)
     */
    const ENTITY_COLORS = {
        character: '#0173B2', // Blue
        location: '#029E73',  // Green
        event: '#D55E00',     // Orange
        faction: '#CC78BC',   // Purple
        artifact: '#ECE133',  // Yellow
        concept: '#56B4E9'    // Light Blue
    };

    /**
     * Icons for entity types (Unicode symbols)
     */
    const ENTITY_ICONS = {
        character: '\u{1F464}', // Person
        location: '\u{1F4CD}',  // Pin
        event: '\u{1F4C5}',     // Calendar
        faction: '\u{1F6E1}',   // Shield
        artifact: '\u{1F3FA}',  // Amphora
        concept: '\u{1F4A1}'    // Light bulb
    };

    /**
     * Relationship Graph Class
     */
    class RelationshipGraph {
        constructor(containerId, options = {}) {
            this.containerId = containerId;
            this.container = document.getElementById(containerId);

            if (!this.container) {
                console.error(`Container ${containerId} not found`);
                return;
            }

            // Configuration
            this.config = {
                entityId: options.entityId || 0,
                depth: options.depth || 1,
                entityType: options.entityType || '',
                relationshipType: options.relationshipType || '',
                limit: options.limit || 100,
                width: options.width || this.container.clientWidth,
                height: options.height || 600,
                enableZoom: options.enableZoom !== false,
                enableDrag: options.enableDrag !== false,
                enableTooltip: options.enableTooltip !== false,
                enableMinimap: options.enableMinimap !== false,
                ...options
            };

            // State
            this.data = { nodes: [], edges: [] };
            this.simulation = null;
            this.svg = null;
            this.g = null;
            this.zoom = null;
            this.selectedNode = null;
            this.focusedNode = null;

            // Initialize
            this.init();
        }

        /**
         * Initialize the graph
         */
        init() {
            this.createSVG();
            this.createDefs();
            this.createLayers();
            this.setupZoom();
            this.setupSimulation();
            this.loadData();

            // Accessibility
            this.setupKeyboardNavigation();
            this.createAriaLiveRegion();
        }

        /**
         * Create SVG container
         */
        createSVG() {
            this.svg = d3.select(`#${this.containerId}`)
                .append('svg')
                .attr('width', this.config.width)
                .attr('height', this.config.height)
                .attr('role', 'application')
                .attr('aria-label', 'Entity Relationship Graph')
                .attr('class', 'saga-graph-svg');

            // Create main group for zoom/pan
            this.g = this.svg.append('g')
                .attr('class', 'saga-graph-main');
        }

        /**
         * Create SVG definitions (markers, gradients)
         */
        createDefs() {
            const defs = this.svg.append('defs');

            // Arrow markers for different relationship types
            Object.keys(ENTITY_COLORS).forEach(type => {
                defs.append('marker')
                    .attr('id', `arrow-${type}`)
                    .attr('viewBox', '0 -5 10 10')
                    .attr('refX', 25)
                    .attr('refY', 0)
                    .attr('markerWidth', 6)
                    .attr('markerHeight', 6)
                    .attr('orient', 'auto')
                    .append('path')
                    .attr('d', 'M0,-5L10,0L0,5')
                    .attr('fill', ENTITY_COLORS[type]);
            });

            // Gradient for importance
            const gradient = defs.append('radialGradient')
                .attr('id', 'importance-gradient');

            gradient.append('stop')
                .attr('offset', '0%')
                .attr('stop-color', '#fff')
                .attr('stop-opacity', 0.8);

            gradient.append('stop')
                .attr('offset', '100%')
                .attr('stop-color', '#000')
                .attr('stop-opacity', 0.1);
        }

        /**
         * Create graph layers (edges, nodes, labels)
         */
        createLayers() {
            this.edgeLayer = this.g.append('g').attr('class', 'edges');
            this.nodeLayer = this.g.append('g').attr('class', 'nodes');
            this.labelLayer = this.g.append('g').attr('class', 'labels');
        }

        /**
         * Setup zoom and pan
         */
        setupZoom() {
            if (!this.config.enableZoom) return;

            this.zoom = d3.zoom()
                .scaleExtent([0.1, 10])
                .on('zoom', (event) => {
                    this.g.attr('transform', event.transform);
                });

            this.svg.call(this.zoom);

            // Add zoom controls
            this.createZoomControls();
        }

        /**
         * Create zoom control buttons
         */
        createZoomControls() {
            const controls = d3.select(`#${this.containerId}`)
                .append('div')
                .attr('class', 'saga-graph-controls')
                .attr('role', 'toolbar')
                .attr('aria-label', 'Graph controls');

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Zoom in')
                .attr('title', 'Zoom in')
                .html('&#43;')
                .on('click', () => this.zoomIn());

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Zoom out')
                .attr('title', 'Zoom out')
                .html('&#8722;')
                .on('click', () => this.zoomOut());

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Reset view')
                .attr('title', 'Reset view')
                .html('&#8634;')
                .on('click', () => this.resetZoom());

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Fullscreen')
                .attr('title', 'Fullscreen')
                .html('&#x26F6;')
                .on('click', () => this.toggleFullscreen());

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Export as PNG')
                .attr('title', 'Export as PNG')
                .text('PNG')
                .on('click', () => this.exportPNG());

            controls.append('button')
                .attr('class', 'saga-graph-btn')
                .attr('aria-label', 'Export as SVG')
                .attr('title', 'Export as SVG')
                .text('SVG')
                .on('click', () => this.exportSVG());
        }

        /**
         * Setup force simulation
         */
        setupSimulation() {
            this.simulation = d3.forceSimulation()
                .force('link', d3.forceLink().id(d => d.id).distance(100))
                .force('charge', d3.forceManyBody().strength(-300))
                .force('center', d3.forceCenter(this.config.width / 2, this.config.height / 2))
                .force('collision', d3.forceCollide().radius(30))
                .on('tick', () => this.tick());
        }

        /**
         * Load graph data
         */
        async loadData() {
            this.showLoading();

            try {
                const url = this.buildAPIUrl();
                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success !== undefined) {
                    // AJAX response format
                    this.data = result.data;
                } else {
                    // REST API response format
                    this.data = result;
                }

                this.render();
                this.hideLoading();

                // Announce to screen readers
                this.announceGraphLoaded();

            } catch (error) {
                console.error('Failed to load graph data:', error);
                this.showError('Failed to load graph data. Please try again.');
                this.hideLoading();
            }
        }

        /**
         * Build API URL based on configuration
         */
        buildAPIUrl() {
            if (this.config.useRestAPI) {
                // REST API endpoint
                const params = new URLSearchParams({
                    depth: this.config.depth,
                    limit: this.config.limit
                });

                if (this.config.entityType) params.append('entity_type', this.config.entityType);
                if (this.config.relationshipType) params.append('relationship_type', this.config.relationshipType);

                if (this.config.entityId > 0) {
                    return `/wp-json/saga/v1/entities/${this.config.entityId}/relationships?${params}`;
                } else {
                    return `/wp-json/saga/v1/graph/all?${params}`;
                }
            } else {
                // AJAX endpoint
                const params = new URLSearchParams({
                    action: 'saga_get_graph_data',
                    nonce: window.sagaGraphData?.nonce || '',
                    entity_id: this.config.entityId,
                    depth: this.config.depth,
                    limit: this.config.limit
                });

                if (this.config.entityType) params.append('entity_type', this.config.entityType);
                if (this.config.relationshipType) params.append('relationship_type', this.config.relationshipType);

                return `${window.sagaGraphData?.ajaxUrl || '/wp-admin/admin-ajax.php'}?${params}`;
            }
        }

        /**
         * Render the graph
         */
        render() {
            if (!this.data.nodes || this.data.nodes.length === 0) {
                this.showError('No entities found.');
                return;
            }

            // Clear existing elements
            this.edgeLayer.selectAll('*').remove();
            this.nodeLayer.selectAll('*').remove();
            this.labelLayer.selectAll('*').remove();

            // Render edges
            this.renderEdges();

            // Render nodes
            this.renderNodes();

            // Render labels
            this.renderLabels();

            // Update simulation
            this.simulation.nodes(this.data.nodes);
            this.simulation.force('link').links(this.data.edges);
            this.simulation.alpha(1).restart();

            // Load saved layout if exists
            this.loadSavedLayout();
        }

        /**
         * Render edges (relationships)
         */
        renderEdges() {
            const edges = this.edgeLayer
                .selectAll('line')
                .data(this.data.edges)
                .enter()
                .append('line')
                .attr('class', 'saga-graph-edge')
                .attr('stroke', d => this.getEdgeColor(d))
                .attr('stroke-width', d => this.getEdgeWidth(d))
                .attr('stroke-opacity', 0.6)
                .attr('marker-end', d => `url(#arrow-${this.getNodeType(d.source)})`)
                .on('mouseenter', (event, d) => this.showEdgeTooltip(event, d))
                .on('mouseleave', () => this.hideTooltip());

            // Edge labels
            this.edgeLayer
                .selectAll('text')
                .data(this.data.edges)
                .enter()
                .append('text')
                .attr('class', 'saga-graph-edge-label')
                .attr('text-anchor', 'middle')
                .attr('font-size', '10px')
                .attr('fill', '#666')
                .text(d => d.label);

            this.edges = edges;
        }

        /**
         * Render nodes (entities)
         */
        renderNodes() {
            const nodeGroup = this.nodeLayer
                .selectAll('g')
                .data(this.data.nodes)
                .enter()
                .append('g')
                .attr('class', 'saga-graph-node')
                .attr('tabindex', 0)
                .attr('role', 'button')
                .attr('aria-label', d => `${d.label}, ${d.type}`)
                .call(this.config.enableDrag ? this.drag() : () => {})
                .on('click', (event, d) => this.handleNodeClick(event, d))
                .on('mouseenter', (event, d) => this.showNodeTooltip(event, d))
                .on('mouseleave', () => this.hideTooltip())
                .on('focus', (event, d) => this.handleNodeFocus(event, d))
                .on('blur', () => this.handleNodeBlur());

            // Node circles
            nodeGroup.append('circle')
                .attr('class', 'saga-graph-node-circle')
                .attr('r', d => this.getNodeRadius(d))
                .attr('fill', d => ENTITY_COLORS[d.type] || '#999')
                .attr('stroke', '#fff')
                .attr('stroke-width', 2);

            // Node icons
            nodeGroup.append('text')
                .attr('class', 'saga-graph-node-icon')
                .attr('text-anchor', 'middle')
                .attr('dominant-baseline', 'central')
                .attr('font-size', '16px')
                .text(d => ENTITY_ICONS[d.type] || '?');

            this.nodes = nodeGroup;
        }

        /**
         * Render labels
         */
        renderLabels() {
            const labels = this.labelLayer
                .selectAll('text')
                .data(this.data.nodes)
                .enter()
                .append('text')
                .attr('class', 'saga-graph-label')
                .attr('text-anchor', 'middle')
                .attr('dy', d => this.getNodeRadius(d) + 15)
                .attr('font-size', '12px')
                .attr('fill', '#333')
                .attr('pointer-events', 'none')
                .text(d => this.truncateLabel(d.label, 20));

            this.labels = labels;
        }

        /**
         * Simulation tick handler
         */
        tick() {
            // Update edges
            if (this.edges) {
                this.edges
                    .attr('x1', d => d.source.x)
                    .attr('y1', d => d.source.y)
                    .attr('x2', d => d.target.x)
                    .attr('y2', d => d.target.y);

                // Update edge labels
                this.edgeLayer.selectAll('text')
                    .attr('x', d => (d.source.x + d.target.x) / 2)
                    .attr('y', d => (d.source.y + d.target.y) / 2);
            }

            // Update nodes
            if (this.nodes) {
                this.nodes.attr('transform', d => `translate(${d.x},${d.y})`);
            }

            // Update labels
            if (this.labels) {
                this.labels
                    .attr('x', d => d.x)
                    .attr('y', d => d.y);
            }
        }

        /**
         * Drag behavior
         */
        drag() {
            return d3.drag()
                .on('start', (event, d) => {
                    if (!event.active) this.simulation.alphaTarget(0.3).restart();
                    d.fx = d.x;
                    d.fy = d.y;
                })
                .on('drag', (event, d) => {
                    d.fx = event.x;
                    d.fy = event.y;
                })
                .on('end', (event, d) => {
                    if (!event.active) this.simulation.alphaTarget(0);
                    // Keep position fixed after drag (can be released with double-click)
                });
        }

        /**
         * Handle node click
         */
        handleNodeClick(event, node) {
            event.stopPropagation();

            // Double-click to release fixed position
            if (event.detail === 2) {
                node.fx = null;
                node.fy = null;
                this.simulation.alpha(0.3).restart();
                return;
            }

            // Single click to select/navigate
            if (node.url) {
                window.location.href = node.url;
            } else {
                this.selectNode(node);
            }
        }

        /**
         * Select a node
         */
        selectNode(node) {
            // Deselect previous
            if (this.selectedNode) {
                this.nodes.filter(d => d.id === this.selectedNode.id)
                    .select('circle')
                    .attr('stroke', '#fff')
                    .attr('stroke-width', 2);
            }

            // Select new
            this.selectedNode = node;
            this.nodes.filter(d => d.id === node.id)
                .select('circle')
                .attr('stroke', '#000')
                .attr('stroke-width', 4);

            // Highlight connected nodes
            this.highlightConnections(node);
        }

        /**
         * Highlight connected nodes and edges
         */
        highlightConnections(node) {
            const connectedNodeIds = new Set();

            // Find connected edges
            this.data.edges.forEach(edge => {
                if (edge.source.id === node.id || edge.target.id === node.id) {
                    connectedNodeIds.add(edge.source.id);
                    connectedNodeIds.add(edge.target.id);
                }
            });

            // Dim unconnected nodes
            this.nodes.attr('opacity', d => connectedNodeIds.has(d.id) ? 1 : 0.3);
            this.edges.attr('opacity', d =>
                (d.source.id === node.id || d.target.id === node.id) ? 1 : 0.1
            );
        }

        /**
         * Handle node focus (keyboard navigation)
         */
        handleNodeFocus(event, node) {
            this.focusedNode = node;
            this.announceNode(node);

            // Highlight focused node
            d3.select(event.currentTarget)
                .select('circle')
                .attr('stroke', '#ff9900')
                .attr('stroke-width', 3);
        }

        /**
         * Handle node blur
         */
        handleNodeBlur() {
            if (this.focusedNode && this.focusedNode !== this.selectedNode) {
                this.nodes.filter(d => d.id === this.focusedNode.id)
                    .select('circle')
                    .attr('stroke', '#fff')
                    .attr('stroke-width', 2);
            }
            this.focusedNode = null;
        }

        /**
         * Show node tooltip
         */
        showNodeTooltip(event, node) {
            if (!this.config.enableTooltip) return;

            const tooltip = this.getOrCreateTooltip();

            let html = `
                <strong>${node.label}</strong><br>
                <span style="color: ${ENTITY_COLORS[node.type]}">${node.type}</span><br>
                Importance: ${node.importance}
            `;

            if (node.thumbnail) {
                html = `<img src="${node.thumbnail}" alt="${node.label}" style="width: 100%; max-width: 150px; margin-bottom: 8px;"><br>` + html;
            }

            tooltip.html(html)
                .style('left', (event.pageX + 10) + 'px')
                .style('top', (event.pageY + 10) + 'px')
                .style('display', 'block');
        }

        /**
         * Show edge tooltip
         */
        showEdgeTooltip(event, edge) {
            if (!this.config.enableTooltip) return;

            const tooltip = this.getOrCreateTooltip();

            const html = `
                <strong>${edge.label}</strong><br>
                Strength: ${edge.strength}
            `;

            tooltip.html(html)
                .style('left', (event.pageX + 10) + 'px')
                .style('top', (event.pageY + 10) + 'px')
                .style('display', 'block');
        }

        /**
         * Hide tooltip
         */
        hideTooltip() {
            const tooltip = d3.select('.saga-graph-tooltip');
            if (!tooltip.empty()) {
                tooltip.style('display', 'none');
            }
        }

        /**
         * Get or create tooltip element
         */
        getOrCreateTooltip() {
            let tooltip = d3.select('.saga-graph-tooltip');

            if (tooltip.empty()) {
                tooltip = d3.select('body')
                    .append('div')
                    .attr('class', 'saga-graph-tooltip')
                    .style('position', 'absolute')
                    .style('display', 'none')
                    .style('pointer-events', 'none');
            }

            return tooltip;
        }

        /**
         * Setup keyboard navigation
         */
        setupKeyboardNavigation() {
            this.container.addEventListener('keydown', (event) => {
                if (!this.focusedNode) return;

                switch (event.key) {
                    case 'Enter':
                    case ' ':
                        event.preventDefault();
                        if (this.focusedNode.url) {
                            window.location.href = this.focusedNode.url;
                        }
                        break;

                    case 'Escape':
                        event.preventDefault();
                        this.clearSelection();
                        break;
                }
            });
        }

        /**
         * Clear node selection
         */
        clearSelection() {
            if (this.selectedNode) {
                this.nodes.filter(d => d.id === this.selectedNode.id)
                    .select('circle')
                    .attr('stroke', '#fff')
                    .attr('stroke-width', 2);
                this.selectedNode = null;
            }

            // Reset opacity
            this.nodes.attr('opacity', 1);
            this.edges.attr('opacity', 0.6);
        }

        /**
         * Create ARIA live region for announcements
         */
        createAriaLiveRegion() {
            const liveRegion = document.createElement('div');
            liveRegion.id = `${this.containerId}-live`;
            liveRegion.className = 'saga-graph-sr-only';
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            this.container.appendChild(liveRegion);
            this.liveRegion = liveRegion;
        }

        /**
         * Announce text to screen readers
         */
        announce(text) {
            if (this.liveRegion) {
                this.liveRegion.textContent = text;
            }
        }

        /**
         * Announce graph loaded
         */
        announceGraphLoaded() {
            const nodeCount = this.data.nodes.length;
            const edgeCount = this.data.edges.length;
            this.announce(`Graph loaded with ${nodeCount} entities and ${edgeCount} relationships. Use Tab to navigate between entities, Enter to visit entity page.`);
        }

        /**
         * Announce node
         */
        announceNode(node) {
            const connections = this.data.edges.filter(e =>
                e.source.id === node.id || e.target.id === node.id
            ).length;

            this.announce(`${node.label}, ${node.type}, importance ${node.importance}, ${connections} connections.`);
        }

        /**
         * Zoom controls
         */
        zoomIn() {
            this.svg.transition().duration(300).call(this.zoom.scaleBy, 1.3);
        }

        zoomOut() {
            this.svg.transition().duration(300).call(this.zoom.scaleBy, 0.7);
        }

        resetZoom() {
            this.svg.transition().duration(500).call(
                this.zoom.transform,
                d3.zoomIdentity.translate(0, 0).scale(1)
            );
        }

        /**
         * Toggle fullscreen
         */
        toggleFullscreen() {
            if (!document.fullscreenElement) {
                this.container.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }

        /**
         * Export as PNG
         */
        exportPNG() {
            const svgElement = this.svg.node();
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            canvas.width = this.config.width;
            canvas.height = this.config.height;

            const svgString = new XMLSerializer().serializeToString(svgElement);
            const img = new Image();

            img.onload = () => {
                ctx.drawImage(img, 0, 0);
                canvas.toBlob(blob => {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'relationship-graph.png';
                    a.click();
                    URL.revokeObjectURL(url);
                });
            };

            img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgString)));
        }

        /**
         * Export as SVG
         */
        exportSVG() {
            const svgElement = this.svg.node();
            const svgString = new XMLSerializer().serializeToString(svgElement);
            const blob = new Blob([svgString], { type: 'image/svg+xml' });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'relationship-graph.svg';
            a.click();

            URL.revokeObjectURL(url);
        }

        /**
         * Save layout to localStorage
         */
        saveLayout() {
            const layout = this.data.nodes.map(node => ({
                id: node.id,
                x: node.x,
                y: node.y,
                fx: node.fx,
                fy: node.fy
            }));

            localStorage.setItem(`saga-graph-layout-${this.config.entityId}`, JSON.stringify(layout));
        }

        /**
         * Load saved layout from localStorage
         */
        loadSavedLayout() {
            const saved = localStorage.getItem(`saga-graph-layout-${this.config.entityId}`);

            if (saved) {
                try {
                    const layout = JSON.parse(saved);
                    const layoutMap = new Map(layout.map(l => [l.id, l]));

                    this.data.nodes.forEach(node => {
                        const savedPos = layoutMap.get(node.id);
                        if (savedPos) {
                            node.x = savedPos.x;
                            node.y = savedPos.y;
                            node.fx = savedPos.fx;
                            node.fy = savedPos.fy;
                        }
                    });
                } catch (e) {
                    console.error('Failed to load saved layout:', e);
                }
            }
        }

        /**
         * Utility: Get node radius based on importance
         */
        getNodeRadius(node) {
            return 10 + (node.importance / 10);
        }

        /**
         * Utility: Get edge width based on strength
         */
        getEdgeWidth(edge) {
            return 1 + (edge.strength / 25);
        }

        /**
         * Utility: Get edge color
         */
        getEdgeColor(edge) {
            const sourceType = this.getNodeType(edge.source);
            return ENTITY_COLORS[sourceType] || '#999';
        }

        /**
         * Utility: Get node type from node or ID
         */
        getNodeType(node) {
            if (typeof node === 'object' && node.type) {
                return node.type;
            }

            const foundNode = this.data.nodes.find(n => n.id === node);
            return foundNode ? foundNode.type : 'concept';
        }

        /**
         * Utility: Truncate label
         */
        truncateLabel(label, maxLength) {
            return label.length > maxLength ? label.substring(0, maxLength) + '...' : label;
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            const loading = document.createElement('div');
            loading.className = 'saga-graph-loading';
            loading.textContent = 'Loading graph...';
            loading.setAttribute('role', 'status');
            loading.setAttribute('aria-live', 'polite');
            this.container.appendChild(loading);
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            const loading = this.container.querySelector('.saga-graph-loading');
            if (loading) {
                loading.remove();
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            const error = document.createElement('div');
            error.className = 'saga-graph-error';
            error.textContent = message;
            error.setAttribute('role', 'alert');
            this.container.appendChild(error);
        }

        /**
         * Destroy the graph
         */
        destroy() {
            if (this.simulation) {
                this.simulation.stop();
            }

            // Save layout before destroying
            this.saveLayout();

            // Remove all elements
            if (this.svg) {
                this.svg.remove();
            }

            // Remove controls
            d3.select(`#${this.containerId} .saga-graph-controls`).remove();

            // Remove tooltip
            d3.select('.saga-graph-tooltip').remove();

            // Remove live region
            if (this.liveRegion) {
                this.liveRegion.remove();
            }
        }
    }

    // Export to window
    window.SagaRelationshipGraph = RelationshipGraph;

})();
