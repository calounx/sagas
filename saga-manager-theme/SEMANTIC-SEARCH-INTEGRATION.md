# Semantic Search Integration Guide

## Quick Start

Add this line to your `functions.php`:

```php
require_once get_template_directory() . '/inc/search-init.php';
```

That's it! The search system will auto-initialize.

## Files Created

### JavaScript (2 files)
```
assets/js/semantic-search.js          # Main search engine (32KB)
assets/js/search-autocomplete.js      # Autocomplete system (18KB)
```

### CSS (1 file)
```
assets/css/semantic-search.css        # Complete styling (25KB)
```

### PHP Backend (4 files)
```
inc/search/semantic-scorer.php        # Relevance algorithm
inc/ajax/search-handler.php           # AJAX endpoints
inc/widgets/search-widget.php         # Sidebar widget
inc/shortcodes/search-shortcode.php   # Shortcode handler
inc/search-init.php                   # Initialization
```

### Templates (4 files)
```
template-parts/search-form.php        # Reusable search form
template-parts/search-results.php     # Results container
template-parts/search-results-list.php # Result items
page-templates/search-page.php        # Full page template
```

### Documentation (2 files)
```
SEMANTIC-SEARCH.md                    # Full documentation
SEMANTIC-SEARCH-INTEGRATION.md        # This file
```

**Total: 15 files**

## Auto-Configuration

The `search-init.php` file automatically:

1. ✅ Loads all required PHP classes
2. ✅ Registers and enqueues assets
3. ✅ Localizes JavaScript with AJAX config
4. ✅ Creates search page on theme activation
5. ✅ Registers widget and shortcode
6. ✅ Adds dashboard analytics widget
7. ✅ Sets up cache invalidation
8. ✅ Modifies WordPress search query

## Manual Integration (Optional)

If you need more control, integrate manually:

### 1. In functions.php

```php
// Load semantic search
require_once get_template_directory() . '/inc/search-init.php';

// Optional: Add search to header
add_action('wp_body_open', function() {
    if (!is_search() && !is_page_template('page-templates/search-page.php')) {
        \SagaManager\Search\add_header_search_form();
    }
});
```

### 2. In header.php (Optional)

Add quick search to navigation:

```php
<nav class="main-navigation">
    <?php wp_nav_menu(['theme_location' => 'primary']); ?>

    <div class="header-search-wrapper">
        <?php
        get_template_part('template-parts/search-form', null, [
            'placeholder' => __('Quick search...', 'saga-manager'),
            'show_filters' => false,
            'show_results' => true,
            'compact' => true,
        ]);
        ?>
    </div>
</nav>
```

### 3. In sidebar.php (Optional)

```php
<aside class="sidebar">
    <?php
    // Search widget will automatically appear if added via Widgets screen
    dynamic_sidebar('sidebar-1');
    ?>
</aside>
```

### 4. In single.php or page.php (Optional)

Add entity search meta box in content:

```php
<article>
    <?php the_content(); ?>

    <?php if (is_singular('saga_entity')): ?>
        <div class="related-entity-search">
            <h3><?php esc_html_e('Find Related Entities', 'saga-manager'); ?></h3>
            <?php
            get_template_part('template-parts/search-form', null, [
                'show_filters' => true,
                'max_results' => 5,
            ]);
            ?>
        </div>
    <?php endif; ?>
</article>
```

## Usage Examples

### Example 1: Basic Shortcode in Content

```php
<!-- In post/page content -->
[saga_search]
```

### Example 2: Shortcode with Attributes

```php
<!-- Search only characters and locations -->
[saga_search types="character,location" max_results="10" show_filters="false"]
```

### Example 3: Template Function

```php
<?php
// In any template file
get_template_part('template-parts/search-form', null, [
    'placeholder' => __('Search your saga...', 'saga-manager'),
    'show_filters' => true,
    'show_voice' => true,
    'max_results' => 20,
    'saga_id' => get_the_ID(), // Current saga only
]);
?>
```

### Example 4: Widget Configuration

1. Go to **Appearance → Widgets**
2. Drag **Saga Search** to any widget area
3. Configure:
   - **Title**: "Find Entities"
   - **Placeholder**: "Search saga..."
   - **Show Filters**: Yes
   - **Voice Search**: Yes
   - **Compact Mode**: No

### Example 5: Custom Search Page

Create `page-custom-search.php`:

```php
<?php
/**
 * Template Name: Custom Entity Search
 */

get_header();
?>

<main class="custom-search-page">
    <h1><?php esc_html_e('Advanced Entity Search', 'saga-manager'); ?></h1>

    <?php
    get_template_part('template-parts/search-form', null, [
        'placeholder' => __('Enter your search query...', 'saga-manager'),
        'show_filters' => true,
        'show_voice' => true,
        'show_results' => true,
        'show_saved_searches' => true,
        'max_results' => 100,
    ]);
    ?>
</main>

<?php
get_footer();
```

## Testing

### 1. Test Basic Search

```javascript
// Browser console
jQuery('.saga-search-input').val('jedi temple').trigger('input');
```

### 2. Test AJAX Endpoint

```javascript
// Browser console
jQuery.post(ajaxurl, {
    action: 'saga_semantic_search',
    nonce: sagaSearchData.nonce,
    query: 'test search',
    filters: {},
    sort: 'relevance',
    limit: 10,
    offset: 0
}, function(response) {
    console.log(response);
});
```

### 3. Test Autocomplete

