/**
 * Graph Web Worker
 *
 * Offloads D3 force simulation to Web Worker for better performance
 * Handles 1000+ nodes without blocking main thread
 *
 * @package SagaManager
 * @since 1.3.0
 */

/* global self, importScripts */

// Import D3 force simulation (CDN version for worker)
try {
    importScripts('https://cdn.jsdelivr.net/npm/d3-force@3/dist/d3-force.min.js');
} catch (e) {
    console.error('Failed to load D3 in worker:', e);
}

let simulation = null;
let nodes = [];
let edges = [];

/**
 * Message handler
 */
self.addEventListener('message', function(e) {
    const { type, data } = e.data;

    switch (type) {
        case 'init':
            initSimulation(data);
            break;

        case 'tick':
            tickSimulation(data);
            break;

        case 'drag':
            handleDrag(data);
            break;

        case 'stop':
            stopSimulation();
            break;

        case 'update':
            updateSimulation(data);
            break;

        case 'reheat':
            reheatSimulation(data);
            break;

        default:
            console.warn('Unknown worker message type:', type);
    }
});

/**
 * Initialize force simulation
 */
function initSimulation(data) {
    const { nodes: nodeData, edges: edgeData, config } = data;

    nodes = nodeData;
    edges = edgeData;

    // Create simulation
    simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(edges)
            .id(d => d.id)
            .distance(config.linkDistance || 100)
            .strength(config.linkStrength || 0.7))
        .force('charge', d3.forceManyBody()
            .strength(config.chargeStrength || -300)
            .distanceMax(config.chargeDistanceMax || 500))
        .force('center', d3.forceCenter(config.width / 2, config.height / 2)
            .strength(config.centerStrength || 0.1))
        .force('collision', d3.forceCollide()
            .radius(config.collisionRadius || 30)
            .strength(config.collisionStrength || 0.7))
        .force('x', d3.forceX(config.width / 2).strength(0.05))
        .force('y', d3.forceY(config.height / 2).strength(0.05))
        .alphaDecay(config.alphaDecay || 0.0228)
        .velocityDecay(config.velocityDecay || 0.4)
        .on('tick', onTick)
        .on('end', onEnd);

    // Start simulation
    simulation.alpha(1).restart();
}

/**
 * Tick handler
 */
function onTick() {
    // Send positions back to main thread
    self.postMessage({
        type: 'tick',
        nodes: nodes.map(n => ({
            id: n.id,
            x: n.x,
            y: n.y,
            vx: n.vx,
            vy: n.vy
        })),
        alpha: simulation.alpha()
    });
}

/**
 * End handler
 */
function onEnd() {
    self.postMessage({
        type: 'end',
        nodes: nodes.map(n => ({
            id: n.id,
            x: n.x,
            y: n.y
        }))
    });
}

/**
 * Manual tick for controlled simulation
 */
function tickSimulation(data) {
    if (!simulation) return;

    const iterations = data.iterations || 1;

    for (let i = 0; i < iterations; i++) {
        simulation.tick();
    }

    onTick();
}

/**
 * Handle drag events
 */
function handleDrag(data) {
    const { nodeId, x, y, type: dragType } = data;

    const node = nodes.find(n => n.id === nodeId);
    if (!node) return;

    switch (dragType) {
        case 'start':
            node.fx = node.x;
            node.fy = node.y;
            if (simulation) simulation.alphaTarget(0.3).restart();
            break;

        case 'drag':
            node.fx = x;
            node.fy = y;
            break;

        case 'end':
            if (simulation) simulation.alphaTarget(0);
            // Keep fixed position (release with double-click in main thread)
            break;

        case 'release':
            node.fx = null;
            node.fy = null;
            if (simulation) simulation.alpha(0.3).restart();
            break;
    }

    onTick();
}

/**
 * Stop simulation
 */
function stopSimulation() {
    if (simulation) {
        simulation.stop();
    }
}

/**
 * Update simulation with new data
 */
function updateSimulation(data) {
    const { nodes: newNodes, edges: newEdges, config } = data;

    nodes = newNodes;
    edges = newEdges;

    if (simulation) {
        simulation.nodes(nodes);
        simulation.force('link').links(edges);

        // Update forces if config provided
        if (config) {
            if (config.linkDistance !== undefined) {
                simulation.force('link').distance(config.linkDistance);
            }
            if (config.chargeStrength !== undefined) {
                simulation.force('charge').strength(config.chargeStrength);
            }
            if (config.collisionRadius !== undefined) {
                simulation.force('collision').radius(config.collisionRadius);
            }
        }

        simulation.alpha(1).restart();
    }
}

