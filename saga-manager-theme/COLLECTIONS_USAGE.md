# Collections/Bookmarks Feature - Usage Guide

## Overview

The Personal Collections/Bookmarks feature allows users to save and organize saga entities into custom collections. It supports both logged-in users (server-side storage) and guest users (localStorage fallback).

## Files Created

1. **`inc/class-saga-collections.php`** - Collection manager class with full CRUD operations
2. **`assets/js/collections.js`** - Frontend JavaScript for AJAX operations and localStorage
3. **`assets/css/collections.css`** - Complete styling for buttons, toasts, and collection management
4. **`template-parts/collection-button.php`** - Reusable bookmark button template
5. **`page-templates/my-collections.php`** - Full collection management page
6. **`functions.php`** - AJAX handlers, asset enqueuing, and helper functions

## Quick Start

### 1. Add Bookmark Button to Entity Cards

In your entity card template (e.g., `template-parts/saga/entity-card.php`):

```php
<article class="saga-entity-card">
    <h3><?php the_title(); ?></h3>

    <div class="saga-entity-card__excerpt">
        <?php the_excerpt(); ?>
    </div>

    <div class="saga-entity-card__actions">
        <a href="<?php the_permalink(); ?>" class="btn-primary">
            <?php esc_html_e('View Details', 'saga-manager'); ?>
        </a>

        <!-- Bookmark button -->
        <?php saga_bookmark_button(get_the_ID()); ?>
    </div>
</article>
```

### 2. Add Bookmark Button to Single Entity Page

In your single entity template (e.g., `single-saga_entity.php`):

```php
<article class="saga-entity-single">
    <header class="entry-header">
        <h1><?php the_title(); ?></h1>

        <div class="saga-entity-actions">
            <!-- Icon-only bookmark button -->
            <?php
            saga_bookmark_button(get_the_ID(), 'favorites', [
                'variant' => 'icon-only',
                'show_text' => false,
            ]);
            ?>
        </div>
    </header>

    <div class="entry-content">
        <?php the_content(); ?>
    </div>
</article>
```

### 3. Create a Collections Management Page

1. In WordPress admin, create a new page (e.g., "My Collections")
2. Select the **"My Collections"** template from the Page Attributes metabox
3. Publish the page

The page will automatically display:
- All user collections
- Collection creation form
- Entity lists within collections
- Management options (rename, delete, export)
- Guest user notices with localStorage info

## Helper Functions

### Display Bookmark Button

```php
saga_bookmark_button(
    int|null $entity_id = null,    // Entity post ID (default: current post)
    string $collection = 'favorites', // Collection slug
    array $args = []                  // Additional arguments
);
```

**Arguments:**
- `variant`: 'default' or 'icon-only'
- `button_text`: Custom button text
- `show_text`: Show/hide text label (bool)
- `class`: Additional CSS classes

**Examples:**

```php
// Default button
saga_bookmark_button();

// Icon-only button
saga_bookmark_button(123, 'favorites', ['variant' => 'icon-only']);

// Custom text
saga_bookmark_button(123, 'favorites', ['button_text' => 'Save for Later']);

// Custom collection
saga_bookmark_button(123, 'reading-list');
```

### Check if Entity is Bookmarked

```php
$is_bookmarked = saga_is_bookmarked(123, 'favorites');

if ($is_bookmarked) {
    echo 'This entity is in your favorites!';
}
```

### Get User Collections

```php
$collections = saga_get_user_collections();

foreach ($collections as $slug => $data) {
    echo $data['name'] . ' (' . count($data['entity_ids']) . ' items)';
}
```

## Direct Template Part Usage

For more control, include the template part directly:

```php
get_template_part('template-parts/collection-button', null, [
    'entity_id' => 123,
    'collection' => 'favorites',
    'variant' => 'icon-only',
    'button_text' => 'Bookmark',
    'show_text' => true,
    'class' => 'my-custom-class',
]);
```

## JavaScript API

The collections JavaScript is exposed as `window.SagaCollections` for custom integrations.

### Custom Event Listeners

```javascript
// Listen for collection updates
jQuery(document).on('saga:collection-updated', function(e, collectionSlug, collectionData) {
    console.log('Collection updated:', collectionSlug, collectionData);
});

// Listen for collection creation
jQuery(document).on('saga:collection-created', function(e, collectionSlug, collectionData) {
    console.log('Collection created:', collectionSlug);
});

// Listen for collection deletion
jQuery(document).on('saga:collection-deleted', function(e, collectionSlug) {
    console.log('Collection deleted:', collectionSlug);
});

// Guest user events
jQuery(document).on('saga:guest-collection-updated', function(e, collectionSlug, collectionData) {
    console.log('Guest collection updated:', collectionSlug);
});
```

### Manual Operations

