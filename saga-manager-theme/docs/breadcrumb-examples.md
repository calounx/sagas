# Breadcrumb Navigation Examples

## Visual Examples

### Desktop View

```
< Back | Home > Star Wars > Characters > Luke Skywalker
```

### Mobile View (< 768px)

```
< | Home > ... > Characters > Luke Skywalker
```

## Breadcrumb Patterns by Page Type

### 1. Single Entity Page

**URL**: `/entities/luke-skywalker/`

**Breadcrumb**:
```
Home > Star Wars > Characters > Luke Skywalker
```

**HTML Output**:
```html
<nav class="saga-breadcrumbs" role="navigation" aria-label="Breadcrumb">
  <ol itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a href="/" itemprop="item">
        <span itemprop="name">Home</span>
      </a>
      <meta itemprop="position" content="1" />
    </li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a href="/saga/star-wars/" itemprop="item">
        <span itemprop="name">Star Wars</span>
      </a>
      <meta itemprop="position" content="2" />
    </li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a href="/entity-type/characters/" itemprop="item">
        <span itemprop="name">Characters</span>
      </a>
      <meta itemprop="position" content="3" />
    </li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <span itemprop="name">Luke Skywalker</span>
      <meta itemprop="position" content="4" />
    </li>
  </ol>
</nav>
```

### 2. Entity Archive

**URL**: `/entities/`

**Breadcrumb**:
```
Home > Entities
```

### 3. Entity Type Archive (Filtered)

**URL**: `/entities/?saga=star-wars&entity_type=characters`

**Breadcrumb**:
```
Home > Star Wars > Characters
```

### 4. Taxonomy Page (Hierarchical)

**URL**: `/saga/dune/`

**Breadcrumb**:
```
Home > Dune
```

**With parent taxonomy**:
```
Home > Science Fiction > Dune > Characters
```

### 5. Search Results

**URL**: `/search/?s=skywalker`

**Breadcrumb**:
```
Home > Search Results: "skywalker"
```

### 6. Regular Post

**URL**: `/blog/saga-manager-release/`

**Breadcrumb**:
```
Home > Blog > Saga Manager Release
```

**With category**:
```
Home > Blog > Announcements > Saga Manager Release
```

### 7. Page (Hierarchical)

**URL**: `/about/team/developers/`

**Breadcrumb**:
```
Home > About > Team > Developers
```

### 8. Author Archive

**URL**: `/author/john-doe/`

**Breadcrumb**:
```
Home > Author: John Doe
```

### 9. Date Archive

**Year**: `/2025/`
```
Home > 2025
```

**Month**: `/2025/12/`
```
Home > 2025 > December
```

**Day**: `/2025/12/31/`
```
Home > 2025 > December > 31
```

### 10. 404 Page

**URL**: `/non-existent-page/`

**Breadcrumb**:
```
Home > 404 - Page Not Found
```

## With Session History

### Scenario: User Navigation Path

1. **User visits**: `/` (Home)
2. **User visits**: `/entities/` (Entities Archive)
3. **User visits**: `/saga/star-wars/` (Star Wars Saga)
4. **User visits**: `/entities/luke-skywalker/` (Luke Skywalker Entity)

**Current Breadcrumb Display**:
```
< Back | Home > Star Wars > Characters > Luke Skywalker
```

**Session Storage** (after step 4):
```json
[
  {"url": "/", "title": "Home - Saga Manager", "timestamp": 1704067200000},
  {"url": "/entities/", "title": "Entities - Saga Manager", "timestamp": 1704067230000},
  {"url": "/saga/star-wars/", "title": "Star Wars - Saga Manager", "timestamp": 1704067250000},
  {"url": "/entities/luke-skywalker/", "title": "Luke Skywalker - Saga Manager", "timestamp": 1704067280000}
]
```

**Back Button Behavior**:
- Clicking "Back" navigates to: `/saga/star-wars/`
- aria-label: "Go back to Star Wars - Saga Manager"

