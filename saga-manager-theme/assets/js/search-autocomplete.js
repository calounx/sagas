/**
 * Search Autocomplete
 *
 * Provides intelligent autocomplete suggestions for search queries
 * with entity preview, recent searches, and popular queries.
 *
 * @package SagaManager
 * @since 1.3.0
 */

(function($) {
    'use strict';

    class SearchAutocomplete {
        constructor(inputSelector, options = {}) {
            this.input = $(inputSelector);
            this.options = {
                minChars: 2,
                maxSuggestions: 10,
                debounceDelay: 200,
                showRecentSearches: true,
                showPopularSearches: true,
                showEntityPreviews: true,
                highlightMatches: true,
                ...options
            };

            this.dropdown = null;
            this.suggestions = [];
            this.selectedIndex = -1;
            this.activeRequest = null;

            this.init();
        }

        init() {
            this.createDropdown();
            this.bindEvents();
        }

        createDropdown() {
            this.dropdown = $('<div>', {
                class: 'saga-autocomplete-dropdown',
                role: 'listbox',
                'aria-label': 'Search suggestions'
            }).hide();

            this.input.after(this.dropdown);

            // Position dropdown
            this.positionDropdown();
        }

        bindEvents() {
            const self = this;

            // Input events
            this.input.on('input',
                this.debounce(function() {
                    const query = $(this).val().trim();
                    if (query.length >= self.options.minChars) {
                        self.fetchSuggestions(query);
                    } else if (query.length === 0) {
                        self.showDefaultSuggestions();
                    } else {
                        self.hideDropdown();
                    }
                }, this.options.debounceDelay)
            );

            this.input.on('focus', function() {
                const query = $(this).val().trim();
                if (query.length >= self.options.minChars) {
                    self.fetchSuggestions(query);
                } else if (self.options.showRecentSearches || self.options.showPopularSearches) {
                    self.showDefaultSuggestions();
                }
            });

            this.input.on('blur', function(e) {
                // Delay to allow click on dropdown
                setTimeout(() => {
                    if (!self.dropdown.is(':hover')) {
                        self.hideDropdown();
                    }
                }, 200);
            });

            // Keyboard navigation
            this.input.on('keydown', function(e) {
                if (!self.dropdown.is(':visible')) {
                    return;
                }

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        self.selectNext();
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        self.selectPrevious();
                        break;

                    case 'Enter':
                        if (self.selectedIndex >= 0) {
                            e.preventDefault();
                            self.selectSuggestion(self.selectedIndex);
                        }
                        break;

                    case 'Escape':
                        e.preventDefault();
                        self.hideDropdown();
                        break;

                    case 'Tab':
                        if (self.selectedIndex >= 0) {
                            e.preventDefault();
                            self.selectSuggestion(self.selectedIndex);
                        }
                        break;
                }
            });

            // Dropdown clicks
            this.dropdown.on('mouseenter', '.saga-autocomplete-item', function() {
                self.setSelected($(this).index());
            });

            this.dropdown.on('click', '.saga-autocomplete-item', function(e) {
                e.preventDefault();
                self.selectSuggestion($(this).index());
            });

            // Window resize
            $(window).on('resize', () => {
                this.positionDropdown();
            });

            // Click outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest(this.input).length &&
                    !$(e.target).closest(this.dropdown).length) {
                    this.hideDropdown();
                }
            });
        }

        fetchSuggestions(query) {
            // Cancel previous request
            if (this.activeRequest) {
                this.activeRequest.abort();
            }

            this.showLoading();

            this.activeRequest = $.ajax({
                url: sagaSearchData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_autocomplete',
                    nonce: sagaSearchData.nonce,
                    query: query,
                    max_suggestions: this.options.maxSuggestions,
                    include_entities: this.options.showEntityPreviews
                },
                success: (response) => {
                    if (response.success) {
                        this.suggestions = response.data;
                        this.renderSuggestions(query);
                    }
                },
                error: (xhr, status) => {
                    if (status !== 'abort') {
                        console.error('Autocomplete request failed');
                    }
                },
                complete: () => {
                    this.activeRequest = null;
                }
            });
        }

        showDefaultSuggestions() {
            const sections = [];

            // Recent searches
            if (this.options.showRecentSearches) {
                const recent = this.getRecentSearches();
                if (recent.length > 0) {
                    sections.push({
                        title: 'Recent Searches',
                        icon: 'history',
                        items: recent.slice(0, 5).map(query => ({
                            type: 'recent',
                            text: query,
                            value: query
                        }))
                    });
                }
            }

            // Popular searches
            if (this.options.showPopularSearches) {
                const popular = this.getPopularSearches();
                if (popular.length > 0) {
                    sections.push({
                        title: 'Popular Searches',
                        icon: 'trending',
                        items: popular.slice(0, 5).map(query => ({
                            type: 'popular',
                            text: query,
                            value: query
                        }))
                    });
                }
            }

            if (sections.length > 0) {
                this.renderSections(sections);
                this.showDropdown();
            }
        }

        renderSuggestions(query) {
            if (!this.suggestions || this.suggestions.length === 0) {
                this.hideDropdown();
                return;
            }

            this.dropdown.empty();
            this.selectedIndex = -1;

            const sections = this.groupSuggestions(this.suggestions);

            sections.forEach(section => {
                if (section.title) {
                    this.dropdown.append($('<div>', {
                        class: 'saga-autocomplete-section-title',
                        text: section.title
                    }));
                }

                section.items.forEach((item, index) => {
                    const suggestionEl = this.renderSuggestion(item, query);
                    this.dropdown.append(suggestionEl);
                });
            });

            this.showDropdown();
        }

        renderSuggestion(item, query) {
            const el = $('<div>', {
                class: `saga-autocomplete-item saga-autocomplete-${item.type}`,
                role: 'option',
                'data-value': item.value || item.text
            });

            // Icon
            if (item.icon) {
                el.append($('<i>', {
                    class: `saga-autocomplete-icon saga-icon-${item.icon}`
                }));
            }

            // Main content
            const content = $('<div>', { class: 'saga-autocomplete-content' });

            // Text with highlighting
            const text = $('<div>', {
                class: 'saga-autocomplete-text',
                html: this.options.highlightMatches && query
                    ? this.highlightMatch(item.text, query)
                    : this.escapeHtml(item.text)
            });

            content.append(text);

            // Meta information
            if (item.meta) {
                content.append($('<div>', {
                    class: 'saga-autocomplete-meta',
                    text: item.meta
                }));
            }

            // Entity preview
            if (item.type === 'entity' && item.preview) {
                const preview = $('<div>', { class: 'saga-autocomplete-preview' });

                if (item.preview.image) {
                    preview.append($('<img>', {
                        src: item.preview.image,
                        alt: item.text,
                        class: 'saga-autocomplete-thumb'
                    }));
                }

                if (item.preview.snippet) {
                    preview.append($('<p>', {
                        class: 'saga-autocomplete-snippet',
                        text: item.preview.snippet
                    }));
                }

                content.append(preview);
            }

            el.append(content);

            // Action icon (e.g., arrow for navigation)
            el.append($('<i>', {
                class: 'saga-autocomplete-action saga-icon-arrow-right'
            }));

            return el;
        }

        renderSections(sections) {
            this.dropdown.empty();
            this.selectedIndex = -1;

            sections.forEach(section => {
                // Section header
                const header = $('<div>', {
                    class: 'saga-autocomplete-section'
                });

                if (section.icon) {
                    header.append($('<i>', {
                        class: `saga-icon-${section.icon}`
                    }));
                }

                header.append($('<span>', { text: section.title }));
                this.dropdown.append(header);

                // Section items
                section.items.forEach(item => {
                    const itemEl = $('<div>', {
                        class: `saga-autocomplete-item saga-autocomplete-${item.type}`,
                        role: 'option',
                        'data-value': item.value
                    })
                        .append($('<i>', { class: `saga-icon-${section.icon}` }))
                        .append($('<span>', { text: item.text }));

                    this.dropdown.append(itemEl);
                });
            });
        }

        groupSuggestions(suggestions) {
            const sections = [];
            const byType = {};

            suggestions.forEach(item => {
                if (!byType[item.type]) {
                    byType[item.type] = [];
                }
                byType[item.type].push(item);
            });

            // Order: exact matches, entities, suggestions, corrections
            const order = ['exact', 'entity', 'suggestion', 'correction'];

            order.forEach(type => {
                if (byType[type] && byType[type].length > 0) {
                    sections.push({
                        title: this.getSectionTitle(type),
                        items: byType[type]
                    });
                }
            });

            // Add any other types
            Object.keys(byType).forEach(type => {
                if (!order.includes(type)) {
                    sections.push({
                        title: this.getSectionTitle(type),
                        items: byType[type]
                    });
                }
            });

            return sections;
        }

        getSectionTitle(type) {
            const titles = {
                exact: 'Exact Matches',
                entity: 'Entities',
                suggestion: 'Suggestions',
                correction: 'Did you mean?',
                recent: 'Recent Searches',
                popular: 'Popular Searches'
            };
            return titles[type] || type;
        }

        selectNext() {
            const items = this.dropdown.find('.saga-autocomplete-item');
            if (items.length === 0) return;

            this.selectedIndex = (this.selectedIndex + 1) % items.length;
            this.setSelected(this.selectedIndex);
        }

        selectPrevious() {
            const items = this.dropdown.find('.saga-autocomplete-item');
            if (items.length === 0) return;

            this.selectedIndex = this.selectedIndex <= 0
                ? items.length - 1
                : this.selectedIndex - 1;
            this.setSelected(this.selectedIndex);
        }

        setSelected(index) {
            const items = this.dropdown.find('.saga-autocomplete-item');
            items.removeClass('selected').removeAttr('aria-selected');

            this.selectedIndex = index;

            if (index >= 0 && index < items.length) {
                const selected = items.eq(index);
                selected.addClass('selected').attr('aria-selected', 'true');

                // Scroll into view if needed
                const dropdown = this.dropdown;
                const itemTop = selected.position().top;
                const itemBottom = itemTop + selected.outerHeight();
                const dropdownHeight = dropdown.height();
                const scrollTop = dropdown.scrollTop();

                if (itemBottom > dropdownHeight) {
                    dropdown.scrollTop(scrollTop + itemBottom - dropdownHeight);
                } else if (itemTop < 0) {
                    dropdown.scrollTop(scrollTop + itemTop);
                }
            }
        }

        selectSuggestion(index) {
            const items = this.dropdown.find('.saga-autocomplete-item');
            if (index < 0 || index >= items.length) return;

            const item = items.eq(index);
            const value = item.data('value');

            if (value) {
                this.input.val(value);
                this.input.trigger('input');
                this.addToRecentSearches(value);
            }

            this.hideDropdown();
            this.input.focus();
        }

        showLoading() {
            this.dropdown.html('<div class="saga-autocomplete-loading">Loading...</div>').show();
        }

        showDropdown() {
            this.positionDropdown();
            this.dropdown.show();
            this.input.attr('aria-expanded', 'true');
        }

        hideDropdown() {
            this.dropdown.hide();
            this.input.attr('aria-expanded', 'false');
            this.selectedIndex = -1;
        }

        positionDropdown() {
            const inputOffset = this.input.offset();
            const inputWidth = this.input.outerWidth();
            const inputHeight = this.input.outerHeight();

            this.dropdown.css({
                position: 'absolute',
                top: inputOffset.top + inputHeight,
                left: inputOffset.left,
                width: inputWidth,
                'z-index': 9999
            });
        }

        highlightMatch(text, query) {
            if (!query) return this.escapeHtml(text);

            const terms = query.split(/\s+/).filter(t => t.length > 0);
            let highlighted = this.escapeHtml(text);

            terms.forEach(term => {
                const regex = new RegExp(`(${this.escapeRegex(term)})`, 'gi');
                highlighted = highlighted.replace(regex, '<strong>$1</strong>');
            });

            return highlighted;
        }

        getRecentSearches() {
            try {
                const history = JSON.parse(localStorage.getItem('saga_search_history') || '[]');
                return history.slice(0, 10);
            } catch (e) {
                return [];
            }
        }

        addToRecentSearches(query) {
            try {
                let history = JSON.parse(localStorage.getItem('saga_search_history') || '[]');

                // Remove duplicates
                history = history.filter(q => q !== query);

                // Add to beginning
                history.unshift(query);

                // Keep only last 50
                history = history.slice(0, 50);

                localStorage.setItem('saga_search_history', JSON.stringify(history));
            } catch (e) {
                console.error('Failed to save recent search:', e);
            }
        }

        getPopularSearches() {
            // This would typically come from the server
            // For now, return empty array
            return [];
        }

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

        destroy() {
            this.dropdown.remove();
            this.input.off();
            $(window).off('resize');
        }
    }

    // jQuery plugin
    $.fn.sagaAutocomplete = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('saga-autocomplete');

            if (!instance) {
                instance = new SearchAutocomplete(this, options);
                $this.data('saga-autocomplete', instance);
            }

            return instance;
        });
    };

    // Auto-initialize
    $(function() {
        $('.saga-search-input').sagaAutocomplete();
    });

})(jQuery);
