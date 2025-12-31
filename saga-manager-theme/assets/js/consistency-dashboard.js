/**
 * AI Consistency Guardian Dashboard JavaScript
 *
 * Handles all frontend interactions for the consistency guardian admin interface
 *
 * @package SagaManager
 * @version 1.4.0
 */

(function($) {
    'use strict';

    /**
     * Consistency Dashboard Controller
     */
    const ConsistencyDashboard = {
        // State
        currentPage: 1,
        totalPages: 1,
        filters: {
            status: 'open',
            severity: '',
            issueType: ''
        },
        selectedIssues: new Set(),
        scanInterval: null,

        /**
         * Initialize dashboard
         */
        init: function() {
            if (typeof sagaConsistencyData === 'undefined') {
                return;
            }

            this.bindEvents();
            this.initCharts();
            this.loadIssues();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Saga selector
            $('#saga-selector').on('change', this.handleSagaChange.bind(this));

            // Run scan
            $('#run-scan-btn').on('click', this.handleRunScan.bind(this));

            // Filters
            $('#apply-filters').on('click', this.handleApplyFilters.bind(this));

            // Bulk actions
            $('#select-all-issues').on('change', this.handleSelectAll.bind(this));
            $(document).on('change', '.issue-checkbox', this.handleIssueSelect.bind(this));
            $('#apply-bulk-action').on('click', this.handleBulkAction.bind(this));

            // Export
            $('#export-csv').on('click', this.handleExport.bind(this));

            // Pagination
            $('#first-page').on('click', () => this.goToPage(1));
            $('#prev-page').on('click', () => this.goToPage(this.currentPage - 1));
            $('#next-page').on('click', () => this.goToPage(this.currentPage + 1));
            $('#last-page').on('click', () => this.goToPage(this.totalPages));
            $('#current-page-selector').on('change', (e) => this.goToPage(parseInt(e.target.value)));

            // Issue details modal
            $(document).on('click', '.view-issue-details', this.handleViewDetails.bind(this));
            $('.saga-modal-close, .saga-modal-backdrop').on('click', this.closeModal.bind(this));
            $('.saga-modal-footer button[data-action]').on('click', this.handleModalAction.bind(this));

            // Individual issue actions
            $(document).on('click', '.resolve-issue', this.handleResolveIssue.bind(this));
            $(document).on('click', '.dismiss-issue', this.handleDismissIssue.bind(this));
        },

        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                setTimeout(() => this.initCharts(), 100);
                return;
            }

            const stats = sagaConsistencyData.statsData;
            const issuesByType = sagaConsistencyData.issuesByType;

            // Severity chart (Pie)
            const severityCtx = document.getElementById('severity-chart');
            if (severityCtx) {
                new Chart(severityCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Critical', 'High', 'Medium', 'Low'],
                        datasets: [{
                            data: [
                                stats.critical_count || 0,
                                stats.high_count || 0,
                                stats.medium_count || 0,
                                stats.low_count || 0
                            ],
                            backgroundColor: [
                                '#dc2626',
                                '#ea580c',
                                '#ca8a04',
                                '#2563eb'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Type chart (Bar)
            const typeCtx = document.getElementById('type-chart');
            if (typeCtx && Object.keys(issuesByType).length > 0) {
                const typeLabels = Object.keys(issuesByType).map(type =>
                    type.charAt(0).toUpperCase() + type.slice(1)
                );
                const typeData = Object.values(issuesByType);

                new Chart(typeCtx, {
                    type: 'bar',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            label: 'Issues',
                            data: typeData,
                            backgroundColor: '#2271b1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        },

        /**
         * Load issues from server
         */
        loadIssues: function() {
            const $tbody = $('#issues-tbody');
            $tbody.html('<tr class="loading-row"><td colspan="8"><span class="spinner is-active"></span> Loading issues...</td></tr>');

            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_load_issues',
                    saga_id: sagaConsistencyData.sagaId,
                    status: this.filters.status,
                    severity: this.filters.severity,
                    issue_type: this.filters.issueType,
                    page: this.currentPage,
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.renderIssues(response.data.issues);
                        this.updatePagination(response.data.pagination);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(sagaConsistencyData.i18n.networkError);
                }
            });
        },

        /**
         * Render issues table
         */
        renderIssues: function(issues) {
            const $tbody = $('#issues-tbody');

            if (issues.length === 0) {
                $tbody.html(`
                    <tr class="no-items">
                        <td colspan="8" class="colspanchange">
                            <span class="dashicons dashicons-info"></span>
                            No issues found with current filters.
                        </td>
                    </tr>
                `);
                return;
            }

            const rows = issues.map(issue => this.renderIssueRow(issue)).join('');
            $tbody.html(rows);
        },

        /**
         * Render single issue row
         */
        renderIssueRow: function(issue) {
            const severityClass = `severity-${issue.severity}`;
            const typeIcon = this.getTypeIcon(issue.type);

            return `
                <tr class="issue-row ${severityClass}" data-issue-id="${issue.id}">
                    <th class="check-column">
                        <input type="checkbox" class="issue-checkbox" value="${issue.id}">
                    </th>
                    <td class="column-severity">
                        <span class="severity-badge ${issue.severity}">${issue.severity_label}</span>
                    </td>
                    <td class="column-type">
                        <span class="dashicons ${typeIcon}"></span>
                        ${issue.type_label}
                    </td>
                    <td class="column-description">
                        <a href="#" class="view-issue-details" data-issue-id="${issue.id}">
                            ${this.escapeHtml(issue.description)}
                        </a>
                        ${issue.ai_confidence ? `<span class="ai-badge" title="AI Confidence: ${Math.round(issue.ai_confidence * 100)}%">AI</span>` : ''}
                    </td>
                    <td class="column-entity">
                        ${issue.entity_name ? this.escapeHtml(issue.entity_name) : '—'}
                    </td>
                    <td class="column-detected">
                        ${issue.detected_ago} ago
                    </td>
                    <td class="column-status">
                        <span class="status-badge status-${issue.status}">${issue.status}</span>
                    </td>
                    <td class="column-actions">
                        <button type="button" class="button button-small resolve-issue" data-issue-id="${issue.id}">
                            Resolve
                        </button>
                        <button type="button" class="button button-small dismiss-issue" data-issue-id="${issue.id}">
                            Dismiss
                        </button>
                    </td>
                </tr>
            `;
        },

        /**
         * Get dashicon for issue type
         */
        getTypeIcon: function(type) {
            const icons = {
                'timeline': 'dashicons-clock',
                'character': 'dashicons-admin-users',
                'location': 'dashicons-location',
                'relationship': 'dashicons-networking',
                'logical': 'dashicons-warning'
            };
            return icons[type] || 'dashicons-info';
        },

        /**
         * Update pagination
         */
        updatePagination: function(pagination) {
            this.currentPage = pagination.current_page;
            this.totalPages = pagination.total_pages;

            $('.displaying-num').text(`${pagination.total} items`);
            $('.total-pages').text(pagination.total_pages);
            $('#current-page-selector').val(pagination.current_page);

            // Enable/disable buttons
            $('#first-page, #prev-page').prop('disabled', pagination.current_page === 1);
            $('#next-page, #last-page').prop('disabled', pagination.current_page === pagination.total_pages);
        },

        /**
         * Handle saga change
         */
        handleSagaChange: function(e) {
            const sagaId = $(e.target).val();
            window.location.href = `admin.php?page=saga-consistency-guardian&saga_id=${sagaId}`;
        },

        /**
         * Handle run scan
         */
        handleRunScan: function() {
            const $btn = $('#run-scan-btn');
            const useAI = $('#use-ai-scan').is(':checked');

            $btn.prop('disabled', true);
            $('.scan-progress').show();

            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_run_consistency_scan',
                    saga_id: sagaConsistencyData.sagaId,
                    use_ai: useAI,
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.loadIssues();

                        // Reload page to update stats
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(sagaConsistencyData.i18n.networkError);
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $('.scan-progress').hide();
                }
            });
        },

        /**
         * Handle apply filters
         */
        handleApplyFilters: function() {
            this.filters.status = $('#filter-status').val();
            this.filters.severity = $('#filter-severity').val();
            this.filters.issueType = $('#filter-type').val();
            this.currentPage = 1;
            this.loadIssues();
        },

        /**
         * Handle select all
         */
        handleSelectAll: function(e) {
            const checked = $(e.target).is(':checked');
            $('.issue-checkbox').prop('checked', checked);

            this.selectedIssues.clear();
            if (checked) {
                $('.issue-checkbox').each((i, el) => {
                    this.selectedIssues.add(parseInt($(el).val()));
                });
            }
        },

        /**
         * Handle individual issue select
         */
        handleIssueSelect: function(e) {
            const issueId = parseInt($(e.target).val());
            if ($(e.target).is(':checked')) {
                this.selectedIssues.add(issueId);
            } else {
                this.selectedIssues.delete(issueId);
            }
        },

        /**
         * Handle bulk action
         */
        handleBulkAction: function() {
            const action = $('#bulk-action').val();

            if (!action) {
                this.showError('Please select an action');
                return;
            }

            if (this.selectedIssues.size === 0) {
                this.showError(sagaConsistencyData.i18n.noIssuesSelected);
                return;
            }

            if (!confirm(sagaConsistencyData.i18n.confirmBulkAction)) {
                return;
            }

            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_bulk_action',
                    action_type: action,
                    issue_ids: Array.from(this.selectedIssues),
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.selectedIssues.clear();
                        $('#select-all-issues').prop('checked', false);
                        this.loadIssues();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(sagaConsistencyData.i18n.networkError);
                }
            });
        },

        /**
         * Handle export
         */
        handleExport: function() {
            const url = new URL(sagaConsistencyData.ajaxUrl);
            url.searchParams.append('action', 'saga_export_issues');
            url.searchParams.append('saga_id', sagaConsistencyData.sagaId);
            url.searchParams.append('status', this.filters.status);
            url.searchParams.append('nonce', sagaConsistencyData.nonce);

            window.location.href = url.toString();
        },

        /**
         * Handle view issue details
         */
        handleViewDetails: function(e) {
            e.preventDefault();
            const issueId = $(e.currentTarget).data('issue-id');
            this.showIssueDetails(issueId);
        },

        /**
         * Show issue details modal
         */
        showIssueDetails: function(issueId) {
            const $modal = $('#issue-details-modal');
            const $body = $modal.find('.saga-modal-body');

            $modal.data('issue-id', issueId).show();
            $body.html('<div class="spinner is-active" style="float: none; margin: 40px auto;"></div>');

            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_get_issue_details',
                    issue_id: issueId,
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $body.html(this.renderIssueDetails(response.data.issue));
                    } else {
                        $body.html(`<p class="error">${response.data.message}</p>`);
                    }
                },
                error: () => {
                    $body.html(`<p class="error">${sagaConsistencyData.i18n.networkError}</p>`);
                }
            });
        },

        /**
         * Render issue details
         */
        renderIssueDetails: function(issue) {
            return `
                <div class="issue-details">
                    <div class="detail-row">
                        <strong>Type:</strong>
                        <span>${issue.type_label}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Severity:</strong>
                        <span class="severity-badge ${issue.severity}">${issue.severity_label}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Description:</strong>
                        <p>${this.escapeHtml(issue.description)}</p>
                    </div>
                    ${issue.entity_name ? `
                        <div class="detail-row">
                            <strong>Entity:</strong>
                            <span>${this.escapeHtml(issue.entity_name)}</span>
                        </div>
                    ` : ''}
                    ${issue.related_entity_name ? `
                        <div class="detail-row">
                            <strong>Related Entity:</strong>
                            <span>${this.escapeHtml(issue.related_entity_name)}</span>
                        </div>
                    ` : ''}
                    ${issue.suggested_fix ? `
                        <div class="detail-row">
                            <strong>Suggested Fix:</strong>
                            <p class="suggested-fix">${this.escapeHtml(issue.suggested_fix)}</p>
                        </div>
                    ` : ''}
                    ${issue.ai_confidence ? `
                        <div class="detail-row">
                            <strong>AI Confidence:</strong>
                            <span>${Math.round(issue.ai_confidence * 100)}%</span>
                        </div>
                    ` : ''}
                    <div class="detail-row">
                        <strong>Detected:</strong>
                        <span>${issue.detected_at}</span>
                    </div>
                </div>
            `;
        },

        /**
         * Handle modal action
         */
        handleModalAction: function(e) {
            const action = $(e.currentTarget).data('action');
            const issueId = $('#issue-details-modal').data('issue-id');

            if (action === 'resolve') {
                this.resolveIssue(issueId);
            } else if (action === 'dismiss') {
                this.dismissIssue(issueId, false);
            } else if (action === 'false-positive') {
                this.dismissIssue(issueId, true);
            }
        },

        /**
         * Handle resolve issue button
         */
        handleResolveIssue: function(e) {
            const issueId = $(e.currentTarget).data('issue-id');
            this.resolveIssue(issueId);
        },

        /**
         * Handle dismiss issue button
         */
        handleDismissIssue: function(e) {
            const issueId = $(e.currentTarget).data('issue-id');
            this.dismissIssue(issueId, false);
        },

        /**
         * Resolve issue
         */
        resolveIssue: function(issueId) {
            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_resolve_issue',
                    issue_id: issueId,
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModal();
                        this.loadIssues();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(sagaConsistencyData.i18n.networkError);
                }
            });
        },

        /**
         * Dismiss issue
         */
        dismissIssue: function(issueId, isFalsePositive) {
            $.ajax({
                url: sagaConsistencyData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_dismiss_issue',
                    issue_id: issueId,
                    false_positive: isFalsePositive,
                    nonce: sagaConsistencyData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.closeModal();
                        this.loadIssues();
                    } else {
                        this.showError(response.data.message);
                    }
                },
                error: () => {
                    this.showError(sagaConsistencyData.i18n.networkError);
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#issue-details-modal').hide();
        },

        /**
         * Go to page
         */
        goToPage: function(page) {
            if (page < 1 || page > this.totalPages) {
                return;
            }
            this.currentPage = page;
            this.loadIssues();
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);

            $('.wrap h1').after($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ConsistencyDashboard.init();
    });

})(jQuery);
