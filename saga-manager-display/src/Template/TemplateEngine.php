<?php
/**
 * Template engine with theme override support.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Template;

/**
 * Template engine for rendering saga display components.
 */
class TemplateEngine
{
    private const THEME_TEMPLATE_DIR = 'saga-manager';

    private string $pluginTemplateDir;
    private array $templateCache = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->pluginTemplateDir = SAGA_DISPLAY_PLUGIN_DIR . 'templates/';
    }

    /**
     * Render a template.
     *
     * @param string $template Template name (e.g., 'entity/single', 'timeline/list').
     * @param array $data Data to pass to template.
     * @param bool $return Whether to return output or echo it.
     * @return string|void Template output if $return is true.
     */
    public function render(string $template, array $data = [], bool $return = true): string|null
    {
        $templatePath = $this->locateTemplate($template);

        if ($templatePath === null) {
            if (WP_DEBUG) {
                error_log("[SAGA_DISPLAY] Template not found: {$template}");
            }
            return $return ? '' : null;
        }

        // Extract data to variables
        $data = apply_filters('saga_display_template_data', $data, $template);

        // Add helper functions to data
        $data['esc'] = $this->getEscapingHelpers();
        $data['__'] = fn(string $text) => __($text, 'saga-manager-display');
        $data['_e'] = fn(string $text) => _e($text, 'saga-manager-display');

        if ($return) {
            ob_start();
            $this->includeTemplate($templatePath, $data);
            return ob_get_clean();
        }

        $this->includeTemplate($templatePath, $data);
        return null;
    }

    /**
     * Include template with isolated scope.
     *
     * @param string $templatePath Full path to template file.
     * @param array $data Data to extract into template scope.
     */
    private function includeTemplate(string $templatePath, array $data): void
    {
        // Create isolated scope
        $__template_path = $templatePath;
        $__template_data = $data;

        unset($templatePath, $data);

        extract($__template_data, EXTR_SKIP);

        include $__template_path;
    }

    /**
     * Locate a template file.
     *
     * Priority:
     * 1. Child theme: {child-theme}/saga-manager/{template}.php
     * 2. Parent theme: {parent-theme}/saga-manager/{template}.php
     * 3. Plugin: {plugin}/templates/{template}.php
     *
     * @param string $template Template name.
     * @return string|null Full path to template or null if not found.
     */
    public function locateTemplate(string $template): ?string
    {
        // Normalize template name
        $template = ltrim($template, '/');
        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        // Check cache
        if (isset($this->templateCache[$template])) {
            return $this->templateCache[$template];
        }

        // Check child theme
        $childThemePath = get_stylesheet_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template;
        if (file_exists($childThemePath)) {
            $this->templateCache[$template] = $childThemePath;
            return $childThemePath;
        }

        // Check parent theme
        $parentThemePath = get_template_directory() . '/' . self::THEME_TEMPLATE_DIR . '/' . $template;
        if (file_exists($parentThemePath)) {
            $this->templateCache[$template] = $parentThemePath;
            return $parentThemePath;
        }

        // Check plugin templates
        $pluginPath = $this->pluginTemplateDir . $template;
        if (file_exists($pluginPath)) {
            $this->templateCache[$template] = $pluginPath;
            return $pluginPath;
        }

        // Allow filtering for custom template locations
        $customPath = apply_filters('saga_display_template_path', null, $template);
        if ($customPath !== null && file_exists($customPath)) {
            $this->templateCache[$template] = $customPath;
            return $customPath;
        }

        return null;
    }

    /**
     * Get escaping helper functions.
     *
     * @return array Array of escaping functions.
     */
    private function getEscapingHelpers(): array
    {
        return [
            'html' => 'esc_html',
            'attr' => 'esc_attr',
            'url' => 'esc_url',
            'js' => 'esc_js',
            'textarea' => 'esc_textarea',
            'kses' => fn(string $content) => wp_kses_post($content),
            'date' => fn(string $date, string $format = '') => $this->formatDate($date, $format),
            'number' => fn(int|float $number) => number_format_i18n($number),
        ];
    }

    /**
     * Format a date.
     *
     * @param string $date Date string.
     * @param string $format Optional format (defaults to WordPress date format).
     * @return string Formatted date.
     */
    private function formatDate(string $date, string $format = ''): string
    {
        if (empty($format)) {
            $format = get_option('date_format');
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return esc_html($date);
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * Render a partial template.
     *
     * @param string $partial Partial template name.
     * @param array $data Data to pass to partial.
     * @return string Rendered partial.
     */
    public function partial(string $partial, array $data = []): string
    {
        return $this->render('partials/' . $partial, $data);
    }

    /**
     * Check if a template exists.
     *
     * @param string $template Template name.
     * @return bool True if template exists.
     */
    public function templateExists(string $template): bool
    {
        return $this->locateTemplate($template) !== null;
    }

    /**
     * Get the template directory for themes to override.
     *
     * @return string Theme template directory name.
     */
    public function getThemeTemplateDir(): string
    {
        return self::THEME_TEMPLATE_DIR;
    }

    /**
     * Clear template cache.
     */
    public function clearCache(): void
    {
        $this->templateCache = [];
    }

    /**
     * Render an entity card.
     *
     * @param array $entity Entity data.
     * @param string $size Card size ('small', 'medium', 'large').
     * @return string Rendered card.
     */
    public function entityCard(array $entity, string $size = 'medium'): string
    {
        return $this->render('entity/card', [
            'entity' => $entity,
            'size' => $size,
        ]);
    }

    /**
     * Render an entity list.
     *
     * @param array $entities Array of entities.
     * @param array $options Display options.
     * @return string Rendered list.
     */
    public function entityList(array $entities, array $options = []): string
    {
        return $this->render('entity/list', [
            'entities' => $entities,
            'options' => array_merge([
                'show_type' => true,
                'show_importance' => false,
                'columns' => 1,
            ], $options),
        ]);
    }

    /**
     * Render pagination.
     *
     * @param int $currentPage Current page number.
     * @param int $totalPages Total number of pages.
     * @param string $baseUrl Base URL for pagination links.
     * @return string Rendered pagination.
     */
    public function pagination(int $currentPage, int $totalPages, string $baseUrl): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        return $this->render('partials/pagination', [
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'base_url' => $baseUrl,
        ]);
    }

    /**
     * Render an error message.
     *
     * @param string $message Error message.
     * @param string $type Error type ('error', 'warning', 'info').
     * @return string Rendered error message.
     */
    public function error(string $message, string $type = 'error'): string
    {
        return $this->render('partials/message', [
            'message' => $message,
            'type' => $type,
        ]);
    }

    /**
     * Render a loading placeholder.
     *
     * @param string $type Placeholder type ('card', 'list', 'text').
     * @return string Rendered placeholder.
     */
    public function loadingPlaceholder(string $type = 'card'): string
    {
        return $this->render('partials/loading', [
            'type' => $type,
        ]);
    }

    /**
     * Build CSS classes string.
     *
     * @param array $classes Array of class names or conditional classes.
     * @return string CSS class string.
     */
    public function classes(array $classes): string
    {
        $result = [];

        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                // Simple class name
                $result[] = $value;
            } elseif ($value) {
                // Conditional class
                $result[] = $key;
            }
        }

        return implode(' ', array_filter($result));
    }

    /**
     * Build inline style string.
     *
     * @param array $styles Array of CSS property => value pairs.
     * @return string Inline style string.
     */
    public function styles(array $styles): string
    {
        $result = [];

        foreach ($styles as $property => $value) {
            if ($value !== null && $value !== '') {
                $result[] = esc_attr($property) . ': ' . esc_attr($value);
            }
        }

        return implode('; ', $result);
    }
}
