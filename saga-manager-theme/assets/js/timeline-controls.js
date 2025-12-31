/**
 * Timeline Interactive Controls
 * UI controls for timeline navigation, bookmarks, and minimap
 *
 * @package SagaManager
 * @since 1.3.0
 */

class TimelineControls {
    constructor(timeline, container) {
        this.timeline = timeline;
        this.container = container;
        this.bookmarks = [];
        this.minimap = null;
        this.tooltip = null;
        this.searchPanel = null;

        this.init();
    }

    init() {
        this.createControlPanel();
        this.createMinimap();
        this.createTooltip();
        this.createSearchPanel();
        this.createBookmarkPanel();
        this.setupEventListeners();
        this.loadBookmarks();
    }

    createControlPanel() {
        const panel = document.createElement('div');
        panel.className = 'saga-timeline-controls';
        panel.innerHTML = `
            <div class="timeline-control-group">
                <button class="timeline-btn" data-action="zoom-in" title="Zoom In (+)" aria-label="Zoom in">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="timeline-btn" data-action="zoom-out" title="Zoom Out (-)" aria-label="Zoom out">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <path d="M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="timeline-btn" data-action="fit-all" title="Fit All Events (Home)" aria-label="Fit all events">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <rect x="3" y="3" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" rx="2"/>
                        <path d="M7 10h6M10 7v6" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </button>
            </div>

            <div class="timeline-control-group">
                <button class="timeline-btn" data-action="search" title="Search Timeline" aria-label="Search timeline">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="8" cy="8" r="5" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 12l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="timeline-btn" data-action="bookmarks" title="Bookmarks" aria-label="View bookmarks">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <path d="M5 2h10a1 1 0 011 1v15l-6-4-6 4V3a1 1 0 011-1z" stroke="currentColor" stroke-width="2" fill="none"/>
                    </svg>
                </button>
                <button class="timeline-btn" data-action="add-bookmark" title="Add Bookmark" aria-label="Add bookmark">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2"/>
                        <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none"/>
                    </svg>
                </button>
            </div>

            <div class="timeline-control-group">
                <button class="timeline-btn" data-action="export-image" title="Export as Image" aria-label="Export as image">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <path d="M10 12V4m0 8l-3-3m3 3l3-3M4 16h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="timeline-btn" data-action="settings" title="Settings" aria-label="Timeline settings">
                    <svg width="20" height="20" viewBox="0 0 20 20">
                        <circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M10 2v2m0 12v2M2 10h2m12 0h2m-2.93-5.07l-1.41 1.41M7.34 13.66l-1.41 1.41m11.14 0l-1.41-1.41M7.34 6.34L5.93 4.93" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="timeline-zoom-level">
                <span class="zoom-label">Zoom:</span>
                <span class="zoom-value">100%</span>
            </div>
        `;

        this.container.appendChild(panel);
        this.controlPanel = panel;
    }

    createMinimap() {
        const minimap = document.createElement('div');
        minimap.className = 'saga-timeline-minimap';
        minimap.innerHTML = `
            <canvas class="minimap-canvas"></canvas>
            <div class="minimap-viewport"></div>
        `;

        this.container.appendChild(minimap);
        this.minimap = minimap;

        this.minimapCanvas = minimap.querySelector('.minimap-canvas');
        this.minimapViewport = minimap.querySelector('.minimap-viewport');
        this.minimapCtx = this.minimapCanvas.getContext('2d');

        // Set canvas size
        this.minimapCanvas.width = 200;
        this.minimapCanvas.height = 60;

        // Setup minimap interactions
        this.setupMinimapInteraction();
    }

    setupMinimapInteraction() {
        let isDragging = false;

        this.minimapCanvas.addEventListener('mousedown', (e) => {
            isDragging = true;
            this.handleMinimapClick(e);
        });

        this.minimapCanvas.addEventListener('mousemove', (e) => {
            if (isDragging) {
                this.handleMinimapClick(e);
            }
        });

        this.minimapCanvas.addEventListener('mouseup', () => {
            isDragging = false;
        });

        this.minimapCanvas.addEventListener('mouseleave', () => {
            isDragging = false;
        });
    }

