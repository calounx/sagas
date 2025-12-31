# Saga Manager Analytics System

Privacy-first entity popularity tracking with GDPR compliance and accurate metrics.

## Features

### Tracking Capabilities
- **Page Views**: Total and unique visitor tracking
- **Engagement**: Time spent on page (accurate to the second)
- **Interactions**: Bookmark and annotation counts
- **Popularity Scoring**: Weighted algorithm combining all metrics
- **Trending Detection**: Identifies trending entities based on recent activity

### Privacy & Compliance
- ✅ GDPR Compliant (90-day data retention)
- ✅ Do Not Track header support
- ✅ Anonymous visitor IDs (UUID v4)
- ✅ IP address hashing (HMAC-SHA256)
- ✅ User agent anonymization
- ✅ No PII collection

## File Structure

```
saga-manager-theme/
├── inc/
│   ├── analytics-init.php              # Main initialization
│   ├── class-saga-analytics-db.php     # Database schema & migrations
│   ├── class-saga-analytics.php        # Core tracking logic
│   ├── class-saga-popularity.php       # Popularity calculations
│   ├── ajax-handlers.php               # AJAX endpoints
│   ├── admin-analytics-dashboard.php   # Admin dashboard
│   └── cron-jobs.php                   # Background tasks
├── widgets/
│   └── class-popular-entities-widget.php # Popular entities widget
├── template-parts/
│   └── popularity-badge.php            # Badge template
├── assets/
│   ├── js/
│   │   └── view-tracker.js             # Client-side tracking
│   └── css/
│       └── popularity-indicators.css   # Badge styling
└── ANALYTICS_README.md                 # This file
```

## Database Schema

### Tables Created

1. **`wp_saga_entity_stats`**: Aggregated statistics per entity
2. **`wp_saga_view_log`**: Individual view records
3. **`wp_saga_trending_cache`**: Pre-calculated trending lists

### Storage Requirements
- ~100 bytes per view log entry
- ~200 bytes per entity stats record
- For 100K entities with 1M views: ~150MB

## Installation

### 1. Initialize Database

Add to `functions.php`:

```php
require_once get_template_directory() . '/inc/analytics-init.php';
```

### 2. Activate Theme

Tables are created automatically on theme activation.

### 3. Verify Cron Jobs

Check WP-Admin → Saga Analytics → Cron Status widget

## Usage

### Display Popularity Badge

```php
// In your template
$entity_id = get_the_ID();
include get_template_directory() . '/template-parts/popularity-badge.php';
```

### Get Entity Statistics

```php
$stats = Saga_Analytics::get_entity_stats($entity_id);

if ($stats) {
    echo "Views: " . number_format($stats['total_views']);
    echo "Score: " . number_format($stats['popularity_score'], 2);
}
```

### Get Trending Entities

```php
// Weekly trending (last 7 days)
$trending = Saga_Popularity::get_trending(10, 'weekly');

// Popular all-time
$popular = Saga_Popularity::get_popular(10);

foreach ($trending as $entity) {
    $post = get_post($entity['entity_id']);
    echo get_the_title($post) . ": " . $entity['trend_score'];
}
```

### Manual Tracking

```javascript
// Track custom action from JavaScript
window.sagaTracker.trackCustomAction('bookmark_added', {
    timestamp: Date.now()
});
```

### Shortcodes

```
[saga_trending count="5" period="weekly" show_views="yes"]
```

### REST API

```
GET /wp-json/wp/v2/saga_entity/123

Response includes:
{
  "popularity": {
    "total_views": 1234,
    "unique_views": 567,
    "popularity_score": 123.45,
    "badge_type": "trending",
    "is_trending": true
  }
}
```

## Popularity Score Algorithm

### Weights

```php
const WEIGHTS = [
    'views'         => 1.0,   // Each view = 1 point
    'unique_views'  => 2.0,   // Unique visitor = 2 points
    'bookmarks'     => 5.0,   // Bookmark = 5 points
    'annotations'   => 3.0,   // Annotation = 3 points
    'avg_time'      => 0.1,   // Per second on page
    'recency'       => 2.0,   // Recent views boost
];
```

