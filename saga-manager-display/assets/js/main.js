/**
 * Saga Manager Display - Main JavaScript
 *
 * Vanilla JavaScript (no jQuery dependency)
 *
 * @package SagaManagerDisplay
 */

(function() {
    'use strict';

    /**
     * Global namespace for Saga Manager Display
     */
    window.SagaDisplay = window.SagaDisplay || {};

    /**
     * Configuration (injected via wp_localize_script)
     */
    const config = window.sagaDisplayConfig || {
        apiUrl: '/wp-json/saga/v1',
        nonce: '',
        i18n: {
            loading: 'Loading...',
            error: 'An error occurred',
            noResults: 'No results found',
            searchPlaceholder: 'Search entities...'
        }
    };

    /**
     * Utility: Debounce function
     * @param {Function} func Function to debounce
     * @param {number} wait Wait time in milliseconds
     * @returns {Function} Debounced function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Utility: Throttle function
     * @param {Function} func Function to throttle
     * @param {number} limit Limit in milliseconds
     * @returns {Function} Throttled function
     */
    function throttle(func, limit) {
        let inThrottle;
        return function executedFunction(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Utility: Escape HTML
     * @param {string} str String to escape
     * @returns {string} Escaped string
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * API Client
     */
    const api = {
        /**
         * Make an API request
         * @param {string} endpoint API endpoint
         * @param {Object} options Request options
         * @returns {Promise} API response
         */
        async request(endpoint, options = {}) {
            const url = new URL(`${config.apiUrl}/${endpoint}`, window.location.origin);

            if (options.params) {
                Object.entries(options.params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null && value !== '') {
                        url.searchParams.append(key, value);
                    }
                });
            }

            const response = await fetch(url.toString(), {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                    ...options.headers
                },
                body: options.body ? JSON.stringify(options.body) : undefined
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || `HTTP error ${response.status}`);
            }

            return response.json();
        },

        /**
         * Get entity by ID
         * @param {number} id Entity ID
         * @returns {Promise} Entity data
         */
        getEntity(id) {
            return this.request(`entities/${id}`);
        },

        /**
         * Search entities
         * @param {string} query Search query
         * @param {Object} filters Additional filters
         * @returns {Promise} Search results
         */
        searchEntities(query, filters = {}) {
            return this.request('entities/search', {
                params: { q: query, ...filters }
            });
        },

        /**
         * Get timeline
         * @param {string} saga Saga slug
         * @param {Object} params Additional parameters
         * @returns {Promise} Timeline data
         */
        getTimeline(saga, params = {}) {
            return this.request('timeline', {
                params: { saga, ...params }
            });
        },

        /**
         * Get relationships
         * @param {number} entityId Entity ID
         * @param {Object} params Additional parameters
         * @returns {Promise} Relationships data
         */
        getRelationships(entityId, params = {}) {
            return this.request(`entities/${entityId}/relationships`, {
                params
            });
        }
    };

    /**
     * Loading state helper
     */
    const loading = {
        /**
         * Show loading state
         * @param {HTMLElement} container Container element
         * @param {string} type Loading type (card, list, text)
         */
        show(container, type = 'card') {
            container.innerHTML = `
                <div class="saga-loading">
                    <div class="saga-loading__spinner"></div>
                    <span class="saga-loading__text">${escapeHtml(config.i18n.loading)}</span>
                </div>
            `;
        },

        /**
         * Show skeleton loading
         * @param {HTMLElement} container Container element
         * @param {number} count Number of skeletons
         */
        showSkeleton(container, count = 3) {
            let html = '';
            for (let i = 0; i < count; i++) {
                html += '<div class="saga-skeleton saga-skeleton--card"></div>';
            }
            container.innerHTML = html;
        },

        /**
         * Hide loading state
         * @param {HTMLElement} container Container element
         */
        hide(container) {
            const loader = container.querySelector('.saga-loading, .saga-skeleton');
            if (loader) {
                loader.remove();
            }
        }
    };

    /**
     * Error handling helper
     */
    const errors = {
        /**
         * Show error message
         * @param {HTMLElement} container Container element
         * @param {string} message Error message
         */
        show(container, message) {
            container.innerHTML = `
                <div class="saga-message saga-message--error">
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        },

        /**
         * Show warning message
         * @param {HTMLElement} container Container element
         * @param {string} message Warning message
         */
        showWarning(container, message) {
            container.innerHTML = `
                <div class="saga-message saga-message--warning">
                    <span>${escapeHtml(message)}</span>
                </div>
            `;
        }
    };

    /**
     * Entity renderer
     */
    const entityRenderer = {
        /**
         * Render entity card
         * @param {Object} entity Entity data
         * @param {Object} options Render options
         * @returns {string} HTML string
         */
        card(entity, options = {}) {
            const showImage = options.showImage !== false;
            const showType = options.showType !== false;
            const link = options.link !== false;

            const imageHtml = showImage && entity.image ? `
                <div class="saga-entity__image">
                    <img src="${escapeHtml(entity.image)}" alt="${escapeHtml(entity.canonical_name)}">
                </div>
            ` : '';

            const typeHtml = showType ? `
                <span class="saga-badge saga-badge--${escapeHtml(entity.entity_type)}">
                    ${escapeHtml(entity.entity_type)}
                </span>
            ` : '';

            const nameHtml = link && entity.url ? `
                <a href="${escapeHtml(entity.url)}">${escapeHtml(entity.canonical_name)}</a>
            ` : escapeHtml(entity.canonical_name);

            return `
                <article class="saga-entity saga-entity--card" data-entity-id="${entity.id}">
                    ${imageHtml}
                    <div class="saga-entity__content">
                        ${typeHtml}
                        <h3 class="saga-entity__name">${nameHtml}</h3>
                        ${entity.description ? `
                            <p class="saga-entity__description saga-line-clamp-2">
                                ${escapeHtml(entity.description)}
                            </p>
                        ` : ''}
                    </div>
                </article>
            `;
        },

        /**
         * Render entity compact
         * @param {Object} entity Entity data
         * @param {Object} options Render options
         * @returns {string} HTML string
         */
        compact(entity, options = {}) {
            return `
                <article class="saga-entity saga-entity--compact" data-entity-id="${entity.id}">
                    ${entity.image ? `
                        <div class="saga-entity__image">
                            <img src="${escapeHtml(entity.image)}" alt="${escapeHtml(entity.canonical_name)}">
                        </div>
                    ` : ''}
                    <div class="saga-entity__content">
                        <h4 class="saga-entity__name">${escapeHtml(entity.canonical_name)}</h4>
                        <span class="saga-badge saga-badge--${escapeHtml(entity.entity_type)}">
                            ${escapeHtml(entity.entity_type)}
                        </span>
                    </div>
                </article>
            `;
        },

        /**
         * Render entity list
         * @param {Array} entities Array of entities
         * @param {Object} options Render options
         * @returns {string} HTML string
         */
        list(entities, options = {}) {
            const layout = options.layout || 'grid';
            const renderer = options.style === 'compact' ? this.compact : this.card;

            const items = entities.map(entity => renderer.call(this, entity, options)).join('');

            return `
                <div class="saga-entity-list saga-entity-list--${layout}">
                    ${items}
                </div>
            `;
        }
    };

    /**
     * Pagination renderer
     */
    const paginationRenderer = {
        /**
         * Render pagination
         * @param {number} currentPage Current page
         * @param {number} totalPages Total pages
         * @param {Function} onPageChange Page change callback
         * @returns {string} HTML string
         */
        render(currentPage, totalPages, onPageChange) {
            if (totalPages <= 1) {
                return '';
            }

            let pages = [];
            const range = 2;

            // Always show first page
            pages.push(1);

            // Add ellipsis if needed
            if (currentPage > range + 2) {
                pages.push('...');
            }

            // Pages around current
            for (let i = Math.max(2, currentPage - range); i <= Math.min(totalPages - 1, currentPage + range); i++) {
                pages.push(i);
            }

            // Add ellipsis if needed
            if (currentPage < totalPages - range - 1) {
                pages.push('...');
            }

            // Always show last page
            if (totalPages > 1) {
                pages.push(totalPages);
            }

            const items = pages.map(page => {
                if (page === '...') {
                    return '<span class="saga-pagination__ellipsis">...</span>';
                }

                const isCurrent = page === currentPage;
                const classes = ['saga-pagination__item'];
                if (isCurrent) classes.push('saga-pagination__item--current');

                return `
                    <button type="button"
                            class="${classes.join(' ')}"
                            data-page="${page}"
                            ${isCurrent ? 'aria-current="page"' : ''}>
                        ${page}
                    </button>
                `;
            }).join('');

            return `
                <nav class="saga-pagination" aria-label="Pagination">
                    <button type="button"
                            class="saga-pagination__item saga-pagination__item--prev ${currentPage === 1 ? 'saga-pagination__item--disabled' : ''}"
                            data-page="${currentPage - 1}"
                            ${currentPage === 1 ? 'disabled' : ''}>
                        &laquo;
                    </button>
                    ${items}
                    <button type="button"
                            class="saga-pagination__item saga-pagination__item--next ${currentPage === totalPages ? 'saga-pagination__item--disabled' : ''}"
                            data-page="${currentPage + 1}"
                            ${currentPage === totalPages ? 'disabled' : ''}>
                        &raquo;
                    </button>
                </nav>
            `;
        }
    };

    /**
     * Initialize all components
     */
    function init() {
        // Dispatch custom event for extensions
        document.dispatchEvent(new CustomEvent('sagaDisplayReady', {
            detail: { api, config, debounce, throttle }
        }));
    }

    // Export to global namespace
    SagaDisplay.api = api;
    SagaDisplay.config = config;
    SagaDisplay.loading = loading;
    SagaDisplay.errors = errors;
    SagaDisplay.entityRenderer = entityRenderer;
    SagaDisplay.paginationRenderer = paginationRenderer;
    SagaDisplay.utils = { debounce, throttle, escapeHtml };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
