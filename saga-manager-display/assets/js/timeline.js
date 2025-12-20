/**
 * Saga Manager Display - Timeline Component
 *
 * Handles interactive timeline functionality
 *
 * @package SagaManagerDisplay
 */

(function() {
    'use strict';

    const { api, loading, errors, utils, config } = window.SagaDisplay;

    /**
     * Timeline component class
     */
    class SagaTimeline {
        constructor(container) {
            this.container = container;
            this.config = this.parseConfig();
            this.state = {
                events: [],
                expanded: new Set(),
                loading: false
            };

            this.elements = this.cacheElements();
            this.bindEvents();
            this.init();
        }

        /**
         * Parse configuration from data attributes
         */
        parseConfig() {
            return {
                saga: this.container.dataset.saga || '',
                layout: this.container.dataset.layout || 'vertical',
                limit: parseInt(this.container.dataset.limit, 10) || 20,
                order: this.container.dataset.order || 'asc',
                interactive: this.container.classList.contains('saga-timeline--interactive')
            };
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            return {
                track: this.container.querySelector('.saga-timeline__track'),
                events: this.container.querySelectorAll('.saga-timeline__event'),
                loadMoreBtn: this.container.querySelector('.saga-timeline__load-more')
            };
        }

        /**
         * Initialize component
         */
        init() {
            // Set up intersection observer for animations
            if (this.config.interactive && 'IntersectionObserver' in window) {
                this.setupScrollAnimation();
            }

            // Initialize horizontal scroll if needed
            if (this.config.layout === 'horizontal') {
                this.setupHorizontalScroll();
            }
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Event expansion
            this.elements.events.forEach(event => {
                const content = event.querySelector('.saga-timeline__content');
                if (content) {
                    content.addEventListener('click', (e) => {
                        if (!e.target.closest('a')) {
                            this.toggleEvent(event);
                        }
                    });

                    // Keyboard accessibility
                    content.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            this.toggleEvent(event);
                        }
                    });

                    // Make focusable
                    content.setAttribute('tabindex', '0');
                    content.setAttribute('role', 'button');
                }
            });

            // Load more button
            if (this.elements.loadMoreBtn) {
                this.elements.loadMoreBtn.addEventListener('click', () => {
                    this.loadMore();
                });
            }

            // Horizontal scroll with mouse wheel
            if (this.config.layout === 'horizontal' && this.elements.track) {
                this.elements.track.addEventListener('wheel', (e) => {
                    if (Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
                        e.preventDefault();
                        this.elements.track.scrollLeft += e.deltaY;
                    }
                }, { passive: false });
            }
        }

        /**
         * Set up scroll-triggered animations
         */
        setupScrollAnimation() {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('saga-timeline__event--visible');
                            observer.unobserve(entry.target);
                        }
                    });
                },
                {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                }
            );

            this.elements.events.forEach(event => {
                event.classList.add('saga-timeline__event--animate');
                observer.observe(event);
            });

            this.observer = observer;
        }

        /**
         * Set up horizontal scroll controls
         */
        setupHorizontalScroll() {
            const track = this.elements.track;
            if (!track) return;

            // Add scroll buttons
            const prevBtn = document.createElement('button');
            prevBtn.className = 'saga-timeline__scroll-btn saga-timeline__scroll-btn--prev';
            prevBtn.innerHTML = '&lsaquo;';
            prevBtn.setAttribute('aria-label', 'Scroll left');

            const nextBtn = document.createElement('button');
            nextBtn.className = 'saga-timeline__scroll-btn saga-timeline__scroll-btn--next';
            nextBtn.innerHTML = '&rsaquo;';
            nextBtn.setAttribute('aria-label', 'Scroll right');

            this.container.appendChild(prevBtn);
            this.container.appendChild(nextBtn);

            // Scroll handlers
            const scrollAmount = 300;

            prevBtn.addEventListener('click', () => {
                track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });

            nextBtn.addEventListener('click', () => {
                track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });

            // Update button visibility
            const updateButtons = () => {
                prevBtn.disabled = track.scrollLeft <= 0;
                nextBtn.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth;
            };

            track.addEventListener('scroll', utils.throttle(updateButtons, 100));
            updateButtons();
        }

        /**
         * Toggle event expansion
         */
        toggleEvent(eventElement) {
            const eventId = eventElement.dataset.eventId;
            const isExpanded = this.state.expanded.has(eventId);

            if (isExpanded) {
                this.state.expanded.delete(eventId);
                eventElement.classList.remove('saga-timeline__event--expanded');
            } else {
                this.state.expanded.add(eventId);
                eventElement.classList.add('saga-timeline__event--expanded');
            }

            // Dispatch custom event
            this.container.dispatchEvent(new CustomEvent('sagaTimelineEventToggle', {
                bubbles: true,
                detail: { eventId, expanded: !isExpanded }
            }));
        }

        /**
         * Load more events
         */
        async loadMore() {
            if (this.state.loading) return;

            this.state.loading = true;

            const btn = this.elements.loadMoreBtn;
            const originalText = btn.textContent;
            btn.textContent = config.i18n.loading;
            btn.disabled = true;

            try {
                const currentCount = this.elements.track.querySelectorAll('.saga-timeline__event').length;

                const response = await api.getTimeline(this.config.saga, {
                    limit: this.config.limit,
                    offset: currentCount,
                    order: this.config.order
                });

                const newEvents = response.data || [];

                if (newEvents.length > 0) {
                    this.appendEvents(newEvents);
                }

                // Hide button if no more events
                if (newEvents.length < this.config.limit) {
                    btn.style.display = 'none';
                }
            } catch (error) {
                console.error('Timeline load error:', error);
            } finally {
                this.state.loading = false;
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }

        /**
         * Append new events to timeline
         */
        appendEvents(events) {
            const track = this.elements.track;

            events.forEach(event => {
                const eventHtml = this.renderEvent(event);
                const temp = document.createElement('div');
                temp.innerHTML = eventHtml;
                const eventElement = temp.firstElementChild;

                track.appendChild(eventElement);

                // Animate in
                if (this.config.interactive) {
                    requestAnimationFrame(() => {
                        eventElement.classList.add('saga-timeline__event--visible');
                    });
                }
            });

            // Re-cache elements
            this.elements.events = this.container.querySelectorAll('.saga-timeline__event');
        }

        /**
         * Render a single event
         */
        renderEvent(event) {
            return `
                <div class="saga-timeline__event" data-event-id="${event.id}">
                    <div class="saga-timeline__marker"></div>
                    <div class="saga-timeline__content" tabindex="0" role="button">
                        <time class="saga-timeline__date">${utils.escapeHtml(event.canon_date)}</time>
                        <h4 class="saga-timeline__title">${utils.escapeHtml(event.title)}</h4>
                        ${event.description ? `
                            <p class="saga-timeline__description">${utils.escapeHtml(event.description)}</p>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        /**
         * Filter events by date range
         */
        filterByDateRange(start, end) {
            this.elements.events.forEach(event => {
                const date = event.querySelector('.saga-timeline__date')?.textContent || '';
                // Simple visibility toggle - actual date parsing would need saga-specific logic
                const visible = true; // Implement date comparison logic
                event.style.display = visible ? '' : 'none';
            });
        }

        /**
         * Jump to specific event
         */
        jumpToEvent(eventId) {
            const event = this.container.querySelector(`[data-event-id="${eventId}"]`);
            if (event) {
                event.scrollIntoView({ behavior: 'smooth', block: 'center' });
                event.classList.add('saga-timeline__event--highlight');
                setTimeout(() => {
                    event.classList.remove('saga-timeline__event--highlight');
                }, 2000);
            }
        }

        /**
         * Destroy component
         */
        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }
        }
    }

    /**
     * Initialize all timeline components
     */
    function init() {
        const timelineContainers = document.querySelectorAll('.saga-timeline--interactive');

        timelineContainers.forEach(container => {
            if (!container.sagaTimeline) {
                container.sagaTimeline = new SagaTimeline(container);
            }
        });
    }

    // Export
    window.SagaDisplay.Timeline = SagaTimeline;

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on dynamic content
    document.addEventListener('sagaDisplayReady', init);
})();
