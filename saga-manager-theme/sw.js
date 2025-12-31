/**
 * Service Worker for Saga Manager PWA
 *
 * Caching Strategies:
 * - Network First: API calls, dynamic content
 * - Cache First: Static assets (CSS, JS, images)
 * - Stale While Revalidate: Entity pages, archives
 * - Network Only: Admin pages, forms
 *
 * @version 1.0.0
 */

const CACHE_VERSION = 'saga-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const ENTITIES_CACHE = `${CACHE_VERSION}-entities`;
const API_CACHE = `${CACHE_VERSION}-api`;
const MAX_CACHE_SIZE = 50; // Maximum items per dynamic cache
const MAX_CACHE_AGE = 7 * 24 * 60 * 60 * 1000; // 7 days in milliseconds

// Static assets to precache on install
const PRECACHE_ASSETS = [
    '/wp-content/themes/saga-manager-theme/offline.html',
    '/wp-content/themes/saga-manager-theme/assets/css/main.css',
    '/wp-content/themes/saga-manager-theme/assets/js/main.js',
    '/wp-content/themes/saga-manager-theme/assets/images/logo.svg',
];

// Install event - precache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Precaching static assets');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => self.skipWaiting())
            .catch((error) => {
                console.error('[SW] Precache failed:', error);
            })
    );
});

// Activate event - cleanup old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((cacheName) => {
                            return cacheName.startsWith('saga-') &&
                                   !cacheName.startsWith(CACHE_VERSION);
                        })
                        .map((cacheName) => {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// Fetch event - handle requests with appropriate strategy
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip admin pages
    if (url.pathname.includes('/wp-admin/')) {
        return;
    }

    // Choose strategy based on request type
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isEntityPage(url)) {
        event.respondWith(staleWhileRevalidate(request, ENTITIES_CACHE));
    } else if (isApiRequest(url)) {
        event.respondWith(networkFirst(request, API_CACHE));
    } else if (isNavigationRequest(request)) {
        event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE));
    } else {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    }
});

// Background Sync - sync queued operations
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);

    if (event.tag === 'sync-annotations') {
        event.waitUntil(syncAnnotations());
    } else if (event.tag === 'sync-bookmarks') {
        event.waitUntil(syncBookmarks());
    } else if (event.tag === 'sync-all') {
        event.waitUntil(Promise.all([
            syncAnnotations(),
            syncBookmarks()
        ]));
    }
});

// Push notification
self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();
    const options = {
        body: data.body || 'New update available',
        icon: '/wp-content/themes/saga-manager-theme/assets/images/icon-192.png',
        badge: '/wp-content/themes/saga-manager-theme/assets/images/badge-72.png',
        data: data.url || '/',
        tag: data.tag || 'saga-notification',
        requireInteraction: false,
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Saga Manager', options)
    );
});

// Notification click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        clients.openWindow(event.notification.data || '/')
    );
});

// Message handling
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(clearAllCaches());
    } else if (event.data && event.data.type === 'CACHE_URLS') {
        event.waitUntil(cacheUrls(event.data.urls));
    }
});

/**
 * Caching Strategies
 */

// Cache First - for static assets
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);

    if (cached) {
        console.log('[SW] Cache hit:', request.url);
        return cached;
    }

    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(cacheName);
            await cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.error('[SW] Cache First failed:', error);
        return new Response('Offline', { status: 503 });
    }
}

