/**
 * Saga Collections - Bookmark and Collection Management
 *
 * Handles AJAX operations for adding/removing entities to collections,
 * with localStorage fallback for guest users.
 *
 * @package Saga_Manager_Theme
 */

(function($) {
    'use strict';

    /**
     * Collections Manager
     */
    const SagaCollections = {

        /**
         * LocalStorage key for guest bookmarks
         */
        STORAGE_KEY: 'saga_guest_collections',

        /**
         * Toast notification timeout
         */
        TOAST_TIMEOUT: 3000,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initToastContainer();
            this.syncGuestCollections();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Bookmark button toggle
            $(document).on('click', '.saga-bookmark-btn', this.handleBookmarkToggle.bind(this));

            // Collection creation
            $(document).on('submit', '#saga-create-collection-form', this.handleCreateCollection.bind(this));

            // Collection deletion
            $(document).on('click', '.saga-delete-collection', this.handleDeleteCollection.bind(this));

            // Collection rename
            $(document).on('submit', '.saga-rename-collection-form', this.handleRenameCollection.bind(this));

            // Export collection
            $(document).on('click', '.saga-export-collection', this.handleExportCollection.bind(this));

            // Collection selector dropdown
            $(document).on('click', '.saga-collection-selector .dropdown-item', this.handleCollectionSelect.bind(this));
        },

        /**
         * Initialize toast notification container
         */
        initToastContainer: function() {
            if ($('#saga-toast-container').length === 0) {
                $('body').append('<div id="saga-toast-container" class="saga-toast-container"></div>');
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'success') {
            const toast = $(`
                <div class="saga-toast saga-toast-${type}">
                    <span class="saga-toast-message">${this.escapeHtml(message)}</span>
                    <button type="button" class="saga-toast-close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `);

            $('#saga-toast-container').append(toast);

            // Trigger animation
            setTimeout(() => toast.addClass('show'), 10);

            // Auto-dismiss
            const timeout = setTimeout(() => this.hideToast(toast), this.TOAST_TIMEOUT);

            // Manual dismiss
            toast.find('.saga-toast-close').on('click', () => {
                clearTimeout(timeout);
                this.hideToast(toast);
            });
        },

        /**
         * Hide toast notification
         */
        hideToast: function(toast) {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        },

        /**
         * Escape HTML to prevent XSS
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
        },

        /**
         * Handle bookmark button toggle
         */
        handleBookmarkToggle: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const entityId = parseInt($btn.data('entity-id'), 10);
            const collection = $btn.data('collection') || 'favorites';
            const isBookmarked = $btn.hasClass('is-bookmarked');

            // Optimistic UI update
            this.toggleButtonState($btn, !isBookmarked);

            if (typeof sagaCollectionsData !== 'undefined' && sagaCollectionsData.isLoggedIn) {
                // Logged-in user: AJAX request
                this.ajaxToggleBookmark(entityId, collection, isBookmarked, $btn);
            } else {
                // Guest user: localStorage
                this.guestToggleBookmark(entityId, collection, isBookmarked, $btn);
            }
        },

        /**
         * Toggle button state
         */
        toggleButtonState: function($btn, isBookmarked) {
            if (isBookmarked) {
                $btn.addClass('is-bookmarked');
                $btn.attr('aria-label', $btn.data('label-remove') || 'Remove from collection');
                $btn.find('.saga-bookmark-icon').html('&#9829;'); // Filled heart
            } else {
                $btn.removeClass('is-bookmarked');
                $btn.attr('aria-label', $btn.data('label-add') || 'Add to collection');
                $btn.find('.saga-bookmark-icon').html('&#9825;'); // Empty heart
            }
        },

        /**
         * AJAX toggle bookmark (logged-in users)
         */
        ajaxToggleBookmark: function(entityId, collection, wasBookmarked, $btn) {
            const action = wasBookmarked ? 'saga_remove_from_collection' : 'saga_add_to_collection';

            $.ajax({
                url: sagaCollectionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: sagaCollectionsData.nonce,
                    entity_id: entityId,
                    collection: collection
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        $(document).trigger('saga:collection-updated', [collection, response.data.collection]);
                    } else {
                        // Revert UI on error
                        this.toggleButtonState($btn, wasBookmarked);
                        this.showToast(response.data.message || 'An error occurred', 'error');
                    }
                },
                error: (xhr) => {
                    // Revert UI on error
                    this.toggleButtonState($btn, wasBookmarked);

                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.is_guest) {
                        this.showToast('Please log in to use collections', 'info');
                    } else {
                        this.showToast('Network error. Please try again.', 'error');
                    }
                }
            });
        },

        /**
         * Guest toggle bookmark (localStorage)
         */
        guestToggleBookmark: function(entityId, collection, wasBookmarked, $btn) {
            const collections = this.getGuestCollections();

            if (!collections[collection]) {
                collections[collection] = {
                    name: collection.charAt(0).toUpperCase() + collection.slice(1),
                    entity_ids: []
                };
            }

            if (wasBookmarked) {
                // Remove from collection
                const index = collections[collection].entity_ids.indexOf(entityId);
                if (index > -1) {
                    collections[collection].entity_ids.splice(index, 1);
                }
                this.showToast('Removed from ' + collections[collection].name, 'success');
            } else {
                // Add to collection
                if (!collections[collection].entity_ids.includes(entityId)) {
                    collections[collection].entity_ids.push(entityId);
                }
                this.showToast('Added to ' + collections[collection].name, 'success');
            }

            this.saveGuestCollections(collections);
            $(document).trigger('saga:guest-collection-updated', [collection, collections[collection]]);
        },

        /**
         * Get guest collections from localStorage
         */
        getGuestCollections: function() {
            try {
                const data = localStorage.getItem(this.STORAGE_KEY);
                return data ? JSON.parse(data) : { favorites: { name: 'Favorites', entity_ids: [] } };
            } catch (e) {
                console.error('Failed to parse guest collections:', e);
                return { favorites: { name: 'Favorites', entity_ids: [] } };
            }
        },

        /**
         * Save guest collections to localStorage
         */
        saveGuestCollections: function(collections) {
            try {
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(collections));
            } catch (e) {
                console.error('Failed to save guest collections:', e);
                this.showToast('Failed to save bookmark (storage full)', 'error');
            }
        },

        /**
         * Sync guest collections on page load
         */
        syncGuestCollections: function() {
            if (typeof sagaCollectionsData === 'undefined' || !sagaCollectionsData.isLoggedIn) {
                // Update all bookmark buttons based on localStorage
                const collections = this.getGuestCollections();

                $('.saga-bookmark-btn').each(function() {
                    const $btn = $(this);
                    const entityId = parseInt($btn.data('entity-id'), 10);
                    const collection = $btn.data('collection') || 'favorites';

                    if (collections[collection] && collections[collection].entity_ids.includes(entityId)) {
                        $btn.addClass('is-bookmarked');
                        $btn.find('.saga-bookmark-icon').html('&#9829;');
                    }
                });
            }
        },

        /**
         * Handle collection creation
         */
        handleCreateCollection: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $nameInput = $form.find('input[name="collection_name"]');
            const collectionName = $nameInput.val().trim();

            if (!collectionName) {
                this.showToast('Please enter a collection name', 'error');
                return;
            }

            $.ajax({
                url: sagaCollectionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_create_collection',
                    nonce: sagaCollectionsData.nonce,
                    name: collectionName
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        $nameInput.val('');
                        $(document).trigger('saga:collection-created', [response.data.slug, response.data.collection]);

                        // Reload page or update UI
                        if (typeof sagaCollectionsData.reloadOnCreate !== 'undefined' && sagaCollectionsData.reloadOnCreate) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        this.showToast(response.data.message || 'Failed to create collection', 'error');
                    }
                },
                error: () => {
                    this.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Handle collection deletion
         */
        handleDeleteCollection: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const collection = $btn.data('collection');

            if (!confirm('Are you sure you want to delete this collection?')) {
                return;
            }

            $.ajax({
                url: sagaCollectionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_delete_collection',
                    nonce: sagaCollectionsData.nonce,
                    collection: collection
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        $(document).trigger('saga:collection-deleted', [collection]);

                        // Remove collection element
                        $btn.closest('.saga-collection-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        this.showToast(response.data.message || 'Failed to delete collection', 'error');
                    }
                },
                error: () => {
                    this.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Handle collection rename
         */
        handleRenameCollection: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const collection = $form.data('collection');
            const newName = $form.find('input[name="new_name"]').val().trim();

            if (!newName) {
                this.showToast('Please enter a new name', 'error');
                return;
            }

            $.ajax({
                url: sagaCollectionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_rename_collection',
                    nonce: sagaCollectionsData.nonce,
                    collection: collection,
                    new_name: newName
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        $(document).trigger('saga:collection-renamed', [collection, response.data.new_slug, response.data.collection]);

                        // Update UI
                        $form.closest('.saga-collection-item').find('.saga-collection-name').text(newName);
                        $form.data('collection', response.data.new_slug);
                    } else {
                        this.showToast(response.data.message || 'Failed to rename collection', 'error');
                    }
                },
                error: () => {
                    this.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Handle collection export
         */
        handleExportCollection: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const collection = $btn.data('collection');

            // For guest users, export from localStorage
            if (typeof sagaCollectionsData === 'undefined' || !sagaCollectionsData.isLoggedIn) {
                this.exportGuestCollection(collection);
                return;
            }

            // For logged-in users, fetch from server
            $.ajax({
                url: sagaCollectionsData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'saga_export_collection',
                    nonce: sagaCollectionsData.nonce,
                    collection: collection
                },
                success: (response) => {
                    if (response.success) {
                        this.downloadJSON(response.data, `saga-collection-${collection}.json`);
                        this.showToast('Collection exported', 'success');
                    } else {
                        this.showToast(response.data.message || 'Failed to export collection', 'error');
                    }
                },
                error: () => {
                    this.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Export guest collection
         */
        exportGuestCollection: function(collectionSlug) {
            const collections = this.getGuestCollections();

            if (!collections[collectionSlug]) {
                this.showToast('Collection not found', 'error');
                return;
            }

            const exportData = {
                collection_name: collections[collectionSlug].name,
                entity_ids: collections[collectionSlug].entity_ids,
                export_date: new Date().toISOString(),
                is_guest_export: true
            };

            this.downloadJSON(exportData, `saga-guest-collection-${collectionSlug}.json`);
            this.showToast('Collection exported', 'success');
        },

        /**
         * Download data as JSON file
         */
        downloadJSON: function(data, filename) {
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        /**
         * Handle collection selector dropdown
         */
        handleCollectionSelect: function(e) {
            e.preventDefault();

            const $item = $(e.currentTarget);
            const collection = $item.data('collection');
            const entityId = parseInt($item.closest('.saga-collection-selector').data('entity-id'), 10);

            // Trigger bookmark toggle for selected collection
            const $btn = $(`.saga-bookmark-btn[data-entity-id="${entityId}"][data-collection="${collection}"]`);

            if ($btn.length) {
                $btn.trigger('click');
            } else {
                // Create temporary button and trigger
                const isBookmarked = $item.hasClass('is-selected');
                this.ajaxToggleBookmark(entityId, collection, isBookmarked, $item);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SagaCollections.init();
    });

    // Expose to global scope for external access
    window.SagaCollections = SagaCollections;

})(jQuery);
