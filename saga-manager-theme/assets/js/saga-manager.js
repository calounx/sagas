/**
 * Saga Manager Theme - JavaScript
 *
 * Handles AJAX filtering, search, and interactive features
 *
 * @package SagaTheme
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Saga Entity Filters
     */
    const SagaFilters = {
        /**
         * Initialize filters
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            // Filter form submission
            $(document).on('submit', '.saga-filters__form', this.handleFilter.bind(this));

            // Search input with debounce
            let searchTimeout;
            $(document).on('input', '.saga-filters__input[name="search"]', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function () {
                    $('.saga-filters__form').trigger('submit');
                }, 500);
            });

            // Reset filters
            $(document).on('click', '.saga-filters__reset', this.resetFilters.bind(this));

            // Load more (pagination)
            $(document).on('click', '.saga-load-more', this.loadMore.bind(this));
        },

        /**
         * Handle filter form submission
         *
         * @param {Event} e Submit event
         */
        handleFilter: function (e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $container = $('.saga-entities-grid');
            const formData = $form.serializeArray();

            // Add nonce
            formData.push({
                name: 'saga_filter_nonce',
                value: sagaAjax.nonces.filter
            });

            // Show loading state
            $container.addClass('saga-loading');

            // AJAX request
            $.ajax({
                url: sagaAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_filter_entities',
                    ...Object.fromEntries(formData.map(item => [item.name, item.value]))
                },
                success: function (response) {
                    if (response.success) {
                        $container.html(response.data.entities);

                        // Animate new items
                        $container.find('.saga-entity-card').addClass('saga-entity-card--animated');

                        // Update count
                        $('.saga-archive-header__count').text(
                            response.data.count + ' ' +
                            (response.data.count === 1 ? 'entity' : 'entities')
                        );
                    } else {
                        console.error('Filter failed:', response.data.message);
                        alert(sagaAjax.strings.error);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert(sagaAjax.strings.error);
                },
                complete: function () {
                    $container.removeClass('saga-loading');
                }
            });
        },

        /**
         * Reset all filters to default
         *
         * @param {Event} e Click event
         */
        resetFilters: function (e) {
            e.preventDefault();

            const $form = $('.saga-filters__form');

            // Reset form fields
            $form.find('input[type="text"], input[type="search"]').val('');
            $form.find('select').prop('selectedIndex', 0);
            $form.find('input[type="range"]').val(50).trigger('input');

            // Submit to reload default results
            $form.trigger('submit');
        },

        /**
         * Load more entities (pagination)
         *
         * @param {Event} e Click event
         */
        loadMore: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const page = parseInt($button.data('page')) || 1;
            const nextPage = page + 1;

            $button.prop('disabled', true).text(sagaAjax.strings.loading);

            // Get current filters
            const $form = $('.saga-filters__form');
            const formData = $form.serializeArray();
            formData.push({ name: 'paged', value: nextPage });
            formData.push({ name: 'saga_filter_nonce', value: sagaAjax.nonces.filter });

            $.ajax({
                url: sagaAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_filter_entities',
                    ...Object.fromEntries(formData.map(item => [item.name, item.value]))
                },
                success: function (response) {
                    if (response.success && response.data.entities) {
                        // Append new entities
                        $('.saga-entities-grid').append(response.data.entities);

                        // Update button state
                        if (nextPage >= response.data.max_pages) {
                            $button.hide();
                        } else {
                            $button.data('page', nextPage).prop('disabled', false).text('Load More');
                        }
                    }
                },
                error: function () {
                    $button.prop('disabled', false).text('Load More');
                    alert(sagaAjax.strings.error);
                }
            });
        }
    };

    /**
     * Saga Relationships
     */
    const SagaRelationships = {
        /**
         * Initialize relationships
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            // Lazy load relationships on demand
            $(document).on('click', '.saga-relationships__toggle', this.toggleRelationships.bind(this));
        },

        /**
         * Toggle relationships display
         *
         * @param {Event} e Click event
         */
        toggleRelationships: function (e) {
            e.preventDefault();

            const $toggle = $(e.currentTarget);
            const $container = $toggle.next('.saga-relationships__content');
            const entityId = $toggle.data('entity-id');

            if ($container.is(':visible')) {
                $container.slideUp();
                return;
            }

            // Check if already loaded
            if ($container.data('loaded')) {
                $container.slideDown();
                return;
            }

            // Load relationships via AJAX
            $toggle.prop('disabled', true);

            $.ajax({
                url: sagaAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_get_relationships',
                    entity_id: entityId,
                    saga_relationships_nonce: sagaAjax.nonces.relationships
                },
                success: function (response) {
                    if (response.success && response.data.relationships) {
                        // Render relationships
                        const html = SagaRelationships.renderRelationships(response.data.relationships);
                        $container.html(html).data('loaded', true).slideDown();
                    }
                },
                error: function () {
                    alert(sagaAjax.strings.error);
                },
                complete: function () {
                    $toggle.prop('disabled', false);
                }
            });
        },

        /**
         * Render relationships HTML
         *
         * @param {Array} relationships Array of relationship objects
         * @return {string} HTML string
         */
        renderRelationships: function (relationships) {
            if (!relationships.length) {
                return '<p>' + sagaAjax.strings.noResults + '</p>';
            }

            let html = '<ul class="saga-relationships__list">';

            relationships.forEach(function (rel) {
                html += '<li class="saga-relationships__item">';
                if (rel.entity.permalink) {
                    html += '<a href="' + rel.entity.permalink + '" class="saga-relationships__item-link">';
                    html += rel.entity.canonical_name;
                    html += '</a>';
                } else {
                    html += '<span class="saga-relationships__item-link">' + rel.entity.canonical_name + '</span>';
                }
                html += rel.strength_badge;
                html += '</li>';
            });

            html += '</ul>';

            return html;
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        SagaFilters.init();
        SagaRelationships.init();

        // Update range input display values
        $(document).on('input', 'input[type="range"]', function () {
            const $input = $(this);
            const $display = $input.siblings('.saga-filters__range-value');
            $display.text($input.val());
        });
    });

})(jQuery);
