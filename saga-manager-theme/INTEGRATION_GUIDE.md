# Collections Feature - Quick Integration Guide

## 5-Minute Integration

Add bookmark functionality to your existing saga entity pages in just a few minutes.

## Step 1: Add to Entity Archive (Entity Grid/List)

**File:** `archive-saga_entity.php` or your entity loop template

```php
<?php while (have_posts()) : the_post(); ?>
    <article class="saga-entity-card">
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

        <div class="entity-actions">
            <!-- ADD THIS LINE -->
            <?php saga_bookmark_button(); ?>
        </div>
    </article>
<?php endwhile; ?>
```

## Step 2: Add to Single Entity Page

**File:** `single-saga_entity.php` or your single entity template

```php
<article class="saga-entity-single">
    <header class="entry-header">
        <h1><?php the_title(); ?></h1>

        <!-- ADD THIS LINE (icon-only version for header) -->
        <?php saga_bookmark_button(null, 'favorites', ['variant' => 'icon-only']); ?>
    </header>

    <div class="entry-content">
        <?php the_content(); ?>
    </div>
</article>
```

## Step 3: Create Collections Page

1. Go to **Pages → Add New**
2. Title: "My Collections"
3. **Template:** Select "My Collections" from Page Attributes
4. **Publish**

Done! Your users can now bookmark entities and manage collections.

## Optional Enhancements

### Add to Navigation Menu

```php
// In functions.php or navigation area
if (is_user_logged_in()) {
    $collections = saga_get_user_collections();
    $total_bookmarks = 0;

    foreach ($collections as $collection) {
        $total_bookmarks += count($collection['entity_ids']);
    }

    echo '<a href="' . get_permalink(get_page_by_path('my-collections')) . '" class="nav-collections">';
    echo 'My Collections <span class="count">(' . $total_bookmarks . ')</span>';
    echo '</a>';
}
```

### Add Collection Widget to Sidebar

```php
// In sidebar.php or widget area
if (is_user_logged_in()) {
    $collections = saga_get_user_collections();

    echo '<div class="widget saga-collections-widget">';
    echo '<h3>' . __('My Collections', 'saga-manager') . '</h3>';
    echo '<ul>';

    foreach ($collections as $slug => $data) {
        $count = count($data['entity_ids']);
        echo '<li>';
        echo '<a href="' . add_query_arg('collection', $slug, get_permalink(get_page_by_path('my-collections'))) . '">';
        echo esc_html($data['name']) . ' <span class="count">(' . $count . ')</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}
```

### Custom Styling

Add to your theme's CSS file:

```css
/* Bookmark button in entity grid */
.saga-entity-card .saga-bookmark-btn {
    margin-top: 0.5rem;
}

/* Icon-only button in header */
.entry-header .saga-bookmark-btn.icon-only {
    position: absolute;
    top: 1rem;
    right: 1rem;
}

/* Adjust toast position for mobile */
@media (max-width: 768px) {
    .saga-toast-container {
        left: 1rem;
        right: 1rem;
    }
}
```

## Testing

1. **Test as logged-in user:**
   - Click bookmark button → Should show "Added to collection" toast
   - Visit My Collections page → Should see entity listed
   - Click bookmark again → Should show "Removed from collection" toast

2. **Test as guest user:**
   - Click bookmark button → Should show toast
   - Open browser console → Run `localStorage.getItem('saga_guest_collections')`
   - Should see JSON with your bookmarks

3. **Test collection management:**
   - Visit My Collections page
   - Create new collection → Should appear in grid
   - Export collection → Should download JSON file
   - Delete collection → Should remove from grid

## Troubleshooting

**Button doesn't appear:**
- Check that `saga_bookmark_button()` function exists
- Verify assets are enqueued (check page source for `collections.js` and `collections.css`)

**AJAX errors:**
- Open browser console and check for JavaScript errors
- Verify nonce is valid (check `sagaCollectionsData.nonce`)
- Check PHP error logs for server-side issues

**Collections page is blank:**
- Verify template file exists: `page-templates/my-collections.php`
- Check that template is selected in page editor
- Re-save permalinks: Settings → Permalinks → Save

**Guest collections not saving:**
- Check browser localStorage is enabled
- Try incognito mode to rule out browser extensions
- Verify quota not exceeded (browser console: `localStorage.length`)

## Next Steps

1. **Customize button appearance** - Edit `assets/css/collections.css`
2. **Add to more templates** - Entity cards, search results, related entities
3. **Create custom collections** - Reading lists, character favorites, location guides
4. **Enable collection sharing** - Export and import collections between users

## Support

For detailed documentation, see `COLLECTIONS_USAGE.md`.

For implementation examples, see `template-parts/entity-card-example.php`.
