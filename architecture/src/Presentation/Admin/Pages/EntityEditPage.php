<?php

declare(strict_types=1);

namespace SagaManagerCore\Presentation\Admin\Pages;

use SagaManager\Contract\EntityTypes;
use SagaManagerCore\Application\Service\EntityService;
use SagaManagerCore\Application\DTO\EntityDTO;
use SagaManagerCore\Presentation\Admin\AdminMenuManager;
use SagaManagerCore\Presentation\Admin\Form\EntityForm;
use SagaManagerCore\Domain\Exception\EntityNotFoundException;
use SagaManagerCore\Domain\Exception\ValidationException;

/**
 * Entity add/edit page
 */
final class EntityEditPage
{
    private EntityService $entityService;
    private ?EntityDTO $entity = null;
    private array $errors = [];
    private array $formData = [];

    public function __construct()
    {
        $this->entityService = $GLOBALS['saga_container']->get(EntityService::class);
    }

    public function render(): void
    {
        $action = sanitize_key($_GET['action'] ?? 'new');
        $entityId = absint($_GET['id'] ?? 0);

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSubmit($entityId);
        }

        // Load existing entity for edit
        if ($action === 'edit' && $entityId) {
            try {
                $this->entity = $this->entityService->getEntity($entityId);
                $this->formData = $this->entity->toArray();
            } catch (EntityNotFoundException $e) {
                wp_die(__('Entity not found.', 'saga-manager-core'));
            }
        }

        $isNew = $this->entity === null;
        $pageTitle = $isNew
            ? __('Add New Entity', 'saga-manager-core')
            : sprintf(__('Edit Entity: %s', 'saga-manager-core'), $this->entity->canonicalName);

        ?>
        <div class="wrap saga-admin-page">
            <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>

            <a href="<?php echo esc_url(AdminMenuManager::getUrl('entities')); ?>" class="page-title-action">
                <?php esc_html_e('Back to List', 'saga-manager-core'); ?>
            </a>

            <hr class="wp-header-end">

            <?php $this->displayErrors(); ?>

            <form method="post" id="entity-form" class="saga-form">
                <?php wp_nonce_field('saga_entity_' . ($entityId ?: 'new')); ?>

                <div class="saga-form-layout">
                    <div class="saga-form-main">
                        <?php $this->renderMainFields(); ?>
                        <?php $this->renderAttributeFields(); ?>
                    </div>

                    <div class="saga-form-sidebar">
                        <?php $this->renderSidebarFields(); ?>
                        <?php $this->renderRelationshipsPreview(); ?>
                    </div>
                </div>

                <div class="saga-form-actions">
                    <?php
                    submit_button(
                        $isNew ? __('Create Entity', 'saga-manager-core') : __('Update Entity', 'saga-manager-core'),
                        'primary',
                        'submit',
                        false
                    );
                    ?>

