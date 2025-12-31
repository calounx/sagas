# Entity Quick Create - Feature Documentation

**Version:** 1.3.0
**Status:** Production Ready
**Phase:** Phase 1 - Next-Gen Features

## Overview

The Entity Quick Create system provides a streamlined workflow for rapid entity creation directly from the WordPress admin bar. This feature dramatically reduces the time needed to create new saga entities by providing a modal interface with keyboard shortcuts, auto-save, and smart templates.

## Features

### 1. Admin Bar Integration
- **New Entity Menu**: Adds "+ New Entity" to WordPress admin bar
- **Quick Access**: Dropdown with shortcuts for each entity type (character, location, event, faction, artifact, concept)
- **Entity Count Badge**: Real-time display of total entities in saga
- **Recent Entities**: Shows 5 most recently created entities for quick access
- **Visibility**: Available on both frontend and backend for logged-in users with `edit_posts` capability

### 2. Quick Create Modal
- **Keyboard Shortcut**: `Ctrl+Shift+E` to open modal from anywhere
- **Entity Type Selector**: Visual cards for selecting entity type
- **Core Fields**:
  - Name (required, with duplicate detection)
  - Entity Type (required, visual selector)
  - Description (TinyMCE rich text editor)
  - Importance Score (0-100 slider with visual feedback)
  - Saga Selector (if multiple sagas exist)
- **Advanced Options** (collapsible):
  - Featured Image uploader
  - Quick relationships (search and add)
  - Entity templates

### 3. Smart Features

#### Autosave & Draft Recovery
- Automatically saves form data to localStorage every 2 seconds
- Recovery prompt on next modal open if unsaved data exists
- Clear draft option to discard saved data

#### Duplicate Detection
- Real-time check for duplicate entity names
- Visual warning indicator when duplicate detected
- AJAX validation on input debounce (500ms)

#### Templates
- Pre-filled templates for common entity types
- Multiple templates per entity type (basic, detailed, specialized)
- Dynamic template loading based on selected entity type

#### Validation
- Client-side validation with inline errors
- Server-side validation for security
- Field-specific error messages
- Visual feedback on invalid fields

### 4. User Experience

#### Keyboard Navigation
- `Ctrl+Shift+E`: Open modal
- `Esc`: Close modal
- `Ctrl+Enter`: Submit and publish
- `Tab`: Navigate between fields
- Focus trap within modal (accessibility)

#### Loading States
- Loading spinner during entity creation
- Disabled buttons during submission
- Progress feedback for long operations

#### Notifications
- Success notification with links to edit/view
- Error notification with retry option
- Auto-dismiss after 5 seconds
- Persistent on hover

#### Animations
- Smooth fade-in/out for modal
- Slide-up animation for modal container
- Slide-in for notifications
- Reduced motion support for accessibility

### 5. WordPress Integration

#### Custom Post Type Sync
- Creates `saga_entity` WordPress post
- Syncs with `wp_saga_entities` database table
- Bidirectional sync maintained
- Sets proper post status (draft/publish)

#### Database Operations
- Transaction-based creation (rollback on error)
- Creates entity record in `wp_saga_entities`
- Stores attributes in `wp_saga_attribute_values`
- Creates relationships in `wp_saga_entity_relationships`
- Generates content fragments for search
- Updates quality metrics

#### Caching
- Clears relevant caches on creation
- Updates admin bar entity count
- Invalidates query caches

## File Structure

```
inc/
├── admin/
│   ├── quick-create.php              # Main QuickCreate class
│   ├── quick-create-modal.php        # Modal HTML template
│   ├── entity-templates.php          # Template definitions
│   └── README-QUICK-CREATE.md        # This file
├── ajax/
│   └── quick-create-handler.php      # AJAX request handlers
assets/
├── js/
│   └── quick-create.js               # Modal interactions & logic
└── css/
    └── quick-create.css              # Modal & UI styling
```

## Architecture

### Class: `QuickCreate`
**Namespace:** `SagaManager\Admin`
**File:** `inc/admin/quick-create.php`

