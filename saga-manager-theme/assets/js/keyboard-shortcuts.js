/**
 * Keyboard Shortcuts Handler
 *
 * Manages keyboard shortcuts and command sequences with visual feedback
 *
 * @package SagaManagerTheme
 */

(function() {
    'use strict';

    class KeyboardShortcuts {
        constructor() {
            this.shortcuts = new Map();
            this.sequences = new Map();
            this.currentSequence = [];
            this.sequenceTimeout = null;
            this.recentCommands = this.loadRecentCommands();
            this.customShortcuts = this.loadCustomShortcuts();
            this.disabled = false;
            this.sequenceIndicator = null;

            this.init();
        }

        /**
         * Initialize keyboard shortcuts
         */
        init() {
            this.registerCommands();
            this.createSequenceIndicator();
            this.attachEventListeners();

            // Expose to window for command palette
            window.sagaShortcuts = this;
        }

        /**
         * Register all commands from the registry
         */
        registerCommands() {
            if (typeof sagaCommands === 'undefined' || !sagaCommands.commands) {
                console.warn('Saga commands not loaded');
                return;
            }

            sagaCommands.commands.forEach(command => {
                const keys = this.normalizeKeys(command.keys);

                if (command.sequence) {
                    this.sequences.set(keys, command);
                } else {
                    this.shortcuts.set(keys, command);
                }
            });
        }

        /**
         * Normalize key notation
         */
        normalizeKeys(keys) {
            // Replace Ctrl with Cmd on Mac
            if (sagaCommands.isMac) {
                keys = keys.replace(/Ctrl/gi, 'Cmd');
            } else {
                keys = keys.replace(/Cmd/gi, 'Ctrl');
            }

            return keys.toLowerCase();
        }

        /**
         * Create visual indicator for key sequences
         */
        createSequenceIndicator() {
            this.sequenceIndicator = document.createElement('div');
            this.sequenceIndicator.className = 'saga-sequence-indicator';
            this.sequenceIndicator.setAttribute('aria-live', 'polite');
            this.sequenceIndicator.hidden = true;
            document.body.appendChild(this.sequenceIndicator);
        }

        /**
         * Attach event listeners
         */
        attachEventListeners() {
            document.addEventListener('keydown', this.handleKeyDown.bind(this));

            // Disable shortcuts in certain contexts
            document.addEventListener('focusin', (e) => {
                if (this.isTypingContext(e.target)) {
                    this.disabled = true;
                }
            });

            document.addEventListener('focusout', (e) => {
                if (this.isTypingContext(e.target)) {
                    this.disabled = false;
                }
            });
        }

        /**
         * Handle keydown events
         */
        handleKeyDown(e) {
            // Skip if disabled or in typing context
            if (this.disabled || this.isTypingContext(e.target)) {
                return;
            }

            // Skip if modifier keys only
            if (this.isModifierOnly(e)) {
                return;
            }

            // Get key string representation
            const keyString = this.getKeyString(e);

            // Try single-key shortcut first
            if (this.handleSingleKeyShortcut(keyString, e)) {
                return;
            }

            // Try sequence shortcut
            this.handleSequenceShortcut(e.key, e);
        }

        /**
         * Handle single-key shortcuts
         */
        handleSingleKeyShortcut(keyString, event) {
            const command = this.shortcuts.get(keyString);

            if (command && this.canExecuteCommand(command)) {
                event.preventDefault();
                this.executeCommand(command);
                this.addToRecentCommands(command);
                return true;
            }

            return false;
        }

        /**
         * Handle sequence shortcuts (e.g., G then H)
         */
        handleSequenceShortcut(key, event) {
            clearTimeout(this.sequenceTimeout);

            this.currentSequence.push(key.toLowerCase());
            const sequenceString = this.currentSequence.join(' ');

            // Show sequence indicator
            this.showSequenceIndicator(sequenceString);

            // Check if sequence matches any command
            const command = this.sequences.get(sequenceString);

            if (command && this.canExecuteCommand(command)) {
                event.preventDefault();
                this.executeCommand(command);
                this.addToRecentCommands(command);
                this.resetSequence();
                return;
            }

            // Check if sequence is a prefix of any command
            const hasMatchingPrefix = Array.from(this.sequences.keys()).some(
                seq => seq.startsWith(sequenceString)
            );

            if (hasMatchingPrefix) {
                // Continue sequence
                this.sequenceTimeout = setTimeout(() => {
                    this.resetSequence();
                }, 1000);
            } else {
                // No match, reset
                this.resetSequence();
            }
        }

        /**
         * Get string representation of key combination
         */
        getKeyString(e) {
            const parts = [];

            if (e.ctrlKey || e.metaKey) {
                parts.push(sagaCommands.isMac ? 'cmd' : 'ctrl');
            }
            if (e.altKey) parts.push('alt');
            if (e.shiftKey) parts.push('shift');

            // Add the actual key
            let key = e.key.toLowerCase();

            // Special key mappings
            const keyMap = {
                'escape': 'esc',
                '=': '=',
                '-': '-',
                '/': '/'
            };

            key = keyMap[key] || key;

            // Don't add modifier keys as the final key
            if (!['control', 'alt', 'shift', 'meta'].includes(key)) {
                parts.push(key);
            }

            return parts.join('+');
        }

        /**
         * Check if key event is modifier-only
         */
        isModifierOnly(e) {
            return ['Control', 'Alt', 'Shift', 'Meta'].includes(e.key);
        }

        /**
         * Check if target is a typing context
         */
        isTypingContext(target) {
            if (!target) return false;

            const tagName = target.tagName.toLowerCase();
            const isContentEditable = target.isContentEditable;
            const isInput = ['input', 'textarea', 'select'].includes(tagName);

            return isContentEditable || isInput;
        }

        /**
         * Check if command can be executed
         */
        canExecuteCommand(command) {
            // Check authentication
            if (command.requiresAuth && !sagaCommands.isLoggedIn) {
                return false;
            }

            // Check if entity page is required
            if (command.requiresEntity && !document.body.classList.contains('single-saga_entity')) {
                return false;
            }

            // Check if search page is required
            if (command.requiresSearch && !this.isSearchContext()) {
                return false;
            }

            return true;
        }

        /**
         * Check if current context is search
         */
        isSearchContext() {
            return document.body.classList.contains('page-template-search') ||
                   document.querySelector('.saga-search-results') !== null;
        }

        /**
         * Execute a command
         */
        executeCommand(command) {
            const action = command.action;

            // URL navigation
            if (typeof action === 'string' && action.startsWith('http')) {
                window.location.href = action;
                return;
            }

            // Filter actions
            if (action.startsWith('filterByType:')) {
                const type = action.split(':')[1];
                this.filterByType(type);
                return;
            }

            // Built-in actions
            const actions = {
                'toggleCommandPalette': () => window.sagaPalette?.toggle(),
                'closeModals': () => this.closeAllModals(),
                'showShortcutsHelp': () => this.showShortcutsHelp(),
                'focusSearch': () => this.focusSearchBar(),
                'toggleDarkMode': () => this.toggleDarkMode(),
                'toggleReadingMode': () => this.toggleReadingMode(),
                'toggleBookmark': () => this.toggleBookmark(),
                'openAnnotationModal': () => this.openAnnotationModal(),
                'shareEntity': () => this.shareEntity(),
                'increaseFontSize': () => this.adjustFontSize(1),
                'decreaseFontSize': () => this.adjustFontSize(-1),
                'clearFilters': () => this.clearFilters(),
            };

            const actionFn = actions[action];
            if (actionFn) {
                actionFn();
            } else {
                console.warn('Unknown action:', action);
            }
        }

        /**
         * Filter search results by entity type
         */
        filterByType(type) {
            const event = new CustomEvent('saga:filterByType', { detail: { type } });
            document.dispatchEvent(event);

            // Show feedback
            this.showNotification(`Filtering by ${type}`);
        }

        /**
         * Clear all search filters
         */
        clearFilters() {
            const event = new CustomEvent('saga:clearFilters');
            document.dispatchEvent(event);

            this.showNotification('Filters cleared');
        }

        /**
         * Close all open modals
         */
        closeAllModals() {
            // Close command palette
            if (window.sagaPalette) {
                window.sagaPalette.close();
            }

            // Close shortcuts help
            const helpOverlay = document.querySelector('.saga-shortcuts-help');
            if (helpOverlay) {
                helpOverlay.hidden = true;
            }

            // Close any other modals
            document.querySelectorAll('[role="dialog"][aria-modal="true"]').forEach(modal => {
                modal.hidden = true;
            });
        }

        /**
         * Show shortcuts help overlay
         */
        showShortcutsHelp() {
            const helpOverlay = document.querySelector('.saga-shortcuts-help');
            if (helpOverlay) {
                helpOverlay.hidden = false;
                helpOverlay.querySelector('.shortcuts-close')?.focus();
            }
        }

        /**
         * Focus search bar
         */
        focusSearchBar() {
            const searchInput = document.querySelector('.saga-search-input, #saga-search, input[type="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }

        /**
         * Toggle dark mode
         */
        toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');

            localStorage.setItem('sagaDarkMode', isDark ? 'true' : 'false');
            this.showNotification(isDark ? 'Dark mode enabled' : 'Light mode enabled');
        }

        /**
         * Toggle reading mode
         */
        toggleReadingMode() {
            document.body.classList.toggle('reading-mode');
            const isReading = document.body.classList.contains('reading-mode');

            this.showNotification(isReading ? 'Reading mode enabled' : 'Reading mode disabled');
        }

        /**
         * Toggle bookmark for current entity
         */
        toggleBookmark() {
            const bookmarkBtn = document.querySelector('[data-action="bookmark"]');
            if (bookmarkBtn) {
                bookmarkBtn.click();
            } else {
                this.showNotification('Bookmark not available on this page', 'warning');
            }
        }

        /**
         * Open annotation modal
         */
        openAnnotationModal() {
            const event = new CustomEvent('saga:openAnnotation');
            document.dispatchEvent(event);
        }

        /**
         * Share current entity
         */
        shareEntity() {
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    url: window.location.href
                }).catch(() => {
                    // Fallback to copying URL
                    this.copyToClipboard(window.location.href);
                });
            } else {
                this.copyToClipboard(window.location.href);
            }
        }

        /**
         * Adjust font size
         */
        adjustFontSize(delta) {
            const root = document.documentElement;
            const currentSize = parseFloat(getComputedStyle(root).fontSize);
            const newSize = Math.max(12, Math.min(24, currentSize + delta));

            root.style.fontSize = newSize + 'px';
            this.showNotification(`Font size: ${newSize}px`);
        }

        /**
         * Copy text to clipboard
         */
        copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                this.showNotification('Link copied to clipboard');
            }).catch(() => {
                this.showNotification('Failed to copy link', 'error');
            });
        }

        /**
         * Show sequence indicator
         */
        showSequenceIndicator(sequence) {
            this.sequenceIndicator.textContent = sequence.toUpperCase();
            this.sequenceIndicator.hidden = false;
        }

        /**
         * Reset sequence
         */
        resetSequence() {
            this.currentSequence = [];
            this.sequenceIndicator.hidden = true;
            clearTimeout(this.sequenceTimeout);
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'info') {
            const event = new CustomEvent('saga:notify', {
                detail: { message, type }
            });
            document.dispatchEvent(event);
        }

        /**
         * Add command to recent history
         */
        addToRecentCommands(command) {
            this.recentCommands = this.recentCommands.filter(c => c.id !== command.id);
            this.recentCommands.unshift(command);
            this.recentCommands = this.recentCommands.slice(0, 5);

            this.saveRecentCommands();
        }

        /**
         * Load recent commands from localStorage
         */
        loadRecentCommands() {
            try {
                const stored = localStorage.getItem('sagaRecentCommands');
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        }

        /**
         * Save recent commands to localStorage
         */
        saveRecentCommands() {
            try {
                localStorage.setItem('sagaRecentCommands', JSON.stringify(this.recentCommands));
            } catch (e) {
                console.warn('Failed to save recent commands');
            }
        }

        /**
         * Load custom shortcuts from localStorage
         */
        loadCustomShortcuts() {
            try {
                const stored = localStorage.getItem('sagaCustomShortcuts');
                return stored ? JSON.parse(stored) : {};
            } catch (e) {
                return {};
            }
        }

        /**
         * Get recent commands
         */
        getRecentCommands() {
            return this.recentCommands;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new KeyboardShortcuts();
        });
    } else {
        new KeyboardShortcuts();
    }
})();