```javascript
// Browser console
jQuery.post(ajaxurl, {
    action: 'saga_autocomplete',
    nonce: sagaSearchData.nonce,
    query: 'jedi',
    max_suggestions: 10
}, function(response) {
    console.log(response);
});
```

### 4. Test Relevance Scoring

```php
// In functions.php or custom plugin
add_action('init', function() {
    $scorer = new \SagaManager\Search\SemanticScorer();

    $entity = [
        'canonical_name' => 'Jedi Temple',
        'entity_type' => 'location',
        'importance_score' => 90,
        'description' => 'The main Jedi Temple on Coruscant',
    ];

    $score = $scorer->score($entity, 'jedi temple', [
        'original' => 'jedi temple',
        'terms' => ['jedi', 'temple'],
    ]);

    error_log('Relevance Score: ' . $score);
});
```

## Customization

### Change Color Scheme

Add to your theme's CSS:

```css
:root {
    --color-primary: #0073aa;
    --color-primary-alpha: rgba(0, 115, 170, 0.1);
    --color-accent: #d63638;
    --color-accent-alpha: rgba(214, 54, 56, 0.1);
    --color-highlight: #fff3cd;
}
```

### Add Custom Synonyms

```php
add_action('init', function() {
    $scorer = new \SagaManager\Search\SemanticScorer();

    // Star Wars specific
    $scorer->addSynonym('lightsaber', ['blade', 'laser sword']);
    $scorer->addSynonym('force', ['power', 'energy']);
    $scorer->addSynonym('ship', ['vessel', 'craft', 'starship']);
});
```

### Modify Result Display

Edit `template-parts/search-results-list.php` to customize how results appear.

### Change Cache Duration

```php
add_filter('saga_search_cache_duration', function($duration) {
    return 10 * MINUTE_IN_SECONDS; // 10 minutes instead of 5
});
```

### Disable Voice Search

```php
add_filter('saga_search_enable_voice', '__return_false');
```

## Performance Tips

### 1. Enable Object Cache

Install Redis or Memcached:

```php
// wp-config.php
define('WP_CACHE', true);
```

### 2. Add Database Indexes

Run this SQL once:

```sql
CREATE INDEX idx_search_name ON wp_saga_entities(canonical_name(50));
CREATE INDEX idx_search_importance ON wp_saga_entities(importance_score DESC);
CREATE INDEX idx_search_composite ON wp_saga_entities(saga_id, entity_type, importance_score);
```

### 3. Optimize Images

```php
add_filter('saga_search_thumbnail_size', function() {
    return 'thumbnail'; // or 'medium', 'small'
});
```

### 4. Reduce Result Limit

```php
add_filter('saga_search_default_limit', function() {
    return 20; // Instead of 50
});
```

## Troubleshooting

### Issue: "Search not working"

**Solution:**
```php
// Check if assets loaded
add_action('wp_footer', function() {
    if (wp_script_is('saga-semantic-search', 'enqueued')) {
        echo '<!-- ✓ Search JS loaded -->';
    } else {
        echo '<!-- ✗ Search JS NOT loaded -->';
    }
});
```

### Issue: "AJAX returning 400 error"

**Solution:**
```php
// Enable WP_DEBUG
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check error log
tail -f wp-content/debug.log
```

### Issue: "No results found"

**Solution:**
```php
// Verify entities exist
global $wpdb;
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}saga_entities");
error_log("Total entities: " . $count);
```

### Issue: "Autocomplete not appearing"

**Solution:**
```javascript
// Check jQuery and dependencies
console.log('jQuery:', typeof jQuery);
console.log('Search engine:', typeof sagaSearch);
console.log('Autocomplete:', typeof jQuery.fn.sagaAutocomplete);
```

## Security Checklist

- ✅ Nonce verification on all AJAX requests
- ✅ Input sanitization with `sanitize_text_field()`
- ✅ SQL injection prevention with `$wpdb->prepare()`
- ✅ Capability checks for edit links
- ✅ XSS prevention with `esc_html()`, `esc_attr()`
- ✅ CSRF protection with nonces
- ✅ Rate limiting on analytics tracking

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Basic Search | ✓ | ✓ | ✓ | ✓ |
| Autocomplete | ✓ | ✓ | ✓ | ✓ |
| Voice Search | ✓ | ✗ | ✗ | ✓ |
| Keyboard Nav | ✓ | ✓ | ✓ | ✓ |
| Filters | ✓ | ✓ | ✓ | ✓ |

## Accessibility Compliance

- ✓ WCAG 2.1 AA compliant
- ✓ Keyboard navigable
- ✓ Screen reader compatible
- ✓ ARIA labels and roles
- ✓ Focus indicators
- ✓ Color contrast 4.5:1+
- ✓ Reduced motion support

## Support & Resources

- **Documentation**: `SEMANTIC-SEARCH.md`
- **Integration Guide**: This file
- **Issue Tracker**: GitHub Issues
- **Code Examples**: `/examples` directory (create if needed)

## Next Steps

1. ✅ Add `require_once` to functions.php
2. ✅ Test search functionality
3. ✅ Create search page
4. ✅ Add widget to sidebar
5. ✅ Customize colors/styling
6. ✅ Add custom synonyms
7. ✅ Enable analytics tracking
8. ✅ Optimize database indexes

---

**Happy Searching!** 🔍

For questions or issues, refer to `SEMANTIC-SEARCH.md` or open a GitHub issue.
