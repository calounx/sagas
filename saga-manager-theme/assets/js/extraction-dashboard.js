/**
 * Entity Extraction Dashboard
 *
 * Frontend JavaScript for the entity extraction admin interface.
 * Handles form submission, progress polling, entity preview, and all user interactions.
 *
 * @package SagaManager
 * @subpackage Admin\Assets
 * @since 1.4.0
 */

(function($) {
    'use strict';

    // State
    let currentJobId = null;
    let progressInterval = null;
    let currentPage = 1;
    let selectedEntities = [];

    // Initialize on document ready
    $(document).ready(function() {
        initializeEventHandlers();
        loadJobHistory();
        initializeCharacterCounter();
    });

    /**
     * Initialize all event handlers
     */
    function initializeEventHandlers() {
        // Form submission
        $('#saga-extraction-form').on('submit', handleExtractionStart);

        // Advanced settings toggle
        $('#toggle-advanced').on('click', toggleAdvancedSettings);

        // Cancel job
        $('#cancel-job-btn').on('click', handleCancelJob);

        // Filters
        $('#apply-filters-btn').on('click', loadEntities);

        // Bulk actions
        $('#select-all-entities').on('change', handleSelectAll);
        $('#bulk-approve-btn').on('click', handleBulkApprove);
        $('#bulk-reject-btn').on('click', handleBulkReject);
        $('#create-approved-btn').on('click', handleBatchCreate);

        // Entity actions (delegated)
        $(document).on('click', '.approve-entity-btn', handleApproveEntity);
        $(document).on('click', '.reject-entity-btn', handleRejectEntity);
        $(document).on('click', '.resolve-duplicate-btn', handleResolveDuplicate);
        $(document).on('change', '.entity-select', handleEntitySelect);

        // Modal
        $('.saga-modal-close').on('click', closeModal);
        $('#confirm-duplicate-btn').on('click', confirmDuplicate);
        $('#mark-unique-btn').on('click', markUnique);

        // Pagination (delegated)
        $(document).on('click', '.page-link', handlePageChange);

        // Job history
        $('#load-more-jobs-btn').on('click', loadMoreJobs);
        $(document).on('click', '.load-job-btn', handleLoadJob);

        // Cost estimation (debounced)
        let estimateTimeout;
        $('#source-text').on('input', function() {
            clearTimeout(estimateTimeout);
            estimateTimeout = setTimeout(estimateExtractionCost, sagaExtraction.settings.debounceDelay);
        });
    }

    /**
     * Initialize character counter
     */
    function initializeCharacterCounter() {
        $('#source-text').on('input', function() {
            const count = $(this).val().length;
            $('#char-count').text(count.toLocaleString());

            if (count > sagaExtraction.settings.maxTextLength) {
                $('#char-count').css('color', 'red');
            } else {
                $('#char-count').css('color', '');
            }
        });
    }

    /**
     * Toggle advanced settings
     */
    function toggleAdvancedSettings() {
        const $settings = $('#advanced-settings');
        const $button = $('#toggle-advanced');

        if ($settings.is(':visible')) {
            $settings.slideUp();
            $button.text('Show Advanced Settings');
        } else {
            $settings.slideDown();
            $button.text('Hide Advanced Settings');
        }
    }

    /**
     * Estimate extraction cost
     */
    function estimateExtractionCost() {
        const text = $('#source-text').val();

        if (!text || text.trim().length === 0) {
            $('#cost-estimation').hide();
            return;
        }

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_estimate_extraction_cost',
                nonce: sagaExtraction.nonce,
                source_text: text
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $('#est-tokens').text(data.total_tokens.toLocaleString());
                    $('#est-cost').text(data.estimated_cost_usd.toFixed(4));
                    $('#est-time').text(data.processing_time_seconds);
                    $('#est-entities').text(data.estimated_entities);
                    $('#cost-estimation').fadeIn();
                }
            },
            error: function() {
                // Silently fail for estimation
            }
        });
    }

    /**
     * Handle extraction start
     */
    function handleExtractionStart(e) {
        e.preventDefault();

        const sagaId = $('#saga-select').val();
        const sourceText = $('#source-text').val();
        const chunkSize = $('#chunk-size').val();
        const aiProvider = $('#ai-provider').val();
        const aiModel = $('#ai-model').val();

        // Validation
        if (!sagaId) {
            showToast(sagaExtraction.i18n.error, 'Please select a saga', 'error');
            return;
        }

        if (!sourceText || sourceText.trim().length === 0) {
            showToast(sagaExtraction.i18n.error, 'Please enter source text', 'error');
            return;
        }

        if (sourceText.length > sagaExtraction.settings.maxTextLength) {
            showToast(sagaExtraction.i18n.error, 'Text exceeds maximum length', 'error');
            return;
        }

        // Disable form
        $('#start-extraction-btn').prop('disabled', true).text(sagaExtraction.i18n.loading);

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_start_extraction',
                nonce: sagaExtraction.nonce,
                saga_id: sagaId,
                source_text: sourceText,
                chunk_size: chunkSize,
                source_type: 'manual',
                ai_provider: aiProvider,
                ai_model: aiModel
            },
            success: function(response) {
                if (response.success) {
                    currentJobId = response.data.job_id;
                    showToast(sagaExtraction.i18n.extractionStarted, `Job #${currentJobId} started`, 'success');

                    // Show progress section
                    $('#progress-section').slideDown();

                    // Start polling
                    startProgressPolling();

                    // Scroll to progress
                    $('html, body').animate({
                        scrollTop: $('#progress-section').offset().top - 50
                    }, 500);
                } else {
                    showToast(sagaExtraction.i18n.extractionFailed, response.data.message, 'error');
                    $('#start-extraction-btn').prop('disabled', false).text('Start Extraction');
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.data?.message || 'Unknown error';
                showToast(sagaExtraction.i18n.extractionFailed, message, 'error');
                $('#start-extraction-btn').prop('disabled', false).text('Start Extraction');
            }
        });
    }

    /**
     * Start progress polling
     */
    function startProgressPolling() {
        if (progressInterval) {
            clearInterval(progressInterval);
        }

        progressInterval = setInterval(pollProgress, sagaExtraction.settings.pollInterval);
        pollProgress(); // Immediate first poll
    }

    /**
     * Stop progress polling
     */
    function stopProgressPolling() {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    }

    /**
     * Poll job progress
     */
    function pollProgress() {
        if (!currentJobId) {
            stopProgressPolling();
            return;
        }

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_extraction_progress',
                nonce: sagaExtraction.nonce,
                job_id: currentJobId
            },
            success: function(response) {
                if (response.success) {
                    updateProgressDisplay(response.data);

                    // Stop polling if complete
                    if (response.data.is_complete) {
                        stopProgressPolling();
                        onExtractionComplete(response.data);
                    }
                }
            },
            error: function() {
                stopProgressPolling();
            }
        });
    }

    /**
     * Update progress display
     */
    function updateProgressDisplay(data) {
        $('#progress-percent').text(Math.round(data.progress_percent));
        $('#progress-bar-fill').css('width', data.progress_percent + '%');
        $('#progress-message').text(data.status);
        $('#job-status').text(data.status);
        $('#entities-found').text(data.entities_found);
        $('#pending-review').text(data.pending_review);
        $('#approved-count').text(data.approved);

        // Update status color
        const statusColors = {
            pending: '#999',
            processing: '#0073aa',
            completed: '#46b450',
            failed: '#dc3232',
            cancelled: '#999'
        };

        $('#job-status').css('color', statusColors[data.status] || '#999');
    }

    /**
     * Handle extraction completion
     */
    function onExtractionComplete(data) {
        $('#start-extraction-btn').prop('disabled', false).text('Start Extraction');

        if (data.is_successful) {
            showToast('Extraction Complete', `Found ${data.entities_found} entities`, 'success');

            // Load entities for review
            loadEntities();

            // Show preview section
            $('#preview-section').slideDown();

            // Scroll to preview
            setTimeout(() => {
                $('html, body').animate({
                    scrollTop: $('#preview-section').offset().top - 50
                }, 500);
            }, 500);
        } else {
            showToast('Extraction Failed', data.error_message || 'Unknown error', 'error');
        }

        // Reload job history
        loadJobHistory();
    }

    /**
     * Load entities for preview
     */
    function loadEntities(page = 1) {
        if (!currentJobId) return;

        const filterType = $('#filter-type').val();
        const filterStatus = $('#filter-status').val();
        const filterConfidence = $('#filter-confidence').val();

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_load_extracted_entities',
                nonce: sagaExtraction.nonce,
                job_id: currentJobId,
                page: page,
                per_page: sagaExtraction.settings.entitiesPerPage,
                filter_type: filterType,
                filter_status: filterStatus,
                filter_confidence: filterConfidence
            },
            success: function(response) {
                if (response.success) {
                    renderEntities(response.data.entities);
                    renderPagination(response.data.pagination);
                    currentPage = page;
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to load entities', 'error');
            }
        });
    }

    /**
     * Render entities grid
     */
    function renderEntities(entities) {
        const $grid = $('#entities-grid');
        $grid.empty();

        if (entities.length === 0) {
            $grid.html('<p style="text-align: center; padding: 40px;">No entities found</p>');
            return;
        }

        entities.forEach(entity => {
            const card = renderEntityCard(entity);
            $grid.append(card);
        });
    }

    /**
     * Render single entity card
     */
    function renderEntityCard(entity) {
        const confidenceLevel = entity.confidence_score >= 0.8 ? 'high' :
                               entity.confidence_score >= 0.6 ? 'medium' : 'low';
        const confidencePercent = Math.round(entity.confidence_score * 100);

        let attributesHtml = '';
        if (entity.attributes && Object.keys(entity.attributes).length > 0) {
            attributesHtml = '<div class="entity-attributes">';
            for (const [key, value] of Object.entries(entity.attributes)) {
                attributesHtml += `<div class="attribute"><strong>${escapeHtml(key)}:</strong> ${escapeHtml(value)}</div>`;
            }
            attributesHtml += '</div>';
        }

        let duplicatesHtml = '';
        if (entity.duplicates && entity.duplicates.length > 0) {
            duplicatesHtml = `
                <div class="duplicates-list">
                    <strong>Potential Duplicates (${entity.duplicates.length}):</strong>
                    <ul>
            `;
            entity.duplicates.forEach(dup => {
                const simPercent = Math.round(dup.similarity_score * 100);
                duplicatesHtml += `
                    <li>
                        ${escapeHtml(dup.existing_entity_name)} (${simPercent}% similar)
                        <button type="button" class="button button-small resolve-duplicate-btn" data-duplicate-id="${dup.id}">
                            Resolve
                        </button>
                    </li>
                `;
            });
            duplicatesHtml += '</ul></div>';
        }

        const statusClass = entity.status === 'approved' ? 'status-approved' :
                           entity.status === 'rejected' ? 'status-rejected' : '';

        return `
            <div class="entity-card ${statusClass}" data-entity-id="${entity.id}" data-status="${entity.status}">
                <div class="entity-card-header">
                    <label class="entity-checkbox">
                        <input type="checkbox" class="entity-select" value="${entity.id}">
                    </label>
                    <span class="entity-type-badge entity-type-${entity.entity_type}">${entity.entity_type}</span>
                    <span class="confidence-badge confidence-${confidenceLevel}">${confidencePercent}%</span>
                </div>
                <div class="entity-card-body">
                    <h3 class="entity-name">${escapeHtml(entity.canonical_name)}</h3>
                    ${entity.description ? `<p class="entity-description">${escapeHtml(entity.description)}</p>` : ''}
                    ${attributesHtml}
                    ${entity.context_snippet ? `
                        <div class="entity-context">
                            <strong>Context:</strong>
                            <p class="context-snippet">${escapeHtml(entity.context_snippet)}</p>
                        </div>
                    ` : ''}
                    ${duplicatesHtml}
                </div>
                <div class="entity-card-footer">
                    <button type="button" class="button button-primary approve-entity-btn" data-entity-id="${entity.id}">
                        Approve
                    </button>
                    <button type="button" class="button button-secondary reject-entity-btn" data-entity-id="${entity.id}">
                        Reject
                    </button>
                    <span class="entity-status-text">${entity.status}</span>
                </div>
            </div>
        `;
    }

    /**
     * Render pagination
     */
    function renderPagination(pagination) {
        const $controls = $('#pagination-controls');
        $controls.empty();

        if (pagination.total_pages <= 1) {
            return;
        }

        let html = '<div class="pagination">';

        // Previous
        if (pagination.current_page > 1) {
            html += `<button class="page-link" data-page="${pagination.current_page - 1}">« Previous</button>`;
        }

        // Page numbers
        for (let i = 1; i <= pagination.total_pages; i++) {
            const active = i === pagination.current_page ? 'active' : '';
            html += `<button class="page-link ${active}" data-page="${i}">${i}</button>`;
        }

        // Next
        if (pagination.current_page < pagination.total_pages) {
            html += `<button class="page-link" data-page="${pagination.current_page + 1}">Next »</button>`;
        }

        html += '</div>';
        $controls.html(html);
    }

    /**
     * Handle page change
     */
    function handlePageChange(e) {
        const page = parseInt($(this).data('page'));
        loadEntities(page);

        // Scroll to top of preview
        $('html, body').animate({
            scrollTop: $('#preview-section').offset().top - 50
        }, 300);
    }

    /**
     * Handle select all
     */
    function handleSelectAll() {
        const checked = $(this).is(':checked');
        $('.entity-select').prop('checked', checked);
        updateSelectedEntities();
    }

    /**
     * Handle entity select
     */
    function handleEntitySelect() {
        updateSelectedEntities();
    }

    /**
     * Update selected entities array
     */
    function updateSelectedEntities() {
        selectedEntities = $('.entity-select:checked').map(function() {
            return parseInt($(this).val());
        }).get();
    }

    /**
     * Handle approve entity
     */
    function handleApproveEntity() {
        const entityId = parseInt($(this).data('entity-id'));
        const $card = $(`.entity-card[data-entity-id="${entityId}"]`);

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_approve_entity',
                nonce: sagaExtraction.nonce,
                entity_id: entityId
            },
            success: function(response) {
                if (response.success) {
                    $card.addClass('status-approved').attr('data-status', 'approved');
                    $card.find('.entity-status-text').text('approved');
                    showToast(sagaExtraction.i18n.entityApproved, '', 'success');

                    // Update progress stats
                    pollProgress();
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to approve entity', 'error');
            }
        });
    }

    /**
     * Handle reject entity
     */
    function handleRejectEntity() {
        const entityId = parseInt($(this).data('entity-id'));
        const $card = $(`.entity-card[data-entity-id="${entityId}"]`);

        if (!confirm(sagaExtraction.i18n.confirmReject)) {
            return;
        }

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_reject_entity',
                nonce: sagaExtraction.nonce,
                entity_id: entityId
            },
            success: function(response) {
                if (response.success) {
                    $card.addClass('status-rejected').attr('data-status', 'rejected');
                    $card.find('.entity-status-text').text('rejected');
                    showToast(sagaExtraction.i18n.entityRejected, '', 'success');

                    // Update progress stats
                    pollProgress();
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to reject entity', 'error');
            }
        });
    }

    /**
     * Handle bulk approve
     */
    function handleBulkApprove() {
        if (selectedEntities.length === 0) {
            showToast(sagaExtraction.i18n.error, sagaExtraction.i18n.noEntitiesSelected, 'error');
            return;
        }

        if (!confirm(sagaExtraction.i18n.confirmBulkApprove)) {
            return;
        }

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_bulk_approve_entities',
                nonce: sagaExtraction.nonce,
                entity_ids: selectedEntities
            },
            success: function(response) {
                if (response.success) {
                    showToast('Success', `${response.data.approved_count} entities approved`, 'success');
                    loadEntities(currentPage);
                    pollProgress();
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to approve entities', 'error');
            }
        });
    }

    /**
     * Handle bulk reject
     */
    function handleBulkReject() {
        if (selectedEntities.length === 0) {
            showToast(sagaExtraction.i18n.error, sagaExtraction.i18n.noEntitiesSelected, 'error');
            return;
        }

        if (!confirm(sagaExtraction.i18n.confirmBulkReject)) {
            return;
        }

        // Use same endpoint as approve but reject
        // (Implementation would need bulk reject endpoint)
        showToast('Info', 'Bulk reject not yet implemented', 'info');
    }

    /**
     * Handle batch create approved entities
     */
    function handleBatchCreate() {
        if (!currentJobId) return;

        if (!confirm(sagaExtraction.i18n.confirmCreate)) {
            return;
        }

        $('#create-approved-btn').prop('disabled', true).text(sagaExtraction.i18n.loading);

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_batch_create_approved',
                nonce: sagaExtraction.nonce,
                job_id: currentJobId,
                entity_ids: [] // Empty = all approved
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaExtraction.i18n.entitiesCreated,
                             `Created ${response.data.created} entities`, 'success');
                    loadEntities(currentPage);
                    pollProgress();
                    loadJobHistory();
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
                $('#create-approved-btn').prop('disabled', false).text('Create Approved Entities');
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to create entities', 'error');
                $('#create-approved-btn').prop('disabled', false).text('Create Approved Entities');
            }
        });
    }

    /**
     * Handle cancel job
     */
    function handleCancelJob() {
        if (!currentJobId) return;

        if (!confirm(sagaExtraction.i18n.confirmCancel)) {
            return;
        }

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_cancel_extraction_job',
                nonce: sagaExtraction.nonce,
                job_id: currentJobId
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaExtraction.i18n.jobCancelled, '', 'success');
                    stopProgressPolling();
                    loadJobHistory();
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to cancel job', 'error');
            }
        });
    }

    /**
     * Handle resolve duplicate
     */
    function handleResolveDuplicate() {
        const duplicateId = parseInt($(this).data('duplicate-id'));

        // Store for modal
        $('#duplicate-modal').data('duplicate-id', duplicateId);

        // Show modal
        $('#duplicate-modal').fadeIn();
    }

    /**
     * Confirm duplicate
     */
    function confirmDuplicate() {
        const duplicateId = $('#duplicate-modal').data('duplicate-id');

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_resolve_duplicate',
                nonce: sagaExtraction.nonce,
                duplicate_id: duplicateId,
                action_type: 'confirmed_duplicate'
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaExtraction.i18n.duplicateResolved, '', 'success');
                    closeModal();
                    loadEntities(currentPage);
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to resolve duplicate', 'error');
            }
        });
    }

    /**
     * Mark as unique
     */
    function markUnique() {
        const duplicateId = $('#duplicate-modal').data('duplicate-id');

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_resolve_duplicate',
                nonce: sagaExtraction.nonce,
                duplicate_id: duplicateId,
                action_type: 'marked_unique'
            },
            success: function(response) {
                if (response.success) {
                    showToast(sagaExtraction.i18n.duplicateResolved, '', 'success');
                    closeModal();
                    loadEntities(currentPage);
                } else {
                    showToast(sagaExtraction.i18n.error, response.data.message, 'error');
                }
            },
            error: function(xhr) {
                showToast(sagaExtraction.i18n.error, 'Failed to resolve duplicate', 'error');
            }
        });
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('#duplicate-modal').fadeOut();
    }

    /**
     * Load job history
     */
    function loadJobHistory() {
        const sagaId = $('#saga-select').val() || 0;

        $.ajax({
            url: sagaExtraction.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_load_job_history',
                nonce: sagaExtraction.nonce,
                saga_id: sagaId,
                page: 1,
                per_page: 10
            },
            success: function(response) {
                if (response.success) {
                    renderJobHistory(response.data.jobs);
                }
            },
            error: function() {
                // Silently fail
            }
        });
    }

    /**
     * Render job history table
     */
    function renderJobHistory(jobs) {
        const $tbody = $('#job-history-tbody');
        $tbody.empty();

        if (jobs.length === 0) {
            $tbody.html('<tr><td colspan="8" style="text-align: center; padding: 20px;">No extraction jobs yet</td></tr>');
            return;
        }

        jobs.forEach(job => {
            const statusColor = {
                pending: '#999',
                processing: '#0073aa',
                completed: '#46b450',
                failed: '#dc3232',
                cancelled: '#999'
            }[job.status] || '#999';

            const date = new Date(job.created_at * 1000);
            const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();

            $tbody.append(`
                <tr>
                    <td><strong>#${job.id}</strong></td>
                    <td><span style="color: ${statusColor};">${job.status}</span></td>
                    <td>${job.total_entities_found}</td>
                    <td>${job.entities_created}</td>
                    <td>${job.entities_rejected}</td>
                    <td>${job.duplicates_found}</td>
                    <td>${dateStr}</td>
                    <td>
                        <button type="button" class="button button-small load-job-btn" data-job-id="${job.id}">
                            Load
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    /**
     * Handle load job
     */
    function handleLoadJob() {
        currentJobId = parseInt($(this).data('job-id'));
        loadEntities(1);
        $('#preview-section').slideDown();

        $('html, body').animate({
            scrollTop: $('#preview-section').offset().top - 50
        }, 500);
    }

    /**
     * Load more jobs
     */
    function loadMoreJobs() {
        // Implementation for pagination
        showToast('Info', 'Load more not yet implemented', 'info');
    }

    /**
     * Show toast notification
     */
    function showToast(title, message, type = 'info') {
        const $container = $('#toast-container');

        const typeColors = {
            success: '#46b450',
            error: '#dc3232',
            warning: '#ffb900',
            info: '#0073aa'
        };

        const color = typeColors[type] || typeColors.info;

        const $toast = $(`
            <div class="saga-toast" style="background-color: ${color};">
                <strong>${escapeHtml(title)}</strong>
                ${message ? `<p>${escapeHtml(message)}</p>` : ''}
            </div>
        `);

        $container.append($toast);

        setTimeout(() => {
            $toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
