/**
 * Infinite Scroll with AJAX Pagination
 *
 * Automatically loads more entities when scrolling near bottom
 * Integrates with masonry layout for smooth item addition
 *
 * @package SagaTheme
 * @version 1.0.0
 */

(function () {
    'use strict';

    /**
     * Infinite Scroll Manager Class
     */
    class SagaInfiniteScroll {
        constructor(container, options = {}) {
            this.container = container;
            this.options = {
                threshold: 300, // Load when 300px from bottom
                ajaxAction: 'saga_load_more',
                perPage: 12,
                debounceDelay: 100,
                enableLoadMoreButton: true,
                ...options
            };

            this.currentPage = 1;
            this.isLoading = false;
            this.hasMore = true;
            this.observer = null;
            this.loadMoreButton = null;
            this.loadingElement = null;
            this.endElement = null;
            this.scrollTimer = null;
            this.masonryInstance = null;

            this.init();
        }

        /**
         * Initialize infinite scroll
         */
        init() {
            this.createElements();
            this.setupIntersectionObserver();
            this.setupEventListeners();
            this.getMasonryInstance();

            // Get initial page from URL or data attribute
            const urlParams = new URLSearchParams(window.location.search);
            const pageParam = urlParams.get('page');
            if (pageParam) {
                this.currentPage = parseInt(pageParam, 10) || 1;
            }

            this.updateURL(this.currentPage, false);
        }

        /**
         * Create loading and end-of-content elements
         */
        createElements() {
            // Create loading element
            this.loadingElement = document.createElement('div');
            this.loadingElement.className = 'saga-masonry-loading';
            this.loadingElement.style.display = 'none';
            this.loadingElement.innerHTML = `
                <div class="saga-masonry-loading__spinner"></div>
                <span class="saga-masonry-loading__text">Loading more entities...</span>
            `;

            // Create end-of-content element
            this.endElement = document.createElement('div');
            this.endElement.className = 'saga-masonry-end';
            this.endElement.style.display = 'none';
            this.endElement.innerHTML = `
                <div class="saga-masonry-end__icon">✨</div>
                <h3 class="saga-masonry-end__title">You've reached the end</h3>
                <p class="saga-masonry-end__message">No more entities to display</p>
            `;

            // Create load more button (accessibility fallback)
            if (this.options.enableLoadMoreButton) {
                const buttonWrapper = document.createElement('div');
                buttonWrapper.className = 'saga-masonry-load-more';
                buttonWrapper.style.display = 'none';

                this.loadMoreButton = document.createElement('button');
                this.loadMoreButton.className = 'saga-masonry-load-more__button';
                this.loadMoreButton.type = 'button';
                this.loadMoreButton.innerHTML = `
                    <span>Load More</span>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                `;

                buttonWrapper.appendChild(this.loadMoreButton);
                this.container.parentNode.insertBefore(buttonWrapper, this.container.nextSibling);
            }

            // Insert elements after container
            this.container.parentNode.insertBefore(this.loadingElement, this.container.nextSibling);
            this.container.parentNode.insertBefore(this.endElement, this.container.nextSibling);
        }

        /**
         * Setup Intersection Observer for automatic loading
         */
        setupIntersectionObserver() {
            // Create sentinel element at the bottom
            const sentinel = document.createElement('div');
            sentinel.className = 'saga-masonry-sentinel';
            sentinel.style.height = '1px';
            this.container.parentNode.insertBefore(sentinel, this.loadingElement);

            // Observer options
            const observerOptions = {
                root: null,
                rootMargin: `${this.options.threshold}px`,
                threshold: 0
            };

            // Create observer
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !this.isLoading && this.hasMore) {
                        this.loadMore();
                    }
                });
            }, observerOptions);

            // Start observing
            this.observer.observe(sentinel);
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Load more button click
            if (this.loadMoreButton) {
                this.loadMoreButton.addEventListener('click', () => {
                    this.loadMore();
                });
            }

            // Handle browser back/forward buttons
            window.addEventListener('popstate', (event) => {
                if (event.state && event.state.page) {
                    this.currentPage = event.state.page;
                }
            });

            // Handle page visibility (pause when hidden)
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.isLoading = false;
                }
            });
        }

        /**
         * Get masonry instance from container
         */
        getMasonryInstance() {
            if (this.container.sagaMasonry) {
                this.masonryInstance = this.container.sagaMasonry;
            } else {
                // Wait for masonry to initialize
                this.container.addEventListener('sagaMasonry:masonryLoaded', () => {
                    this.masonryInstance = this.container.sagaMasonry;
                });
            }
        }

        /**
         * Load more entities via AJAX
         */
        async loadMore() {
            if (this.isLoading || !this.hasMore) {
                return;
            }

            this.isLoading = true;
            this.currentPage++;

            this.showLoading();

            try {
                const data = await this.fetchEntities(this.currentPage);

                if (data.success) {
                    this.handleLoadSuccess(data.data);
                } else {
                    this.handleLoadError(data.message || 'Failed to load entities');
                }
            } catch (error) {
                this.handleLoadError(error.message);
            } finally {
                this.isLoading = false;
                this.hideLoading();
            }
        }

        /**
         * Fetch entities from server
         */
        async fetchEntities(page) {
            const formData = new FormData();
            formData.append('action', this.options.ajaxAction);
            formData.append('nonce', sagaInfiniteScrollData.nonce);
            formData.append('page', page);
            formData.append('per_page', this.options.perPage);

            // Add filters from data attributes or form
            const filters = this.getFilters();
            Object.keys(filters).forEach(key => {
                formData.append(key, filters[key]);
            });

            const response = await fetch(sagaInfiniteScrollData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        }

        /**
         * Get active filters
         */
        getFilters() {
            const filters = {};

            // Get from data attributes
            if (this.container.dataset.sagaId) {
                filters.saga_id = this.container.dataset.sagaId;
            }

            if (this.container.dataset.entityType) {
                filters.entity_type = this.container.dataset.entityType;
            }

            if (this.container.dataset.orderby) {
                filters.orderby = this.container.dataset.orderby;
            }

            // Get from filter form if exists
            const filterForm = document.querySelector('.saga-filter-form');
            if (filterForm) {
                const formData = new FormData(filterForm);
                formData.forEach((value, key) => {
                    if (value) {
                        filters[key] = value;
                    }
                });
            }

            return filters;
        }

        /**
         * Handle successful load
         */
        handleLoadSuccess(data) {
            if (!data.html || data.html.trim() === '') {
                this.hasMore = false;
                this.showEndMessage();
                return;
            }

            // Parse HTML and create elements
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.html;
            const newItems = Array.from(tempDiv.children);

            // Add items to masonry grid
            if (this.masonryInstance) {
                this.masonryInstance.addItems(newItems);
            } else {
                // Fallback: just append items
                newItems.forEach(item => this.container.appendChild(item));
            }

            // Update state
            this.hasMore = data.has_more;
            this.updateURL(this.currentPage);

            // Show end message if no more items
            if (!this.hasMore) {
                this.showEndMessage();
            }

            // Announce to screen readers
            this.announceToScreenReaders(`Loaded ${newItems.length} more entities. Page ${this.currentPage} of ${data.total_pages}.`);

            // Dispatch custom event
            this.dispatchEvent('itemsLoaded', {
                page: this.currentPage,
                items: newItems,
                hasMore: this.hasMore
            });
        }

        /**
         * Handle load error
         */
        handleLoadError(message) {
            console.error('[Saga Infinite Scroll] Load error:', message);

            // Revert page number
            this.currentPage--;

            // Show error message
            this.showErrorMessage(message);

            // Dispatch error event
            this.dispatchEvent('loadError', { message });
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            if (this.loadingElement) {
                this.loadingElement.style.display = 'flex';
            }

            if (this.loadMoreButton) {
                this.loadMoreButton.disabled = true;
                this.loadMoreButton.innerHTML = '<span>Loading...</span>';
            }
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            if (this.loadingElement) {
                this.loadingElement.style.display = 'none';
            }

            if (this.loadMoreButton && this.hasMore) {
                this.loadMoreButton.disabled = false;
                this.loadMoreButton.innerHTML = `
                    <span>Load More</span>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                `;
            }
        }

        /**
         * Show end-of-content message
         */
        showEndMessage() {
            if (this.endElement) {
                this.endElement.style.display = 'block';
            }

            if (this.loadMoreButton) {
                this.loadMoreButton.parentNode.style.display = 'none';
            }

            // Disconnect observer
            if (this.observer) {
                this.observer.disconnect();
            }
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'saga-masonry-error';
            errorDiv.innerHTML = `
                <p>Error loading entities: ${message}</p>
                <button type="button" onclick="this.parentNode.remove()">Dismiss</button>
            `;

            this.container.parentNode.insertBefore(errorDiv, this.loadingElement);

            setTimeout(() => errorDiv.remove(), 5000);
        }

        /**
         * Update URL with current page
         */
        updateURL(page, pushState = true) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);

            if (pushState) {
                window.history.pushState({ page }, '', url);
            } else {
                window.history.replaceState({ page }, '', url);
            }
        }

        /**
         * Announce to screen readers
         */
        announceToScreenReaders(message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'sr-only';
            announcement.textContent = message;

            document.body.appendChild(announcement);

            setTimeout(() => announcement.remove(), 1000);
        }

        /**
         * Dispatch custom event
         */
        dispatchEvent(name, detail = {}) {
            const event = new CustomEvent(`sagaInfiniteScroll:${name}`, {
                bubbles: true,
                detail
            });
            this.container.dispatchEvent(event);
        }

        /**
         * Destroy infinite scroll
         */
        destroy() {
            if (this.observer) {
                this.observer.disconnect();
            }

            if (this.loadMoreButton) {
                this.loadMoreButton.removeEventListener('click', this.loadMore);
            }

            clearTimeout(this.scrollTimer);
        }
    }

    /**
     * Initialize infinite scroll on DOM ready
     */
    function initInfiniteScroll() {
        const containers = document.querySelectorAll('.saga-masonry-grid[data-infinite-scroll="true"]');

        if (containers.length === 0) {
            return;
        }

        containers.forEach(container => {
            // Store infinite scroll instance on the element
            container.sagaInfiniteScroll = new SagaInfiniteScroll(container, {
                perPage: parseInt(container.dataset.perPage) || 12,
                threshold: parseInt(container.dataset.threshold) || 300
            });
        });
    }

    /**
     * Auto-initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInfiniteScroll);
    } else {
        initInfiniteScroll();
    }

    /**
     * Expose class globally for external access
     */
    window.SagaInfiniteScroll = SagaInfiniteScroll;

})();
