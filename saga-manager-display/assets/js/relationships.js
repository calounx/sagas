/**
 * Saga Manager Display - Relationships Component
 *
 * Handles interactive relationship graph visualization
 *
 * @package SagaManagerDisplay
 */

(function() {
    'use strict';

    const { api, loading, errors, utils, config } = window.SagaDisplay;

    /**
     * Simple force-directed graph layout
     */
    class ForceGraph {
        constructor(nodes, edges, options = {}) {
            this.nodes = nodes.map((node, i) => ({
                ...node,
                x: options.width / 2 + (Math.random() - 0.5) * 100,
                y: options.height / 2 + (Math.random() - 0.5) * 100,
                vx: 0,
                vy: 0
            }));

            this.edges = edges.map(edge => ({
                ...edge,
                sourceNode: this.nodes.find(n => n.id == edge.source),
                targetNode: this.nodes.find(n => n.id == edge.target)
            }));

            this.options = {
                width: options.width || 600,
                height: options.height || 400,
                centerForce: 0.01,
                repulsionForce: 500,
                linkForce: 0.1,
                linkDistance: 100,
                damping: 0.9,
                iterations: 100
            };
        }

        /**
         * Run simulation
         */
        simulate() {
            for (let i = 0; i < this.options.iterations; i++) {
                this.tick();
            }
            return this;
        }

        /**
         * Single simulation tick
         */
        tick() {
            const { width, height, centerForce, repulsionForce, linkForce, linkDistance, damping } = this.options;
            const centerX = width / 2;
            const centerY = height / 2;

            // Reset forces
            this.nodes.forEach(node => {
                node.fx = 0;
                node.fy = 0;
            });

            // Center force
            this.nodes.forEach(node => {
                node.fx += (centerX - node.x) * centerForce;
                node.fy += (centerY - node.y) * centerForce;
            });

            // Repulsion between nodes
            for (let i = 0; i < this.nodes.length; i++) {
                for (let j = i + 1; j < this.nodes.length; j++) {
                    const nodeA = this.nodes[i];
                    const nodeB = this.nodes[j];

                    const dx = nodeB.x - nodeA.x;
                    const dy = nodeB.y - nodeA.y;
                    const dist = Math.sqrt(dx * dx + dy * dy) || 1;

                    const force = repulsionForce / (dist * dist);
                    const fx = (dx / dist) * force;
                    const fy = (dy / dist) * force;

                    nodeA.fx -= fx;
                    nodeA.fy -= fy;
                    nodeB.fx += fx;
                    nodeB.fy += fy;
                }
            }

            // Link forces
            this.edges.forEach(edge => {
                if (!edge.sourceNode || !edge.targetNode) return;

                const dx = edge.targetNode.x - edge.sourceNode.x;
                const dy = edge.targetNode.y - edge.sourceNode.y;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;

                const force = (dist - linkDistance) * linkForce;
                const fx = (dx / dist) * force;
                const fy = (dy / dist) * force;

                edge.sourceNode.fx += fx;
                edge.sourceNode.fy += fy;
                edge.targetNode.fx -= fx;
                edge.targetNode.fy -= fy;
            });

            // Apply forces
            this.nodes.forEach(node => {
                node.vx = (node.vx + node.fx) * damping;
                node.vy = (node.vy + node.fy) * damping;

                node.x += node.vx;
                node.y += node.vy;

                // Boundary constraints
                const padding = 50;
                node.x = Math.max(padding, Math.min(width - padding, node.x));
                node.y = Math.max(padding, Math.min(height - padding, node.y));
            });
        }

        /**
         * Get positioned nodes
         */
        getNodes() {
            return this.nodes;
        }

        /**
         * Get edges with positions
         */
        getEdges() {
            return this.edges;
        }
    }

    /**
     * Relationships component class
     */
    class SagaRelationships {
        constructor(container) {
            this.container = container;
            this.config = this.parseConfig();
            this.state = {
                nodes: [],
                edges: [],
                selected: null,
                zoom: 1,
                pan: { x: 0, y: 0 }
            };

            this.elements = this.cacheElements();
            this.init();
        }

        /**
         * Parse configuration from data attributes
         */
        parseConfig() {
            let nodes = [];
            let edges = [];

            try {
                nodes = JSON.parse(this.container.dataset.nodes || '[]');
                edges = JSON.parse(this.container.dataset.edges || '[]');
            } catch (e) {
                console.error('Failed to parse graph data:', e);
            }

            return {
                entityId: this.container.dataset.entity || '',
                nodes,
                edges,
                height: parseInt(this.container.dataset.height, 10) || 400
            };
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            return {
                canvas: this.container.querySelector('.saga-relationships__canvas'),
                controls: this.container.querySelector('.saga-relationships__controls'),
                tooltip: null
            };
        }

        /**
         * Initialize component
         */
        init() {
            if (!this.elements.canvas) {
                this.createCanvas();
            }

            this.state.nodes = this.config.nodes;
            this.state.edges = this.config.edges;

            this.runLayout();
            this.render();
            this.bindEvents();
            this.createControls();
        }

        /**
         * Create SVG canvas
         */
        createCanvas() {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.classList.add('saga-relationships__canvas');
            svg.setAttribute('width', '100%');
            svg.setAttribute('height', this.config.height);

            this.container.appendChild(svg);
            this.elements.canvas = svg;
        }

        /**
         * Run force-directed layout
         */
        runLayout() {
            const rect = this.container.getBoundingClientRect();
            const width = rect.width || 600;
            const height = this.config.height;

            const graph = new ForceGraph(this.state.nodes, this.state.edges, {
                width,
                height
            });

            graph.simulate();

            this.state.nodes = graph.getNodes();
            this.state.edges = graph.getEdges();
        }

        /**
         * Render graph
         */
        render() {
            const svg = this.elements.canvas;
            svg.innerHTML = '';

            // Add defs for markers
            const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            defs.innerHTML = `
                <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="var(--saga-color-border)" />
                </marker>
            `;
            svg.appendChild(defs);

            // Create group for zoom/pan
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.classList.add('saga-relationships__graph');

            // Render edges
            this.state.edges.forEach(edge => {
                if (!edge.sourceNode || !edge.targetNode) return;

                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', edge.sourceNode.x);
                line.setAttribute('y1', edge.sourceNode.y);
                line.setAttribute('x2', edge.targetNode.x);
                line.setAttribute('y2', edge.targetNode.y);
                line.setAttribute('stroke', 'var(--saga-color-border)');
                line.setAttribute('stroke-width', Math.max(1, (edge.strength || 50) / 25));
                line.setAttribute('marker-end', 'url(#arrowhead)');
                line.classList.add('saga-relationships__edge');
                line.dataset.type = edge.type || '';

                g.appendChild(line);

                // Edge label
                if (edge.label) {
                    const midX = (edge.sourceNode.x + edge.targetNode.x) / 2;
                    const midY = (edge.sourceNode.y + edge.targetNode.y) / 2;

                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', midX);
                    text.setAttribute('y', midY - 5);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('font-size', '10');
                    text.setAttribute('fill', 'var(--saga-color-text-muted)');
                    text.textContent = edge.label;

                    g.appendChild(text);
                }
            });

            // Render nodes
            this.state.nodes.forEach(node => {
                const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                group.classList.add('saga-relationships__node');
                group.dataset.nodeId = node.id;
                group.setAttribute('transform', `translate(${node.x}, ${node.y})`);

                // Circle
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('r', node.id == this.config.entityId ? 25 : 20);
                circle.setAttribute('fill', this.getNodeColor(node));
                circle.setAttribute('stroke', 'var(--saga-color-bg)');
                circle.setAttribute('stroke-width', '2');

                group.appendChild(circle);

                // Label
                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('y', 35);
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('font-size', '12');
                text.setAttribute('fill', 'var(--saga-color-text)');
                text.textContent = this.truncate(node.canonical_name || node.name || '', 15);

                group.appendChild(text);

                g.appendChild(group);
            });

            svg.appendChild(g);
        }

        /**
         * Get node color based on entity type
         */
        getNodeColor(node) {
            const colors = {
                character: '#3b82f6',
                location: '#22c55e',
                event: '#f59e0b',
                faction: '#8b5cf6',
                artifact: '#ec4899',
                concept: '#06b6d4'
            };
            return colors[node.entity_type] || '#64748b';
        }

        /**
         * Truncate text
         */
        truncate(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            const svg = this.elements.canvas;

            // Node click
            svg.addEventListener('click', (e) => {
                const node = e.target.closest('.saga-relationships__node');
                if (node) {
                    this.selectNode(node.dataset.nodeId);
                } else {
                    this.deselectNode();
                }
            });

            // Node hover
            svg.addEventListener('mouseover', (e) => {
                const node = e.target.closest('.saga-relationships__node');
                if (node) {
                    this.showTooltip(node);
                }
            });

            svg.addEventListener('mouseout', (e) => {
                const node = e.target.closest('.saga-relationships__node');
                if (node) {
                    this.hideTooltip();
                }
            });

            // Zoom
            svg.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY > 0 ? 0.9 : 1.1;
                this.zoom(delta);
            });
        }

        /**
         * Create zoom/pan controls
         */
        createControls() {
            if (this.elements.controls) return;

            const controls = document.createElement('div');
            controls.className = 'saga-relationships__controls';
            controls.innerHTML = `
                <button type="button" class="saga-relationships__control-btn" data-action="zoom-in" aria-label="Zoom in">+</button>
                <button type="button" class="saga-relationships__control-btn" data-action="zoom-out" aria-label="Zoom out">-</button>
                <button type="button" class="saga-relationships__control-btn" data-action="reset" aria-label="Reset view">&#8635;</button>
            `;

            this.container.appendChild(controls);
            this.elements.controls = controls;

            controls.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                switch (btn.dataset.action) {
                    case 'zoom-in':
                        this.zoom(1.2);
                        break;
                    case 'zoom-out':
                        this.zoom(0.8);
                        break;
                    case 'reset':
                        this.resetView();
                        break;
                }
            });
        }

        /**
         * Zoom graph
         */
        zoom(factor) {
            this.state.zoom *= factor;
            this.state.zoom = Math.max(0.5, Math.min(2, this.state.zoom));

            const g = this.elements.canvas.querySelector('.saga-relationships__graph');
            if (g) {
                g.setAttribute('transform', `scale(${this.state.zoom}) translate(${this.state.pan.x}, ${this.state.pan.y})`);
            }
        }

        /**
         * Reset view
         */
        resetView() {
            this.state.zoom = 1;
            this.state.pan = { x: 0, y: 0 };

            const g = this.elements.canvas.querySelector('.saga-relationships__graph');
            if (g) {
                g.setAttribute('transform', '');
            }
        }

        /**
         * Select node
         */
        selectNode(nodeId) {
            this.state.selected = nodeId;

            // Highlight selected node and connected edges
            this.elements.canvas.querySelectorAll('.saga-relationships__node').forEach(node => {
                node.classList.toggle('saga-relationships__node--selected', node.dataset.nodeId == nodeId);
            });

            // Dispatch event
            this.container.dispatchEvent(new CustomEvent('sagaNodeSelect', {
                bubbles: true,
                detail: { nodeId }
            }));
        }

        /**
         * Deselect node
         */
        deselectNode() {
            this.state.selected = null;

            this.elements.canvas.querySelectorAll('.saga-relationships__node--selected').forEach(node => {
                node.classList.remove('saga-relationships__node--selected');
            });
        }

        /**
         * Show tooltip
         */
        showTooltip(nodeElement) {
            const nodeId = nodeElement.dataset.nodeId;
            const node = this.state.nodes.find(n => n.id == nodeId);
            if (!node) return;

            if (!this.elements.tooltip) {
                this.elements.tooltip = document.createElement('div');
                this.elements.tooltip.className = 'saga-relationships__tooltip';
                document.body.appendChild(this.elements.tooltip);
            }

            this.elements.tooltip.innerHTML = `
                <strong>${utils.escapeHtml(node.canonical_name || node.name || '')}</strong>
                <span class="saga-badge saga-badge--${node.entity_type}">${node.entity_type}</span>
            `;

            const rect = nodeElement.getBoundingClientRect();
            this.elements.tooltip.style.left = `${rect.left + rect.width / 2}px`;
            this.elements.tooltip.style.top = `${rect.top - 10}px`;
            this.elements.tooltip.style.display = 'block';
        }

        /**
         * Hide tooltip
         */
        hideTooltip() {
            if (this.elements.tooltip) {
                this.elements.tooltip.style.display = 'none';
            }
        }

        /**
         * Destroy component
         */
        destroy() {
            if (this.elements.tooltip) {
                this.elements.tooltip.remove();
            }
        }
    }

    /**
     * Initialize all relationship components
     */
    function init() {
        const containers = document.querySelectorAll('.saga-relationships--interactive.saga-relationships--graph');

        containers.forEach(container => {
            if (!container.sagaRelationships) {
                container.sagaRelationships = new SagaRelationships(container);
            }
        });
    }

    // Export
    window.SagaDisplay.Relationships = SagaRelationships;
    window.SagaDisplay.ForceGraph = ForceGraph;

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on dynamic content
    document.addEventListener('sagaDisplayReady', init);
})();
