/**
 * Relationship Suggestions Dashboard JavaScript
 *
 * Handles all frontend interactions for the AI suggestions interface.
 */

(function($) {
    'use strict';

    // State management
    const state = {
        currentSagaId: window.SAGA_CURRENT_ID || 0,
        currentPage: 1,
        selectedSuggestions: new Set(),
        isGenerating: false,
        pollInterval: null,
        filters: {
            status: 'pending',
            minConfidence: 0.8,
            relationshipType: '',
            sortBy: 'confidence_score',
            sortOrder: 'DESC',
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        initializeEventHandlers();
        loadSuggestions();
        loadAnalytics();
        loadLearningStats();
        setupKeyboardShortcuts();
    });

    /**
     * Initialize all event handlers
     */
    function initializeEventHandlers() {
        // Header controls
        $('#saga-select').on('change', handleSagaChange);
        $('#generate-suggestions-btn').on('click', handleGenerateSuggestions);

        // Filters
        $('#apply-filters-btn').on('click', handleApplyFilters);
        $('#reset-filters-btn').on('click', handleResetFilters);

        // Selection
        $('#select-all').on('change', handleSelectAll);
        $(document).on('change', '.suggestion-checkbox', handleSuggestionSelect);

        // Bulk actions
        $('#bulk-accept-btn').on('click', handleBulkAccept);
        $('#bulk-reject-btn').on('click', handleBulkReject);

        // Suggestion actions
        $(document).on('click', '.accept-suggestion-btn', handleAcceptSuggestion);
        $(document).on('click', '.reject-suggestion-btn', handleRejectSuggestion);
        $(document).on('click', '.modify-suggestion-btn', handleModifySuggestion);
        $(document).on('click', '.details-suggestion-btn', handleViewDetails);

        // Pagination
        $('#prev-page-btn').on('click', handlePrevPage);
        $('#next-page-btn').on('click', handleNextPage);

        // Tabs
        $('.tab-button').on('click', handleTabSwitch);

        // Modal
        $('.modal-close').on('click', closeModal);
        $('.modal-overlay').on('click', closeModal);
        $('#modal-accept-btn').on('click', handleModalAccept);
        $('#modal-reject-btn').on('click', handleModalReject);
        $('#modal-modify-btn').on('click', handleModalModify);
        $('#modal-create-relationship-btn').on('click', handleModalCreateRelationship);

        // Learning controls
        $('#trigger-learning-btn').on('click', handleTriggerLearning);
        $('#reset-learning-btn').on('click', handleResetLearning);
    }

    /**
     * Load suggestions with current filters
     */
    function loadSuggestions() {
        showLoading();

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_load_suggestions',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
                page: state.currentPage,
                per_page: sagaSuggestions.settings.perPage,
                status: state.filters.status,
                min_confidence: state.filters.minConfidence,
                relationship_type: state.filters.relationshipType,
                sort_by: state.filters.sortBy,
                sort_order: state.filters.sortOrder,
            },
            success: function(response) {
                if (response.success) {
                    renderSuggestions(response.data.suggestions);
                    updatePagination(response.data.pagination);
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            },
            complete: function() {
                hideLoading();
            }
        });
    }

    /**
     * Render suggestions table
     */
    function renderSuggestions(suggestions) {
        const tbody = $('#suggestions-tbody');
        tbody.empty();

        if (suggestions.length === 0) {
            $('#suggestions-empty').show();
            return;
        }

        $('#suggestions-empty').hide();

        suggestions.forEach(function(suggestion) {
            const row = createSuggestionRow(suggestion);
            tbody.append(row);
        });
    }

    /**
     * Create suggestion table row
     */
    function createSuggestionRow(suggestion) {
        const confidencePct = Math.round(suggestion.confidence_score * 100);
        const confidenceClass = confidencePct >= 80 ? 'high' : (confidencePct >= 60 ? 'medium' : 'low');

        const row = $('<tr>').attr('data-suggestion-id', suggestion.id);

        // Checkbox
        row.append(
            $('<td>').addClass('col-checkbox').append(
                $('<input>')
                    .attr('type', 'checkbox')
                    .addClass('suggestion-checkbox')
                    .val(suggestion.id)
            )
        );

        // Entities
        row.append(
            $('<td>').addClass('col-entities').html(
                `<strong>${escapeHtml(suggestion.source_name)}</strong>
                <span class="entity-type">(${escapeHtml(suggestion.source_type)})</span>
                <br>→<br>
                <strong>${escapeHtml(suggestion.target_name)}</strong>
                <span class="entity-type">(${escapeHtml(suggestion.target_type)})</span>`
            )
        );

        // Relationship Type
        row.append(
            $('<td>').addClass('col-type').html(
                `<span class="relationship-badge">${escapeHtml(suggestion.suggested_type)}</span>`
            )
        );

        // Confidence
        row.append(
            $('<td>').addClass('col-confidence').html(
                `<span class="confidence-badge ${confidenceClass}">${confidencePct}%</span>`
            )
        );

        // Strength
        const strengthPct = (suggestion.suggested_strength / 100) * 100;
        row.append(
            $('<td>').addClass('col-strength').html(
                `<div class="strength-bar">
                    <div class="strength-fill" style="width: ${strengthPct}%"></div>
                </div>
                <span class="strength-value">${suggestion.suggested_strength}</span>`
            )
        );

        // Actions
        const actions = $('<td>').addClass('col-actions');

        actions.append(
            $('<button>')
                .addClass('button button-small accept-suggestion-btn')
                .attr('data-id', suggestion.id)
                .attr('title', 'Accept')
                .html('<span class="dashicons dashicons-yes"></span>')
        );

        actions.append(
            $('<button>')
                .addClass('button button-small reject-suggestion-btn')
                .attr('data-id', suggestion.id)
                .attr('title', 'Reject')
                .html('<span class="dashicons dashicons-no"></span>')
        );

        actions.append(
            $('<button>')
                .addClass('button button-small modify-suggestion-btn')
                .attr('data-id', suggestion.id)
                .attr('title', 'Modify')
                .html('<span class="dashicons dashicons-edit"></span>')
        );

        actions.append(
            $('<button>')
                .addClass('button button-small details-suggestion-btn')
                .attr('data-id', suggestion.id)
                .attr('title', 'Details')
                .html('<span class="dashicons dashicons-info"></span>')
        );

        row.append(actions);

        return row;
    }

    /**
     * Update pagination controls
     */
    function updatePagination(pagination) {
        $('#page-info').text(`Page ${pagination.page} of ${pagination.total_pages}`);

        $('#prev-page-btn').prop('disabled', pagination.page <= 1);
        $('#next-page-btn').prop('disabled', pagination.page >= pagination.total_pages);
    }

    /**
     * Handle saga change
     */
    function handleSagaChange() {
        state.currentSagaId = parseInt($(this).val());
        state.currentPage = 1;
        loadSuggestions();
        loadAnalytics();
        loadLearningStats();
    }

    /**
     * Handle generate suggestions
     */
    function handleGenerateSuggestions() {
        if (state.isGenerating) {
            showToast('Generation already in progress', 'warning');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_generate_suggestions',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successGenerate, 'success');
                    startProgressPolling();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                    btn.prop('disabled', false);
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
                btn.prop('disabled', false);
            }
        });
    }

    /**
     * Start polling for generation progress
     */
    function startProgressPolling() {
        state.isGenerating = true;
        $('#generation-progress').show();

        state.pollInterval = setInterval(function() {
            $.ajax({
                url: sagaSuggestions.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_get_suggestion_progress',
                    nonce: sagaSuggestions.nonce,
                    saga_id: state.currentSagaId,
                },
                success: function(response) {
                    if (response.success) {
                        updateProgress(response.data);

                        if (response.data.status === 'completed' || response.data.status === 'error') {
                            stopProgressPolling();
                            loadSuggestions();
                            loadAnalytics();
                        }
                    }
                }
            });
        }, sagaSuggestions.settings.pollInterval);
    }

    /**
     * Stop polling for progress
     */
    function stopProgressPolling() {
        if (state.pollInterval) {
            clearInterval(state.pollInterval);
            state.pollInterval = null;
        }

        state.isGenerating = false;
        $('#generate-suggestions-btn').prop('disabled', false);

        setTimeout(function() {
            $('#generation-progress').fadeOut();
        }, 2000);
    }

    /**
     * Update progress bar
     */
    function updateProgress(data) {
        const progressPct = data.progress || 0;
        $('.progress-fill').css('width', progressPct + '%');
        $('.progress-text').text(progressPct + '%');

        if (data.error) {
            showToast('Generation error: ' + data.error, 'error');
        }
    }

    /**
     * Handle accept suggestion
     */
    function handleAcceptSuggestion(e) {
        e.preventDefault();

        const suggestionId = parseInt($(this).data('id'));
        const row = $(this).closest('tr');

        // Optimistic update
        row.addClass('accepting');

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_accept_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successAccept, 'success');
                    row.fadeOut(function() {
                        $(this).remove();
                    });
                    loadAnalytics();
                } else {
                    row.removeClass('accepting');
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                row.removeClass('accepting');
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle reject suggestion
     */
    function handleRejectSuggestion(e) {
        e.preventDefault();

        const suggestionId = parseInt($(this).data('id'));
        const row = $(this).closest('tr');

        // Optimistic update
        row.addClass('rejecting');

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_reject_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
                reason: '',
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successReject, 'success');
                    row.fadeOut(function() {
                        $(this).remove();
                    });
                    loadAnalytics();
                } else {
                    row.removeClass('rejecting');
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                row.removeClass('rejecting');
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle modify suggestion
     */
    function handleModifySuggestion(e) {
        e.preventDefault();

        const suggestionId = parseInt($(this).data('id'));

        // Show modal with modification form
        loadSuggestionDetails(suggestionId, function(data) {
            showModifyModal(data);
        });
    }

    /**
     * Handle view details
     */
    function handleViewDetails(e) {
        e.preventDefault();

        const suggestionId = parseInt($(this).data('id'));

        loadSuggestionDetails(suggestionId, function(data) {
            showDetailsModal(data);
        });
    }

    /**
     * Load suggestion details
     */
    function loadSuggestionDetails(suggestionId, callback) {
        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_suggestion_details',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data);
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Show details modal
     */
    function showDetailsModal(data) {
        const modalBody = $('#modal-body');
        modalBody.empty();

        const suggestion = data.suggestion;
        const source = data.source_entity;
        const target = data.target_entity;
        const features = data.features;

        // Entity information
        modalBody.append(`
            <div class="modal-section">
                <h3>Entities</h3>
                <div class="entity-info">
                    <div class="entity-card">
                        <h4>${escapeHtml(source.canonical_name)}</h4>
                        <p><strong>Type:</strong> ${escapeHtml(source.entity_type)}</p>
                        <p><strong>Importance:</strong> ${source.importance_score}/100</p>
                    </div>
                    <div class="arrow">→</div>
                    <div class="entity-card">
                        <h4>${escapeHtml(target.canonical_name)}</h4>
                        <p><strong>Type:</strong> ${escapeHtml(target.entity_type)}</p>
                        <p><strong>Importance:</strong> ${target.importance_score}/100</p>
                    </div>
                </div>
            </div>
        `);

        // Suggestion details
        const confidencePct = Math.round(suggestion.confidence * 100);
        const confidenceClass = confidencePct >= 80 ? 'high' : (confidencePct >= 60 ? 'medium' : 'low');

        modalBody.append(`
            <div class="modal-section">
                <h3>Suggested Relationship</h3>
                <p><strong>Type:</strong> <span class="relationship-badge">${escapeHtml(suggestion.suggested_type)}</span></p>
                <p><strong>Strength:</strong> ${suggestion.suggested_strength}/100</p>
                <p><strong>Confidence:</strong> <span class="confidence-badge ${confidenceClass}">${confidencePct}%</span></p>
                <p><strong>Priority:</strong> ${Math.round(suggestion.priority * 100)}/100</p>
            </div>
        `);

        // AI Reasoning
        if (suggestion.reasoning) {
            modalBody.append(`
                <div class="modal-section">
                    <h3>AI Reasoning</h3>
                    <p class="reasoning-text">${escapeHtml(suggestion.reasoning)}</p>
                </div>
            `);
        }

        // Features breakdown
        if (features && features.length > 0) {
            const featuresHtml = features.map(f => `
                <div class="feature-item">
                    <span class="feature-name">${escapeHtml(f.name)}</span>
                    <div class="feature-bar">
                        <div class="feature-fill" style="width: ${f.value * 100}%"></div>
                    </div>
                    <span class="feature-value">${f.value.toFixed(3)} × ${f.weight.toFixed(2)}</span>
                </div>
            `).join('');

            modalBody.append(`
                <div class="modal-section">
                    <h3>Feature Breakdown</h3>
                    <div class="features-list">
                        ${featuresHtml}
                    </div>
                </div>
            `);
        }

        // Store suggestion ID for modal actions
        $('#suggestion-details-modal').data('suggestion-id', suggestion.id);

        // Show modal
        $('#suggestion-details-modal').fadeIn();
    }

    /**
     * Show modify modal
     */
    function showModifyModal(data) {
        const modalBody = $('#modal-body');
        modalBody.empty();

        const suggestion = data.suggestion;

        modalBody.append(`
            <div class="modal-section">
                <h3>Modify Suggestion</h3>
                <form id="modify-form">
                    <p>
                        <label>Corrected Relationship Type:</label>
                        <select id="corrected-type">
                            <option value="">Keep current (${escapeHtml(suggestion.suggested_type)})</option>
                            <option value="ally">Ally</option>
                            <option value="enemy">Enemy</option>
                            <option value="family">Family</option>
                            <option value="mentor">Mentor</option>
                            <option value="rival">Rival</option>
                            <option value="lover">Lover</option>
                            <option value="friend">Friend</option>
                            <option value="colleague">Colleague</option>
                        </select>
                    </p>
                    <p>
                        <label>Corrected Strength (0-100):</label>
                        <input type="number" id="corrected-strength" min="0" max="100"
                               placeholder="${suggestion.suggested_strength}">
                    </p>
                </form>
            </div>
        `);

        $('#suggestion-details-modal').data('suggestion-id', suggestion.id);
        $('#suggestion-details-modal').fadeIn();
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('#suggestion-details-modal').fadeOut();
    }

    /**
     * Handle modal accept
     */
    function handleModalAccept() {
        const suggestionId = $('#suggestion-details-modal').data('suggestion-id');

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_accept_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successAccept, 'success');
                    closeModal();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle modal reject
     */
    function handleModalReject() {
        const suggestionId = $('#suggestion-details-modal').data('suggestion-id');

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_reject_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successReject, 'success');
                    closeModal();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle modal modify
     */
    function handleModalModify() {
        const suggestionId = $('#suggestion-details-modal').data('suggestion-id');
        const correctedType = $('#corrected-type').val();
        const correctedStrength = parseInt($('#corrected-strength').val()) || 0;

        if (!correctedType && !correctedStrength) {
            showToast('Please specify at least one correction', 'warning');
            return;
        }

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_modify_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
                corrected_type: correctedType,
                corrected_strength: correctedStrength,
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaSuggestions.strings.successModify, 'success');
                    closeModal();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle create relationship from modal
     */
    function handleModalCreateRelationship() {
        const suggestionId = $('#suggestion-details-modal').data('suggestion-id');

        if (!confirm(sagaSuggestions.strings.confirmCreateRelationship)) {
            return;
        }

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_create_relationship_from_suggestion',
                nonce: sagaSuggestions.nonce,
                suggestion_id: suggestionId,
            },
            success: function(response) {
                if (response.success) {
                    showToast('Relationship created successfully', 'success');
                    closeModal();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle bulk accept
     */
    function handleBulkAccept() {
        const count = state.selectedSuggestions.size;

        if (count === 0) {
            return;
        }

        const confirmMsg = sagaSuggestions.strings.confirmBulkAccept.replace('%d', count);
        if (!confirm(confirmMsg)) {
            return;
        }

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_bulk_accept_suggestions',
                nonce: sagaSuggestions.nonce,
                suggestion_ids: Array.from(state.selectedSuggestions),
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    state.selectedSuggestions.clear();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle bulk reject
     */
    function handleBulkReject() {
        const count = state.selectedSuggestions.size;

        if (count === 0) {
            return;
        }

        const confirmMsg = sagaSuggestions.strings.confirmBulkReject.replace('%d', count);
        if (!confirm(confirmMsg)) {
            return;
        }

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_bulk_reject_suggestions',
                nonce: sagaSuggestions.nonce,
                suggestion_ids: Array.from(state.selectedSuggestions),
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    state.selectedSuggestions.clear();
                    loadSuggestions();
                    loadAnalytics();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            }
        });
    }

    /**
     * Handle select all checkbox
     */
    function handleSelectAll() {
        const isChecked = $(this).is(':checked');

        $('.suggestion-checkbox').prop('checked', isChecked).trigger('change');
    }

    /**
     * Handle suggestion checkbox
     */
    function handleSuggestionSelect() {
        const suggestionId = parseInt($(this).val());

        if ($(this).is(':checked')) {
            state.selectedSuggestions.add(suggestionId);
        } else {
            state.selectedSuggestions.delete(suggestionId);
            $('#select-all').prop('checked', false);
        }

        updateBulkActionsBar();
    }

    /**
     * Update bulk actions bar visibility
     */
    function updateBulkActionsBar() {
        const count = state.selectedSuggestions.size;

        if (count > 0) {
            $('#bulk-actions-bar').show();
            $('#selected-count').text(count + ' selected');
        } else {
            $('#bulk-actions-bar').hide();
        }
    }

    /**
     * Handle apply filters
     */
    function handleApplyFilters() {
        state.filters.status = $('#filter-status').val();
        state.filters.minConfidence = parseFloat($('#filter-confidence').val());
        state.filters.relationshipType = $('#filter-type').val();
        state.filters.sortBy = $('#sort-by').val();
        state.filters.sortOrder = $('#sort-order').val();
        state.currentPage = 1;

        loadSuggestions();
    }

    /**
     * Handle reset filters
     */
    function handleResetFilters() {
        $('#filter-status').val('pending');
        $('#filter-confidence').val('0.8');
        $('#filter-type').val('');
        $('#sort-by').val('confidence_score');
        $('#sort-order').val('DESC');

        handleApplyFilters();
    }

    /**
     * Handle pagination
     */
    function handlePrevPage() {
        if (state.currentPage > 1) {
            state.currentPage--;
            loadSuggestions();
        }
    }

    function handleNextPage() {
        state.currentPage++;
        loadSuggestions();
    }

    /**
     * Handle tab switching
     */
    function handleTabSwitch() {
        const tab = $(this).data('tab');

        $('.tab-button').removeClass('active');
        $(this).addClass('active');

        $('.tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');

        if (tab === 'learning') {
            loadLearningDashboard();
        }
    }

    /**
     * Load analytics
     */
    function loadAnalytics() {
        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_suggestion_analytics',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
            },
            success: function(response) {
                if (response.success) {
                    updateAnalytics(response.data);
                }
            }
        });
    }

    /**
     * Update analytics display
     */
    function updateAnalytics(data) {
        // Status counts
        const statusCounts = {};
        data.status_counts.forEach(function(item) {
            statusCounts[item.status] = parseInt(item.count);
        });

        $('#stat-pending').text(statusCounts.pending || 0);
        $('#stat-accepted').text(statusCounts.accepted || 0);
        $('#stat-accuracy').text(data.acceptance_rate.toFixed(1) + '%');
        $('#stat-avg-confidence').text(data.avg_confidence.toFixed(2));

        // Header metrics
        $('#acceptance-rate').text(data.acceptance_rate.toFixed(1) + '%');
        $('#avg-confidence').text(data.avg_confidence.toFixed(2));
    }

    /**
     * Load learning stats
     */
    function loadLearningStats() {
        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_learning_stats',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
            },
            success: function(response) {
                if (response.success) {
                    // Store for learning dashboard
                    window.learningStats = response.data;
                }
            }
        });
    }

    /**
     * Load learning dashboard
     */
    function loadLearningDashboard() {
        if (!window.learningStats) {
            loadLearningStats();
            return;
        }

        // Render charts using Chart.js if available
        if (typeof Chart !== 'undefined') {
            renderLearningCharts(window.learningStats);
        }
    }

    /**
     * Render learning charts
     */
    function renderLearningCharts(stats) {
        // Feature weights chart
        if (stats.feature_weights) {
            const weightsCtx = document.getElementById('weights-canvas');
            if (weightsCtx) {
                new Chart(weightsCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(stats.feature_weights),
                        datasets: [{
                            label: 'Weight',
                            data: Object.values(stats.feature_weights),
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        }

        // Accuracy over time (if data available)
        // Placeholder for now

        // Feedback distribution
        if (stats.total_accepted !== undefined) {
            const feedbackCtx = document.getElementById('feedback-canvas');
            if (feedbackCtx) {
                new Chart(feedbackCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Accepted', 'Rejected', 'Modified'],
                        datasets: [{
                            data: [
                                stats.total_accepted || 0,
                                stats.total_rejected || 0,
                                stats.total_modified || 0
                            ],
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(255, 206, 86, 0.5)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            }
        }
    }

    /**
     * Handle trigger learning
     */
    function handleTriggerLearning() {
        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_trigger_learning_update',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    loadLearningStats();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    }

    /**
     * Handle reset learning
     */
    function handleResetLearning() {
        if (!confirm(sagaSuggestions.strings.confirmResetLearning)) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: sagaSuggestions.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_reset_learning',
                nonce: sagaSuggestions.nonce,
                saga_id: state.currentSagaId,
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    loadLearningStats();
                } else {
                    showToast(response.data.message || sagaSuggestions.strings.errorGeneric, 'error');
                }
            },
            error: function() {
                showToast(sagaSuggestions.strings.errorGeneric, 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    }

    /**
     * Setup keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Only if not in input field
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            // j/k for navigation (not implemented yet)
            // a for accept (not implemented yet)
            // r for reject (not implemented yet)
        });
    }

    /**
     * Show loading state
     */
    function showLoading() {
        $('#suggestions-loading').show();
        $('#suggestions-empty').hide();
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('#suggestions-loading').hide();
    }

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        type = type || 'info';

        const toast = $('<div>')
            .addClass('toast toast-' + type)
            .text(message);

        $('#toast-container').append(toast);

        setTimeout(function() {
            toast.addClass('show');
        }, 10);

        setTimeout(function() {
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }

})(jQuery);