    handleMinimapClick(e) {
        const rect = this.minimapCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;

        // Convert minimap position to timeline timestamp
        const events = this.timeline.events;
        if (events.length === 0) return;

        const timestamps = events.map(e => e.timestamp);
        const minTimestamp = Math.min(...timestamps);
        const maxTimestamp = Math.max(...timestamps);

        const range = maxTimestamp - minTimestamp;
        const clickRatio = x / this.minimapCanvas.width;
        const targetTimestamp = minTimestamp + range * clickRatio;

        this.timeline.goToTimestamp(targetTimestamp, true);
    }

    createTooltip() {
        const tooltip = document.createElement('div');
        tooltip.className = 'saga-timeline-tooltip';
        tooltip.style.display = 'none';
        tooltip.innerHTML = `
            <div class="tooltip-header">
                <span class="tooltip-type"></span>
                <span class="tooltip-title"></span>
            </div>
            <div class="tooltip-content">
                <div class="tooltip-date"></div>
                <div class="tooltip-description"></div>
            </div>
        `;

        document.body.appendChild(tooltip);
        this.tooltip = tooltip;

        // Listen for hover events from timeline
        this.container.addEventListener('saga-timeline-hover', (e) => {
            if (e.detail && e.detail.event) {
                this.showTooltip(e.detail.event, e.detail.x, e.detail.y);
            } else {
                this.hideTooltip();
            }
        });
    }

