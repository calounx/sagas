<?php
/**
 * Galaxy Controls Template
 *
 * UI controls for the 3D galaxy visualization.
 *
 * @package SagaManagerTheme
 * @version 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="saga-galaxy-controls" role="region" aria-label="Galaxy visualization controls">

    <h3><?php esc_html_e('Galaxy Controls', 'saga-manager-theme'); ?></h3>

    <!-- Search -->
    <div class="saga-galaxy-search">
        <label for="galaxy-search-<?php echo esc_attr($saga_id ?? ''); ?>" class="saga-galaxy-sr-only">
            <?php esc_html_e('Search entities', 'saga-manager-theme'); ?>
        </label>
        <input type="text"
               id="galaxy-search-<?php echo esc_attr($saga_id ?? ''); ?>"
               placeholder="<?php esc_attr_e('Search entities...', 'saga-manager-theme'); ?>"
               aria-label="<?php esc_attr_e('Search entities', 'saga-manager-theme'); ?>"
               autocomplete="off">
        <button class="saga-galaxy-search-clear"
                aria-label="<?php esc_attr_e('Clear search', 'saga-manager-theme'); ?>"
                title="<?php esc_attr_e('Clear', 'saga-manager-theme'); ?>"
                style="display: none;">×</button>
    </div>

    <!-- Entity Type Filters -->
    <div class="saga-galaxy-filters">
        <label><?php esc_html_e('Entity Types', 'saga-manager-theme'); ?></label>
        <div class="saga-galaxy-filter-buttons" role="group" aria-label="<?php esc_attr_e('Filter by entity type', 'saga-manager-theme'); ?>">
            <button class="saga-galaxy-filter-btn active"
                    data-type="character"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle characters', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Character', 'saga-manager-theme'); ?>
            </button>
            <button class="saga-galaxy-filter-btn active"
                    data-type="location"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle locations', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Location', 'saga-manager-theme'); ?>
            </button>
            <button class="saga-galaxy-filter-btn active"
                    data-type="event"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle events', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Event', 'saga-manager-theme'); ?>
            </button>
            <button class="saga-galaxy-filter-btn active"
                    data-type="faction"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle factions', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Faction', 'saga-manager-theme'); ?>
            </button>
            <button class="saga-galaxy-filter-btn active"
                    data-type="artifact"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle artifacts', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Artifact', 'saga-manager-theme'); ?>
            </button>
            <button class="saga-galaxy-filter-btn active"
                    data-type="concept"
                    aria-pressed="true"
                    title="<?php esc_attr_e('Toggle concepts', 'saga-manager-theme'); ?>">
                <?php esc_html_e('Concept', 'saga-manager-theme'); ?>
            </button>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="saga-galaxy-actions">
        <button class="saga-galaxy-btn"
                data-action="reset"
                aria-label="<?php esc_attr_e('Reset camera view', 'saga-manager-theme'); ?>"
                title="<?php esc_attr_e('Reset View (R)', 'saga-manager-theme'); ?>">
            <span class="saga-galaxy-btn-icon" aria-hidden="true">⟲</span>
            <?php esc_html_e('Reset View', 'saga-manager-theme'); ?>
        </button>

        <button class="saga-galaxy-btn"
                data-action="auto-rotate"
                aria-label="<?php esc_attr_e('Toggle auto-rotate', 'saga-manager-theme'); ?>"
                aria-pressed="false"
                title="<?php esc_attr_e('Auto-Rotate (A)', 'saga-manager-theme'); ?>">
            <span class="saga-galaxy-btn-icon" aria-hidden="true">↻</span>
            <?php esc_html_e('Auto-Rotate', 'saga-manager-theme'); ?>
        </button>

        <button class="saga-galaxy-btn"
                data-action="toggle-perf"
                aria-label="<?php esc_attr_e('Toggle performance monitor', 'saga-manager-theme'); ?>"
                aria-pressed="false"
                title="<?php esc_attr_e('Show Performance Stats', 'saga-manager-theme'); ?>">
            <span class="saga-galaxy-btn-icon" aria-hidden="true">📊</span>
            <?php esc_html_e('Performance', 'saga-manager-theme'); ?>
        </button>

        <button class="saga-galaxy-btn"
                data-action="toggle-shortcuts"
                aria-label="<?php esc_attr_e('Show keyboard shortcuts', 'saga-manager-theme'); ?>"
                aria-pressed="false"
                title="<?php esc_attr_e('Keyboard Shortcuts (?)', 'saga-manager-theme'); ?>">
            <span class="saga-galaxy-btn-icon" aria-hidden="true">⌨️</span>
            <?php esc_html_e('Shortcuts', 'saga-manager-theme'); ?>
        </button>

        <?php if (current_user_can('edit_posts')) : ?>
        <button class="saga-galaxy-btn"
                data-action="export"
                aria-label="<?php esc_attr_e('Export galaxy data as JSON', 'saga-manager-theme'); ?>"
                title="<?php esc_attr_e('Export Data', 'saga-manager-theme'); ?>">
            <span class="saga-galaxy-btn-icon" aria-hidden="true">⬇</span>
            <?php esc_html_e('Export', 'saga-manager-theme'); ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Stats Info -->
    <div class="saga-galaxy-info" role="status" aria-live="polite">
        <div class="saga-galaxy-info-row">
            <span class="saga-galaxy-info-label"><?php esc_html_e('Nodes:', 'saga-manager-theme'); ?></span>
            <span class="saga-galaxy-info-value saga-galaxy-info-nodes">0</span>
        </div>
        <div class="saga-galaxy-info-row">
            <span class="saga-galaxy-info-label"><?php esc_html_e('Links:', 'saga-manager-theme'); ?></span>
            <span class="saga-galaxy-info-value saga-galaxy-info-links">0</span>
        </div>
        <div class="saga-galaxy-info-row">
            <span class="saga-galaxy-info-label"><?php esc_html_e('Visible:', 'saga-manager-theme'); ?></span>
            <span class="saga-galaxy-info-value saga-galaxy-info-visible">0</span>
        </div>
    </div>

    <!-- Legend -->
    <div class="saga-galaxy-legend" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
        <label style="display: block; font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
            <?php esc_html_e('Legend', 'saga-manager-theme'); ?>
        </label>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #4488ff; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Character', 'saga-manager-theme'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #44ff88; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Location', 'saga-manager-theme'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #ff8844; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Event', 'saga-manager-theme'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #ff4488; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Faction', 'saga-manager-theme'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #ffaa44; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Artifact', 'saga-manager-theme'); ?></span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="width: 12px; height: 12px; background: #8844ff; border-radius: 50%; display: inline-block;"></span>
                <span style="color: rgba(255, 255, 255, 0.7);"><?php esc_html_e('Concept', 'saga-manager-theme'); ?></span>
            </div>
        </div>
    </div>

    <!-- Mouse/Touch Controls Help -->
    <div class="saga-galaxy-help" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1);">
        <details style="cursor: pointer;">
            <summary style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; letter-spacing: 0.5px; user-select: none;">
                <?php esc_html_e('Controls Help', 'saga-manager-theme'); ?>
            </summary>
            <div style="margin-top: 0.75rem; font-size: 0.75rem; color: rgba(255, 255, 255, 0.7); line-height: 1.6;">
                <p style="margin: 0 0 0.5rem;">
                    <strong style="color: rgba(255, 255, 255, 0.9);"><?php esc_html_e('Rotate:', 'saga-manager-theme'); ?></strong>
                    <?php esc_html_e('Left-click + drag', 'saga-manager-theme'); ?>
                </p>
                <p style="margin: 0 0 0.5rem;">
                    <strong style="color: rgba(255, 255, 255, 0.9);"><?php esc_html_e('Zoom:', 'saga-manager-theme'); ?></strong>
                    <?php esc_html_e('Scroll wheel or pinch', 'saga-manager-theme'); ?>
                </p>
                <p style="margin: 0 0 0.5rem;">
                    <strong style="color: rgba(255, 255, 255, 0.9);"><?php esc_html_e('Pan:', 'saga-manager-theme'); ?></strong>
                    <?php esc_html_e('Right-click + drag', 'saga-manager-theme'); ?>
                </p>
                <p style="margin: 0;">
                    <strong style="color: rgba(255, 255, 255, 0.9);"><?php esc_html_e('Select:', 'saga-manager-theme'); ?></strong>
                    <?php esc_html_e('Click on any entity', 'saga-manager-theme'); ?>
                </p>
            </div>
        </details>
    </div>

</div>

<script>
(function() {
    // Additional UI enhancements for controls
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('.saga-galaxy-search input');
        const clearBtn = document.querySelector('.saga-galaxy-search-clear');

        if (searchInput && clearBtn) {
            // Show/hide clear button
            searchInput.addEventListener('input', function() {
                clearBtn.style.display = this.value ? 'block' : 'none';
            });

            // Clear search
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                this.style.display = 'none';
                searchInput.focus();
            });
        }

        // Filter button ARIA states
        const filterBtns = document.querySelectorAll('.saga-galaxy-filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const isPressed = this.getAttribute('aria-pressed') === 'true';
                this.setAttribute('aria-pressed', !isPressed);
            });
        });

        // Toggle shortcuts panel
        const shortcutsBtn = document.querySelector('[data-action="toggle-shortcuts"]');
        const shortcutsPanel = document.querySelector('.saga-galaxy-shortcuts');

        if (shortcutsBtn && shortcutsPanel) {
            shortcutsBtn.addEventListener('click', function() {
                const isVisible = shortcutsPanel.classList.contains('visible');
                shortcutsPanel.classList.toggle('visible');
                this.setAttribute('aria-pressed', !isVisible);
            });
        }

        // Export data functionality
        const exportBtn = document.querySelector('[data-action="export"]');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                const sagaId = document.querySelector('.saga-galaxy-container').dataset.sagaId;
                if (!sagaId) return;

                // Show loading state
                this.disabled = true;
                this.innerHTML = '<span class="saga-galaxy-btn-icon">⏳</span> Exporting...';

                // Fetch and download data
                fetch(sagaGalaxy.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'saga_galaxy_data',
                        saga_id: sagaId,
                        nonce: sagaGalaxy.nonce
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create download
                        const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `saga-galaxy-${sagaId}-${Date.now()}.json`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    }
                })
                .catch(error => {
                    console.error('Export failed:', error);
                    alert('Failed to export galaxy data.');
                })
                .finally(() => {
                    // Reset button
                    this.disabled = false;
                    this.innerHTML = '<span class="saga-galaxy-btn-icon">⬇</span> Export';
                });
            });
        }

        // Update info panel on data load
        document.addEventListener('galaxy:dataLoaded', function(e) {
            const nodes = e.detail.nodes || [];
            const links = e.detail.links || [];

            const nodesEl = document.querySelector('.saga-galaxy-info-nodes');
            const linksEl = document.querySelector('.saga-galaxy-info-links');
            const visibleEl = document.querySelector('.saga-galaxy-info-visible');

            if (nodesEl) nodesEl.textContent = nodes.length;
            if (linksEl) linksEl.textContent = links.length;
            if (visibleEl) visibleEl.textContent = nodes.length;
        });

        // Update visible count on filter
        document.addEventListener('galaxy:typeFilter', function(e) {
            const visibleEl = document.querySelector('.saga-galaxy-info-visible');
            if (!visibleEl) return;

            // Count visible nodes
            const galaxy = e.detail.galaxy;
            if (galaxy && galaxy.nodes) {
                const visibleCount = galaxy.nodes.filter(node => {
                    const sphere = galaxy.nodeObjects.get(node.id);
                    return sphere && sphere.visible;
                }).length;

                visibleEl.textContent = visibleCount;
            }
        });
    });
})();
</script>
