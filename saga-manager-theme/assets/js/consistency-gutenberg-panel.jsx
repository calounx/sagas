/**
 * Gutenberg Consistency Panel
 *
 * Custom sidebar panel showing consistency issues for current entity
 * with real-time updates as content changes
 *
 * @package SagaManager
 * @version 1.4.0
 */

const { Component, Fragment } = wp.element;
const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
const { PanelBody, PanelRow, Button, Spinner, Notice } = wp.components;
const { registerPlugin } = wp.plugins;
const { __, sprintf } = wp.i18n;
const { select, subscribe } = wp.data;

/**
 * Consistency Status Component
 */
class ConsistencyStatusPanel extends Component {
    constructor(props) {
        super(props);

        this.state = {
            issues: [],
            isLoading: false,
            score: null,
            status: null,
            lastUpdate: null,
            error: null,
            dismissedIssues: []
        };

        this.checkInterval = null;
        this.unsubscribe = null;
    }

    componentDidMount() {
        // Load initial issues
        this.loadIssues();

        // Listen for real-time check updates
        jQuery(document).on('saga:consistency-updated', (event, data) => {
            this.setState({
                score: data.score,
                status: data.status,
                lastUpdate: new Date()
            });
        });

        // Subscribe to editor changes for auto-refresh
        this.unsubscribe = subscribe(() => {
            const isSavingPost = select('core/editor').isSavingPost();
            const isAutosavingPost = select('core/editor').isAutosavingPost();

            // Reload issues after save
            if (this.wasSaving && !isSavingPost && !isAutosavingPost) {
                this.loadIssues();
            }

            this.wasSaving = isSavingPost;
        });
    }