```javascript
// Show toast notification
SagaCollections.showToast('Custom message', 'success'); // types: success, error, info, warning

// Get guest collections (localStorage)
const guestCollections = SagaCollections.getGuestCollections();

// Save guest collections
SagaCollections.saveGuestCollections(collectionsObject);
```

## Customization

### Modify Default Collections

Hook into collection initialization:

```php
add_filter('saga_collections_defaults', function($defaults) {
    $defaults['reading-list'] = [
        'name' => __('Reading List', 'saga-manager'),
        'entity_ids' => [],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];

    return $defaults;
});
```

### Custom Collection Limits

Modify class constants in `class-saga-collections.php`:

```php
const MAX_COLLECTIONS = 20;          // Max collections per user
const MAX_ENTITIES_PER_COLLECTION = 1000; // Max entities per collection
```

### Custom Styling

Override CSS variables in your child theme:

```css
:root {
    --saga-bookmark-color: #007cba;
    --saga-bookmark-hover-color: #005a87;
}

/* Custom bookmark button */
.saga-bookmark-btn {
    border-radius: 0.5rem;
    padding: 0.75rem 1.25rem;
}

/* Custom toast position */
.saga-toast-container {
    top: auto;
    bottom: 2rem;
    right: 2rem;
}
```

## WordPress Integration

### Add to Navigation Menu

Add the collections page to your navigation menu:
1. Go to Appearance → Menus
2. Add the "My Collections" page to your menu
3. Optionally add a custom icon using CSS classes

### Widget Area

Display user collections in a widget area:

```php
function saga_collections_widget() {
    if (!is_user_logged_in()) {
        return;
    }

    $collections = saga_get_user_collections();

    echo '<div class="widget saga-collections-widget">';
    echo '<h3 class="widget-title">' . __('My Collections', 'saga-manager') . '</h3>';
    echo '<ul>';

    foreach ($collections as $slug => $data) {
        $url = add_query_arg('collection', $slug, get_permalink(get_page_by_path('my-collections')));
        printf(
            '<li><a href="%s">%s <span class="count">(%d)</span></a></li>',
            esc_url($url),
            esc_html($data['name']),
            count($data['entity_ids'])
        );
    }

    echo '</ul>';
    echo '</div>';
}

add_action('generate_before_sidebar_content', 'saga_collections_widget');
```

## Security Notes

- All AJAX requests are protected with WordPress nonces
- User input is sanitized using WordPress sanitization functions
- Collection operations check user capabilities
- SQL injection prevention via `sanitize_key()` and `absint()`
- XSS prevention via `esc_html()` and `esc_attr()`

## Guest User Limitations

Guest users (not logged in):
- Collections stored in browser localStorage
- Limited to browser/device (no sync)
- Cleared when browser cache is cleared
- No server-side export with entity details
- Prompted to log in for full features

## Performance Considerations

- User meta stored as single serialized array (not individual rows)
- Entity IDs stored as PHP arrays (not objects)
- AJAX requests debounced on client side
- Toast notifications auto-dismiss after 3 seconds
- Maximum limits prevent excessive storage

## Troubleshooting

### Bookmark button doesn't appear
- Check if template part is included: `get_template_part('template-parts/collection-button')`
- Verify entity_id is valid
- Check browser console for JavaScript errors

### AJAX requests fail
- Verify nonce is properly generated: check `sagaCollectionsData.nonce` in browser console
- Check AJAX URL: `sagaCollectionsData.ajaxUrl` should point to `admin-ajax.php`
- Review PHP error logs for server-side issues

### Guest collections not persisting
- Check browser localStorage is enabled
- Verify localStorage quota not exceeded
- Test in private/incognito mode to rule out extensions

### Collections page shows 404
- Verify page template exists: `page-templates/my-collections.php`
- Re-save permalinks: Settings → Permalinks → Save Changes
- Check page template is selected in page editor

## Advanced: Custom Collection Types

Create specialized collections for different entity types:

```php
// In your theme or plugin
function saga_create_character_collection() {
    if (!is_user_logged_in()) {
        return;
    }

    $collections_manager = new Saga_Collections();
    $user_id = get_current_user_id();

    $result = $collections_manager->create_collection($user_id, 'Favorite Characters');

    if (!is_wp_error($result)) {
        // Add custom meta or filters specific to characters
        update_user_meta($user_id, '_saga_collection_favorite-characters_type', 'character');
    }
}
```

Then filter queries to only show character entities in that collection.

## REST API Integration (Future Enhancement)

The collections system can be extended to support REST API:

```php
// Register REST route
add_action('rest_api_init', function() {
    register_rest_route('saga/v1', '/collections', [
        'methods' => 'GET',
        'callback' => 'saga_rest_get_collections',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
});

function saga_rest_get_collections() {
    $collections = saga_get_user_collections();
    return rest_ensure_response($collections);
}
```

## Support

For issues or feature requests, please open a ticket in the theme repository.