### Calculation

```
score = (views × 1.0)
      + (unique_views × 2.0)
      + (bookmarks × 5.0)
      + (annotations × 3.0)
      + ((avg_time_seconds / 60) × 0.1)
      + (recency_factor × 200)
```

**Recency Factor:**
- Last 7 days: Linear decay from 1.0 to 0.0
- Example: 3 days ago = 0.57 × 200 = 114 bonus points

### Thresholds

- **Trending**: Score ≥ 100 in last 7 days
- **Popular**: Score ≥ 50 (all-time)
- **Rising**: Score ≥ 20 with recent growth

## Cron Jobs

### Hourly: Score Updates
- Updates 100 entities per run
- Prioritizes entities with recent activity
- Average execution: 2-5 seconds

### Daily: Cleanup
- Deletes logs older than 90 days
- Optimizes database tables
- Clears stale caches

### Weekly: Trending Refresh
- Pre-calculates trending lists
- Updates all time periods (hourly, daily, weekly)

### Manual Trigger

```php
// Admin only
do_action('saga_hourly_score_update');
```

## Admin Dashboard

**Location:** WP-Admin → Analytics

### Features

1. **Overview Cards**
   - Total views
   - Unique visitors
   - Trending count
   - 24h activity

2. **Entity Lists**
   - Trending this week
   - Most popular all-time
   - Detailed statistics table

3. **Maintenance Tools**
   - Recalculate all scores
   - Cleanup old logs
   - Export analytics data (CSV)

## Widget

**Widget:** Popular Saga Entities

**Options:**
- Display type (trending, popular, recent)
- Number of entities (1-20)
- Show view counts
- Show popularity badges

**Placement:** Appearance → Widgets

## Performance

### Optimization Strategies

1. **Object Caching**
   - Entity stats cached for 5 minutes
   - Trending lists cached for 15 minutes
   - Uses WordPress object cache (Redis compatible)

2. **Database Indexing**
   - Covering indexes on common queries
   - Composite indexes for JOIN queries
   - Optimized for sub-50ms response

3. **Batch Processing**
   - Score updates batched (100 entities/hour)
   - View tracking non-blocking (async)
   - Trending cache pre-calculated

4. **Query Optimization**
   - Limited to 3 JOINs maximum
   - WHERE clauses use indexed columns
   - LIMIT/OFFSET pagination

### Monitoring

Check query performance:

```php
if (WP_DEBUG) {
    global $wpdb;
    var_dump($wpdb->queries);
}
```

## Privacy Compliance

### GDPR Requirements

✅ **Data Minimization**: Only essential metrics collected
✅ **Purpose Limitation**: Analytics only, no marketing
✅ **Storage Limitation**: 90-day retention period
✅ **Anonymization**: No PII stored
✅ **User Rights**: Data export available (CSV)

### Do Not Track Support

Tracking automatically disabled if DNT header present:

```
DNT: 1 → No tracking
```

### Data Retention

**Automatic Deletion:**
- View logs > 90 days: Deleted daily
- Entity stats: Retained indefinitely (aggregated)
- Trending cache: Refreshed weekly

## Troubleshooting

### Views Not Tracking

1. Check JavaScript console for errors
2. Verify nonce is valid
3. Check Do Not Track header
4. Confirm AJAX endpoint accessible

```javascript
console.log(sagaAnalytics);
// Should show: { ajaxUrl, nonce, entityId }
```

### Scores Not Updating

1. Check WP-Cron status:
   ```php
   wp_next_scheduled('saga_hourly_score_update');
   ```

2. Manually trigger update:
   ```php
   Saga_Popularity::update_score($entity_id);
   ```

3. Verify database tables exist:
   ```sql
   SHOW TABLES LIKE 'wp_saga_entity_stats';
   ```

### Slow Performance

1. Check query execution time:
   ```php
   $start = microtime(true);
   Saga_Popularity::get_trending(10);
   echo (microtime(true) - $start) * 1000 . "ms";
   ```

