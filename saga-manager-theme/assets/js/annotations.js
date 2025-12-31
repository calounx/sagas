/**
 * Saga Annotations JavaScript
 *
 * Handles annotation UI interactions, AJAX requests, text selection,
 * and annotation management.
 *
 * @package Saga_Manager_Theme
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SagaAnnotations = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initTextSelection();
            this.initDashboardFeatures();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Modal controls
            $(document).on('click', '#saga-add-annotation-button', this.openModal.bind(this));
            $(document).on('click', '.saga-annotation-modal__close, .saga-annotation-form__cancel', this.closeModal.bind(this));
            $(document).on('click', '.saga-annotation-modal__overlay', this.closeModal.bind(this));

            // Form submission
            $(document).on('submit', '#saga-annotation-form', this.saveAnnotation.bind(this));

            // Annotation actions
            $(document).on('click', '.saga-annotation__edit', this.editAnnotation.bind(this));
            $(document).on('click', '.saga-annotation__delete', this.deleteAnnotation.bind(this));
            $(document).on('click', '.saga-annotation__toggle', this.toggleAnnotation.bind(this));

            // Remove quote button
            $(document).on('click', '.saga-annotation-form__remove-quote', this.removeQuote.bind(this));

            // Tag input
            $(document).on('input', '#annotation-tags', this.handleTagInput.bind(this));

            // Escape key to close modal
            $(document).on('keydown', this.handleEscapeKey.bind(this));
        },

        /**
         * Initialize text selection for highlighting
         */
        initTextSelection: function() {
            if (!$('body').hasClass('single-saga_entity')) {
                return;
            }

            let selectionTimer;
            const self = this;

            $(document).on('mouseup touchend', '.entry-content', function(e) {
                clearTimeout(selectionTimer);

                selectionTimer = setTimeout(function() {
                    const selection = window.getSelection();
                    const selectedText = selection.toString().trim();

                    // Remove existing popup
                    $('.saga-selection-popup').remove();

                    if (selectedText.length > 10 && selectedText.length < 500) {
                        self.showSelectionPopup(selection, selectedText);
                    }
                }, 150);
            });

            // Clear popup on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.saga-selection-popup').length) {
                    $('.saga-selection-popup').remove();
                }
            });
        },

        /**
         * Show popup for text selection
         */
        showSelectionPopup: function(selection, text) {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();

            const popup = $('<div class="saga-selection-popup">')
                .html('<button type="button" class="saga-selection-popup__button">' +
                      '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">' +
                      '<path d="M8 3V13M3 8H13" stroke="currentColor" stroke-width="2"/>' +
                      '</svg>' +
                      sagaAnnotations.strings.annotateSelection ||
                      'Add Note' +
                      '</button>')
                .css({
                    position: 'fixed',
                    top: (rect.top + window.scrollY - 40) + 'px',
                    left: (rect.left + rect.width / 2) + 'px',
                    transform: 'translateX(-50%)'
                });

            const self = this;
            popup.find('.saga-selection-popup__button').on('click', function() {
                self.openModalWithQuote(text);
                $('.saga-selection-popup').remove();
                selection.removeAllRanges();
            });

            $('body').append(popup);
        },

        /**
         * Open modal with quoted text
         */
        openModalWithQuote: function(quote) {
            $('#annotation-quote').val(quote);
            $('#annotation-quote-display').html('<p>' + this.escapeHtml(quote) + '</p>').closest('.saga-annotation-form__quote-container').show();
            this.openModal();
        },

        /**
         * Open annotation modal
         */
        openModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            if (!sagaAnnotations.nonce) {
                alert(sagaAnnotations.strings.loginRequired);
                return;
            }

            const $modal = $('#saga-annotation-modal');

            // Reset form if not editing
            if (!$(e?.target).hasClass('saga-annotation__edit')) {
                this.resetForm();
            }

            $modal.fadeIn(200);
            $modal.find('.saga-annotation-modal__content').attr('tabindex', '-1').focus();
            $('body').addClass('saga-modal-open');

            // Initialize TinyMCE if not already initialized
            if (typeof tinymce !== 'undefined' && !tinymce.get('annotation-content')) {
                tinymce.init({
                    selector: '#annotation-content',
                    menubar: false,
                    statusbar: false,
                    height: 200,
                    plugins: 'lists link',
                    toolbar: 'bold italic underline strikethrough | bullist numlist | link unlink | undo redo'
                });
            }
        },

        /**
         * Close annotation modal
         */
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            const $modal = $('#saga-annotation-modal');
            $modal.fadeOut(200);
            $('body').removeClass('saga-modal-open');

            // Return focus to button that opened modal
            $('#saga-add-annotation-button').focus();
        },

        /**
         * Reset annotation form
         */
        resetForm: function() {
            const $form = $('#saga-annotation-form');

            $form.find('#annotation-id').val('');
            $form.find('#annotation-section').val('');
            $form.find('#annotation-quote').val('');
            $form.find('#annotation-quote-display').html('').closest('.saga-annotation-form__quote-container').hide();
            $form.find('#annotation-tags').val('');
            $form.find('input[name="visibility"][value="private"]').prop('checked', true);

            // Reset TinyMCE
            if (typeof tinymce !== 'undefined' && tinymce.get('annotation-content')) {
                tinymce.get('annotation-content').setContent('');
            } else {
                $form.find('#annotation-content').val('');
            }

            $('.saga-annotation-form__message').hide().empty();
            $('.saga-annotation-modal__title').text(sagaAnnotations.strings.addAnnotation || 'Add Annotation');
        },

        /**
         * Save annotation via AJAX
         */
        saveAnnotation: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $submitBtn = $form.find('.saga-annotation-form__submit');
            const $spinner = $form.find('.saga-annotation-form__submit-spinner');
            const $message = $('.saga-annotation-form__message');

            // Get content from TinyMCE or textarea
            let content;
            if (typeof tinymce !== 'undefined' && tinymce.get('annotation-content')) {
                content = tinymce.get('annotation-content').getContent();
            } else {
                content = $('#annotation-content').val();
            }

            if (!content || content.trim() === '') {
                this.showMessage($message, sagaAnnotations.strings.emptyContent || 'Please enter annotation content.', 'error');
                return;
            }

            // Parse tags
            const tagsInput = $('#annotation-tags').val();
            const tags = tagsInput ? tagsInput.split(',').map(t => t.trim()).filter(t => t).slice(0, sagaAnnotations.maxTags) : [];

            if (tags.length > sagaAnnotations.maxTags) {
                this.showMessage($message, sagaAnnotations.strings.tooManyTags, 'error');
                return;
            }

            const data = {
                action: 'saga_save_annotation',
                nonce: sagaAnnotations.nonce,
                annotation_id: $('#annotation-id').val(),
                entity_id: $('#annotation-entity-id').val(),
                section: $('#annotation-section').val(),
                quote: $('#annotation-quote').val(),
                content: content,
                tags: tags,
                visibility: $form.find('input[name="visibility"]:checked').val()
            };

            $submitBtn.prop('disabled', true);
            $spinner.show();

            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage($message, response.data.message, 'success');

                        setTimeout(() => {
                            this.closeModal();
                            location.reload(); // Reload to show updated annotations
                        }, 1000);
                    } else {
                        this.showMessage($message, response.data.message || sagaAnnotations.strings.error, 'error');
                    }
                },
                error: () => {
                    this.showMessage($message, sagaAnnotations.strings.error, 'error');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        },

        /**
         * Edit annotation
         */
        editAnnotation: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const annotationId = $button.data('annotation-id');
            const $annotation = $('#annotation-' + annotationId);

            // Populate form with annotation data
            const entityId = $('#annotation-entity-id').val() || $annotation.data('entity-id');

            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_get_annotations',
                    nonce: sagaAnnotations.nonce,
                    entity_id: entityId
                },
                success: (response) => {
                    if (response.success) {
                        const annotation = response.data.annotations.find(a => a.id === annotationId);

                        if (annotation) {
                            this.populateForm(annotation);
                            this.openModal();
                        }
                    }
                }
            });
        },

        /**
         * Populate form with annotation data
         */
        populateForm: function(annotation) {
            $('#annotation-id').val(annotation.id);
            $('#annotation-section').val(annotation.section || '');
            $('#annotation-quote').val(annotation.quote || '');
            $('#annotation-tags').val((annotation.tags || []).join(', '));
            $('input[name="visibility"][value="' + (annotation.visibility || 'private') + '"]').prop('checked', true);

            if (annotation.quote) {
                $('#annotation-quote-display').html(annotation.quote).closest('.saga-annotation-form__quote-container').show();
            } else {
                $('#annotation-quote-display').html('').closest('.saga-annotation-form__quote-container').hide();
            }

            // Set content in TinyMCE
            if (typeof tinymce !== 'undefined' && tinymce.get('annotation-content')) {
                tinymce.get('annotation-content').setContent(annotation.content || '');
            } else {
                $('#annotation-content').val(annotation.content || '');
            }

            $('.saga-annotation-modal__title').text(sagaAnnotations.strings.editAnnotation || 'Edit Annotation');
        },

        /**
         * Delete annotation
         */
        deleteAnnotation: function(e) {
            e.preventDefault();

            if (!confirm(sagaAnnotations.strings.deleteConfirm)) {
                return;
            }

            const $button = $(e.currentTarget);
            const annotationId = $button.data('annotation-id');

            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_delete_annotation',
                    nonce: sagaAnnotations.nonce,
                    annotation_id: annotationId
                },
                success: (response) => {
                    if (response.success) {
                        const $annotation = $('#annotation-' + annotationId);
                        $annotation.fadeOut(300, function() {
                            $(this).remove();

                            // Check if any annotations remain
                            if ($('.saga-annotation').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message || sagaAnnotations.strings.error);
                    }
                },
                error: () => {
                    alert(sagaAnnotations.strings.error);
                }
            });
        },

        /**
         * Toggle annotation visibility
         */
        toggleAnnotation: function(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $annotation = $button.closest('.saga-annotation');
            const $body = $annotation.find('.saga-annotation__body');
            const isExpanded = $button.attr('aria-expanded') === 'true';

            $body.slideToggle(200);
            $button.attr('aria-expanded', !isExpanded);
            $annotation.toggleClass('is-collapsed');
        },

        /**
         * Remove quote from annotation
         */
        removeQuote: function(e) {
            e.preventDefault();

            $('#annotation-quote').val('');
            $('#annotation-quote-display').html('').closest('.saga-annotation-form__quote-container').hide();
        },

        /**
         * Handle tag input with autocomplete
         */
        handleTagInput: function(e) {
            const $input = $(e.target);
            const value = $input.val();
            const lastComma = value.lastIndexOf(',');
            const currentTag = lastComma >= 0 ? value.substring(lastComma + 1).trim() : value.trim();

            if (currentTag.length < 2) {
                $('#annotation-tags-suggestions').hide().empty();
                return;
            }

            // Get user tags
            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_get_user_tags',
                    nonce: sagaAnnotations.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const suggestions = response.data.tags.filter(tag =>
                            tag.toLowerCase().includes(currentTag.toLowerCase()) &&
                            !value.includes(tag)
                        ).slice(0, 5);

                        this.showTagSuggestions(suggestions, $input);
                    }
                }
            });
        },

        /**
         * Show tag suggestions
         */
        showTagSuggestions: function(suggestions, $input) {
            const $container = $('#annotation-tags-suggestions');
            $container.empty();

            if (suggestions.length === 0) {
                $container.hide();
                return;
            }

            suggestions.forEach(tag => {
                const $option = $('<div class="saga-annotation-form__tag-suggestion" role="option">')
                    .text(tag)
                    .on('click', () => {
                        const currentValue = $input.val();
                        const lastComma = currentValue.lastIndexOf(',');
                        const newValue = lastComma >= 0
                            ? currentValue.substring(0, lastComma + 1) + ' ' + tag
                            : tag;

                        $input.val(newValue + ', ').focus();
                        $container.hide().empty();
                    });

                $container.append($option);
            });

            $container.show();
        },

        /**
         * Handle escape key to close modal
         */
        handleEscapeKey: function(e) {
            if (e.key === 'Escape' && $('#saga-annotation-modal').is(':visible')) {
                this.closeModal();
            }
        },

        /**
         * Initialize dashboard features
         */
        initDashboardFeatures: function() {
            if (!$('body').hasClass('page-template-my-annotations')) {
                return;
            }

            // Search annotations
            $('#annotation-search').on('input', this.searchAnnotations.bind(this));

            // Filter annotations
            $('#filter-tag, #filter-visibility').on('change', this.filterAnnotations.bind(this));

            // Sort annotations
            $('#sort-by').on('change', this.sortAnnotations.bind(this));

            // Export annotations
            $('#export-annotations').on('click', this.toggleExportMenu.bind(this));
            $('.saga-export-menu__option').on('click', this.exportAnnotations.bind(this));

            // Delete from dashboard
            $('.saga-annotation-card__delete').on('click', this.deleteFromDashboard.bind(this));
        },

        /**
         * Search annotations
         */
        searchAnnotations: function(e) {
            const query = $(e.target).val().toLowerCase();
            let visibleCount = 0;

            $('.saga-annotation-card').each(function() {
                const $card = $(this);
                const content = $card.text().toLowerCase();

                if (content.includes(query)) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });

            $('#no-results').toggle(visibleCount === 0);
        },

        /**
         * Filter annotations
         */
        filterAnnotations: function() {
            const tag = $('#filter-tag').val();
            const visibility = $('#filter-visibility').val();
            let visibleCount = 0;

            $('.saga-annotation-card').each(function() {
                const $card = $(this);
                const tags = JSON.parse($card.data('tags') || '[]');
                const cardVisibility = $card.data('visibility');

                let showCard = true;

                if (tag && !tags.includes(tag)) {
                    showCard = false;
                }

                if (visibility && cardVisibility !== visibility) {
                    showCard = false;
                }

                if (showCard) {
                    $card.show();
                    visibleCount++;
                } else {
                    $card.hide();
                }
            });

            $('#no-results').toggle(visibleCount === 0);
        },

        /**
         * Sort annotations
         */
        sortAnnotations: function(e) {
            const sortBy = $(e.target).val();
            const $list = $('#annotations-list');
            const $cards = $list.find('.saga-annotation-card').get();

            $cards.sort((a, b) => {
                const $a = $(a);
                const $b = $(b);

                switch (sortBy) {
                    case 'updated-desc':
                        return new Date($b.data('updated')) - new Date($a.data('updated'));
                    case 'updated-asc':
                        return new Date($a.data('updated')) - new Date($b.data('updated'));
                    case 'created-desc':
                        return new Date($b.data('created')) - new Date($a.data('created'));
                    case 'created-asc':
                        return new Date($a.data('created')) - new Date($b.data('created'));
                    default:
                        return 0;
                }
            });

            $list.empty().append($cards);
        },

        /**
         * Toggle export menu
         */
        toggleExportMenu: function(e) {
            e.preventDefault();
            $('#export-menu').toggle();
        },

        /**
         * Export annotations
         */
        exportAnnotations: function(e) {
            e.preventDefault();

            const format = $(e.currentTarget).data('format');

            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'saga_export_annotations',
                    nonce: sagaAnnotations.nonce,
                    format: format
                },
                success: (response) => {
                    if (response.success) {
                        this.downloadFile(response.data.data, format);
                        $('#export-menu').hide();
                    } else {
                        alert(response.data.message || sagaAnnotations.strings.error);
                    }
                },
                error: () => {
                    alert(sagaAnnotations.strings.error);
                }
            });
        },

        /**
         * Download file
         */
        downloadFile: function(content, format) {
            const mimeTypes = {
                'markdown': 'text/markdown',
                'json': 'application/json'
            };

            const extensions = {
                'markdown': 'md',
                'json': 'json'
            };

            const blob = new Blob([content], { type: mimeTypes[format] });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');

            a.href = url;
            a.download = 'saga-annotations-' + Date.now() + '.' + extensions[format];
            document.body.appendChild(a);
            a.click();

            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        },

        /**
         * Delete annotation from dashboard
         */
        deleteFromDashboard: function(e) {
            e.preventDefault();

            if (!confirm(sagaAnnotations.strings.deleteConfirm)) {
                return;
            }

            const $button = $(e.currentTarget);
            const annotationId = $button.data('annotation-id');
            const $card = $button.closest('.saga-annotation-card');

            $.ajax({
                url: sagaAnnotations.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_delete_annotation',
                    nonce: sagaAnnotations.nonce,
                    annotation_id: annotationId
                },
                success: (response) => {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();

                            if ($('.saga-annotation-card').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message || sagaAnnotations.strings.error);
                    }
                },
                error: () => {
                    alert(sagaAnnotations.strings.error);
                }
            });
        },

        /**
         * Show message
         */
        showMessage: function($container, message, type) {
            $container
                .removeClass('saga-annotation-form__message--success saga-annotation-form__message--error')
                .addClass('saga-annotation-form__message--' + type)
                .html(message)
                .slideDown(200);

            setTimeout(() => {
                $container.slideUp(200);
            }, 5000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SagaAnnotations.init();
    });

})(jQuery);