**Responsibilities:**
- Admin bar menu registration
- Asset enqueuing
- Modal rendering
- Security (nonce, capability checks)

**Key Methods:**
- `init()`: Initialize hooks and filters
- `add_admin_bar_menu()`: Register admin bar items
- `enqueue_assets()`: Load CSS/JS
- `render_modal()`: Output modal HTML
- `handle_ajax_create()`: Process entity creation (delegated to handler)

### Class: `QuickCreateHandler`
**Namespace:** `SagaManager\Ajax`
**File:** `inc/ajax/quick-create-handler.php`

**Responsibilities:**
- AJAX request handling
- Input sanitization and validation
- Database operations
- Transaction management

**Key Methods:**
- `register()`: Register AJAX hooks
- `handle_create()`: Main creation handler
- `handle_get_templates()`: Fetch templates
- `handle_check_duplicate()`: Duplicate name check
- `handle_search_entities()`: Relationship search

### Class: `EntityTemplates`
**Namespace:** `SagaManager\Admin`
**File:** `inc/admin/entity-templates.php`

**Responsibilities:**
- Template definition and storage
- Template retrieval by entity type
- HTML template generation

**Key Methods:**
- `get_templates_for_type()`: Get templates for specific entity type
- `get_*_template()`: Private methods for each template type

## Security

### 1. Authentication & Authorization
```php
// Capability check
if (!current_user_can('edit_posts')) {
    wp_send_json_error(['message' => 'Insufficient permissions'], 403);
}
```

### 2. Nonce Verification
```php
// On every AJAX request
check_ajax_referer('saga_quick_create', 'nonce');
```

### 3. Input Sanitization
```php
$data = [
    'name' => sanitize_text_field($raw_data['name'] ?? ''),
    'entity_type' => sanitize_key($raw_data['entity_type'] ?? 'character'),
    'description' => wp_kses_post($raw_data['description'] ?? ''),
    'importance' => min(100, max(0, absint($raw_data['importance'] ?? 50))),
    // ...
];
```

### 4. SQL Injection Prevention
```php
// Always use wpdb->prepare
$wpdb->prepare(
    "SELECT * FROM {$table} WHERE canonical_name = %s AND saga_id = %d",
    $name,
    $saga_id
);
```

### 5. XSS Protection
```php
// All output escaped
echo esc_html($entity->canonical_name);
echo esc_attr($entity->entity_type);
echo esc_url($permalink);
```

## Performance

### Database Optimization
- **Transactions**: All operations wrapped in transaction (rollback on error)
- **Batch Operations**: Multiple inserts in single transaction
- **Indexed Queries**: Uses indexed columns (saga_id, canonical_name)
- **Query Caching**: WordPress object cache integration

### Asset Loading
- **Conditional Loading**: Only loads on pages where user can create entities
- **Asset Minification**: Production assets should be minified
- **CDN Support**: Compatible with WordPress CDN plugins
- **Cache Busting**: Version-based cache invalidation

### Client-side Performance
- **Debouncing**: Duplicate check debounced to 500ms
- **LocalStorage**: Autosave uses localStorage (no server requests)
- **Lazy Loading**: Modal HTML loaded once on first open
- **DOM Optimization**: Minimal DOM manipulation

## Error Handling

### Client-side Errors
```javascript
try {
    // Form submission
} catch (error) {
    this.showNotification('error', 'Failed to create entity');
    console.error('Quick create error:', error);
}
```

### Server-side Errors
```php
try {
    // Create entity
    $wpdb->query('COMMIT');
} catch (\Exception $e) {
    $wpdb->query('ROLLBACK');
    error_log('[SAGA][ERROR] Quick create failed: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Failed to create entity'], 500);
}
```

### Error Types
1. **Validation Errors**: Field-specific, shown inline
2. **Duplicate Errors**: Pre-submission warning
3. **Database Errors**: Transaction rollback, generic error message
4. **Permission Errors**: 403 Forbidden response
5. **Network Errors**: Retry option in notification

## Accessibility

