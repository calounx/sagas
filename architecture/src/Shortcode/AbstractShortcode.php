<?php

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use SagaManagerDisplay\ApiClient\SagaApiClient;
use SagaManagerDisplay\Template\TemplateLoader;

/**
 * Base class for all shortcodes
 */
abstract class AbstractShortcode
{
    protected SagaApiClient $apiClient;
    protected TemplateLoader $templateLoader;

    protected string $tag = '';
    protected array $defaults = [];

    public function __construct(SagaApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->templateLoader = new TemplateLoader();
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @param string|null $content Enclosed content
     * @param string $tag Shortcode tag
     * @return string Rendered HTML
     */
    abstract public function render(array $atts, ?string $content = null, string $tag = ''): string;

    /**
     * Parse and validate shortcode attributes
     */
    protected function parseAttributes(array $atts): array
    {
        return shortcode_atts($this->defaults, $atts, $this->tag);
    }

    /**
     * Render a template file with data
     */
    protected function renderTemplate(string $template, array $data = []): string
    {
        return $this->templateLoader->render('shortcode/' . $template, $data);
    }

    /**
     * Render error message (visible only in preview/admin)
     */
    protected function renderError(string $message, ?\Exception $exception = null): string
    {
        // Only show detailed errors in debug mode
        if (WP_DEBUG && $exception) {
            $message .= ' (' . $exception->getMessage() . ')';
        }

        // Only show errors to admins in frontend
        if (!current_user_can('edit_posts') && !is_admin()) {
            return '';
        }

        return sprintf(
            '<div class="saga-shortcode-error" style="padding: 10px; background: #fef1f1; border: 1px solid #d63638; border-radius: 4px; color: #d63638;">
                <strong>%s</strong> %s
            </div>',
            esc_html__('Saga Manager:', 'saga-manager-display'),
            esc_html($message)
        );
    }

    /**
     * Render loading placeholder (for AJAX-loaded content)
     */
    protected function renderLoading(string $id, array $attributes = []): string
    {
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= sprintf(' data-%s="%s"', esc_attr($key), esc_attr($value));
        }

        return sprintf(
            '<div id="%s" class="saga-loading" %s>
                <span class="saga-spinner"></span>
                <span class="saga-loading-text">%s</span>
            </div>',
            esc_attr($id),
            $attrString,
            esc_html__('Loading...', 'saga-manager-display')
        );
    }

    /**
     * Build CSS classes string
     */
    protected function buildClasses(array $baseClasses, string $customClasses = ''): string
    {
        $classes = $baseClasses;

        if ($customClasses) {
            $classes = array_merge($classes, explode(' ', $customClasses));
        }

        return implode(' ', array_unique(array_filter($classes)));
    }

    /**
     * Get the shortcode tag
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * Get default attributes
     */
    public function getDefaults(): array
    {
        return $this->defaults;
    }
}
