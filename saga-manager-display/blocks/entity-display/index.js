/**
 * Entity Display Block
 *
 * @package SagaManagerDisplay
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl, TextControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';

/**
 * Entity selector component.
 */
function EntitySelector({ value, onChange }) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedEntity, setSelectedEntity] = useState(null);

    // Fetch entity details if we have a value
    useEffect(() => {
        if (value && !selectedEntity) {
            setLoading(true);
            apiFetch({ path: `/saga/v1/entities/${value}` })
                .then((entity) => {
                    setSelectedEntity(entity);
                    setLoading(false);
                })
                .catch(() => {
                    setLoading(false);
                });
        }
    }, [value]);

    // Search entities
    useEffect(() => {
        if (search.length < 2) {
            setResults([]);
            return;
        }

        const timeoutId = setTimeout(() => {
            setLoading(true);
            apiFetch({ path: `/saga/v1/entities/search?q=${encodeURIComponent(search)}&limit=10` })
                .then((response) => {
                    setResults(response.data || []);
                    setLoading(false);
                })
                .catch(() => {
                    setResults([]);
                    setLoading(false);
                });
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [search]);

    const handleSelect = (entity) => {
        setSelectedEntity(entity);
        onChange(entity.id);
        setSearch('');
        setResults([]);
    };

    const handleClear = () => {
        setSelectedEntity(null);
        onChange(0);
    };

    return (
        <div className="saga-entity-selector">
            {selectedEntity ? (
                <div className="saga-entity-selector__selected">
                    <span className="saga-entity-selector__name">
                        {selectedEntity.canonical_name}
                    </span>
                    <span className="saga-entity-selector__type">
                        {selectedEntity.entity_type}
                    </span>
                    <button
                        type="button"
                        className="saga-entity-selector__clear"
                        onClick={handleClear}
                    >
                        {__('Clear', 'saga-manager-display')}
                    </button>
                </div>
            ) : (
                <>
                    <TextControl
                        label={__('Search entities', 'saga-manager-display')}
                        value={search}
                        onChange={setSearch}
                        placeholder={__('Type to search...', 'saga-manager-display')}
                    />
                    {loading && <Spinner />}
                    {results.length > 0 && (
                        <ul className="saga-entity-selector__results">
                            {results.map((entity) => (
                                <li key={entity.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleSelect(entity)}
                                    >
                                        <strong>{entity.canonical_name}</strong>
                                        <span>{entity.entity_type}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}

/**
 * Edit component.
 */
function Edit({ attributes, setAttributes }) {
    const { entityId, layout, showImage, showType, showAttributes } = attributes;
    const blockProps = useBlockProps();

    const [entity, setEntity] = useState(null);
    const [loading, setLoading] = useState(false);

    // Fetch entity for preview
    useEffect(() => {
        if (entityId) {
            setLoading(true);
            apiFetch({ path: `/saga/v1/entities/${entityId}` })
                .then((data) => {
                    setEntity(data);
                    setLoading(false);
                })
                .catch(() => {
                    setEntity(null);
                    setLoading(false);
                });
        } else {
            setEntity(null);
        }
    }, [entityId]);

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Entity Selection', 'saga-manager-display')}>
                    <EntitySelector
                        value={entityId}
                        onChange={(id) => setAttributes({ entityId: id })}
                    />
                </PanelBody>
                <PanelBody title={__('Display Settings', 'saga-manager-display')}>
                    <SelectControl
                        label={__('Layout', 'saga-manager-display')}
                        value={layout}
                        options={[
                            { label: __('Card', 'saga-manager-display'), value: 'card' },
                            { label: __('Full', 'saga-manager-display'), value: 'full' },
                            { label: __('Compact', 'saga-manager-display'), value: 'compact' },
                            { label: __('Inline', 'saga-manager-display'), value: 'inline' },
                        ]}
                        onChange={(value) => setAttributes({ layout: value })}
                    />
                    <ToggleControl
                        label={__('Show Image', 'saga-manager-display')}
                        checked={showImage}
                        onChange={(value) => setAttributes({ showImage: value })}
                    />
                    <ToggleControl
                        label={__('Show Entity Type', 'saga-manager-display')}
                        checked={showType}
                        onChange={(value) => setAttributes({ showType: value })}
                    />
                    <ToggleControl
                        label={__('Show Attributes', 'saga-manager-display')}
                        checked={showAttributes}
                        onChange={(value) => setAttributes({ showAttributes: value })}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {loading ? (
                    <div className="saga-block-loading">
                        <Spinner />
                        <span>{__('Loading entity...', 'saga-manager-display')}</span>
                    </div>
                ) : entity ? (
                    <div className={`saga-entity saga-entity--${layout}`}>
                        {showImage && entity.image && (
                            <div className="saga-entity__image">
                                <img src={entity.image} alt={entity.canonical_name} />
                            </div>
                        )}
                        <div className="saga-entity__content">
                            <h3 className="saga-entity__name">{entity.canonical_name}</h3>
                            {showType && (
                                <span className="saga-entity__type">{entity.entity_type}</span>
                            )}
                            {showAttributes && entity.attributes && (
                                <dl className="saga-entity__attributes">
                                    {Object.entries(entity.attributes).slice(0, 3).map(([key, value]) => (
                                        <div key={key} className="saga-entity__attribute">
                                            <dt>{key}</dt>
                                            <dd>{String(value)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="saga-block-placeholder saga-block-placeholder--entity-display">
                        <div className="saga-block-placeholder__icon">
                            <span className="dashicons dashicons-id"></span>
                        </div>
                        <p className="saga-block-placeholder__message">
                            {__('Select an entity to display', 'saga-manager-display')}
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
