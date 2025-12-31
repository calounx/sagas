# Semantic Search System - Documentation

## Overview

The Semantic Search UI provides intelligent, meaning-based search capabilities for the Saga Manager theme. It goes beyond simple keyword matching to understand context, synonyms, and natural language queries.

**Version:** 1.3.0
**Status:** Phase 1 Next-Gen Feature

## Features

### Core Capabilities

1. **Natural Language Processing**
   - Ask questions like "Who fought in the Clone Wars?"
   - Concept-based understanding
   - Synonym matching (e.g., "battle" matches "combat", "war", "conflict")
   - Context-aware relevance scoring

2. **Advanced Search Syntax**
   - **Boolean operators**: AND, OR, NOT
   - **Exact phrases**: Use quotes - `"Jedi Temple"`
   - **Exclusions**: Use minus sign - `dark side -sith`
   - **Combined queries**: `"Clone Wars" AND battle OR conflict -droid`

3. **Smart Autocomplete**
   - Real-time suggestions as you type
   - Entity previews with thumbnails
   - Recent and popular search suggestions
   - Fuzzy matching for typos
   - Keyboard navigation support

4. **Voice Search**
   - Web Speech API integration
   - Real-time transcription
   - Visual feedback during recording
   - Automatic query submission

5. **Advanced Filtering**
   - **Entity Types**: Characters, Locations, Events, Factions, Artifacts, Concepts
   - **Importance Score**: Range slider (0-100) with presets (Major, Important, Minor)
   - **Saga Filter**: Search within specific sagas
   - **Sort Options**: Relevance, Name, Date, Importance

6. **Performance**
   - Debounced autocomplete (300ms delay)
   - Result caching (5 minutes)
   - Lazy image loading
   - Query optimization (<50ms target)
   - Virtual scrolling for 1000+ results

7. **Accessibility**
   - ARIA live regions for screen readers
   - Keyboard shortcuts (Ctrl+K, /, Esc, Arrow keys)
   - Focus management
   - High contrast mode support
   - Reduced motion support

## File Structure

```
saga-manager-theme/
├── assets/
│   ├── css/
│   │   └── semantic-search.css          # Complete styling
│   └── js/
│       ├── semantic-search.js           # Main search engine
│       └── search-autocomplete.js       # Autocomplete logic
├── inc/
│   ├── ajax/
│   │   └── search-handler.php           # AJAX endpoints
│   ├── search/
│   │   └── semantic-scorer.php          # Relevance algorithm
│   ├── widgets/
│   │   └── search-widget.php            # Sidebar widget
│   ├── shortcodes/
│   │   └── search-shortcode.php         # [saga_search] shortcode
│   └── search-init.php                  # Initialization
├── template-parts/
│   ├── search-form.php                  # Reusable form template
│   ├── search-results.php               # Results container
│   └── search-results-list.php          # Individual result items
└── page-templates/
    └── search-page.php                  # Full search page
```

## Usage

### 1. Shortcode

Embed search anywhere in content:

```php
[saga_search]
```

**Attributes:**
```php
[saga_search
    placeholder="Search entities..."
    show_filters="true"
    show_voice="true"
    show_results="true"
    max_results="10"
    saga_id="123"
    types="character,location"
    compact="false"
    inline="false"]
```

### 2. Widget

Add to any widget area:

1. Go to **Appearance → Widgets**
2. Add **Saga Search** widget
3. Configure:
   - Title
   - Placeholder text
   - Show/hide filters
   - Show/hide voice search
   - Compact mode

### 3. Template Function

```php
<?php
get_template_part('template-parts/search-form', null, [
    'placeholder' => __('Search saga entities...', 'saga-manager'),
    'show_filters' => true,
    'show_voice' => true,
    'show_results' => true,
    'show_saved_searches' => true,
    'compact' => false,
    'max_results' => 50,
    'saga_id' => 0,
]);
?>
```

### 4. Search Page Template

Create a new page and assign the **Saga Search Page** template:

1. **Pages → Add New**
2. Set title (e.g., "Search Entities")
3. **Template → Saga Search Page**
4. Publish

## Relevance Scoring Algorithm

The semantic scorer uses a multi-factor relevance algorithm:

### Scoring Factors