    componentWillUnmount() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }
        if (this.unsubscribe) {
            this.unsubscribe();
        }
        jQuery(document).off('saga:consistency-updated');
    }

    /**
     * Load issues from server
     */
    loadIssues = () => {
        if (typeof sagaConsistency === 'undefined' || !sagaConsistency.entityId) {
            return;
        }

        this.setState({ isLoading: true, error: null });

        jQuery.ajax({
            url: sagaConsistency.ajaxUrl,
            type: 'GET',
            data: {
                action: 'saga_get_entity_issues',
                nonce: sagaConsistency.nonce,
                entity_id: sagaConsistency.entityId
            },
            success: (response) => {
                if (response.success) {
                    this.setState({
                        issues: response.data.issues || [],
                        isLoading: false,
                        lastUpdate: new Date()
                    });
                } else {
                    this.setState({
                        error: response.data?.message || __('Failed to load issues', 'saga-manager-theme'),
                        isLoading: false
                    });
                }
            },
            error: (xhr, status, error) => {
                this.setState({
                    error: __('Unable to connect to server', 'saga-manager-theme'),
                    isLoading: false
                });
            }
        });
    };

    /**
     * Check now (manual trigger)
     */
    handleCheckNow = () => {
        if (window.SagaConsistencyChecker) {
            window.SagaConsistencyChecker.checkNow();
            setTimeout(() => this.loadIssues(), 2000);
        }
    };

    /**
     * Resolve issue
     */
    handleResolve = (issueId) => {
        jQuery.ajax({
            url: sagaConsistency.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_resolve_issue',
                nonce: sagaConsistency.nonce,
                issue_id: issueId
            },
            success: (response) => {
                if (response.success) {
                    this.loadIssues();
                }
            }
        });
    };

    /**
     * Dismiss issue
     */
    handleDismiss = (issueId) => {
        const { dismissedIssues } = this.state;

        jQuery.ajax({
            url: sagaConsistency.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_dismiss_inline_warning',
                nonce: sagaConsistency.nonce,
                issue_id: issueId
            },
            success: (response) => {
                if (response.success) {
                    this.setState({
                        dismissedIssues: [...dismissedIssues, issueId]
                    });
                }
            }
        });
    };

    /**
     * Get severity color
     */
    getSeverityColor = (severity) => {
        const colors = {
            critical: '#dc2626',
            high: '#f59e0b',
            medium: '#3b82f6',
            low: '#10b981',
            info: '#6b7280'
        };
        return colors[severity] || colors.info;
    };

    /**
     * Get severity icon
     */
    getSeverityIcon = (severity) => {
        const icons = {
            critical: '🔴',
            high: '🟠',
            medium: '🔵',
            low: '🟢',
            info: 'ℹ️'
        };
        return icons[severity] || icons.info;
    };

    /**
     * Render score badge
     */
    renderScoreBadge = () => {
        const { score, status } = this.state;

        if (score === null) {
            return null;
        }

        const statusColors = {
            excellent: '#10b981',
            good: '#3b82f6',
            fair: '#f59e0b',
            poor: '#dc2626'
        };

        const backgroundColor = statusColors[status] || '#6b7280';

        return (
            <div
                style={{
                    padding: '12px',
                    marginBottom: '12px',
                    backgroundColor: backgroundColor + '20',
                    border: `2px solid ${backgroundColor}`,
                    borderRadius: '8px',
                    textAlign: 'center'
                }}
            >
                <div style={{ fontSize: '32px', fontWeight: 'bold', color: backgroundColor }}>
                    {score}%
                </div>
                <div style={{ fontSize: '14px', color: backgroundColor, textTransform: 'uppercase' }}>
                    {status}
                </div>
            </div>
        );
    };

    /**
     * Render issue item
     */
    renderIssue = (issue) => {
        const { dismissedIssues } = this.state;

        if (dismissedIssues.includes(issue.id)) {
            return null;
        }

        return (
            <div
                key={issue.id}
                style={{
                    marginBottom: '12px',
                    padding: '12px',
                    backgroundColor: '#f9fafb',
                    border: `1px solid ${this.getSeverityColor(issue.severity)}30`,
                    borderLeft: `4px solid ${this.getSeverityColor(issue.severity)}`,
                    borderRadius: '4px'
                }}
            >
                <div style={{ marginBottom: '8px' }}>
                    <span style={{ marginRight: '8px' }}>
                        {this.getSeverityIcon(issue.severity)}
                    </span>
                    <strong style={{ color: this.getSeverityColor(issue.severity) }}>
                        {issue.severity_label}
                    </strong>
                    <span style={{ marginLeft: '8px', fontSize: '12px', color: '#6b7280' }}>
                        {issue.type_label}
                    </span>
                </div>

                <div style={{ fontSize: '14px', marginBottom: '8px' }}>
                    {issue.description}
                </div>

                {issue.suggested_fix && (
                    <div
                        style={{
                            fontSize: '13px',
                            padding: '8px',
                            backgroundColor: '#fff',
                            borderRadius: '4px',
                            marginBottom: '8px'
                        }}
                    >
                        <strong>{__('Suggested Fix:', 'saga-manager-theme')}</strong>
                        <div>{issue.suggested_fix}</div>
                    </div>
                )}

                {issue.ai_confidence && (
                    <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '8px' }}>
                        {sprintf(__('AI Confidence: %d%%', 'saga-manager-theme'), Math.round(issue.ai_confidence * 100))}
                    </div>
                )}

                <div style={{ display: 'flex', gap: '8px' }}>
                    <Button
                        isSmall
                        variant="primary"
                        onClick={() => this.handleResolve(issue.id)}
                    >
                        {__('Resolve', 'saga-manager-theme')}
                    </Button>
                    <Button
                        isSmall
                        variant="secondary"
                        onClick={() => this.handleDismiss(issue.id)}
                    >
                        {__('Dismiss', 'saga-manager-theme')}
                    </Button>
                </div>
            </div>
        );
    };

    /**
     * Render issues by severity
     */
    renderIssuesBySeverity = () => {
        const { issues } = this.state;

        if (issues.length === 0) {
            return (
                <Notice status="success" isDismissible={false}>
                    {__('No consistency issues found', 'saga-manager-theme')}
                </Notice>
            );
        }

        const severityOrder = ['critical', 'high', 'medium', 'low', 'info'];
        const groupedIssues = {};

        // Group by severity
        issues.forEach(issue => {
            if (!groupedIssues[issue.severity]) {
                groupedIssues[issue.severity] = [];
            }
            groupedIssues[issue.severity].push(issue);
        });

        return (
            <Fragment>
                {severityOrder.map(severity => {
                    const severityIssues = groupedIssues[severity];
                    if (!severityIssues || severityIssues.length === 0) {
                        return null;
                    }

                    return (
                        <PanelBody
                            key={severity}
                            title={sprintf(
                                __('%s (%d)', 'saga-manager-theme'),
                                severity.charAt(0).toUpperCase() + severity.slice(1),
                                severityIssues.length
                            )}
                            initialOpen={severity === 'critical' || severity === 'high'}
                        >
                            {severityIssues.map(issue => this.renderIssue(issue))}
                        </PanelBody>
                    );
                })}
            </Fragment>
        );
    };

    render() {
        const { isLoading, error, lastUpdate, issues } = this.state;

        return (
            <Fragment>
                <PluginSidebarMoreMenuItem target="saga-consistency-sidebar">
                    {__('Consistency Status', 'saga-manager-theme')}
                </PluginSidebarMoreMenuItem>

                <PluginSidebar
                    name="saga-consistency-sidebar"
                    title={__('Consistency Status', 'saga-manager-theme')}
                    icon="shield-alt"
                >
                    <PanelBody initialOpen={true}>
                        {this.renderScoreBadge()}

                        <PanelRow>
                            <Button
                                variant="secondary"
                                onClick={this.handleCheckNow}
                                disabled={isLoading}
                                style={{ width: '100%' }}
                            >
                                {isLoading ? (
                                    <Fragment>
                                        <Spinner />
                                        {__('Checking...', 'saga-manager-theme')}
                                    </Fragment>
                                ) : (
                                    __('Check Now', 'saga-manager-theme')
                                )}
                            </Button>
                        </PanelRow>

                        {lastUpdate && (
                            <PanelRow>
                                <small style={{ color: '#6b7280' }}>
                                    {sprintf(
                                        __('Last checked: %s', 'saga-manager-theme'),
                                        lastUpdate.toLocaleTimeString()
                                    )}
                                </small>
                            </PanelRow>
                        )}

                        {error && (
                            <Notice status="error" isDismissible={false}>
                                {error}
                            </Notice>
                        )}
                    </PanelBody>

                    {!isLoading && this.renderIssuesBySeverity()}

                    <PanelBody title={__('About', 'saga-manager-theme')} initialOpen={false}>
                        <p style={{ fontSize: '13px', color: '#6b7280' }}>
                            {__('Consistency checks run automatically as you edit. Issues are detected using rule-based analysis and AI assistance.', 'saga-manager-theme')}
                        </p>
                        <Button
                            variant="link"
                            href={sagaConsistency.dashboardUrl || '#'}
                            target="_blank"
                        >
                            {__('View Full Dashboard', 'saga-manager-theme')}
                        </Button>
                    </PanelBody>
                </PluginSidebar>
            </Fragment>
        );
    }
}

/**
 * Register the plugin
 */
registerPlugin('saga-consistency-status', {
    render: ConsistencyStatusPanel,
    icon: 'shield-alt'
});
