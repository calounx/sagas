# Analytics System Integration Guide

## Quick Start (5 Minutes)

### Step 1: Include Analytics in functions.php

Add this line to your theme's `functions.php`:

```php
// Load analytics system
require_once get_template_directory() . '/inc/analytics-init.php';
```

### Step 2: Activate Theme

The analytics system will automatically:
- ✅ Create database tables
- ✅ Schedule cron jobs
- ✅ Register widgets
- ✅ Set up AJAX endpoints

### Step 3: Verify Installation

1. Go to **WP-Admin → Analytics**
2. Check that the dashboard loads
3. Verify cron jobs are scheduled (Dashboard Widget)

Done! The system is now tracking entity views.

## Template Integration

### Display Popularity Badge

Add to your entity template (e.g., `single-saga_entity.php`):

```php
<?php if (is_singular('saga_entity')) : ?>
    <div class="entity-meta">
        <?php saga_the_popularity_badge(); ?>
    </div>
<?php endif; ?>
```

### Display in Entity Cards

Add to `template-parts/entity-card.php`:

```php
<article class="entity-card">
    <h3><?php the_title(); ?></h3>

    <?php
    // Compact badge in card view
    saga_the_popularity_badge(get_the_ID(), true);
    ?>
</article>
```

### Show View Count Only

```php
<div class="entity-stats">
    <?php saga_the_view_count(); ?>
</div>
```

## Widget Setup

### Add Widget to Sidebar

1. Go to **Appearance → Widgets**
2. Drag **Popular Saga Entities** to your sidebar
3. Configure options:
   - **Title:** "Trending Entities"
   - **Display Type:** Trending
   - **Count:** 5
   - **Show Views:** Yes
   - **Show Badges:** Yes

### Widget in Template

```php
<?php if (is_active_sidebar('sidebar-1')) : ?>
    <aside class="sidebar">
        <?php dynamic_sidebar('sidebar-1'); ?>
    </aside>
<?php endif; ?>
```

## Shortcode Usage

### Basic Trending List

```
[saga_trending]
```

### Customized List

```
[saga_trending count="10" period="daily" show_views="yes" show_badges="no"]
```

**Parameters:**
- `count`: Number of entities (1-20)
- `period`: hourly, daily, weekly
- `show_views`: yes, no
- `show_badges`: yes, no

## Query by Popularity

### Get Popular Entities

```php
$popular_query = saga_query_popular_entities([
    'posts_per_page' => 10,
]);

if ($popular_query->have_posts()) :
    while ($popular_query->have_posts()) : $popular_query->the_post();
        // Display entity
    endwhile;
    wp_reset_postdata();
endif;
```

### Get Trending Entities

```php
$trending = Saga_Popularity::get_trending(10, 'weekly');

foreach ($trending as $entity) {
    $post = get_post($entity['entity_id']);
    echo get_the_title($post);
    echo Saga_Popularity::get_formatted_views($entity['entity_id']);
}
```

### Custom WP_Query with Popularity

```php
$query = new WP_Query([
    'post_type' => 'saga_entity',
    'orderby_popularity' => true,
    'posts_per_page' => 5,
    'meta_query' => [
        [
            'key' => '_saga_entity_type',
            'value' => 'character',
        ],
    ],
]);
```

## Track Custom Actions

### Bookmark Tracking

```php
// When user bookmarks an entity
add_action('user_bookmark_added', function($entity_id, $user_id) {
    saga_track_bookmark_added($entity_id, $user_id);
}, 10, 2);

// When bookmark is removed
add_action('user_bookmark_removed', function($entity_id, $user_id) {
    saga_track_bookmark_removed($entity_id, $user_id);
}, 10, 2);
```

### Annotation Tracking

```php
// When user adds annotation
add_action('annotation_created', function($annotation_id) {
    $entity_id = get_post_meta($annotation_id, '_entity_id', true);
    if ($entity_id) {
        saga_track_annotation_added($entity_id, get_current_user_id());
    }
});

// When annotation deleted
add_action('annotation_deleted', function($annotation_id) {
    $entity_id = get_post_meta($annotation_id, '_entity_id', true);
    if ($entity_id) {
        saga_track_annotation_removed($entity_id, get_current_user_id());
    }
});
```