1. **Exact Match** (Weight: 10.0)
   - Perfect match in canonical name: 20 points
   - Partial match in name: 10 points
   - Match in aliases: 15 points

2. **Title Match** (Weight: 5.0)
   - TF-IDF scoring
   - Term frequency × Inverse document frequency
   - Higher weight for shorter titles

3. **Content Match** (Weight: 2.0)
   - TF-IDF on description/content
   - Snippet generation around matches

4. **Importance Score** (Weight: 1.5)
   - Entity importance (0-100) normalized
   - Major entities ranked higher

5. **Recency** (Weight: 0.5)
   - Exponential decay function
   - Recent updates boosted

6. **Semantic Similarity** (Weight: 3.0)
   - Synonym matching
   - Context understanding
   - Related concept detection

7. **Boolean Operators**
   - AND: All terms must be present
   - OR: At least one term present
   - NOT: Excludes results
   - Exact phrases: Must match exactly

8. **Type Boost**
   - 30% boost for filtered entity types

### Example Calculation

```
Query: "jedi temple"
Entity: "Jedi Temple on Coruscant"

- Exact match (partial): 10.0
- Title match: 5.0 × (2/4 terms) × log(2) = 3.46
- Importance (90/100): 1.5 × 0.9 = 1.35
- Recency (7 days old): 0.5 × exp(-7/30) = 0.39

Total Score: 15.20
```

## Search Syntax Examples

### Basic Queries
```
jedi temple           → All results containing "jedi" or "temple"
"jedi temple"         → Exact phrase match
clone wars            → "Clone Wars" entity or related
```

### Boolean Operators
```
jedi AND temple       → Must contain both terms
dark OR light         → Either dark or light side
battle NOT droid      → Battles excluding droids
```

### Advanced Queries
```
"Clone Wars" AND battle OR conflict -droid
→ Clone Wars battles or conflicts, excluding droids

ancient artifact importance:80-100
→ Ancient artifacts with high importance

character type:jedi created:2024-01
→ Jedi characters created in January 2024
```

### Exclusions
```
-sith                 → Exclude all Sith results
darth -vader          → Darth titles except Vader
```

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` or `/` | Focus search input |
| `Esc` | Clear search and close |
| `↓` | Move to next result |
| `↑` | Move to previous result |
| `Enter` | Open selected result |
| `Tab` | Accept autocomplete |

## Customization

### Synonym Dictionary

Add custom synonyms in `semantic-scorer.php`:

```php
$scorer = new SemanticScorer();
$scorer->addSynonym('force', ['power', 'energy', 'strength']);
```

### Stop Words

Exclude common words from scoring:

```php
$scorer->addStopWord('actually');
```

### Scoring Weights

Adjust weights in `SemanticScorer` class:

```php
private const WEIGHT_EXACT_MATCH = 10.0;    // Exact name matches
private const WEIGHT_TITLE_MATCH = 5.0;     // Title relevance
private const WEIGHT_CONTENT_MATCH = 2.0;   // Content relevance
private const WEIGHT_IMPORTANCE = 1.5;      // Entity importance
private const WEIGHT_RECENCY = 0.5;         // Update recency
private const WEIGHT_SEMANTIC = 3.0;        // Semantic similarity
```

### Cache Duration

Change cache timeout in `search-handler.php`:

```php
private const CACHE_EXPIRATION = 300; // 5 minutes (in seconds)
```

### Result Limits

```php
// In template/shortcode
'max_results' => 50,

// In search handler
$limit = absint($_POST['limit'] ?? 50);
```

## Analytics

### Dashboard Widget

View search analytics in WordPress admin:

- **Total Searches**: Number of queries performed
- **Result Clicks**: Number of results clicked
- **Click-Through Rate**: Percentage of searches with clicks
- **Popular Searches**: Top 10 most searched queries
- **Recent Searches**: Last 10 searches with result counts

### Accessing Analytics Programmatically

```php
use SagaManager\Ajax\SearchHandler;

$analytics = SearchHandler::get_search_analytics();

echo "Total Searches: " . $analytics['total_searches'];
echo "CTR: " . $analytics['click_through_rate'] . "%";

