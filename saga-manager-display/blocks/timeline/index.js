/**
 * Timeline Block
 *
 * @package SagaManagerDisplay
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    ToggleControl,
    RangeControl,
    Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

/**
 * Edit component.
 */
function Edit({ attributes, setAttributes }) {
    const {
        sagaSlug,
        layout,
        limit,
        order,
        showParticipants,
        showLocations,
        showDescriptions,
        interactive,
    } = attributes;

    const blockProps = useBlockProps();
    const [sagas, setSagas] = useState([]);
    const [events, setEvents] = useState([]);
    const [loading, setLoading] = useState(false);
    const [loadingSagas, setLoadingSagas] = useState(true);

    // Fetch available sagas
    useEffect(() => {
        apiFetch({ path: '/saga/v1/sagas' })
            .then((response) => {
                setSagas(response.data || []);
                setLoadingSagas(false);
            })
            .catch(() => {
                setSagas([]);
                setLoadingSagas(false);
            });
    }, []);

    // Fetch timeline events
    useEffect(() => {
        if (!sagaSlug) {
            setEvents([]);
            return;
        }

        setLoading(true);
        const params = new URLSearchParams({
            saga: sagaSlug,
            limit: String(limit),
            order: order,
        });

        apiFetch({ path: `/saga/v1/timeline?${params.toString()}` })
            .then((response) => {
                setEvents(response.data || []);
                setLoading(false);
            })
            .catch(() => {
                setEvents([]);
                setLoading(false);
            });
    }, [sagaSlug, limit, order]);

    const sagaOptions = [
        { label: __('Select a saga', 'saga-manager-display'), value: '' },
        ...sagas.map((saga) => ({
            label: saga.name,
            value: saga.slug || saga.name.toLowerCase().replace(/\s+/g, '-'),
        })),
    ];

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Saga Selection', 'saga-manager-display')}>
                    {loadingSagas ? (
                        <Spinner />
                    ) : (
                        <SelectControl
                            label={__('Saga', 'saga-manager-display')}
                            value={sagaSlug}
                            options={sagaOptions}
                            onChange={(value) => setAttributes({ sagaSlug: value })}
                        />
                    )}
                </PanelBody>
                <PanelBody title={__('Display Settings', 'saga-manager-display')}>
                    <SelectControl
                        label={__('Layout', 'saga-manager-display')}
                        value={layout}
                        options={[
                            { label: __('Vertical', 'saga-manager-display'), value: 'vertical' },
                            { label: __('Horizontal', 'saga-manager-display'), value: 'horizontal' },
                            { label: __('Compact', 'saga-manager-display'), value: 'compact' },
                        ]}
                        onChange={(value) => setAttributes({ layout: value })}
                    />
                    <RangeControl
                        label={__('Number of Events', 'saga-manager-display')}
                        value={limit}
                        onChange={(value) => setAttributes({ limit: value })}
                        min={1}
                        max={100}
                    />
                    <SelectControl
                        label={__('Order', 'saga-manager-display')}
                        value={order}
                        options={[
                            { label: __('Ascending (oldest first)', 'saga-manager-display'), value: 'asc' },
                            { label: __('Descending (newest first)', 'saga-manager-display'), value: 'desc' },
                        ]}
                        onChange={(value) => setAttributes({ order: value })}
                    />
                </PanelBody>
                <PanelBody title={__('Content Options', 'saga-manager-display')}>
                    <ToggleControl
                        label={__('Show Participants', 'saga-manager-display')}
                        checked={showParticipants}
                        onChange={(value) => setAttributes({ showParticipants: value })}
                    />
                    <ToggleControl
                        label={__('Show Locations', 'saga-manager-display')}
                        checked={showLocations}
                        onChange={(value) => setAttributes({ showLocations: value })}
                    />
                    <ToggleControl
                        label={__('Show Descriptions', 'saga-manager-display')}
                        checked={showDescriptions}
                        onChange={(value) => setAttributes({ showDescriptions: value })}
                    />
                    <ToggleControl
                        label={__('Interactive Mode', 'saga-manager-display')}
                        checked={interactive}
                        onChange={(value) => setAttributes({ interactive: value })}
                        help={__('Enable JavaScript interactivity', 'saga-manager-display')}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {loading ? (
                    <div className="saga-block-loading">
                        <Spinner />
                        <span>{__('Loading timeline...', 'saga-manager-display')}</span>
                    </div>
                ) : events.length > 0 ? (
                    <div className={`saga-timeline saga-timeline--${layout}`}>
                        <div className="saga-timeline__track">
                            {events.slice(0, 5).map((event, index) => (
                                <div key={event.id || index} className="saga-timeline__event">
                                    <div className="saga-timeline__marker"></div>
                                    <div className="saga-timeline__content">
                                        <time className="saga-timeline__date">
                                            {event.canon_date}
                                        </time>
                                        <h4 className="saga-timeline__title">{event.title}</h4>
                                        {showDescriptions && event.description && (
                                            <p className="saga-timeline__description">
                                                {event.description.substring(0, 100)}...
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {events.length > 5 && (
                                <div className="saga-timeline__more">
                                    {__('+ more events in preview', 'saga-manager-display').replace(
                                        '%d',
                                        String(events.length - 5)
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                ) : sagaSlug ? (
                    <div className="saga-block-message saga-block-message--warning">
                        <p>{__('No timeline events found for this saga.', 'saga-manager-display')}</p>
                    </div>
                ) : (
                    <div className="saga-block-placeholder saga-block-placeholder--timeline">
                        <div className="saga-block-placeholder__icon">
                            <span className="dashicons dashicons-calendar-alt"></span>
                        </div>
                        <p className="saga-block-placeholder__message">
                            {__('Select a saga to display its timeline', 'saga-manager-display')}
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}

/**
 * Register the block.
 */
registerBlockType(metadata.name, {
    edit: Edit,
    save: () => null, // Dynamic block - rendered on server
});
