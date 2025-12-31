/**
 * Entity Comparison JavaScript
 * Handles synchronized scrolling, entity search, and comparison interactions
 *
 * @package Saga_Manager_Theme
 */

(function($) {
    'use strict';

    /**
     * Entity Comparison Manager
     */
    const ComparisonManager = {

        /** Configuration */
        config: {
            maxEntities: 4,
            scrollSyncDelay: 16, // 60fps
            searchDebounce: 300,
        },

        /** State */
        state: {
            entities: [],
            isScrolling: false,
            scrollTimeout: null,
            searchTimeout: null,
        },

        /**
         * Initialize comparison functionality
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initializeFromUrl();
            this.setupSynchronizedScrolling();
            this.updateUrl();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$searchInput = $('#entity-search');
            this.$searchResults = $('.entity-search-results');
            this.$comparisonTable = $('.comparison-table');
            this.$comparisonWrapper = $('.comparison-wrapper');
            this.$tableScroll = $('.comparison-table-scroll');
            this.$showDifferencesToggle = $('#show-only-differences');
            this.$btnShareUrl = $('.btn-share-url');
            this.$btnExport = $('.btn-export-comparison');
            this.$emptyState = $('.comparison-empty-state');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Entity search
            this.$searchInput.on('input', function() {
                self.handleSearchInput($(this).val());
            });

            this.$searchInput.on('focus', function() {
                if ($(this).val().length >= 2) {
                    self.$searchResults.show();
                }
            });

            // Close search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.entity-selector').length) {
                    self.$searchResults.hide();
                }
            });

            // Remove entity buttons
            $(document).on('click', '.btn-remove-entity', function(e) {
                e.preventDefault();
                const entityId = $(this).data('entity-id');
                self.removeEntity(entityId);
            });

            // Show only differences toggle
            this.$showDifferencesToggle.on('change', function() {
                self.toggleDifferencesOnly($(this).is(':checked'));
            });

            // Share URL button
            this.$btnShareUrl.on('click', function() {
                self.shareUrl();
            });

            // Export button
            this.$btnExport.on('click', function() {
                self.exportComparison();
            });

            // Keyboard navigation
            this.$searchInput.on('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    self.$searchResults.find('.search-result-item').first().focus();
                }
            });

            this.$searchResults.on('keydown', '.search-result-item', function(e) {
                const $current = $(this);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    $current.next('.search-result-item').focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const $prev = $current.prev('.search-result-item');
                    if ($prev.length) {
                        $prev.focus();
                    } else {
                        self.$searchInput.focus();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    $current.click();
                }
            });
        },

        /**
         * Initialize entities from URL parameters
         */
        initializeFromUrl: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const entitiesParam = urlParams.get('entities');

            if (entitiesParam) {
                this.state.entities = entitiesParam.split(',').map(id => id.trim());
            }
        },

        /**
         * Setup synchronized scrolling for comparison table
         */
        setupSynchronizedScrolling: function() {
            const self = this;

            if (!this.$tableScroll.length) {
                return;
            }

            // Synchronized horizontal scrolling (if table is wider than viewport)
            this.$tableScroll.on('scroll', function() {
                if (!self.state.isScrolling) {
                    self.handleTableScroll();
                }
            });

            // Make header sticky on scroll
            this.setupStickyHeader();
        },

        /**
         * Handle table scroll with debouncing
         */
        handleTableScroll: function() {
            const self = this;

            // Clear existing timeout
            if (this.state.scrollTimeout) {
                clearTimeout(this.state.scrollTimeout);
            }

            // Set scrolling flag
            this.state.isScrolling = true;

            // Add scrolling class for visual feedback
            this.$comparisonTable.addClass('is-scrolling');

            // Clear scrolling state after delay
            this.state.scrollTimeout = setTimeout(function() {
                self.state.isScrolling = false;
                self.$comparisonTable.removeClass('is-scrolling');
            }, 150);
        },

        /**
         * Setup sticky header behavior
         */
        setupStickyHeader: function() {
            const self = this;
            const $header = this.$comparisonTable.find('.comparison-thead');

            if (!$header.length) {
                return;
            }

            $(window).on('scroll', function() {
                const scrollTop = $(window).scrollTop();
                const tableOffset = self.$comparisonTable.offset();

                if (tableOffset && scrollTop > tableOffset.top) {
                    $header.addClass('is-sticky');
                } else {
                    $header.removeClass('is-sticky');
                }
            });
        },

        /**
         * Handle entity search input
         */
        handleSearchInput: function(query) {
            const self = this;

            // Clear existing timeout
            if (this.state.searchTimeout) {
                clearTimeout(this.state.searchTimeout);
            }

            // Hide results if query too short
            if (query.length < 2) {
                this.$searchResults.hide();
                return;
            }

            // Show loading state
            this.$searchResults.html('<div class="search-loading">Searching...</div>').show();

            // Debounce search
            this.state.searchTimeout = setTimeout(function() {
                self.performSearch(query);
            }, this.config.searchDebounce);
        },

        /**
         * Perform entity search via AJAX
         */
        performSearch: function(query) {
            const self = this;

            $.ajax({
                url: sagaComparison.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_search_entities',
                    nonce: sagaComparison.nonce,
                    query: query,
                    exclude: this.state.entities.join(','),
                },
                success: function(response) {
                    if (response.success) {
                        self.displaySearchResults(response.data);
                    } else {
                        self.$searchResults.html('<div class="search-error">' + sagaComparison.i18n.noResults + '</div>');
                    }
                },
                error: function() {
                    self.$searchResults.html('<div class="search-error">Search failed. Please try again.</div>');
                }
            });
        },

        /**
         * Display search results
         */
        displaySearchResults: function(results) {
            const self = this;

            if (!results || results.length === 0) {
                this.$searchResults.html('<div class="search-no-results">' + sagaComparison.i18n.noResults + '</div>');
                return;
            }

            let html = '<div class="search-results-list" role="listbox">';

            results.forEach(function(entity) {
                html += '<button type="button" class="search-result-item" role="option" data-entity-id="' + entity.id + '" data-entity-slug="' + entity.slug + '">';

                if (entity.thumbnail) {
                    html += '<img src="' + entity.thumbnail + '" alt="" class="result-thumbnail" loading="lazy" />';
                }

                html += '<div class="result-content">';
                html += '<div class="result-title">' + entity.title + '</div>';

                if (entity.type) {
                    html += '<div class="result-type">' + entity.type + '</div>';
                }

                html += '</div>';
                html += '</button>';
            });

            html += '</div>';

            this.$searchResults.html(html);

            // Bind click handlers to results
            this.$searchResults.find('.search-result-item').on('click', function() {
                const entityId = $(this).data('entity-id');
                self.addEntity(entityId);
            });
        },

        /**
         * Add entity to comparison
         */
        addEntity: function(entityId) {
            // Check max entities limit
            if (this.state.entities.length >= this.config.maxEntities) {
                alert(sagaComparison.i18n.maxEntitiesError);
                return;
            }

            // Check if already added
            if (this.state.entities.includes(entityId.toString())) {
                return;
            }

            // Add to state
            this.state.entities.push(entityId.toString());

            // Update URL and reload page
            this.updateUrlAndReload();

            // Clear search
            this.$searchInput.val('');
            this.$searchResults.hide();
        },

        /**
         * Remove entity from comparison
         */
        removeEntity: function(entityId) {
            // Remove from state
            this.state.entities = this.state.entities.filter(id => id !== entityId.toString());

            // Update URL and reload page
            this.updateUrlAndReload();
        },

        /**
         * Toggle show only differences
         */
        toggleDifferencesOnly: function(showOnly) {
            const $rows = $('.comparison-row');

            if (showOnly) {
                $rows.each(function() {
                    const hasDiff = $(this).data('has-differences');
                    if (hasDiff !== true && hasDiff !== 'true') {
                        $(this).hide();
                    }
                });
            } else {
                $rows.show();
            }
        },

        /**
         * Share comparison URL
         */
        shareUrl: function() {
            const url = window.location.href;

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    // Show success message
                    const $btn = $('.btn-share-url');
                    const originalText = $btn.html();

                    $btn.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!');

                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 2000);
                }).catch(function() {
                    // Fallback: show prompt
                    prompt('Copy this URL:', url);
                });
            } else {
                // Fallback for older browsers
                prompt('Copy this URL:', url);
            }
        },

        /**
         * Export comparison data
         */
        exportComparison: function() {
            const self = this;

            // Get comparison data
            $.ajax({
                url: sagaComparison.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_export_comparison',
                    nonce: sagaComparison.nonce,
                    entities: this.state.entities.join(','),
                },
                success: function(response) {
                    if (response.success) {
                        // Create download
                        const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(response.data, null, 2));
                        const downloadAnchor = document.createElement('a');
                        downloadAnchor.setAttribute('href', dataStr);
                        downloadAnchor.setAttribute('download', 'entity-comparison-' + Date.now() + '.json');
                        document.body.appendChild(downloadAnchor);
                        downloadAnchor.click();
                        downloadAnchor.remove();
                    } else {
                        alert('Export failed. Please try again.');
                    }
                },
                error: function() {
                    alert('Export failed. Please try again.');
                }
            });
        },

        /**
         * Update URL without reloading
         */
        updateUrl: function() {
            if (this.state.entities.length === 0) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('entities', this.state.entities.join(','));

            window.history.replaceState({}, '', url.toString());
        },

        /**
         * Update URL and reload page
         */
        updateUrlAndReload: function() {
            const url = new URL(window.location.href);

            if (this.state.entities.length === 0) {
                url.searchParams.delete('entities');
            } else {
                url.searchParams.set('entities', this.state.entities.join(','));
            }

            window.location.href = url.toString();
        }
    };

    /**
     * AJAX Handlers
     */

    // Register entity search handler (if not already registered)
    if (typeof sagaComparison !== 'undefined') {
        // Entity search is handled by the theme's existing search functionality
        // or can be implemented as a custom AJAX handler in functions.php
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.comparison-page').length) {
            ComparisonManager.init();
        }
    });

})(jQuery);
