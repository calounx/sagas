<?php
/**
 * Abstract base class for shortcodes.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use SagaManagerDisplay\API\SagaApiClient;
use SagaManagerDisplay\Template\TemplateEngine;
use WP_Error;

/**
 * Abstract shortcode handler.
 */
abstract class AbstractShortcode
{
    protected SagaApiClient $apiClient;
    protected TemplateEngine $templateEngine;
    protected string $shortcodeTag;

    /**
     * Constructor.
     *
     * @param SagaApiClient $apiClient API client instance.
     * @param TemplateEngine $templateEngine Template engine instance.
     */
    public function __construct(SagaApiClient $apiClient, TemplateEngine $templateEngine)
    {
        $this->apiClient = $apiClient;
        $this->templateEngine = $templateEngine;
    }

    /**
     * Render the shortcode.
     *
     * @param array|string $atts Shortcode attributes.
     * @param string|null $content Shortcode content.
     * @param string $tag Shortcode tag.
     * @return string Rendered output.
     */
    public function render(array|string $atts, ?string $content = null, string $tag = ''): string
    {
        // Normalize attributes
        $atts = $this->normalizeAttributes($atts);

        // Parse with defaults
        $atts = shortcode_atts($this->getDefaultAttributes(), $atts, $tag);

        // Validate attributes
        $validation = $this->validateAttributes($atts);
        if (is_wp_error($validation)) {
            return $this->renderError($validation->get_error_message());
        }

        // Check API availability
        if (!$this->apiClient->isAvailable()) {
            return $this->renderError(
                __('The Saga Manager API is currently unavailable. Please try again later.', 'saga-manager-display')
            );
        }

        try {
            return $this->doRender($atts, $content);
        } catch (\Throwable $e) {
            if (WP_DEBUG) {
                error_log("[SAGA_DISPLAY][{$this->shortcodeTag}] Error: " . $e->getMessage());
            }
            return $this->renderError(
                __('An error occurred while loading content.', 'saga-manager-display')
            );
        }
    }

    /**
     * Perform the actual rendering.
     *
     * @param array $atts Parsed attributes.
     * @param string|null $content Shortcode content.
     * @return string Rendered output.
     */
    abstract protected function doRender(array $atts, ?string $content): string;

    /**
     * Get default attribute values.
     *
     * @return array Default attributes.
     */
    abstract protected function getDefaultAttributes(): array;

    /**
     * Validate attributes.
     *
     * @param array $atts Attributes to validate.
     * @return true|WP_Error True if valid, WP_Error if not.
     */
    protected function validateAttributes(array $atts): true|WP_Error
    {
        return true;
    }

    /**
     * Normalize attributes array.
     *
     * @param array|string $atts Raw attributes.
     * @return array Normalized attributes.
     */
    protected function normalizeAttributes(array|string $atts): array
    {
        if (is_string($atts)) {
            return [];
        }

        // Convert numeric keys to empty string values
        $normalized = [];
        foreach ($atts as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = '';
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Render an error message.
     *
     * @param string $message Error message.
     * @return string Rendered error.
     */
    protected function renderError(string $message): string
    {
        return $this->templateEngine->error($message, 'error');
    }

    /**
     * Render a warning message.
     *
     * @param string $message Warning message.
     * @return string Rendered warning.
     */
    protected function renderWarning(string $message): string
    {
        return $this->templateEngine->error($message, 'warning');
    }

    /**
     * Render a loading placeholder.
     *
     * @param string $type Placeholder type.
     * @return string Rendered placeholder.
     */
    protected function renderLoading(string $type = 'card'): string
    {
        return $this->templateEngine->loadingPlaceholder($type);
    }

    /**
     * Parse a boolean attribute.
     *
     * @param mixed $value Attribute value.
     * @return bool Boolean value.
     */
    protected function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Parse an integer attribute.
     *
     * @param mixed $value Attribute value.
     * @param int $default Default value.
     * @return int Integer value.
     */
    protected function parseInt(mixed $value, int $default = 0): int
    {
        if ($value === '' || $value === null) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * Parse a list attribute (comma-separated).
     *
     * @param mixed $value Attribute value.
     * @return array List of values.
     */
    protected function parseList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', (string) $value));
    }

    /**
     * Build wrapper element with common attributes.
     *
     * @param string $content Inner content.
     * @param array $atts Shortcode attributes.
     * @param string $baseClass Base CSS class.
     * @return string Wrapped content.
     */
    protected function wrapOutput(string $content, array $atts, string $baseClass): string
    {
        $classes = [$baseClass];

        if (!empty($atts['class'])) {
            $classes[] = sanitize_html_class($atts['class']);
        }

        $id = !empty($atts['id']) ? ' id="' . esc_attr($atts['id']) . '"' : '';

        return sprintf(
            '<div class="%s"%s>%s</div>',
            esc_attr(implode(' ', $classes)),
            $id,
            $content
        );
    }

    /**
     * Add data attributes for JavaScript initialization.
     *
     * @param array $data Data to add.
     * @return string Data attributes string.
     */
    protected function dataAttributes(array $data): string
    {
        $attrs = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $key = 'data-' . str_replace('_', '-', $key);

            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            }

            $attrs[] = esc_attr($key) . '="' . esc_attr((string) $value) . '"';
        }

        return implode(' ', $attrs);
    }
}
