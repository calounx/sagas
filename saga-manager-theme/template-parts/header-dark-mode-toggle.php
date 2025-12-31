<?php
/**
 * Dark Mode Toggle Button Template
 *
 * Accessible toggle button with:
 * - Icon-based UI (sun/moon)
 * - ARIA attributes for screen readers
 * - Keyboard navigation support
 * - Focus states for accessibility
 *
 * Usage:
 * <?php get_template_part('template-parts/header-dark-mode-toggle'); ?>
 *
 * Or via action hook:
 * add_action('generate_after_header', function() {
 *     get_template_part('template-parts/header-dark-mode-toggle');
 * });
 *
 * @package SagaTheme
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<button
    type="button"
    class="saga-dark-mode-toggle"
    aria-pressed="false"
    aria-label="<?php esc_attr_e('Switch to dark mode', 'saga-manager-theme'); ?>"
    title="<?php esc_attr_e('Toggle dark mode', 'saga-manager-theme'); ?>"
>
    <span class="saga-dark-mode-toggle__icon">
        <!-- Sun Icon (Light Mode) -->
        <svg
            class="saga-dark-mode-toggle__sun"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
            />
        </svg>

        <!-- Moon Icon (Dark Mode) -->
        <svg
            class="saga-dark-mode-toggle__moon"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
            />
        </svg>
    </span>

    <!-- Screen Reader Text -->
    <span class="saga-dark-mode-toggle__text">
        <?php esc_html_e('Switch to dark mode', 'saga-manager-theme'); ?>
    </span>
</button>
