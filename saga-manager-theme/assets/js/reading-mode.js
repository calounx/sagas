/**
 * Reading Mode Controller
 *
 * Provides distraction-free reading experience with:
 * - Customizable typography (font size, line height)
 * - Multiple color themes (light, sepia, dark, black)
 * - Progress tracking
 * - Keyboard shortcuts
 * - Auto-hiding controls
 * - localStorage persistence
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * Reading Mode Manager
     */
    class ReadingModeManager {
        constructor() {
            this.isActive = false;
            this.preferences = this.loadPreferences();
            this.autoHideTimer = null;
            this.lastScrollPosition = 0;

            // DOM elements (created dynamically)
            this.container = null;
            this.controls = null;
            this.progressFill = null;

            this.init();
        }

        /**
         * Initialize reading mode
         */
        init() {
            // Add event listener to reading mode buttons
            this.attachButtonListeners();

            // Handle escape key globally
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isActive) {
                    this.exit();
                }
            });
        }

        /**
         * Attach click listeners to reading mode buttons
         */
        attachButtonListeners() {
            const buttons = document.querySelectorAll('.saga-reading-mode-button');
            buttons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.enter();
                });
            });
        }

        /**
         * Enter reading mode
         */
        enter() {
            if (this.isActive) return;

            // Store scroll position
            this.lastScrollPosition = window.pageYOffset;

            // Get content
            const content = this.extractContent();

            if (!content) {
                console.error('Reading Mode: Could not extract content');
                return;
            }

            // Create reading mode container
            this.createContainer(content);

            // Apply preferences
            this.applyPreferences();

            // Show container with animation
            requestAnimationFrame(() => {
                this.container.classList.remove('rm-entering');
                this.container.classList.add('rm-active');
            });

            // Setup event listeners
            this.setupEventListeners();

            // Mark as active
            this.isActive = true;

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Start auto-hide timer
            this.resetAutoHideTimer();

            // Announce to screen readers
            this.announce('Reading mode activated');
        }

        /**
         * Exit reading mode
         */
        exit() {
            if (!this.isActive) return;

            // Animate out
            this.container.classList.remove('rm-active');
            this.container.classList.add('rm-exiting');

            // Remove after animation
            setTimeout(() => {
                if (this.container && this.container.parentNode) {
                    this.container.parentNode.removeChild(this.container);
                }
                this.container = null;
                this.controls = null;
                this.progressFill = null;
            }, 300);

            // Restore body scroll
            document.body.style.overflow = '';

            // Restore scroll position
            window.scrollTo(0, this.lastScrollPosition);

            // Mark as inactive
            this.isActive = false;

            // Clear auto-hide timer
            if (this.autoHideTimer) {
                clearTimeout(this.autoHideTimer);
                this.autoHideTimer = null;
            }

            // Announce to screen readers
            this.announce('Reading mode deactivated');
        }

        /**
         * Extract content from current page
         */
        extractContent() {
            const article = document.querySelector('.saga-entity-article') ||
                          document.querySelector('article') ||
                          document.querySelector('.entry-content');

            if (!article) return null;

            // Extract title
            const titleElement = article.querySelector('.entry-title') ||
                               document.querySelector('h1') ||
                               document.querySelector('title');
            const title = titleElement ? titleElement.textContent.trim() : 'Untitled';

            // Extract meta information
            const entityType = this.extractEntityType(article);
            const readingTime = this.calculateReadingTime(article);

            // Extract main content
            const contentElement = article.querySelector('.entry-content') || article;
            const bodyHTML = contentElement.innerHTML;

            return {
                title,
                entityType,
                readingTime,
                bodyHTML
            };
        }

        /**
         * Extract entity type from article
         */
        extractEntityType(article) {
            const badge = article.querySelector('.saga-entity-type-badge');
            if (badge) {
                return badge.textContent.trim();
            }

            const classList = document.body.className;
            const match = classList.match(/saga-entity-type-(\w+)/);
            return match ? match[1].charAt(0).toUpperCase() + match[1].slice(1) : null;
        }

        /**
         * Calculate reading time
         */
        calculateReadingTime(element) {
            const text = element.textContent || element.innerText || '';
            const words = text.trim().split(/\s+/).length;
            const wordsPerMinute = 200;
            const minutes = Math.ceil(words / wordsPerMinute);
            return minutes;
        }

        /**
         * Create reading mode container
         */
        createContainer(content) {
            const container = document.createElement('div');
            container.className = 'reading-mode rm-entering';
            container.setAttribute('role', 'dialog');
            container.setAttribute('aria-label', 'Reading Mode');
            container.setAttribute('aria-modal', 'true');

            // Build meta items
            let metaItems = '';
            if (content.entityType) {
                metaItems += `<span class="reading-mode__meta-item">📚 ${this.escapeHTML(content.entityType)}</span>`;
            }
            if (content.readingTime) {
                metaItems += `<span class="reading-mode__meta-item">⏱️ ${content.readingTime} min read</span>`;
            }

            container.innerHTML = `
                <div class="reading-mode__content">
                    <header class="reading-mode__header">
                        <h1 class="reading-mode__title">${this.escapeHTML(content.title)}</h1>
                        ${metaItems ? `<div class="reading-mode__meta">${metaItems}</div>` : ''}
                    </header>
                    <div class="reading-mode__body">
                        ${content.bodyHTML}
                    </div>
                </div>
            `;

            // Create controls
            const controls = this.createControls();
            container.appendChild(controls);

            // Append to body
            document.body.appendChild(container);

            // Store references
            this.container = container;
            this.controls = controls;
            this.progressFill = controls.querySelector('.rm-progress-fill');
        }

        /**
         * Create controls panel
         */
        createControls() {
            const controls = document.createElement('div');
            controls.className = 'reading-mode-controls';
            controls.setAttribute('aria-label', 'Reading Mode Controls');

            controls.innerHTML = `
                <div class="reading-mode-controls__container">
                    <button class="rm-exit" aria-label="Exit reading mode (Esc)" title="Exit (Esc)">×</button>

                    <div class="rm-settings">
                        <div class="rm-setting-group rm-font-size">
                            <span class="rm-setting-label">Font Size</span>
                            <button data-size="small" aria-label="Small font" title="Small (-)">A</button>
                            <button data-size="medium" aria-label="Medium font" title="Medium">A</button>
                            <button data-size="large" aria-label="Large font" title="Large (+)">A</button>
                            <button data-size="xlarge" aria-label="Extra large font" title="Extra Large">A</button>
                        </div>

                        <div class="rm-setting-group rm-line-height">
                            <span class="rm-setting-label">Spacing</span>
                            <button data-spacing="compact" aria-label="Compact spacing" title="Compact">☰</button>
                            <button data-spacing="normal" aria-label="Normal spacing" title="Normal">☰</button>
                            <button data-spacing="relaxed" aria-label="Relaxed spacing" title="Relaxed">☰</button>
                        </div>

                        <div class="rm-setting-group rm-theme">
                            <span class="rm-setting-label">Theme</span>
                            <button data-theme="light" aria-label="Light theme" title="Light (1)">☀️</button>
                            <button data-theme="sepia" aria-label="Sepia theme" title="Sepia (2)">📄</button>
                            <button data-theme="dark" aria-label="Dark theme" title="Dark (3)">🌙</button>
                            <button data-theme="black" aria-label="Black theme" title="Black (4)">⬛</button>
                        </div>
                    </div>
                </div>

                <div class="rm-progress-bar" role="progressbar" aria-label="Reading progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="rm-progress-fill"></div>
                </div>
            `;

            return controls;
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Exit button
            const exitBtn = this.controls.querySelector('.rm-exit');
            exitBtn.addEventListener('click', () => this.exit());

            // Font size controls
            const fontSizeButtons = this.controls.querySelectorAll('.rm-font-size button');
            fontSizeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.setFontSize(btn.dataset.size);
                });
            });

            // Line height controls
            const lineHeightButtons = this.controls.querySelectorAll('.rm-line-height button');
            lineHeightButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.setLineHeight(btn.dataset.spacing);
                });
            });

            // Theme controls
            const themeButtons = this.controls.querySelectorAll('.rm-theme button');
            themeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.setTheme(btn.dataset.theme);
                });
            });

            // Scroll progress
            this.container.addEventListener('scroll', () => {
                this.updateProgress();
                this.resetAutoHideTimer();
            });

            // Keyboard shortcuts
            this.container.addEventListener('keydown', (e) => {
                this.handleKeyboardShortcut(e);
            });

            // Mouse movement - show controls
            this.container.addEventListener('mousemove', () => {
                this.showControls();
                this.resetAutoHideTimer();
            });

            // Focus trap
            this.setupFocusTrap();
        }

        /**
         * Apply saved preferences
         */
        applyPreferences() {
            this.setFontSize(this.preferences.font_size);
            this.setLineHeight(this.preferences.line_height);
            this.setTheme(this.preferences.theme);
        }

        /**
         * Set font size
         */
        setFontSize(size) {
            this.container.setAttribute('data-font-size', size);
            this.preferences.font_size = size;
            this.savePreferences();

            // Update active button
            this.controls.querySelectorAll('.rm-font-size button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.size === size);
            });

            this.announce(`Font size set to ${size}`);
        }

        /**
         * Set line height
         */
        setLineHeight(spacing) {
            this.container.setAttribute('data-line-height', spacing);
            this.preferences.line_height = spacing;
            this.savePreferences();

            // Update active button
            this.controls.querySelectorAll('.rm-line-height button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.spacing === spacing);
            });

            this.announce(`Line spacing set to ${spacing}`);
        }

        /**
         * Set theme
         */
        setTheme(theme) {
            this.container.setAttribute('data-theme', theme);
            this.preferences.theme = theme;
            this.savePreferences();

            // Update active button
            this.controls.querySelectorAll('.rm-theme button').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.theme === theme);
            });

            this.announce(`Theme set to ${theme}`);
        }

        /**
         * Update reading progress
         */
        updateProgress() {
            const scrollTop = this.container.scrollTop;
            const scrollHeight = this.container.scrollHeight - this.container.clientHeight;
            const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;

            this.progressFill.style.width = `${progress}%`;
            this.progressFill.parentElement.setAttribute('aria-valuenow', Math.round(progress));
        }

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcut(e) {
            // Ignore if typing in input/textarea
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            const sizes = ['small', 'medium', 'large', 'xlarge'];
            const currentSizeIndex = sizes.indexOf(this.preferences.font_size);

            switch(e.key) {
                case 'Escape':
                    this.exit();
                    break;

                case '+':
                case '=':
                    e.preventDefault();
                    if (currentSizeIndex < sizes.length - 1) {
                        this.setFontSize(sizes[currentSizeIndex + 1]);
                    }
                    break;

                case '-':
                    e.preventDefault();
                    if (currentSizeIndex > 0) {
                        this.setFontSize(sizes[currentSizeIndex - 1]);
                    }
                    break;

                case '1':
                    e.preventDefault();
                    this.setTheme('light');
                    break;

                case '2':
                    e.preventDefault();
                    this.setTheme('sepia');
                    break;

                case '3':
                    e.preventDefault();
                    this.setTheme('dark');
                    break;

                case '4':
                    e.preventDefault();
                    this.setTheme('black');
                    break;

                case ' ':
                    if (e.target === this.container || e.target.classList.contains('reading-mode__content')) {
                        e.preventDefault();
                        this.toggleControls();
                    }
                    break;
            }
        }

        /**
         * Show controls
         */
        showControls() {
            this.controls.classList.remove('rm-controls-hidden');
        }

        /**
         * Hide controls
         */
        hideControls() {
            if (this.preferences.auto_hide_controls) {
                this.controls.classList.add('rm-controls-hidden');
            }
        }

        /**
         * Toggle controls visibility
         */
        toggleControls() {
            this.controls.classList.toggle('rm-controls-hidden');
        }

        /**
         * Reset auto-hide timer
         */
        resetAutoHideTimer() {
            if (this.autoHideTimer) {
                clearTimeout(this.autoHideTimer);
            }

            this.showControls();

            if (this.preferences.auto_hide_controls) {
                this.autoHideTimer = setTimeout(() => {
                    this.hideControls();
                }, 3000);
            }
        }

        /**
         * Setup focus trap
         */
        setupFocusTrap() {
            const focusableElements = this.container.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            this.container.addEventListener('keydown', (e) => {
                if (e.key !== 'Tab') return;

                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            });

            // Focus first element
            setTimeout(() => firstElement.focus(), 100);
        }

        /**
         * Load preferences from localStorage
         */
        loadPreferences() {
            const defaults = {
                font_size: 'medium',
                line_height: 'normal',
                theme: 'sepia',
                auto_hide_controls: true
            };

            try {
                const saved = localStorage.getItem('saga_reading_mode_preferences');
                if (saved) {
                    return { ...defaults, ...JSON.parse(saved) };
                }
            } catch (e) {
                console.warn('Reading Mode: Could not load preferences', e);
            }

            return defaults;
        }

        /**
         * Save preferences to localStorage
         */
        savePreferences() {
            try {
                localStorage.setItem('saga_reading_mode_preferences', JSON.stringify(this.preferences));
            } catch (e) {
                console.warn('Reading Mode: Could not save preferences', e);
            }
        }

        /**
         * Announce to screen readers
         */
        announce(message) {
            const announcement = document.createElement('div');
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'sr-only';
            announcement.textContent = message;
            document.body.appendChild(announcement);

            setTimeout(() => {
                document.body.removeChild(announcement);
            }, 1000);
        }

        /**
         * Escape HTML
         */
        escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.sagaReadingMode = new ReadingModeManager();
        });
    } else {
        window.sagaReadingMode = new ReadingModeManager();
    }

})();
