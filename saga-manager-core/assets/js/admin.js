/**
 * Saga Manager Core - Admin JavaScript
 */

(function($) {
    'use strict';

    const SagaManagerAdmin = {
        config: window.sagaManagerCore || {},

        init: function() {
            this.bindEvents();
            this.initDashboard();
        },

        bindEvents: function() {
            // Confirm delete actions
            $(document).on('click', '.saga-delete-btn', this.confirmDelete.bind(this));

            // Form submissions
            $(document).on('submit', '.saga-ajax-form', this.handleFormSubmit.bind(this));

            // Search input debounce
            let searchTimeout;
            $(document).on('input', '.saga-search-input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    SagaManagerAdmin.handleSearch($(e.target).val());
                }, 300);
            });
        },

        initDashboard: function() {
            const dashboard = document.getElementById('saga-manager-dashboard');
            if (!dashboard) {
                return;
            }

            this.loadDashboardStats();
        },

        loadDashboardStats: function() {
            const container = document.getElementById('saga-manager-dashboard');
            if (!container) {
                return;
            }

            container.innerHTML = '<div class="saga-loading"><span class="spinner is-active"></span> Loading...</div>';

            wp.apiFetch({
                path: '/saga/v1/stats',
                method: 'GET',
            }).then(function(data) {
                SagaManagerAdmin.renderDashboard(container, data);
            }).catch(function(error) {
                container.innerHTML = '<div class="notice notice-error"><p>' +
                    SagaManagerAdmin.config.i18n.error + '</p></div>';
                console.error('Dashboard load error:', error);
            });
        },

        renderDashboard: function(container, data) {
            const html = `
                <div class="saga-stats-grid">
                    <div class="saga-stat-card">
                        <h3>${data.sagas_label || 'Sagas'}</h3>
                        <p class="stat-value">${data.sagas_count || 0}</p>
                    </div>
                    <div class="saga-stat-card">
                        <h3>${data.entities_label || 'Entities'}</h3>
                        <p class="stat-value">${data.entities_count || 0}</p>
                    </div>
                    <div class="saga-stat-card">
                        <h3>${data.relationships_label || 'Relationships'}</h3>
                        <p class="stat-value">${data.relationships_count || 0}</p>
                    </div>
                    <div class="saga-stat-card">
                        <h3>${data.timeline_label || 'Timeline Events'}</h3>
                        <p class="stat-value">${data.timeline_count || 0}</p>
                    </div>
                </div>
            `;

            container.innerHTML = html;
        },

        confirmDelete: function(e) {
            e.preventDefault();

            const message = this.config.i18n.confirmDelete || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                return;
            }

            const $button = $(e.currentTarget);
            const url = $button.data('url');

            if (url) {
                this.deleteItem(url, $button);
            }
        },

        deleteItem: function(url, $button) {
            $button.prop('disabled', true);

            wp.apiFetch({
                path: url,
                method: 'DELETE',
            }).then(function(response) {
                // Remove the row if in a table
                $button.closest('tr').fadeOut(300, function() {
                    $(this).remove();
                });
            }).catch(function(error) {
                alert(SagaManagerAdmin.config.i18n.error || 'An error occurred');
                $button.prop('disabled', false);
            });
        },

        handleFormSubmit: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $submit = $form.find('[type="submit"]');
            const originalText = $submit.text();

            $submit.prop('disabled', true).text(this.config.i18n.saving || 'Saving...');

            const formData = new FormData($form[0]);
            const data = Object.fromEntries(formData.entries());

            wp.apiFetch({
                path: $form.attr('action'),
                method: $form.attr('method') || 'POST',
                data: data,
            }).then(function(response) {
                $submit.text(SagaManagerAdmin.config.i18n.saved || 'Saved!');
                setTimeout(function() {
                    $submit.prop('disabled', false).text(originalText);
                }, 2000);
            }).catch(function(error) {
                alert(error.message || SagaManagerAdmin.config.i18n.error);
                $submit.prop('disabled', false).text(originalText);
            });
        },

        handleSearch: function(query) {
            // Override in specific pages
            console.log('Search:', query);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SagaManagerAdmin.init();
    });

    // Expose for external use
    window.SagaManagerAdmin = SagaManagerAdmin;

})(jQuery);