    showTooltip(event, x, y) {
        this.tooltip.querySelector('.tooltip-type').textContent = event.entityType || 'Event';
        this.tooltip.querySelector('.tooltip-title').textContent = event.title;
        this.tooltip.querySelector('.tooltip-date').textContent = this.formatDate(event.timestamp);
        this.tooltip.querySelector('.tooltip-description').textContent = event.description || '';

        // Position tooltip
        this.tooltip.style.left = `${x + 15}px`;
        this.tooltip.style.top = `${y + 15}px`;
        this.tooltip.style.display = 'block';

        // Adjust position if tooltip goes off screen
        requestAnimationFrame(() => {
            const rect = this.tooltip.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                this.tooltip.style.left = `${x - rect.width - 15}px`;
            }
            if (rect.bottom > window.innerHeight) {
                this.tooltip.style.top = `${y - rect.height - 15}px`;
            }
        });
    }

    hideTooltip() {
        this.tooltip.style.display = 'none';
    }

    createSearchPanel() {
        const panel = document.createElement('div');
        panel.className = 'saga-timeline-search-panel';
        panel.style.display = 'none';
        panel.innerHTML = `
            <div class="search-panel-header">
                <h3>Search Timeline</h3>
                <button class="close-panel" aria-label="Close search">×</button>
            </div>
            <div class="search-panel-content">
                <input type="text" class="search-input" placeholder="Search events..." />
                <div class="search-results"></div>
            </div>
        `;

        this.container.appendChild(panel);
        this.searchPanel = panel;

        const searchInput = panel.querySelector('.search-input');
        searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });

        panel.querySelector('.close-panel').addEventListener('click', () => {
            this.hideSearchPanel();
        });
    }

    createBookmarkPanel() {
        const panel = document.createElement('div');
        panel.className = 'saga-timeline-bookmark-panel';
        panel.style.display = 'none';
        panel.innerHTML = `
            <div class="bookmark-panel-header">
                <h3>Bookmarks</h3>
                <button class="close-panel" aria-label="Close bookmarks">×</button>
            </div>
            <div class="bookmark-panel-content">
                <div class="bookmark-list"></div>
            </div>
        `;

        this.container.appendChild(panel);
        this.bookmarkPanel = panel;

        panel.querySelector('.close-panel').addEventListener('click', () => {
            this.hideBookmarkPanel();
        });
    }

    setupEventListeners() {
        // Control buttons
        this.controlPanel.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;
            this.handleAction(action);
        });

        // Update zoom display
        setInterval(() => {
            this.updateZoomDisplay();
            this.updateMinimap();
        }, 100);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;

            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        this.showSearchPanel();
                        break;
                    case 'b':
                        e.preventDefault();
                        this.addBookmark();
                        break;
                }
            }
        });
    }

    handleAction(action) {
        switch(action) {
            case 'zoom-in':
                this.timeline.zoomIn();
                break;
            case 'zoom-out':
                this.timeline.zoomOut();
                break;
            case 'fit-all':
                this.timeline.fitToEvents();
                break;
            case 'search':
                this.toggleSearchPanel();
                break;
            case 'bookmarks':
                this.toggleBookmarkPanel();
                break;
            case 'add-bookmark':
                this.addBookmark();
                break;
            case 'export-image':
                this.exportImage();
                break;
            case 'settings':
                this.showSettings();
                break;
        }
    }

    updateZoomDisplay() {
        const zoomPercent = Math.round(this.timeline.zoom * 100);
        const zoomValue = this.controlPanel.querySelector('.zoom-value');
        if (zoomValue) {
            zoomValue.textContent = `${zoomPercent}%`;
        }
    }

    updateMinimap() {
        const ctx = this.minimapCtx;
        const width = this.minimapCanvas.width;
        const height = this.minimapCanvas.height;

        // Clear
        ctx.fillStyle = '#0a0e27';
        ctx.fillRect(0, 0, width, height);

        // Draw all events
        const events = this.timeline.events;
        if (events.length === 0) return;

        const timestamps = events.map(e => e.timestamp);
        const minTimestamp = Math.min(...timestamps);
        const maxTimestamp = Math.max(...timestamps);
        const range = maxTimestamp - minTimestamp;

        // Draw events as vertical lines
        ctx.strokeStyle = '#16213e';
        ctx.lineWidth = 1;

        for (const event of events) {
            const x = ((event.timestamp - minTimestamp) / range) * width;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
        }

        // Draw viewport indicator
        const leftTimestamp = this.timeline.screenToTimestamp(0);
        const rightTimestamp = this.timeline.screenToTimestamp(this.timeline.options.width);

        const viewportLeft = ((leftTimestamp - minTimestamp) / range) * width;
        const viewportRight = ((rightTimestamp - minTimestamp) / range) * width;

        ctx.fillStyle = 'rgba(233, 69, 96, 0.3)';
        ctx.fillRect(viewportLeft, 0, viewportRight - viewportLeft, height);

        ctx.strokeStyle = '#e94560';
        ctx.lineWidth = 2;
        ctx.strokeRect(viewportLeft, 0, viewportRight - viewportLeft, height);
    }

    toggleSearchPanel() {
        if (this.searchPanel.style.display === 'none') {
            this.showSearchPanel();
        } else {
            this.hideSearchPanel();
        }
    }

    showSearchPanel() {
        this.searchPanel.style.display = 'block';
        this.hideBookmarkPanel();
        this.searchPanel.querySelector('.search-input').focus();

        // Announce to screen readers
        this.announceToScreenReader('Search panel opened');
    }

    hideSearchPanel() {
        this.searchPanel.style.display = 'none';
    }

    handleSearch(query) {
        const results = this.timeline.events.filter(event =>
            event.title.toLowerCase().includes(query.toLowerCase()) ||
            (event.description && event.description.toLowerCase().includes(query.toLowerCase()))
        );

        this.displaySearchResults(results);
    }

    displaySearchResults(results) {
        const container = this.searchPanel.querySelector('.search-results');

        if (results.length === 0) {
            container.innerHTML = '<div class="no-results">No events found</div>';
            return;
        }

        container.innerHTML = results.map(event => `
            <div class="search-result-item" data-timestamp="${event.timestamp}">
                <div class="result-title">${this.highlightText(event.title)}</div>
                <div class="result-date">${this.formatDate(event.timestamp)}</div>
            </div>
        `).join('');

        // Add click handlers
        container.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', () => {
                const timestamp = parseFloat(item.dataset.timestamp);
                this.timeline.goToTimestamp(timestamp, true);
                this.hideSearchPanel();
            });
        });

        // Announce results to screen readers
        this.announceToScreenReader(`Found ${results.length} events`);
    }

    highlightText(text) {
        const query = this.searchPanel.querySelector('.search-input').value;
        if (!query) return text;

        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    toggleBookmarkPanel() {
        if (this.bookmarkPanel.style.display === 'none') {
            this.showBookmarkPanel();
        } else {
            this.hideBookmarkPanel();
        }
    }

    showBookmarkPanel() {
        this.bookmarkPanel.style.display = 'block';
        this.hideSearchPanel();
        this.updateBookmarkList();

        this.announceToScreenReader('Bookmarks panel opened');
    }

    hideBookmarkPanel() {
        this.bookmarkPanel.style.display = 'none';
    }

    addBookmark() {
        const timestamp = this.timeline.centerTimestamp;
        const name = prompt('Enter bookmark name:');

        if (!name) return;

        const bookmark = {
            id: Date.now(),
            name: name,
            timestamp: timestamp,
            zoom: this.timeline.zoom
        };

        this.bookmarks.push(bookmark);
        this.saveBookmarks();
        this.updateBookmarkList();

        this.announceToScreenReader(`Bookmark "${name}" added`);
    }

    updateBookmarkList() {
        const container = this.bookmarkPanel.querySelector('.bookmark-list');

        if (this.bookmarks.length === 0) {
            container.innerHTML = '<div class="no-bookmarks">No bookmarks yet</div>';
            return;
        }

        container.innerHTML = this.bookmarks.map(bookmark => `
            <div class="bookmark-item" data-id="${bookmark.id}">
                <div class="bookmark-name">${bookmark.name}</div>
                <div class="bookmark-actions">
                    <button class="bookmark-goto" data-timestamp="${bookmark.timestamp}" data-zoom="${bookmark.zoom}">Go</button>
                    <button class="bookmark-delete" data-id="${bookmark.id}">Delete</button>
                </div>
            </div>
        `).join('');

        // Add event listeners
        container.querySelectorAll('.bookmark-goto').forEach(btn => {
            btn.addEventListener('click', () => {
                const timestamp = parseFloat(btn.dataset.timestamp);
                const zoom = parseFloat(btn.dataset.zoom);
                this.timeline.zoom = zoom;
                this.timeline.goToTimestamp(timestamp, true);
                this.hideBookmarkPanel();
            });
        });

        container.querySelectorAll('.bookmark-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id);
                this.deleteBookmark(id);
            });
        });
    }

    deleteBookmark(id) {
        this.bookmarks = this.bookmarks.filter(b => b.id !== id);
        this.saveBookmarks();
        this.updateBookmarkList();

        this.announceToScreenReader('Bookmark deleted');
    }

    saveBookmarks() {
        const key = `saga_timeline_bookmarks_${this.timeline.options.sagaId}`;
        localStorage.setItem(key, JSON.stringify(this.bookmarks));
    }

    loadBookmarks() {
        const key = `saga_timeline_bookmarks_${this.timeline.options.sagaId}`;
        const data = localStorage.getItem(key);

        if (data) {
            try {
                this.bookmarks = JSON.parse(data);
            } catch (e) {
                console.error('Failed to load bookmarks:', e);
                this.bookmarks = [];
            }
        }
    }

    async exportImage() {
        const canvas = this.timeline.useWebGPU ? this.timeline.canvas : this.timeline.canvas2d;

        try {
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = `saga-timeline-${Date.now()}.png`;
            a.click();

            URL.revokeObjectURL(url);

            this.announceToScreenReader('Timeline exported as image');
        } catch (error) {
            console.error('Failed to export image:', error);
            alert('Failed to export image');
        }
    }

    showSettings() {
        // TODO: Implement settings panel
        alert('Settings panel coming soon');
    }

    formatDate(timestamp) {
        // This should use the saga's calendar system
        // For now, use standard date formatting
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    announceToScreenReader(message) {
        // Create live region for screen reader announcements
        let liveRegion = document.getElementById('timeline-announcer');

        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.id = 'timeline-announcer';
            liveRegion.className = 'sr-only';
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(liveRegion);
        }

        liveRegion.textContent = message;
    }

    destroy() {
        if (this.tooltip) {
            this.tooltip.remove();
        }
        if (this.controlPanel) {
            this.controlPanel.remove();
        }
        if (this.minimap) {
            this.minimap.remove();
        }
        if (this.searchPanel) {
            this.searchPanel.remove();
        }
        if (this.bookmarkPanel) {
            this.bookmarkPanel.remove();
        }
    }
}

// Export for WordPress
if (typeof window !== 'undefined') {
    window.TimelineControls = TimelineControls;
}
