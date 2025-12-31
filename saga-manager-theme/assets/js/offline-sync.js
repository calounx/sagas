/**
 * Offline Sync Manager
 *
 * Manages background sync for annotations and bookmarks
 * - Queue writes when offline
 * - Sync when connection restored
 * - Show sync status to user
 * - Handle conflicts
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    const OfflineSync = {
        db: null,
        isOnline: navigator.onLine,
        syncInProgress: false,
        syncQueue: {
            annotations: 0,
            bookmarks: 0
        },

        init() {
            this.openDatabase()
                .then(() => {
                    this.setupEventListeners();
                    this.updateSyncStatus();
                    this.startPeriodicSync();
                    console.log('[Sync] Offline sync initialized');
                })
                .catch(error => {
                    console.error('[Sync] Failed to initialize:', error);
                });
        },

        /**
         * Open IndexedDB
         */
        openDatabase() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open('SagaOfflineDB', 1);

                request.onerror = () => reject(request.error);
                request.onsuccess = () => {
                    this.db = request.result;
                    this.updateQueueCounts();
                    resolve(this.db);
                };

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;

                    // Annotations store
                    if (!db.objectStoreNames.contains('annotations')) {
                        const annotationsStore = db.createObjectStore('annotations', {
                            keyPath: 'id',
                            autoIncrement: true
                        });
                        annotationsStore.createIndex('entity_id', 'entity_id', { unique: false });
                        annotationsStore.createIndex('created_at', 'created_at', { unique: false });
                    }

                    // Bookmarks store
                    if (!db.objectStoreNames.contains('bookmarks')) {
                        const bookmarksStore = db.createObjectStore('bookmarks', {
                            keyPath: 'id',
                            autoIncrement: true
                        });
                        bookmarksStore.createIndex('entity_id', 'entity_id', { unique: false });
                        bookmarksStore.createIndex('created_at', 'created_at', { unique: false });
                    }

                    // Sync metadata store
                    if (!db.objectStoreNames.contains('sync_metadata')) {
                        db.createObjectStore('sync_metadata', { keyPath: 'key' });
                    }
                };
            });
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Online/offline events
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.handleOnline();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
                this.handleOffline();
            });

            // Service Worker sync event
            if ('serviceWorker' in navigator && 'sync' in self.registration) {
                navigator.serviceWorker.ready.then(registration => {
                    // Listen for sync completion
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        if (event.data && event.data.type === 'SYNC_COMPLETE') {
                            this.handleSyncComplete(event.data.tag);
                        }
                    });
                });
            }

            // Custom events for saving data
            window.addEventListener('saga:save-annotation', (event) => {
                this.queueAnnotation(event.detail);
            });

            window.addEventListener('saga:save-bookmark', (event) => {
                this.queueBookmark(event.detail);
            });

            // Visibility change - sync when tab becomes visible
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.isOnline) {
                    this.triggerSync();
                }
            });
        },

        /**
         * Handle online event
         */
        handleOnline() {
            console.log('[Sync] Connection restored');
            this.updateOnlineIndicator(true);
            this.triggerSync();
        },

        /**
         * Handle offline event
         */
        handleOffline() {
            console.log('[Sync] Connection lost');
            this.updateOnlineIndicator(false);
        },

        /**
         * Queue annotation for sync
         */
        async queueAnnotation(data) {
            try {
                const annotation = {
                    data: {
                        entity_id: data.entity_id,
                        content: data.content,
                        position: data.position || null,
                        nonce: data.nonce || window.sagaVars?.nonce
                    },
                    created_at: new Date().toISOString(),
                    synced: false
                };

                const transaction = this.db.transaction(['annotations'], 'readwrite');
                const store = transaction.objectStore('annotations');
                const request = store.add(annotation);

                request.onsuccess = () => {
                    console.log('[Sync] Annotation queued:', request.result);
                    this.updateQueueCounts();
                    this.showQueuedNotification('annotation');

                    if (this.isOnline) {
                        this.triggerSync();
                    }
                };

                request.onerror = () => {
                    console.error('[Sync] Failed to queue annotation:', request.error);
                };
            } catch (error) {
                console.error('[Sync] Error queueing annotation:', error);
            }
        },

        /**
         * Queue bookmark for sync
         */
        async queueBookmark(data) {
            try {
                const bookmark = {
                    data: {
                        entity_id: data.entity_id,
                        title: data.title,
                        note: data.note || '',
                        nonce: data.nonce || window.sagaVars?.nonce
                    },
                    created_at: new Date().toISOString(),
                    synced: false
                };

                const transaction = this.db.transaction(['bookmarks'], 'readwrite');
                const store = transaction.objectStore('bookmarks');
                const request = store.add(bookmark);

                request.onsuccess = () => {
                    console.log('[Sync] Bookmark queued:', request.result);
                    this.updateQueueCounts();
                    this.showQueuedNotification('bookmark');

                    if (this.isOnline) {
                        this.triggerSync();
                    }
                };

                request.onerror = () => {
                    console.error('[Sync] Failed to queue bookmark:', request.error);
                };
            } catch (error) {
                console.error('[Sync] Error queueing bookmark:', error);
            }
        },

        /**
         * Trigger background sync
         */
        async triggerSync() {
            if (!this.isOnline || this.syncInProgress) {
                return;
            }

            console.log('[Sync] Triggering sync...');

            // Try service worker background sync first
            if ('serviceWorker' in navigator && 'sync' in self.registration) {
                try {
                    const registration = await navigator.serviceWorker.ready;
                    await registration.sync.register('sync-all');
                    console.log('[Sync] Background sync registered');
                    return;
                } catch (error) {
                    console.log('[Sync] Background sync not available, using manual sync');
                }
            }

            // Fallback to manual sync
            this.manualSync();
        },

        /**
         * Manual sync (fallback when background sync unavailable)
         */
        async manualSync() {
            if (this.syncInProgress) {
                return;
            }

            this.syncInProgress = true;
            this.updateSyncStatus(true);

            try {
                await Promise.all([
                    this.syncAnnotations(),
                    this.syncBookmarks()
                ]);

                console.log('[Sync] Manual sync completed');
                this.showSyncSuccessNotification();
            } catch (error) {
                console.error('[Sync] Manual sync failed:', error);
                this.showSyncErrorNotification();
            } finally {
                this.syncInProgress = false;
                this.updateSyncStatus(false);
                this.updateQueueCounts();
            }
        },

        /**
         * Sync annotations to server
         */
        async syncAnnotations() {
            const items = await this.getQueuedItems('annotations');

            for (const item of items) {
                try {
                    const response = await fetch('/wp-json/saga/v1/annotations', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': item.data.nonce
                        },
                        body: JSON.stringify(item.data)
                    });

                    if (response.ok) {
                        await this.removeQueuedItem('annotations', item.id);
                        console.log('[Sync] Annotation synced:', item.id);
                    } else {
                        const error = await response.json();
                        console.error('[Sync] Failed to sync annotation:', error);

                        // Remove invalid items
                        if (response.status === 400 || response.status === 401) {
                            await this.removeQueuedItem('annotations', item.id);
                        }
                    }
                } catch (error) {
                    console.error('[Sync] Error syncing annotation:', error);
                    throw error;
                }
            }
        },

        /**
         * Sync bookmarks to server
         */
        async syncBookmarks() {
            const items = await this.getQueuedItems('bookmarks');

            for (const item of items) {
                try {
                    const response = await fetch('/wp-json/saga/v1/bookmarks', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': item.data.nonce
                        },
                        body: JSON.stringify(item.data)
                    });

                    if (response.ok) {
                        await this.removeQueuedItem('bookmarks', item.id);
                        console.log('[Sync] Bookmark synced:', item.id);
                    } else {
                        const error = await response.json();
                        console.error('[Sync] Failed to sync bookmark:', error);

                        // Remove invalid items
                        if (response.status === 400 || response.status === 401) {
                            await this.removeQueuedItem('bookmarks', item.id);
                        }
                    }
                } catch (error) {
                    console.error('[Sync] Error syncing bookmark:', error);
                    throw error;
                }
            }
        },

        /**
         * Get queued items from IndexedDB
         */
        getQueuedItems(storeName) {
            return new Promise((resolve, reject) => {
                const transaction = this.db.transaction([storeName], 'readonly');
                const store = transaction.objectStore(storeName);
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Remove queued item from IndexedDB
         */
        removeQueuedItem(storeName, id) {
            return new Promise((resolve, reject) => {
                const transaction = this.db.transaction([storeName], 'readwrite');
                const store = transaction.objectStore(storeName);
                const request = store.delete(id);

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Update queue counts
         */
        async updateQueueCounts() {
            try {
                const annotations = await this.getQueuedItems('annotations');
                const bookmarks = await this.getQueuedItems('bookmarks');

                this.syncQueue.annotations = annotations.length;
                this.syncQueue.bookmarks = bookmarks.length;

                this.updateSyncBadge();
            } catch (error) {
                console.error('[Sync] Failed to update queue counts:', error);
            }
        },

        /**
         * Update sync badge
         */
        updateSyncBadge() {
            const totalQueued = this.syncQueue.annotations + this.syncQueue.bookmarks;
            const badge = document.querySelector('.sync-queue-badge');

            if (badge) {
                if (totalQueued > 0) {
                    badge.textContent = totalQueued;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }

            // Dispatch event
            const event = new CustomEvent('saga:sync-queue-updated', {
                detail: this.syncQueue
            });
            window.dispatchEvent(event);
        },

        /**
         * Update sync status indicator
         */
        updateSyncStatus(syncing = false) {
            const indicator = document.querySelector('.sync-status-indicator');

            if (indicator) {
                if (syncing) {
                    indicator.classList.add('syncing');
                    indicator.textContent = 'Syncing...';
                } else {
                    indicator.classList.remove('syncing');
                    const lastSync = this.getLastSyncTime();
                    indicator.textContent = lastSync ? `Last synced: ${lastSync}` : 'Ready to sync';
                }
            }
        },

        /**
         * Update online/offline indicator
         */
        updateOnlineIndicator(online) {
            const indicator = document.querySelector('.offline-indicator');

            if (indicator) {
                if (online) {
                    indicator.setAttribute('hidden', '');
                } else {
                    indicator.removeAttribute('hidden');
                }
            }
        },

        /**
         * Get last sync time
         */
        getLastSyncTime() {
            const lastSync = localStorage.getItem('saga_last_sync');
            if (!lastSync) return null;

            const date = new Date(lastSync);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) return 'just now';
            if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
            if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
            return `${Math.floor(diff / 86400000)}d ago`;
        },

        /**
         * Set last sync time
         */
        setLastSyncTime() {
            localStorage.setItem('saga_last_sync', new Date().toISOString());
            this.updateSyncStatus();
        },

        /**
         * Handle sync complete
         */
        handleSyncComplete(tag) {
            console.log('[Sync] Sync complete:', tag);
            this.setLastSyncTime();
            this.updateQueueCounts();
        },

        /**
         * Start periodic sync
         */
        startPeriodicSync() {
            // Sync every 5 minutes if online
            setInterval(() => {
                if (this.isOnline && !this.syncInProgress) {
                    const totalQueued = this.syncQueue.annotations + this.syncQueue.bookmarks;
                    if (totalQueued > 0) {
                        this.triggerSync();
                    }
                }
            }, 5 * 60 * 1000);
        },

        /**
         * Show queued notification
         */
        showQueuedNotification(type) {
            const message = type === 'annotation' ? 'Annotation saved for sync' : 'Bookmark saved for sync';

            this.showNotification(message, 'info');
        },

        /**
         * Show sync success notification
         */
        showSyncSuccessNotification() {
            this.showNotification('All changes synced successfully', 'success');
            this.setLastSyncTime();
        },

        /**
         * Show sync error notification
         */
        showSyncErrorNotification() {
            this.showNotification('Sync failed. Will retry automatically.', 'error');
        },

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `sync-notification sync-notification--${type}`;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('visible');
            }, 100);

            setTimeout(() => {
                notification.classList.remove('visible');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        /**
         * Clear all queued items (for testing)
         */
        async clearQueue() {
            const annotations = await this.getQueuedItems('annotations');
            const bookmarks = await this.getQueuedItems('bookmarks');

            for (const item of annotations) {
                await this.removeQueuedItem('annotations', item.id);
            }

            for (const item of bookmarks) {
                await this.removeQueuedItem('bookmarks', item.id);
            }

            this.updateQueueCounts();
            console.log('[Sync] Queue cleared');
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => OfflineSync.init());
    } else {
        OfflineSync.init();
    }

    // Expose to window for manual triggering
    window.SagaOfflineSync = OfflineSync;

})();
