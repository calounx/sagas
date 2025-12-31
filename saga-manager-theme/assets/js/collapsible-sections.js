/**
 * Collapsible Sections
 *
 * Handles accordion-style collapsible sections with:
 * - Smooth animations
 * - localStorage state persistence
 * - Keyboard navigation
 * - Deep linking via URL hash
 * - Accessibility (ARIA)
 *
 * @package SagaManager
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * CollapsibleSections Class
     */
    class CollapsibleSections {
        constructor() {
            this.config = window.sagaCollapsible || {};
            this.pageId = this.config.pageId || 'default';
            this.storageKey = `saga_sections_page_${this.pageId}`;
            this.sections = new Map();
            this.reducedMotion = this.config.reducedMotion ||
                                 window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            this.init();
        }

        /**
         * Initialize
         */
        init() {
            // Wait for DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }

        /**
         * Setup all sections
         */
        setup() {
            // Find all sections
            const sectionElements = document.querySelectorAll('.saga-collapsible-section');

            if (sectionElements.length === 0) {
                return;
            }

            // Initialize each section
            sectionElements.forEach(element => this.initSection(element));

            // Setup global controls
            this.setupControls();

            // Handle URL hash
            this.handleHashNavigation();

            // Handle hash changes
            window.addEventListener('hashchange', () => this.handleHashNavigation());

            // Mobile default state
            if (this.isMobile() && this.config.mobileCollapsed) {
                this.collapseAllSections(false);
            }

            // Listen for storage changes (multi-tab sync)
            window.addEventListener('storage', (e) => this.handleStorageChange(e));
        }

        /**
         * Initialize individual section
         */
        initSection(element) {
            const sectionId = element.dataset.sectionId;
            if (!sectionId) return;

            const toggle = element.querySelector('.saga-section-toggle');
            const content = element.querySelector('.saga-section-content');

            if (!toggle || !content) return;

            // Store references
            this.sections.set(sectionId, {
                element,
                toggle,
                content,
                id: sectionId
            });

            // Restore saved state
            const savedState = this.getSectionState(sectionId);
            if (savedState !== null) {
                this.setSectionExpanded(sectionId, savedState, false);
            }

            // Add event listeners
            toggle.addEventListener('click', () => this.toggleSection(sectionId));
            toggle.addEventListener('keydown', (e) => this.handleKeydown(e, sectionId));
        }

        /**
         * Setup Expand All / Collapse All controls
         */
        setupControls() {
            const expandAll = document.querySelectorAll('.saga-expand-all');
            const collapseAll = document.querySelectorAll('.saga-collapse-all');

            expandAll.forEach(button => {
                button.addEventListener('click', () => this.expandAllSections());
            });

            collapseAll.forEach(button => {
                button.addEventListener('click', () => this.collapseAllSections());
            });
        }

        /**
         * Toggle section expanded state
         */
        toggleSection(sectionId) {
            const section = this.sections.get(sectionId);
            if (!section) return;

            const isExpanded = section.toggle.getAttribute('aria-expanded') === 'true';
            this.setSectionExpanded(sectionId, !isExpanded);
        }

        /**
         * Set section expanded state
         */
        setSectionExpanded(sectionId, expanded, animate = true, scroll = false) {
            const section = this.sections.get(sectionId);
            if (!section) return;

            const { toggle, content, element } = section;
            const wasExpanded = toggle.getAttribute('aria-expanded') === 'true';

            // No change
            if (wasExpanded === expanded) return;

            // Update ARIA attributes
            toggle.setAttribute('aria-expanded', expanded.toString());
            content.setAttribute('aria-hidden', (!expanded).toString());

            // Update screen reader text
            const srText = toggle.querySelector('.toggle-state');
            if (srText) {
                srText.textContent = expanded ?
                    this.config.i18n?.expanded || 'Expanded' :
                    this.config.i18n?.collapsed || 'Collapsed';
            }

            // Handle animation
            if (!animate || this.reducedMotion) {
                // Instant show/hide
                content.style.maxHeight = expanded ? 'none' : '0';
            } else {
                // Smooth animation
                if (expanded) {
                    // Get actual content height
                    content.style.maxHeight = '0';
                    const scrollHeight = content.scrollHeight;
                    content.style.maxHeight = scrollHeight + 'px';

                    // Reset to 'none' after animation
                    setTimeout(() => {
                        if (content.getAttribute('aria-hidden') === 'false') {
                            content.style.maxHeight = 'none';
                        }
                    }, 300);
                } else {
                    // Collapse
                    const scrollHeight = content.scrollHeight;
                    content.style.maxHeight = scrollHeight + 'px';

                    // Force reflow
                    content.offsetHeight;

                    content.style.maxHeight = '0';
                }
            }

            // Save state
            this.saveSectionState(sectionId, expanded);

            // Scroll to section if requested
            if (scroll && expanded) {
                setTimeout(() => {
                    element.scrollIntoView({
                        behavior: this.reducedMotion ? 'auto' : 'smooth',
                        block: 'start'
                    });
                }, animate ? 100 : 0);
            }

            // Emit custom event
            const event = new CustomEvent('saga:section:toggle', {
                detail: { sectionId, expanded }
            });
            element.dispatchEvent(event);
        }

        /**
         * Expand all sections
         */
        expandAllSections(animate = true) {
            this.sections.forEach((section, sectionId) => {
                this.setSectionExpanded(sectionId, true, animate);
            });
        }

        /**
         * Collapse all sections
         */
        collapseAllSections(animate = true) {
            this.sections.forEach((section, sectionId) => {
                this.setSectionExpanded(sectionId, false, animate);
            });
        }

        /**
         * Handle keyboard navigation
         */
        handleKeydown(event, sectionId) {
            // Space or Enter to toggle
            if (event.key === ' ' || event.key === 'Enter') {
                event.preventDefault();
                this.toggleSection(sectionId);
            }

            // Arrow keys for navigation
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                this.navigateToSection(sectionId, event.key === 'ArrowDown' ? 1 : -1);
            }
        }

        /**
         * Navigate to adjacent section
         */
        navigateToSection(currentId, direction) {
            const sectionIds = Array.from(this.sections.keys());
            const currentIndex = sectionIds.indexOf(currentId);

            if (currentIndex === -1) return;

            const nextIndex = currentIndex + direction;
            if (nextIndex < 0 || nextIndex >= sectionIds.length) return;

            const nextId = sectionIds[nextIndex];
            const nextSection = this.sections.get(nextId);

            if (nextSection?.toggle) {
                nextSection.toggle.focus();
            }
        }

        /**
         * Handle URL hash navigation
         */
        handleHashNavigation() {
            const hash = window.location.hash.substring(1);
            if (!hash) return;

            // Check if hash matches a section ID
            const section = this.sections.get(hash);
            if (section) {
                this.setSectionExpanded(hash, true, true, true);
            }

            // Check if hash is a content ID (section-xxx)
            if (hash.startsWith('section-')) {
                const sectionId = hash.replace('section-', '');
                const section = this.sections.get(sectionId);
                if (section) {
                    this.setSectionExpanded(sectionId, true, true, true);
                }
            }
        }

        /**
         * Get section state from localStorage
         */
        getSectionState(sectionId) {
            try {
                const stored = localStorage.getItem(this.storageKey);
                if (!stored) return null;

                const states = JSON.parse(stored);
                return states[sectionId] !== undefined ? states[sectionId] : null;
            } catch (error) {
                console.error('[SAGA] Failed to read section state:', error);
                return null;
            }
        }

        /**
         * Save section state to localStorage
         */
        saveSectionState(sectionId, expanded) {
            // Debounce writes
            if (this.saveTimeout) {
                clearTimeout(this.saveTimeout);
            }

            this.saveTimeout = setTimeout(() => {
                try {
                    const stored = localStorage.getItem(this.storageKey);
                    const states = stored ? JSON.parse(stored) : {};

                    states[sectionId] = expanded;

                    localStorage.setItem(this.storageKey, JSON.stringify(states));
                } catch (error) {
                    console.error('[SAGA] Failed to save section state:', error);
                }
            }, 300);
        }

        /**
         * Handle storage changes (multi-tab sync)
         */
        handleStorageChange(event) {
            if (event.key !== this.storageKey) return;

            try {
                const states = JSON.parse(event.newValue || '{}');

                this.sections.forEach((section, sectionId) => {
                    if (states[sectionId] !== undefined) {
                        this.setSectionExpanded(sectionId, states[sectionId], false);
                    }
                });
            } catch (error) {
                console.error('[SAGA] Failed to sync section state:', error);
            }
        }

        /**
         * Check if mobile
         */
        isMobile() {
            return window.innerWidth < 640;
        }

        /**
         * Get all section states
         */
        getAllStates() {
            const states = {};
            this.sections.forEach((section, sectionId) => {
                states[sectionId] = section.toggle.getAttribute('aria-expanded') === 'true';
            });
            return states;
        }

        /**
         * Reset all section states
         */
        resetStates() {
            try {
                localStorage.removeItem(this.storageKey);

                // Reset to default states
                this.sections.forEach((section, sectionId) => {
                    const defaultExpanded = section.toggle.getAttribute('aria-expanded') === 'true';
                    this.setSectionExpanded(sectionId, defaultExpanded, false);
                });
            } catch (error) {
                console.error('[SAGA] Failed to reset section states:', error);
            }
        }
    }

    /**
     * Initialize on page load
     */
    window.sagaSections = new CollapsibleSections();

    /**
     * Expose public API
     */
    window.sagaCollapsibleAPI = {
        expand: (sectionId) => window.sagaSections.setSectionExpanded(sectionId, true),
        collapse: (sectionId) => window.sagaSections.setSectionExpanded(sectionId, false),
        toggle: (sectionId) => window.sagaSections.toggleSection(sectionId),
        expandAll: () => window.sagaSections.expandAllSections(),
        collapseAll: () => window.sagaSections.collapseAllSections(),
        getStates: () => window.sagaSections.getAllStates(),
        reset: () => window.sagaSections.resetStates()
    };

})();
