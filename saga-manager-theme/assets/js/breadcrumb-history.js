/**
 * Breadcrumb History Tracking
 *
 * Manages session-based navigation history for breadcrumb "Back" button.
 * Uses sessionStorage for per-tab history (max 5 pages).
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Configuration
     */
    const CONFIG = {
        storageKey: 'saga_breadcrumb_history',
        maxHistoryLength: 5,
        backButtonId: 'saga-breadcrumb-back',
        separatorClass: 'saga-breadcrumbs__separator--back'
    };

    /**
     * BreadcrumbHistory class
     */
    class BreadcrumbHistory {
        constructor() {
            this.history = this.loadHistory();
            this.currentUrl = this.getCurrentUrl();
            this.backButton = null;
            this.separator = null;
        }

        /**
         * Initialize breadcrumb history
         */
        init() {
            // Get DOM elements
            this.backButton = document.getElementById(CONFIG.backButtonId);
            this.separator = document.querySelector(`.${CONFIG.separatorClass}`);

            if (!this.backButton) {
                return;
            }

            // Update history
            this.updateHistory();

            // Setup back button
            this.setupBackButton();

            // Track navigation
            this.trackNavigation();
        }

        /**
         * Get current URL without hash
         *
         * @returns {string}
         */
        getCurrentUrl() {
            return window.location.href.split('#')[0];
        }

        /**
         * Load history from sessionStorage
         *
         * @returns {Array<{url: string, title: string, timestamp: number}>}
         */
        loadHistory() {
            try {
                const stored = sessionStorage.getItem(CONFIG.storageKey);
                if (!stored) {
                    return [];
                }

                const history = JSON.parse(stored);

                // Validate history structure
                if (!Array.isArray(history)) {
                    return [];
                }

                return history.filter(item =>
                    item &&
                    typeof item.url === 'string' &&
                    typeof item.title === 'string' &&
                    typeof item.timestamp === 'number'
                );

            } catch (error) {
                console.error('[Breadcrumbs] Failed to load history:', error);
                return [];
            }
        }

        /**
         * Save history to sessionStorage
         */
        saveHistory() {
            try {
                sessionStorage.setItem(
                    CONFIG.storageKey,
                    JSON.stringify(this.history)
                );
            } catch (error) {
                console.error('[Breadcrumbs] Failed to save history:', error);
            }
        }

        /**
         * Update history with current page
         */
        updateHistory() {
            // Remove current URL if already in history
            this.history = this.history.filter(item => item.url !== this.currentUrl);

            // Don't add if coming from the same page (refresh)
            const lastEntry = this.history[this.history.length - 1];
            if (lastEntry && lastEntry.url === this.currentUrl) {
                return;
            }

            // Add current page to history
            this.history.push({
                url: this.currentUrl,
                title: document.title,
                timestamp: Date.now()
            });

            // Trim to max length
            if (this.history.length > CONFIG.maxHistoryLength) {
                this.history = this.history.slice(-CONFIG.maxHistoryLength);
            }

            this.saveHistory();
        }

        /**
         * Get previous page from history
         *
         * @returns {Object|null}
         */
        getPreviousPage() {
            // History should have at least 2 items (previous + current)
            if (this.history.length < 2) {
                return null;
            }

            // Get second to last item (last is current page)
            return this.history[this.history.length - 2];
        }

        /**
         * Setup back button
         */
        setupBackButton() {
            const previousPage = this.getPreviousPage();

            if (!previousPage) {
                // Hide back button and separator
                this.backButton.style.display = 'none';
                if (this.separator) {
                    this.separator.style.display = 'none';
                }
                return;
            }

            // Show back button and separator
            this.backButton.style.display = '';
            if (this.separator) {
                this.separator.style.display = '';
            }

            // Update aria-label with previous page title
            const ariaLabel = `Go back to ${this.sanitizeText(previousPage.title)}`;
            this.backButton.setAttribute('aria-label', ariaLabel);

            // Add click handler
            this.backButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.navigateBack();
            });

            // Add keyboard support
            this.backButton.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.navigateBack();
                }
            });
        }

        /**
         * Navigate back to previous page
         */
        navigateBack() {
            const previousPage = this.getPreviousPage();

            if (!previousPage) {
                return;
            }

            // Remove current page from history before navigating
            this.history.pop();
            this.saveHistory();

            // Navigate
            window.location.href = previousPage.url;
        }

        /**
         * Track navigation for external links
         */
        trackNavigation() {
            // Track clicks on breadcrumb links
            const breadcrumbLinks = document.querySelectorAll('.saga-breadcrumbs__link');

            breadcrumbLinks.forEach(link => {
                link.addEventListener('click', () => {
                    // Let the normal history update handle this
                    // History will be updated on next page load
                });
            });

            // Track beforeunload to ensure history is saved
            window.addEventListener('beforeunload', () => {
                this.saveHistory();
            });
        }

        /**
         * Sanitize text for aria-label
         *
         * @param {string} text
         * @returns {string}
         */
        sanitizeText(text) {
            return text
                .replace(/<[^>]*>/g, '') // Remove HTML tags
                .replace(/\s+/g, ' ')     // Normalize whitespace
                .trim()
                .substring(0, 100);       // Limit length
        }

        /**
         * Clear history (for testing/debugging)
         */
        clearHistory() {
            this.history = [];
            this.saveHistory();
            console.log('[Breadcrumbs] History cleared');
        }

        /**
         * Get history (for testing/debugging)
         *
         * @returns {Array}
         */
        getHistory() {
            return this.history;
        }
    }

    /**
     * Initialize when DOM is ready
     */
    function initBreadcrumbHistory() {
        const breadcrumbNav = document.querySelector('.saga-breadcrumbs');

        if (!breadcrumbNav) {
            return;
        }

        const history = new BreadcrumbHistory();
        history.init();

        // Expose to window for debugging (in development only)
        if (window.sagaDebug || localStorage.getItem('saga_debug') === 'true') {
            window.sagaBreadcrumbHistory = history;
            console.log('[Breadcrumbs] History tracking initialized (debug mode)');
        }
    }

    /**
     * DOM Ready handler
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBreadcrumbHistory);
    } else {
        initBreadcrumbHistory();
    }

    /**
     * Re-initialize on Turbo/PJAX navigation (if used)
     */
    document.addEventListener('turbo:load', initBreadcrumbHistory);
    document.addEventListener('pjax:end', initBreadcrumbHistory);

})();
