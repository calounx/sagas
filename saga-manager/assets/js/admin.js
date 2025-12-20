/**
 * Saga Manager Admin JavaScript
 */

(function($) {
    'use strict';

    // Namespace
    var SagaManager = window.SagaManager || {};

    /**
     * Initialize admin functionality
     */
    SagaManager.init = function() {
        this.bindEvents();
        this.initSlugGeneration();
        this.initImportanceSlider();
        this.initEntityTypeChange();
        this.initBulkActions();
        this.initConfirmDialogs();
    };

    /**
     * Bind global events
     */
    SagaManager.bindEvents = function() {
        // Dismiss notices
        $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
            $(this).closest('.notice').fadeOut();
        });
    };

    /**
     * Auto-generate slug from canonical name
     */
    SagaManager.initSlugGeneration = function() {
        var $nameInput = $('#canonical_name');
        var $slugInput = $('#slug');
        var slugGenerated = !$slugInput.val();

        if (!$nameInput.length || !$slugInput.length) {
            return;
        }

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
    };

    /**
     * Update importance display value
     */
    SagaManager.initImportanceSlider = function() {
        var $slider = $('#importance_score');
        var $display = $('.saga-importance-display');

        if (!$slider.length) {
            return;
        }

        $slider.on('input', function() {
            $display.text($(this).val());
        });
    };

    /**
     * Load attribute definitions when entity type changes
     */
    SagaManager.initEntityTypeChange = function() {
        var $entityType = $('#entity_type');
        var $container = $('#saga-attributes-container');
        var $fields = $('#saga-attributes-fields');

        if (!$entityType.length) {
            return;
        }

        $entityType.on('change', function() {
            var entityType = $(this).val();

            if (!entityType) {
                $container.hide();
                return;
            }

            SagaManager.loadAttributeDefinitions(entityType, function(attributes) {
                if (attributes && attributes.length > 0) {
                    var html = '';
                    attributes.forEach(function(attr) {
                        html += SagaManager.renderAttributeField(attr);
                    });
                    $fields.html(html);
                    $container.show();
                } else {
                    $fields.html('<p class="saga-no-attributes">' +
                        (sagaManagerAdmin.strings.noAttributes || 'No custom attributes defined for this entity type.') +
                        '</p>');
                    $container.show();
                }
            });
        });
    };

    /**
     * Load attribute definitions via AJAX
     */
    SagaManager.loadAttributeDefinitions = function(entityType, callback) {
        $.ajax({
            url: sagaManagerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saga_get_attribute_definitions',
                nonce: sagaManagerAdmin.nonce,
                entity_type: entityType
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.attributes);
                } else {
                    callback([]);
                }
            },
            error: function() {
                callback([]);
            }
        });
    };

    /**
     * Render a single attribute field
     */
    SagaManager.renderAttributeField = function(attr) {
        var fieldName = 'attributes[' + attr.id + ']';
        var fieldId = 'attr_' + attr.id;
        var required = attr.is_required ? ' required' : '';
        var requiredMark = attr.is_required ? ' <span class="required">*</span>' : '';
        var defaultValue = attr.default_value || '';

        var html = '<p class="saga-attribute-field">';
        html += '<label for="' + fieldId + '">' + SagaManager.escapeHtml(attr.display_name) + requiredMark + '</label>';

        switch (attr.data_type) {
            case 'text':
                html += '<textarea name="' + fieldName + '" id="' + fieldId + '" class="large-text" rows="4"' + required + '>' +
                    SagaManager.escapeHtml(defaultValue) + '</textarea>';
                break;
            case 'bool':
                html += '<input type="checkbox" name="' + fieldName + '" id="' + fieldId + '" value="1" />';
                break;
            case 'int':
                html += '<input type="number" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' +
                    SagaManager.escapeHtml(defaultValue) + '"' + required + ' step="1" />';
                break;
            case 'float':
                html += '<input type="number" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' +
                    SagaManager.escapeHtml(defaultValue) + '"' + required + ' step="any" />';
                break;
            case 'date':
                html += '<input type="date" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' +
                    SagaManager.escapeHtml(defaultValue) + '"' + required + ' />';
                break;
            default:
                html += '<input type="text" name="' + fieldName + '" id="' + fieldId + '" class="regular-text" value="' +
                    SagaManager.escapeHtml(defaultValue) + '"' + required + ' />';
        }

        html += '</p>';
        return html;
    };

    /**
     * Initialize bulk action confirmations
     */
    SagaManager.initBulkActions = function() {
        $('#entities-filter, #sagas-filter').on('submit', function(e) {
            var $form = $(this);
            var action = $form.find('select[name="action"]').val() ||
                         $form.find('select[name="action2"]').val();

            if (action === 'delete') {
                var checkedCount = $form.find('input[type="checkbox"]:checked').not('#cb-select-all-1, #cb-select-all-2').length;

                if (checkedCount > 0) {
                    if (!confirm(sagaManagerAdmin.strings.confirmBulkDelete || 'Are you sure you want to delete the selected items?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            }
        });
    };

    /**
     * Initialize confirmation dialogs for delete links
     */
    SagaManager.initConfirmDialogs = function() {
        $(document).on('click', '.saga-delete-entity, .saga-delete-saga, .submitdelete', function(e) {
            var message = $(this).data('confirm') ||
                          sagaManagerAdmin.strings.confirmDelete ||
                          'Are you sure you want to delete this item?';

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    };

    /**
     * Escape HTML special characters
     */
    SagaManager.escapeHtml = function(text) {
        if (typeof text !== 'string') {
            return text;
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    };

    /**
     * Show loading spinner
     */
    SagaManager.showLoading = function($element) {
        $element.addClass('saga-loading').append('<span class="spinner is-active"></span>');
    };

    /**
     * Hide loading spinner
     */
    SagaManager.hideLoading = function($element) {
        $element.removeClass('saga-loading').find('.spinner').remove();
    };

    /**
     * Show notification
     */
    SagaManager.notify = function(message, type) {
        type = type || 'info';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' +
            SagaManager.escapeHtml(message) + '</p></div>');

        $('.wrap h1').first().after($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * AJAX helper for entity operations
     */
    SagaManager.ajaxRequest = function(action, data, callback) {
        data.action = action;
        data.nonce = sagaManagerAdmin.nonce;

        $.ajax({
            url: sagaManagerAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            beforeSend: function() {
                // Could show loading indicator here
            },
            success: function(response) {
                if (callback) {
                    callback(response.success, response.data);
                }
            },
            error: function(xhr, status, error) {
                if (callback) {
                    callback(false, { message: error });
                }
            }
        });
    };

    /**
     * Search entities (for autocomplete/select2 style dropdowns)
     */
    SagaManager.searchEntities = function(query, sagaId, entityType, callback) {
        SagaManager.ajaxRequest('saga_search_entities', {
            search: query,
            saga_id: sagaId || 0,
            entity_type: entityType || ''
        }, function(success, data) {
            callback(success ? data.entities : []);
        });
    };

    /**
     * Get entity details
     */
    SagaManager.getEntity = function(entityId, callback) {
        SagaManager.ajaxRequest('saga_get_entity', {
            entity_id: entityId
        }, function(success, data) {
            callback(success ? data.entity : null);
        });
    };

    // Initialize on document ready
    $(document).ready(function() {
        SagaManager.init();
    });

    // Expose to global scope
    window.SagaManager = SagaManager;

})(jQuery);
