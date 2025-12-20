/**
 * Search Block
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
    TextControl,
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
        placeholder,
        showFilters,
        showTypeFilter,
        showSagaFilter,
        resultsLayout,
        resultsPerPage,
        showPagination,
        semantic,
        liveSearch,
        minChars,
        debounce,
    } = attributes;

    const blockProps = useBlockProps();
    const [sagas, setSagas] = useState([]);
    const [entityTypes, setEntityTypes] = useState([]);
    const [loadingSagas, setLoadingSagas] = useState(true);

    // Fetch available sagas and entity types
    useEffect(() => {
        Promise.all([
            apiFetch({ path: '/saga/v1/sagas' }),
            apiFetch({ path: '/saga/v1/entities/types' }),
        ])
            .then(([sagasResponse, typesResponse]) => {
                setSagas(sagasResponse.data || []);
                setEntityTypes(typesResponse.data || []);
                setLoadingSagas(false);
            })
            .catch(() => {
                setSagas([]);
                setEntityTypes([]);
                setLoadingSagas(false);
            });
    }, []);

    const sagaOptions = [
        { label: __('All sagas', 'saga-manager-display'), value: '' },
        ...sagas.map((saga) => ({
            label: saga.name,
            value: saga.slug || saga.name.toLowerCase().replace(/\s+/g, '-'),
        })),
    ];

    const defaultPlaceholder = __('Search entities...', 'saga-manager-display');

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Search Settings', 'saga-manager-display')}>
                    <TextControl
                        label={__('Placeholder Text', 'saga-manager-display')}
                        value={placeholder}
                        onChange={(value) => setAttributes({ placeholder: value })}
                        placeholder={defaultPlaceholder}
                    />
                    {loadingSagas ? (
                        <Spinner />
                    ) : (
                        <SelectControl
                            label={__('Limit to Saga', 'saga-manager-display')}
                            value={sagaSlug}
                            options={sagaOptions}
                            onChange={(value) => setAttributes({ sagaSlug: value })}
                            help={__('Leave empty to search all sagas', 'saga-manager-display')}
                        />
                    )}
                </PanelBody>
                <PanelBody title={__('Filter Options', 'saga-manager-display')}>
                    <ToggleControl
                        label={__('Show Filters', 'saga-manager-display')}
                        checked={showFilters}
                        onChange={(value) => setAttributes({ showFilters: value })}
                    />
                    {showFilters && (
                        <>
                            <ToggleControl
                                label={__('Show Type Filter', 'saga-manager-display')}
                                checked={showTypeFilter}
                                onChange={(value) => setAttributes({ showTypeFilter: value })}
                            />
                            <ToggleControl
                                label={__('Show Saga Filter', 'saga-manager-display')}
                                checked={showSagaFilter}
                                onChange={(value) => setAttributes({ showSagaFilter: value })}
                                disabled={!!sagaSlug}
                            />
                        </>
                    )}
                </PanelBody>
                <PanelBody title={__('Results Display', 'saga-manager-display')}>
                    <SelectControl
                        label={__('Results Layout', 'saga-manager-display')}
                        value={resultsLayout}
                        options={[
                            { label: __('Grid', 'saga-manager-display'), value: 'grid' },
                            { label: __('List', 'saga-manager-display'), value: 'list' },
                        ]}
                        onChange={(value) => setAttributes({ resultsLayout: value })}
                    />
                    <RangeControl
                        label={__('Results Per Page', 'saga-manager-display')}
                        value={resultsPerPage}
                        onChange={(value) => setAttributes({ resultsPerPage: value })}
                        min={4}
                        max={48}
                        step={4}
                    />
                    <ToggleControl
                        label={__('Show Pagination', 'saga-manager-display')}
                        checked={showPagination}
                        onChange={(value) => setAttributes({ showPagination: value })}
                    />
                </PanelBody>
                <PanelBody title={__('Advanced', 'saga-manager-display')} initialOpen={false}>
                    <ToggleControl
                        label={__('Semantic Search', 'saga-manager-display')}
                        checked={semantic}
                        onChange={(value) => setAttributes({ semantic: value })}
                        help={__('Use AI-powered semantic search', 'saga-manager-display')}
                    />
                    <ToggleControl
                        label={__('Live Search', 'saga-manager-display')}
                        checked={liveSearch}
                        onChange={(value) => setAttributes({ liveSearch: value })}
                        help={__('Search as you type', 'saga-manager-display')}
                    />
                    {liveSearch && (
                        <>
                            <RangeControl
                                label={__('Minimum Characters', 'saga-manager-display')}
                                value={minChars}
                                onChange={(value) => setAttributes({ minChars: value })}
                                min={1}
                                max={10}
                            />
                            <RangeControl
                                label={__('Debounce Delay (ms)', 'saga-manager-display')}
                                value={debounce}
                                onChange={(value) => setAttributes({ debounce: value })}
                                min={100}
                                max={1000}
                                step={50}
                            />
                        </>
                    )}
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className={`saga-search saga-search--${resultsLayout}`}>
                    <div className="saga-search__form">
                        <div className="saga-search__input-wrapper">
                            <span className="dashicons dashicons-search saga-search__icon"></span>
                            <input
                                type="text"
                                className="saga-search__input"
                                placeholder={placeholder || defaultPlaceholder}
                                disabled
                            />
                        </div>
                        {showFilters && (
                            <div className="saga-search__filters">
                                {showTypeFilter && entityTypes.length > 0 && (
                                    <select className="saga-search__filter" disabled>
                                        <option>{__('All Types', 'saga-manager-display')}</option>
                                        {entityTypes.map((type, index) => (
                                            <option key={type.key || index}>{type.label || type}</option>
                                        ))}
                                    </select>
                                )}
                                {showSagaFilter && !sagaSlug && sagas.length > 0 && (
                                    <select className="saga-search__filter" disabled>
                                        <option>{__('All Sagas', 'saga-manager-display')}</option>
                                        {sagas.map((saga, index) => (
                                            <option key={saga.id || index}>{saga.name}</option>
                                        ))}
                                    </select>
                                )}
                            </div>
                        )}
                    </div>
                    <div className="saga-search__results-preview">
                        <p className="saga-search__preview-text">
                            {__('Search results will appear here', 'saga-manager-display')}
                        </p>
                    </div>
                </div>
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