/**
 * Reheat simulation (restart with new alpha)
 */
function reheatSimulation(data) {
    if (simulation) {
        const alpha = data.alpha || 0.3;
        simulation.alpha(alpha).restart();
    }
}

/**
 * Calculate graph analytics (betweenness centrality, clustering)
 */
self.addEventListener('message', function(e) {
    if (e.data.type === 'calculate-centrality') {
        const centrality = calculateBetweennessCentrality(nodes, edges);
        self.postMessage({
            type: 'centrality-result',
            centrality
        });
    }

    if (e.data.type === 'find-communities') {
        const communities = findCommunities(nodes, edges);
        self.postMessage({
            type: 'communities-result',
            communities
        });
    }

    if (e.data.type === 'shortest-path') {
        const { sourceId, targetId } = e.data;
        const path = findShortestPath(nodes, edges, sourceId, targetId);
        self.postMessage({
            type: 'shortest-path-result',
            path
        });
    }
});

/**
 * Calculate betweenness centrality for all nodes
 */
function calculateBetweennessCentrality(nodes, edges) {
    const centrality = new Map();
    nodes.forEach(n => centrality.set(n.id, 0));

    // Build adjacency list
    const adjacency = buildAdjacencyList(nodes, edges);

    // For each pair of nodes, find shortest paths
    nodes.forEach(source => {
        const paths = findAllShortestPaths(adjacency, source.id);

        paths.forEach((path, target) => {
            if (source.id === target) return;

            // Count nodes on shortest path (excluding endpoints)
            path.forEach(nodeId => {
                if (nodeId !== source.id && nodeId !== target) {
                    centrality.set(nodeId, centrality.get(nodeId) + 1);
                }
            });
        });
    });

    // Normalize
    const max = Math.max(...centrality.values());
    if (max > 0) {
        centrality.forEach((value, key) => {
            centrality.set(key, value / max);
        });
    }

    return Object.fromEntries(centrality);
}

/**
 * Find communities using simple label propagation
 */
function findCommunities(nodes, edges) {
    const communities = new Map();
    const adjacency = buildAdjacencyList(nodes, edges);

    // Initialize each node to its own community
    nodes.forEach(n => communities.set(n.id, n.id));

    // Label propagation (5 iterations)
    for (let iter = 0; iter < 5; iter++) {
        let changed = false;

        nodes.forEach(node => {
            const neighbors = adjacency.get(node.id) || [];
            if (neighbors.length === 0) return;

            // Count community labels of neighbors
            const labelCounts = new Map();
            neighbors.forEach(neighborId => {
                const label = communities.get(neighborId);
                labelCounts.set(label, (labelCounts.get(label) || 0) + 1);
            });

            // Adopt most common label
            const mostCommon = [...labelCounts.entries()]
                .reduce((max, curr) => curr[1] > max[1] ? curr : max);

            if (mostCommon[0] !== communities.get(node.id)) {
                communities.set(node.id, mostCommon[0]);
                changed = true;
            }
        });

        if (!changed) break;
    }

    return Object.fromEntries(communities);
}

/**
 * Find shortest path between two nodes (BFS)
 */
function findShortestPath(nodes, edges, sourceId, targetId) {
    const adjacency = buildAdjacencyList(nodes, edges);
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

    return null; // No path found
}

/**
 * Find all shortest paths from source using BFS
 */
function findAllShortestPaths(adjacency, sourceId) {
    const paths = new Map();
    const queue = [[sourceId]];
    const visited = new Set([sourceId]);

    paths.set(sourceId, [sourceId]);

    while (queue.length > 0) {
        const path = queue.shift();
        const current = path[path.length - 1];

        const neighbors = adjacency.get(current) || [];
        neighbors.forEach(neighborId => {
            if (!visited.has(neighborId)) {
                visited.add(neighborId);
                const newPath = [...path, neighborId];
                paths.set(neighborId, newPath);
                queue.push(newPath);
            }
        });
    }

    return paths;
}

/**
 * Build adjacency list from edges
 */
function buildAdjacencyList(nodes, edges) {
    const adjacency = new Map();
    nodes.forEach(n => adjacency.set(n.id, []));

    edges.forEach(edge => {
        const sourceId = typeof edge.source === 'object' ? edge.source.id : edge.source;
        const targetId = typeof edge.target === 'object' ? edge.target.id : edge.target;

        if (adjacency.has(sourceId)) {
            adjacency.get(sourceId).push(targetId);
        }
        if (adjacency.has(targetId)) {
            adjacency.get(targetId).push(sourceId); // Undirected
        }
    });

    return adjacency;
}
