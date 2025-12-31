/**
 * Saga Manager - Smart Autocomplete Search
 *
 * Features:
 * - Real-time search with 300ms debouncing
 * - Grouped results by entity type
 * - Keyboard navigation (Arrow keys, Enter, Escape)
 * - Recent searches history (localStorage)
 * - Accessibility support (ARIA attributes)
 * - Mobile-responsive
 * - Click outside to close
 *
 * @package SagaTheme
 */

(function () {
    'use strict';

    /**
     * Autocomplete Search Class
     */
    class SagaAutocomplete {
        constructor(inputElement, options = {}) {
            this.input = inputElement;
            this.container = null;
            this.dropdown = null;
            this.debounceTimer = null;
            this.currentFocus = -1;
            this.cache = new Map();
            this.recentSearches = this.loadRecentSearches();

            // Configuration
            this.config = {
                debounceDelay: options.debounceDelay || 300,
                minChars: options.minChars || 2,
                maxResults: options.maxResults || 10,
                maxRecentSearches: options.maxRecentSearches || 10,
                sagaId: options.sagaId || null,
                ajaxUrl: options.ajaxUrl || window.sagaAutocomplete?.ajaxUrl || '/wp-admin/admin-ajax.php',
                nonce: options.nonce || window.sagaAutocomplete?.nonce || '',
                ...options
            };

            this.init();
        }

        /**
         * Initialize autocomplete
         */
        init() {
            this.createDropdown();
            this.attachEventListeners();
        }

        /**
         * Create dropdown container
         */
        createDropdown() {
            // Create container wrapper
            this.container = document.createElement('div');
            this.container.className = 'saga-autocomplete';
            this.container.setAttribute('role', 'combobox');
            this.container.setAttribute('aria-expanded', 'false');
            this.container.setAttribute('aria-haspopup', 'listbox');
            this.container.setAttribute('aria-owns', 'saga-autocomplete-dropdown');

            // Create dropdown
            this.dropdown = document.createElement('div');
            this.dropdown.id = 'saga-autocomplete-dropdown';
            this.dropdown.className = 'saga-autocomplete__dropdown';
            this.dropdown.setAttribute('role', 'listbox');
            this.dropdown.setAttribute('aria-label', 'Search suggestions');
            this.dropdown.style.display = 'none';

            // Wrap input and insert dropdown
            this.input.parentNode.insertBefore(this.container, this.input);
            this.container.appendChild(this.input);
            this.container.appendChild(this.dropdown);

            // Update input ARIA attributes
            this.input.setAttribute('role', 'searchbox');
            this.input.setAttribute('aria-autocomplete', 'list');
            this.input.setAttribute('aria-controls', 'saga-autocomplete-dropdown');
            this.input.setAttribute('autocomplete', 'off');
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            // Input events
            this.input.addEventListener('input', this.handleInput.bind(this));
            this.input.addEventListener('keydown', this.handleKeydown.bind(this));
            this.input.addEventListener('focus', this.handleFocus.bind(this));

            // Click outside to close
            document.addEventListener('click', this.handleClickOutside.bind(this));

            // Dropdown delegation
            this.dropdown.addEventListener('click', this.handleDropdownClick.bind(this));
            this.dropdown.addEventListener('mouseover', this.handleMouseOver.bind(this));
        }

        /**
         * Handle input changes (with debouncing)
         */
        handleInput(event) {
            const query = event.target.value.trim();

            clearTimeout(this.debounceTimer);

            if (query.length < this.config.minChars) {
                this.hideDropdown();
                return;
            }

            // Show loading state
            this.showLoading();

            // Debounce search
            this.debounceTimer = setTimeout(() => {
                this.performSearch(query);
            }, this.config.debounceDelay);
        }

        /**
         * Handle keyboard navigation
         */
        handleKeydown(event) {
            const items = this.dropdown.querySelectorAll('.saga-autocomplete__item');

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.currentFocus++;
                    this.setActiveFocus(items);
                    break;

                case 'ArrowUp':
                    event.preventDefault();
                    this.currentFocus--;
                    this.setActiveFocus(items);
                    break;

                case 'Enter':
                    event.preventDefault();
                    if (this.currentFocus > -1 && items[this.currentFocus]) {
                        items[this.currentFocus].click();
                    } else {
                        // Submit form
                        this.input.closest('form')?.submit();
                    }
                    break;

                case 'Escape':
                    this.hideDropdown();
                    break;

                case 'Tab':
                    // Allow tab navigation
                    this.hideDropdown();
                    break;
            }
        }

        /**
         * Handle focus event
         */
        handleFocus(event) {
            const query = event.target.value.trim();

            if (query.length >= this.config.minChars) {
                // Show cached results or perform search
                if (this.cache.has(query)) {
                    this.renderResults(this.cache.get(query), query);
                } else {
                    this.performSearch(query);
                }
            } else if (this.recentSearches.length > 0) {
                // Show recent searches
                this.showRecentSearches();
            }
        }

        /**
         * Handle click outside
         */
        handleClickOutside(event) {
            if (!this.container.contains(event.target)) {
                this.hideDropdown();
            }
        }

        /**
         * Handle dropdown clicks
         */
        handleDropdownClick(event) {
            const item = event.target.closest('.saga-autocomplete__item');

            if (item) {
                const url = item.dataset.url;
                const title = item.dataset.title;

                if (url) {
                    // Save to recent searches
                    this.addRecentSearch(title, url);

                    // Navigate to URL
                    window.location.href = url;
                }
            }

            // Handle recent search clear
            const clearBtn = event.target.closest('.saga-autocomplete__clear-recent');
            if (clearBtn) {
                event.preventDefault();
                this.clearRecentSearches();
            }
        }

        /**
         * Handle mouse over items
         */
        handleMouseOver(event) {
            const item = event.target.closest('.saga-autocomplete__item');

            if (item) {
                const items = this.dropdown.querySelectorAll('.saga-autocomplete__item');
                items.forEach((el, index) => {
                    el.classList.remove('saga-autocomplete__item--active');
                    if (el === item) {
                        this.currentFocus = index;
                    }
                });
                item.classList.add('saga-autocomplete__item--active');
            }
        }

        /**
         * Set active focus on keyboard navigation
         */
        setActiveFocus(items) {
            if (!items.length) return;

            // Remove all active classes
            items.forEach(item => item.classList.remove('saga-autocomplete__item--active'));

            // Loop around
            if (this.currentFocus >= items.length) this.currentFocus = 0;
            if (this.currentFocus < 0) this.currentFocus = items.length - 1;

            // Add active class
            items[this.currentFocus].classList.add('saga-autocomplete__item--active');
            items[this.currentFocus].setAttribute('aria-selected', 'true');

            // Scroll into view
            items[this.currentFocus].scrollIntoView({
                block: 'nearest',
                behavior: 'smooth'
            });
        }

        /**
         * Perform AJAX search
         */
        async performSearch(query) {
            try {
                const formData = new FormData();
                formData.append('action', 'saga_autocomplete_search');
                formData.append('saga_autocomplete_nonce', this.config.nonce);
                formData.append('q', query);
                formData.append('limit', this.config.maxResults);

                if (this.config.sagaId) {
                    formData.append('saga_id', this.config.sagaId);
                }

                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    // Cache results
                    this.cache.set(query, data.data);

                    // Render results
                    this.renderResults(data.data, query);
                } else {
                    this.showError(data.data?.message || 'Search failed');
                }

            } catch (error) {
                console.error('Autocomplete search error:', error);
                this.showError('Network error occurred');
            }
        }

        /**
         * Render search results
         */
        renderResults(data, query) {
            if (!data.grouped || Object.keys(data.grouped).length === 0) {
                this.showNoResults();
                return;
            }

            let html = '';

            // Group results by entity type
            Object.entries(data.grouped).forEach(([type, items]) => {
                if (items.length === 0) return;

                const typeLabel = items[0].type_label || this.capitalizeType(type);

                html += `
                    <div class="saga-autocomplete__group" role="group" aria-labelledby="saga-ac-group-${type}">
                        <div class="saga-autocomplete__group-header" id="saga-ac-group-${type}">
                            <span class="saga-autocomplete__group-icon">${items[0].type_icon || '📄'}</span>
                            <span class="saga-autocomplete__group-title">${this.escapeHtml(typeLabel)}</span>
                        </div>
                        <div class="saga-autocomplete__group-items">
                            ${items.map(item => this.renderItem(item)).join('')}
                        </div>
                    </div>
                `;
            });

            this.dropdown.innerHTML = html;
            this.showDropdown();
            this.currentFocus = -1;
        }

        /**
         * Render single autocomplete item
         */
        renderItem(item) {
            return `
                <div class="saga-autocomplete__item"
                     role="option"
                     aria-selected="false"
                     data-url="${this.escapeHtml(item.url)}"
                     data-title="${this.escapeHtml(item.title)}"
                     tabindex="-1">
                    <div class="saga-autocomplete__item-content">
                        <div class="saga-autocomplete__item-title">${item.title_highlighted}</div>
                        ${item.excerpt_highlighted ? `<div class="saga-autocomplete__item-excerpt">${item.excerpt_highlighted}</div>` : ''}
                    </div>
                    ${item.importance_score > 0 ? `<div class="saga-autocomplete__item-badge">${item.importance_score}</div>` : ''}
                </div>
            `;
        }

        /**
         * Show recent searches
         */
        showRecentSearches() {
            if (this.recentSearches.length === 0) return;

            let html = `
                <div class="saga-autocomplete__group" role="group" aria-labelledby="saga-ac-group-recent">
                    <div class="saga-autocomplete__group-header" id="saga-ac-group-recent">
                        <span class="saga-autocomplete__group-icon">🕐</span>
                        <span class="saga-autocomplete__group-title">Recent Searches</span>
                        <button type="button" class="saga-autocomplete__clear-recent" aria-label="Clear recent searches">Clear</button>
                    </div>
                    <div class="saga-autocomplete__group-items">
                        ${this.recentSearches.map(search => `
                            <div class="saga-autocomplete__item saga-autocomplete__item--recent"
                                 role="option"
                                 aria-selected="false"
                                 data-url="${this.escapeHtml(search.url)}"
                                 data-title="${this.escapeHtml(search.title)}"
                                 tabindex="-1">
                                <div class="saga-autocomplete__item-content">
                                    <div class="saga-autocomplete__item-title">${this.escapeHtml(search.title)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            this.dropdown.innerHTML = html;
            this.showDropdown();
        }

        /**
         * Show loading state
         */
        showLoading() {
            this.dropdown.innerHTML = `
                <div class="saga-autocomplete__loading">
                    <div class="saga-autocomplete__spinner"></div>
                    <span>Searching...</span>
                </div>
            `;
            this.showDropdown();
        }

        /**
         * Show no results message
         */
        showNoResults() {
            this.dropdown.innerHTML = `
                <div class="saga-autocomplete__empty">
                    <div class="saga-autocomplete__empty-icon">🔍</div>
                    <div class="saga-autocomplete__empty-text">No results found</div>
                </div>
            `;
            this.showDropdown();
        }

        /**
         * Show error message
         */
        showError(message) {
            this.dropdown.innerHTML = `
                <div class="saga-autocomplete__error">
                    <div class="saga-autocomplete__error-icon">⚠️</div>
                    <div class="saga-autocomplete__error-text">${this.escapeHtml(message)}</div>
                </div>
            `;
            this.showDropdown();
        }

        /**
         * Show dropdown
         */
        showDropdown() {
            this.dropdown.style.display = 'block';
            this.container.setAttribute('aria-expanded', 'true');
        }

        /**
         * Hide dropdown
         */
        hideDropdown() {
            this.dropdown.style.display = 'none';
            this.container.setAttribute('aria-expanded', 'false');
            this.currentFocus = -1;

            // Remove all active states
            const items = this.dropdown.querySelectorAll('.saga-autocomplete__item');
            items.forEach(item => {
                item.classList.remove('saga-autocomplete__item--active');
                item.setAttribute('aria-selected', 'false');
            });
        }

        /**
         * Load recent searches from localStorage
         */
        loadRecentSearches() {
            try {
                const stored = localStorage.getItem('saga_recent_searches');
                return stored ? JSON.parse(stored) : [];
            } catch (error) {
                console.error('Failed to load recent searches:', error);
                return [];
            }
        }

        /**
         * Save recent searches to localStorage
         */
        saveRecentSearches() {
            try {
                localStorage.setItem('saga_recent_searches', JSON.stringify(this.recentSearches));
            } catch (error) {
                console.error('Failed to save recent searches:', error);
            }
        }

        /**
         * Add search to recent history
         */
        addRecentSearch(title, url) {
            // Remove if already exists
            this.recentSearches = this.recentSearches.filter(s => s.url !== url);

            // Add to beginning
            this.recentSearches.unshift({ title, url });

            // Limit to max
            if (this.recentSearches.length > this.config.maxRecentSearches) {
                this.recentSearches = this.recentSearches.slice(0, this.config.maxRecentSearches);
            }

            this.saveRecentSearches();
        }

        /**
         * Clear recent searches
         */
        clearRecentSearches() {
            this.recentSearches = [];
            this.saveRecentSearches();
            this.hideDropdown();
        }

        /**
         * Capitalize entity type
         */
        capitalizeType(type) {
            return type.charAt(0).toUpperCase() + type.slice(1).replace(/_/g, ' ');
        }

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    /**
     * Initialize autocomplete on page load
     */
    function initAutocomplete() {
        const searchInputs = document.querySelectorAll('.saga-search-input, input[name="s"][type="search"]');

        searchInputs.forEach(input => {
            // Skip if already initialized
            if (input.dataset.sagaAutocomplete === 'initialized') {
                return;
            }

            new SagaAutocomplete(input, {
                ajaxUrl: window.sagaAutocomplete?.ajaxUrl,
                nonce: window.sagaAutocomplete?.nonce,
                sagaId: input.dataset.sagaId || null
            });

            input.dataset.sagaAutocomplete = 'initialized';
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    } else {
        initAutocomplete();
    }

    // Re-initialize on AJAX content load (for dynamic content)
    document.addEventListener('saga:contentLoaded', initAutocomplete);

})();