## Mobile Responsive Behavior

### Example: Long Breadcrumb Trail

**Desktop** (full trail):
```
< Back | Home > Star Wars > Expanded Universe > Characters > Jedi Order > Luke Skywalker
```

**Mobile** (collapsed):
```
< | Home > ... > Jedi Order > Luke Skywalker
```

**Visible Items**:
- First item: "Home"
- Ellipsis: "..."
- Last 2 items: "Jedi Order", "Luke Skywalker"

**Hidden Items** (via CSS):
- "Star Wars"
- "Expanded Universe"
- "Characters"

## Schema.org JSON-LD Example

### Complete Structured Data

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://saga-manager.example.com/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Star Wars",
      "item": "https://saga-manager.example.com/saga/star-wars/"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "Characters",
      "item": "https://saga-manager.example.com/entity-type/characters/"
    },
    {
      "@type": "ListItem",
      "position": 4,
      "name": "Luke Skywalker"
    }
  ]
}
```

### Google Search Result Preview

```
saga-manager.example.com › saga › star-wars › characters
Luke Skywalker | Saga Manager
Home > Star Wars > Characters > Luke Skywalker
A legendary Jedi Knight who destroyed the first Death Star...
```

## Customization Examples

### 1. Custom Home Label (French)

```php
add_filter('saga_breadcrumb_home_label', function($label) {
    return 'Accueil';
});
```

**Result**:
```
Accueil > Star Wars > Personnages > Luke Skywalker
```

### 2. Custom Separator (Arrow)

```php
add_filter('saga_breadcrumb_separator', function($separator) {
    return ' → ';
});
```

**Result**:
```
Home → Star Wars → Characters → Luke Skywalker
```

### 3. Hide Breadcrumbs on Landing Pages

```php
add_filter('saga_show_breadcrumbs', function($show) {
    if (is_page('landing-page')) {
        return false;
    }
    return $show;
});
```

## Accessibility Features in Action

### Keyboard Navigation

1. **Tab** to Back button → Focus visible outline
2. **Enter/Space** to activate → Navigate to previous page
3. **Tab** through breadcrumb links → Each link focusable
4. **Enter** on breadcrumb link → Navigate to that page

### Screen Reader Output (NVDA)

```
Navigation landmark, Breadcrumb
Button, Go back to Star Wars - Saga Manager
Separator
List, 4 items
Link, Home, 1 of 4
Link, Star Wars, 2 of 4
Link, Characters, 3 of 4
Current page, Luke Skywalker, 4 of 4
```

### High Contrast Mode

- Border width increased to 2px
- Focus outline increased to 3px
- Separator contrast enhanced

## Performance Metrics

### Page Load Impact

- **CSS**: 9.3KB (uncompressed), ~2KB (gzipped)
- **JavaScript**: 8.4KB (uncompressed), ~3KB (gzipped)
- **HTML**: ~500 bytes per breadcrumb trail
- **JSON-LD**: ~300 bytes per trail
- **Session Storage**: ~100 bytes per page (max 500 bytes)

### Rendering Performance

- **DOM Nodes**: ~15-25 nodes per breadcrumb trail
- **Paint Time**: < 5ms
- **Layout Shift**: 0 (breadcrumbs position fixed)
- **JavaScript Execution**: < 10ms on page load

## Testing Checklist

- [ ] Desktop: Full breadcrumb trail displays
- [ ] Mobile: Ellipsis shows, only 3 items visible
- [ ] Back button: Appears after 2+ page visits
- [ ] Back button: Navigates to previous page
- [ ] Schema.org: Validates in Google Rich Results Test
- [ ] Keyboard: Tab/Enter navigation works
- [ ] Screen reader: Announces correctly
- [ ] Dark mode: Styles apply correctly
- [ ] Print: Shows full trail, hides back button
- [ ] No JavaScript: Breadcrumbs still visible (no back button)
