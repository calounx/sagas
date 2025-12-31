/**
 * Saga Manager - Quick Create Entity Modal
 *
 * @package SagaManager
 * @since 1.3.0
 */

(function($) {
    'use strict';

    const SagaQuickCreate = {
        modal: null,
        form: null,
        isOpen: false,
        isDirty: false,
        autosaveTimeout: null,
        duplicateCheckTimeout: null,

        /**
         * Initialize quick create system
         */
        init() {
            this.modal = $('#saga-quick-create-modal');
            this.form = $('#saga-quick-create-form');

            if (!this.modal.length) {
                return;
            }

            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.loadDraft();
            this.initializeImportanceSlider();
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Admin bar triggers
            $(document).on('click', '.saga-quick-create-trigger, .saga-quick-create-type', (e) => {
                e.preventDefault();
                const entityType = $(e.currentTarget).data('entity-type');
                this.openModal(entityType);
            });

            // Close modal
            $(document).on('click', '[data-close-modal]', (e) => {
                e.preventDefault();
                this.closeModal();
            });

            // Form submission
            this.form.on('submit', (e) => {
                e.preventDefault();
                const status = $(e.originalEvent.submitter).data('status') || 'draft';
                this.submitForm(status);
            });

            // Entity name duplicate check
            $('#saga-entity-name').on('input', () => {
                this.checkDuplicate();
            });

            // Form changes for autosave
            this.form.on('change input', () => {
                this.isDirty = true;
                this.scheduleAutosave();
            });

            // Entity type change
            $('input[name="entity_type"]').on('change', () => {
                this.onEntityTypeChange();
            });

            // Advanced options toggle
            $(document).on('click', '[data-toggle="advanced-options"]', (e) => {
                e.preventDefault();
                this.toggleAdvancedOptions();
            });

            // Featured image upload
            $(document).on('click', '.saga-upload-image', (e) => {
                e.preventDefault();
                this.openMediaUploader();
            });

            // Remove featured image
            $(document).on('click', '.saga-remove-image', (e) => {
                e.preventDefault();
                this.removeFeaturedImage();
            });

            // Template buttons
            $(document).on('click', '.saga-template-button', (e) => {
                e.preventDefault();
                const template = $(e.currentTarget).data('template');
                this.applyTemplate(template);
            });

            // Clear draft
            $(document).on('click', '.saga-clear-draft', (e) => {
                e.preventDefault();
                this.clearDraft();
            });

            // Relationship search
            let relationshipSearchTimeout;
            $('#saga-relationship-search').on('input', (e) => {
                clearTimeout(relationshipSearchTimeout);
                relationshipSearchTimeout = setTimeout(() => {
                    this.searchRelationships($(e.target).val());
                }, 300);
            });

            // Notification close
            $(document).on('click', '.saga-notification-close', (e) => {
                $(e.currentTarget).closest('.saga-notification').fadeOut();
            });

            // Importance slider
            $('#saga-importance').on('input', (e) => {
                $('.saga-importance-value').text($(e.target).val());
            });
        },

        /**
         * Setup keyboard shortcuts
         */
        setupKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                // Ctrl+Shift+E - Open modal
                if (e.ctrlKey && e.shiftKey && e.key === 'E') {
                    e.preventDefault();
                    this.openModal();
                }

                // Escape - Close modal
                if (e.key === 'Escape' && this.isOpen) {
                    e.preventDefault();
                    this.closeModal();
                }

                // Ctrl+Enter - Submit form
                if (e.ctrlKey && e.key === 'Enter' && this.isOpen) {
                    e.preventDefault();
                    this.submitForm('publish');
                }
            });

            // Tab trapping in modal
            this.modal.on('keydown', (e) => {
                if (e.key === 'Tab') {
                    this.trapFocus(e);
                }
            });
        },

        /**
         * Open modal
         *
         * @param {string|null} entityType - Pre-select entity type
         */
        openModal(entityType = null) {
            if (this.isOpen) {
                return;
            }

            this.isOpen = true;
            this.modal.fadeIn(200).attr('aria-hidden', 'false');
            $('body').addClass('saga-modal-open');

            if (entityType) {
                $(`input[name="entity_type"][value="${entityType}"]`).prop('checked', true).trigger('change');
            }

            // Focus first input
            setTimeout(() => {
                $('#saga-entity-name').focus();
            }, 250);
        },

        /**
         * Close modal
         */
        closeModal() {
            if (!this.isOpen) {
                return;
            }

            // Confirm if form is dirty
            if (this.isDirty && !confirm(sagaQuickCreate.i18n.confirm_cancel)) {
                return;
            }

            this.isOpen = false;
            this.modal.fadeOut(200).attr('aria-hidden', 'true');
            $('body').removeClass('saga-modal-open');

            // Reset form after animation
            setTimeout(() => {
                this.resetForm();
            }, 200);
        },

        /**
         * Submit form via AJAX
         *
         * @param {string} status - 'draft' or 'publish'
         */
        submitForm(status) {
            // Clear previous errors
            $('.saga-field-error').text('').hide();

            // Validate
            if (!this.validateForm()) {
                return;
            }

            // Show loading
            this.showLoading(true);

            // Get TinyMCE content
            if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('saga-entity-description');
                if (editor) {
                    $('#saga-entity-description').val(editor.getContent());
                }
            }

            // Prepare form data
            const formData = this.form.serializeArray();
            formData.push({ name: 'action', value: 'saga_quick_create' });
            formData.push({ name: 'nonce', value: sagaQuickCreate.nonce });
            formData.push({ name: 'status', value: status });

            // AJAX request
            $.ajax({
                url: sagaQuickCreate.ajaxUrl,
                type: 'POST',
                data: $.param(formData),
                dataType: 'json',
                success: (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        this.onCreateSuccess(response.data);
                    } else {
                        this.onCreateError(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    this.showLoading(false);
                    this.showNotification('error', sagaQuickCreate.i18n.error);
                    console.error('Quick create error:', error);
                }
            });
        },

        /**
         * Validate form
         *
         * @returns {boolean}
         */
        validateForm() {
            let isValid = true;

            // Required fields
            const name = $('#saga-entity-name').val().trim();
            if (!name) {
                this.showFieldError('name', sagaQuickCreate.i18n.required);
                isValid = false;
            }

            // Entity type
            if (!$('input[name="entity_type"]:checked').length) {
                this.showFieldError('entity_type', sagaQuickCreate.i18n.required);
                isValid = false;
            }

            return isValid;
        },

        /**
         * Show field error
         *
         * @param {string} field
         * @param {string} message
         */
        showFieldError(field, message) {
            const errorEl = $(`.saga-field-error[data-field="${field}"]`);
            errorEl.text(message).show();

            // Focus field
            $(`[name="${field}"]`).first().focus();
        },

        /**
         * Handle successful creation
         *
         * @param {object} data
         */
        onCreateSuccess(data) {
            this.showNotification('success', data.message, {
                editUrl: data.edit_url,
                viewUrl: data.view_url
            });

            this.closeModal();
            this.clearDraft();

            // Reload admin bar to update count
            this.updateAdminBarCount();
        },

        /**
         * Handle creation error
         *
         * @param {object} data
         */
        onCreateError(data) {
            this.showNotification('error', data.message);

            // Show field-specific errors
            if (data.errors) {
                Object.keys(data.errors).forEach(field => {
                    this.showFieldError(field, data.errors[field]);
                });
            }
        },

        /**
         * Show notification
         *
         * @param {string} type - 'success' or 'error'
         * @param {string} message
         * @param {object} options
         */
        showNotification(type, message, options = {}) {
            const notification = $(`#saga-${type}-notification`).clone();
            notification.removeAttr('id').css('display', 'flex');

            notification.find('.saga-notification-message').text(message);

            if (options.editUrl) {
                notification.find('.saga-notification-link').eq(0).attr('href', options.editUrl);
                notification.find('.saga-notification-link').eq(1).attr('href', options.viewUrl);
            }

            $('body').append(notification);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);
        },

        /**
         * Show/hide loading overlay
         *
         * @param {boolean} show
         */
        showLoading(show) {
            const overlay = this.modal.find('.saga-loading-overlay');

            if (show) {
                overlay.fadeIn(200);
            } else {
                overlay.fadeOut(200);
            }
        },

        /**
         * Check for duplicate entity name
         */
        checkDuplicate() {
            clearTimeout(this.duplicateCheckTimeout);

            const name = $('#saga-entity-name').val().trim();
            const indicator = $('.saga-duplicate-indicator');

            if (!name) {
                indicator.hide();
                return;
            }

            this.duplicateCheckTimeout = setTimeout(() => {
                $.ajax({
                    url: sagaQuickCreate.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'saga_check_duplicate',
                        nonce: sagaQuickCreate.nonce,
                        name: name,
                        saga_id: $('#saga-select').val() || 1
                    },
                    success: (response) => {
                        if (response.success && response.data.is_duplicate) {
                            indicator.fadeIn();
                        } else {
                            indicator.fadeOut();
                        }
                    }
                });
            }, 500);
        },

        /**
         * Entity type change handler
         */
        onEntityTypeChange() {
            const entityType = $('input[name="entity_type"]:checked').val();

            // Load templates for this type
            this.loadTemplates(entityType);

            // Update placeholder text based on type
            const placeholders = {
                character: 'e.g., Luke Skywalker',
                location: 'e.g., Tatooine',
                event: 'e.g., Battle of Yavin',
                faction: 'e.g., Rebel Alliance',
                artifact: 'e.g., Lightsaber',
                concept: 'e.g., The Force'
            };

            $('#saga-entity-name').attr('placeholder', placeholders[entityType] || 'Enter entity name...');
        },

        /**
         * Load templates for entity type
         *
         * @param {string} entityType
         */
        loadTemplates(entityType) {
            $.ajax({
                url: sagaQuickCreate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_get_entity_templates',
                    nonce: sagaQuickCreate.nonce,
                    entity_type: entityType
                },
                success: (response) => {
                    if (response.success && response.data.templates) {
                        this.renderTemplates(response.data.templates);
                    }
                }
            });
        },

        /**
         * Render template buttons
         *
         * @param {array} templates
         */
        renderTemplates(templates) {
            const container = $('.saga-templates-list');
            container.empty();

            templates.forEach(template => {
                const button = $('<button>')
                    .addClass('saga-template-button')
                    .attr('type', 'button')
                    .attr('data-template', template.id)
                    .text(template.name);

                container.append(button);
            });
        },

        /**
         * Apply template
         *
         * @param {string} templateId
         */
        applyTemplate(templateId) {
            // This would fetch template data and populate form
            console.log('Applying template:', templateId);
            // Implementation depends on template structure
        },

        /**
         * Schedule autosave
         */
        scheduleAutosave() {
            clearTimeout(this.autosaveTimeout);

            this.autosaveTimeout = setTimeout(() => {
                this.autosave();
            }, 2000);
        },

        /**
         * Autosave to localStorage
         */
        autosave() {
            const formData = this.form.serializeArray();
            const draft = {};

            formData.forEach(item => {
                draft[item.name] = item.value;
            });

            localStorage.setItem('saga_quick_create_draft', JSON.stringify(draft));

            // Show autosave indicator
            $('.saga-autosave-indicator').addClass('active');
            setTimeout(() => {
                $('.saga-autosave-indicator').removeClass('active');
            }, 1000);
        },

        /**
         * Load draft from localStorage
         */
        loadDraft() {
            const draft = localStorage.getItem('saga_quick_create_draft');

            if (!draft) {
                return;
            }

            try {
                const data = JSON.parse(draft);

                Object.keys(data).forEach(key => {
                    const input = $(`[name="${key}"]`);

                    if (input.is(':radio')) {
                        $(`[name="${key}"][value="${data[key]}"]`).prop('checked', true);
                    } else {
                        input.val(data[key]);
                    }
                });

                $('.saga-draft-recovery').fadeIn();
            } catch (e) {
                console.error('Failed to load draft:', e);
            }
        },

        /**
         * Clear draft
         */
        clearDraft() {
            localStorage.removeItem('saga_quick_create_draft');
            $('.saga-draft-recovery').fadeOut();
        },

        /**
         * Reset form
         */
        resetForm() {
            this.form[0].reset();
            $('.saga-field-error').text('').hide();
            $('.saga-duplicate-indicator').hide();
            this.isDirty = false;

            // Reset TinyMCE
            if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('saga-entity-description');
                if (editor) {
                    editor.setContent('');
                }
            }

            // Reset importance slider
            $('.saga-importance-value').text('50');
            $('#saga-importance').val(50);
        },

        /**
         * Toggle advanced options
         */
        toggleAdvancedOptions() {
            const section = $('#saga-advanced-options');
            const button = $('.saga-toggle-advanced');

            section.slideToggle(300);
            button.toggleClass('active');

            const icon = button.find('.dashicons');
            icon.toggleClass('dashicons-arrow-right-alt2 dashicons-arrow-down-alt2');
        },

        /**
         * Open WordPress media uploader
         */
        openMediaUploader() {
            if (this.mediaUploader) {
                this.mediaUploader.open();
                return;
            }

            this.mediaUploader = wp.media({
                title: 'Select Featured Image',
                button: { text: 'Set Featured Image' },
                multiple: false
            });

            this.mediaUploader.on('select', () => {
                const attachment = this.mediaUploader.state().get('selection').first().toJSON();
                this.setFeaturedImage(attachment);
            });

            this.mediaUploader.open();
        },

        /**
         * Set featured image
         *
         * @param {object} attachment
         */
        setFeaturedImage(attachment) {
            $('#saga-featured-image').val(attachment.id);
            $('.saga-image-preview img').attr('src', attachment.url);
            $('.saga-image-preview').fadeIn();
            $('.saga-upload-image').hide();
        },

        /**
         * Remove featured image
         */
        removeFeaturedImage() {
            $('#saga-featured-image').val('');
            $('.saga-image-preview').fadeOut();
            $('.saga-upload-image').show();
        },

        /**
         * Search relationships
         *
         * @param {string} query
         */
        searchRelationships(query) {
            if (!query || query.length < 2) {
                $('.saga-search-results').hide();
                return;
            }

            // This would make AJAX call to search entities
            console.log('Searching relationships:', query);
        },

        /**
         * Initialize importance slider
         */
        initializeImportanceSlider() {
            const slider = $('#saga-importance');
            const value = slider.val();

            // Set initial gradient
            this.updateSliderGradient(slider, value);

            slider.on('input', (e) => {
                const val = $(e.target).val();
                this.updateSliderGradient($(e.target), val);
            });
        },

        /**
         * Update slider gradient based on value
         *
         * @param {jQuery} slider
         * @param {number} value
         */
        updateSliderGradient(slider, value) {
            const percent = value;
            const gradient = `linear-gradient(90deg, #0073aa ${percent}%, #ddd ${percent}%)`;
            slider.css('background', gradient);
        },

        /**
         * Trap focus within modal
         *
         * @param {Event} e
         */
        trapFocus(e) {
            const focusableElements = this.modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
            const firstElement = focusableElements.first();
            const lastElement = focusableElements.last();

            if (e.shiftKey) {
                if (document.activeElement === firstElement[0]) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                if (document.activeElement === lastElement[0]) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        },

        /**
         * Update admin bar entity count
         */
        updateAdminBarCount() {
            // Would make AJAX call to get updated count
            // For now, just increment displayed count
            const badge = $('.saga-entity-count-badge');
            const current = parseInt(badge.text().replace(/,/g, ''));
            badge.text((current + 1).toLocaleString());
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        SagaQuickCreate.init();
    });

})(jQuery);
