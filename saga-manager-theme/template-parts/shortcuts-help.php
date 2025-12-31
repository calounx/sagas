<?php
/**
 * Keyboard Shortcuts Help Overlay
 *
 * @package SagaManagerTheme
 */

use SagaManagerTheme\Commands\CommandRegistry;

// Get all commands grouped by category
$all_commands = CommandRegistry::get_commands();
?>

<div class="saga-shortcuts-help" role="dialog" aria-modal="true" aria-labelledby="shortcuts-help-title" hidden>
    <div class="shortcuts-help__container">
        <div class="shortcuts-help__header">
            <h2 id="shortcuts-help-title" class="shortcuts-help__title">Keyboard Shortcuts</h2>
            <button type="button" class="shortcuts-close" aria-label="Close shortcuts help">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="shortcuts-help__content">
            <?php foreach ($all_commands as $category_key => $commands) : ?>
                <?php if (empty($commands)) continue; ?>

                <div class="shortcuts-section">
                    <h3><?php echo esc_html(ucfirst($category_key)); ?></h3>

                    <div class="shortcuts-list">
                        <?php foreach ($commands as $command) : ?>
                            <?php
                            // Skip if requires auth and user not logged in
                            if (isset($command['requiresAuth']) && $command['requiresAuth'] && !is_user_logged_in()) {
                                continue;
                            }
                            ?>

                            <div class="shortcut-item">
                                <span class="shortcut-item__label">
                                    <?php echo esc_html($command['label']); ?>
                                </span>
                                <div class="shortcut-item__keys">
                                    <?php
                                    // Parse and display keyboard shortcut
                                    $keys = $command['keys'];

                                    // Detect Mac platform
                                    $is_mac = strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Mac') !== false;

                                    // Replace Ctrl/Cmd based on platform
                                    if ($is_mac) {
                                        $keys = str_replace('Ctrl', 'Cmd', $keys);
                                    } else {
                                        $keys = str_replace('Cmd', 'Ctrl', $keys);
                                    }

                                    // Replace with symbols on Mac
                                    if ($is_mac) {
                                        $keys = str_replace('Cmd', '⌘', $keys);
                                        $keys = str_replace('Alt', '⌥', $keys);
                                        $keys = str_replace('Shift', '⇧', $keys);
                                        $keys = str_replace('Ctrl', '⌃', $keys);
                                    }

                                    // Split into individual keys
                                    $key_parts = [];
                                    if ($command['sequence'] ?? false) {
                                        // Sequence (e.g., "G H")
                                        $key_parts = explode(' ', $keys);
                                    } else {
                                        // Combination (e.g., "Ctrl+K")
                                        $key_parts = preg_split('/[+\s]+/', $keys);
                                    }

                                    foreach ($key_parts as $key) :
                                        $key = trim($key);
                                        if (empty($key)) continue;
                                    ?>
                                        <kbd><?php echo esc_html($key); ?></kbd>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="shortcuts-section">
                <h3>Tips</h3>
                <ul style="list-style: disc; padding-left: 20px; color: var(--color-text-muted, #6b7280);">
                    <li>Press <kbd>Ctrl</kbd> + <kbd>K</kbd> (or <kbd>⌘</kbd> + <kbd>K</kbd> on Mac) to open the command palette</li>
                    <li>Press <kbd>?</kbd> anytime to show this help overlay</li>
                    <li>Shortcuts are disabled when typing in input fields</li>
                    <li>Use arrow keys to navigate command palette results</li>
                    <li>Sequence shortcuts require pressing keys in order (e.g., press <kbd>G</kbd> then <kbd>H</kbd>)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // Close button handler
    const closeBtn = document.querySelector('.shortcuts-close');
    const overlay = document.querySelector('.saga-shortcuts-help');

    if (closeBtn && overlay) {
        closeBtn.addEventListener('click', function() {
            overlay.hidden = true;
        });

        // Close on backdrop click
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.hidden = true;
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !overlay.hidden) {
                overlay.hidden = true;
            }
        });

        // Trap focus within overlay
        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                const focusableElements = overlay.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                const firstFocusable = focusableElements[0];
                const lastFocusable = focusableElements[focusableElements.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        lastFocusable.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        firstFocusable.focus();
                        e.preventDefault();
                    }
                }
            }
        });
    }
})();
</script>
