/**
 * Dark Mode Toggle
 *
 * Pure JavaScript implementation with:
 * - localStorage persistence
 * - System preference detection
 * - Smooth transitions
 * - Accessible keyboard navigation
 * - Debounced localStorage writes
 *
 * @package SagaTheme
 * @version 1.0.0
 */

(function () {
    'use strict';

    /**
     * Dark Mode Manager Class
     *
     * Handles all dark mode functionality including:
     * - Theme initialization
     * - Toggle state management
     * - localStorage persistence
     * - System preference detection
     */
    class DarkModeManager {
        /**
         * Constructor
         */
        constructor() {
            this.storageKey = 'saga-theme-preference';
            this.dataAttribute = 'data-theme';
            this.darkTheme = 'dark';
            this.lightTheme = 'light';
            this.debounceTimer = null;
            this.debounceDelay = 300;

            // Cache DOM elements
            this.htmlElement = document.documentElement;
            this.toggleButtons = [];

            // Bind methods
            this.toggle = this.toggle.bind(this);
            this.handleToggleClick = this.handleToggleClick.bind(this);
            this.handleKeyPress = this.handleKeyPress.bind(this);
        }

        /**
         * Initialize dark mode
         *
         * Sets initial theme based on:
         * 1. localStorage preference (highest priority)
         * 2. System preference (prefers-color-scheme)
         * 3. Default light theme (fallback)
         */
        init() {
            // Prevent flash of unstyled content (FOUC)
            this.htmlElement.classList.add('no-transitions');

            // Get saved preference or detect system preference
            const savedTheme = this.getSavedTheme();
            const systemPreference = this.getSystemPreference();
            const initialTheme = savedTheme || systemPreference || this.lightTheme;

            // Apply initial theme
            this.setTheme(initialTheme, false);

            // Remove no-transitions class after a brief delay
            setTimeout(() => {
                this.htmlElement.classList.remove('no-transitions');
            }, 50);

            // Initialize toggle buttons
            this.initToggleButtons();

            // Listen for system preference changes
            this.watchSystemPreference();

            // Expose global API
            window.sagaDarkMode = {
                toggle: () => this.toggle(),
                setTheme: (theme) => this.setTheme(theme, true),
                getTheme: () => this.getCurrentTheme(),
                isDark: () => this.isDarkMode()
            };
        }

        /**
         * Get saved theme from localStorage
         *
         * @returns {string|null} Saved theme or null
         */
        getSavedTheme() {
            try {
                return localStorage.getItem(this.storageKey);
            } catch (error) {
                console.warn('[Saga Dark Mode] localStorage not available:', error);
                return null;
            }
        }

        /**
         * Save theme to localStorage (debounced)
         *
         * Prevents excessive writes when toggling rapidly
         *
         * @param {string} theme Theme to save
         */
        saveTheme(theme) {
            // Clear existing timer
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            // Debounce the save operation
            this.debounceTimer = setTimeout(() => {
                try {
                    localStorage.setItem(this.storageKey, theme);
                } catch (error) {
                    console.warn('[Saga Dark Mode] Failed to save theme:', error);
                }
            }, this.debounceDelay);
        }

        /**
         * Get system color scheme preference
         *
         * @returns {string|null} 'dark' or 'light' or null
         */
        getSystemPreference() {
            if (window.matchMedia) {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    return this.darkTheme;
                }
                if (window.matchMedia('(prefers-color-scheme: light)').matches) {
                    return this.lightTheme;
                }
            }
            return null;
        }

        /**
         * Watch for system preference changes
         *
         * Updates theme if no manual preference is saved
         */
        watchSystemPreference() {
            if (!window.matchMedia) {
                return;
            }

            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

            // Modern browsers
            if (darkModeQuery.addEventListener) {
                darkModeQuery.addEventListener('change', (e) => {
                    // Only auto-update if user hasn't set a manual preference
                    if (!this.getSavedTheme()) {
                        this.setTheme(e.matches ? this.darkTheme : this.lightTheme, false);
                        this.updateToggleButtons();
                    }
                });
            }
            // Legacy browsers
            else if (darkModeQuery.addListener) {
                darkModeQuery.addListener((e) => {
                    if (!this.getSavedTheme()) {
                        this.setTheme(e.matches ? this.darkTheme : this.lightTheme, false);
                        this.updateToggleButtons();
                    }
                });
            }
        }

        /**
         * Get current theme
         *
         * @returns {string} Current theme ('dark' or 'light')
         */
        getCurrentTheme() {
            const currentTheme = this.htmlElement.getAttribute(this.dataAttribute);
            return currentTheme === this.darkTheme ? this.darkTheme : this.lightTheme;
        }

        /**
         * Check if dark mode is active
         *
         * @returns {boolean} True if dark mode is active
         */
        isDarkMode() {
            return this.getCurrentTheme() === this.darkTheme;
        }

        /**
         * Set theme
         *
         * @param {string} theme Theme to set ('dark' or 'light')
         * @param {boolean} persist Whether to save to localStorage
         */
        setTheme(theme, persist = true) {
            const validTheme = theme === this.darkTheme ? this.darkTheme : this.lightTheme;

            // Set data attribute on html element
            if (validTheme === this.darkTheme) {
                this.htmlElement.setAttribute(this.dataAttribute, this.darkTheme);
            } else {
                this.htmlElement.removeAttribute(this.dataAttribute);
            }

            // Save to localStorage if requested
            if (persist) {
                this.saveTheme(validTheme);
            }

            // Update toggle button states
            this.updateToggleButtons();

            // Dispatch custom event for other scripts to listen to
            this.dispatchThemeChangeEvent(validTheme);
        }

        /**
         * Toggle between dark and light themes
         */
        toggle() {
            const newTheme = this.isDarkMode() ? this.lightTheme : this.darkTheme;
            this.setTheme(newTheme, true);
        }

        /**
         * Dispatch custom theme change event
         *
         * @param {string} theme New theme
         */
        dispatchThemeChangeEvent(theme) {
            const event = new CustomEvent('sagaThemeChange', {
                detail: {
                    theme: theme,
                    isDark: theme === this.darkTheme
                }
            });
            document.dispatchEvent(event);
        }

        /**
         * Initialize all toggle buttons
         */
        initToggleButtons() {
            // Find all toggle buttons
            this.toggleButtons = document.querySelectorAll('.saga-dark-mode-toggle');

            // Add event listeners to each button
            this.toggleButtons.forEach(button => {
                // Click event
                button.addEventListener('click', this.handleToggleClick);

                // Keyboard events
                button.addEventListener('keydown', this.handleKeyPress);

                // Set initial ARIA state
                this.updateButtonState(button);
            });
        }

        /**
         * Handle toggle button click
         *
         * @param {Event} event Click event
         */
        handleToggleClick(event) {
            event.preventDefault();
            this.toggle();
        }

        /**
         * Handle keyboard press on toggle button
         *
         * Supports:
         * - Enter key
         * - Space bar
         *
         * @param {KeyboardEvent} event Keyboard event
         */
        handleKeyPress(event) {
            // Handle Enter and Space keys
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.toggle();
            }
        }

        /**
         * Update all toggle button states
         */
        updateToggleButtons() {
            this.toggleButtons.forEach(button => {
                this.updateButtonState(button);
            });
        }

        /**
         * Update individual button state
         *
         * @param {HTMLElement} button Toggle button element
         */
        updateButtonState(button) {
            const isDark = this.isDarkMode();
            const screenReaderText = button.querySelector('.saga-dark-mode-toggle__text');

            // Update ARIA attributes
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('aria-label',
                isDark ? 'Switch to light mode' : 'Switch to dark mode'
            );

            // Update screen reader text
            if (screenReaderText) {
                screenReaderText.textContent =
                    isDark ? 'Switch to light mode' : 'Switch to dark mode';
            }
        }
    }

    /**
     * Initialize on DOM ready
     *
     * Uses multiple methods to ensure initialization:
     * 1. DOMContentLoaded event (standard)
     * 2. Immediate execution if DOM already loaded
     */
    function initDarkMode() {
        const darkModeManager = new DarkModeManager();
        darkModeManager.init();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDarkMode);
    } else {
        // DOM already loaded
        initDarkMode();
    }

    /**
     * Optional: Listen for theme changes and log them (development only)
     *
     * Remove or comment out in production
     */
    if (typeof console !== 'undefined' && console.log) {
        document.addEventListener('sagaThemeChange', function(event) {
            // Only log in development (when WP_DEBUG is true)
            if (window.sagaDebug) {
                console.log('[Saga Dark Mode] Theme changed to:', event.detail.theme);
            }
        });
    }

})();