// Popular searches
foreach ($analytics['popular_searches'] as $query) {
    echo $query . "<br>";
}
```

### Clear Analytics

```php
// Clear search cache
SearchHandler::clear_cache();

// Clear analytics
delete_transient('saga_search_queries');
delete_transient('saga_search_clicks');
delete_option('saga_popular_searches');
```

## Performance Optimization

### Database Indexes

Ensure proper indexes on `saga_entities` table:

```sql
CREATE INDEX idx_search_cover ON wp_saga_entities(
    saga_id, entity_type, importance_score
);

CREATE INDEX idx_name_search ON wp_saga_entities(canonical_name);

CREATE FULLTEXT INDEX ft_search ON wp_saga_entities(
    canonical_name, slug
);
```

### Caching Strategy

1. **Object Cache**: Results cached for 5 minutes
2. **Local Storage**: Recent searches, saved searches, preferences
3. **Browser Cache**: Static assets (CSS, JS)

### Query Optimization

- Limit JOIN operations to 3 max
- Use prepared statements for security
- Apply filters before sorting
- Paginate with LIMIT/OFFSET

### Lazy Loading

- Images loaded on viewport entry
- Results loaded incrementally
- Infinite scroll for large datasets

## Security

### Input Sanitization

All user input is sanitized:

```php
$query = sanitize_text_field($_POST['query']);
$types = array_map('sanitize_key', $_POST['types']);
$saga_id = absint($_POST['saga_id']);
```

### SQL Injection Prevention

Always use `$wpdb->prepare()`:

```php
$query = $wpdb->prepare(
    "SELECT * FROM {$table} WHERE canonical_name LIKE %s",
    '%' . $wpdb->esc_like($search_term) . '%'
);
```

### Nonce Verification

AJAX requests require valid nonce:

```php
if (!check_ajax_referer('saga_search_nonce', 'nonce', false)) {
    wp_send_json_error(['message' => 'Invalid security token'], 403);
}
```

### Capability Checks

Edit links only shown to authorized users:

```php
<?php if (current_user_can('edit_posts')): ?>
    <a href="<?php echo get_edit_post_link($post_id); ?>">Edit</a>
<?php endif; ?>
```

## Browser Support

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Voice Search**: Chrome, Edge (Web Speech API)
- **Graceful Degradation**: Falls back to standard search without JS

## Accessibility Features

- **ARIA Labels**: All interactive elements labeled
- **Live Regions**: Screen reader announcements
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Logical focus order
- **Color Contrast**: WCAG AA compliant
- **Reduced Motion**: Respects prefers-reduced-motion
- **High Contrast**: Works in high contrast mode

## Troubleshooting

### Search Not Working

1. Check JavaScript console for errors
2. Verify AJAX endpoint: `/wp-admin/admin-ajax.php`
3. Test nonce generation
4. Check database table exists
5. Enable WP_DEBUG for detailed errors

### No Results Found

1. Verify entities exist in database
2. Check `saga_entities` table has data
3. Test with simple query (single word)
4. Review filter settings
5. Check wp_post_id links are valid

### Slow Performance

1. Check database indexes
2. Enable object cache (Redis/Memcached)
3. Reduce max_results limit
4. Optimize images (thumbnails)
5. Review server resources

### Autocomplete Not Appearing

1. Check script enqueue order
2. Verify jQuery loaded
3. Test input selector
4. Review CSS z-index conflicts
5. Check browser console

## Future Enhancements

### Phase 2 (v1.4.0)
- Vector embeddings for true semantic search
- Machine learning relevance tuning
- Multi-language support
- Search result clustering
- Related entity suggestions

### Phase 3 (v1.5.0)
- Natural language query understanding
- Entity relationship graph search
- Timeline-based filtering
- Advanced analytics dashboard
- A/B testing for relevance

## Support

For issues, feature requests, or questions:

- **GitHub Issues**: [saga-manager/issues](https://github.com/saga-manager/issues)
- **Documentation**: [saga-manager.dev/docs](https://saga-manager.dev/docs)
- **Email**: support@saga-manager.dev

## License

This feature is part of the Saga Manager theme, licensed under GPL v2 or later.

---

**Last Updated**: 2025-12-31
**Version**: 1.3.0
**Contributors**: Saga Manager Team