## Conditional Display

### Show Badge Only if Trending

```php
<?php if (saga_is_trending()) : ?>
    <div class="trending-badge">
        🔥 Trending Now!
    </div>
<?php endif; ?>
```

### Different Content for Popular Entities

```php
<?php if (saga_is_popular()) : ?>
    <div class="popular-highlight">
        <strong>⭐ Popular Entity</strong>
        <p>This entity has <?php echo saga_get_view_count(); ?> views!</p>
    </div>
<?php endif; ?>
```

### Hide Content for Low Popularity

```php
<?php
$score = saga_get_popularity_score();
if ($score >= 20) :
?>
    <div class="featured-content">
        <!-- Special content for popular entities -->
    </div>
<?php endif; ?>
```

## Styling Integration

### Enqueue Widget Styles

Add to `functions.php`:

```php
function saga_enqueue_widget_styles() {
    if (is_active_widget(false, false, 'saga_popular_entities')) {
        wp_enqueue_style(
            'saga-widget-styles',
            get_template_directory_uri() . '/assets/css/popular-entities-widget.css',
            [],
            wp_get_theme()->get('Version')
        );
    }
}
add_action('wp_enqueue_scripts', 'saga_enqueue_widget_styles');
```

### Custom Badge Styling

Override in your theme's CSS:

```css
.popularity-badge--trending {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
}

.popularity-indicator {
    background: var(--your-surface-color);
    color: var(--your-text-color);
}
```

## Admin Customization

### Add Analytics to Custom Post Type Column

```php
function saga_add_analytics_column($columns) {
    $columns['popularity'] = 'Popularity';
    return $columns;
}
add_filter('manage_saga_entity_posts_columns', 'saga_add_analytics_column');

function saga_display_analytics_column($column, $post_id) {
    if ($column === 'popularity') {
        $score = saga_get_popularity_score($post_id);
        $views = saga_get_view_count($post_id);

        echo '<strong>' . number_format($score, 2) . '</strong><br>';
        echo '<small>' . $views . ' views</small>';
    }
}
add_action('manage_saga_entity_posts_custom_column', 'saga_display_analytics_column', 10, 2);
```

### Make Column Sortable

```php
function saga_make_popularity_sortable($columns) {
    $columns['popularity'] = 'popularity_score';
    return $columns;
}
add_filter('manage_edit-saga_entity_sortable_columns', 'saga_make_popularity_sortable');
```

## REST API Integration

### Fetch Entity with Popularity

```javascript
fetch('/wp-json/wp/v2/saga_entity/123')
    .then(response => response.json())
    .then(entity => {
        console.log('Views:', entity.popularity.total_views);
        console.log('Score:', entity.popularity.popularity_score);
        console.log('Badge:', entity.popularity.badge_type);
    });
```

### Filter by Trending

```javascript
fetch('/wp-json/saga/v1/trending?limit=10&period=weekly')
    .then(response => response.json())
    .then(data => {
        data.entities.forEach(entity => {
            console.log(entity.title, entity.views);
        });
    });
```

## Performance Optimization

### Enable Object Cache

Add to `wp-config.php`:

```php
// Enable Redis object cache (requires Redis and plugin)
define('WP_CACHE', true);
```

### Disable Tracking for Admins (Optional)

Add to `functions.php`:

```php
add_filter('saga_track_view', function($should_track, $entity_id) {
    if (current_user_can('manage_options')) {
        return false; // Don't track admin views
    }
    return $should_track;
}, 10, 2);
```

### Increase Score Update Frequency

Modify cron schedule in `functions.php`:

```php
// Update scores every 15 minutes instead of hourly
add_filter('cron_schedules', function($schedules) {
    $schedules['fifteen_minutes'] = [
        'interval' => 900,
        'display' => 'Every 15 Minutes',
    ];
    return $schedules;
});

// Change the schedule
wp_clear_scheduled_hook('saga_hourly_score_update');
wp_schedule_event(time(), 'fifteen_minutes', 'saga_hourly_score_update');
```