### WCAG 2.1 AA Compliance
- **Keyboard Navigation**: Full keyboard support
- **Focus Management**: Focus trap in modal, auto-focus on open
- **Screen Reader Support**: ARIA labels, roles, and live regions
- **Color Contrast**: Minimum 4.5:1 contrast ratio
- **Reduced Motion**: Respects `prefers-reduced-motion` media query

### ARIA Attributes
```html
<div role="dialog" aria-labelledby="saga-modal-title" aria-hidden="true">
    <h2 id="saga-modal-title">Quick Create Entity</h2>
    <button aria-label="Close modal">×</button>
</div>
```

### Focus Management
```javascript
// Trap focus within modal
trapFocus(e) {
    const focusableElements = this.modal.find(':focusable').filter(':visible');
    const first = focusableElements.first();
    const last = focusableElements.last();

    // Wrap around
    if (e.shiftKey && activeElement === first) {
        last.focus();
    } else if (!e.shiftKey && activeElement === last) {
        first.focus();
    }
}
```

## Usage Examples

### 1. Basic Entity Creation
```
1. Press Ctrl+Shift+E (or click "+ New Entity" in admin bar)
2. Select entity type (e.g., "Character")
3. Enter name: "Luke Skywalker"
4. Adjust importance: 90
5. Add description (optional)
6. Click "Create & Publish"
```

### 2. Using Templates
```
1. Open quick create modal
2. Select entity type: "Character"
3. Click "Advanced Options"
4. Click "Protagonist" template
5. Form pre-filled with structured content
6. Customize and submit
```

### 3. Adding Relationships
```
1. Create entity as usual
2. Click "Advanced Options"
3. In "Quick Relationships", search for "Obi-Wan"
4. Select from results
5. Entity will be linked on creation
```

### 4. Draft Recovery
```
1. Start creating entity
2. Close modal accidentally (data auto-saved)
3. Reopen modal
4. See "Draft recovered from autosave" message
5. Continue editing or clear draft
```

## Configuration

### Customization Hooks

#### Filter: Entity Types
```php
add_filter('saga_quick_create_entity_types', function($types) {
    $types['custom_type'] = __('Custom Type', 'my-plugin');
    return $types;
});
```

#### Filter: Template List
```php
add_filter('saga_quick_create_templates', function($templates, $entity_type) {
    if ($entity_type === 'character') {
        $templates[] = [
            'id' => 'my_template',
            'name' => 'My Template',
            'fields' => ['description' => 'Custom content...'],
        ];
    }
    return $templates;
}, 10, 2);
```

#### Action: After Entity Created
```php
add_action('saga_quick_create_entity_created', function($entity_id, $post_id, $data) {
    // Custom logic after creation
    do_something($entity_id);
}, 10, 3);
```

### Constants
```php
// In wp-config.php or theme
define('SAGA_QUICK_CREATE_AUTOSAVE_INTERVAL', 3000); // 3 seconds
define('SAGA_QUICK_CREATE_DUPLICATE_CHECK_DELAY', 500); // 500ms
```

## Troubleshooting

### Modal Not Opening
**Symptom:** Clicking admin bar does nothing
**Solution:**
1. Check browser console for JavaScript errors
2. Verify assets are loaded: `view-source` → search for `saga-quick-create.js`
3. Check user has `edit_posts` capability
4. Clear browser cache

### Duplicate Detection Not Working
**Symptom:** No warning when entering duplicate name
**Solution:**
1. Check AJAX requests in Network tab
2. Verify nonce is valid (not expired)
3. Check `wp_saga_entities` table exists
4. Test with different name

### Entity Not Created
**Symptom:** Form submits but entity doesn't appear
**Solution:**
1. Check browser console and network tab for errors
2. Check WordPress error logs: `wp-content/debug.log`
3. Verify `saga_entity` custom post type is registered
4. Check database tables exist with correct prefix
5. Test transaction rollback: check for partial data

### Autosave Not Working
**Symptom:** Draft not recovered after closing modal
**Solution:**
1. Check localStorage is enabled in browser
2. Open DevTools → Application → Local Storage
3. Look for `saga_quick_create_draft` key
4. Try different browser (Safari has stricter rules)