// Network First - for API calls
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(cacheName);
            await cache.put(request, response.clone());
            await limitCacheSize(cacheName, MAX_CACHE_SIZE);
        }

        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);

        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        // Return offline page for navigation requests
        if (isNavigationRequest(request)) {
            return caches.match('/wp-content/themes/saga-manager-theme/offline.html');
        }

        return new Response(JSON.stringify({
            error: 'Offline',
            cached: false
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Stale While Revalidate - for entity pages
async function staleWhileRevalidate(request, cacheName) {
    const cached = await caches.match(request);

    const fetchPromise = fetch(request).then(async (response) => {
        if (response.ok) {
            const cache = await caches.open(cacheName);
            await cache.put(request, response.clone());
            await limitCacheSize(cacheName, MAX_CACHE_SIZE);
        }
        return response;
    }).catch(() => null);

    return cached || fetchPromise || caches.match('/wp-content/themes/saga-manager-theme/offline.html');
}

/**
 * Helper Functions
 */

function isStaticAsset(url) {
    return /\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico)$/.test(url.pathname);
}

function isEntityPage(url) {
    return url.pathname.includes('/saga_entity/') ||
           url.pathname.includes('/entity/') ||
           url.searchParams.has('entity_id');
}

function isApiRequest(url) {
    return url.pathname.includes('/wp-json/') ||
           url.pathname.includes('/wp-admin/admin-ajax.php');
}

function isNavigationRequest(request) {
    return request.mode === 'navigate' ||
           (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
}

async function limitCacheSize(cacheName, maxSize) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();

    if (keys.length > maxSize) {
        // Delete oldest entries
        const toDelete = keys.slice(0, keys.length - maxSize);
        await Promise.all(toDelete.map(key => cache.delete(key)));
    }
}

async function clearOldCacheEntries() {
    const cacheNames = await caches.keys();
    const now = Date.now();

    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const requests = await cache.keys();

        for (const request of requests) {
            const response = await cache.match(request);
            const dateHeader = response.headers.get('date');

            if (dateHeader) {
                const cacheDate = new Date(dateHeader).getTime();

                if (now - cacheDate > MAX_CACHE_AGE) {
                    await cache.delete(request);
                    console.log('[SW] Deleted old cache entry:', request.url);
                }
            }
        }
    }
}

async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(cache => caches.delete(cache)));
    console.log('[SW] All caches cleared');
}

async function cacheUrls(urls) {
    const cache = await caches.open(DYNAMIC_CACHE);

    for (const url of urls) {
        try {
            const response = await fetch(url);
            if (response.ok) {
                await cache.put(url, response);
            }
        } catch (error) {
            console.error('[SW] Failed to cache URL:', url, error);
        }
    }
}

/**
 * Background Sync Functions
 */

async function syncAnnotations() {
    try {
        const db = await openIndexedDB();
        const annotations = await getQueuedItems(db, 'annotations');

        for (const annotation of annotations) {
            try {
                const response = await fetch('/wp-json/saga/v1/annotations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(annotation.data)
                });

                if (response.ok) {
                    await removeQueuedItem(db, 'annotations', annotation.id);
                    console.log('[SW] Synced annotation:', annotation.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync annotation:', error);
            }
        }

        return true;
    } catch (error) {
        console.error('[SW] Sync annotations failed:', error);
        throw error;
    }
}

async function syncBookmarks() {
    try {
        const db = await openIndexedDB();
        const bookmarks = await getQueuedItems(db, 'bookmarks');

        for (const bookmark of bookmarks) {
            try {
                const response = await fetch('/wp-json/saga/v1/bookmarks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(bookmark.data)
                });

                if (response.ok) {
                    await removeQueuedItem(db, 'bookmarks', bookmark.id);
                    console.log('[SW] Synced bookmark:', bookmark.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync bookmark:', error);
            }
        }

        return true;
    } catch (error) {
        console.error('[SW] Sync bookmarks failed:', error);
        throw error;
    }
}

/**
 * IndexedDB Helpers
 */

function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('SagaOfflineDB', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains('annotations')) {
                db.createObjectStore('annotations', { keyPath: 'id', autoIncrement: true });
            }

            if (!db.objectStoreNames.contains('bookmarks')) {
                db.createObjectStore('bookmarks', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

function getQueuedItems(db, storeName) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, 'readonly');
        const store = transaction.objectStore(storeName);
        const request = store.getAll();

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function removeQueuedItem(db, storeName, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, 'readwrite');
        const store = transaction.objectStore(storeName);
        const request = store.delete(id);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// Periodic cleanup
setInterval(() => {
    clearOldCacheEntries();
}, 24 * 60 * 60 * 1000); // Daily cleanup
