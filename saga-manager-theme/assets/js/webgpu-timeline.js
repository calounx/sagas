/**
 * WebGPU Infinite Zoom Timeline
 * High-performance timeline visualization with GPU acceleration
 *
 * @package SagaManager
 * @since 1.3.0
 */

class WebGPUTimeline {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            sagaId: options.sagaId || null,
            width: options.width || container.clientWidth,
            height: options.height || container.clientHeight,
            minZoom: options.minZoom || 0.0001, // Years to millennia
            maxZoom: options.maxZoom || 1000, // Down to hours
            initialZoom: options.initialZoom || 1,
            initialCenter: options.initialCenter || 0,
            backgroundColor: options.backgroundColor || '#1a1a2e',
            gridColor: options.gridColor || '#16213e',
            eventColor: options.eventColor || '#0f3460',
            accentColor: options.accentColor || '#e94560',
            ...options
        };

        // State
        this.zoom = this.options.initialZoom;
        this.centerTimestamp = this.options.initialCenter;
        this.isDragging = false;
        this.lastMouseX = 0;
        this.lastMouseY = 0;
        this.velocity = { x: 0, y: 0 };
        this.events = [];
        this.quadtree = null;
        this.devicePixelRatio = window.devicePixelRatio || 1;

        // WebGPU support
        this.gpu = null;
        this.device = null;
        this.context = null;
        this.pipeline = null;
        this.useWebGPU = false;

        // Fallback canvas
        this.canvas2d = null;
        this.ctx2d = null;

        this.init();
    }

    async init() {
        try {
            await this.initWebGPU();
            this.useWebGPU = true;
        } catch (error) {
            console.warn('WebGPU not available, falling back to Canvas 2D:', error);
            this.initCanvas2D();
            this.useWebGPU = false;
        }

        this.setupEventListeners();
        this.loadTimelineData();
        this.startRenderLoop();
    }

    async initWebGPU() {
        if (!navigator.gpu) {
            throw new Error('WebGPU not supported');
        }

        // Create canvas
        this.canvas = document.createElement('canvas');
        this.canvas.width = this.options.width * this.devicePixelRatio;
        this.canvas.height = this.options.height * this.devicePixelRatio;
        this.canvas.style.width = `${this.options.width}px`;
        this.canvas.style.height = `${this.options.height}px`;
        this.canvas.className = 'saga-timeline-canvas';
        this.container.appendChild(this.canvas);

        // Get GPU adapter
        const adapter = await navigator.gpu.requestAdapter();
        if (!adapter) {
            throw new Error('No GPU adapter found');
        }

        // Get GPU device
        this.device = await adapter.requestDevice();

        // Configure context
        this.context = this.canvas.getContext('webgpu');
        const format = navigator.gpu.getPreferredCanvasFormat();

        this.context.configure({
            device: this.device,
            format: format,
            alphaMode: 'premultiplied',
        });

        await this.createPipeline();
    }

    async createPipeline() {
        const shaderCode = `
            struct VertexInput {
                @location(0) position: vec2<f32>,
                @location(1) color: vec4<f32>,
            }

            struct VertexOutput {
                @builtin(position) position: vec4<f32>,
                @location(0) color: vec4<f32>,
            }

            struct Uniforms {
                viewMatrix: mat4x4<f32>,
                projectionMatrix: mat4x4<f32>,
                zoom: f32,
                centerTimestamp: f32,
                screenWidth: f32,
                screenHeight: f32,
            }

            @group(0) @binding(0) var<uniform> uniforms: Uniforms;

            @vertex
            fn vertex_main(input: VertexInput) -> VertexOutput {
                var output: VertexOutput;

                // Transform position based on timeline zoom and center
                var transformedPos = input.position;
                transformedPos.x = (transformedPos.x - uniforms.centerTimestamp) * uniforms.zoom;
                transformedPos.x = transformedPos.x / (uniforms.screenWidth * 0.5);
                transformedPos.y = transformedPos.y / (uniforms.screenHeight * 0.5);

                output.position = vec4<f32>(transformedPos, 0.0, 1.0);
                output.color = input.color;

                return output;
            }

            @fragment
            fn fragment_main(input: VertexOutput) -> @location(0) vec4<f32> {
                return input.color;
            }
        `;

        const shaderModule = this.device.createShaderModule({
            code: shaderCode,
        });

        // Create uniform buffer
        this.uniformBuffer = this.device.createBuffer({
            size: 128, // mat4x4 + mat4x4 + 4 floats = 32+32+16 = 80 bytes (padded to 128)
            usage: GPUBufferUsage.UNIFORM | GPUBufferUsage.COPY_DST,
        });

        // Create bind group layout
        const bindGroupLayout = this.device.createBindGroupLayout({
            entries: [{
                binding: 0,
                visibility: GPUShaderStage.VERTEX | GPUShaderStage.FRAGMENT,
                buffer: { type: 'uniform' }
            }]
        });

        // Create bind group
        this.bindGroup = this.device.createBindGroup({
            layout: bindGroupLayout,
            entries: [{
                binding: 0,
                resource: { buffer: this.uniformBuffer }
            }]
        });

        // Create pipeline layout
        const pipelineLayout = this.device.createPipelineLayout({
            bindGroupLayouts: [bindGroupLayout]
        });

        // Create render pipeline
        this.pipeline = this.device.createRenderPipeline({
            layout: pipelineLayout,
            vertex: {
                module: shaderModule,
                entryPoint: 'vertex_main',
                buffers: [{
                    arrayStride: 24, // 2 floats (position) + 4 floats (color) = 24 bytes
                    attributes: [
                        {
                            shaderLocation: 0,
                            offset: 0,
                            format: 'float32x2'
                        },
                        {
                            shaderLocation: 1,
                            offset: 8,
                            format: 'float32x4'
                        }
                    ]
                }]
            },
            fragment: {
                module: shaderModule,
                entryPoint: 'fragment_main',
                targets: [{
                    format: navigator.gpu.getPreferredCanvasFormat()
                }]
            },
            primitive: {
                topology: 'triangle-list',
            }
        });
    }

    initCanvas2D() {
        // Create 2D canvas fallback
        this.canvas2d = document.createElement('canvas');
        this.canvas2d.width = this.options.width * this.devicePixelRatio;
        this.canvas2d.height = this.options.height * this.devicePixelRatio;
        this.canvas2d.style.width = `${this.options.width}px`;
        this.canvas2d.style.height = `${this.options.height}px`;
        this.canvas2d.className = 'saga-timeline-canvas';
        this.container.appendChild(this.canvas2d);

        this.ctx2d = this.canvas2d.getContext('2d');
        this.ctx2d.scale(this.devicePixelRatio, this.devicePixelRatio);
    }

    setupEventListeners() {
        const canvas = this.useWebGPU ? this.canvas : this.canvas2d;

        // Mouse events
        canvas.addEventListener('mousedown', this.onMouseDown.bind(this));
        canvas.addEventListener('mousemove', this.onMouseMove.bind(this));
        canvas.addEventListener('mouseup', this.onMouseUp.bind(this));
        canvas.addEventListener('mouseleave', this.onMouseUp.bind(this));

        // Wheel event for zoom
        canvas.addEventListener('wheel', this.onWheel.bind(this), { passive: false });

        // Touch events for mobile
        canvas.addEventListener('touchstart', this.onTouchStart.bind(this), { passive: false });
        canvas.addEventListener('touchmove', this.onTouchMove.bind(this), { passive: false });
        canvas.addEventListener('touchend', this.onTouchEnd.bind(this));

        // Keyboard shortcuts
        document.addEventListener('keydown', this.onKeyDown.bind(this));

        // Resize
        window.addEventListener('resize', this.onResize.bind(this));
    }

    onMouseDown(event) {
        this.isDragging = true;
        this.lastMouseX = event.clientX;
        this.lastMouseY = event.clientY;
        this.velocity = { x: 0, y: 0 };
        event.preventDefault();
    }

    onMouseMove(event) {
        if (!this.isDragging) {
            this.handleHover(event.clientX, event.clientY);
            return;
        }

        const deltaX = event.clientX - this.lastMouseX;
        const deltaY = event.clientY - this.lastMouseY;

        // Update velocity for inertia
        this.velocity.x = deltaX;
        this.velocity.y = deltaY;

        // Pan timeline
        this.pan(deltaX, deltaY);

        this.lastMouseX = event.clientX;
        this.lastMouseY = event.clientY;
    }

    onMouseUp(event) {
        if (this.isDragging) {
            this.isDragging = false;
            // Apply inertia
            this.applyInertia();
        }
    }

    onWheel(event) {
        event.preventDefault();

        const delta = -event.deltaY * 0.001;
        const mouseX = event.clientX - this.container.getBoundingClientRect().left;

        this.zoomAt(mouseX, delta);
    }

    onTouchStart(event) {
        if (event.touches.length === 1) {
            this.isDragging = true;
            this.lastMouseX = event.touches[0].clientX;
            this.lastMouseY = event.touches[0].clientY;
        } else if (event.touches.length === 2) {
            // Pinch zoom
            this.lastPinchDistance = this.getPinchDistance(event.touches);
        }
        event.preventDefault();
    }

    onTouchMove(event) {
        if (event.touches.length === 1 && this.isDragging) {
            const touch = event.touches[0];
            const deltaX = touch.clientX - this.lastMouseX;
            const deltaY = touch.clientY - this.lastMouseY;

            this.pan(deltaX, deltaY);

            this.lastMouseX = touch.clientX;
            this.lastMouseY = touch.clientY;
        } else if (event.touches.length === 2) {
            const distance = this.getPinchDistance(event.touches);
            const delta = (distance - this.lastPinchDistance) * 0.01;

            const centerX = (event.touches[0].clientX + event.touches[1].clientX) / 2;
            const containerX = centerX - this.container.getBoundingClientRect().left;

            this.zoomAt(containerX, delta);
            this.lastPinchDistance = distance;
        }
        event.preventDefault();
    }

    onTouchEnd(event) {
        this.isDragging = false;
    }

    getPinchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    onKeyDown(event) {
        const step = 50 / this.zoom;

        switch(event.key) {
            case 'ArrowLeft':
                this.centerTimestamp -= step;
                event.preventDefault();
                break;
            case 'ArrowRight':
                this.centerTimestamp += step;
                event.preventDefault();
                break;
            case '+':
            case '=':
                this.zoom *= 1.2;
                this.zoom = Math.min(this.zoom, this.options.maxZoom);
                event.preventDefault();
                break;
            case '-':
            case '_':
                this.zoom *= 0.8;
                this.zoom = Math.max(this.zoom, this.options.minZoom);
                event.preventDefault();
                break;
            case 'Home':
                this.centerTimestamp = 0;
                event.preventDefault();
                break;
        }
    }

    onResize() {
        this.options.width = this.container.clientWidth;
        this.options.height = this.container.clientHeight;

        if (this.useWebGPU && this.canvas) {
            this.canvas.width = this.options.width * this.devicePixelRatio;
            this.canvas.height = this.options.height * this.devicePixelRatio;
            this.canvas.style.width = `${this.options.width}px`;
            this.canvas.style.height = `${this.options.height}px`;
        } else if (this.canvas2d) {
            this.canvas2d.width = this.options.width * this.devicePixelRatio;
            this.canvas2d.height = this.options.height * this.devicePixelRatio;
            this.canvas2d.style.width = `${this.options.width}px`;
            this.canvas2d.style.height = `${this.options.height}px`;
            this.ctx2d.scale(this.devicePixelRatio, this.devicePixelRatio);
        }
    }

    pan(deltaX, deltaY) {
        // Convert pixel delta to timestamp delta
        const timestampDelta = -deltaX / this.zoom;
        this.centerTimestamp += timestampDelta;
    }

    zoomAt(mouseX, delta) {
        const oldZoom = this.zoom;
        this.zoom *= (1 + delta);
        this.zoom = Math.max(this.options.minZoom, Math.min(this.options.maxZoom, this.zoom));

        // Adjust center to zoom at mouse position
        const centerOffset = (mouseX - this.options.width / 2) / oldZoom;
        const newCenterOffset = (mouseX - this.options.width / 2) / this.zoom;
        this.centerTimestamp += centerOffset - newCenterOffset;
    }

    applyInertia() {
        const friction = 0.95;
        const threshold = 0.1;

        const animate = () => {
            if (Math.abs(this.velocity.x) > threshold || Math.abs(this.velocity.y) > threshold) {
                this.pan(this.velocity.x, this.velocity.y);
                this.velocity.x *= friction;
                this.velocity.y *= friction;
                requestAnimationFrame(animate);
            }
        };

        animate();
    }

    handleHover(clientX, clientY) {
        const rect = this.container.getBoundingClientRect();
        const x = clientX - rect.left;
        const y = clientY - rect.top;

        // Find event at position
        const event = this.getEventAtPosition(x, y);

        if (event) {
            this.showTooltip(event, clientX, clientY);
        } else {
            this.hideTooltip();
        }
    }

    getEventAtPosition(x, y) {
        // Convert screen position to timestamp
        const timestamp = this.screenToTimestamp(x);

        // Find events near this timestamp
        const tolerance = 10 / this.zoom; // 10 pixels in timestamp units

        for (const event of this.events) {
            if (Math.abs(event.timestamp - timestamp) < tolerance) {
                // Check Y position for multi-track support
                const eventY = this.getEventY(event);
                if (Math.abs(eventY - y) < 20) {
                    return event;
                }
            }
        }

        return null;
    }

    screenToTimestamp(screenX) {
        const centerX = this.options.width / 2;
        const offsetX = screenX - centerX;
        return this.centerTimestamp + offsetX / this.zoom;
    }

    timestampToScreen(timestamp) {
        const centerX = this.options.width / 2;
        const offset = (timestamp - this.centerTimestamp) * this.zoom;
        return centerX + offset;
    }

    getEventY(event) {
        // Multi-track support: distribute events across vertical tracks
        const trackHeight = 60;
        const track = event.track || 0;
        return this.options.height / 2 + track * trackHeight;
    }

    async loadTimelineData() {
        if (!this.options.sagaId) {
            console.error('No saga ID provided');
            return;
        }

        try {
            const response = await fetch(`${sagaTimelineAjax.ajaxUrl}?action=get_timeline_events&saga_id=${this.options.sagaId}&nonce=${sagaTimelineAjax.nonce}`);
            const data = await response.json();

            if (data.success) {
                this.events = data.data.events;
                this.buildQuadtree();

                // Set initial view to show all events
                if (this.events.length > 0) {
                    this.fitToEvents();
                }
            } else {
                console.error('Failed to load timeline data:', data.message);
            }
        } catch (error) {
            console.error('Error loading timeline data:', error);
        }
    }

    buildQuadtree() {
        // Simple quadtree for spatial indexing
        // This enables efficient culling of off-screen events
        this.quadtree = new TimelineQuadtree(this.events);
    }

    fitToEvents() {
        if (this.events.length === 0) return;

        const timestamps = this.events.map(e => e.timestamp);
        const minTimestamp = Math.min(...timestamps);
        const maxTimestamp = Math.max(...timestamps);

        this.centerTimestamp = (minTimestamp + maxTimestamp) / 2;

        const range = maxTimestamp - minTimestamp;
        this.zoom = this.options.width * 0.8 / range;
        this.zoom = Math.max(this.options.minZoom, Math.min(this.options.maxZoom, this.zoom));
    }

    startRenderLoop() {
        const render = () => {
            if (this.useWebGPU) {
                this.renderWebGPU();
            } else {
                this.renderCanvas2D();
            }
            requestAnimationFrame(render);
        };
        render();
    }

    renderWebGPU() {
        if (!this.device || !this.context || !this.pipeline) return;

        // Update uniforms
        const uniformData = new Float32Array([
            // View matrix (identity for now)
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1,
            // Projection matrix (identity for now)
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1,
            // Zoom, center, width, height
            this.zoom,
            this.centerTimestamp,
            this.options.width,
            this.options.height,
        ]);

        this.device.queue.writeBuffer(this.uniformBuffer, 0, uniformData);

        // Get visible events
        const visibleEvents = this.getVisibleEvents();

        // Create vertex data
        const vertices = this.createVertexData(visibleEvents);

        if (vertices.length === 0) {
            // Clear screen
            const commandEncoder = this.device.createCommandEncoder();
            const textureView = this.context.getCurrentTexture().createView();

            const renderPass = commandEncoder.beginRenderPass({
                colorAttachments: [{
                    view: textureView,
                    clearValue: this.hexToRgb(this.options.backgroundColor),
                    loadOp: 'clear',
                    storeOp: 'store',
                }]
            });

            renderPass.end();
            this.device.queue.submit([commandEncoder.finish()]);
            return;
        }

        // Create vertex buffer
        const vertexBuffer = this.device.createBuffer({
            size: vertices.byteLength,
            usage: GPUBufferUsage.VERTEX | GPUBufferUsage.COPY_DST,
        });

        this.device.queue.writeBuffer(vertexBuffer, 0, vertices);

        // Render
        const commandEncoder = this.device.createCommandEncoder();
        const textureView = this.context.getCurrentTexture().createView();

        const renderPass = commandEncoder.beginRenderPass({
            colorAttachments: [{
                view: textureView,
                clearValue: this.hexToRgb(this.options.backgroundColor),
                loadOp: 'clear',
                storeOp: 'store',
            }]
        });

        renderPass.setPipeline(this.pipeline);
        renderPass.setBindGroup(0, this.bindGroup);
        renderPass.setVertexBuffer(0, vertexBuffer);
        renderPass.draw(vertices.length / 6); // 6 floats per vertex
        renderPass.end();

        this.device.queue.submit([commandEncoder.finish()]);
    }

    renderCanvas2D() {
        if (!this.ctx2d) return;

        const ctx = this.ctx2d;
        const width = this.options.width;
        const height = this.options.height;

        // Clear
        ctx.fillStyle = this.options.backgroundColor;
        ctx.fillRect(0, 0, width, height);

        // Draw grid
        this.drawGrid(ctx);

        // Get visible events
        const visibleEvents = this.getVisibleEvents();

        // Draw era bands
        this.drawEraBands(ctx);

        // Draw relationship connections
        this.drawRelationships(ctx, visibleEvents);

        // Draw events
        for (const event of visibleEvents) {
            this.drawEvent(ctx, event);
        }

        // Draw time labels
        this.drawTimeLabels(ctx);
    }

    drawGrid(ctx) {
        const width = this.options.width;
        const height = this.options.height;

        // Determine grid interval based on zoom
        const interval = this.getGridInterval();

        ctx.strokeStyle = this.options.gridColor;
        ctx.lineWidth = 1;
        ctx.globalAlpha = 0.3;

        // Calculate visible timestamp range
        const leftTimestamp = this.screenToTimestamp(0);
        const rightTimestamp = this.screenToTimestamp(width);

        // Draw vertical grid lines
        const startGrid = Math.floor(leftTimestamp / interval) * interval;

        for (let timestamp = startGrid; timestamp <= rightTimestamp; timestamp += interval) {
            const x = this.timestampToScreen(timestamp);

            if (x >= 0 && x <= width) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, height);
                ctx.stroke();
            }
        }

        ctx.globalAlpha = 1.0;
    }

    getGridInterval() {
        // Adaptive grid interval based on zoom level
        const pixelsPerUnit = this.zoom;

        const YEAR = 365.25 * 24 * 3600;
        const MONTH = 30 * 24 * 3600;
        const DAY = 24 * 3600;
        const HOUR = 3600;

        const targetPixelSpacing = 80;

        const intervals = [
            { size: 1000 * YEAR, label: '1000 years' },
            { size: 100 * YEAR, label: '100 years' },
            { size: 10 * YEAR, label: '10 years' },
            { size: YEAR, label: '1 year' },
            { size: MONTH, label: '1 month' },
            { size: DAY, label: '1 day' },
            { size: HOUR, label: '1 hour' },
        ];

        for (const interval of intervals) {
            if (interval.size * pixelsPerUnit >= targetPixelSpacing) {
                return interval.size;
            }
        }

        return HOUR;
    }

    drawEraBands(ctx) {
        // Draw background era/age bands
        // This would require era data from the saga configuration
        // Placeholder for now
    }

    drawRelationships(ctx, events) {
        ctx.strokeStyle = this.options.accentColor;
        ctx.lineWidth = 1;
        ctx.globalAlpha = 0.4;
        ctx.setLineDash([5, 5]);

        for (const event of events) {
            if (event.relatedEvents) {
                for (const relatedId of event.relatedEvents) {
                    const related = events.find(e => e.id === relatedId);
                    if (related) {
                        const x1 = this.timestampToScreen(event.timestamp);
                        const y1 = this.getEventY(event);
                        const x2 = this.timestampToScreen(related.timestamp);
                        const y2 = this.getEventY(related);

                        ctx.beginPath();
                        ctx.moveTo(x1, y1);
                        ctx.lineTo(x2, y2);
                        ctx.stroke();
                    }
                }
            }
        }

        ctx.setLineDash([]);
        ctx.globalAlpha = 1.0;
    }

    drawEvent(ctx, event) {
        const x = this.timestampToScreen(event.timestamp);
        const y = this.getEventY(event);

        // Don't draw if off-screen
        if (x < -50 || x > this.options.width + 50) return;

        // Event marker
        const radius = 8;
        const color = this.getEventColor(event);

        // Glow effect
        const gradient = ctx.createRadialGradient(x, y, 0, x, y, radius * 2);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, this.options.backgroundColor);

        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(x, y, radius * 2, 0, Math.PI * 2);
        ctx.fill();

        // Solid marker
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(x, y, radius, 0, Math.PI * 2);
        ctx.fill();

        // Border
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Label (only if zoomed in enough)
        if (this.zoom > 0.1) {
            ctx.fillStyle = '#ffffff';
            ctx.font = '12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(event.title, x, y + radius + 15);
        }
    }

    getEventColor(event) {
        // Color code by entity type
        const colors = {
            character: '#e94560',
            location: '#0f3460',
            event: '#16213e',
            faction: '#533483',
            artifact: '#f97068',
            concept: '#57cc99'
        };

        return colors[event.entityType] || this.options.eventColor;
    }

    drawTimeLabels(ctx) {
        const width = this.options.width;
        const interval = this.getGridInterval();

        ctx.fillStyle = '#ffffff';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'center';

        const leftTimestamp = this.screenToTimestamp(0);
        const rightTimestamp = this.screenToTimestamp(width);
        const startGrid = Math.floor(leftTimestamp / interval) * interval;

        for (let timestamp = startGrid; timestamp <= rightTimestamp; timestamp += interval) {
            const x = this.timestampToScreen(timestamp);

            if (x >= 30 && x <= width - 30) {
                const label = this.formatTimestamp(timestamp);
                ctx.fillText(label, x, 20);
            }
        }
    }

    formatTimestamp(timestamp) {
        // This should integrate with the saga calendar system
        // For now, simple formatting
        const date = new Date(timestamp * 1000);

        if (this.zoom < 0.001) {
            return `${Math.floor(timestamp / (365.25 * 24 * 3600))} Y`;
        } else if (this.zoom < 0.01) {
            return date.getFullYear().toString();
        } else if (this.zoom < 1) {
            return date.toLocaleDateString();
        } else {
            return date.toLocaleString();
        }
    }

    getVisibleEvents() {
        const leftTimestamp = this.screenToTimestamp(-100);
        const rightTimestamp = this.screenToTimestamp(this.options.width + 100);

        // Use quadtree for efficient culling if available
        if (this.quadtree) {
            return this.quadtree.query(leftTimestamp, rightTimestamp);
        }

        // Fallback: linear search
        return this.events.filter(event =>
            event.timestamp >= leftTimestamp &&
            event.timestamp <= rightTimestamp
        );
    }

    createVertexData(events) {
        const vertices = [];

        for (const event of events) {
            const x = event.timestamp;
            const y = this.getEventY(event);
            const color = this.hexToRgba(this.getEventColor(event));

            // Create a simple quad for each event
            const size = 10;

            // Triangle 1
            vertices.push(
                x - size, y - size, ...color,
                x + size, y - size, ...color,
                x + size, y + size, ...color
            );

            // Triangle 2
            vertices.push(
                x - size, y - size, ...color,
                x + size, y + size, ...color,
                x - size, y + size, ...color
            );
        }

        return new Float32Array(vertices);
    }

    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16) / 255,
            g: parseInt(result[2], 16) / 255,
            b: parseInt(result[3], 16) / 255,
            a: 1
        } : { r: 0, g: 0, b: 0, a: 1 };
    }

    hexToRgba(hex) {
        const rgb = this.hexToRgb(hex);
        return [rgb.r, rgb.g, rgb.b, rgb.a];
    }

    showTooltip(event, x, y) {
        // Dispatch custom event for tooltip
        const tooltipEvent = new CustomEvent('saga-timeline-hover', {
            detail: { event, x, y }
        });
        this.container.dispatchEvent(tooltipEvent);
    }

    hideTooltip() {
        const tooltipEvent = new CustomEvent('saga-timeline-hover', {
            detail: null
        });
        this.container.dispatchEvent(tooltipEvent);
    }

    // Public API methods
    zoomIn() {
        this.zoom *= 1.5;
        this.zoom = Math.min(this.zoom, this.options.maxZoom);
    }

    zoomOut() {
        this.zoom *= 0.67;
        this.zoom = Math.max(this.zoom, this.options.minZoom);
    }

    goToTimestamp(timestamp, animate = true) {
        if (animate) {
            // Smooth animation to timestamp
            const start = this.centerTimestamp;
            const distance = timestamp - start;
            const duration = 500; // ms
            const startTime = performance.now();

            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing function
                const eased = 1 - Math.pow(1 - progress, 3);

                this.centerTimestamp = start + distance * eased;

                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };

            requestAnimationFrame(animate);
        } else {
            this.centerTimestamp = timestamp;
        }
    }

    destroy() {
        // Clean up resources
        if (this.useWebGPU) {
            if (this.device) {
                this.device.destroy();
            }
            if (this.canvas) {
                this.canvas.remove();
            }
        } else {
            if (this.canvas2d) {
                this.canvas2d.remove();
            }
        }
    }
}

