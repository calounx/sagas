/**
 * PWA Install Prompt Handler
 *
 * Handles "Add to Home Screen" prompt with custom UI
 * - Shows banner after 2 page views
 * - Dismissible with "Don't show again" option
 * - Tracks install events
 *
 * @version 1.0.0
 */

(function() {
    'use strict';

    const PWAInstall = {
        deferredPrompt: null,
        installButton: null,
        installBanner: null,
        minPageViews: 2,
        storageKeys: {
            pageViews: 'saga_pwa_page_views',
            dismissed: 'saga_pwa_dismissed',
            installed: 'saga_pwa_installed'
        },

        init() {
            this.checkInstallStatus();
            this.trackPageView();
            this.setupEventListeners();
            this.createInstallUI();
            this.registerServiceWorker();
        },

        /**
         * Check if PWA is already installed
         */
        checkInstallStatus() {
            // Check if running as PWA
            if (window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone === true) {
                localStorage.setItem(this.storageKeys.installed, 'true');
                console.log('[PWA] App is installed');
                return true;
            }

            return localStorage.getItem(this.storageKeys.installed) === 'true';
        },

        /**
         * Track page views for install prompt
         */
        trackPageView() {
            if (this.checkInstallStatus()) {
                return;
            }

            const pageViews = parseInt(localStorage.getItem(this.storageKeys.pageViews) || '0', 10);
            const newPageViews = pageViews + 1;

            localStorage.setItem(this.storageKeys.pageViews, newPageViews.toString());

            console.log('[PWA] Page views:', newPageViews);

            // Show prompt after minimum page views
            if (newPageViews >= this.minPageViews && !this.isDismissed()) {
                this.schedulePrompt();
            }
        },

        /**
         * Check if user dismissed the prompt
         */
        isDismissed() {
            const dismissed = localStorage.getItem(this.storageKeys.dismissed);
            if (!dismissed) return false;

            const dismissedDate = new Date(dismissed);
            const now = new Date();
            const daysSinceDismissed = (now - dismissedDate) / (1000 * 60 * 60 * 24);

            // Show again after 30 days
            return daysSinceDismissed < 30;
        },

        /**
         * Schedule prompt to show after a delay
         */
        schedulePrompt() {
            setTimeout(() => {
                this.showInstallBanner();
            }, 3000); // Show after 3 seconds
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Capture install prompt event
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                console.log('[PWA] Install prompt available');

                // Update UI to show install button
                this.updateInstallButton(true);
            });

            // Track successful install
            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App installed successfully');
                localStorage.setItem(this.storageKeys.installed, 'true');
                this.hideInstallBanner();
                this.updateInstallButton(false);
                this.trackInstallEvent('installed');
                this.showSuccessMessage();
            });

            // Listen for online/offline events
            window.addEventListener('online', () => {
                this.updateOnlineStatus(true);
            });

            window.addEventListener('offline', () => {
                this.updateOnlineStatus(false);
            });
        },

        /**
         * Register service worker
         */
        registerServiceWorker() {
            if (!('serviceWorker' in navigator)) {
                console.warn('[PWA] Service workers not supported');
                return;
            }

            navigator.serviceWorker.register('/wp-content/themes/saga-manager-theme/sw.js', {
                scope: '/'
            })
            .then((registration) => {
                console.log('[PWA] Service worker registered:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });

                // Check for updates periodically
                setInterval(() => {
                    registration.update();
                }, 60 * 60 * 1000); // Check every hour
            })
            .catch((error) => {
                console.error('[PWA] Service worker registration failed:', error);
            });

            // Handle service worker messages
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'CACHE_UPDATED') {
                    console.log('[PWA] Cache updated:', event.data.url);
                }
            });
        },

        /**
         * Create install UI elements
         */
        createInstallUI() {
            // Create install banner
            this.installBanner = document.createElement('div');
            this.installBanner.className = 'pwa-install-banner';
            this.installBanner.innerHTML = `
                <div class="pwa-install-content">
                    <div class="pwa-install-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </div>
                    <div class="pwa-install-text">
                        <h3>Install Saga Manager</h3>
                        <p>Access your sagas offline and get a better experience</p>
                    </div>
                    <div class="pwa-install-actions">
                        <button class="pwa-install-btn" data-action="install">
                            Install
                        </button>
                        <button class="pwa-dismiss-btn" data-action="dismiss">
                            Not Now
                        </button>
                        <button class="pwa-dismiss-forever-btn" data-action="dismiss-forever">
                            Don't Show Again
                        </button>
                    </div>
                    <button class="pwa-close-btn" data-action="close" aria-label="Close">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            `;

            // Add event listeners to banner buttons
            this.installBanner.querySelectorAll('[data-action]').forEach(button => {
                button.addEventListener('click', (e) => {
                    const action = e.currentTarget.dataset.action;
                    this.handleBannerAction(action);
                });
            });

            document.body.appendChild(this.installBanner);

            // Create install button in header (if header exists)
            const header = document.querySelector('.site-header') || document.querySelector('header');
            if (header) {
                this.installButton = document.createElement('button');
                this.installButton.className = 'pwa-install-header-btn';
                this.installButton.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Install App</span>
                `;
                this.installButton.style.display = 'none';
                this.installButton.addEventListener('click', () => this.promptInstall());

                header.appendChild(this.installButton);
            }
        },

        /**
         * Handle banner actions
         */
        handleBannerAction(action) {
            switch (action) {
                case 'install':
                    this.promptInstall();
                    break;
                case 'dismiss':
                    this.hideInstallBanner();
                    break;
                case 'dismiss-forever':
                    this.dismissForever();
                    break;
                case 'close':
                    this.hideInstallBanner();
                    break;
            }
        },

        /**
         * Show install banner
         */
        showInstallBanner() {
            if (!this.deferredPrompt || this.checkInstallStatus()) {
                return;
            }

            this.installBanner.classList.add('visible');
            this.trackInstallEvent('banner_shown');
        },

        /**
         * Hide install banner
         */
        hideInstallBanner() {
            this.installBanner.classList.remove('visible');
        },

        /**
         * Dismiss banner permanently
         */
        dismissForever() {
            localStorage.setItem(this.storageKeys.dismissed, new Date().toISOString());
            this.hideInstallBanner();
            this.trackInstallEvent('dismissed_forever');
        },

        /**
         * Prompt user to install
         */
        async promptInstall() {
            if (!this.deferredPrompt) {
                console.warn('[PWA] Install prompt not available');
                return;
            }

            this.deferredPrompt.prompt();

            const { outcome } = await this.deferredPrompt.userChoice;

            console.log('[PWA] User choice:', outcome);
            this.trackInstallEvent(outcome);

            if (outcome === 'accepted') {
                this.hideInstallBanner();
            }

            this.deferredPrompt = null;
        },

        /**
         * Update install button visibility
         */
        updateInstallButton(show) {
            if (!this.installButton) return;

            this.installButton.style.display = show && !this.checkInstallStatus() ? 'flex' : 'none';
        },

        /**
         * Show update notification
         */
        showUpdateNotification() {
            const notification = document.createElement('div');
            notification.className = 'pwa-update-notification';
            notification.innerHTML = `
                <div class="pwa-update-content">
                    <p>A new version is available!</p>
                    <button class="pwa-update-btn">Update Now</button>
                    <button class="pwa-update-dismiss">Later</button>
                </div>
            `;

            notification.querySelector('.pwa-update-btn').addEventListener('click', () => {
                if (navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
                }
                window.location.reload();
            });

            notification.querySelector('.pwa-update-dismiss').addEventListener('click', () => {
                notification.remove();
            });

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('visible');
            }, 100);
        },

        /**
         * Show success message after install
         */
        showSuccessMessage() {
            const message = document.createElement('div');
            message.className = 'pwa-success-message';
            message.innerHTML = `
                <div class="pwa-success-content">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <p>Saga Manager installed successfully!</p>
                </div>
            `;

            document.body.appendChild(message);

            setTimeout(() => {
                message.classList.add('visible');
            }, 100);

            setTimeout(() => {
                message.classList.remove('visible');
                setTimeout(() => message.remove(), 300);
            }, 5000);
        },

        /**
         * Update online/offline status
         */
        updateOnlineStatus(isOnline) {
            console.log('[PWA] Online status:', isOnline);

            const event = new CustomEvent('saga:online-status', {
                detail: { online: isOnline }
            });
            window.dispatchEvent(event);
        },

        /**
         * Track install events
         */
        trackInstallEvent(action) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_install', {
                    event_category: 'PWA',
                    event_label: action
                });
            }

            console.log('[PWA] Install event:', action);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PWAInstall.init());
    } else {
        PWAInstall.init();
    }

    // Expose to window for manual triggering
    window.SagaPWAInstall = PWAInstall;

})();
