/**
 * Saga Entity Hover Preview
 *
 * Displays preview cards on hover over entity links
 * Desktop-only, performance-optimized with caching
 *
 * @package SagaManagerTheme
 */

(function () {
    'use strict';

    // Configuration
    const CONFIG = {
        hoverDelay: 300,        // ms before showing preview
        hideDelay: 100,         // ms before hiding preview
        maxPreviewWidth: 360,   // px
        viewportPadding: 10,    // px from viewport edge
        maxConcurrentRequests: 5,
        cacheExpiry: 3600000,   // 1 hour in ms
    };

    // State
    const state = {
        activePreview: null,
        hoverTimer: null,
        hideTimer: null,
        previewCache: new Map(),
        activeRequests: new Set(),
        template: null,
    };

    // Check for touch device
    const isTouchDevice = () => {
        return ('ontouchstart' in window) ||
            (navigator.maxTouchPoints > 0) ||
            (navigator.msMaxTouchPoints > 0);
    };

    // Check for reduced motion preference
    const prefersReducedMotion = () => {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    };

    /**
     * Initialize hover preview system
     */
    function init() {
        // Skip on touch devices
        if (isTouchDevice()) {
            return;
        }

        // Get template
        state.template = document.getElementById('saga-preview-card-template');
        if (!state.template) {
            console.error('Saga Preview: Template not found');
            return;
        }

        // Find all entity links
        const links = document.querySelectorAll('a.saga-entity-link, a[data-entity-id]');

        if (links.length === 0) {
            return;
        }

        // Attach event listeners
        links.forEach(link => {
            link.addEventListener('mouseenter', handleMouseEnter);
            link.addEventListener('mouseleave', handleMouseLeave);
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', cleanup);

        console.log(`Saga Preview: Initialized for ${links.length} links`);
    }

    /**
     * Handle mouse enter on entity link
     *
     * @param {MouseEvent} event
     */
    function handleMouseEnter(event) {
        const link = event.currentTarget;

        // Clear any pending hide timer
        if (state.hideTimer) {
            clearTimeout(state.hideTimer);
            state.hideTimer = null;
        }

        // Set hover timer
        state.hoverTimer = setTimeout(() => {
            showPreview(link);
        }, CONFIG.hoverDelay);
    }

    /**
     * Handle mouse leave from entity link
     *
     * @param {MouseEvent} event
     */
    function handleMouseLeave(event) {
        // Clear hover timer if preview hasn't shown yet
        if (state.hoverTimer) {
            clearTimeout(state.hoverTimer);
            state.hoverTimer = null;
        }

        // Set hide timer
        state.hideTimer = setTimeout(() => {
            hidePreview();
        }, CONFIG.hideDelay);
    }

    /**
     * Show preview for entity link
     *
     * @param {HTMLElement} link
     */
    async function showPreview(link) {
        // Get entity ID
        const entityId = getEntityId(link);
        if (!entityId) {
            console.error('Saga Preview: No entity ID found', link);
            return;
        }

        // Create preview card
        const previewCard = createPreviewCard();
        document.body.appendChild(previewCard);

        // Position preview
        positionPreview(previewCard, link);

        // Store active preview
        state.activePreview = {
            card: previewCard,
            link: link,
            entityId: entityId,
        };

        // Add hover listeners to preview card
        previewCard.addEventListener('mouseenter', () => {
            if (state.hideTimer) {
                clearTimeout(state.hideTimer);
                state.hideTimer = null;
            }
        });

        previewCard.addEventListener('mouseleave', () => {
            state.hideTimer = setTimeout(() => {
                hidePreview();
            }, CONFIG.hideDelay);
        });

        // Load preview data
        await loadPreviewData(entityId, previewCard);
    }

    /**
     * Hide active preview
     */
    function hidePreview() {
        if (!state.activePreview) {
            return;
        }

        const { card } = state.activePreview;

        // Fade out animation
        card.classList.add('saga-preview-hiding');

        setTimeout(() => {
            if (card.parentNode) {
                card.parentNode.removeChild(card);
            }
            state.activePreview = null;
        }, prefersReducedMotion() ? 0 : 150);
    }

    /**
     * Get entity ID from link
     *
     * @param {HTMLElement} link
     * @return {number|null}
     */
    function getEntityId(link) {
        // Check data attribute
        if (link.dataset.entityId) {
            return parseInt(link.dataset.entityId, 10);
        }

        // Parse from URL
        const href = link.getAttribute('href');
        if (!href) {
            return null;
        }

        // Match /entity/{id}/ pattern
        const match = href.match(/\/entity\/(\d+)\/?/);
        if (match) {
            return parseInt(match[1], 10);
        }

        return null;
    }

    /**
     * Create preview card element from template
     *
     * @return {HTMLElement}
     */
    function createPreviewCard() {
        const clone = state.template.content.cloneNode(true);
        const card = clone.querySelector('.saga-preview-card');

        // Add animation class
        if (!prefersReducedMotion()) {
            card.classList.add('saga-preview-animating');
        }

        return card;
    }

    /**
     * Position preview card relative to link
     *
     * @param {HTMLElement} card
     * @param {HTMLElement} link
     */
    function positionPreview(card, link) {
        const linkRect = link.getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();
        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight,
        };

        const position = calculatePosition(linkRect, cardRect, viewport);

        // Apply position
        card.style.left = `${position.left}px`;
        card.style.top = `${position.top}px`;

        // Position arrow
        const arrow = card.querySelector('.saga-preview-arrow');
        arrow.className = `saga-preview-arrow saga-preview-arrow-${position.arrowSide}`;

        if (position.arrowSide === 'left' || position.arrowSide === 'right') {
            arrow.style.top = `${position.arrowOffset}px`;
        } else {
            arrow.style.left = `${position.arrowOffset}px`;
        }
    }

    /**
     * Calculate optimal position for preview card
     *
     * @param {DOMRect} linkRect
     * @param {DOMRect} cardRect
     * @param {Object} viewport
     * @return {Object} Position and arrow configuration
     */
    function calculatePosition(linkRect, cardRect, viewport) {
        const gap = 10;
        let left, top, arrowSide, arrowOffset;

        // Try right side first (preferred)
        left = linkRect.right + gap;
        top = linkRect.top + (linkRect.height / 2) - (cardRect.height / 2);

        if (left + cardRect.width + CONFIG.viewportPadding > viewport.width) {
            // Try left side
            left = linkRect.left - cardRect.width - gap;
            arrowSide = 'right';
        } else {
            arrowSide = 'left';
        }

        // If left side also doesn't fit, try bottom
        if (left < CONFIG.viewportPadding) {
            left = linkRect.left;
            top = linkRect.bottom + gap;
            arrowSide = 'top';

            // If bottom doesn't fit, try top
            if (top + cardRect.height + CONFIG.viewportPadding > viewport.height) {
                top = linkRect.top - cardRect.height - gap;
                arrowSide = 'bottom';
            }
        }

        // Ensure card stays within viewport vertically
        if (arrowSide === 'left' || arrowSide === 'right') {
            if (top < CONFIG.viewportPadding) {
                top = CONFIG.viewportPadding;
            } else if (top + cardRect.height + CONFIG.viewportPadding > viewport.height) {
                top = viewport.height - cardRect.height - CONFIG.viewportPadding;
            }

            // Calculate arrow offset (center on link)
            arrowOffset = linkRect.top + (linkRect.height / 2) - top;
            arrowOffset = Math.max(20, Math.min(arrowOffset, cardRect.height - 20));
        } else {
            // Horizontal positioning for top/bottom arrows
            if (left < CONFIG.viewportPadding) {
                left = CONFIG.viewportPadding;
            } else if (left + cardRect.width + CONFIG.viewportPadding > viewport.width) {
                left = viewport.width - cardRect.width - CONFIG.viewportPadding;
            }

            // Calculate arrow offset (center on link)
            arrowOffset = linkRect.left + (linkRect.width / 2) - left;
            arrowOffset = Math.max(20, Math.min(arrowOffset, cardRect.width - 20));
        }

        return { left, top, arrowSide, arrowOffset };
    }

    /**
     * Load preview data for entity
     *
     * @param {number} entityId
     * @param {HTMLElement} card
     */
    async function loadPreviewData(entityId, card) {
        // Check cache first
        const cached = getCachedPreview(entityId);
        if (cached) {
            populatePreview(card, cached);
            return;
        }

        // Check concurrent request limit
        if (state.activeRequests.size >= CONFIG.maxConcurrentRequests) {
            console.warn('Saga Preview: Max concurrent requests reached');
            showError(card, 'Too many requests');
            return;
        }

        // Add to active requests
        state.activeRequests.add(entityId);

        try {
            const response = await fetch(`/wp-json/saga/v1/entities/${entityId}/preview`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Cache the response
            cachePreview(entityId, data);

            // Populate preview if still active
            if (state.activePreview && state.activePreview.entityId === entityId) {
                populatePreview(card, data);
            }

        } catch (error) {
            console.error('Saga Preview: Failed to load', error);
            if (state.activePreview && state.activePreview.entityId === entityId) {
                showError(card, 'Failed to load preview');
            }
        } finally {
            state.activeRequests.delete(entityId);
        }
    }

    /**
     * Populate preview card with data
     *
     * @param {HTMLElement} card
     * @param {Object} data
     */
    function populatePreview(card, data) {
        const loadingEl = card.querySelector('.saga-preview-loading');
        const loadedEl = card.querySelector('.saga-preview-loaded');

        // Populate content
        const thumbnailImg = card.querySelector('.saga-preview-thumbnail img');
        if (data.thumbnail) {
            thumbnailImg.src = data.thumbnail;
            thumbnailImg.alt = data.title;
            card.querySelector('.saga-preview-thumbnail').style.display = 'block';
        } else {
            card.querySelector('.saga-preview-thumbnail').style.display = 'none';
        }

        card.querySelector('.saga-preview-title').textContent = data.title;
        card.querySelector('.saga-preview-type-badge').textContent = formatEntityType(data.type);
        card.querySelector('.saga-preview-type-badge').className = `saga-preview-type-badge saga-preview-type-${data.type}`;

        const excerptEl = card.querySelector('.saga-preview-excerpt');
        if (data.excerpt) {
            excerptEl.textContent = data.excerpt;
            excerptEl.style.display = 'block';
        } else {
            excerptEl.style.display = 'none';
        }

        // Populate attributes
        const attributesEl = card.querySelector('.saga-preview-attributes');
        if (data.attributes && data.attributes.length > 0) {
            attributesEl.innerHTML = data.attributes.map(attr => `
                <div class="saga-preview-attribute">
                    <dt class="saga-preview-attribute-label">${escapeHtml(attr.label)}</dt>
                    <dd class="saga-preview-attribute-value">${escapeHtml(attr.value)}</dd>
                </div>
            `).join('');
            attributesEl.style.display = 'grid';
        } else {
            attributesEl.style.display = 'none';
        }

        // Set link
        if (data.url) {
            card.querySelector('.saga-preview-link').href = data.url;
        }

        // Show loaded content
        loadingEl.style.display = 'none';
        loadedEl.style.display = 'block';
    }

    /**
     * Show error in preview card
     *
     * @param {HTMLElement} card
     * @param {string} message
     */
    function showError(card, message) {
        const loadingText = card.querySelector('.saga-preview-loading-text');
        loadingText.textContent = message;
        card.querySelector('.saga-preview-spinner').style.display = 'none';
    }

    /**
     * Format entity type for display
     *
     * @param {string} type
     * @return {string}
     */
    function formatEntityType(type) {
        return type.charAt(0).toUpperCase() + type.slice(1);
    }

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} str
     * @return {string}
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Get cached preview data
     *
     * @param {number} entityId
     * @return {Object|null}
     */
    function getCachedPreview(entityId) {
        const cached = state.previewCache.get(entityId);
        if (!cached) {
            return null;
        }

        // Check expiry
        if (Date.now() - cached.timestamp > CONFIG.cacheExpiry) {
            state.previewCache.delete(entityId);
            return null;
        }

        return cached.data;
    }

    /**
     * Cache preview data
     *
     * @param {number} entityId
     * @param {Object} data
     */
    function cachePreview(entityId, data) {
        state.previewCache.set(entityId, {
            data: data,
            timestamp: Date.now(),
        });

        // Limit cache size to 100 entries
        if (state.previewCache.size > 100) {
            const firstKey = state.previewCache.keys().next().value;
            state.previewCache.delete(firstKey);
        }
    }

    /**
     * Cleanup on page unload
     */
    function cleanup() {
        if (state.hoverTimer) {
            clearTimeout(state.hoverTimer);
        }
        if (state.hideTimer) {
            clearTimeout(state.hideTimer);
        }
        hidePreview();
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
