/**
 * Real-Time Consistency Checker for WordPress Editor
 *
 * Monitors entity edits and provides inline consistency warnings
 * Works with both Classic Editor and Gutenberg
 *
 * @package SagaManager
 * @version 1.4.0
 */

(function($) {
    'use strict';

    /**
     * Real-Time Consistency Checker
     */
    const SagaConsistencyChecker = {
        // State
        entityId: null,
        checkTimer: null,
        lastCheckContent: '',
        isChecking: false,
        lastCheckTime: 0,
        cachedResult: null,

        // Configuration
        checkDelay: 5000, // 5 seconds after typing stops
        minCheckInterval: 10000, // Minimum 10 seconds between checks
        cacheLifetime: 60000, // Cache results for 60 seconds

        /**
         * Initialize checker
         */
        init: function() {
            if (typeof sagaConsistency === 'undefined') {
                return;
            }

            this.entityId = sagaConsistency.entityId;

            if (!this.entityId) {
                return;
            }

            this.bindEvents();
            this.createCheckButton();
            this.createToastContainer();

            // Initial check
            this.scheduleCheck();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Gutenberg editor
            if (wp && wp.data) {
                const { subscribe, select } = wp.data;

                let previousContent = '';

                subscribe(() => {
                    const editor = select('core/editor');
                    if (!editor) return;

                    const currentContent = editor.getEditedPostContent();

                    if (currentContent !== previousContent) {
                        previousContent = currentContent;
                        self.scheduleCheck();
                    }
                });
            }

            // Classic Editor
            if ($('#content').length) {
                $('#content').on('input', function() {
                    self.scheduleCheck();
                });
            }

            // Manual check button
            $(document).on('click', '.saga-check-now', function(e) {
                e.preventDefault();
                self.checkNow();
            });

            // Dismiss toast
            $(document).on('click', '.saga-toast-close', function() {
                $(this).closest('.saga-consistency-toast').fadeOut(function() {
                    $(this).remove();
                });
            });

            // View details
            $(document).on('click', '.saga-toast-view-details', function(e) {
                e.preventDefault();
                const issueId = $(this).data('issue-id');
                self.showIssueDetails(issueId);
            });
        },

        /**
         * Create manual check button
         */
        createCheckButton: function() {
            const button = $('<button>')
                .addClass('button saga-check-now')
                .html('<span class="dashicons dashicons-shield-alt"></span> Check Consistency')
                .attr('type', 'button');

            // Gutenberg
            if ($('.edit-post-header__settings').length) {
                $('.edit-post-header__settings').prepend(button);
            }
            // Classic Editor
            else if ($('#publishing-action').length) {
                $('#publishing-action').before(
                    $('<div>')
                        .attr('id', 'saga-consistency-action')
                        .addClass('misc-pub-section')
                        .append(button)
                );
            }
        },

        /**
         * Create toast notification container
         */
        createToastContainer: function() {
            if (!$('#saga-toast-container').length) {
                $('body').append('<div id="saga-toast-container"></div>');
            }
        },

        /**
         * Schedule consistency check
         */
        scheduleCheck: function() {
            const now = Date.now();

            // Clear existing timer
            if (this.checkTimer) {
                clearTimeout(this.checkTimer);
            }

            // Check if cached result is still valid
            if (this.cachedResult && (now - this.lastCheckTime) < this.cacheLifetime) {
                return;
            }

            // Schedule new check
            this.checkTimer = setTimeout(() => {
                // Respect minimum interval
                if ((now - this.lastCheckTime) >= this.minCheckInterval) {
                    this.runCheck();
                }
            }, this.checkDelay);
        },

        /**
         * Check now (manual trigger)
         */
        checkNow: function() {
            clearTimeout(this.checkTimer);
            this.runCheck(true);
        },

        /**
         * Run consistency check
         */
        runCheck: function(isManual = false) {
            if (this.isChecking) {
                return;
            }

            const self = this;
            const content = this.getEditorContent();

            // Don't check if content hasn't changed
            if (!isManual && content === this.lastCheckContent) {
                return;
            }

            this.isChecking = true;
            this.lastCheckContent = content;
            this.lastCheckTime = Date.now();

            // Show loading indicator
            this.showLoadingIndicator();

            $.ajax({
                url: sagaConsistency.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_check_entity_realtime',
                    nonce: sagaConsistency.nonce,
                    entity_id: this.entityId,
                    content: content
                },
                success: function(response) {
                    self.hideLoadingIndicator();

                    if (response.success) {
                        self.cachedResult = response.data;
                        self.handleCheckResult(response.data);

                        if (isManual) {
                            self.showToast(
                                'Consistency check complete',
                                `Score: ${response.data.score}% (${response.data.issues_count} issues found)`,
                                self.getScoreSeverity(response.data.score)
                            );
                        }
                    } else {
                        self.showToast(
                            'Check failed',
                            response.data?.message || 'An error occurred',
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    self.hideLoadingIndicator();
                    console.error('Consistency check error:', error);

                    if (isManual) {
                        self.showToast(
                            'Check failed',
                            'Unable to connect to the server',
                            'error'
                        );
                    }
                },
                complete: function() {
                    self.isChecking = false;
                }
            });
        },

        /**
         * Get editor content
         */
        getEditorContent: function() {
            // Gutenberg
            if (wp && wp.data) {
                const editor = wp.data.select('core/editor');
                if (editor) {
                    return editor.getEditedPostContent();
                }
            }

            // Classic Editor
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                return tinymce.activeEditor.getContent();
            }

            return $('#content').val() || '';
        },

        /**
         * Handle check result
         */
        handleCheckResult: function(data) {
            // Update score badge if exists
            this.updateScoreBadge(data.score, data.status);

            // Show critical issues as toasts
            if (data.has_critical && data.critical_issues.length > 0) {
                data.critical_issues.forEach(issue => {
                    this.showIssueToast(issue);
                });
            }

            // Trigger custom event for Gutenberg panel
            $(document).trigger('saga:consistency-updated', [data]);
        },

        /**
         * Update score badge
         */
        updateScoreBadge: function(score, status) {
            let $badge = $('.saga-consistency-badge');

            if (!$badge.length) {
                $badge = $('<div>')
                    .addClass('saga-consistency-badge')
                    .insertAfter('.saga-check-now');
            }

            $badge
                .removeClass('excellent good fair poor')
                .addClass(status)
                .html(`
                    <span class="score-value">${score}%</span>
                    <span class="score-label">${this.getScoreLabel(status)}</span>
                `);
        },

        /**
         * Show issue toast notification
         */
        showIssueToast: function(issue) {
            const severity = issue.severity || 'info';
            const description = issue.description || 'Unknown issue';
            const issueId = issue.id;

            this.showToast(
                `${this.getSeverityLabel(severity)} Issue`,
                description,
                severity,
                true,
                issueId
            );
        },

        /**
         * Show toast notification
         */
        showToast: function(title, message, severity = 'info', persistent = false, issueId = null) {
            const $toast = $('<div>')
                .addClass(`saga-consistency-toast ${severity}`)
                .attr('role', 'alert')
                .html(`
                    <span class="dashicons ${this.getSeverityIcon(severity)}"></span>
                    <div class="toast-content">
                        <strong>${title}</strong>
                        <p>${message}</p>
                        ${issueId ? `<a href="#" class="saga-toast-view-details" data-issue-id="${issueId}">View Details</a>` : ''}
                    </div>
                    <button class="toast-close" aria-label="Dismiss">×</button>
                `);

            $('#saga-toast-container').append($toast);

            // Auto-dismiss after 5 seconds unless persistent
            if (!persistent) {
                setTimeout(() => {
                    $toast.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Announce to screen readers
            this.announceToScreenReader(`${title}: ${message}`);
        },

        /**
         * Show loading indicator
         */
        showLoadingIndicator: function() {
            $('.saga-check-now')
                .prop('disabled', true)
                .find('.dashicons')
                .addClass('saga-spinner');
        },

        /**
         * Hide loading indicator
         */
        hideLoadingIndicator: function() {
            $('.saga-check-now')
                .prop('disabled', false)
                .find('.dashicons')
                .removeClass('saga-spinner');
        },

        /**
         * Show issue details modal
         */
        showIssueDetails: function(issueId) {
            // TODO: Implement modal with full issue details
            console.log('Show issue details:', issueId);
        },

        /**
         * Get score severity level
         */
        getScoreSeverity: function(score) {
            if (score >= 90) return 'success';
            if (score >= 75) return 'info';
            if (score >= 50) return 'warning';
            return 'error';
        },

        /**
         * Get score label
         */
        getScoreLabel: function(status) {
            const labels = {
                excellent: 'Excellent',
                good: 'Good',
                fair: 'Fair',
                poor: 'Poor'
            };
            return labels[status] || 'Unknown';
        },

        /**
         * Get severity label
         */
        getSeverityLabel: function(severity) {
            const labels = {
                critical: 'Critical',
                high: 'High',
                medium: 'Medium',
                low: 'Low',
                info: 'Info'
            };
            return labels[severity] || 'Unknown';
        },

        /**
         * Get severity icon
         */
        getSeverityIcon: function(severity) {
            const icons = {
                critical: 'dashicons-warning',
                high: 'dashicons-warning',
                medium: 'dashicons-info',
                low: 'dashicons-info',
                info: 'dashicons-info',
                error: 'dashicons-dismiss',
                success: 'dashicons-yes-alt'
            };
            return icons[severity] || 'dashicons-info';
        },

        /**
         * Announce to screen readers
         */
        announceToScreenReader: function(message) {
            let $announcer = $('#saga-sr-announcer');

            if (!$announcer.length) {
                $announcer = $('<div>')
                    .attr({
                        id: 'saga-sr-announcer',
                        'aria-live': 'polite',
                        'aria-atomic': 'true',
                        role: 'status'
                    })
                    .css({
                        position: 'absolute',
                        left: '-10000px',
                        width: '1px',
                        height: '1px',
                        overflow: 'hidden'
                    })
                    .appendTo('body');
            }

            $announcer.text(message);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SagaConsistencyChecker.init();
    });

    // Export to global scope for Gutenberg
    window.SagaConsistencyChecker = SagaConsistencyChecker;

})(jQuery);
