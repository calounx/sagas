/**
 * Summaries Dashboard JavaScript
 *
 * Frontend JavaScript for Auto-Generated Summaries admin interface.
 * Handles form submission, AJAX calls, progress polling, and UI interactions.
 * Fully bilingual with error handling.
 *
 * @package SagaManager
 * @since 1.5.0
 */

(function($) {
    'use strict';

    const SummariesDashboard = {
        // Configuration
        config: {
            pollInterval: 2000, // 2 seconds
            maxPollAttempts: 150, // 5 minutes max
        },

        // State
        state: {
            currentRequestId: null,
            pollTimer: null,
            pollAttempts: 0,
            currentSagaId: window.sagaSummariesData?.currentSagaId || 0,
            chart: null,
        },

        /**
         * Initialize dashboard
         */
        init() {
            this.bindEvents();
            this.initAdvancedSettings();
            this.loadSummaries();
            this.loadStatistics();

            // Set saga from URL if available
            if (this.state.currentSagaId > 0) {
                $('#saga-select').val(this.state.currentSagaId);
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Form submission
            $('#saga-summary-form').on('submit', (e) => {
                e.preventDefault();
                this.submitGenerationRequest();
            });

            // Summary type change (show/hide entity selector)
            $('#summary-type-select').on('change', (e) => {
                this.handleTypeChange(e.target.value);
            });

            // Saga selection change (load entities)
            $('#saga-select').on('change', (e) => {
                this.state.currentSagaId = parseInt(e.target.value);
                this.loadEntities();
                this.loadSummaries();
                this.loadStatistics();
            });

            // Advanced settings toggle
            $('#toggle-advanced').on('click', () => {
                $('#advanced-settings').slideToggle();
                const btn = $('#toggle-advanced');
                const isVisible = $('#advanced-settings').is(':visible');
                btn.text(isVisible ? window.sagaSummariesData.i18n.hideAdvanced || 'Hide Advanced Settings' : window.sagaSummariesData.i18n.showAdvanced || 'Show Advanced Settings');
            });

            // AI provider change (update models)
            $('#ai-provider').on('change', (e) => {
                this.updateModelOptions(e.target.value);
            });

            // Cancel request
            $('#cancel-request-btn').on('click', () => {
                this.cancelRequest();
            });

            // Filters
            $('#apply-filters-btn').on('click', () => {
                this.loadSummaries();
            });

            // Search (debounced)
            let searchTimeout;
            $('#summary-search').on('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchSummaries(e.target.value);
                }, 500);
            });

            // Modal close buttons
            $('.saga-modal-close').on('click', (e) => {
                $(e.target).closest('.saga-modal').fadeOut(200);
            });

            // Click outside modal to close
            $('.saga-modal-overlay').on('click', (e) => {
                $(e.target).closest('.saga-modal').fadeOut(200);
            });

            // Export buttons
            $('#export-markdown-btn').on('click', () => this.exportSummary('markdown'));
            $('#export-html-btn').on('click', () => this.exportSummary('html'));

            // Regenerate button
            $('#regenerate-btn').on('click', () => this.regenerateSummary());

            // Delete button
            $('#delete-summary-btn').on('click', () => this.deleteSummary());

            // Feedback stars
            $('.star').on('click', (e) => {
                const rating = $(e.target).data('rating');
                this.setRating(rating);
            });

            // Submit feedback
            $('#submit-feedback-btn').on('click', () => this.submitFeedback());
        },

        /**
         * Initialize advanced settings
         */
        initAdvancedSettings() {
            this.updateModelOptions('openai');
        },

        /**
         * Handle summary type change
         */
        handleTypeChange(type) {
            const requiresEntity = ['character_arc', 'faction', 'location'].includes(type);

            if (requiresEntity) {
                $('#entity-select-group').slideDown();
                $('#entity-select').prop('required', true);
                this.loadEntities(type);
            } else {
                $('#entity-select-group').slideUp();
                $('#entity-select').prop('required', false);
            }

            // Update description
            const descriptions = {
                character_arc: 'Generate a narrative arc summary for a character based on verified data.',
                timeline: 'Create a chronological timeline summary of events.',
                relationship: 'Analyze relationships and connections between entities.',
                faction: 'Summarize faction structure, members, and activities.',
                location: 'Describe a location with events and associated entities.',
            };

            $('#type-description').text(descriptions[type] || '');
        },

        /**
         * Load entities for saga and type
         */
        loadEntities(entityType = null) {
            const sagaId = this.state.currentSagaId;

            if (!sagaId) {
                return;
            }

            // Determine entity type from summary type
            const typeMap = {
                character_arc: 'character',
                faction: 'faction',
                location: 'location',
            };

            const filterType = entityType ? typeMap[entityType] : null;

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_load_entities',
                    nonce: window.sagaSummariesData.nonce,
                    saga_id: sagaId,
                    entity_type: filterType,
                },
                success: (response) => {
                    if (response.success) {
                        this.populateEntitySelect(response.data.entities);
                    }
                },
                error: () => {
                    this.showToast('error', window.sagaSummariesData.i18n.errorNetwork);
                },
            });
        },

        /**
         * Populate entity select dropdown
         */
        populateEntitySelect(entities) {
            const $select = $('#entity-select');
            $select.empty();
            $select.append('<option value="">-- Select Entity --</option>');

            entities.forEach(entity => {
                $select.append(
                    $('<option></option>')
                        .val(entity.id)
                        .text(entity.canonical_name)
                );
            });
        },

        /**
         * Update AI model options based on provider
         */
        updateModelOptions(provider) {
            const $select = $('#ai-model');
            $select.empty();

            if (provider === 'openai') {
                $select.append('<option value="gpt-4">GPT-4 (Best Quality)</option>');
                $select.append('<option value="gpt-3.5-turbo">GPT-3.5 Turbo (Faster)</option>');
            } else if (provider === 'anthropic') {
                $select.append('<option value="claude-3-opus-20240229">Claude 3 Opus (Best Quality)</option>');
                $select.append('<option value="claude-3-sonnet-20240229">Claude 3 Sonnet (Balanced)</option>');
            }
        },

        /**
         * Submit generation request
         */
        submitGenerationRequest() {
            const formData = {
                action: 'saga_request_summary',
                nonce: window.sagaSummariesData.nonce,
                saga_id: $('#saga-select').val(),
                summary_type: $('#summary-type-select').val(),
                entity_id: $('#entity-select').val() || null,
                scope: $('#scope-select').val(),
                scope_params: {},
                ai_provider: $('#ai-provider').val(),
                ai_model: $('#ai-model').val(),
            };

            // Validate
            if (!formData.saga_id) {
                this.showToast('error', window.sagaSummariesData.i18n.errorNoSaga || 'Please select a saga');
                return;
            }

            if (!formData.summary_type) {
                this.showToast('error', window.sagaSummariesData.i18n.errorNoType || 'Please select a summary type');
                return;
            }

            // Disable button
            const $btn = $('#generate-summary-btn');
            $btn.prop('disabled', true).text(window.sagaSummariesData.i18n.processing || 'Processing...');

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.state.currentRequestId = response.data.request_id;
                        this.showProgressSection();
                        this.startProgressPolling();
                        this.showToast('success', response.data.message);
                    } else {
                        this.showToast('error', response.data.message || 'Request failed');
                        $btn.prop('disabled', false).text('Generate Summary');
                    }
                },
                error: (xhr) => {
                    const message = xhr.responseJSON?.data?.message || window.sagaSummariesData.i18n.errorNetwork;
                    this.showToast('error', message);
                    $btn.prop('disabled', false).text('Generate Summary');
                },
            });
        },

        /**
         * Show progress section
         */
        showProgressSection() {
            $('#progress-section').slideDown();
            this.updateProgress(0, window.sagaSummariesData.i18n.loading || 'Initializing...');
        },

        /**
         * Hide progress section
         */
        hideProgressSection() {
            $('#progress-section').slideUp();
            $('#generate-summary-btn').prop('disabled', false).text('Generate Summary');
        },

        /**
         * Start progress polling
         */
        startProgressPolling() {
            this.state.pollAttempts = 0;
            this.pollProgress();
        },

        /**
         * Poll progress
         */
        pollProgress() {
            if (!this.state.currentRequestId) {
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_get_summary_progress',
                    nonce: window.sagaSummariesData.nonce,
                    request_id: this.state.currentRequestId,
                },
                success: (response) => {
                    if (response.success) {
                        const progress = response.data;
                        this.updateProgress(progress.percent, progress.message);

                        // Check if complete or failed
                        if (progress.status === 'completed') {
                            this.handleGenerationComplete();
                        } else if (progress.status === 'failed') {
                            this.handleGenerationFailed(progress.error);
                        } else if (progress.status === 'generating' || progress.status === 'pending') {
                            // Continue polling
                            this.state.pollAttempts++;

                            if (this.state.pollAttempts < this.config.maxPollAttempts) {
                                this.state.pollTimer = setTimeout(() => {
                                    this.pollProgress();
                                }, this.config.pollInterval);
                            } else {
                                this.handleGenerationTimeout();
                            }
                        }
                    }
                },
                error: () => {
                    // Continue polling on network errors
                    this.state.pollAttempts++;

                    if (this.state.pollAttempts < this.config.maxPollAttempts) {
                        this.state.pollTimer = setTimeout(() => {
                            this.pollProgress();
                        }, this.config.pollInterval);
                    }
                },
            });
        },

        /**
         * Update progress UI
         */
        updateProgress(percent, message) {
            $('#progress-bar-fill').css('width', percent + '%');
            $('#progress-percent').text(Math.round(percent));
            $('#progress-message').text(message);
        },

        /**
         * Handle generation complete
         */
        handleGenerationComplete() {
            this.stopProgressPolling();
            this.updateProgress(100, window.sagaSummariesData.i18n.progressComplete || 'Complete!');

            setTimeout(() => {
                this.hideProgressSection();
                this.loadSummaries();
                this.loadStatistics();
                this.showToast('success', window.sagaSummariesData.i18n.successGenerated || 'Summary generated successfully!');
            }, 1500);
        },

        /**
         * Handle generation failed
         */
        handleGenerationFailed(errorMessage) {
            this.stopProgressPolling();
            this.hideProgressSection();
            this.showToast('error', errorMessage || window.sagaSummariesData.i18n.errorGenerationFailed || 'Generation failed');
        },

        /**
         * Handle generation timeout
         */
        handleGenerationTimeout() {
            this.stopProgressPolling();
            this.hideProgressSection();
            this.showToast('warning', 'Generation is taking longer than expected. Check the summary list later.');
        },

        /**
         * Stop progress polling
         */
        stopProgressPolling() {
            if (this.state.pollTimer) {
                clearTimeout(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        },

        /**
         * Cancel request
         */
        cancelRequest() {
            if (!confirm(window.sagaSummariesData.i18n.confirmCancel)) {
                return;
            }

            if (!this.state.currentRequestId) {
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_cancel_request',
                    nonce: window.sagaSummariesData.nonce,
                    request_id: this.state.currentRequestId,
                },
                success: (response) => {
                    if (response.success) {
                        this.stopProgressPolling();
                        this.hideProgressSection();
                        this.showToast('info', 'Request cancelled');
                    }
                },
            });
        },

        /**
         * Load summaries
         */
        loadSummaries() {
            if (!this.state.currentSagaId) {
                return;
            }

            const filterType = $('#filter-type').val();

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_load_summaries',
                    nonce: window.sagaSummariesData.nonce,
                    saga_id: this.state.currentSagaId,
                    filter_type: filterType,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderSummaries(response.data.summaries);
                    }
                },
            });
        },

        /**
         * Render summaries table
         */
        renderSummaries(summaries) {
            const $tbody = $('#summaries-tbody');
            $tbody.empty();

            if (summaries.length === 0) {
                $tbody.append(`
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            ${window.sagaSummariesData.i18n.noSummaries || 'No summaries yet'}
                        </td>
                    </tr>
                `);
                return;
            }

            summaries.forEach(summary => {
                const createdDate = new Date(summary.created_at * 1000);
                const formattedDate = createdDate.toLocaleDateString();

                const row = `
                    <tr data-summary-id="${summary.id}">
                        <td>${summary.id}</td>
                        <td><span class="summary-type-badge type-${summary.summary_type}">${summary.summary_type_label}</span></td>
                        <td><strong>${this.escapeHtml(summary.title)}</strong></td>
                        <td>
                            <span class="quality-badge quality-${this.getQualityClass(summary.quality_score)}">
                                ${summary.quality_label || 'N/A'}
                            </span>
                        </td>
                        <td>${summary.word_count.toLocaleString()}</td>
                        <td>$${summary.generation_cost.toFixed(4)}</td>
                        <td>${formattedDate}</td>
                        <td>
                            <button class="button button-small view-summary-btn" data-id="${summary.id}">
                                View
                            </button>
                        </td>
                    </tr>
                `;

                $tbody.append(row);
            });

            // Bind view buttons
            $('.view-summary-btn').on('click', (e) => {
                const summaryId = $(e.target).data('id');
                this.viewSummary(summaryId);
            });
        },

        /**
         * Get quality CSS class
         */
        getQualityClass(score) {
            if (!score) return 'unknown';
            if (score >= 90) return 'excellent';
            if (score >= 75) return 'good';
            if (score >= 60) return 'fair';
            return 'poor';
        },

        /**
         * View summary detail
         */
        viewSummary(summaryId) {
            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_load_summary_detail',
                    nonce: window.sagaSummariesData.nonce,
                    summary_id: summaryId,
                },
                success: (response) => {
                    if (response.success) {
                        this.showSummaryModal(response.data);
                    } else {
                        this.showToast('error', response.data.message || 'Failed to load summary');
                    }
                },
                error: () => {
                    this.showToast('error', window.sagaSummariesData.i18n.errorNetwork);
                },
            });
        },

        /**
         * Show summary modal
         */
        showSummaryModal(summary) {
            $('#modal-title').text(summary.title);

            const content = `
                <div class="summary-meta">
                    <div class="meta-item">
                        <strong>Type:</strong> ${summary.summary_type_label}
                    </div>
                    <div class="meta-item">
                        <strong>Quality:</strong> <span class="quality-badge quality-${this.getQualityClass(summary.quality_score)}">${summary.quality_label}</span>
                    </div>
                    <div class="meta-item">
                        <strong>Readability:</strong> ${summary.readability_label}
                    </div>
                    <div class="meta-item">
                        <strong>Words:</strong> ${summary.word_count.toLocaleString()}
                    </div>
                    <div class="meta-item">
                        <strong>Version:</strong> ${summary.version}
                    </div>
                    <div class="meta-item">
                        <strong>Model:</strong> ${summary.ai_model}
                    </div>
                </div>

                <div class="summary-content">
                    ${this.formatSummaryText(summary.summary_text)}
                </div>

                ${summary.key_points && summary.key_points.length > 0 ? `
                    <div class="summary-key-points">
                        <h3>Key Points</h3>
                        <ul>
                            ${summary.key_points.map(point => `<li>${this.escapeHtml(point)}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            `;

            $('#summary-detail-content').html(content);
            $('#summary-detail-modal').data('summary-id', summary.id).fadeIn(200);
        },

        /**
         * Format summary text (preserve markdown)
         */
        formatSummaryText(text) {
            // Simple markdown-like formatting
            let formatted = this.escapeHtml(text);

            // Headers
            formatted = formatted.replace(/^### (.+)$/gm, '<h3>$1</h3>');
            formatted = formatted.replace(/^## (.+)$/gm, '<h2>$1</h2>');
            formatted = formatted.replace(/^# (.+)$/gm, '<h1>$1</h1>');

            // Bold
            formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

            // Italic
            formatted = formatted.replace(/\*(.+?)\*/g, '<em>$1</em>');

            // Line breaks
            formatted = formatted.replace(/\n/g, '<br>');

            return formatted;
        },

        /**
         * Export summary
         */
        exportSummary(format) {
            const summaryId = $('#summary-detail-modal').data('summary-id');

            if (!summaryId) {
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_export_summary',
                    nonce: window.sagaSummariesData.nonce,
                    summary_id: summaryId,
                    format: format,
                },
                success: (response) => {
                    if (response.success) {
                        this.downloadFile(response.data.content, response.data.filename);
                        this.showToast('success', 'Summary exported successfully');
                    }
                },
            });
        },

        /**
         * Download file
         */
        downloadFile(content, filename) {
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        /**
         * Regenerate summary
         */
        regenerateSummary() {
            if (!confirm(window.sagaSummariesData.i18n.confirmRegenerate)) {
                return;
            }

            const summaryId = $('#summary-detail-modal').data('summary-id');

            if (!summaryId) {
                return;
            }

            const reason = prompt('Reason for regeneration (optional):') || 'Manual regeneration';

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_regenerate_summary',
                    nonce: window.sagaSummariesData.nonce,
                    summary_id: summaryId,
                    reason: reason,
                },
                success: (response) => {
                    if (response.success) {
                        $('#summary-detail-modal').fadeOut(200);
                        this.loadSummaries();
                        this.loadStatistics();
                        this.showToast('success', response.data.message);
                    } else {
                        this.showToast('error', response.data.message || 'Regeneration failed');
                    }
                },
                error: () => {
                    this.showToast('error', window.sagaSummariesData.i18n.errorNetwork);
                },
            });
        },

        /**
         * Delete summary
         */
        deleteSummary() {
            if (!confirm(window.sagaSummariesData.i18n.confirmDelete)) {
                return;
            }

            const summaryId = $('#summary-detail-modal').data('summary-id');

            if (!summaryId) {
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_delete_summary',
                    nonce: window.sagaSummariesData.nonce,
                    summary_id: summaryId,
                },
                success: (response) => {
                    if (response.success) {
                        $('#summary-detail-modal').fadeOut(200);
                        this.loadSummaries();
                        this.loadStatistics();
                        this.showToast('success', response.data.message);
                    } else {
                        this.showToast('error', response.data.message || 'Delete failed');
                    }
                },
                error: () => {
                    this.showToast('error', window.sagaSummariesData.i18n.errorNetwork);
                },
            });
        },

        /**
         * Search summaries
         */
        searchSummaries(searchTerm) {
            if (!searchTerm || searchTerm.length < 2) {
                this.loadSummaries();
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_search_summaries',
                    nonce: window.sagaSummariesData.nonce,
                    saga_id: this.state.currentSagaId,
                    search_term: searchTerm,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderSummaries(response.data.summaries);
                    }
                },
            });
        },

        /**
         * Load statistics
         */
        loadStatistics() {
            if (!this.state.currentSagaId) {
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_get_summary_statistics',
                    nonce: window.sagaSummariesData.nonce,
                    saga_id: this.state.currentSagaId,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderStatistics(response.data);
                    }
                },
            });
        },

        /**
         * Render statistics
         */
        renderStatistics(stats) {
            $('#stat-total').text(stats.total_summaries);
            $('#stat-quality').text(stats.avg_quality ? stats.avg_quality.toFixed(1) : 'N/A');
            $('#stat-readability').text(stats.avg_readability ? stats.avg_readability.toFixed(1) : 'N/A');
            $('#stat-cost').text('$' + stats.total_cost.toFixed(4));

            // Update chart
            this.renderChart(stats.by_type);
        },

        /**
         * Render chart
         */
        renderChart(byType) {
            const ctx = document.getElementById('stats-chart');

            if (!ctx) {
                return;
            }

            // Destroy existing chart
            if (this.state.chart) {
                this.state.chart.destroy();
            }

            const data = {
                labels: Object.keys(byType).map(type => type.replace('_', ' ').toUpperCase()),
                datasets: [{
                    label: 'Summaries by Type',
                    data: Object.values(byType),
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                    ],
                }]
            };

            // Check if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.state.chart = new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false,
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                },
                            },
                        },
                    },
                });
            }
        },

        /**
         * Set feedback rating
         */
        setRating(rating) {
            $('#feedback-rating').val(rating);

            // Update stars
            $('.star').each(function() {
                const starRating = $(this).data('rating');
                $(this).toggleClass('active', starRating <= rating);
            });
        },

        /**
         * Submit feedback
         */
        submitFeedback() {
            const summaryId = $('#feedback-summary-id').val();
            const rating = $('#feedback-rating').val();
            const feedback = $('#feedback-text').val();

            if (!rating) {
                this.showToast('error', 'Please select a rating');
                return;
            }

            $.ajax({
                url: window.sagaSummariesData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'saga_submit_summary_feedback',
                    nonce: window.sagaSummariesData.nonce,
                    summary_id: summaryId,
                    rating: rating,
                    feedback: feedback,
                },
                success: (response) => {
                    if (response.success) {
                        $('#feedback-modal').fadeOut(200);
                        this.showToast('success', response.data.message);
                        $('#feedback-form')[0].reset();
                        $('.star').removeClass('active');
                    } else {
                        this.showToast('error', response.data.message || 'Feedback submission failed');
                    }
                },
                error: () => {
                    this.showToast('error', window.sagaSummariesData.i18n.errorNetwork);
                },
            });
        },

        /**
         * Show toast notification
         */
        showToast(type, message) {
            const toast = $('<div></div>')
                .addClass('saga-toast saga-toast-' + type)
                .text(message);

            $('#toast-container').append(toast);

            setTimeout(() => {
                toast.addClass('show');
            }, 10);

            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        },
    };

    // Initialize on document ready
    $(document).ready(() => {
        SummariesDashboard.init();
    });

    // Expose to window for debugging
    window.SummariesDashboard = SummariesDashboard;

})(jQuery);
