/**
 * Semantic Search Engine
 *
 * Provides natural language search capabilities with synonym matching,
 * concept understanding, and relevance scoring.
 *
 * @package SagaManager
 * @since 1.3.0
 */

(function($) {
    'use strict';

    class SemanticSearchEngine {
        constructor(options = {}) {
            this.options = {
                minQueryLength: 2,
                maxResults: 50,
                cacheTimeout: 5 * 60 * 1000, // 5 minutes
                debounceDelay: 300,
                voiceLanguage: 'en-US',
                ...options
            };

            this.cache = new Map();
            this.searchHistory = this.loadSearchHistory();
            this.savedSearches = this.loadSavedSearches();
            this.activeRequest = null;

            this.init();
        }

        init() {
            this.bindEvents();
            this.initKeyboardShortcuts();
            this.initVoiceSearch();
            this.restoreLastSearch();
        }

        bindEvents() {
            const self = this;

            // Main search input
            $(document).on('input', '.saga-search-input',
                this.debounce(function() {
                    const query = $(this).val().trim();
                    if (query.length >= self.options.minQueryLength) {
                        self.performSearch(query, $(this).closest('.saga-search-form'));
                    } else {
                        self.clearResults($(this).closest('.saga-search-form'));
                    }
                }, this.options.debounceDelay)
            );

            // Search form submit
            $(document).on('submit', '.saga-search-form', function(e) {
                e.preventDefault();
                const query = $(this).find('.saga-search-input').val().trim();
                if (query) {
                    self.performFullSearch(query, $(this));
                }
            });

            // Clear search
            $(document).on('click', '.saga-search-clear', function(e) {
                e.preventDefault();
                const form = $(this).closest('.saga-search-form');
                form.find('.saga-search-input').val('').focus();
                self.clearResults(form);
            });

            // Voice search toggle
            $(document).on('click', '.saga-voice-search-btn', function(e) {
                e.preventDefault();
                self.toggleVoiceSearch($(this));
            });

            // Filter changes
            $(document).on('change', '.saga-search-filters input, .saga-search-filters select', function() {
                const form = $(this).closest('.saga-search-form');
                const query = form.find('.saga-search-input').val().trim();
                if (query) {
                    self.performSearch(query, form);
                }
            });

            // Sort changes
            $(document).on('change', '.saga-search-sort', function() {
                const form = $(this).closest('.saga-search-form');
                const query = form.find('.saga-search-input').val().trim();
                if (query) {
                    self.performSearch(query, form);
                }
            });

            // Save search
            $(document).on('click', '.saga-save-search-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('.saga-search-form');
                const query = form.find('.saga-search-input').val().trim();
                if (query) {
                    self.saveSearch(query, self.getFilters(form));
                }
            });

            // Load saved search
            $(document).on('click', '.saga-saved-search-item', function(e) {
                e.preventDefault();
                const searchData = $(this).data('search');
                self.loadSearch(searchData);
            });

            // Remove saved search
            $(document).on('click', '.saga-remove-saved-search', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const name = $(this).closest('.saga-saved-search-item').data('name');
                self.removeSavedSearch(name);
            });

            // Infinite scroll
            $(document).on('scroll', '.saga-search-results-container', function() {
                if (self.shouldLoadMore($(this))) {
                    self.loadMoreResults($(this));
                }
            });

            // Result click tracking
            $(document).on('click', '.saga-search-result-item', function() {
                const query = $(this).closest('.saga-search-results').data('query');
                const entityId = $(this).data('entity-id');
                self.trackResultClick(query, entityId);
            });
        }

        initKeyboardShortcuts() {
            const self = this;

            $(document).on('keydown', function(e) {
                // Ctrl+K or / to focus search
                if ((e.ctrlKey && e.key === 'k') || e.key === '/') {
                    e.preventDefault();
                    $('.saga-search-input:first').focus().select();
                }

                // Esc to clear/blur search
                if (e.key === 'Escape') {
                    const activeSearch = $('.saga-search-input:focus');
                    if (activeSearch.length) {
                        activeSearch.blur();
                        self.clearResults(activeSearch.closest('.saga-search-form'));
                    }
                }
            });

            // Arrow key navigation in results
            $(document).on('keydown', '.saga-search-input', function(e) {
                const results = $(this).closest('.saga-search-form').find('.saga-search-result-item');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    results.first().focus();
                }
            });

            $(document).on('keydown', '.saga-search-result-item', function(e) {
                const current = $(this);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    current.next('.saga-search-result-item').focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prev = current.prev('.saga-search-result-item');
                    if (prev.length) {
                        prev.focus();
                    } else {
                        current.closest('.saga-search-form').find('.saga-search-input').focus();
                    }
                } else if (e.key === 'Enter') {
                    current.find('a').get(0).click();
                }
            });
        }

        initVoiceSearch() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                $('.saga-voice-search-btn').hide();
                return;
            }

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = true;
            this.recognition.lang = this.options.voiceLanguage;

            const self = this;

            this.recognition.onresult = function(event) {
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }

                const input = $('.saga-search-input:focus, .saga-search-input.voice-active').first();
                if (finalTranscript) {
                    input.val(finalTranscript).trigger('input');
                } else if (interimTranscript) {
                    input.val(interimTranscript);
                }
            };

            this.recognition.onerror = function(event) {
                console.error('Voice recognition error:', event.error);
                self.stopVoiceSearch();
            };

            this.recognition.onend = function() {
                self.stopVoiceSearch();
            };
        }

        toggleVoiceSearch(btn) {
            if (btn.hasClass('active')) {
                this.stopVoiceSearch();
            } else {
                this.startVoiceSearch(btn);
            }
        }

        startVoiceSearch(btn) {
            if (!this.recognition) return;

            const form = btn.closest('.saga-search-form');
            const input = form.find('.saga-search-input');

            btn.addClass('active');
            input.addClass('voice-active').attr('placeholder', 'Listening...');

            try {
                this.recognition.start();
            } catch (e) {
                console.error('Voice recognition failed to start:', e);
                this.stopVoiceSearch();
            }
        }

        stopVoiceSearch() {
            if (!this.recognition) return;

            try {
                this.recognition.stop();
            } catch (e) {
                // Already stopped
            }

            $('.saga-voice-search-btn').removeClass('active');
            $('.saga-search-input').removeClass('voice-active')
                .attr('placeholder', 'Search saga entities...');
        }

        async performSearch(query, form) {
            // Cancel previous request
            if (this.activeRequest) {
                this.activeRequest.abort();
            }

            // Check cache
            const cacheKey = this.getCacheKey(query, this.getFilters(form));
            const cached = this.getCached(cacheKey);
            if (cached) {
                this.displayResults(cached, form);
                return;
            }

            // Show loading
            this.showLoading(form);

            // Parse query
            const parsedQuery = this.parseQuery(query);

            try {
                const data = {
                    action: 'saga_semantic_search',
                    nonce: sagaSearchData.nonce,
                    query: parsedQuery.original,
                    parsed: parsedQuery,
                    filters: this.getFilters(form),
                    sort: form.find('.saga-search-sort').val() || 'relevance',
                    limit: this.options.maxResults,
                    offset: 0
                };

                this.activeRequest = $.ajax({
                    url: sagaSearchData.ajaxUrl,
                    type: 'POST',
                    data: data,
                    success: (response) => {
                        if (response.success) {
                            this.setCached(cacheKey, response.data);
                            this.displayResults(response.data, form);
                            this.addToHistory(query);
                            this.updateSuggestions(response.data.suggestions || []);
                        } else {
                            this.showError(form, response.data?.message || 'Search failed');
                        }
                    },
                    error: (xhr, status, error) => {
                        if (status !== 'abort') {
                            this.showError(form, 'Search request failed');
                        }
                    },
                    complete: () => {
                        this.hideLoading(form);
                        this.activeRequest = null;
                    }
                });
            } catch (error) {
                console.error('Search error:', error);
                this.showError(form, 'An error occurred during search');
                this.hideLoading(form);
            }
        }

        performFullSearch(query, form) {
            // Navigate to full search page with parameters
            const filters = this.getFilters(form);
            const params = new URLSearchParams({
                s: query,
                ...filters
            });

            window.location.href = `${sagaSearchData.searchPageUrl}?${params.toString()}`;
        }

        parseQuery(query) {
            const parsed = {
                original: query,
                terms: [],
                exact: [],
                exclude: [],
                operators: {
                    and: [],
                    or: [],
                    not: []
                }
            };

            // Extract exact phrases (in quotes)
            const exactMatches = query.match(/"([^"]+)"/g);
            if (exactMatches) {
                exactMatches.forEach(match => {
                    const phrase = match.replace(/"/g, '');
                    parsed.exact.push(phrase);
                });
                query = query.replace(/"[^"]+"/g, '');
            }

            // Extract excluded terms (prefixed with -)
            const excludeMatches = query.match(/-\w+/g);
            if (excludeMatches) {
                excludeMatches.forEach(match => {
                    parsed.exclude.push(match.substring(1));
                });
                query = query.replace(/-\w+/g, '');
            }

            // Extract boolean operators
            const words = query.split(/\s+/).filter(w => w.length > 0);
            let currentOperator = 'and';

            words.forEach((word, index) => {
                const upperWord = word.toUpperCase();

                if (upperWord === 'AND') {
                    currentOperator = 'and';
                } else if (upperWord === 'OR') {
                    currentOperator = 'or';
                } else if (upperWord === 'NOT') {
                    currentOperator = 'not';
                } else {
                    parsed.operators[currentOperator].push(word);
                    parsed.terms.push(word);
                }
            });

            return parsed;
        }

        getFilters(form) {
            const filters = {};

            // Entity types
            const types = [];
            form.find('.saga-filter-type:checked').each(function() {
                types.push($(this).val());
            });
            if (types.length > 0) {
                filters.types = types;
            }

            // Importance range
            const minImportance = form.find('.saga-filter-importance-min').val();
            const maxImportance = form.find('.saga-filter-importance-max').val();
            if (minImportance || maxImportance) {
                filters.importance = {
                    min: parseInt(minImportance) || 0,
                    max: parseInt(maxImportance) || 100
                };
            }

            // Date range
            const dateFrom = form.find('.saga-filter-date-from').val();
            const dateTo = form.find('.saga-filter-date-to').val();
            if (dateFrom || dateTo) {
                filters.dateRange = {
                    from: dateFrom,
                    to: dateTo
                };
            }

            // Saga filter
            const sagaId = form.find('.saga-filter-saga').val();
            if (sagaId) {
                filters.sagaId = sagaId;
            }

            return filters;
        }

        displayResults(data, form) {
            const container = form.find('.saga-search-results');
            container.data('query', data.query);

            if (!data.results || data.results.length === 0) {
                this.showEmptyState(container, data.query);
                return;
            }

            container.empty().addClass('has-results');

            // Results header
            const header = $('<div>', { class: 'saga-search-results-header' })
                .append($('<h3>', {
                    class: 'saga-results-count',
                    text: `${data.total} ${data.total === 1 ? 'result' : 'results'} found`
                }));

            if (data.query_time) {
                header.append($('<span>', {
                    class: 'saga-query-time',
                    text: `(${data.query_time}ms)`
                }));
            }

            container.append(header);

            // Group by type if enabled
            if (data.grouped) {
                Object.entries(data.results).forEach(([type, items]) => {
                    const group = $('<div>', { class: `saga-results-group saga-results-group-${type}` })
                        .append($('<h4>', {
                            class: 'saga-results-group-title',
                            text: this.formatEntityType(type)
                        }))
                        .append(this.renderResults(items, data.query));

                    container.append(group);
                });
            } else {
                container.append(this.renderResults(data.results, data.query));
            }

            // Did you mean suggestions
            if (data.suggestions && data.suggestions.length > 0) {
                const suggestions = $('<div>', { class: 'saga-search-suggestions' })
                    .append($('<p>', { text: 'Did you mean:' }))
                    .append(data.suggestions.map(s =>
                        $('<a>', {
                            href: '#',
                            class: 'saga-suggestion-link',
                            text: s,
                            click: (e) => {
                                e.preventDefault();
                                form.find('.saga-search-input').val(s).trigger('input');
                            }
                        })
                    ));

                container.prepend(suggestions);
            }

            // Announce results for screen readers
            this.announceResults(data.total);
        }

        renderResults(results, query) {
            const list = $('<div>', { class: 'saga-search-results-list' });

            results.forEach(result => {
                const item = $('<article>', {
                    class: `saga-search-result-item saga-entity-type-${result.type}`,
                    'data-entity-id': result.id,
                    tabindex: 0
                });

                // Icon
                const icon = $('<div>', { class: 'saga-result-icon' })
                    .append($('<i>', { class: `saga-icon saga-icon-${result.type}` }));

                // Content
                const content = $('<div>', { class: 'saga-result-content' });

                // Title with highlighting
                const title = $('<h4>', { class: 'saga-result-title' })
                    .append($('<a>', {
                        href: result.url,
                        html: this.highlightText(result.title, query)
                    }));

                // Meta information
                const meta = $('<div>', { class: 'saga-result-meta' })
                    .append($('<span>', {
                        class: 'saga-result-type',
                        text: this.formatEntityType(result.type)
                    }));

                if (result.saga_name) {
                    meta.append($('<span>', {
                        class: 'saga-result-saga',
                        text: result.saga_name
                    }));
                }

                if (result.importance_score !== undefined) {
                    meta.append(this.renderImportanceIndicator(result.importance_score));
                }

                // Snippet/excerpt
                if (result.snippet) {
                    const snippet = $('<p>', {
                        class: 'saga-result-snippet',
                        html: this.highlightText(result.snippet, query)
                    });
                    content.append(snippet);
                }

                // Relevance score (if in debug mode)
                if (sagaSearchData.debug && result.relevance_score) {
                    meta.append($('<span>', {
                        class: 'saga-result-score',
                        text: `Score: ${result.relevance_score.toFixed(2)}`
                    }));
                }

                content.append(title, meta);
                item.append(icon, content);
                list.append(item);
            });

            return list;
        }

        highlightText(text, query) {
            if (!text || !query) return text;

            const terms = query.split(/\s+/).filter(t => t.length > 1);
            let highlighted = text;

            terms.forEach(term => {
                const regex = new RegExp(`(${this.escapeRegex(term)})`, 'gi');
                highlighted = highlighted.replace(regex, '<mark>$1</mark>');
            });

            return highlighted;
        }

        formatEntityType(type) {
            const types = {
                character: 'Character',
                location: 'Location',
                event: 'Event',
                faction: 'Faction',
                artifact: 'Artifact',
                concept: 'Concept'
            };
            return types[type] || type.charAt(0).toUpperCase() + type.slice(1);
        }

        renderImportanceIndicator(score) {
            const stars = Math.round(score / 20); // Convert 0-100 to 0-5
            const indicator = $('<span>', {
                class: 'saga-importance-indicator',
                'aria-label': `Importance: ${score}/100`
            });

            for (let i = 0; i < 5; i++) {
                indicator.append($('<i>', {
                    class: `saga-star ${i < stars ? 'filled' : 'empty'}`
                }));
            }

            return indicator;
        }

        showEmptyState(container, query) {
            container.empty().removeClass('has-results').addClass('empty-state');

            const empty = $('<div>', { class: 'saga-search-empty' })
                .append($('<i>', { class: 'saga-icon-search-empty' }))
                .append($('<h3>', { text: 'No results found' }))
                .append($('<p>', {
                    html: `Your search for <strong>${this.escapeHtml(query)}</strong> didn't match any entities.`
                }))
                .append($('<ul>', { class: 'saga-search-tips' })
                    .append($('<li>', { text: 'Try different keywords' }))
                    .append($('<li>', { text: 'Use more general terms' }))
                    .append($('<li>', { text: 'Check your spelling' }))
                    .append($('<li>', { text: 'Remove filters to expand results' }))
                );

            container.append(empty);
        }

        showLoading(form) {
            form.addClass('loading');
            form.find('.saga-search-results').html('<div class="saga-search-loading"><div class="spinner"></div><p>Searching...</p></div>');
        }

        hideLoading(form) {
            form.removeClass('loading');
        }

        showError(form, message) {
            form.find('.saga-search-results').html(
                `<div class="saga-search-error"><i class="saga-icon-error"></i><p>${this.escapeHtml(message)}</p></div>`
            );
        }

        clearResults(form) {
            form.find('.saga-search-results').empty().removeClass('has-results empty-state');
        }

        // Cache management
        getCacheKey(query, filters) {
            return JSON.stringify({ query, filters });
        }

        getCached(key) {
            const cached = this.cache.get(key);
            if (!cached) return null;

            if (Date.now() - cached.timestamp > this.options.cacheTimeout) {
                this.cache.delete(key);
                return null;
            }

            return cached.data;
        }

        setCached(key, data) {
            this.cache.set(key, {
                data,
                timestamp: Date.now()
            });
        }

        // Search history
        loadSearchHistory() {
            try {
                return JSON.parse(localStorage.getItem('saga_search_history') || '[]');
            } catch (e) {
                return [];
            }
        }

        saveSearchHistory() {
            try {
                localStorage.setItem('saga_search_history', JSON.stringify(this.searchHistory));
            } catch (e) {
                console.error('Failed to save search history:', e);
            }
        }

        addToHistory(query) {
            // Remove duplicates
            this.searchHistory = this.searchHistory.filter(q => q !== query);
            // Add to beginning
            this.searchHistory.unshift(query);
            // Keep only last 50
            this.searchHistory = this.searchHistory.slice(0, 50);
            this.saveSearchHistory();
        }

        // Saved searches
        loadSavedSearches() {
            try {
                return JSON.parse(localStorage.getItem('saga_saved_searches') || '{}');
            } catch (e) {
                return {};
            }
        }

        saveSavedSearches() {
            try {
                localStorage.setItem('saga_saved_searches', JSON.stringify(this.savedSearches));
            } catch (e) {
                console.error('Failed to save searches:', e);
            }
        }

        saveSearch(query, filters) {
            const name = prompt('Name this search:');
            if (!name) return;

            this.savedSearches[name] = {
                query,
                filters,
                timestamp: Date.now()
            };

            this.saveSavedSearches();
            this.updateSavedSearchesUI();
        }

        loadSearch(searchData) {
            const form = $('.saga-search-form').first();
            form.find('.saga-search-input').val(searchData.query);

            // Apply filters
            Object.entries(searchData.filters).forEach(([key, value]) => {
                // Implementation depends on filter UI structure
            });

            form.find('.saga-search-input').trigger('input');
        }

        removeSavedSearch(name) {
            delete this.savedSearches[name];
            this.saveSavedSearches();
            this.updateSavedSearchesUI();
        }

        updateSavedSearchesUI() {
            // Update saved searches dropdown/panel
            const container = $('.saga-saved-searches-list');
            if (!container.length) return;

            container.empty();

            Object.entries(this.savedSearches).forEach(([name, data]) => {
                const item = $('<div>', {
                    class: 'saga-saved-search-item',
                    'data-name': name,
                    'data-search': JSON.stringify(data)
                })
                    .append($('<span>', { text: name }))
                    .append($('<button>', {
                        class: 'saga-remove-saved-search',
                        'aria-label': 'Remove',
                        html: '&times;'
                    }));

                container.append(item);
            });
        }

        updateSuggestions(suggestions) {
            // Update autocomplete suggestions
            const container = $('.saga-search-suggestions-list');
            if (!container.length) return;

            container.empty();

            suggestions.forEach(suggestion => {
                container.append($('<div>', {
                    class: 'saga-suggestion-item',
                    text: suggestion,
                    click: function() {
                        $('.saga-search-input').val(suggestion).trigger('input');
                    }
                }));
            });
        }

        restoreLastSearch() {
            const params = new URLSearchParams(window.location.search);
            const query = params.get('s');

            if (query) {
                $('.saga-search-input').val(query).trigger('input');
            }
        }

        shouldLoadMore(container) {
            const scrollTop = container.scrollTop();
            const scrollHeight = container.prop('scrollHeight');
            const height = container.height();

            return scrollTop + height >= scrollHeight - 100;
        }

        loadMoreResults(container) {
            // Implement pagination/infinite scroll
        }

        trackResultClick(query, entityId) {
            // Track analytics
            if (sagaSearchData.trackAnalytics) {
                $.post(sagaSearchData.ajaxUrl, {
                    action: 'saga_track_search_click',
                    nonce: sagaSearchData.nonce,
                    query,
                    entity_id: entityId
                });
            }
        }

        announceResults(count) {
            const announcement = `${count} ${count === 1 ? 'result' : 'results'} found`;
            const liveRegion = $('.saga-search-live-region');

            if (liveRegion.length) {
                liveRegion.text(announcement);
            }
        }

        // Utility functions
        debounce(func, wait) {
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

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        escapeRegex(text) {
            return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
    }

    // Initialize on document ready
    $(function() {
        if (typeof sagaSearchData !== 'undefined') {
            window.sagaSearch = new SemanticSearchEngine();
        }
    });

})(jQuery);
