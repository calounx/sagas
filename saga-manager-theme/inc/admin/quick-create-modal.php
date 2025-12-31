<?php
/**
 * Quick Create Modal Template
 *
 * @package SagaManager
 * @since 1.3.0
 */

defined('ABSPATH') || exit;

global $wpdb;

// Get available sagas
$sagas = $wpdb->get_results(
    "SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name ASC"
);

// Get entity types
$entity_types = [
    'character' => ['icon' => 'admin-users', 'label' => __('Character', 'saga-manager')],
    'location' => ['icon' => 'location', 'label' => __('Location', 'saga-manager')],
    'event' => ['icon' => 'calendar-alt', 'label' => __('Event', 'saga-manager')],
    'faction' => ['icon' => 'groups', 'label' => __('Faction', 'saga-manager')],
    'artifact' => ['icon' => 'star-filled', 'label' => __('Artifact', 'saga-manager')],
    'concept' => ['icon' => 'lightbulb', 'label' => __('Concept', 'saga-manager')],
];
?>

<div id="saga-quick-create-modal" class="saga-modal" style="display: none;" role="dialog" aria-labelledby="saga-modal-title" aria-hidden="true">
    <div class="saga-modal-overlay" data-close-modal></div>

    <div class="saga-modal-container">
        <div class="saga-modal-header">
            <h2 id="saga-modal-title">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('Quick Create Entity', 'saga-manager'); ?>
            </h2>

            <button type="button" class="saga-modal-close" data-close-modal aria-label="<?php esc_attr_e('Close', 'saga-manager'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>

            <div class="saga-keyboard-hint">
                <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>E</kbd>
                <span class="saga-hint-separator">|</span>
                <kbd>Esc</kbd> <?php esc_html_e('to close', 'saga-manager'); ?>
            </div>
        </div>

        <div class="saga-modal-body">
            <form id="saga-quick-create-form" method="post">
                <?php wp_nonce_field('saga_quick_create', 'saga_quick_create_nonce'); ?>

                <!-- Entity Type Selector -->
                <div class="saga-form-section saga-entity-type-selector">
                    <label class="saga-form-label">
                        <?php esc_html_e('Entity Type', 'saga-manager'); ?>
                        <span class="required">*</span>
                    </label>

                    <div class="saga-entity-types">
                        <?php foreach ($entity_types as $type => $config): ?>
                            <label class="saga-entity-type-option">
                                <input
                                    type="radio"
                                    name="entity_type"
                                    value="<?php echo esc_attr($type); ?>"
                                    <?php checked($type, 'character'); ?>
                                    data-entity-type="<?php echo esc_attr($type); ?>"
                                >
                                <span class="saga-type-card">
                                    <span class="dashicons dashicons-<?php echo esc_attr($config['icon']); ?>"></span>
                                    <span class="saga-type-label"><?php echo esc_html($config['label']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="saga-field-error" data-field="entity_type"></div>
                </div>

                <!-- Entity Name -->
                <div class="saga-form-section">
                    <label for="saga-entity-name" class="saga-form-label">
                        <?php esc_html_e('Name', 'saga-manager'); ?>
                        <span class="required">*</span>
                    </label>

                    <div class="saga-input-wrapper">
                        <input
                            type="text"
                            id="saga-entity-name"
                            name="name"
                            class="saga-form-input"
                            placeholder="<?php esc_attr_e('Enter entity name...', 'saga-manager'); ?>"
                            autocomplete="off"
                            required
                            autofocus
                        >

                        <span class="saga-duplicate-indicator" style="display: none;">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Already exists', 'saga-manager'); ?>
                        </span>
                    </div>

                    <div class="saga-field-error" data-field="name"></div>
                </div>

                <!-- Saga Selector (if multiple sagas) -->
                <?php if (count($sagas) > 1): ?>
                <div class="saga-form-section">
                    <label for="saga-select" class="saga-form-label">
                        <?php esc_html_e('Saga', 'saga-manager'); ?>
                    </label>

                    <select id="saga-select" name="saga_id" class="saga-form-select">
                        <?php foreach ($sagas as $saga): ?>
                            <option value="<?php echo esc_attr($saga->id); ?>">
                                <?php echo esc_html($saga->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="saga-field-error" data-field="saga_id"></div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="saga_id" value="<?php echo esc_attr($sagas[0]->id ?? 1); ?>">
                <?php endif; ?>

                <!-- Description -->
                <div class="saga-form-section">
                    <label for="saga-entity-description" class="saga-form-label">
                        <?php esc_html_e('Description', 'saga-manager'); ?>
                    </label>

                    <div class="saga-editor-wrapper">
                        <?php
                        wp_editor('', 'saga-entity-description', [
                            'textarea_name' => 'description',
                            'textarea_rows' => 6,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => true,
                            'tinymce' => [
                                'toolbar1' => 'bold,italic,underline,link,bullist,numlist,blockquote,undo,redo',
                                'toolbar2' => '',
                            ],
                        ]);
                        ?>
                    </div>

                    <div class="saga-field-error" data-field="description"></div>
                </div>

                <!-- Importance Score -->
                <div class="saga-form-section">
                    <label for="saga-importance" class="saga-form-label">
                        <?php esc_html_e('Importance Score', 'saga-manager'); ?>
                        <span class="saga-importance-value">50</span>
                    </label>

                    <div class="saga-slider-wrapper">
                        <input
                            type="range"
                            id="saga-importance"
                            name="importance"
                            min="0"
                            max="100"
                            value="50"
                            step="1"
                            class="saga-importance-slider"
                        >

                        <div class="saga-slider-labels">
                            <span><?php esc_html_e('Minor', 'saga-manager'); ?></span>
                            <span><?php esc_html_e('Major', 'saga-manager'); ?></span>
                        </div>
                    </div>

                    <div class="saga-field-error" data-field="importance"></div>
                </div>

                <!-- Advanced Options Toggle -->
                <div class="saga-form-section">
                    <button type="button" class="saga-toggle-advanced" data-toggle="advanced-options">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <?php esc_html_e('Advanced Options', 'saga-manager'); ?>
                    </button>
                </div>

                <!-- Advanced Options (Collapsible) -->
                <div id="saga-advanced-options" class="saga-advanced-section" style="display: none;">

                    <!-- Featured Image -->
                    <div class="saga-form-section">
                        <label class="saga-form-label">
                            <?php esc_html_e('Featured Image', 'saga-manager'); ?>
                        </label>

                        <div class="saga-image-upload">
                            <input type="hidden" name="featured_image" id="saga-featured-image" value="">

                            <div class="saga-image-preview" style="display: none;">
                                <img src="" alt="<?php esc_attr_e('Preview', 'saga-manager'); ?>">
                                <button type="button" class="saga-remove-image">
                                    <span class="dashicons dashicons-no"></span>
                                </button>
                            </div>

                            <button type="button" class="saga-upload-image button">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php esc_html_e('Set Featured Image', 'saga-manager'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Relationships -->
                    <div class="saga-form-section">
                        <label class="saga-form-label">
                            <?php esc_html_e('Quick Relationships', 'saga-manager'); ?>
                        </label>

                        <div class="saga-relationship-search">
                            <input
                                type="text"
                                class="saga-form-input"
                                placeholder="<?php esc_attr_e('Search entities to relate...', 'saga-manager'); ?>"
                                id="saga-relationship-search"
                                autocomplete="off"
                            >

                            <div class="saga-search-results" style="display: none;"></div>
                        </div>

                        <div class="saga-selected-relationships"></div>
                    </div>

                    <!-- Templates -->
                    <div class="saga-form-section">
                        <label class="saga-form-label">
                            <?php esc_html_e('Quick Templates', 'saga-manager'); ?>
                        </label>

                        <div class="saga-templates-list">
                            <button type="button" class="saga-template-button" data-template="basic">
                                <?php esc_html_e('Basic Template', 'saga-manager'); ?>
                            </button>
                            <button type="button" class="saga-template-button" data-template="detailed">
                                <?php esc_html_e('Detailed Template', 'saga-manager'); ?>
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Draft Recovery Indicator -->
                <div class="saga-draft-recovery" style="display: none;">
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e('Draft recovered from autosave', 'saga-manager'); ?>
                    <button type="button" class="saga-clear-draft"><?php esc_html_e('Clear', 'saga-manager'); ?></button>
                </div>

            </form>
        </div>

        <div class="saga-modal-footer">
            <div class="saga-footer-left">
                <span class="saga-autosave-indicator">
                    <span class="dashicons dashicons-cloud"></span>
                    <span class="saga-autosave-text"><?php esc_html_e('Autosaving...', 'saga-manager'); ?></span>
                </span>
            </div>

            <div class="saga-footer-actions">
                <button type="button" class="button button-secondary" data-close-modal>
                    <?php esc_html_e('Cancel', 'saga-manager'); ?>
                </button>

                <button type="submit" class="button button-primary" form="saga-quick-create-form" data-status="draft">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Save as Draft', 'saga-manager'); ?>
                </button>

                <button type="submit" class="button button-primary saga-publish-button" form="saga-quick-create-form" data-status="publish">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Create & Publish', 'saga-manager'); ?>
                </button>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="saga-loading-overlay" style="display: none;">
            <div class="saga-spinner"></div>
            <p class="saga-loading-text"><?php esc_html_e('Creating entity...', 'saga-manager'); ?></p>
        </div>
    </div>
</div>

<!-- Success Notification Template -->
<div id="saga-success-notification" class="saga-notification saga-notification-success" style="display: none;">
    <span class="dashicons dashicons-yes-alt"></span>
    <div class="saga-notification-content">
        <strong class="saga-notification-title"><?php esc_html_e('Success!', 'saga-manager'); ?></strong>
        <p class="saga-notification-message"></p>
    </div>
    <div class="saga-notification-actions">
        <a href="#" class="saga-notification-link"><?php esc_html_e('Edit', 'saga-manager'); ?></a>
        <a href="#" class="saga-notification-link"><?php esc_html_e('View', 'saga-manager'); ?></a>
    </div>
    <button type="button" class="saga-notification-close">
        <span class="dashicons dashicons-no-alt"></span>
    </button>
</div>

<!-- Error Notification Template -->
<div id="saga-error-notification" class="saga-notification saga-notification-error" style="display: none;">
    <span class="dashicons dashicons-warning"></span>
    <div class="saga-notification-content">
        <strong class="saga-notification-title"><?php esc_html_e('Error', 'saga-manager'); ?></strong>
        <p class="saga-notification-message"></p>
    </div>
    <button type="button" class="saga-notification-close">
        <span class="dashicons dashicons-no-alt"></span>
    </button>
</div>
