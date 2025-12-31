/**
 * Consistency Badge Block for Gutenberg
 *
 * Displays entity consistency score with color-coded badge
 * Can be embedded in posts to show entity quality
 *
 * @package SagaManager
 * @version 1.4.0
 */

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, ToggleControl, RangeControl } = wp.components;
const { useSelect } = wp.data;
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;

/**
 * Register Consistency Badge Block
 */
registerBlockType('saga/consistency-badge', {
    apiVersion: 2,
    title: __('Consistency Badge', 'saga-manager-theme'),
    description: __('Display consistency score for an entity', 'saga-manager-theme'),
    category: 'saga-manager',
    icon: 'shield-alt',
    keywords: [__('consistency', 'saga-manager-theme'), __('quality', 'saga-manager-theme'), __('score', 'saga-manager-theme')],

    attributes: {
        entityId: {
            type: 'number',
            default: 0
        },
        showIssueCount: {
            type: 'boolean',
            default: true
        },
        showLabel: {
            type: 'boolean',
            default: true
        },
        size: {
            type: 'string',
            default: 'medium'
        },
        autoDetect: {
            type: 'boolean',
            default: true
        }
    },

    example: {
        attributes: {
            entityId: 1,
            showIssueCount: true,
            showLabel: true
        }
    },

    edit: function(props) {
        const { attributes, setAttributes } = props;
        const { entityId, showIssueCount, showLabel, size, autoDetect } = attributes;

        const [score, setScore] = useState(null);
        const [issueCount, setIssueCount] = useState(0);
        const [status, setStatus] = useState('loading');
        const [isLoading, setIsLoading] = useState(false);

        const blockProps = useBlockProps();

        // Auto-detect current entity ID
        const currentPostId = useSelect(select => {
            return select('core/editor').getCurrentPostId();
        }, []);

        // Fetch consistency data
        useEffect(() => {
            const targetEntityId = autoDetect ? currentPostId : entityId;

            if (!targetEntityId || typeof sagaConsistency === 'undefined') {
                return;
            }

            setIsLoading(true);

            jQuery.ajax({
                url: sagaConsistency.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_get_entity_issues',
                    nonce: sagaConsistency.nonce,
                    entity_id: targetEntityId
                },
                success: function(response) {
                    if (response.success) {
                        const issues = response.data.issues || [];
                        const calculatedScore = calculateScoreFromIssues(issues);
                        const calculatedStatus = getScoreStatus(calculatedScore);

                        setScore(calculatedScore);
                        setIssueCount(issues.length);
                        setStatus(calculatedStatus);
                    }
                    setIsLoading(false);
                },
                error: function() {
                    setStatus('error');
                    setIsLoading(false);
                }
            });
        }, [entityId, autoDetect, currentPostId]);

        // Calculate score from issues
        const calculateScoreFromIssues = (issues) => {
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
        };

        // Get status from score
        const getScoreStatus = (score) => {
            if (score >= 90) return 'excellent';
            if (score >= 75) return 'good';
            if (score >= 50) return 'fair';
            return 'poor';
        };

        // Get status color
        const getStatusColor = (status) => {
            const colors = {
                excellent: '#10b981',
                good: '#3b82f6',
                fair: '#f59e0b',
                poor: '#dc2626',
                loading: '#6b7280',
                error: '#dc2626'
            };
            return colors[status] || colors.loading;
        };

        // Get status label
        const getStatusLabel = (status) => {
            const labels = {
                excellent: __('Excellent', 'saga-manager-theme'),
                good: __('Good', 'saga-manager-theme'),
                fair: __('Fair', 'saga-manager-theme'),
                poor: __('Poor', 'saga-manager-theme'),
                loading: __('Loading...', 'saga-manager-theme'),
                error: __('Error', 'saga-manager-theme')
            };
            return labels[status] || labels.loading;
        };

        // Get size class
        const getSizeClass = (size) => {
            const sizeClasses = {
                small: 'saga-badge-small',
                medium: 'saga-badge-medium',
                large: 'saga-badge-large'
            };
            return sizeClasses[size] || sizeClasses.medium;
        };

        const statusColor = getStatusColor(status);
        const statusLabel = getStatusLabel(status);
        const sizeClass = getSizeClass(size);

        return (
            <>
                <InspectorControls>
                    <PanelBody title={__('Badge Settings', 'saga-manager-theme')}>
                        <ToggleControl
                            label={__('Auto-detect Entity', 'saga-manager-theme')}
                            help={__('Automatically use current post as entity', 'saga-manager-theme')}
                            checked={autoDetect}
                            onChange={(value) => setAttributes({ autoDetect: value })}
                        />

                        {!autoDetect && (
                            <RangeControl
                                label={__('Entity ID', 'saga-manager-theme')}
                                value={entityId}
                                onChange={(value) => setAttributes({ entityId: value })}
                                min={0}
                                max={10000}
                            />
                        )}

                        <ToggleControl
                            label={__('Show Issue Count', 'saga-manager-theme')}
                            checked={showIssueCount}
                            onChange={(value) => setAttributes({ showIssueCount: value })}
                        />

                        <ToggleControl
                            label={__('Show Status Label', 'saga-manager-theme')}
                            checked={showLabel}
                            onChange={(value) => setAttributes({ showLabel: value })}
                        />

                        <SelectControl
                            label={__('Badge Size', 'saga-manager-theme')}
                            value={size}
                            options={[
                                { label: __('Small', 'saga-manager-theme'), value: 'small' },
                                { label: __('Medium', 'saga-manager-theme'), value: 'medium' },
                                { label: __('Large', 'saga-manager-theme'), value: 'large' }
                            ]}
                            onChange={(value) => setAttributes({ size: value })}
                        />
                    </PanelBody>
                </InspectorControls>

                <div {...blockProps}>
                    <div
                        className={`saga-consistency-badge ${sizeClass} status-${status}`}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '8px',
                            padding: size === 'small' ? '6px 12px' : size === 'large' ? '12px 20px' : '8px 16px',
                            backgroundColor: statusColor + '20',
                            border: `2px solid ${statusColor}`,
                            borderRadius: '8px',
                            fontFamily: 'system-ui, -apple-system, sans-serif'
                        }}
                    >
                        <div
                            className="score-circle"
                            style={{
                                width: size === 'small' ? '40px' : size === 'large' ? '60px' : '50px',
                                height: size === 'small' ? '40px' : size === 'large' ? '60px' : '50px',
                                borderRadius: '50%',
                                backgroundColor: statusColor,
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: size === 'small' ? '14px' : size === 'large' ? '20px' : '16px',
                                fontWeight: 'bold'
                            }}
                        >
                            {isLoading ? '...' : score !== null ? `${score}%` : '--'}
                        </div>

                        <div className="badge-content">
                            {showLabel && (
                                <div
                                    className="status-label"
                                    style={{
                                        fontSize: size === 'small' ? '12px' : size === 'large' ? '16px' : '14px',
                                        fontWeight: '600',
                                        color: statusColor,
                                        textTransform: 'uppercase',
                                        letterSpacing: '0.5px'
                                    }}
                                >
                                    {statusLabel}
                                </div>
                            )}

                            {showIssueCount && !isLoading && issueCount > 0 && (
                                <div
                                    className="issue-count"
                                    style={{
                                        fontSize: size === 'small' ? '11px' : size === 'large' ? '13px' : '12px',
                                        color: '#6b7280'
                                    }}
                                >
                                    {issueCount} {issueCount === 1 ? __('issue', 'saga-manager-theme') : __('issues', 'saga-manager-theme')}
                                </div>
                            )}
                        </div>
                    </div>

                    {autoDetect && (
                        <p style={{ fontSize: '12px', color: '#6b7280', marginTop: '8px' }}>
                            {__('Auto-detecting current entity', 'saga-manager-theme')}
                        </p>
                    )}
                </div>
            </>
        );
    },

    save: function(props) {
        const { attributes } = props;
        const { entityId, showIssueCount, showLabel, size, autoDetect } = attributes;

        const blockProps = useBlockProps.save();

        return (
            <div {...blockProps}>
                <div
                    className="saga-consistency-badge-placeholder"
                    data-entity-id={autoDetect ? 'auto' : entityId}
                    data-show-issue-count={showIssueCount}
                    data-show-label={showLabel}
                    data-size={size}
                >
                    {/* Placeholder - will be rendered by frontend JS */}
                    <div className="saga-badge-loading">
                        {__('Loading consistency score...', 'saga-manager-theme')}
                    </div>
                </div>
            </div>
        );
    }
});
