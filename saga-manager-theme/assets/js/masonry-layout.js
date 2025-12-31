/**
 * Masonry Layout Manager
 *
 * Handles masonry grid initialization and layout management
 * Uses Masonry.js library for Pinterest-style layout
 *
 * @package SagaTheme
 * @version 1.0.0
 */

(function () {
    'use strict';

    /**
     * Masonry Layout Manager Class
     */
    class SagaMasonryLayout {
        constructor(container, options = {}) {
            this.container = container;
            this.options = {
                itemSelector: '.saga-masonry-grid__item',
                columnWidth: '.saga-masonry-grid__sizer',
                percentPosition: true,
                gutter: 24,
                transitionDuration: '0.4s',
                initLayout: false,
                ...options
            };

            this.masonry = null;
            this.imagesLoaded = null;
            this.resizeTimer = null;

            this.init();
        }

        /**
         * Initialize masonry layout
         */
        init() {
            // Wait for Masonry and imagesLoaded libraries to be available
            this.waitForLibraries()
                .then(() => {
                    this.initializeMasonry();
                    this.setupEventListeners();
                    this.handleResponsiveGutter();
                })
                .catch((error) => {
                    console.error('[Saga Masonry] Failed to initialize:', error);
                });
        }

        /**
         * Wait for required libraries to load
         */
        waitForLibraries() {
            return new Promise((resolve, reject) => {
                const maxAttempts = 50;
                let attempts = 0;

                const checkLibraries = () => {
                    attempts++;

                    if (typeof Masonry !== 'undefined' && typeof imagesLoaded !== 'undefined') {
                        resolve();
                    } else if (attempts >= maxAttempts) {
                        reject(new Error('Required libraries (Masonry.js, imagesLoaded) not found'));
                    } else {
                        setTimeout(checkLibraries, 100);
                    }
                };

                checkLibraries();
            });
        }

        /**
         * Initialize Masonry instance
         */
        initializeMasonry() {
            if (!this.container) {
                console.error('[Saga Masonry] Container not found');
                return;
            }

            // Create masonry instance
            this.masonry = new Masonry(this.container, this.options);

            // Wait for all images to load before laying out
            this.imagesLoaded = imagesLoaded(this.container);

            this.imagesLoaded.on('progress', () => {
                // Layout after each image loads
                this.layout();
            });

            this.imagesLoaded.on('done', () => {
                // Final layout when all images are loaded
                this.layout();
                this.container.classList.add('saga-masonry-grid--loaded');

                // Trigger custom event
                this.dispatchEvent('masonryLoaded');
            });
        }

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Debounced window resize
            window.addEventListener('resize', () => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => {
                    this.handleResponsiveGutter();
                    this.layout();
                }, 250);
            });

            // Handle orientation change on mobile
            window.addEventListener('orientationchange', () => {
                setTimeout(() => {
                    this.layout();
                }, 200);
            });
        }

        /**
         * Handle responsive gutter sizes
         */
        handleResponsiveGutter() {
            const width = window.innerWidth;
            let gutter = 24;

            if (width < 768) {
                gutter = 16;
            } else if (width < 1200) {
                gutter = 20;
            }

            if (this.masonry && this.masonry.options.gutter !== gutter) {
                this.masonry.options.gutter = gutter;
                this.layout();
            }
        }

        /**
         * Perform masonry layout
         */
        layout() {
            if (this.masonry) {
                this.masonry.layout();
            }
        }

        /**
         * Add new items to masonry grid
         */
        addItems(elements) {
            if (!this.masonry || !elements || elements.length === 0) {
                return;
            }

            // Append elements to container
            elements.forEach(el => {
                this.container.appendChild(el);
            });

            // Add lazy-loaded class for faster animation
            elements.forEach(el => {
                el.classList.add('saga-masonry-grid__item--lazy-loaded');
            });

            // Prepare masonry for new items
            this.masonry.appended(elements);

            // Wait for images to load
            const imgLoad = imagesLoaded(elements);

            imgLoad.on('progress', () => {
                this.layout();
            });

            imgLoad.on('done', () => {
                this.layout();
                this.dispatchEvent('itemsAdded', { items: elements });
            });
        }

        /**
         * Remove items from masonry grid
         */
        removeItems(elements) {
            if (!this.masonry || !elements || elements.length === 0) {
                return;
            }

            this.masonry.remove(elements);
            this.layout();
        }

        /**
         * Reload masonry (useful after filtering)
         */
        reload() {
            if (this.masonry) {
                this.masonry.reloadItems();
                this.layout();
            }
        }

        /**
         * Destroy masonry instance
         */
        destroy() {
            if (this.masonry) {
                this.masonry.destroy();
                this.masonry = null;
            }

            window.removeEventListener('resize', this.handleResize);
            window.removeEventListener('orientationchange', this.handleOrientationChange);
        }

        /**
         * Dispatch custom event
         */
        dispatchEvent(name, detail = {}) {
            const event = new CustomEvent(`sagaMasonry:${name}`, {
                bubbles: true,
                detail: detail
            });
            this.container.dispatchEvent(event);
        }
    }

    /**
     * Initialize masonry on DOM ready
     */
    function initMasonry() {
        const containers = document.querySelectorAll('.saga-masonry-grid');

        if (containers.length === 0) {
            return;
        }

        containers.forEach(container => {
            // Store masonry instance on the element
            container.sagaMasonry = new SagaMasonryLayout(container);
        });
    }

    /**
     * Auto-initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMasonry);
    } else {
        initMasonry();
    }

    /**
     * Expose class globally for external access
     */
    window.SagaMasonryLayout = SagaMasonryLayout;

})();