// Quadtree for spatial indexing
class TimelineQuadtree {
    constructor(events, bounds = null) {
        this.events = events;

        if (!bounds && events.length > 0) {
            const timestamps = events.map(e => e.timestamp);
            this.bounds = {
                min: Math.min(...timestamps),
                max: Math.max(...timestamps)
            };
        } else {
            this.bounds = bounds || { min: 0, max: 0 };
        }

        this.capacity = 10;
        this.divided = false;
        this.left = null;
        this.right = null;

        if (this.events.length > this.capacity) {
            this.subdivide();
        }
    }

    subdivide() {
        const mid = (this.bounds.min + this.bounds.max) / 2;

        const leftEvents = this.events.filter(e => e.timestamp < mid);
        const rightEvents = this.events.filter(e => e.timestamp >= mid);

        if (leftEvents.length > 0) {
            this.left = new TimelineQuadtree(leftEvents, {
                min: this.bounds.min,
                max: mid
            });
        }

        if (rightEvents.length > 0) {
            this.right = new TimelineQuadtree(rightEvents, {
                min: mid,
                max: this.bounds.max
            });
        }

        this.divided = true;
        this.events = []; // Clear events from parent node
    }

    query(minTimestamp, maxTimestamp) {
        // Return events within timestamp range
        if (maxTimestamp < this.bounds.min || minTimestamp > this.bounds.max) {
            return [];
        }

        if (!this.divided) {
            return this.events.filter(e =>
                e.timestamp >= minTimestamp &&
                e.timestamp <= maxTimestamp
            );
        }

        const results = [];

        if (this.left) {
            results.push(...this.left.query(minTimestamp, maxTimestamp));
        }

        if (this.right) {
            results.push(...this.right.query(minTimestamp, maxTimestamp));
        }

        return results;
    }
}

// Export for use in WordPress
if (typeof window !== 'undefined') {
    window.WebGPUTimeline = WebGPUTimeline;
}
