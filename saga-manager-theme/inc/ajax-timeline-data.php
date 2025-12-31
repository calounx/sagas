<?php
/**
 * AJAX Timeline Data Handler
 *
 * Handles AJAX requests for timeline event data.
 * Implements proper security, caching, and performance optimizations.
 *
 * @package SagaManagerTheme
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handlers
 */
function saga_register_timeline_ajax_handlers() {
    // Public (for non-logged in users)
    add_action('wp_ajax_nopriv_saga_get_timeline_data', 'saga_ajax_get_timeline_data');

    // Logged in users
    add_action('wp_ajax_saga_get_timeline_data', 'saga_ajax_get_timeline_data');
}
add_action('init', 'saga_register_timeline_ajax_handlers');

/**
 * Get timeline data via AJAX
 *
 * Returns timeline events with metadata for a given saga.
 *
 * Expected POST parameters:
 * - saga_id (required): Saga ID
 * - entity_id (optional): Filter by specific entity
 * - date_range (optional): Array with 'start' and 'end' dates
 * - nonce (required): Security nonce
 */
function saga_ajax_get_timeline_data() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saga_timeline_nonce')) {
        wp_send_json_error([
            'message' => __('Security check failed.', 'saga-manager'),
            'code' => 'invalid_nonce'
        ], 403);
    }

    // Get and validate saga_id
    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;

    if (!$saga_id) {
        wp_send_json_error([
            'message' => __('Saga ID is required.', 'saga-manager'),
            'code' => 'missing_saga_id'
        ], 400);
    }

    // Optional parameters
    $entity_id = isset($_POST['entity_id']) ? absint($_POST['entity_id']) : null;
    $date_range = isset($_POST['date_range']) ? $_POST['date_range'] : null;

    // Check cache first
    $cache_key = saga_get_timeline_cache_key($saga_id, $entity_id, $date_range);
    $cached_data = wp_cache_get($cache_key, 'saga_timeline');

    if (false !== $cached_data) {
        wp_send_json_success($cached_data);
    }

    global $wpdb;

    // Verify saga exists
    $saga = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saga_sagas WHERE id = %d",
        $saga_id
    ));

    if (!$saga) {
        wp_send_json_error([
            'message' => sprintf(__('Saga #%d not found.', 'saga-manager'), $saga_id),
            'code' => 'saga_not_found'
        ], 404);
    }

    // Build query for timeline events
    $query = saga_build_timeline_query($saga_id, $entity_id, $date_range);

    // Execute query with error handling
    try {
        $events = $wpdb->get_results($query);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

    } catch (Exception $e) {
        error_log('[SAGA][ERROR] Timeline query failed: ' . $e->getMessage());

        wp_send_json_error([
            'message' => __('Failed to retrieve timeline data.', 'saga-manager'),
            'code' => 'query_error'
        ], 500);
    }

    // Transform events data
    $transformed_events = array_map('saga_transform_timeline_event', $events);

    // Prepare response data
    $response_data = [
        'events' => $transformed_events,
        'calendar_type' => $saga->calendar_type,
        'calendar_config' => json_decode($saga->calendar_config, true) ?: [],
        'saga_name' => $saga->name,
        'saga_universe' => $saga->universe,
        'total_events' => count($transformed_events),
        'date_range' => saga_calculate_date_range($transformed_events),
    ];

    // Cache the response (5 minutes)
    wp_cache_set($cache_key, $response_data, 'saga_timeline', 300);

    // Send success response
    wp_send_json_success($response_data);
}

/**
 * Build timeline query
 *
 * Constructs optimized SQL query for timeline events.
 *
 * @param int $saga_id Saga ID
 * @param int|null $entity_id Entity ID filter
 * @param array|null $date_range Date range filter
 * @return string SQL query
 */
function saga_build_timeline_query($saga_id, $entity_id = null, $date_range = null) {
    global $wpdb;

    $where_clauses = [];
    $where_clauses[] = $wpdb->prepare("te.saga_id = %d", $saga_id);

    // Entity filter
    if ($entity_id) {
        $where_clauses[] = $wpdb->prepare("te.event_entity_id = %d", $entity_id);
    }

    // Date range filter
    if (is_array($date_range)) {
        if (!empty($date_range['start'])) {
            $start_timestamp = strtotime($date_range['start']);
            if ($start_timestamp) {
                $where_clauses[] = $wpdb->prepare("te.normalized_timestamp >= %d", $start_timestamp);
            }
        }

        if (!empty($date_range['end'])) {
            $end_timestamp = strtotime($date_range['end']);
            if ($end_timestamp) {
                $where_clauses[] = $wpdb->prepare("te.normalized_timestamp <= %d", $end_timestamp);
            }
        }
    }

    $where_sql = implode(' AND ', $where_clauses);

    // Optimized query with LEFT JOIN to get entity details
    $query = "
        SELECT
            te.id,
            te.canon_date,
            te.normalized_timestamp,
            te.title,
            te.description,
            te.participants,
            te.locations,
            e.entity_type,
            e.importance_score as importance,
            e.canonical_name as entity_name
        FROM {$wpdb->prefix}saga_timeline_events te
        LEFT JOIN {$wpdb->prefix}saga_entities e ON te.event_entity_id = e.id
        WHERE {$where_sql}
        ORDER BY te.normalized_timestamp ASC
        LIMIT 10000
    ";

    return $query;
}

