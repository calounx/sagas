/**
 * Interactive Timeline Visualization
 *
 * Elegant timeline viewer using vis-timeline library for saga events.
 * Supports custom calendars, responsive design, and advanced filtering.
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

(function($) {
    'use strict';

    class SagaTimeline {
        constructor(container, options = {}) {
            this.container = container;
            this.timeline = null;
            this.items = new vis.DataSet();
            this.groups = new vis.DataSet();

            // Configuration
            this.config = {
                sagaId: options.sagaId || 1,
                entityId: options.entityId || null,
                type: options.type || 'linear', // linear, grouped, stacked
                height: options.height || 600,
                calendarType: options.calendarType || 'standard',
                calendarConfig: options.calendarConfig || {},
                dateRange: options.dateRange || null,
                filters: {
                    entityTypes: options.filters?.entityTypes || [],
                    importance: options.filters?.importance || [0, 100],
                    participants: options.filters?.participants || []
                },
                endpoint: options.endpoint || sagaTimelineData.ajaxurl,
                nonce: options.nonce || sagaTimelineData.nonce
            };

            this.eventTypes = {
                battle: { color: '#dc2626', icon: '⚔️' },
                birth: { color: '#16a34a', icon: '🎂' },
                death: { color: '#1f2937', icon: '💀' },
                founding: { color: '#9333ea', icon: '🏛️' },
                discovery: { color: '#0891b2', icon: '🔍' },
                treaty: { color: '#059669', icon: '📜' },
                coronation: { color: '#d97706', icon: '👑' },
                destruction: { color: '#be123c', icon: '💥' },
                meeting: { color: '#4f46e5', icon: '🤝' },
                journey: { color: '#7c3aed', icon: '🗺️' },
                default: { color: '#6b7280', icon: '📌' }
            };

            this.init();
        }

        /**
         * Initialize timeline
         */
        async init() {
            try {
                this.showLoader();
                await this.loadData();
                this.setupGroups();
                this.createTimeline();
                this.setupControls();
                this.setupFilters();
                this.setupEventHandlers();
                this.hideLoader();
            } catch (error) {
                console.error('Timeline initialization error:', error);
                this.showError('Failed to initialize timeline. Please try again.');
            }
        }

        /**
         * Load timeline data via AJAX
         */
        async loadData() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.config.endpoint,
                    type: 'POST',
                    data: {
                        action: 'saga_get_timeline_data',
                        nonce: this.config.nonce,
                        saga_id: this.config.sagaId,
                        entity_id: this.config.entityId,
                        date_range: this.config.dateRange
                    },
                    success: (response) => {
                        if (response.success) {
                            this.processData(response.data);
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Data load failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX error: ${error}`));
                    }
                });
            });
        }

        /**
         * Process and transform timeline data
         */
        processData(data) {
            const items = data.events.map(event => this.transformEvent(event));
            this.items.add(items);

            // Store metadata
            this.metadata = {
                calendarType: data.calendar_type,
                calendarConfig: data.calendar_config,
                totalEvents: data.events.length,
                dateRange: data.date_range
            };
        }

        /**
         * Transform event data to vis-timeline format
         */
        transformEvent(event) {
            const eventType = this.eventTypes[event.type] || this.eventTypes.default;
            const importance = parseInt(event.importance) || 50;

            // Calculate marker size based on importance
            const size = this.getMarkerSize(importance);

            return {
                id: event.id,
                content: this.createEventContent(event, eventType),
                start: this.parseDate(event.normalized_timestamp),
                type: 'point',
                className: `timeline-event event-${event.type} importance-${this.getImportanceClass(importance)}`,
                title: this.createTooltip(event),
                group: this.config.type === 'grouped' ? event.entity_type : null,
                style: `
                    background-color: ${eventType.color};
                    border-color: ${eventType.color};
                    font-size: ${size}px;
                `,
                // Store full event data for detail view
                eventData: event
            };
        }

        /**
         * Create event content HTML
         */
        createEventContent(event, eventType) {
            const icon = eventType.icon;
            const importance = parseInt(event.importance) || 50;
            const size = importance > 80 ? 'large' : importance > 50 ? 'medium' : 'small';

            return `
                <div class="timeline-event-marker ${size}" data-event-id="${event.id}">
                    <span class="event-icon">${icon}</span>
                    <span class="event-label">${this.truncate(event.title, 30)}</span>
                </div>
            `;
        }

        /**
         * Create hover tooltip
         */
        createTooltip(event) {
            const parts = [
                `<strong>${event.title}</strong>`,
                event.canon_date ? `Date: ${event.canon_date}` : '',
                event.description ? this.truncate(event.description, 100) : '',
                event.participants?.length ? `Participants: ${event.participants.slice(0, 3).join(', ')}` : '',
                event.location ? `Location: ${event.location}` : ''
            ].filter(Boolean);

            return parts.join('\n');
        }

        /**
         * Setup timeline groups for grouped view
         */
        setupGroups() {
            if (this.config.type !== 'grouped') return;

            const groupTypes = [
                { id: 'character', content: 'Characters', order: 1 },
                { id: 'location', content: 'Locations', order: 2 },
                { id: 'event', content: 'Events', order: 3 },
                { id: 'faction', content: 'Factions', order: 4 },
                { id: 'artifact', content: 'Artifacts', order: 5 },
                { id: 'concept', content: 'Concepts', order: 6 }
            ];

            this.groups.add(groupTypes);
        }

        /**
         * Create vis-timeline instance
         */
        createTimeline() {
            const options = {
                width: '100%',
                height: `${this.config.height}px`,
                stack: this.config.type === 'stacked',
                orientation: window.innerWidth < 768 ? 'top' : 'top',
                zoomMin: 1000 * 60 * 60 * 24, // 1 day
                zoomMax: 1000 * 60 * 60 * 24 * 365 * 1000, // 1000 years
                margin: {
                    item: {
                        horizontal: 10,
                        vertical: 10
                    }
                },
                showCurrentTime: false,
                showMajorLabels: true,
                showMinorLabels: true,
                cluster: {
                    maxItems: 5,
                    clusterCriteria: (firstItem, secondItem) => {
                        return Math.abs(firstItem.start - secondItem.start) < 1000 * 60 * 60 * 24 * 7; // 1 week
                    }
                },
                tooltip: {
                    followMouse: true,
                    overflowMethod: 'cap'
                },
                format: {
                    minorLabels: (date) => this.formatTimelineDate(date, 'minor'),
                    majorLabels: (date) => this.formatTimelineDate(date, 'major')
                },
                groupOrder: 'order',
                editable: false,
                selectable: true,
                multiselect: false,
                snap: null,
                verticalScroll: this.config.type === 'grouped',
                horizontalScroll: true,
                zoomKey: 'ctrlKey'
            };

            // Create timeline
            const timelineContainer = this.container.querySelector('.timeline-container');
            this.timeline = new vis.Timeline(
                timelineContainer,
                this.items,
                this.config.type === 'grouped' ? this.groups : null,
                options
            );

            // Fit timeline to show all events
            this.timeline.fit({ animation: { duration: 1000, easingFunction: 'easeInOutQuad' } });
        }

        /**
         * Setup timeline controls
         */
        setupControls() {
            const controls = this.container.querySelector('.timeline-controls');
            if (!controls) return;

            // Zoom controls
            controls.querySelector('.zoom-in')?.addEventListener('click', () => {
                this.timeline.zoomIn(0.5);
            });

            controls.querySelector('.zoom-out')?.addEventListener('click', () => {
                this.timeline.zoomOut(0.5);
            });

            controls.querySelector('.fit-window')?.addEventListener('click', () => {
                this.timeline.fit({ animation: true });
            });

            // Timeline type toggle
            controls.querySelector('.timeline-type')?.addEventListener('change', (e) => {
                this.changeTimelineType(e.target.value);
            });

            // Export controls
            controls.querySelector('.export-json')?.addEventListener('click', () => {
                this.exportData('json');
            });

            controls.querySelector('.export-csv')?.addEventListener('click', () => {
                this.exportData('csv');
            });

            controls.querySelector('.export-image')?.addEventListener('click', () => {
                this.exportImage();
            });
        }

        /**
         * Setup filter panel
         */
        setupFilters() {
            const filterPanel = this.container.querySelector('.timeline-filters');
            if (!filterPanel) return;

            // Entity type checkboxes
            filterPanel.querySelectorAll('.filter-entity-type').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.applyFilters();
                });
            });

            // Importance range slider
            const importanceSlider = filterPanel.querySelector('.filter-importance');
            if (importanceSlider) {
                importanceSlider.addEventListener('input', (e) => {
                    this.config.filters.importance = [0, parseInt(e.target.value)];
                    filterPanel.querySelector('.importance-value').textContent = e.target.value;
                    this.applyFilters();
                });
            }

            // Date range inputs
            filterPanel.querySelector('.filter-date-from')?.addEventListener('change', () => {
                this.applyFilters();
            });

            filterPanel.querySelector('.filter-date-to')?.addEventListener('change', () => {
                this.applyFilters();
            });

            // Reset filters button
            filterPanel.querySelector('.reset-filters')?.addEventListener('click', () => {
                this.resetFilters();
            });
        }

        /**
         * Setup event handlers
         */
        setupEventHandlers() {
            // Click event to show details
            this.timeline.on('select', (properties) => {
                if (properties.items.length > 0) {
                    const itemId = properties.items[0];
                    const item = this.items.get(itemId);
                    this.showEventDetails(item.eventData);
                }
            });

            // Double-click to zoom
            this.timeline.on('doubleClick', (properties) => {
                if (properties.time) {
                    this.timeline.moveTo(properties.time, {
                        animation: { duration: 500, easingFunction: 'easeInOutQuad' }
                    });
                    this.timeline.zoomIn(0.5);
                }
            });

            // Responsive handling
            window.addEventListener('resize', this.debounce(() => {
                this.handleResize();
            }, 250));

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (!this.container.contains(document.activeElement)) return;

                switch(e.key) {
                    case 'ArrowLeft':
                        this.timeline.move(-0.2);
                        break;
                    case 'ArrowRight':
                        this.timeline.move(0.2);
                        break;
                    case '+':
                    case '=':
                        this.timeline.zoomIn(0.5);
                        break;
                    case '-':
                        this.timeline.zoomOut(0.5);
                        break;
                    case 'Home':
                        this.timeline.fit();
                        break;
                }
            });
        }

        /**
         * Apply filters to timeline
         */
        applyFilters() {
            const filterPanel = this.container.querySelector('.timeline-filters');
            if (!filterPanel) return;

            // Get selected entity types
            const selectedTypes = Array.from(
                filterPanel.querySelectorAll('.filter-entity-type:checked')
            ).map(cb => cb.value);

            // Get importance threshold
            const minImportance = this.config.filters.importance[0];
            const maxImportance = this.config.filters.importance[1];

            // Get date range
            const dateFrom = filterPanel.querySelector('.filter-date-from')?.value;
            const dateTo = filterPanel.querySelector('.filter-date-to')?.value;

            // Filter items
            const allItems = this.items.get();
            allItems.forEach(item => {
                const event = item.eventData;
                let show = true;

                // Entity type filter
                if (selectedTypes.length > 0 && !selectedTypes.includes(event.entity_type)) {
                    show = false;
                }

                // Importance filter
                const importance = parseInt(event.importance) || 50;
                if (importance < minImportance || importance > maxImportance) {
                    show = false;
                }

                // Date range filter
                if (dateFrom) {
                    const fromDate = new Date(dateFrom);
                    if (item.start < fromDate) show = false;
                }
                if (dateTo) {
                    const toDate = new Date(dateTo);
                    if (item.start > toDate) show = false;
                }

                // Update item visibility
                this.items.update({
                    id: item.id,
                    className: show ? item.className : `${item.className} hidden`
                });
            });

            // Announce for screen readers
            this.announceForScreenReader(`Applied filters. Showing ${this.getVisibleItemsCount()} of ${allItems.length} events.`);
        }

        /**
         * Reset all filters
         */
        resetFilters() {
            const filterPanel = this.container.querySelector('.timeline-filters');
            if (!filterPanel) return;

            // Reset checkboxes
            filterPanel.querySelectorAll('.filter-entity-type').forEach(cb => {
                cb.checked = false;
            });

            // Reset importance slider
            const importanceSlider = filterPanel.querySelector('.filter-importance');
            if (importanceSlider) {
                importanceSlider.value = 100;
                filterPanel.querySelector('.importance-value').textContent = '100';
            }

            // Reset date inputs
            filterPanel.querySelector('.filter-date-from').value = '';
            filterPanel.querySelector('.filter-date-to').value = '';

            // Reset config
            this.config.filters = {
                entityTypes: [],
                importance: [0, 100],
                participants: []
            };

            // Show all items
            const allItems = this.items.get();
            allItems.forEach(item => {
                this.items.update({
                    id: item.id,
                    className: item.className.replace(' hidden', '')
                });
            });

            this.announceForScreenReader('Filters reset. Showing all events.');
        }

        /**
         * Change timeline type
         */
        changeTimelineType(type) {
            this.config.type = type;
            // Reinitialize timeline with new type
            this.timeline.destroy();
            this.setupGroups();
            this.createTimeline();
        }

        /**
         * Show event details modal
         */
        showEventDetails(event) {
            const modal = this.container.querySelector('.event-details-modal');
            if (!modal) return;

            const eventType = this.eventTypes[event.type] || this.eventTypes.default;

            const content = `
                <div class="event-details-header" style="border-left: 4px solid ${eventType.color}">
                    <span class="event-icon">${eventType.icon}</span>
                    <h3>${event.title}</h3>
                    <button class="close-modal" aria-label="Close">&times;</button>
                </div>
                <div class="event-details-body">
                    <div class="detail-row">
                        <strong>Date:</strong>
                        <span>${event.canon_date || 'Unknown'}</span>
                    </div>
                    ${event.description ? `
                        <div class="detail-row">
                            <strong>Description:</strong>
                            <p>${event.description}</p>
                        </div>
                    ` : ''}
                    ${event.participants?.length ? `
                        <div class="detail-row">
                            <strong>Participants:</strong>
                            <ul class="participants-list">
                                ${event.participants.map(p => `<li>${p}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                    ${event.location ? `
                        <div class="detail-row">
                            <strong>Location:</strong>
                            <span>${event.location}</span>
                        </div>
                    ` : ''}
                    <div class="detail-row">
                        <strong>Importance:</strong>
                        <div class="importance-bar">
                            <div class="importance-fill" style="width: ${event.importance}%; background-color: ${eventType.color}"></div>
                            <span>${event.importance}/100</span>
                        </div>
                    </div>
                    ${event.metadata ? `
                        <div class="detail-row">
                            <strong>Additional Info:</strong>
                            <pre>${JSON.stringify(event.metadata, null, 2)}</pre>
                        </div>
                    ` : ''}
                </div>
            `;

            modal.querySelector('.modal-content').innerHTML = content;
            modal.classList.add('active');

            // Close button
            modal.querySelector('.close-modal').addEventListener('click', () => {
                modal.classList.remove('active');
            });

            // Focus trap
            modal.querySelector('.close-modal').focus();
        }

        /**
         * Export timeline data
         */
        exportData(format) {
            const items = this.items.get().map(item => item.eventData);
            let content, filename, mimeType;

            switch (format) {
                case 'json':
                    content = JSON.stringify(items, null, 2);
                    filename = `saga-timeline-${this.config.sagaId}-${Date.now()}.json`;
                    mimeType = 'application/json';
                    break;

                case 'csv':
                    content = this.convertToCSV(items);
                    filename = `saga-timeline-${this.config.sagaId}-${Date.now()}.csv`;
                    mimeType = 'text/csv';
                    break;

                default:
                    return;
            }

            this.downloadFile(content, filename, mimeType);
        }

        /**
         * Export timeline as image
         */
        async exportImage() {
            // Use html2canvas or similar library
            if (typeof html2canvas === 'undefined') {
                alert('Image export requires html2canvas library');
                return;
            }

            const timelineContainer = this.container.querySelector('.timeline-container');

            try {
                const canvas = await html2canvas(timelineContainer, {
                    backgroundColor: '#ffffff',
                    scale: 2
                });

                canvas.toBlob((blob) => {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `saga-timeline-${this.config.sagaId}-${Date.now()}.png`;
                    a.click();
                    URL.revokeObjectURL(url);
                });
            } catch (error) {
                console.error('Image export error:', error);
                alert('Failed to export image');
            }
        }

        /**
         * Convert data to CSV format
         */
        convertToCSV(items) {
            const headers = ['ID', 'Title', 'Date', 'Type', 'Importance', 'Description', 'Participants', 'Location'];
            const rows = items.map(item => [
                item.id,
                `"${item.title.replace(/"/g, '""')}"`,
                item.canon_date || '',
                item.type,
                item.importance,
                `"${(item.description || '').replace(/"/g, '""')}"`,
                `"${(item.participants || []).join(', ')}"`,
                item.location || ''
            ]);

            return [headers, ...rows].map(row => row.join(','')).join('\n');
        }

        /**
         * Download file
         */
        downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        }

        /**
         * Handle responsive resize
         */
        handleResize() {
            const isMobile = window.innerWidth < 768;

            if (isMobile && !this.container.classList.contains('mobile-view')) {
                this.container.classList.add('mobile-view');
                // Switch to vertical timeline on mobile
                this.timeline.setOptions({
                    orientation: 'top',
                    height: '400px'
                });
            } else if (!isMobile && this.container.classList.contains('mobile-view')) {
                this.container.classList.remove('mobile-view');
                this.timeline.setOptions({
                    orientation: 'top',
                    height: `${this.config.height}px`
                });
            }

            this.timeline.redraw();
        }

        /**
         * Format date for timeline labels
         */
        formatTimelineDate(date, type) {
            if (this.metadata?.calendarType === 'bby') {
                return this.formatBBYDate(date, type);
            } else if (this.metadata?.calendarType === 'age_based') {
                return this.formatAgeBasedDate(date, type);
            }

            // Standard date formatting
            const options = type === 'major'
                ? { year: 'numeric' }
                : { year: 'numeric', month: 'short' };
            return date.toLocaleDateString('en-US', options);
        }

        /**
         * Format BBY (Before Battle of Yavin) date
         */
        formatBBYDate(date, type) {
            const epochYear = this.metadata?.calendarConfig?.epoch_year || 1977;
            const year = date.getFullYear();
            const diff = epochYear - year;

            if (diff > 0) {
                return `${diff} BBY`;
            } else if (diff < 0) {
                return `${Math.abs(diff)} ABY`;
            }
            return '0 BY';
        }

        /**
         * Format age-based date (e.g., First Age, Second Age)
         */
        formatAgeBasedDate(date, type) {
            const ages = this.metadata?.calendarConfig?.ages || [];
            const timestamp = date.getTime() / 1000;

            for (const age of ages) {
                if (timestamp >= age.start && timestamp <= age.end) {
                    return `${age.name} ${Math.floor((timestamp - age.start) / 31536000)}`;
                }
            }

            return 'Unknown Age';
        }

        /**
         * Parse date from timestamp
         */
        parseDate(timestamp) {
            if (typeof timestamp === 'string') {
                return new Date(timestamp);
            }
            return new Date(timestamp * 1000); // Assume Unix timestamp
        }

        /**
         * Get marker size based on importance
         */
        getMarkerSize(importance) {
            if (importance > 80) return 24;
            if (importance > 50) return 18;
            return 14;
        }

        /**
         * Get importance CSS class
         */
        getImportanceClass(importance) {
            if (importance > 80) return 'critical';
            if (importance > 50) return 'high';
            if (importance > 25) return 'medium';
            return 'low';
        }

        /**
         * Get visible items count
         */
        getVisibleItemsCount() {
            return this.items.get().filter(item =>
                !item.className.includes('hidden')
            ).length;
        }

        /**
         * Truncate text
         */
        truncate(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        }

        /**
         * Debounce function
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Show loader
         */
        showLoader() {
            const loader = this.container.querySelector('.timeline-loader');
            if (loader) loader.classList.add('active');
        }

        /**
         * Hide loader
         */
        hideLoader() {
            const loader = this.container.querySelector('.timeline-loader');
            if (loader) loader.classList.remove('active');
        }

        /**
         * Show error message
         */
        showError(message) {
            const errorContainer = this.container.querySelector('.timeline-error');
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.classList.add('active');
            }
        }

        /**
         * Announce for screen readers
         */
        announceForScreenReader(message) {
            const announcer = document.querySelector('.sr-announcer');
            if (announcer) {
                announcer.textContent = message;
            }
        }

        /**
         * Destroy timeline instance
         */
        destroy() {
            if (this.timeline) {
                this.timeline.destroy();
                this.timeline = null;
            }
            this.items.clear();
            this.groups.clear();
        }
    }

    // jQuery plugin wrapper
    $.fn.sagaTimeline = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('sagaTimeline');

            if (!instance) {
                instance = new SagaTimeline(this, options);
                $this.data('sagaTimeline', instance);
            }

            return instance;
        });
    };

    // Auto-initialize on page load
    $(document).ready(function() {
        $('.saga-timeline-wrapper').each(function() {
            const $wrapper = $(this);
            const options = {
                sagaId: $wrapper.data('saga-id'),
                entityId: $wrapper.data('entity-id'),
                type: $wrapper.data('timeline-type') || 'linear',
                height: $wrapper.data('height') || 600,
                calendarType: $wrapper.data('calendar-type'),
                calendarConfig: $wrapper.data('calendar-config')
            };

            $wrapper.sagaTimeline(options);
        });
    });

    // Export to window
    window.SagaTimeline = SagaTimeline;

})(jQuery);
