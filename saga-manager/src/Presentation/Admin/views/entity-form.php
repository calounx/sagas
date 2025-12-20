<?php
declare(strict_types=1);

/**
 * Entity Add/Edit Form View
 *
 * @var SagaManager\Infrastructure\Repository\MariaDBEntityRepository $entityRepository
 * @var array|null $entity Entity data array or null for new
 * @var string $action 'create' or 'update'
 */

use SagaManager\Domain\Entity\EntityType;
use SagaManager\Presentation\Admin\EntityFormHandler;

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Display admin notices
$notices = get_transient('saga_admin_notices') ?: [];
delete_transient('saga_admin_notices');

// Get all sagas for dropdown
$sagasTable = $wpdb->prefix . 'saga_sagas';
$sagas = $wpdb->get_results("SELECT id, name, universe FROM {$sagasTable} ORDER BY name ASC", ARRAY_A);

// Get attribute definitions and values if editing
$formHandler = new EntityFormHandler($entityRepository);
$attributeDefinitions = [];
$attributeValues = [];

if ($entity) {
    $attributeDefinitions = $formHandler->getAttributeDefinitions($entity['entity_type']);
    $attributeValues = $formHandler->getAttributeValues((int) $entity['id']);
}

$isEdit = $action === 'update';
$pageTitle = $isEdit ? __('Edit Entity', 'saga-manager') : __('Add New Entity', 'saga-manager');
$submitText = $isEdit ? __('Update Entity', 'saga-manager') : __('Create Entity', 'saga-manager');
$nonceAction = $isEdit ? 'saga_update_entity' : 'saga_create_entity';
$formAction = $isEdit ? 'update_entity' : 'create_entity';
?>