/**
 * Transform timeline event for frontend
 *
 * Transforms database event record to frontend-friendly format.
 *
 * @param object $event Database event record
 * @return array Transformed event
 */
function saga_transform_timeline_event($event) {
    // Parse JSON fields
    $participants = !empty($event->participants) ? json_decode($event->participants, true) : [];
    $locations = !empty($event->locations) ? json_decode($event->locations, true) : [];

    // Determine event type from title/description if not set
    $event_type = saga_detect_event_type($event);

    // Get location names if we have location IDs
    $location_names = [];
    if (!empty($locations)) {
        $location_names = saga_get_entity_names($locations);
    }

    return [
        'id' => (int) $event->id,
        'title' => $event->title,
        'canon_date' => $event->canon_date,
        'normalized_timestamp' => (int) $event->normalized_timestamp,
        'description' => $event->description,
        'type' => $event_type,
        'entity_type' => $event->entity_type ?: 'event',
        'importance' => (int) ($event->importance ?: 50),
        'participants' => $participants,
        'location' => !empty($location_names) ? implode(', ', $location_names) : null,
        'metadata' => [
            'entity_name' => $event->entity_name,
        ]
    ];
}

/**
 * Detect event type from event data
 *
 * Analyzes event title and description to determine event type.
 *
 * @param object $event Event data
 * @return string Event type
 */
function saga_detect_event_type($event) {
    $title_lower = strtolower($event->title);
    $desc_lower = strtolower($event->description ?: '');

    $type_keywords = [
        'battle' => ['battle', 'war', 'fight', 'siege', 'attack', 'invasion'],
        'birth' => ['birth', 'born', 'arrival'],
        'death' => ['death', 'died', 'killed', 'assassination', 'execution'],
        'founding' => ['founding', 'founded', 'established', 'creation', 'built'],
        'discovery' => ['discovery', 'discovered', 'found', 'uncovered'],
        'treaty' => ['treaty', 'agreement', 'pact', 'alliance', 'accord'],
        'coronation' => ['coronation', 'crowned', 'ascension', 'enthronement'],
        'destruction' => ['destruction', 'destroyed', 'fall', 'collapse', 'ruin'],
        'meeting' => ['meeting', 'council', 'assembly', 'gathering'],
        'journey' => ['journey', 'voyage', 'expedition', 'quest', 'travel'],
    ];

    foreach ($type_keywords as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false || strpos($desc_lower, $keyword) !== false) {
                return $type;
            }
        }
    }

    return 'default';
}

/**
 * Get entity names by IDs
 *
 * Retrieves entity names for given entity IDs.
 *
 * @param array $entity_ids Array of entity IDs
 * @return array Array of entity names
 */
function saga_get_entity_names($entity_ids) {
    if (empty($entity_ids)) {
        return [];
    }

    global $wpdb;

    $ids_placeholder = implode(',', array_fill(0, count($entity_ids), '%d'));

    $query = $wpdb->prepare(
        "SELECT canonical_name FROM {$wpdb->prefix}saga_entities WHERE id IN ($ids_placeholder)",
        ...$entity_ids
    );

    return $wpdb->get_col($query);
}

/**
 * Calculate date range from events
 *
 * Determines the min and max dates from event data.
 *
 * @param array $events Array of transformed events
 * @return array Date range with 'min' and 'max' timestamps
 */
function saga_calculate_date_range($events) {
    if (empty($events)) {
        return [
            'min' => null,
            'max' => null
        ];
    }

    $timestamps = array_column($events, 'normalized_timestamp');

    return [
        'min' => min($timestamps),
        'max' => max($timestamps)
    ];
}

/**
 * Generate cache key for timeline data
 *
 * Creates unique cache key based on query parameters.
 *
 * @param int $saga_id Saga ID
 * @param int|null $entity_id Entity ID
 * @param array|null $date_range Date range
 * @return string Cache key
 */
function saga_get_timeline_cache_key($saga_id, $entity_id = null, $date_range = null) {
    $key_parts = ['timeline', $saga_id];

    if ($entity_id) {
        $key_parts[] = 'entity_' . $entity_id;
    }

    if (is_array($date_range)) {
        $key_parts[] = 'range_' . md5(json_encode($date_range));
    }

    return implode('_', $key_parts);
}

/**
 * Invalidate timeline cache
 *
 * Clears cached timeline data for a saga.
 * Called when timeline events are modified.
 *
 * @param int $saga_id Saga ID
 */
