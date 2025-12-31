/**
 * Saga Manager AJAX Filtering
 *
 * Handles real-time filtering of entity archives
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Entity Filter Handler
     */
    const SagaFilters = {

        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initRangeSliders();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$form = $('#saga-filter-form');
            this.$container = $('#saga-entities-container');
            this.$sagaSelect = $('#saga-filter-saga');
            this.$typeSelect = $('#saga-filter-type');
            this.$searchInput = $('#saga-filter-search');
            this.$importanceMin = $('#saga-filter-importance-min');
            this.$importanceMax = $('#saga-filter-importance-max');
            this.$minValue = $('#importance-min-value');
            this.$maxValue = $('#importance-max-value');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.filterEntities();
            });

            // Real-time filtering on select change
            this.$sagaSelect.on('change', function() {
                self.filterEntities();
            });

            this.$typeSelect.on('change', function() {
                self.filterEntities();
            });

            // Debounced search input
            let searchTimeout;
            this.$searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.filterEntities();
                }, 500);
            });

            // Range slider change
            this.$importanceMin.on('change', function() {
                self.filterEntities();
            });

            this.$importanceMax.on('change', function() {
                self.filterEntities();
            });
        },

        /**
         * Initialize range sliders
         */
        initRangeSliders: function() {
            const self = this;

            this.$importanceMin.on('input', function() {
                const minVal = parseInt($(this).val(), 10);
                const maxVal = parseInt(self.$importanceMax.val(), 10);

                if (minVal > maxVal) {
                    $(this).val(maxVal);
                }

                self.$minValue.text($(this).val());
            });

            this.$importanceMax.on('input', function() {
                const minVal = parseInt(self.$importanceMin.val(), 10);
                const maxVal = parseInt($(this).val(), 10);

                if (maxVal < minVal) {
                    $(this).val(minVal);
                }

                self.$maxValue.text($(this).val());
            });
        },

        /**
         * Filter entities via AJAX
         */
        filterEntities: function() {
            const self = this;

            const formData = {
                action: 'saga_filter_entities',
                nonce: sagaAjax.nonce,
                saga_id: this.$sagaSelect.val() || 0,
                entity_type: this.$typeSelect.val() || '',
                search: this.$searchInput.val() || '',
                importance_min: this.$importanceMin.val() || 0,
                importance_max: this.$importanceMax.val() || 100,
                paged: 1
            };

            $.ajax({
                url: sagaAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    self.$container.addClass('loading').css('opacity', '0.5');
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        self.$container.html(response.data.html);
                        self.updateURL(formData);
                        
                        // Update results count if element exists
                        if ($('.saga-archive-header__count').length) {
                            $('.saga-archive-header__count').text(
                                response.data.found_posts + ' ' + 
                                (response.data.found_posts === 1 ? 'entity found' : 'entities found')
                            );
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Filter error:', error);
                    self.$container.html(
                        '<div class="saga-empty-state">' +
                        '<p>An error occurred while filtering. Please try again.</p>' +
                        '</div>'
                    );
                },
                complete: function() {
                    self.$container.removeClass('loading').css('opacity', '1');
                }
            });
        },

        /**
         * Update browser URL without reloading
         */
        updateURL: function(params) {
            const url = new URL(window.location);
            
            // Update URL parameters
            if (params.saga_id) {
                url.searchParams.set('saga', params.saga_id);
            } else {
                url.searchParams.delete('saga');
            }

            if (params.entity_type) {
                url.searchParams.set('type', params.entity_type);
            } else {
                url.searchParams.delete('type');
            }

            if (params.search) {
                url.searchParams.set('s', params.search);
            } else {
                url.searchParams.delete('s');
            }

            if (params.importance_min > 0) {
                url.searchParams.set('importance_min', params.importance_min);
            } else {
                url.searchParams.delete('importance_min');
            }

            if (params.importance_max < 100) {
                url.searchParams.set('importance_max', params.importance_max);
            } else {
                url.searchParams.delete('importance_max');
            }

            // Update URL without reloading
            window.history.pushState({}, '', url);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('#saga-filter-form').length) {
            SagaFilters.init();
        }
    });

})(jQuery);