## Testing

### Test View Tracking

```php
// In a test file or wp-cli
$visitor_id = wp_generate_uuid4();
$success = Saga_Analytics::track_view(123, $visitor_id);
var_dump($success); // Should be true

$stats = Saga_Analytics::get_entity_stats(123);
var_dump($stats['total_views']); // Should be 1
```

### Test Score Calculation

```php
$score = Saga_Popularity::calculate_score(123);
echo "Score: $score\n";

$is_trending = Saga_Popularity::is_trending(123);
echo "Trending: " . ($is_trending ? 'Yes' : 'No') . "\n";
```

### Manual Cron Trigger

```php
// Trigger score update manually
do_action('saga_hourly_score_update');
```

## Troubleshooting

### Views Not Tracking

**Check JavaScript console:**
```javascript
console.log(sagaAnalytics);
// Should output: { ajaxUrl: "...", nonce: "...", entityId: 123 }
```

**Verify AJAX endpoint:**
```bash
curl -X POST https://yoursite.com/wp-admin/admin-ajax.php \
  -d "action=saga_track_view&entity_id=123&visitor_id=test-uuid&nonce=YOUR_NONCE"
```

### Cron Jobs Not Running

**Check schedule:**
```php
wp_next_scheduled('saga_hourly_score_update');
// Should return timestamp
```

**Manual trigger:**
```
WP-Admin → Tools → Cron Events (with WP Crontrol plugin)
```

### Slow Dashboard

**Enable caching:**
```php
update_option('saga_analytics_cache_enabled', true);
```

**Reduce query load:**
```php
// Increase cache TTL
add_filter('saga_stats_cache_ttl', function() {
    return 900; // 15 minutes instead of 5
});
```

## Migration from Existing Analytics

### Import View Counts

```php
function saga_import_existing_views() {
    $entities = get_posts([
        'post_type' => 'saga_entity',
        'posts_per_page' => -1,
    ]);

    foreach ($entities as $entity) {
        // Get existing view count from meta
        $old_views = get_post_meta($entity->ID, '_views_count', true);

        if ($old_views) {
            global $wpdb;
            $table = $wpdb->prefix . 'saga_entity_stats';

            $wpdb->insert($table, [
                'entity_id' => $entity->ID,
                'total_views' => $old_views,
                'unique_views' => $old_views, // Estimate
            ]);

            // Update score
            Saga_Popularity::update_score($entity->ID);
        }
    }
}

// Run once
saga_import_existing_views();
```

## Best Practices

### 1. Always Use Template Functions

✅ Good:
```php
saga_the_popularity_badge();
```

❌ Avoid:
```php
$stats = Saga_Analytics::get_entity_stats(get_the_ID());
// Manual HTML generation
```

### 2. Check Before Display

✅ Good:
```php
if (saga_is_trending()) {
    saga_the_popularity_badge();
}
```

❌ Avoid:
```php
saga_the_popularity_badge(); // Always displays even with no data
```

### 3. Use Caching

✅ Good:
```php
$trending = wp_cache_get('my_trending_list');
if (!$trending) {
    $trending = Saga_Popularity::get_trending(10);
    wp_cache_set('my_trending_list', $trending, '', 900);
}
```

### 4. Escape Output

✅ Good:
```php
echo esc_html(saga_get_view_count());
```

❌ Avoid:
```php
echo saga_get_view_count(); // Template functions already escape, but be aware
```

## Support & Resources

- **Full Documentation:** `/ANALYTICS_README.md`
- **Implementation Details:** `/ANALYTICS_IMPLEMENTATION.md`
- **Admin Dashboard:** WP-Admin → Analytics
- **Error Logs:** Enable `WP_DEBUG` in `wp-config.php`

## Next Steps

1. ✅ Include analytics in `functions.php`
2. ✅ Activate theme
3. ✅ Add widget to sidebar
4. ✅ Add badges to templates
5. ✅ Test on staging site
6. ✅ Monitor dashboard
7. ✅ Deploy to production

---

**Need Help?** Check error logs and dashboard for diagnostics.
