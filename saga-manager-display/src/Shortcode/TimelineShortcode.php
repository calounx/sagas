<?php
/**
 * Timeline display shortcode handler.
 *
 * @package SagaManagerDisplay
 */

declare(strict_types=1);

namespace SagaManagerDisplay\Shortcode;

use WP_Error;

/**
 * Shortcode: [saga_timeline saga="star-wars"]
 *
 * Displays a timeline of events for a saga.
 */
class TimelineShortcode extends AbstractShortcode
{
    protected string $shortcodeTag = 'saga_timeline';

    /**
     * Get default attributes.
     *
     * @return array Default attributes.
     */
    protected function getDefaultAttributes(): array
    {
        return [
            'saga' => '',
            'layout' => 'vertical', // vertical, horizontal, compact
            'limit' => '20',
            'offset' => '0',
            'order' => 'asc', // asc, desc
            'start_date' => '', // filter start date
            'end_date' => '', // filter end date
            'show_participants' => 'true',
            'show_locations' => 'true',
            'show_descriptions' => 'true',
            'interactive' => 'true', // enable JS interactivity
            'group_by' => '', // age, year, decade
            'class' => '',
            'id' => '',
        ];
    }

    /**
     * Validate attributes.
     *
     * @param array $atts Attributes to validate.
     * @return true|WP_Error True if valid.
     */
    protected function validateAttributes(array $atts): true|WP_Error
    {
        if (empty($atts['saga'])) {
            return new WP_Error(
                'missing_saga',
                __('Saga slug is required.', 'saga-manager-display')
            );
        }

        $validLayouts = ['vertical', 'horizontal', 'compact'];
        if (!in_array($atts['layout'], $validLayouts, true)) {
            return new WP_Error(
                'invalid_layout',
                sprintf(
                    __('Invalid layout. Valid options: %s', 'saga-manager-display'),
                    implode(', ', $validLayouts)
                )
            );
        }

        $validOrders = ['asc', 'desc'];
        if (!in_array($atts['order'], $validOrders, true)) {
            return new WP_Error(
                'invalid_order',
                __('Invalid order. Use "asc" or "desc".', 'saga-manager-display')
            );
        }

        return true;
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Parsed attributes.
     * @param string|null $content Shortcode content.
     * @return string Rendered output.
     */
    protected function doRender(array $atts, ?string $content): string
    {
        // Build API request args
        $apiArgs = [
            'limit' => $this->parseInt($atts['limit'], 20),
            'offset' => $this->parseInt($atts['offset'], 0),
            'order' => $atts['order'],
        ];

        if (!empty($atts['start_date'])) {
            $apiArgs['start_date'] = sanitize_text_field($atts['start_date']);
        }

        if (!empty($atts['end_date'])) {
            $apiArgs['end_date'] = sanitize_text_field($atts['end_date']);
        }

        // Fetch timeline data
        $timeline = $this->apiClient->getTimeline($atts['saga'], $apiArgs);

        if (is_wp_error($timeline)) {
            return $this->renderError($timeline->get_error_message());
        }

        $events = $timeline['data'] ?? [];
        $meta = $timeline['meta'] ?? [];

        if (empty($events)) {
            return $this->renderWarning(
                __('No timeline events found for this saga.', 'saga-manager-display')
            );
        }

        // Group events if requested
        $groupedEvents = $events;
        if (!empty($atts['group_by'])) {
            $groupedEvents = $this->groupEvents($events, $atts['group_by']);
        }

        // Prepare template data
        $templateData = [
            'saga_slug' => $atts['saga'],
            'events' => $groupedEvents,
            'meta' => $meta,
            'is_grouped' => !empty($atts['group_by']),
            'group_by' => $atts['group_by'],
            'options' => [
                'layout' => $atts['layout'],
                'show_participants' => $this->parseBool($atts['show_participants']),
                'show_locations' => $this->parseBool($atts['show_locations']),
                'show_descriptions' => $this->parseBool($atts['show_descriptions']),
                'interactive' => $this->parseBool($atts['interactive']),
                'order' => $atts['order'],
            ],
        ];

        // Allow filtering template data
        $templateData = apply_filters('saga_display_timeline_data', $templateData, $atts);

        // Select template based on layout
        $template = match ($atts['layout']) {
            'horizontal' => 'timeline/horizontal',
            'compact' => 'timeline/compact',
            default => 'timeline/vertical',
        };

        $output = $this->templateEngine->render($template, $templateData);

        // Add data attributes for JavaScript
        $dataAttrs = '';
        if ($this->parseBool($atts['interactive'])) {
            $dataAttrs = ' ' . $this->dataAttributes([
                'saga' => $atts['saga'],
                'layout' => $atts['layout'],
                'limit' => $atts['limit'],
                'order' => $atts['order'],
            ]);
        }

        $classes = [
            'saga-timeline',
            'saga-timeline--' . $atts['layout'],
            $this->parseBool($atts['interactive']) ? 'saga-timeline--interactive' : '',
        ];

        return sprintf(
            '<div class="%s"%s>%s</div>',
            esc_attr(implode(' ', array_filter($classes))),
            $dataAttrs,
            $output
        );
    }

    /**
     * Group events by a field.
     *
     * @param array $events Events to group.
     * @param string $groupBy Grouping field.
     * @return array Grouped events.
     */
    private function groupEvents(array $events, string $groupBy): array
    {
        $groups = [];

        foreach ($events as $event) {
            $groupKey = $this->getGroupKey($event, $groupBy);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $this->getGroupLabel($groupKey, $groupBy),
                    'events' => [],
                ];
            }
            $groups[$groupKey]['events'][] = $event;
        }

