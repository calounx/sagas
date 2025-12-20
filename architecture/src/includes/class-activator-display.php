<?php

declare(strict_types=1);

namespace SagaManagerDisplay;

/**
 * Handles plugin activation for Saga Manager Display
 */
final class Activator
{
    /**
     * Run activation tasks
     */
    public static function activate(): void
    {
        // Verify backend plugin is active
        if (!self::isBackendActive()) {
            deactivate_plugins(SAGA_DISPLAY_PLUGIN_BASENAME);
            wp_die(
                __('Saga Manager Display requires Saga Manager Core to be installed and activated.', 'saga-manager-display'),
                __('Plugin Dependency Error', 'saga-manager-display'),
                ['back_link' => true]
            );
        }

        // Verify backend version compatibility
        if (!self::isBackendVersionCompatible()) {
            deactivate_plugins(SAGA_DISPLAY_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Saga Manager Display requires Saga Manager Core version %s or higher.', 'saga-manager-display'),
                    SAGA_DISPLAY_MIN_CORE_VERSION
                ),
                __('Plugin Version Error', 'saga-manager-display'),
                ['back_link' => true]
            );
        }

        // Set default options
        self::setDefaultOptions();

        // Clear any cached templates
        self::clearTemplateCache();

        // Store activation info
        update_option('saga_display_activated', time());
        update_option('saga_display_version', SAGA_DISPLAY_VERSION);

        // Flush rewrite rules (in case we add custom endpoints later)
        flush_rewrite_rules();
    }

    /**
     * Check if backend plugin is active
     */
    private static function isBackendActive(): bool
    {
        return class_exists('\SagaManagerCore\SagaManagerCore');
    }

    /**
     * Check backend version compatibility
     */
    private static function isBackendVersionCompatible(): bool
    {
        if (!defined('SAGA_CORE_VERSION')) {
            return false;
        }

        return version_compare(SAGA_CORE_VERSION, SAGA_DISPLAY_MIN_CORE_VERSION, '>=');
    }

    /**
     * Set default options
     */
    private static function setDefaultOptions(): void
    {
        $defaults = [
            'saga_display_cache_ttl' => 300,
            'saga_display_lazy_load' => true,
            'saga_display_enable_blocks' => true,
            'saga_display_default_template' => 'default',
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Clear template cache
     */
    private static function clearTemplateCache(): void
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_saga_display_%'
                OR option_name LIKE '_transient_timeout_saga_display_%'"
        );
    }
}
