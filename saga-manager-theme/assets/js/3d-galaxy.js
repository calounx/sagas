/**
 * 3D Semantic Galaxy Visualization
 *
 * Interactive Three.js-based 3D force-directed graph for visualizing
 * entity relationships in saga universes.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

(function($) {
    'use strict';

    class SemanticGalaxy {
        constructor(container, options = {}) {
            this.container = container;
            this.options = {
                sagaId: options.sagaId || 1,
                width: options.width || container.clientWidth,
                height: options.height || container.clientHeight || 600,
                backgroundColor: options.backgroundColor || 0x0a0a0a,
                nodeMinSize: options.nodeMinSize || 2,
                nodeMaxSize: options.nodeMaxSize || 15,
                linkOpacity: options.linkOpacity || 0.4,
                particleCount: options.particleCount || 1000,
                forceStrength: options.forceStrength || 0.02,
                ...options
            };

            // Three.js core objects
            this.scene = null;
            this.camera = null;
            this.renderer = null;
            this.controls = null;

            // Graph data
            this.nodes = [];
            this.links = [];
            this.nodeObjects = new Map();
            this.linkObjects = [];

            // Raycasting for interaction
            this.raycaster = new THREE.Raycaster();
            this.mouse = new THREE.Vector2();
            this.hoveredNode = null;
            this.selectedNode = null;

            // Animation
            this.animationId = null;
            this.clock = new THREE.Clock();

            // Performance tracking
            this.stats = {
                fps: 0,
                renderTime: 0,
                nodeCount: 0,
                linkCount: 0
            };

            this.init();
        }

        /**
         * Initialize the 3D scene
         */
        init() {
            this.setupScene();
            this.setupCamera();
            this.setupRenderer();
            this.setupLights();
            this.setupControls();
            this.createStarfield();
            this.setupEventListeners();

            // Load data and start rendering
            this.loadData().then(() => {
                this.createGraph();
                this.animate();
            });
        }

        /**
         * Setup Three.js scene
         */
        setupScene() {
            this.scene = new THREE.Scene();
            this.scene.fog = new THREE.FogExp2(this.options.backgroundColor, 0.001);
        }

        /**
         * Setup camera
         */
        setupCamera() {
            const aspect = this.options.width / this.options.height;
            this.camera = new THREE.PerspectiveCamera(60, aspect, 0.1, 2000);
            this.camera.position.set(0, 0, 200);
        }

        /**
         * Setup WebGL renderer
         */
        setupRenderer() {
            this.renderer = new THREE.WebGLRenderer({
                antialias: true,
                alpha: true,
                powerPreference: 'high-performance'
            });

            this.renderer.setSize(this.options.width, this.options.height);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            this.renderer.setClearColor(this.options.backgroundColor, 1);

            this.container.appendChild(this.renderer.domElement);
        }

        /**
         * Setup lighting
         */
        setupLights() {
            // Ambient light for overall illumination
            const ambientLight = new THREE.AmbientLight(0x404040, 1.5);
            this.scene.add(ambientLight);

            // Point lights for depth
            const pointLight1 = new THREE.PointLight(0x4488ff, 1, 500);
            pointLight1.position.set(100, 100, 100);
            this.scene.add(pointLight1);

            const pointLight2 = new THREE.PointLight(0xff4488, 0.8, 500);
            pointLight2.position.set(-100, -100, -100);
            this.scene.add(pointLight2);
        }

        /**
         * Setup orbit controls
         */
        setupControls() {
            if (typeof THREE.OrbitControls === 'undefined') {
                console.error('OrbitControls not loaded');
                return;
            }

            this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.05;
            this.controls.rotateSpeed = 0.5;
            this.controls.zoomSpeed = 1.2;
            this.controls.panSpeed = 0.8;
            this.controls.minDistance = 50;
            this.controls.maxDistance = 500;
            this.controls.autoRotate = false;
            this.controls.autoRotateSpeed = 0.5;
        }

        /**
         * Create starfield background
         */
        createStarfield() {
            const geometry = new THREE.BufferGeometry();
            const positions = new Float32Array(this.options.particleCount * 3);
            const colors = new Float32Array(this.options.particleCount * 3);

            for (let i = 0; i < this.options.particleCount; i++) {
                const i3 = i * 3;

                // Random position in sphere
                const radius = 400 + Math.random() * 400;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);

                positions[i3] = radius * Math.sin(phi) * Math.cos(theta);
                positions[i3 + 1] = radius * Math.sin(phi) * Math.sin(theta);
                positions[i3 + 2] = radius * Math.cos(phi);

                // Varied star colors (white, blue, yellow)
                const colorChoice = Math.random();
                if (colorChoice < 0.7) {
                    colors[i3] = colors[i3 + 1] = colors[i3 + 2] = 1; // White
                } else if (colorChoice < 0.85) {
                    colors[i3] = 0.6; colors[i3 + 1] = 0.8; colors[i3 + 2] = 1; // Blue
                } else {
                    colors[i3] = 1; colors[i3 + 1] = 0.9; colors[i3 + 2] = 0.6; // Yellow
                }
            }

            geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

            const material = new THREE.PointsMaterial({
                size: 1.5,
                vertexColors: true,
                transparent: true,
                opacity: 0.8,
                sizeAttenuation: true
            });

            const starfield = new THREE.Points(geometry, material);
            this.scene.add(starfield);
        }

        /**
         * Load entity and relationship data via AJAX
         */
        async loadData() {
            try {
                const response = await fetch(sagaGalaxy.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'saga_galaxy_data',
                        saga_id: this.options.sagaId,
                        nonce: sagaGalaxy.nonce
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to load galaxy data');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data || 'Unknown error');
                }

                this.nodes = data.data.nodes || [];
                this.links = data.data.links || [];

                this.stats.nodeCount = this.nodes.length;
                this.stats.linkCount = this.links.length;

                this.triggerEvent('dataLoaded', { nodes: this.nodes, links: this.links });

            } catch (error) {
                console.error('Error loading galaxy data:', error);
                this.triggerEvent('dataError', { error });
            }
        }

        /**
         * Create 3D graph from loaded data
         */
        createGraph() {
            if (this.nodes.length === 0) {
                console.warn('No nodes to display');
                return;
            }

            // Initialize node positions with force-directed layout
            this.initializeNodePositions();

            // Create node objects
            this.createNodes();

            // Create link objects
            this.createLinks();

            // Run force simulation
            this.runForceSimulation(100); // 100 iterations

            this.triggerEvent('graphCreated', { nodes: this.nodes, links: this.links });
        }

        /**
         * Initialize random node positions
         */
        initializeNodePositions() {
            this.nodes.forEach(node => {
                node.x = (Math.random() - 0.5) * 200;
                node.y = (Math.random() - 0.5) * 200;
                node.z = (Math.random() - 0.5) * 200;
                node.vx = 0;
                node.vy = 0;
                node.vz = 0;
            });
        }

        /**
         * Create 3D node objects
         */
        createNodes() {
            const entityTypeColors = {
                character: 0x4488ff,
                location: 0x44ff88,
                event: 0xff8844,
                faction: 0xff4488,
                artifact: 0xffaa44,
                concept: 0x8844ff
            };

            this.nodes.forEach(node => {
                const importance = node.importance || 50;
                const size = this.options.nodeMinSize +
                           (importance / 100) * (this.options.nodeMaxSize - this.options.nodeMinSize);

                const geometry = new THREE.SphereGeometry(size, 16, 16);
                const color = entityTypeColors[node.type] || 0xffffff;
                const material = new THREE.MeshPhongMaterial({
                    color: color,
                    emissive: color,
                    emissiveIntensity: 0.2,
                    shininess: 30,
                    transparent: true,
                    opacity: 0.9
                });

                const sphere = new THREE.Mesh(geometry, material);
                sphere.position.set(node.x, node.y, node.z);
                sphere.userData = { node };

                this.scene.add(sphere);
                this.nodeObjects.set(node.id, sphere);

                // Create label (sprite with text texture)
                if (node.name) {
                    const label = this.createLabel(node.name, size);
                    label.position.copy(sphere.position);
                    label.position.y += size + 5;
                    this.scene.add(label);
                    node.label = label;
                }
            });
        }

        /**
         * Create text label sprite
         */
        createLabel(text, nodeSize) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = 256;
            canvas.height = 64;

            context.fillStyle = 'rgba(255, 255, 255, 0.95)';
            context.font = 'bold 24px Arial';
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(text, 128, 32);

            const texture = new THREE.CanvasTexture(canvas);
            const material = new THREE.SpriteMaterial({
                map: texture,
                transparent: true,
                opacity: 0.8,
                depthTest: false,
                depthWrite: false
            });

            const sprite = new THREE.Sprite(material);
            sprite.scale.set(20, 5, 1);

            return sprite;
        }

        /**
         * Create 3D link objects
         */
        createLinks() {
            this.links.forEach(link => {
                const sourceNode = this.nodes.find(n => n.id === link.source);
                const targetNode = this.nodes.find(n => n.id === link.target);

                if (!sourceNode || !targetNode) return;

                const points = [
                    new THREE.Vector3(sourceNode.x, sourceNode.y, sourceNode.z),
                    new THREE.Vector3(targetNode.x, targetNode.y, targetNode.z)
                ];

                const geometry = new THREE.BufferGeometry().setFromPoints(points);

                const strength = link.strength || 50;
                const opacity = this.options.linkOpacity * (strength / 100);

                const material = new THREE.LineBasicMaterial({
                    color: 0x4488ff,
                    transparent: true,
                    opacity: opacity,
                    linewidth: 1
                });

                const line = new THREE.Line(geometry, material);
                line.userData = { link, sourceNode, targetNode };

                this.scene.add(line);
                this.linkObjects.push(line);
            });
        }

        /**
         * Run force-directed simulation
         */
        runForceSimulation(iterations = 100) {
            const alpha = this.options.forceStrength;

            for (let iter = 0; iter < iterations; iter++) {
                // Repulsion between all nodes
                for (let i = 0; i < this.nodes.length; i++) {
                    for (let j = i + 1; j < this.nodes.length; j++) {
                        const node1 = this.nodes[i];
                        const node2 = this.nodes[j];

                        const dx = node2.x - node1.x;
                        const dy = node2.y - node1.y;
                        const dz = node2.z - node1.z;
                        const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1;

                        const force = 500 / (distance * distance);

                        const fx = (dx / distance) * force;
                        const fy = (dy / distance) * force;
                        const fz = (dz / distance) * force;

                        node1.vx -= fx * alpha;
                        node1.vy -= fy * alpha;
                        node1.vz -= fz * alpha;

                        node2.vx += fx * alpha;
                        node2.vy += fy * alpha;
                        node2.vz += fz * alpha;
                    }
                }

                // Attraction along links
                this.links.forEach(link => {
                    const sourceNode = this.nodes.find(n => n.id === link.source);
                    const targetNode = this.nodes.find(n => n.id === link.target);

                    if (!sourceNode || !targetNode) return;

                    const dx = targetNode.x - sourceNode.x;
                    const dy = targetNode.y - sourceNode.y;
                    const dz = targetNode.z - sourceNode.z;
                    const distance = Math.sqrt(dx * dx + dy * dy + dz * dz) || 1;

                    const force = (distance - 50) * 0.1;

                    const fx = (dx / distance) * force;
                    const fy = (dy / distance) * force;
                    const fz = (dz / distance) * force;

                    sourceNode.vx += fx * alpha;
                    sourceNode.vy += fy * alpha;
                    sourceNode.vz += fz * alpha;

                    targetNode.vx -= fx * alpha;
                    targetNode.vy -= fy * alpha;
                    targetNode.vz -= fz * alpha;
                });

                // Apply velocities and damping
                this.nodes.forEach(node => {
                    node.x += node.vx;
                    node.y += node.vy;
                    node.z += node.vz;

                    node.vx *= 0.9;
                    node.vy *= 0.9;
                    node.vz *= 0.9;
                });
            }

            // Update visual positions
            this.updateNodePositions();
            this.updateLinkPositions();
        }

        /**
         * Update node mesh positions
         */
        updateNodePositions() {
            this.nodes.forEach(node => {
                const sphere = this.nodeObjects.get(node.id);
                if (sphere) {
                    sphere.position.set(node.x, node.y, node.z);

                    if (node.label) {
                        node.label.position.set(node.x, node.y + sphere.geometry.parameters.radius + 5, node.z);
                    }
                }
            });
        }

        /**
         * Update link line positions
         */
        updateLinkPositions() {
            this.linkObjects.forEach(line => {
                const { sourceNode, targetNode } = line.userData;

                const positions = line.geometry.attributes.position.array;
                positions[0] = sourceNode.x;
                positions[1] = sourceNode.y;
                positions[2] = sourceNode.z;
                positions[3] = targetNode.x;
                positions[4] = targetNode.y;
                positions[5] = targetNode.z;

                line.geometry.attributes.position.needsUpdate = true;
            });
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Mouse move for hover
            this.renderer.domElement.addEventListener('mousemove', this.onMouseMove.bind(this), false);

            // Click for selection
            this.renderer.domElement.addEventListener('click', this.onClick.bind(this), false);

            // Window resize
            window.addEventListener('resize', this.onWindowResize.bind(this), false);

            // Keyboard navigation
            document.addEventListener('keydown', this.onKeyDown.bind(this), false);
        }

        /**
         * Handle mouse move
         */
        onMouseMove(event) {
            const rect = this.renderer.domElement.getBoundingClientRect();
            this.mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
            this.mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

            this.raycaster.setFromCamera(this.mouse, this.camera);

            const intersects = this.raycaster.intersectObjects(
                Array.from(this.nodeObjects.values())
            );

            if (intersects.length > 0) {
                const newHovered = intersects[0].object;

                if (this.hoveredNode !== newHovered) {
                    // Unhover previous
                    if (this.hoveredNode) {
                        this.hoveredNode.material.emissiveIntensity = 0.2;
                        this.hoveredNode.scale.set(1, 1, 1);
                    }

                    // Hover new
                    this.hoveredNode = newHovered;
                    this.hoveredNode.material.emissiveIntensity = 0.6;
                    this.hoveredNode.scale.set(1.2, 1.2, 1.2);

                    this.renderer.domElement.style.cursor = 'pointer';

                    this.triggerEvent('nodeHover', { node: newHovered.userData.node });
                }
            } else {
                if (this.hoveredNode) {
                    this.hoveredNode.material.emissiveIntensity = 0.2;
                    this.hoveredNode.scale.set(1, 1, 1);
                    this.hoveredNode = null;
                    this.renderer.domElement.style.cursor = 'default';

                    this.triggerEvent('nodeUnhover');
                }
            }
        }

        /**
         * Handle click
         */
        onClick(event) {
            if (this.hoveredNode) {
                // Unselect previous
                if (this.selectedNode && this.selectedNode !== this.hoveredNode) {
                    this.selectedNode.material.opacity = 0.9;
                }

                // Select new
                this.selectedNode = this.hoveredNode;
                this.selectedNode.material.opacity = 1.0;

                this.triggerEvent('nodeSelect', { node: this.selectedNode.userData.node });
            }
        }

        /**
         * Handle window resize
         */
        onWindowResize() {
            const width = this.container.clientWidth;
            const height = this.container.clientHeight || 600;

            this.camera.aspect = width / height;
            this.camera.updateProjectionMatrix();

            this.renderer.setSize(width, height);
        }

        /**
         * Handle keyboard shortcuts
         */
        onKeyDown(event) {
            switch (event.key) {
                case 'r':
                case 'R':
                    this.resetView();
                    break;
                case 'a':
                case 'A':
                    this.controls.autoRotate = !this.controls.autoRotate;
                    break;
                case 'Escape':
                    this.deselectNode();
                    break;
            }
        }

        /**
         * Reset camera view
         */
        resetView() {
            this.camera.position.set(0, 0, 200);
            this.controls.target.set(0, 0, 0);
            this.controls.update();

            this.triggerEvent('viewReset');
        }

        /**
         * Deselect current node
         */
        deselectNode() {
            if (this.selectedNode) {
                this.selectedNode.material.opacity = 0.9;
                this.selectedNode = null;

                this.triggerEvent('nodeDeselect');
            }
        }

        /**
         * Search and highlight entities
         */
        searchEntities(query) {
            const lowerQuery = query.toLowerCase();
            let matchCount = 0;

            this.nodes.forEach(node => {
                const sphere = this.nodeObjects.get(node.id);
                if (!sphere) return;

                const matches = node.name.toLowerCase().includes(lowerQuery);

                if (matches) {
                    sphere.material.emissiveIntensity = 0.8;
                    sphere.material.opacity = 1.0;
                    if (node.label) node.label.material.opacity = 1.0;
                    matchCount++;
                } else {
                    sphere.material.emissiveIntensity = 0.1;
                    sphere.material.opacity = 0.3;
                    if (node.label) node.label.material.opacity = 0.3;
                }
            });

            this.triggerEvent('searchComplete', { query, matchCount });

            return matchCount;
        }

        /**
         * Clear search filter
         */
        clearSearch() {
            this.nodes.forEach(node => {
                const sphere = this.nodeObjects.get(node.id);
                if (!sphere) return;

                sphere.material.emissiveIntensity = 0.2;
                sphere.material.opacity = 0.9;
                if (node.label) node.label.material.opacity = 0.8;
            });

            this.triggerEvent('searchCleared');
        }

        /**
         * Filter by entity type
         */
        filterByType(types) {
            const typeArray = Array.isArray(types) ? types : [types];

            this.nodes.forEach(node => {
                const sphere = this.nodeObjects.get(node.id);
                if (!sphere) return;

                const visible = typeArray.length === 0 || typeArray.includes(node.type);

                sphere.visible = visible;
                if (node.label) node.label.visible = visible;
            });

            // Update link visibility
            this.linkObjects.forEach(line => {
                const { sourceNode, targetNode } = line.userData;
                const sourceSphere = this.nodeObjects.get(sourceNode.id);
                const targetSphere = this.nodeObjects.get(targetNode.id);

                line.visible = sourceSphere?.visible && targetSphere?.visible;
            });

            this.triggerEvent('typeFilter', { types: typeArray });
        }

        /**
         * Animation loop
         */
        animate() {
            this.animationId = requestAnimationFrame(this.animate.bind(this));

            const startTime = performance.now();

            // Update controls
            if (this.controls) {
                this.controls.update();
            }

            // Render scene
            this.renderer.render(this.scene, this.camera);

            // Update stats
            this.stats.renderTime = performance.now() - startTime;
            this.stats.fps = 1000 / this.stats.renderTime;
        }

        /**
         * Trigger custom event
         */
        triggerEvent(eventName, detail = {}) {
            const event = new CustomEvent(`galaxy:${eventName}`, {
                detail: { galaxy: this, ...detail }
            });
            this.container.dispatchEvent(event);
        }

        /**
         * Dispose and cleanup
         */
        dispose() {
            // Cancel animation
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
            }

            // Remove event listeners
            window.removeEventListener('resize', this.onWindowResize.bind(this));

            // Dispose geometries and materials
            this.scene.traverse(object => {
                if (object.geometry) {
                    object.geometry.dispose();
                }
                if (object.material) {
                    if (Array.isArray(object.material)) {
                        object.material.forEach(material => material.dispose());
                    } else {
                        object.material.dispose();
                    }
                }
            });

            // Dispose renderer
            this.renderer.dispose();

            // Remove DOM element
            if (this.renderer.domElement.parentNode) {
                this.renderer.domElement.parentNode.removeChild(this.renderer.domElement);
            }

            this.triggerEvent('disposed');
        }

        /**
         * Get current stats
         */
        getStats() {
            return { ...this.stats };
        }
    }

    // Expose to global scope
    window.SemanticGalaxy = SemanticGalaxy;

    // Auto-initialize on DOM ready
    $(document).ready(function() {
        $('.saga-galaxy-container').each(function() {
            const $container = $(this);
            const sagaId = $container.data('saga-id') || 1;

            const galaxy = new SemanticGalaxy(this, { sagaId });

            // Store reference
            $container.data('galaxy', galaxy);
        });
    });

})(jQuery);