        return $groups;
    }

    /**
     * Get group key for an event.
     *
     * @param array $event Event data.
     * @param string $groupBy Grouping field.
     * @return string Group key.
     */
    private function getGroupKey(array $event, string $groupBy): string
    {
        $canonDate = $event['canon_date'] ?? '';

        return match ($groupBy) {
            'age' => $this->extractAge($canonDate),
            'year' => $this->extractYear($canonDate),
            'decade' => $this->extractDecade($canonDate),
            default => 'other',
        };
    }

    /**
     * Get human-readable group label.
     *
     * @param string $key Group key.
     * @param string $groupBy Grouping field.
     * @return string Group label.
     */
    private function getGroupLabel(string $key, string $groupBy): string
    {
        if ($key === 'other') {
            return __('Other', 'saga-manager-display');
        }

        return match ($groupBy) {
            'decade' => sprintf(__('%ss', 'saga-manager-display'), $key),
            default => $key,
        };
    }

    /**
     * Extract age/era from canon date.
     *
     * @param string $date Canon date string.
     * @return string Age/era identifier.
     */
    private function extractAge(string $date): string
    {
        // Handle formats like "10,191 AG" or "4 BBY"
        if (preg_match('/(\d+)\s*([A-Z]+)$/i', $date, $matches)) {
            return strtoupper($matches[2]);
        }

        return 'other';
    }

    /**
     * Extract year from canon date.
     *
     * @param string $date Canon date string.
     * @return string Year.
     */
    private function extractYear(string $date): string
    {
        // Handle various date formats
        if (preg_match('/([\d,]+)\s*[A-Z]*$/i', $date, $matches)) {
            return str_replace(',', '', $matches[1]);
        }

        return 'other';
    }

    /**
     * Extract decade from canon date.
     *
     * @param string $date Canon date string.
     * @return string Decade (e.g., "1990").
     */
    private function extractDecade(string $date): string
    {
        if (preg_match('/([\d,]+)/', $date, $matches)) {
            $year = (int) str_replace(',', '', $matches[1]);
            return (string) (floor($year / 10) * 10);
        }

        return 'other';
    }
}