2. Verify indexes:
   ```sql
   SHOW INDEX FROM wp_saga_entity_stats;
   ```

3. Enable object caching (Redis)

## API Reference

### Saga_Analytics

```php
// Track view
Saga_Analytics::track_view(int $entity_id, string $visitor_id): bool

// Track duration
Saga_Analytics::track_duration(int $entity_id, string $visitor_id, int $duration): bool

// Update counts
Saga_Analytics::update_bookmark_count(int $entity_id, int $delta): void
Saga_Analytics::update_annotation_count(int $entity_id, int $delta): void

// Get stats
Saga_Analytics::get_entity_stats(int $entity_id): ?array
```

### Saga_Popularity

```php
// Calculate score
Saga_Popularity::calculate_score(int $entity_id): float

// Update score
Saga_Popularity::update_score(int $entity_id): float

// Get entities
Saga_Popularity::get_trending(int $limit, string $period): array
Saga_Popularity::get_popular(int $limit): array

// Check status
Saga_Popularity::is_trending(int $entity_id): bool
Saga_Popularity::is_popular(int $entity_id): bool
Saga_Popularity::get_badge_type(int $entity_id): ?string

// Format
Saga_Popularity::get_formatted_views(int $entity_id): string
```

### Saga_Analytics_DB

```php
// Setup
Saga_Analytics_DB::create_tables(): void
Saga_Analytics_DB::drop_tables(): void

// Maintenance
Saga_Analytics_DB::cleanup_old_logs(): int
```

## Hooks & Filters

### Actions

```php
// After view tracked
do_action('saga:view-tracked', ['entityId' => 123]);

// After duration tracked
do_action('saga:duration-tracked', ['entityId' => 123, 'duration' => 60]);

// Custom actions
do_action('saga_track_custom_action', $action_name, $metadata);
```

### Filters

```php
// Modify popularity weights
add_filter('saga_popularity_weights', function($weights) {
    $weights['bookmarks'] = 10.0; // Double bookmark value
    return $weights;
});

// Modify trending threshold
add_filter('saga_trending_threshold', function($threshold) {
    return 200.0; // Higher bar for trending
});
```

## Security

### Implemented Protections

1. **Nonce Verification**: All AJAX requests verified
2. **Capability Checks**: Admin functions restricted
3. **SQL Injection Prevention**: `$wpdb->prepare()` everywhere
4. **XSS Prevention**: `esc_html()`, `esc_attr()`, `esc_url()`
5. **Input Sanitization**: `sanitize_text_field()`, `absint()`
6. **Rate Limiting**: 1 view per entity per hour per visitor

### Security Checklist

- ✅ No direct database queries without `prepare()`
- ✅ All user input sanitized
- ✅ All output escaped
- ✅ Nonces on all forms/AJAX
- ✅ Capability checks on admin functions
- ✅ No eval() or dynamic code execution

## Testing

### Manual Testing

```php
// Test view tracking
$visitor_id = wp_generate_uuid4();
$success = Saga_Analytics::track_view(123, $visitor_id);
var_dump($success); // Should be true

// Test score calculation
$score = Saga_Popularity::calculate_score(123);
var_dump($score); // Should be float

// Test trending
$trending = Saga_Popularity::get_trending(5);
var_dump(count($trending)); // Should be <= 5
```

### Load Testing

Simulate 1000 views:

```php
for ($i = 0; $i < 1000; $i++) {
    $visitor_id = wp_generate_uuid4();
    Saga_Analytics::track_view(123, $visitor_id);
}

// Check stats
$stats = Saga_Analytics::get_entity_stats(123);
echo "Total views: " . $stats['total_views']; // Should be ~1000
echo "Unique views: " . $stats['unique_views']; // Should be ~1000
```

## Support

For issues, feature requests, or questions:
- Check WP-Admin → Saga Analytics dashboard
- Enable WP_DEBUG for detailed logging
- Review error logs at `/wp-content/debug.log`

## License

Part of Saga Manager Theme. All rights reserved.
