/**
 * Classic Editor Consistency Integration
 *
 * Meta box and inline checks for Classic WordPress Editor
 * Provides same functionality as Gutenberg panel
 *
 * @package SagaManager
 * @version 1.4.0
 */

(function($) {
    'use strict';

    /**
     * Classic Editor Consistency Handler
     */
    const ClassicConsistency = {
        // State
        entityId: null,
        issues: [],
        score: null,
        status: null,
        isChecking: false,

        /**
         * Initialize
         */
        init: function() {
            if (typeof sagaConsistency === 'undefined' || !sagaConsistency.entityId) {
                return;
            }

            this.entityId = sagaConsistency.entityId;
            this.createMetaBox();
            this.bindEvents();
            this.loadIssues();
        },

        /**
         * Create meta box
         */
        createMetaBox: function() {
            const metaBox = `
                <div id="saga-consistency-metabox" class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span class="dashicons dashicons-shield-alt"></span>
                            Consistency Check
                        </h2>
                        <div class="handle-actions">
                            <button type="button" class="handlediv" aria-expanded="true">
                                <span class="screen-reader-text">Toggle panel: Consistency Check</span>
                                <span class="toggle-indicator" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="inside">
                        <div id="saga-score-container"></div>

                        <div class="saga-actions" style="margin: 12px 0;">
                            <button type="button" class="button button-primary saga-check-now-classic" style="width: 100%;">
                                <span class="dashicons dashicons-update"></span> Check Now
                            </button>
                        </div>

                        <div id="saga-last-check" style="font-size: 12px; color: #666; margin-bottom: 12px;"></div>

                        <div id="saga-issues-container"></div>

                        <div id="saga-loading" style="display: none; text-align: center; padding: 20px;">
                            <span class="spinner is-active" style="float: none;"></span>
                            <p>Checking consistency...</p>
                        </div>

                        <div class="saga-info" style="margin-top: 12px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px;">
                            <strong>About:</strong> Consistency checks run automatically.
                            <a href="${sagaConsistency.dashboardUrl || '#'}" target="_blank">View Full Dashboard</a>
                        </div>
                    </div>
                </div>
            `;

            // Insert after title
            if ($('#titlediv').length) {
                $(metaBox).insertAfter('#titlediv');
            } else if ($('.editor-post-title').length) {
                $(metaBox).insertAfter('.editor-post-title');
            } else {
                $('#poststuff').prepend(metaBox);
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Manual check
            $(document).on('click', '.saga-check-now-classic', function(e) {
                e.preventDefault();
                self.checkNow();
            });

            // Resolve issue
            $(document).on('click', '.saga-resolve-classic', function(e) {
                e.preventDefault();
                const issueId = $(this).data('issue-id');
                self.resolveIssue(issueId);
            });

            // Dismiss issue
            $(document).on('click', '.saga-dismiss-classic', function(e) {
                e.preventDefault();
                const issueId = $(this).data('issue-id');
                self.dismissIssue(issueId);
            });

            // Toggle issue details
            $(document).on('click', '.saga-issue-toggle', function(e) {
                e.preventDefault();
                $(this).next('.saga-issue-details').slideToggle();
                $(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
            });

            // Listen for real-time updates
            $(document).on('saga:consistency-updated', function(event, data) {
                self.updateScore(data.score, data.status);
            });
        },

        /**
         * Load issues from server
         */
        loadIssues: function() {
            const self = this;

            this.showLoading(true);

            $.ajax({
                url: sagaConsistency.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_get_entity_issues',
                    nonce: sagaConsistency.nonce,
                    entity_id: this.entityId
                },
                success: function(response) {
                    self.showLoading(false);

                    if (response.success) {
                        self.issues = response.data.issues || [];
                        self.renderIssues();
                        self.updateLastCheck();

                        // Calculate score
                        self.score = self.calculateScore(self.issues);
                        self.status = self.getScoreStatus(self.score);
                        self.updateScore(self.score, self.status);
                    } else {
                        self.showError(response.data?.message || 'Failed to load issues');
                    }
                },
                error: function() {
                    self.showLoading(false);
                    self.showError('Unable to connect to server');
                }
            });
        },

        /**
         * Check now
         */
        checkNow: function() {
            if (window.SagaConsistencyChecker) {
                window.SagaConsistencyChecker.checkNow();
            }

            const self = this;
            setTimeout(function() {
                self.loadIssues();
            }, 2000);
        },

        /**
         * Resolve issue
         */
        resolveIssue: function(issueId) {
            const self = this;

            $.ajax({
                url: sagaConsistency.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_resolve_issue',
                    nonce: sagaConsistency.nonce,
                    issue_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        self.loadIssues();
                        self.showNotice('Issue resolved successfully', 'success');
                    }
                }
            });
        },

        /**
         * Dismiss issue
         */
        dismissIssue: function(issueId) {
            const self = this;

            $.ajax({
                url: sagaConsistency.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_dismiss_inline_warning',
                    nonce: sagaConsistency.nonce,
                    issue_id: issueId
                },
                success: function(response) {
                    if (response.success) {
                        $(`#saga-issue-${issueId}`).fadeOut(function() {
                            $(this).remove();
                            self.updateIssueCount();
                        });
                        self.showNotice('Issue dismissed', 'info');
                    }
                }
            });
        },

        /**
         * Update score display
         */
        updateScore: function(score, status) {
            const colors = {
                excellent: '#10b981',
                good: '#3b82f6',
                fair: '#f59e0b',
                poor: '#dc2626'
            };

            const labels = {
                excellent: 'Excellent',
                good: 'Good',
                fair: 'Fair',
                poor: 'Poor'
            };

            const color = colors[status] || '#6b7280';
            const label = labels[status] || 'Unknown';

            const scoreHTML = `
                <div class="saga-score-badge" style="
                    padding: 16px;
                    margin-bottom: 12px;
                    background: ${color}20;
                    border: 2px solid ${color};
                    border-radius: 8px;
                    text-align: center;
                ">
                    <div style="font-size: 36px; font-weight: bold; color: ${color};">
                        ${score}%
                    </div>
                    <div style="font-size: 14px; color: ${color}; text-transform: uppercase; letter-spacing: 1px;">
                        ${label}
                    </div>
                </div>
            `;

            $('#saga-score-container').html(scoreHTML);
        },

        /**
         * Render issues list
         */
        renderIssues: function() {
            const container = $('#saga-issues-container');

            if (this.issues.length === 0) {
                container.html(`
                    <div class="notice notice-success inline" style="margin: 0;">
                        <p><strong>No consistency issues found!</strong></p>
                    </div>
                `);
                return;
            }

            // Group by severity
            const groupedIssues = this.groupBySeverity(this.issues);
            let html = '';

            const severityOrder = ['critical', 'high', 'medium', 'low', 'info'];
            severityOrder.forEach(severity => {
                const severityIssues = groupedIssues[severity];
                if (!severityIssues || severityIssues.length === 0) {
                    return;
                }

                html += `
                    <div class="saga-severity-group" style="margin-bottom: 12px;">
                        <h4 style="margin: 8px 0; font-size: 13px; text-transform: uppercase;">
                            ${this.getSeverityIcon(severity)} ${severity.charAt(0).toUpperCase() + severity.slice(1)} (${severityIssues.length})
                        </h4>
                `;

                severityIssues.forEach(issue => {
                    html += this.renderIssueItem(issue);
                });

                html += '</div>';
            });

            container.html(html);
        },

        /**
         * Render single issue item
         */
        renderIssueItem: function(issue) {
            const severityColors = {
                critical: '#dc2626',
                high: '#f59e0b',
                medium: '#3b82f6',
                low: '#10b981',
                info: '#6b7280'
            };

            const color = severityColors[issue.severity] || '#6b7280';

            return `
                <div id="saga-issue-${issue.id}" class="saga-issue-item" style="
                    margin-bottom: 8px;
                    padding: 10px;
                    background: #f9fafb;
                    border-left: 4px solid ${color};
                    border-radius: 4px;
                ">
                    <div class="saga-issue-header">
                        <a href="#" class="saga-issue-toggle" style="text-decoration: none; display: block;">
                            <span class="dashicons dashicons-arrow-down" style="color: ${color};"></span>
                            <strong style="color: ${color};">${issue.type_label}</strong>
                        </a>
                    </div>
                    <div class="saga-issue-details" style="display: none; margin-top: 8px;">
                        <p style="margin: 8px 0; font-size: 13px;">${issue.description}</p>

                        ${issue.suggested_fix ? `
                            <div style="padding: 8px; background: #fff; border-radius: 4px; margin: 8px 0;">
                                <strong style="font-size: 12px;">Suggested Fix:</strong>
                                <p style="margin: 4px 0; font-size: 12px;">${issue.suggested_fix}</p>
                            </div>
                        ` : ''}

                        ${issue.ai_confidence ? `
                            <p style="font-size: 11px; color: #666; margin: 4px 0;">
                                AI Confidence: ${Math.round(issue.ai_confidence * 100)}%
                            </p>
                        ` : ''}

                        <div style="margin-top: 8px;">
                            <button type="button" class="button button-small saga-resolve-classic" data-issue-id="${issue.id}">
                                Resolve
                            </button>
                            <button type="button" class="button button-small saga-dismiss-classic" data-issue-id="${issue.id}">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Group issues by severity
         */
        groupBySeverity: function(issues) {
            const grouped = {};
            issues.forEach(issue => {
                if (!grouped[issue.severity]) {
                    grouped[issue.severity] = [];
                }
                grouped[issue.severity].push(issue);
            });
            return grouped;
        },

        /**
         * Calculate score from issues
         */
        calculateScore: function(issues) {
            const penalties = {
                critical: 25,
                high: 15,
                medium: 8,
                low: 3,
                info: 1
            };

            let totalPenalty = 0;
            issues.forEach(issue => {
                if (penalties[issue.severity]) {
                    totalPenalty += penalties[issue.severity];
                }
            });

            return Math.max(0, 100 - totalPenalty);
        },

        /**
         * Get score status
         */
        getScoreStatus: function(score) {
            if (score >= 90) return 'excellent';
            if (score >= 75) return 'good';
            if (score >= 50) return 'fair';
            return 'poor';
        },

        /**
         * Get severity icon
         */
        getSeverityIcon: function(severity) {
            const icons = {
                critical: '🔴',
                high: '🟠',
                medium: '🔵',
                low: '🟢',
                info: 'ℹ️'
            };
            return icons[severity] || 'ℹ️';
        },

        /**
         * Update last check time
         */
        updateLastCheck: function() {
            const now = new Date();
            $('#saga-last-check').html(`Last checked: ${now.toLocaleTimeString()}`);
        },

        /**
         * Update issue count
         */
        updateIssueCount: function() {
            this.issues = this.issues.filter(issue => {
                return $(`#saga-issue-${issue.id}`).is(':visible');
            });

            if (this.issues.length === 0) {
                this.renderIssues();
            }
        },

        /**
         * Show loading state
         */
        showLoading: function(show) {
            if (show) {
                $('#saga-loading').show();
                $('#saga-issues-container').hide();
                $('.saga-check-now-classic').prop('disabled', true);
            } else {
                $('#saga-loading').hide();
                $('#saga-issues-container').show();
                $('.saga-check-now-classic').prop('disabled', false);
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            $('#saga-issues-container').html(`
                <div class="notice notice-error inline">
                    <p><strong>Error:</strong> ${message}</p>
                </div>
            `).show();
        },

        /**
         * Show notice
         */
        showNotice: function(message, type = 'success') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible" style="margin: 10px 0;">
                    <p>${message}</p>
                </div>
            `);

            $('#saga-consistency-metabox .inside').prepend(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ClassicConsistency.init();
    });

})(jQuery);