<div class="wrap saga-manager-entity-form">
    <h1 class="wp-heading-inline"><?php echo esc_html($pageTitle); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-entities')); ?>" class="page-title-action">
        <?php esc_html_e('Back to List', 'saga-manager'); ?>
    </a>
    <hr class="wp-header-end">

    <?php foreach ($notices as $notice): ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endforeach; ?>

    <?php if (empty($sagas)): ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('You need to create a saga before adding entities.', 'saga-manager'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=saga-manager-sagas&action=add')); ?>">
                    <?php esc_html_e('Create a saga', 'saga-manager'); ?>
                </a>
            </p>
        </div>
    <?php else: ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="saga-entity-form" class="saga-form">
        <input type="hidden" name="action" value="saga_form_handler" />
        <input type="hidden" name="saga_manager_action" value="<?php echo esc_attr($formAction); ?>" />
        <?php wp_nonce_field($nonceAction, '_saga_nonce'); ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="entity_id" value="<?php echo absint($entity['id']); ?>" />
        <?php endif; ?>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">

                <!-- Main Content -->
                <div id="post-body-content">
                    <div id="titlediv">
                        <div id="titlewrap">
                            <label class="screen-reader-text" for="canonical_name"><?php esc_html_e('Name', 'saga-manager'); ?></label>
                            <input type="text" name="canonical_name" id="canonical_name" class="saga-title-input"
                                   value="<?php echo esc_attr($entity['canonical_name'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('Enter entity name', 'saga-manager'); ?>"
                                   required />
                        </div>
                        <div class="inside">
                            <div id="edit-slug-box">
                                <label for="slug"><?php esc_html_e('Slug:', 'saga-manager'); ?></label>
                                <input type="text" name="slug" id="slug" class="saga-slug-input"
                                       value="<?php echo esc_attr($entity['slug'] ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('auto-generated-from-name', 'saga-manager'); ?>" />
                                <span class="saga-slug-hint"><?php esc_html_e('Leave empty to auto-generate', 'saga-manager'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Attributes -->
                    <div id="saga-attributes-container" class="postbox" style="<?php echo empty($attributeDefinitions) ? 'display:none;' : ''; ?>">
                        <h2 class="hndle"><?php esc_html_e('Entity Attributes', 'saga-manager'); ?></h2>
                        <div class="inside" id="saga-attributes-fields">
                            <?php if (!empty($attributeDefinitions)): ?>
                                <?php foreach ($attributeDefinitions as $attr): ?>
                                    <?php
                                    $attrId = (int) $attr['id'];
                                    $value = $attributeValues[$attrId] ?? ($attr['default_value'] ?? '');
                                    $fieldName = "attributes[{$attrId}]";
                                    $fieldId = "attr_{$attrId}";
                                    $isRequired = (bool) $attr['is_required'];
                                    ?>
                                    <p class="saga-attribute-field">
                                        <label for="<?php echo esc_attr($fieldId); ?>">
                                            <?php echo esc_html($attr['display_name']); ?>
                                            <?php if ($isRequired): ?>
                                                <span class="required">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php
                                        switch ($attr['data_type']) {
                                            case 'text':
                                                echo '<textarea name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" class="large-text" rows="4"' . ($isRequired ? ' required' : '') . '>' . esc_textarea($value) . '</textarea>';
                                                break;
                                            case 'bool':
                                                echo '<input type="checkbox" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" value="1"' . checked($value, true, false) . ' />';
                                                break;
                                            case 'int':
                                                echo '<input type="number" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" class="regular-text" value="' . esc_attr($value) . '"' . ($isRequired ? ' required' : '') . ' step="1" />';
                                                break;
                                            case 'float':
                                                echo '<input type="number" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" class="regular-text" value="' . esc_attr($value) . '"' . ($isRequired ? ' required' : '') . ' step="any" />';
                                                break;
                                            case 'date':
                                                echo '<input type="date" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" class="regular-text" value="' . esc_attr($value) . '"' . ($isRequired ? ' required' : '') . ' />';
                                                break;
                                            default:
                                                echo '<input type="text" name="' . esc_attr($fieldName) . '" id="' . esc_attr($fieldId) . '" class="regular-text" value="' . esc_attr($value) . '"' . ($isRequired ? ' required' : '') . ' />';
                                        }
                                        ?>
                                    </p>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <p class="saga-no-attributes" style="<?php echo !empty($attributeDefinitions) ? 'display:none;' : ''; ?>">
                                <?php esc_html_e('No custom attributes defined for this entity type.', 'saga-manager'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div id="postbox-container-1" class="postbox-container">
                    <div id="submitdiv" class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Publish', 'saga-manager'); ?></h2>
                        <div class="inside">
                            <div class="submitbox" id="submitpost">
                                <?php if ($isEdit): ?>
                                    <div id="misc-publishing-actions">
                                        <div class="misc-pub-section">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php esc_html_e('Created:', 'saga-manager'); ?>
                                            <strong>
                                                <?php
                                                $created = new DateTime($entity['created_at']);
                                                echo esc_html($created->format(get_option('date_format') . ' ' . get_option('time_format')));
                                                ?>
                                            </strong>
                                        </div>
                                        <div class="misc-pub-section">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php esc_html_e('Modified:', 'saga-manager'); ?>
                                            <strong>
                                                <?php
                                                $updated = new DateTime($entity['updated_at']);
                                                echo esc_html($updated->format(get_option('date_format') . ' ' . get_option('time_format')));
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div id="major-publishing-actions">
                                    <?php if ($isEdit): ?>
                                        <div id="delete-action">
                                            <a href="<?php echo esc_url(wp_nonce_url(
                                                admin_url('admin.php?page=saga-manager-entities&action=delete&id=' . $entity['id']),
                                                'saga_delete_entity_' . $entity['id']
                                            )); ?>" class="submitdelete deletion" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this entity?', 'saga-manager'); ?>');">
                                                <?php esc_html_e('Delete', 'saga-manager'); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div id="publishing-action">
                                        <button type="submit" class="button button-primary button-large" id="saga-submit-btn">
                                            <?php echo esc_html($submitText); ?>
                                        </button>
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="saga-entity-details" class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Entity Details', 'saga-manager'); ?></h2>
                        <div class="inside">
                            <p>
                                <label for="saga_id"><?php esc_html_e('Saga:', 'saga-manager'); ?> <span class="required">*</span></label>
                                <select name="saga_id" id="saga_id" class="widefat" required>
                                    <option value=""><?php esc_html_e('Select Saga', 'saga-manager'); ?></option>
                                    <?php foreach ($sagas as $saga): ?>
                                        <option value="<?php echo absint($saga['id']); ?>"
                                                <?php selected($entity['saga_id'] ?? '', $saga['id']); ?>>
                                            <?php echo esc_html($saga['name']); ?>
                                            <?php if ($saga['universe']): ?>
                                                (<?php echo esc_html($saga['universe']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p>
                                <label for="entity_type"><?php esc_html_e('Entity Type:', 'saga-manager'); ?> <span class="required">*</span></label>
                                <select name="entity_type" id="entity_type" class="widefat" required <?php echo $isEdit ? 'disabled' : ''; ?>>
                                    <option value=""><?php esc_html_e('Select Type', 'saga-manager'); ?></option>
                                    <?php foreach (EntityType::cases() as $type): ?>
                                        <option value="<?php echo esc_attr($type->value); ?>"
                                                <?php selected($entity['entity_type'] ?? '', $type->value); ?>>
                                            <?php echo esc_html($type->label()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isEdit): ?>
                                    <input type="hidden" name="entity_type" value="<?php echo esc_attr($entity['entity_type']); ?>" />
                                    <span class="description"><?php esc_html_e('Entity type cannot be changed.', 'saga-manager'); ?></span>
                                <?php endif; ?>
                            </p>

                            <p>
                                <label for="importance_score"><?php esc_html_e('Importance Score (0-100):', 'saga-manager'); ?></label>
                                <input type="range" name="importance_score" id="importance_score"
                                       min="0" max="100" step="5"
                                       value="<?php echo absint($entity['importance_score'] ?? 50); ?>"
                                       class="widefat saga-importance-slider" />
                                <span class="saga-importance-display"><?php echo absint($entity['importance_score'] ?? 50); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-generate slug from name
    var slugGenerated = <?php echo ($entity['slug'] ?? '') ? 'false' : 'true'; ?>;
    var $nameInput = $('#canonical_name');
    var $slugInput = $('#slug');

    $nameInput.on('keyup', function() {
        if (slugGenerated || !$slugInput.val()) {
            var slug = $(this).val()
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 200);
            $slugInput.val(slug);
        }
    });

    $slugInput.on('focus', function() {
        slugGenerated = false;
    });

    // Update importance display
    $('#importance_score').on('input', function() {
        $('.saga-importance-display').text($(this).val());
    });

    // Load dynamic attributes when entity type changes
    $('#entity_type').on('change', function() {
        var entityType = $(this).val();
        var $container = $('#saga-attributes-container');
        var $fields = $('#saga-attributes-fields');

        if (!entityType) {
            $container.hide();
            return;
        }

        // Fetch attribute definitions via AJAX
        $.ajax({
            url: sagaManagerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_attribute_definitions',
                nonce: sagaManagerAdmin.nonce,
                entity_type: entityType
            },
            success: function(response) {
                if (response.success && response.data.attributes.length > 0) {
                    var html = '';
                    response.data.attributes.forEach(function(attr) {
                        html += renderAttributeField(attr);
                    });
                    $fields.html(html);
                    $container.show();
                } else {
                    $fields.html('<p class="saga-no-attributes"><?php esc_html_e('No custom attributes defined for this entity type.', 'saga-manager'); ?></p>');
                    $container.show();
                }
            }
        });
    });

    function renderAttributeField(attr) {
        var fieldName = 'attributes[' + attr.id + ']';
        var fieldId = 'attr_' + attr.id;
        var required = attr.is_required ? ' required' : '';
        var requiredMark = attr.is_required ? ' <span class="required">*</span>' : '';

        var html = '<p class="saga-attribute-field">';
        html += '<label for="' + fieldId + '">' + attr.display_name + requiredMark + '</label>';

        switch (attr.data_type) {
            case 'text':
                html += '<textarea name="' + fieldName + '" id="' + fieldId + '" class="large-text" rows="4"' + required + '>' + (attr.default_value || '') + '</textarea>';
                break;
            case 'bool':
                html += '<input type="checkbox" name="' + fieldName + '" id="' + fieldId + '" value="1" />';
                break;
            case 'int':
                html += '<input type="number" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' + (attr.default_value || '') + '"' + required + ' step="1" />';
                break;
            case 'float':
                html += '<input type="number" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' + (attr.default_value || '') + '"' + required + ' step="any" />';
                break;
            case 'date':
                html += '<input type="date" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' + (attr.default_value || '') + '"' + required + ' />';
                break;
            default:
                html += '<input type="text" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' + (attr.default_value || '') + '"' + required + ' />';
        }

        html += '</p>';
        return html;
    }
});
</script>
