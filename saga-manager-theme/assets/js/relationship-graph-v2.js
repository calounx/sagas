/**
 * Enhanced Relationship Graph Visualization v2
 *
 * Advanced D3 v7 features with multiple layouts, analytics, and performance enhancements
 * Supports 1000+ nodes with Web Worker simulation and canvas rendering
 *
 * @package SagaManager
 * @since 1.3.0
 */

(function() {
    'use strict';

    /**
     * Color palette for entity types (color-blind friendly)
     */
    const ENTITY_COLORS = {
        character: '#0173B2',
        location: '#029E73',
        event: '#D55E00',
        faction: '#CC78BC',
        artifact: '#ECE133',
        concept: '#56B4E9'
    };

    /**
     * Icons for entity types
     */
    const ENTITY_ICONS = {
        character: '\u{1F464}',
        location: '\u{1F4CD}',
        event: '\u{1F4C5}',
        faction: '\u{1F6E1}',
        artifact: '\u{1F3FA}',
        concept: '\u{1F4A1}'
    };

    /**
     * Keyboard shortcuts
     */
    const KEYBOARD_SHORTCUTS = {
        'f': 'force',
        'h': 'hierarchical',
        'c': 'circular',
        'r': 'radial',
        'g': 'grid',
        'k': 'clustered',
        's': 'save',
        'Escape': 'clear'
    };

    /**
     * Enhanced Relationship Graph Class
     */
    class RelationshipGraphV2 {
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
                layout: options.layout || 'force',
                useWebWorker: options.useWebWorker !== false,
                useCanvas: options.useCanvas !== false && options.limit > 500,
                enableAnalytics: options.enableAnalytics !== false,
                enableTemporalPlayback: options.enableTemporalPlayback !== false,
                enableMultiSelect: options.enableMultiSelect !== false,
                enableLassoSelect: options.enableLassoSelect !== false,
                animationDuration: options.animationDuration || 750,
                ...options
            };

            // State
            this.data = { nodes: [], edges: [] };
            this.simulation = null;
            this.worker = null;
            this.svg = null;
            this.canvas = null;
            this.ctx = null;
            this.g = null;
            this.zoom = null;
            this.transform = d3.zoomIdentity;
            this.selectedNodes = new Set();
            this.focusedNode = null;
            this.highlightedPath = null;
            this.currentLayout = this.config.layout;
            this.analytics = null;
            this.temporalState = {
                enabled: false,
                currentTime: 0,
                minTime: 0,
                maxTime: 0,
                playing: false
            };

            // Initialize
            this.init();
        }

        /**
         * Initialize the graph
         */
        init() {
            this.createContainer();
            this.createSVG();
            this.createCanvas();
            this.createDefs();
            this.createLayers();
            this.setupZoom();
            this.setupSimulation();
            this.setupKeyboardShortcuts();
            this.setupContextMenu();
            this.loadData();
        }

        /**
         * Create container structure
         */
        createContainer() {
            this.container.classList.add('saga-graph-v2-container');
            this.container.setAttribute('role', 'application');
            this.container.setAttribute('aria-label', 'Enhanced Entity Relationship Graph');
        }

        /**
         * Create SVG container
         */
        createSVG() {
            this.svg = d3.select(`#${this.containerId}`)
                .append('svg')
                .attr('width', this.config.width)
                .attr('height', this.config.height)
                .attr('class', 'saga-graph-v2-svg');

            this.g = this.svg.append('g')
                .attr('class', 'saga-graph-v2-main');
        }

        /**
         * Create canvas for high-performance rendering
         */
        createCanvas() {
            if (!this.config.useCanvas) return;

            this.canvas = d3.select(`#${this.containerId}`)
                .insert('canvas', 'svg')
                .attr('width', this.config.width)
                .attr('height', this.config.height)
                .attr('class', 'saga-graph-v2-canvas')
                .style('position', 'absolute')
                .style('top', 0)
                .style('left', 0)
                .style('pointer-events', 'none');

            this.ctx = this.canvas.node().getContext('2d');
        }

        /**
         * Create SVG definitions
         */
        createDefs() {
            const defs = this.svg.append('defs');

            // Arrow markers with curved paths
            Object.keys(ENTITY_COLORS).forEach(type => {
                const marker = defs.append('marker')
                    .attr('id', `arrow-v2-${type}`)
                    .attr('viewBox', '0 -5 10 10')
                    .attr('refX', 25)
                    .attr('refY', 0)
                    .attr('markerWidth', 8)
                    .attr('markerHeight', 8)
                    .attr('orient', 'auto');

                marker.append('path')
                    .attr('d', 'M0,-5L10,0L0,5')
                    .attr('fill', ENTITY_COLORS[type]);
            });

            // Glow filter for highlights
            const filter = defs.append('filter')
                .attr('id', 'glow');

            filter.append('feGaussianBlur')
                .attr('stdDeviation', '3')
                .attr('result', 'coloredBlur');

            const feMerge = filter.append('feMerge');
            feMerge.append('feMergeNode').attr('in', 'coloredBlur');
            feMerge.append('feMergeNode').attr('in', 'SourceGraphic');

            // Lasso selection pattern
            defs.append('pattern')
                .attr('id', 'lasso-pattern')
                .attr('patternUnits', 'userSpaceOnUse')
                .attr('width', 4)
                .attr('height', 4)
                .append('path')
                .attr('d', 'M-1,1 l2,-2 M0,4 l4,-4 M3,5 l2,-2')
                .attr('stroke', '#0173B2')
                .attr('stroke-width', 1);
        }

        /**
         * Create graph layers
         */
        createLayers() {
            this.edgeLayer = this.g.append('g').attr('class', 'edges-v2');
            this.nodeLayer = this.g.append('g').attr('class', 'nodes-v2');
            this.labelLayer = this.g.append('g').attr('class', 'labels-v2');
            this.particleLayer = this.g.append('g').attr('class', 'particles-v2');
            this.highlightLayer = this.g.append('g').attr('class', 'highlights-v2');
        }

        /**
         * Setup zoom with D3 v7 features
         */
        setupZoom() {
            this.zoom = d3.zoom()
                .scaleExtent([0.1, 10])
                .filter(event => {
                    // Disable zoom on right-click
                    return event.button !== 2;
                })
                .on('zoom', (event) => {
                    this.transform = event.transform;
                    this.g.attr('transform', event.transform);

                    // Update canvas transform if enabled
                    if (this.config.useCanvas) {
                        this.renderCanvas();
                    }
                });

            this.svg.call(this.zoom);

            // Double-click to reset zoom
            this.svg.on('dblclick.zoom', () => {
                this.svg.transition()
                    .duration(this.config.animationDuration)
                    .call(this.zoom.transform, d3.zoomIdentity);
            });
        }

        /**
         * Setup force simulation (or Web Worker)
         */
        setupSimulation() {
            if (this.config.useWebWorker && window.Worker) {
                this.setupWebWorker();
            } else {
                this.setupLocalSimulation();
            }
        }

        /**
         * Setup Web Worker for simulation
         */
        setupWebWorker() {
            const workerPath = this.config.workerPath ||
                window.sagaGraphData?.workerUrl ||
                '/wp-content/themes/saga-manager-theme/assets/js/graph-worker.js';

            try {
                this.worker = new Worker(workerPath);

                this.worker.addEventListener('message', (e) => {
                    this.handleWorkerMessage(e.data);
                });

                this.worker.addEventListener('error', (e) => {
                    console.error('Worker error:', e);
                    // Fallback to local simulation
                    this.config.useWebWorker = false;
                    this.setupLocalSimulation();
                });
            } catch (e) {
                console.warn('Web Worker not available, using local simulation');
                this.config.useWebWorker = false;
                this.setupLocalSimulation();
            }
        }

        /**
         * Setup local D3 simulation
         */
        setupLocalSimulation() {
            this.simulation = d3.forceSimulation()
                .on('tick', () => this.tick())
                .on('end', () => this.onSimulationEnd());
        }

        /**
         * Handle messages from Web Worker
         */
        handleWorkerMessage(data) {
            switch (data.type) {
                case 'tick':
                    this.applyWorkerPositions(data.nodes);
                    this.tick();
                    break;

                case 'end':
                    this.applyWorkerPositions(data.nodes);
                    this.onSimulationEnd();
                    break;

                case 'centrality-result':
                    this.displayCentralityResults(data.centrality);
                    break;

                case 'communities-result':
                    this.displayCommunities(data.communities);
                    break;

                case 'shortest-path-result':
                    this.highlightPath(data.path);
                    break;
            }
        }

        /**
         * Apply positions from worker to nodes
         */
        applyWorkerPositions(workerNodes) {
            const nodeMap = new Map(this.data.nodes.map(n => [n.id, n]));

            workerNodes.forEach(wn => {
                const node = nodeMap.get(wn.id);
                if (node) {
                    node.x = wn.x;
                    node.y = wn.y;
                    if (wn.vx !== undefined) node.vx = wn.vx;
                    if (wn.vy !== undefined) node.vy = wn.vy;
                }
            });
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
                this.data = result.success !== undefined ? result.data : result;

                // Calculate analytics if enabled
                if (this.config.enableAnalytics) {
                    this.calculateAnalytics();
                }

                this.render();
                this.hideLoading();
                this.announceGraphLoaded();

            } catch (error) {
                console.error('Failed to load graph data:', error);
                this.showError('Failed to load graph data. Please try again.');
                this.hideLoading();
            }
        }

        /**
         * Build API URL
         */
        buildAPIUrl() {
            const params = new URLSearchParams({
                depth: this.config.depth,
                limit: this.config.limit
            });

            if (this.config.entityType) params.append('entity_type', this.config.entityType);
            if (this.config.relationshipType) params.append('relationship_type', this.config.relationshipType);

            const baseUrl = this.config.useRestAPI
                ? (this.config.entityId > 0
                    ? `/wp-json/saga/v1/entities/${this.config.entityId}/relationships`
                    : `/wp-json/saga/v1/graph/all`)
                : window.sagaGraphData?.ajaxUrl || '/wp-admin/admin-ajax.php';

            if (!this.config.useRestAPI) {
                params.append('action', 'saga_get_graph_data');
                params.append('nonce', window.sagaGraphData?.nonce || '');
                params.append('entity_id', this.config.entityId);
            }

            return `${baseUrl}?${params}`;
        }

        /**
         * Render the graph with current layout
         */
        render() {
            if (!this.data.nodes || this.data.nodes.length === 0) {
                this.showError('No entities found.');
                return;
            }

            // Apply layout
            this.applyLayout(this.currentLayout);

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

            // Start simulation if needed
            if (this.currentLayout === 'force' || this.currentLayout === 'clustered') {
                if (this.worker) {
                    this.worker.postMessage({
                        type: 'init',
                        data: {
                            nodes: this.data.nodes,
                            edges: this.data.edges,
                            config: {
                                width: this.config.width,
                                height: this.config.height,
                                linkDistance: this.config.linkDistance,
                                chargeStrength: this.config.chargeStrength
                            }
                        }
                    });
                } else if (this.simulation) {
                    this.simulation.nodes(this.data.nodes);

                    const layoutConfig = this.getLayoutConfig();
                    const sim = window.SagaGraphLayouts[this.currentLayout](
                        this.data.nodes,
                        this.data.edges,
                        this.config.width,
                        this.config.height,
                        layoutConfig
                    );

                    if (sim) {
                        this.simulation = sim;
                        this.simulation
                            .on('tick', () => this.tick())
                            .on('end', () => this.onSimulationEnd());
                    }
                }
            } else {
                // Static layout - just render once
                this.tick();
            }
        }

        /**
         * Apply layout to graph
         */
        applyLayout(layoutName) {
            if (!window.SagaGraphLayouts) {
                console.error('SagaGraphLayouts not loaded');
                return;
            }

            const layoutConfig = this.getLayoutConfig();

            const simulation = window.SagaGraphLayouts[layoutName](
                this.data.nodes,
                this.data.edges,
                this.config.width,
                this.config.height,
                layoutConfig
            );

            if (simulation && !this.worker) {
                this.simulation = simulation;
            }

            this.currentLayout = layoutName;
            this.announceLayoutChange(layoutName);
        }

        /**
         * Get layout-specific configuration
         */
        getLayoutConfig() {
            const config = {
                linkDistance: this.config.linkDistance || 100,
                chargeStrength: this.config.chargeStrength || -300,
                collisionRadius: this.config.collisionRadius || 30,
            };

            // Layout-specific options
            switch (this.currentLayout) {
                case 'hierarchical':
                    config.orientation = this.config.hierarchicalOrientation || 'vertical';
                    config.rootId = this.config.entityId > 0 ? this.config.entityId : null;
                    break;

                case 'radial':
                    config.rootId = this.config.entityId > 0 ? this.config.entityId : null;
                    config.radiusStep = this.config.radiusStep || 100;
                    break;

                case 'circular':
                    config.sortBy = this.config.circularSortBy || 'importance';
                    break;

                case 'grid':
                    config.columns = this.config.gridColumns || Math.ceil(Math.sqrt(this.data.nodes.length));
                    config.sortBy = this.config.gridSortBy || 'type';
                    break;

                case 'clustered':
                    config.clusterBy = this.config.clusterBy || 'type';
                    config.clusterStrength = this.config.clusterStrength || 0.5;
                    break;
            }

            return config;
        }

        /**
         * Render edges with curved paths
         */
        renderEdges() {
            const edges = this.edgeLayer
                .selectAll('g.edge-group')
                .data(this.data.edges)
                .enter()
                .append('g')
                .attr('class', 'edge-group');

            // Curved edge paths
            edges.append('path')
                .attr('class', 'saga-graph-edge-v2')
                .attr('stroke', d => this.getEdgeColor(d))
                .attr('stroke-width', d => this.getEdgeWidth(d))
                .attr('stroke-opacity', 0.6)
                .attr('fill', 'none')
                .attr('marker-end', d => `url(#arrow-v2-${this.getNodeType(d.source)})`)
                .on('mouseenter', (event, d) => this.showEdgeTooltip(event, d))
                .on('mouseleave', () => this.hideTooltip())
                .on('click', (event, d) => this.handleEdgeClick(event, d));

            // Edge labels
            edges.append('text')
                .attr('class', 'saga-graph-edge-label-v2')
                .attr('text-anchor', 'middle')
                .attr('font-size', '10px')
                .attr('fill', '#666')
                .attr('opacity', 0)
                .text(d => d.label || d.relationship_type);

            this.edges = edges;
        }

        /**
         * Render nodes with enhanced interactions
         */
        renderNodes() {
            const nodeGroup = this.nodeLayer
                .selectAll('g.node-group')
                .data(this.data.nodes)
                .enter()
                .append('g')
                .attr('class', 'saga-graph-node-v2')
                .attr('tabindex', 0)
                .attr('role', 'button')
                .attr('aria-label', d => `${d.label}, ${d.type}`)
                .call(this.drag())
                .on('click', (event, d) => this.handleNodeClick(event, d))
                .on('contextmenu', (event, d) => this.handleNodeContextMenu(event, d))
                .on('mouseenter', (event, d) => this.showNodeTooltip(event, d))
                .on('mouseleave', () => this.hideTooltip())
                .on('focus', (event, d) => this.handleNodeFocus(event, d))
                .on('blur', () => this.handleNodeBlur());

            // Node circles with importance-based size
            nodeGroup.append('circle')
                .attr('class', 'saga-graph-node-circle-v2')
                .attr('r', d => this.getNodeRadius(d))
                .attr('fill', d => ENTITY_COLORS[d.type] || '#999')
                .attr('stroke', '#fff')
                .attr('stroke-width', 2);

            // Node icons
            nodeGroup.append('text')
                .attr('class', 'saga-graph-node-icon-v2')
                .attr('text-anchor', 'middle')
                .attr('dominant-baseline', 'central')
                .attr('font-size', '16px')
                .attr('pointer-events', 'none')
                .text(d => ENTITY_ICONS[d.type] || '?');

            // Importance ring
            nodeGroup.filter(d => d.importance > 70)
                .append('circle')
                .attr('class', 'importance-ring')
                .attr('r', d => this.getNodeRadius(d) + 4)
                .attr('fill', 'none')
                .attr('stroke', '#FFD700')
                .attr('stroke-width', 2)
                .attr('opacity', 0.5);

            this.nodes = nodeGroup;
        }

        /**
         * Render labels with LOD (level of detail)
         */
        renderLabels() {
            const labels = this.labelLayer
                .selectAll('text')
                .data(this.data.nodes)
                .enter()
                .append('text')
                .attr('class', 'saga-graph-label-v2')
                .attr('text-anchor', 'middle')
                .attr('dy', d => this.getNodeRadius(d) + 15)
                .attr('font-size', '12px')
                .attr('fill', '#333')
                .attr('pointer-events', 'none')
                .text(d => this.truncateLabel(d.label, 20));

            this.labels = labels;
        }

        /**
         * Tick handler with LOD and canvas rendering
         */
        tick() {
            const scale = this.transform.k;

            // Update edges with curved paths
            if (this.edges) {
                this.edges.select('path')
                    .attr('d', d => this.curvedEdgePath(d));

                // Show edge labels on hover or high zoom
                this.edges.select('text')
                    .attr('x', d => (d.source.x + d.target.x) / 2)
                    .attr('y', d => (d.source.y + d.target.y) / 2)
                    .attr('opacity', scale > 1.5 ? 1 : 0);
            }

            // Update nodes
            if (this.nodes) {
                this.nodes.attr('transform', d => `translate(${d.x},${d.y})`);
            }

            // Update labels with LOD
            if (this.labels) {
                this.labels
                    .attr('x', d => d.x)
                    .attr('y', d => d.y)
                    .attr('opacity', scale > 0.8 ? 1 : 0);
            }

            // Update canvas if enabled
            if (this.config.useCanvas && this.data.nodes.length > 100) {
                this.renderCanvas();
            }
        }

        /**
         * Render graph on canvas for performance
         */
        renderCanvas() {
            if (!this.ctx) return;

            const ctx = this.ctx;
            const { width, height } = this.config;

            // Clear canvas
            ctx.clearRect(0, 0, width, height);

            // Apply transform
            ctx.save();
            ctx.translate(this.transform.x, this.transform.y);
            ctx.scale(this.transform.k, this.transform.k);

            // Draw edges
            this.data.edges.forEach(edge => {
                ctx.beginPath();
                ctx.moveTo(edge.source.x, edge.source.y);
                ctx.lineTo(edge.target.x, edge.target.y);
                ctx.strokeStyle = this.getEdgeColor(edge);
                ctx.lineWidth = this.getEdgeWidth(edge);
                ctx.globalAlpha = 0.6;
                ctx.stroke();
            });

            // Draw nodes
            this.data.nodes.forEach(node => {
                ctx.beginPath();
                ctx.arc(node.x, node.y, this.getNodeRadius(node), 0, 2 * Math.PI);
                ctx.fillStyle = ENTITY_COLORS[node.type] || '#999';
                ctx.globalAlpha = 1;
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 2;
                ctx.stroke();
            });

            ctx.restore();
        }

        /**
         * Create curved edge path
         */
        curvedEdgePath(d) {
            const sx = d.source.x;
            const sy = d.source.y;
            const tx = d.target.x;
            const ty = d.target.y;

            // Calculate control point for curve
            const dx = tx - sx;
            const dy = ty - sy;
            const dr = Math.sqrt(dx * dx + dy * dy);

            // Curve strength based on strength property
            const curve = (d.strength || 50) / 100;

            return `M${sx},${sy}Q${sx + dx/2 + dy*curve/2},${sy + dy/2 - dx*curve/2} ${tx},${ty}`;
        }

        /**
         * Drag behavior with Shift for multi-select
         */
        drag() {
            return d3.drag()
                .on('start', (event, d) => this.dragStart(event, d))
                .on('drag', (event, d) => this.dragMove(event, d))
                .on('end', (event, d) => this.dragEnd(event, d));
        }

        dragStart(event, d) {
            if (this.worker) {
                this.worker.postMessage({
                    type: 'drag',
                    data: { nodeId: d.id, x: d.x, y: d.y, type: 'start' }
                });
            } else if (this.simulation && !event.active) {
                this.simulation.alphaTarget(0.3).restart();
            }

            d.fx = d.x;
            d.fy = d.y;
        }

        dragMove(event, d) {
            const [x, y] = d3.pointer(event, this.g.node());
            const transformed = this.transform.invert([x, y]);

            d.fx = transformed[0];
            d.fy = transformed[1];

            if (this.worker) {
                this.worker.postMessage({
                    type: 'drag',
                    data: { nodeId: d.id, x: d.fx, y: d.fy, type: 'drag' }
                });
            }
        }

        dragEnd(event, d) {
            if (this.worker) {
                this.worker.postMessage({
                    type: 'drag',
                    data: { nodeId: d.id, type: 'end' }
                });
            } else if (this.simulation && !event.active) {
                this.simulation.alphaTarget(0);
            }
        }

        /**
         * Handle node click with multi-select support
         */
        handleNodeClick(event, node) {
            event.stopPropagation();

            // Double-click to release fixed position
            if (event.detail === 2) {
                node.fx = null;
                node.fy = null;
                if (this.worker) {
                    this.worker.postMessage({
                        type: 'drag',
                        data: { nodeId: node.id, type: 'release' }
                    });
                } else if (this.simulation) {
                    this.simulation.alpha(0.3).restart();
                }
                return;
            }

            // Shift+click for multi-select
            if (event.shiftKey && this.config.enableMultiSelect) {
                this.toggleNodeSelection(node);
            } else {
                // Single select or navigate
                if (node.url) {
                    window.location.href = node.url;
                } else {
                    this.selectNode(node);
                }
            }
        }

        /**
         * Handle edge click
         */
        handleEdgeClick(event, edge) {
            event.stopPropagation();

            // Highlight relationship path
            const sourceId = typeof edge.source === 'object' ? edge.source.id : edge.source;
            const targetId = typeof edge.target === 'object' ? edge.target.id : edge.target;

            this.findAndHighlightPath(sourceId, targetId);
        }

        /**
         * Handle right-click context menu
         */
        handleNodeContextMenu(event, node) {
            event.preventDefault();
            this.showContextMenu(event, node);
        }

        /**
         * Show context menu
         */
        showContextMenu(event, node) {
            // Remove existing menu
            d3.select('.saga-graph-context-menu').remove();

            const menu = d3.select('body')
                .append('div')
                .attr('class', 'saga-graph-context-menu')
                .style('left', event.pageX + 'px')
                .style('top', event.pageY + 'px');

            const items = [
                { label: 'Expand Neighbors', action: () => this.expandNeighbors(node) },
                { label: 'Collapse', action: () => this.collapseNode(node) },
                { label: 'Focus', action: () => this.focusNode(node) },
                { label: 'Find Shortest Path...', action: () => this.promptShortestPath(node) },
                { label: 'View Details', action: () => this.viewNodeDetails(node) },
                { separator: true },
                { label: 'Pin Position', action: () => this.pinNode(node) },
                { label: 'Release Position', action: () => this.releaseNode(node) }
            ];

            items.forEach(item => {
                if (item.separator) {
                    menu.append('div').attr('class', 'separator');
                } else {
                    menu.append('div')
                        .attr('class', 'menu-item')
                        .text(item.label)
                        .on('click', () => {
                            item.action();
                            menu.remove();
                        });
                }
            });

            // Close menu on click outside
            d3.select('body').on('click.context-menu', () => {
                menu.remove();
                d3.select('body').on('click.context-menu', null);
            });
        }

        /**
         * Toggle node selection (multi-select)
         */
        toggleNodeSelection(node) {
            if (this.selectedNodes.has(node.id)) {
                this.selectedNodes.delete(node.id);
                this.nodes.filter(d => d.id === node.id)
                    .select('circle')
                    .attr('stroke', '#fff')
                    .attr('stroke-width', 2);
            } else {
                this.selectedNodes.add(node.id);
                this.nodes.filter(d => d.id === node.id)
                    .select('circle')
                    .attr('stroke', '#000')
                    .attr('stroke-width', 4);
            }

            this.announceSelection();
        }

        /**
         * Select single node
         */
        selectNode(node) {
            // Clear previous selection
            this.selectedNodes.clear();
            this.nodes.select('circle')
                .attr('stroke', '#fff')
                .attr('stroke-width', 2);

            // Select new node
            this.selectedNodes.add(node.id);
            this.nodes.filter(d => d.id === node.id)
                .select('circle')
                .attr('stroke', '#000')
                .attr('stroke-width', 4);

            this.highlightConnections(node);
        }

        /**
         * Highlight connected nodes and edges
         */
        highlightConnections(node) {
            const connectedNodeIds = new Set();

            this.data.edges.forEach(edge => {
                const sourceId = typeof edge.source === 'object' ? edge.source.id : edge.source;
                const targetId = typeof edge.target === 'object' ? edge.target.id : edge.target;

                if (sourceId === node.id || targetId === node.id) {
                    connectedNodeIds.add(sourceId);
                    connectedNodeIds.add(targetId);
                }
            });

            // Dim unconnected nodes
            this.nodes.attr('opacity', d => connectedNodeIds.has(d.id) ? 1 : 0.2);
            this.edges.attr('opacity', d => {
                const sourceId = typeof d.source === 'object' ? d.source.id : d.source;
                const targetId = typeof d.target === 'object' ? d.target.id : d.target;
                return (sourceId === node.id || targetId === node.id) ? 1 : 0.1;
            });
            this.labels.attr('opacity', d => connectedNodeIds.has(d.id) ? 1 : 0.1);
        }

        /**
         * Clear selection and reset opacity
         */
        clearSelection() {
            this.selectedNodes.clear();
            this.nodes.select('circle')
                .attr('stroke', '#fff')
                .attr('stroke-width', 2);
            this.nodes.attr('opacity', 1);
            this.edges.attr('opacity', 0.6);
            this.labels.attr('opacity', 1);
        }

        /**
         * Focus on node (zoom and center)
         */
        focusNode(node) {
            const scale = 2;
            const translate = [
                this.config.width / 2 - scale * node.x,
                this.config.height / 2 - scale * node.y
            ];

            this.svg.transition()
                .duration(this.config.animationDuration)
                .call(
                    this.zoom.transform,
                    d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
                );

            this.selectNode(node);
        }

        /**
         * Find and highlight shortest path
         */
        findAndHighlightPath(sourceId, targetId) {
            if (this.worker) {
                this.worker.postMessage({
                    type: 'shortest-path',
                    sourceId,
                    targetId
                });
            } else {
                // Calculate locally
                const path = this.calculateShortestPath(sourceId, targetId);
                this.highlightPath(path);
            }
        }

        /**
         * Calculate shortest path (BFS)
         */
        calculateShortestPath(sourceId, targetId) {
            const adjacency = new Map();
            this.data.nodes.forEach(n => adjacency.set(n.id, []));

            this.data.edges.forEach(edge => {
                const sid = typeof edge.source === 'object' ? edge.source.id : edge.source;
                const tid = typeof edge.target === 'object' ? edge.target.id : edge.target;
                adjacency.get(sid).push(tid);
                adjacency.get(tid).push(sid);
            });

            const queue = [[sourceId]];
            const visited = new Set([sourceId]);

            while (queue.length > 0) {
                const path = queue.shift();
                const current = path[path.length - 1];

                if (current === targetId) {
                    return path;
                }

                const neighbors = adjacency.get(current) || [];
                neighbors.forEach(neighborId => {
                    if (!visited.has(neighborId)) {
                        visited.add(neighborId);
                        queue.push([...path, neighborId]);
                    }
                });
            }

            return null;
        }

        /**
         * Highlight path on graph
         */
        highlightPath(path) {
            if (!path || path.length < 2) {
                this.showMessage('No path found');
                return;
            }

            this.highlightedPath = path;

            // Clear previous highlights
            this.highlightLayer.selectAll('*').remove();

            // Highlight path edges
            for (let i = 0; i < path.length - 1; i++) {
                const sourceId = path[i];
                const targetId = path[i + 1];

                const edge = this.data.edges.find(e => {
                    const sid = typeof e.source === 'object' ? e.source.id : e.source;
                    const tid = typeof e.target === 'object' ? e.target.id : e.target;
                    return (sid === sourceId && tid === targetId) || (sid === targetId && tid === sourceId);
                });

                if (edge) {
                    this.highlightLayer.append('path')
                        .datum(edge)
                        .attr('d', d => this.curvedEdgePath(d))
                        .attr('stroke', '#FF6B6B')
                        .attr('stroke-width', 4)
                        .attr('fill', 'none')
                        .attr('filter', 'url(#glow)');
                }
            }

            // Highlight path nodes
            const pathSet = new Set(path);
            this.nodes.attr('opacity', d => pathSet.has(d.id) ? 1 : 0.2);
            this.edges.attr('opacity', 0.1);

            this.announcePathFound(path);
        }

        /**
         * Calculate analytics
         */
        calculateAnalytics() {
            if (this.worker) {
                this.worker.postMessage({ type: 'calculate-centrality' });
                this.worker.postMessage({ type: 'find-communities' });
            } else {
                // Calculate locally (simplified)
                this.analytics = {
                    centrality: this.calculateCentrality(),
                    communities: this.findCommunities()
                };
            }
        }

        /**
         * Simple centrality calculation (degree centrality)
         */
        calculateCentrality() {
            const centrality = new Map();
            this.data.nodes.forEach(n => centrality.set(n.id, 0));

            this.data.edges.forEach(edge => {
                const sid = typeof edge.source === 'object' ? edge.source.id : edge.source;
                const tid = typeof edge.target === 'object' ? edge.target.id : edge.target;
                centrality.set(sid, centrality.get(sid) + 1);
                centrality.set(tid, centrality.get(tid) + 1);
            });

            return Object.fromEntries(centrality);
        }

        /**
         * Simple community detection
         */
        findCommunities() {
            // Group by type for now
            const communities = new Map();
            this.data.nodes.forEach(n => {
                communities.set(n.id, n.type);
            });
            return Object.fromEntries(communities);
        }

        /**
         * Display centrality results
         */
        displayCentralityResults(centrality) {
            this.analytics = { ...this.analytics, centrality };

            // Visualize on nodes
            const maxCentrality = Math.max(...Object.values(centrality));

            this.nodes.select('circle')
                .attr('stroke-width', d => {
                    const c = centrality[d.id] || 0;
                    return 2 + (c / maxCentrality) * 3;
                });
        }

        /**
         * Display communities
         */
        displayCommunities(communities) {
            this.analytics = { ...this.analytics, communities };
            // Could visualize with different colors or clusters
        }

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (event) => {
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
                    return;
                }

                const action = KEYBOARD_SHORTCUTS[event.key];
                if (!action) return;

                event.preventDefault();

                switch (action) {
                    case 'force':
                    case 'hierarchical':
                    case 'circular':
                    case 'radial':
                    case 'grid':
                    case 'clustered':
                        this.switchLayout(action);
                        break;

                    case 'save':
                        this.saveLayout();
                        break;

                    case 'clear':
                        this.clearSelection();
                        break;
                }
            });
        }

        /**
         * Setup context menu blocker
         */
        setupContextMenu() {
            this.svg.on('contextmenu', (event) => {
                // Allow context menu on nodes only
                if (event.target.tagName !== 'circle') {
                    event.preventDefault();
                }
            });
        }

        /**
         * Switch layout with animation
         */
        switchLayout(layoutName) {
            if (layoutName === this.currentLayout) return;

            // Save current positions
            const oldPositions = new Map(
                this.data.nodes.map(n => [n.id, { x: n.x, y: n.y }])
            );

            // Apply new layout
            this.currentLayout = layoutName;
            this.applyLayout(layoutName);

            // Animate transition
            this.nodes
                .transition()
                .duration(this.config.animationDuration)
                .attr('transform', d => `translate(${d.x},${d.y})`);

            this.tick();
        }

        /**
         * Utility methods
         */
        getNodeRadius(node) {
            return 10 + (node.importance / 10);
        }

        getEdgeWidth(edge) {
            return 1 + (edge.strength / 25);
        }

        getEdgeColor(edge) {
            const sourceType = this.getNodeType(edge.source);
            return ENTITY_COLORS[sourceType] || '#999';
        }

        getNodeType(node) {
            if (typeof node === 'object' && node.type) {
                return node.type;
            }
            const foundNode = this.data.nodes.find(n => n.id === node);
            return foundNode ? foundNode.type : 'concept';
        }

        truncateLabel(label, maxLength) {
            return label.length > maxLength ? label.substring(0, maxLength) + '...' : label;
        }

        showLoading() {
            const loading = document.createElement('div');
            loading.className = 'saga-graph-loading';
            loading.textContent = 'Loading graph...';
            this.container.appendChild(loading);
        }

        hideLoading() {
            const loading = this.container.querySelector('.saga-graph-loading');
            if (loading) loading.remove();
        }

        showError(message) {
            const error = document.createElement('div');
            error.className = 'saga-graph-error';
            error.textContent = message;
            this.container.appendChild(error);
        }

        showMessage(message) {
            const msg = document.createElement('div');
            msg.className = 'saga-graph-message';
            msg.textContent = message;
            this.container.appendChild(msg);
            setTimeout(() => msg.remove(), 3000);
        }

        showNodeTooltip(event, node) {
            // Similar to v1
        }

        showEdgeTooltip(event, edge) {
            // Similar to v1
        }

        hideTooltip() {
            // Similar to v1
        }

        handleNodeFocus(event, node) {
            // Similar to v1
        }

        handleNodeBlur() {
            // Similar to v1
        }

        announceGraphLoaded() {
            console.log(`Graph loaded: ${this.data.nodes.length} nodes, ${this.data.edges.length} edges`);
        }

        announceLayoutChange(layout) {
            console.log(`Layout changed to: ${layout}`);
        }

        announceSelection() {
            console.log(`Selected ${this.selectedNodes.size} nodes`);
        }

        announcePathFound(path) {
            console.log(`Path found with ${path.length} nodes`);
        }

        onSimulationEnd() {
            console.log('Simulation ended');
        }

        saveLayout() {
            const layout = this.data.nodes.map(n => ({
                id: n.id,
                x: n.x,
                y: n.y,
                fx: n.fx,
                fy: n.fy
            }));
            localStorage.setItem(`saga-graph-layout-v2-${this.config.entityId}`, JSON.stringify(layout));
            this.showMessage('Layout saved');
        }

        // Additional methods for expand/collapse, pin/release, etc.
        expandNeighbors(node) { console.log('Expand neighbors', node); }
        collapseNode(node) { console.log('Collapse node', node); }
        promptShortestPath(node) { console.log('Prompt shortest path', node); }
        viewNodeDetails(node) { if (node.url) window.open(node.url, '_blank'); }
        pinNode(node) { node.fx = node.x; node.fy = node.y; }
        releaseNode(node) { node.fx = null; node.fy = null; }

        /**
         * Destroy graph
         */
        destroy() {
            if (this.simulation) this.simulation.stop();
            if (this.worker) this.worker.terminate();
            if (this.svg) this.svg.remove();
            if (this.canvas) this.canvas.remove();
            this.saveLayout();
        }
    }

    // Export to window
    window.SagaRelationshipGraphV2 = RelationshipGraphV2;

})();
