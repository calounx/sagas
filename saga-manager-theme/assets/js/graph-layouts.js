/**
 * Graph Layout Algorithms
 *
 * Collection of layout algorithms for D3 v7 relationship graphs
 * Supports force, hierarchical, circular, radial, and grid layouts
 *
 * @package SagaManager
 * @since 1.3.0
 */

(function() {
    'use strict';

    /**
     * Layout algorithm collection
     */
    const GraphLayouts = {
        /**
         * Force-directed layout (D3 v7 simulation)
         */
        force: function(nodes, edges, width, height, options = {}) {
            const config = {
                linkDistance: options.linkDistance || 100,
                linkStrength: options.linkStrength || 0.7,
                chargeStrength: options.chargeStrength || -300,
                collisionRadius: options.collisionRadius || 30,
                centerStrength: options.centerStrength || 0.1,
                ...options
            };

            const simulation = d3.forceSimulation(nodes)
                .force('link', d3.forceLink(edges)
                    .id(d => d.id)
                    .distance(config.linkDistance)
                    .strength(config.linkStrength))
                .force('charge', d3.forceManyBody()
                    .strength(config.chargeStrength))
                .force('center', d3.forceCenter(width / 2, height / 2)
                    .strength(config.centerStrength))
                .force('collision', d3.forceCollide()
                    .radius(config.collisionRadius))
                .force('x', d3.forceX(width / 2).strength(0.05))
                .force('y', d3.forceY(height / 2).strength(0.05));

            return simulation;
        },

        /**
         * Hierarchical tree layout (top-down)
         */
        hierarchical: function(nodes, edges, width, height, options = {}) {
            const config = {
                orientation: options.orientation || 'vertical', // vertical, horizontal
                nodeSpacing: options.nodeSpacing || 100,
                levelSpacing: options.levelSpacing || 150,
                rootId: options.rootId || null,
                ...options
            };

            // Build hierarchy from flat data
            const hierarchy = this._buildHierarchy(nodes, edges, config.rootId);

            // Create tree layout
            const treeLayout = d3.tree()
                .size(config.orientation === 'vertical'
                    ? [width - 100, height - 100]
                    : [height - 100, width - 100]);

            // Apply layout
            const root = d3.hierarchy(hierarchy);
            treeLayout(root);

            // Map positions back to nodes
            const nodeMap = new Map(nodes.map(n => [n.id, n]));

            root.descendants().forEach(d => {
                const node = nodeMap.get(d.data.id);
                if (node) {
                    if (config.orientation === 'vertical') {
                        node.x = d.x + 50;
                        node.y = d.y + 50;
                    } else {
                        node.x = d.y + 50;
                        node.y = d.x + 50;
                    }
                    node.fx = node.x;
                    node.fy = node.y;
                }
            });

            return null; // No simulation needed (fixed positions)
        },

        /**
         * Circular layout
         */
        circular: function(nodes, edges, width, height, options = {}) {
            const config = {
                radius: options.radius || Math.min(width, height) * 0.4,
                startAngle: options.startAngle || 0,
                sortBy: options.sortBy || 'importance', // importance, type, name
                ...options
            };

            // Sort nodes
            const sortedNodes = this._sortNodes(nodes, config.sortBy);

            const centerX = width / 2;
            const centerY = height / 2;
            const angleStep = (2 * Math.PI) / sortedNodes.length;

            sortedNodes.forEach((node, i) => {
                const angle = config.startAngle + (i * angleStep);
                node.x = centerX + config.radius * Math.cos(angle);
                node.y = centerY + config.radius * Math.sin(angle);
                node.fx = node.x;
                node.fy = node.y;
            });

            return null; // No simulation needed
        },

        /**
         * Radial layout (concentric circles by distance from root)
         */
        radial: function(nodes, edges, width, height, options = {}) {
            const config = {
                rootId: options.rootId || nodes[0]?.id,
                radiusStep: options.radiusStep || 100,
                ...options
            };

            // Calculate distances from root using BFS
            const distances = this._calculateDistances(nodes, edges, config.rootId);

            // Group nodes by distance
            const levels = new Map();
            nodes.forEach(node => {
                const distance = distances.get(node.id) || 0;
                if (!levels.has(distance)) {
                    levels.set(distance, []);
                }
                levels.get(distance).push(node);
            });

            const centerX = width / 2;
            const centerY = height / 2;

            // Position nodes in concentric circles
            levels.forEach((levelNodes, distance) => {
                const radius = distance * config.radiusStep;
                const angleStep = (2 * Math.PI) / levelNodes.length;

                levelNodes.forEach((node, i) => {
                    const angle = i * angleStep;
                    node.x = centerX + radius * Math.cos(angle);
                    node.y = centerY + radius * Math.sin(angle);
                    node.fx = node.x;
                    node.fy = node.y;
                });
            });

            return null;
        },

        /**
         * Grid layout
         */
        grid: function(nodes, edges, width, height, options = {}) {
            const config = {
                columns: options.columns || Math.ceil(Math.sqrt(nodes.length)),
                cellPadding: options.cellPadding || 20,
                sortBy: options.sortBy || 'type',
                ...options
            };

            const sortedNodes = this._sortNodes(nodes, config.sortBy);
            const cols = config.columns;
            const rows = Math.ceil(sortedNodes.length / cols);

            const cellWidth = (width - 100) / cols;
            const cellHeight = (height - 100) / rows;

            sortedNodes.forEach((node, i) => {
                const col = i % cols;
                const row = Math.floor(i / cols);

                node.x = 50 + col * cellWidth + cellWidth / 2;
                node.y = 50 + row * cellHeight + cellHeight / 2;
                node.fx = node.x;
                node.fy = node.y;
            });

            return null;
        },

        /**
         * Clustered force layout (group by entity type)
         */
        clustered: function(nodes, edges, width, height, options = {}) {
            const config = {
                clusterBy: options.clusterBy || 'type',
                clusterStrength: options.clusterStrength || 0.5,
                linkDistance: options.linkDistance || 100,
                chargeStrength: options.chargeStrength || -200,
                ...options
            };

            // Calculate cluster centers
            const clusters = this._calculateClusterCenters(nodes, width, height, config.clusterBy);

            const simulation = d3.forceSimulation(nodes)
                .force('link', d3.forceLink(edges)
                    .id(d => d.id)
                    .distance(config.linkDistance))
                .force('charge', d3.forceManyBody()
                    .strength(config.chargeStrength))
                .force('cluster', this._forceCluster(clusters, config.clusterBy, config.clusterStrength))
                .force('collision', d3.forceCollide().radius(30));

            return simulation;
        },

        /**
         * Force cluster (custom force for clustering)
         */
        _forceCluster: function(clusters, clusterBy, strength) {
            return function(alpha) {
                this.forEach(node => {
                    const cluster = clusters.get(node[clusterBy]);
                    if (cluster) {
                        node.vx -= (node.x - cluster.x) * alpha * strength;
                        node.vy -= (node.y - cluster.y) * alpha * strength;
                    }
                });
            };
        },

        /**
         * Calculate cluster centers based on entity type or other property
         */
        _calculateClusterCenters: function(nodes, width, height, clusterBy) {
            const clusters = new Map();
            const types = [...new Set(nodes.map(n => n[clusterBy]))];

            const radius = Math.min(width, height) * 0.3;
            const centerX = width / 2;
            const centerY = height / 2;
            const angleStep = (2 * Math.PI) / types.length;

            types.forEach((type, i) => {
                const angle = i * angleStep;
                clusters.set(type, {
                    x: centerX + radius * Math.cos(angle),
                    y: centerY + radius * Math.sin(angle)
                });
            });

            return clusters;
        },

        /**
         * Build hierarchy from flat node/edge data
         */
        _buildHierarchy: function(nodes, edges, rootId) {
            // Find root node
            let root;
            if (rootId) {
                root = nodes.find(n => n.id === rootId);
            } else {
                // Find node with highest importance or no incoming edges
                const incomingCounts = new Map();
                edges.forEach(e => {
                    const targetId = typeof e.target === 'object' ? e.target.id : e.target;
                    incomingCounts.set(targetId, (incomingCounts.get(targetId) || 0) + 1);
                });

                root = nodes.reduce((max, node) => {
                    const incoming = incomingCounts.get(node.id) || 0;
                    const maxIncoming = incomingCounts.get(max?.id) || 0;
                    return incoming < maxIncoming || (incoming === maxIncoming && node.importance > (max?.importance || 0))
                        ? node : max;
                }, null);
            }

            if (!root) {
                root = nodes[0];
            }

            // Build tree recursively
            const visited = new Set();
            const buildTree = (nodeId) => {
                if (visited.has(nodeId)) return null;
                visited.add(nodeId);

                const node = nodes.find(n => n.id === nodeId);
                if (!node) return null;

                const children = edges
                    .filter(e => {
                        const sourceId = typeof e.source === 'object' ? e.source.id : e.source;
                        return sourceId === nodeId;
                    })
                    .map(e => {
                        const targetId = typeof e.target === 'object' ? e.target.id : e.target;
                        return buildTree(targetId);
                    })
                    .filter(c => c !== null);

                return {
                    ...node,
                    children: children.length > 0 ? children : undefined
                };
            };

            return buildTree(root.id);
        },

        /**
         * Calculate distances from root node using BFS
         */
        _calculateDistances: function(nodes, edges, rootId) {
            const distances = new Map();
            const queue = [{ id: rootId, distance: 0 }];
            const visited = new Set();

            // Build adjacency list
            const adjacency = new Map();
            edges.forEach(edge => {
                const sourceId = typeof edge.source === 'object' ? edge.source.id : edge.source;
                const targetId = typeof edge.target === 'object' ? edge.target.id : edge.target;

                if (!adjacency.has(sourceId)) adjacency.set(sourceId, []);
                if (!adjacency.has(targetId)) adjacency.set(targetId, []);

                adjacency.get(sourceId).push(targetId);
                adjacency.get(targetId).push(sourceId); // Undirected
            });

            // BFS
            while (queue.length > 0) {
                const { id, distance } = queue.shift();
                if (visited.has(id)) continue;

                visited.add(id);
                distances.set(id, distance);

                const neighbors = adjacency.get(id) || [];
                neighbors.forEach(neighborId => {
                    if (!visited.has(neighborId)) {
                        queue.push({ id: neighborId, distance: distance + 1 });
                    }
                });
            }

            return distances;
        },

        /**
         * Sort nodes by various criteria
         */
        _sortNodes: function(nodes, sortBy) {
            const sorted = [...nodes];

            switch (sortBy) {
                case 'importance':
                    sorted.sort((a, b) => (b.importance || 0) - (a.importance || 0));
                    break;
                case 'type':
                    sorted.sort((a, b) => (a.type || '').localeCompare(b.type || ''));
                    break;
                case 'name':
                    sorted.sort((a, b) => (a.label || '').localeCompare(b.label || ''));
                    break;
                default:
                    // No sorting
                    break;
            }

            return sorted;
        }
    };

    // Export to window
    window.SagaGraphLayouts = GraphLayouts;

})();
