<?php
/**
 * Template Part: Timeline Controls
 * Renders accessible UI controls for timeline interaction
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get template variables
$timeline_id = $args['timeline_id'] ?? 'timeline';
$show_help = $args['show_help'] ?? true;
?>

<div class="saga-timeline-controls-wrapper" role="complementary" aria-label="Timeline controls">

    <!-- Keyboard shortcuts help -->
    <?php if ($show_help): ?>
    <div class="timeline-keyboard-shortcuts" id="timeline-shortcuts-<?php echo esc_attr($timeline_id); ?>">
        <button class="shortcuts-toggle"
                aria-expanded="false"
                aria-controls="shortcuts-panel-<?php echo esc_attr($timeline_id); ?>"
                title="Keyboard shortcuts">
            <span class="dashicons dashicons-keyboard"></span>
            <span class="sr-only">Show keyboard shortcuts</span>
        </button>

        <div class="shortcuts-panel"
             id="shortcuts-panel-<?php echo esc_attr($timeline_id); ?>"
             hidden>
            <h4>Keyboard Shortcuts</h4>
            <dl class="shortcuts-list">
                <dt><kbd>←</kbd> <kbd>→</kbd></dt>
                <dd>Pan timeline left/right</dd>

                <dt><kbd>+</kbd> <kbd>-</kbd></dt>
                <dd>Zoom in/out</dd>

                <dt><kbd>Home</kbd></dt>
                <dd>Return to start</dd>

                <dt><kbd>Ctrl</kbd> + <kbd>F</kbd></dt>
                <dd>Search timeline</dd>

                <dt><kbd>Ctrl</kbd> + <kbd>B</kbd></dt>
                <dd>Add bookmark</dd>

                <dt><kbd>Space</kbd></dt>
                <dd>Pause/resume auto-play</dd>

                <dt><kbd>Esc</kbd></dt>
                <dd>Close panels</dd>
            </dl>
        </div>
    </div>
    <?php endif; ?>

    <!-- Zoom level indicator -->
    <div class="timeline-zoom-indicator" role="status" aria-live="polite">
        <label for="zoom-slider-<?php echo esc_attr($timeline_id); ?>" class="sr-only">
            Timeline zoom level
        </label>
        <input type="range"
               id="zoom-slider-<?php echo esc_attr($timeline_id); ?>"
               class="zoom-slider"
               min="0.0001"
               max="1000"
               step="0.0001"
               value="1"
               aria-valuemin="0.0001"
               aria-valuemax="1000"
               aria-valuenow="1"
               aria-label="Zoom level">
        <output class="zoom-value" for="zoom-slider-<?php echo esc_attr($timeline_id); ?>">
            100%
        </output>
    </div>

    <!-- Time position indicator -->
    <div class="timeline-position-indicator" role="status" aria-live="polite" aria-atomic="true">
        <span class="position-label">Current view:</span>
        <time class="position-value" datetime="">--</time>
    </div>

    <!-- Accessibility announcer -->
    <div id="timeline-announcer-<?php echo esc_attr($timeline_id); ?>"
         class="sr-only"
         role="status"
         aria-live="polite"
         aria-atomic="true">
    </div>

</div>

<style>
/* Screen reader only content */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

/* Keyboard shortcuts panel */
.timeline-keyboard-shortcuts {
    position: relative;
}

.shortcuts-toggle {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.shortcuts-toggle:hover,
.shortcuts-toggle:focus {
    background: rgba(255, 255, 255, 0.2);
    outline: 2px solid #e94560;
    outline-offset: 2px;
}

.shortcuts-panel {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: #1a1a2e;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 16px;
    min-width: 300px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.shortcuts-panel h4 {
    margin: 0 0 12px 0;
    color: #e94560;
    font-size: 14px;
    font-weight: 600;
}

.shortcuts-list {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 8px 16px;
    margin: 0;
    font-size: 13px;
    color: #fff;
}

.shortcuts-list dt {
    font-weight: 600;
    text-align: right;
}

.shortcuts-list dd {
    margin: 0;
    color: rgba(255, 255, 255, 0.8);
}

.shortcuts-list kbd {
    display: inline-block;
    padding: 2px 6px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
}

/* Zoom indicator */
.timeline-zoom-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.zoom-slider {
    flex: 1;
    min-width: 100px;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
    outline: none;
    appearance: none;
    cursor: pointer;
}

.zoom-slider::-webkit-slider-thumb {
    appearance: none;
    width: 16px;
    height: 16px;
    background: #e94560;
    border-radius: 50%;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.zoom-slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}

.zoom-slider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    background: #e94560;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.zoom-slider::-moz-range-thumb:hover {
    transform: scale(1.2);
}

.zoom-slider:focus {
    outline: 2px solid #e94560;
    outline-offset: 2px;
}

.zoom-value {
    min-width: 50px;
    text-align: right;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
}

/* Position indicator */
.timeline-position-indicator {
    padding: 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.8);
}

.position-label {
    margin-right: 8px;
}

.position-value {
    font-weight: 600;
    color: #fff;
}

/* Focus visible for accessibility */
*:focus-visible {
    outline: 2px solid #e94560;
    outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .shortcuts-toggle,
    .zoom-slider,
    .timeline-position-indicator {
        border-width: 2px;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .shortcuts-toggle,
    .zoom-slider::-webkit-slider-thumb,
    .zoom-slider::-moz-range-thumb {
        transition: none;
    }
}
</style>

<script>
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const timelineId = '<?php echo esc_js($timeline_id); ?>';

        // Keyboard shortcuts toggle
        const shortcutsToggle = document.querySelector(`#timeline-shortcuts-${timelineId} .shortcuts-toggle`);
        const shortcutsPanel = document.querySelector(`#shortcuts-panel-${timelineId}`);

        if (shortcutsToggle && shortcutsPanel) {
            shortcutsToggle.addEventListener('click', function() {
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                shortcutsPanel.hidden = isExpanded;
            });

            // Close on Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !shortcutsPanel.hidden) {
                    shortcutsToggle.setAttribute('aria-expanded', 'false');
                    shortcutsPanel.hidden = true;
                    shortcutsToggle.focus();
                }
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!shortcutsPanel.hidden &&
                    !shortcutsPanel.contains(e.target) &&
                    !shortcutsToggle.contains(e.target)) {
                    shortcutsToggle.setAttribute('aria-expanded', 'false');
                    shortcutsPanel.hidden = true;
                }
            });
        }

        // Zoom slider integration
        const zoomSlider = document.getElementById(`zoom-slider-${timelineId}`);
        const zoomValue = document.querySelector(`#timeline-shortcuts-${timelineId} .zoom-value`);

        if (zoomSlider) {
            zoomSlider.addEventListener('input', function() {
                const zoom = parseFloat(this.value);
                const percent = Math.round(zoom * 100);

                if (zoomValue) {
                    zoomValue.textContent = percent + '%';
                }

                this.setAttribute('aria-valuenow', zoom);

                // Dispatch custom event for timeline
                const event = new CustomEvent('timeline-zoom-change', {
                    detail: { zoom: zoom }
                });
                document.dispatchEvent(event);
            });
        }

        // Position indicator update
        const positionValue = document.querySelector(`#timeline-shortcuts-${timelineId} .position-value`);

        document.addEventListener('timeline-position-change', function(e) {
            if (positionValue && e.detail && e.detail.date) {
                positionValue.textContent = e.detail.date;
                positionValue.setAttribute('datetime', e.detail.isoDate || '');
            }
        });
    });
})();
</script>