                    <?php if (!$isNew): ?>
                        <a href="<?php echo esc_url(wp_nonce_url(
                            AdminMenuManager::getUrl('entities', ['action' => 'delete', 'id' => $entityId]),
                            'delete_entity_' . $entityId
                        )); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this entity?', 'saga-manager-core'); ?>')">
                            <?php esc_html_e('Delete', 'saga-manager-core'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <style>
            .saga-form-layout {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 20px;
                margin-top: 20px;
            }

            .saga-form-main,
            .saga-form-sidebar {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
            }

            .saga-form-sidebar {
                height: fit-content;
            }

            .saga-field {
                margin-bottom: 20px;
            }

            .saga-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
            }

            .saga-field input[type="text"],
            .saga-field textarea,
            .saga-field select {
                width: 100%;
            }

            .saga-field-description {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }

            .saga-field-required label::after {
                content: " *";
                color: #d63638;
            }

            .saga-importance-slider {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .saga-importance-slider input[type="range"] {
                flex: 1;
            }

            .saga-importance-value {
                min-width: 40px;
                text-align: center;
                font-weight: 600;
            }

            .saga-attributes-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #c3c4c7;
            }

            .saga-attributes-section h3 {
                margin-top: 0;
            }

            .saga-form-actions {
                margin-top: 20px;
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .saga-relationships-preview {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #c3c4c7;
            }

            .saga-relationships-preview h4 {
                margin-top: 0;
            }

            .saga-relationship-item {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px solid #f0f0f1;
            }

            @media (max-width: 960px) {
                .saga-form-layout {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const importanceRange = document.getElementById('importance_score');
                const importanceValue = document.getElementById('importance-value');

                if (importanceRange && importanceValue) {
                    importanceRange.addEventListener('input', function() {
                        importanceValue.textContent = this.value;
                    });
                }

                // Generate slug from name
                const nameInput = document.getElementById('canonical_name');
                const slugInput = document.getElementById('slug');

                if (nameInput && slugInput && !slugInput.value) {
                    nameInput.addEventListener('blur', function() {
                        if (!slugInput.value) {
                            slugInput.value = this.value
                                .toLowerCase()
                                .replace(/[^a-z0-9]+/g, '-')
                                .replace(/^-|-$/g, '');
                        }
                    });
                }
            });
        </script>
        <?php
    }

    private function renderMainFields(): void
    {
        ?>
        <div class="saga-field saga-field-required">
            <label for="canonical_name"><?php esc_html_e('Name', 'saga-manager-core'); ?></label>
            <input type="text"
                   id="canonical_name"
                   name="canonical_name"
                   value="<?php echo esc_attr($this->formData['canonical_name'] ?? ''); ?>"
                   required
                   maxlength="255">
        </div>

        <div class="saga-field">
            <label for="slug"><?php esc_html_e('Slug', 'saga-manager-core'); ?></label>
            <input type="text"
                   id="slug"
                   name="slug"
                   value="<?php echo esc_attr($this->formData['slug'] ?? ''); ?>"
                   maxlength="255">
            <p class="saga-field-description">
                <?php esc_html_e('URL-friendly identifier. Leave empty to auto-generate from name.', 'saga-manager-core'); ?>
            </p>
        </div>
        <?php
    }

    private function renderSidebarFields(): void
    {
        global $wpdb;

        // Get sagas for dropdown
        $sagas = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}saga_sagas ORDER BY name",
            ARRAY_A
        );

        $currentSagaId = $this->formData['saga_id'] ?? absint($_GET['saga_id'] ?? 0);
        $currentType = $this->formData['entity_type'] ?? '';
        $currentImportance = $this->formData['importance_score'] ?? 50;
        ?>

        <div class="saga-field saga-field-required">
            <label for="saga_id"><?php esc_html_e('Saga', 'saga-manager-core'); ?></label>
            <select id="saga_id" name="saga_id" required <?php echo $this->entity ? 'disabled' : ''; ?>>
                <option value=""><?php esc_html_e('Select Saga...', 'saga-manager-core'); ?></option>
                <?php foreach ($sagas as $saga): ?>
                    <option value="<?php echo (int) $saga['id']; ?>" <?php selected($currentSagaId, (int) $saga['id']); ?>>
                        <?php echo esc_html($saga['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($this->entity): ?>
                <input type="hidden" name="saga_id" value="<?php echo (int) $currentSagaId; ?>">
            <?php endif; ?>
        </div>

        <div class="saga-field saga-field-required">
            <label for="entity_type"><?php esc_html_e('Type', 'saga-manager-core'); ?></label>
            <select id="entity_type" name="entity_type" required <?php echo $this->entity ? 'disabled' : ''; ?>>
                <option value=""><?php esc_html_e('Select Type...', 'saga-manager-core'); ?></option>
                <?php foreach (EntityTypes::ALL as $type): ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($currentType, $type); ?>>
                        <?php echo esc_html(EntityTypes::getLabel($type)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($this->entity): ?>
                <input type="hidden" name="entity_type" value="<?php echo esc_attr($currentType); ?>">
            <?php endif; ?>
        </div>

        <div class="saga-field">
            <label for="importance_score"><?php esc_html_e('Importance Score', 'saga-manager-core'); ?></label>
            <div class="saga-importance-slider">
                <input type="range"
                       id="importance_score"
                       name="importance_score"
                       min="0"
                       max="100"
                       value="<?php echo (int) $currentImportance; ?>">
                <span id="importance-value" class="saga-importance-value">
                    <?php echo (int) $currentImportance; ?>
                </span>
            </div>
            <p class="saga-field-description">
                <?php esc_html_e('0-100 scale. Higher values indicate more important entities.', 'saga-manager-core'); ?>
            </p>
        </div>
        <?php
    }

    private function renderAttributeFields(): void
    {
        if (!$this->entity) {
            echo '<p class="saga-field-description">' .
                 esc_html__('Save the entity first to add custom attributes.', 'saga-manager-core') .
                 '</p>';
            return;
        }

        $entityType = $this->entity->entityType;
        $attributes = $this->entityService->getAttributeDefinitions($entityType);
        $values = $this->entityService->getEntityAttributes($this->entity->id);

        if (empty($attributes)) {
            return;
        }

        ?>
        <div class="saga-attributes-section">
            <h3><?php esc_html_e('Attributes', 'saga-manager-core'); ?></h3>

            <?php foreach ($attributes as $attr): ?>
                <?php
                $fieldName = 'attr_' . $attr['attribute_key'];
                $fieldValue = $values[$attr['attribute_key']] ?? $attr['default_value'] ?? '';
                $isRequired = (bool) $attr['is_required'];
                ?>
                <div class="saga-field <?php echo $isRequired ? 'saga-field-required' : ''; ?>">
                    <label for="<?php echo esc_attr($fieldName); ?>">
                        <?php echo esc_html($attr['display_name']); ?>
                    </label>

                    <?php $this->renderAttributeInput($attr, $fieldName, $fieldValue); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function renderAttributeInput(array $attr, string $fieldName, mixed $value): void
    {
        $isRequired = (bool) $attr['is_required'];

        switch ($attr['data_type']) {
            case 'text':
                ?>
                <textarea id="<?php echo esc_attr($fieldName); ?>"
                          name="<?php echo esc_attr($fieldName); ?>"
                          rows="4"
                          <?php echo $isRequired ? 'required' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;

            case 'bool':
                ?>
                <label class="saga-checkbox">
                    <input type="checkbox"
                           id="<?php echo esc_attr($fieldName); ?>"
                           name="<?php echo esc_attr($fieldName); ?>"
                           value="1"
                           <?php checked($value, '1'); ?>>
                    <?php echo esc_html($attr['display_name']); ?>
                </label>
                <?php
                break;

            case 'date':
                ?>
                <input type="date"
                       id="<?php echo esc_attr($fieldName); ?>"
                       name="<?php echo esc_attr($fieldName); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       <?php echo $isRequired ? 'required' : ''; ?>>
                <?php
                break;

            case 'int':
                ?>
                <input type="number"
                       id="<?php echo esc_attr($fieldName); ?>"
                       name="<?php echo esc_attr($fieldName); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       step="1"
                       <?php echo $isRequired ? 'required' : ''; ?>>
                <?php
                break;

            case 'float':
                ?>
                <input type="number"
                       id="<?php echo esc_attr($fieldName); ?>"
                       name="<?php echo esc_attr($fieldName); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       step="0.01"
                       <?php echo $isRequired ? 'required' : ''; ?>>
                <?php
                break;

            default: // string
                ?>
                <input type="text"
                       id="<?php echo esc_attr($fieldName); ?>"
                       name="<?php echo esc_attr($fieldName); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       <?php echo $isRequired ? 'required' : ''; ?>>
                <?php
        }
    }

    private function renderRelationshipsPreview(): void
    {
        if (!$this->entity) {
            return;
        }

        $relationships = $this->entityService->getEntityRelationships($this->entity->id, 'both', null);

        if (empty($relationships)) {
            return;
        }

        ?>
        <div class="saga-relationships-preview">
            <h4>
                <?php esc_html_e('Relationships', 'saga-manager-core'); ?>
                <a href="<?php echo esc_url(AdminMenuManager::getUrl('relationships', ['entity_id' => $this->entity->id])); ?>" class="button button-small">
                    <?php esc_html_e('Manage', 'saga-manager-core'); ?>
                </a>
            </h4>

            <?php foreach (array_slice($relationships, 0, 5) as $rel): ?>
                <div class="saga-relationship-item">
                    <span><?php echo esc_html($rel['target_name']); ?></span>
                    <span class="saga-type-badge" style="background: #666; font-size: 10px;">
                        <?php echo esc_html(str_replace('_', ' ', $rel['relationship_type'])); ?>
                    </span>
                </div>
            <?php endforeach; ?>

            <?php if (count($relationships) > 5): ?>
                <p class="saga-field-description">
                    <?php printf(
                        esc_html__('+ %d more relationships', 'saga-manager-core'),
                        count($relationships) - 5
                    ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handleSubmit(int $entityId): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'saga_entity_' . ($entityId ?: 'new'))) {
            wp_die(__('Security check failed.', 'saga-manager-core'));
        }

        // Collect form data
        $this->formData = [
            'saga_id' => absint($_POST['saga_id'] ?? 0),
            'entity_type' => sanitize_key($_POST['entity_type'] ?? ''),
            'canonical_name' => sanitize_text_field($_POST['canonical_name'] ?? ''),
            'slug' => sanitize_title($_POST['slug'] ?? ''),
            'importance_score' => min(100, max(0, absint($_POST['importance_score'] ?? 50))),
        ];

        // Collect attribute values
        $attributes = [];
        foreach ($_POST as $key => $value) {
            if (str_starts_with($key, 'attr_')) {
                $attrKey = substr($key, 5);
                $attributes[$attrKey] = is_array($value) ? $value : sanitize_text_field($value);
            }
        }

        try {
            if ($entityId) {
                // Update existing
                $this->entityService->updateEntity($entityId, $this->formData);
                if (!empty($attributes)) {
                    $this->entityService->updateEntityAttributes($entityId, $attributes);
                }
                $redirectUrl = AdminMenuManager::getUrl('entities', ['message' => 'updated', 'action' => 'edit', 'id' => $entityId]);
            } else {
                // Create new
                $this->formData['attributes'] = $attributes;
                $entity = $this->entityService->createEntity($this->formData);
                $redirectUrl = AdminMenuManager::getUrl('entities', ['message' => 'created', 'action' => 'edit', 'id' => $entity->id]);
            }

            wp_redirect($redirectUrl);
            exit;

        } catch (ValidationException $e) {
            $this->errors = $e->getErrors();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    private function displayErrors(): void
    {
        if (empty($this->errors)) {
            return;
        }

        echo '<div class="notice notice-error"><ul>';
        foreach ($this->errors as $error) {
            echo '<li>' . esc_html(is_array($error) ? implode(', ', $error) : $error) . '</li>';
        }
        echo '</ul></div>';
    }
}