### Styling Issues
**Symptom:** Modal looks broken or unstyled
**Solution:**
1. Verify `quick-create.css` is loaded
2. Check for CSS conflicts with other plugins
3. Use browser DevTools to inspect element styles
4. Increase CSS specificity if needed

## Testing

### Manual Testing Checklist

#### Basic Functionality
- [ ] Admin bar menu appears for editors+
- [ ] Admin bar menu NOT shown to subscribers
- [ ] Keyboard shortcut (Ctrl+Shift+E) opens modal
- [ ] All 6 entity types selectable
- [ ] Form fields accept input
- [ ] Submit creates entity (draft)
- [ ] Publish creates entity (published)
- [ ] Cancel closes modal without creating

#### Validation
- [ ] Empty name shows error
- [ ] Duplicate name shows warning
- [ ] Importance slider works (0-100)
- [ ] Rich text editor functions
- [ ] Required fields validated

#### Advanced Features
- [ ] Templates load for entity type
- [ ] Template populates fields
- [ ] Featured image uploader works
- [ ] Relationship search finds entities
- [ ] Autosave occurs every 2 seconds
- [ ] Draft recovery works

#### Edge Cases
- [ ] Works with multiple sagas
- [ ] Handles very long names (255 char limit)
- [ ] Handles special characters in name
- [ ] Works with slow network (loading states)
- [ ] Recovers from server errors

#### Accessibility
- [ ] Tab navigates through fields
- [ ] Esc closes modal
- [ ] Focus trapped in modal
- [ ] Screen reader announces changes
- [ ] High contrast mode supported

### Automated Testing

#### PHPUnit Tests (Recommended)
```php
class QuickCreateTest extends WP_UnitTestCase
{
    public function test_creates_entity_with_valid_data()
    {
        $handler = new QuickCreateHandler();

        $_POST = [
            'name' => 'Test Character',
            'entity_type' => 'character',
            'importance' => 50,
            'saga_id' => 1,
            'status' => 'publish',
            'nonce' => wp_create_nonce('saga_quick_create'),
        ];

        // Simulate logged-in user
        wp_set_current_user(1);

        // Call handler
        ob_start();
        $handler->handle_create();
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue($response['success']);
        $this->assertNotEmpty($response['data']['entity_id']);
    }
}
```

## Future Enhancements

### Planned Features (v1.4.0)
- [ ] Bulk entity creation (CSV import in modal)
- [ ] AI-assisted description generation
- [ ] Voice input for name/description
- [ ] Smart suggestions based on context
- [ ] Duplicate entity cloning
- [ ] Multi-step wizard for complex entities
- [ ] Real-time collaboration (multiple users)
- [ ] Entity preview before creation

### Performance Improvements
- [ ] Asset code splitting
- [ ] Service Worker for offline creation
- [ ] IndexedDB for larger drafts
- [ ] WebSocket for real-time updates

### UX Improvements
- [ ] Dark mode variant
- [ ] Customizable keyboard shortcuts
- [ ] Recent templates
- [ ] Quick edit after creation
- [ ] Undo/redo support

## Support

### Documentation
- Feature documentation: This file
- API documentation: `docs/api/quick-create.md` (TBD)
- Video tutorials: `docs/videos/` (TBD)

### Getting Help
1. Check troubleshooting section above
2. Review browser console and WordPress error logs
3. Search GitHub issues for similar problems
4. Create new issue with reproduction steps

### Contributing
Contributions welcome! Please:
1. Follow WordPress Coding Standards
2. Add PHPUnit tests for new features
3. Update documentation
4. Submit PR with clear description

## Changelog

### Version 1.3.0 (2025-01-01)
- Initial release
- Admin bar integration
- Quick create modal
- Entity templates
- Autosave & draft recovery
- Duplicate detection
- AJAX creation workflow
- Keyboard shortcuts
- Accessibility improvements

---

**Last Updated:** 2025-01-01
**Maintainer:** Saga Manager Team
**License:** GPL-3.0+