function saga_invalidate_timeline_cache($saga_id) {
    // WordPress object cache doesn't support wildcard deletion
    // So we use a version key strategy

    $version_key = "saga_timeline_version_{$saga_id}";
    $current_version = wp_cache_get($version_key, 'saga_timeline');

    if (false === $current_version) {
        $current_version = 0;
    }

    wp_cache_set($version_key, $current_version + 1, 'saga_timeline');
}

/**
 * Hook into event save to invalidate cache
 */
function saga_timeline_event_saved($event_id, $saga_id) {
    saga_invalidate_timeline_cache($saga_id);
}
// This would be hooked from the plugin when events are saved

/**
 * Get timeline statistics
 *
 * Additional AJAX endpoint for timeline statistics.
 */
function saga_ajax_get_timeline_stats() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saga_timeline_nonce')) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    $saga_id = isset($_POST['saga_id']) ? absint($_POST['saga_id']) : 0;

    if (!$saga_id) {
        wp_send_json_error(['message' => 'Saga ID required'], 400);
    }

    global $wpdb;

    // Get statistics
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(*) as total_events,
            MIN(normalized_timestamp) as earliest_event,
            MAX(normalized_timestamp) as latest_event,
            AVG(CHAR_LENGTH(description)) as avg_description_length
        FROM {$wpdb->prefix}saga_timeline_events
        WHERE saga_id = %d
    ", $saga_id));

    // Get event type distribution
    $type_distribution = $wpdb->get_results($wpdb->prepare("
        SELECT
            e.entity_type,
            COUNT(*) as count
        FROM {$wpdb->prefix}saga_timeline_events te
        LEFT JOIN {$wpdb->prefix}saga_entities e ON te.event_entity_id = e.id
        WHERE te.saga_id = %d
        GROUP BY e.entity_type
    ", $saga_id));

    wp_send_json_success([
        'total_events' => (int) $stats->total_events,
        'earliest_event' => (int) $stats->earliest_event,
        'latest_event' => (int) $stats->latest_event,
        'avg_description_length' => round($stats->avg_description_length, 2),
        'type_distribution' => $type_distribution,
        'time_span_years' => round(($stats->latest_event - $stats->earliest_event) / 31536000, 2)
    ]);
}
add_action('wp_ajax_saga_get_timeline_stats', 'saga_ajax_get_timeline_stats');
add_action('wp_ajax_nopriv_saga_get_timeline_stats', 'saga_ajax_get_timeline_stats');

/**
 * Performance monitoring
 *
 * Logs slow queries for optimization.
 *
 * @param float $start_time Query start time
 * @param string $query_type Query type identifier
 */
function saga_monitor_timeline_performance($start_time, $query_type) {
    $duration = (microtime(true) - $start_time) * 1000; // Convert to ms

    if ($duration > 50) { // Target: sub-50ms
        error_log(sprintf(
            '[SAGA][PERF] Slow timeline query (%s): %.2fms',
            $query_type,
            $duration
        ));
    }

    // Track metric
    if (function_exists('saga_track_metric')) {
        saga_track_metric('timeline_query_duration', $duration);
    }
}

/**
 * Rate limiting for timeline requests
 *
 * Prevents abuse of timeline AJAX endpoint.
 *
 * @param int $user_id User ID (0 for guests)
 * @return bool True if request allowed, false if rate limited
 */
function saga_check_timeline_rate_limit($user_id = 0) {
    $user_id = $user_id ?: (is_user_logged_in() ? get_current_user_id() : 0);

    // More generous limit for logged-in users
    $limit = $user_id ? 30 : 10; // Requests per minute

    $key = "saga_timeline_rate_{$user_id}_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $count = get_transient($key);

    if ($count === false) {
        set_transient($key, 1, MINUTE_IN_SECONDS);
        return true;
    }

    if ($count >= $limit) {
        return false;
    }

    set_transient($key, $count + 1, MINUTE_IN_SECONDS);
    return true;
}

/**
 * Apply rate limiting to timeline AJAX
 */
function saga_timeline_rate_limit_check() {
    if (!saga_check_timeline_rate_limit()) {
        wp_send_json_error([
            'message' => __('Rate limit exceeded. Please try again later.', 'saga-manager'),
            'code' => 'rate_limit_exceeded'
        ], 429);
    }
}
add_action('wp_ajax_saga_get_timeline_data', 'saga_timeline_rate_limit_check', 1);
add_action('wp_ajax_nopriv_saga_get_timeline_data', 'saga_timeline_rate_limit_check', 1);

/**
 * Error logging for timeline failures
 *
 * @param string $message Error message
 * @param array $context Additional context
 */
function saga_log_timeline_error($message, $context = []) {
    error_log(sprintf(
        '[SAGA][TIMELINE][ERROR] %s | Context: %s',
        $message,
        json_encode($context)
    ));
}
