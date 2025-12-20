/**
 * Saga Manager Display - Search Component
 *
 * Handles live search functionality for saga entities
 *
 * @package SagaManagerDisplay
 */

(function() {
    'use strict';

    const { api, loading, errors, entityRenderer, paginationRenderer, utils, config } = window.SagaDisplay;

    /**
     * Search component class
     */
    class SagaSearch {
        constructor(container) {
            this.container = container;
            this.config = this.parseConfig();
            this.state = {
                query: '',
                type: '',
                saga: this.config.saga || '',
                page: 1,
                results: [],
                total: 0,
                loading: false
            };

            this.elements = this.cacheElements();
            this.bindEvents();

            // Show initial results if configured
            if (this.container.hasAttribute('data-initial-results')) {
                this.search();
            }
        }

        /**
         * Parse configuration from data attributes
         */
        parseConfig() {
            return {
                saga: this.container.dataset.saga || '',
                types: this.container.dataset.types || '',
                layout: this.container.dataset.layout || 'grid',
                perPage: parseInt(this.container.dataset.perPage, 10) || 12,
                semantic: this.container.dataset.semantic === 'true',
                liveSearch: this.container.dataset.liveSearch !== 'false',
                minChars: parseInt(this.container.dataset.minChars, 10) || 3,
                debounce: parseInt(this.container.dataset.debounce, 10) || 300
            };
        }

        /**
         * Cache DOM elements
         */
        cacheElements() {
            return {
                form: this.container.querySelector('.saga-search__form'),
                input: this.container.querySelector('.saga-search__input'),
                typeFilter: this.container.querySelector('.saga-search__filter--type'),
                sagaFilter: this.container.querySelector('.saga-search__filter--saga'),
                results: this.container.querySelector('.saga-search__results'),
                resultsGrid: this.container.querySelector('.saga-search__results-grid'),
                resultsCount: this.container.querySelector('.saga-search__results-count'),
                pagination: this.container.querySelector('.saga-search__pagination')
            };
        }

        /**
         * Bind event listeners
         */
        bindEvents() {
            // Form submission
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.search();
                });
            }

            // Live search
            if (this.config.liveSearch && this.elements.input) {
                const debouncedSearch = utils.debounce(() => {
                    if (this.state.query.length >= this.config.minChars || this.state.query.length === 0) {
                        this.state.page = 1;
                        this.search();
                    }
                }, this.config.debounce);

                this.elements.input.addEventListener('input', (e) => {
                    this.state.query = e.target.value.trim();
                    debouncedSearch();
                });
            }

            // Type filter
            if (this.elements.typeFilter) {
                this.elements.typeFilter.addEventListener('change', (e) => {
                    this.state.type = e.target.value;
                    this.state.page = 1;
                    this.search();
                });
            }

            // Saga filter
            if (this.elements.sagaFilter) {
                this.elements.sagaFilter.addEventListener('change', (e) => {
                    this.state.saga = e.target.value;
                    this.state.page = 1;
                    this.search();
                });
            }

            // Pagination (delegated)
            if (this.elements.pagination) {
                this.elements.pagination.addEventListener('click', (e) => {
                    const button = e.target.closest('[data-page]');
                    if (button && !button.disabled) {
                        this.state.page = parseInt(button.dataset.page, 10);
                        this.search();
                        this.scrollToResults();
                    }
                });
            }

            // Result card clicks (delegated)
            if (this.elements.resultsGrid) {
                this.elements.resultsGrid.addEventListener('click', (e) => {
                    const card = e.target.closest('.saga-entity');
                    if (card) {
                        const entityId = card.dataset.entityId;
                        this.container.dispatchEvent(new CustomEvent('sagaEntityClick', {
                            bubbles: true,
                            detail: { entityId }
                        }));
                    }
                });
            }
        }

        /**
         * Perform search
         */
        async search() {
            if (this.state.loading) return;

            this.state.loading = true;
            this.showLoading();

            try {
                const params = {
                    limit: this.config.perPage,
                    offset: (this.state.page - 1) * this.config.perPage
                };

                if (this.state.query) {
                    params.q = this.state.query;
                }

                if (this.state.type) {
                    params.type = this.state.type;
                }

                if (this.state.saga) {
                    params.saga = this.state.saga;
                }

                if (this.config.semantic) {
                    params.semantic = true;
                }

                const response = await api.searchEntities(this.state.query, params);

                this.state.results = response.data || [];
                this.state.total = response.meta?.total || this.state.results.length;

                this.renderResults();
            } catch (error) {
                console.error('Search error:', error);
                errors.show(this.elements.results, config.i18n.error);
            } finally {
                this.state.loading = false;
            }
        }

        /**
         * Show loading state
         */
        showLoading() {
            if (this.elements.resultsGrid) {
                loading.showSkeleton(this.elements.resultsGrid, this.config.perPage);
            }
        }

        /**
         * Render search results
         */
        renderResults() {
            if (!this.elements.resultsGrid) return;

            // Render count
            if (this.elements.resultsCount) {
                const totalText = this.state.total === 1
                    ? '1 result'
                    : `${this.state.total} results`;
                this.elements.resultsCount.textContent = totalText;
            }

            // Render results
            if (this.state.results.length === 0) {
                this.elements.resultsGrid.innerHTML = `
                    <div class="saga-search__no-results">
                        <div class="saga-search__no-results-icon">
                            <span class="dashicons dashicons-search"></span>
                        </div>
                        <p>${utils.escapeHtml(config.i18n.noResults)}</p>
                    </div>
                `;
            } else {
                this.elements.resultsGrid.innerHTML = this.state.results
                    .map(entity => entityRenderer.card(entity, {
                        showImage: true,
                        showType: true,
                        link: true
                    }))
                    .join('');
            }

            // Render pagination
            this.renderPagination();
        }

        /**
         * Render pagination
         */
        renderPagination() {
            if (!this.elements.pagination) return;

            const totalPages = Math.ceil(this.state.total / this.config.perPage);

            this.elements.pagination.innerHTML = paginationRenderer.render(
                this.state.page,
                totalPages
            );
        }

        /**
         * Scroll to results
         */
        scrollToResults() {
            const resultsTop = this.elements.results.getBoundingClientRect().top + window.scrollY;
            window.scrollTo({
                top: resultsTop - 100,
                behavior: 'smooth'
            });
        }

        /**
         * Reset search
         */
        reset() {
            this.state.query = '';
            this.state.type = '';
            this.state.page = 1;

            if (this.elements.input) {
                this.elements.input.value = '';
            }

            if (this.elements.typeFilter) {
                this.elements.typeFilter.value = '';
            }

            this.search();
        }

        /**
         * Get current state
         */
        getState() {
            return { ...this.state };
        }
    }

    /**
     * Initialize all search components
     */
    function init() {
        const searchContainers = document.querySelectorAll('.saga-search');

        searchContainers.forEach(container => {
            if (!container.sagaSearch) {
                container.sagaSearch = new SagaSearch(container);
            }
        });
    }

    // Export
    window.SagaDisplay.Search = SagaSearch;

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-initialize on dynamic content
    document.addEventListener('sagaDisplayReady', init);
})();
