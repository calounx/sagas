# Reading Mode - Distraction-Free Reading Experience

Production-grade reading mode implementation for Saga Manager Theme with customizable typography, color themes, progress tracking, and accessibility features.

## Features

### Core Functionality
- **Distraction-Free Interface** - Hides all UI chrome (header, sidebar, footer, navigation)
- **Optimal Reading Width** - Content centered at 680px max-width for comfortable reading
- **Elegant Typography** - Serif font optimized for long-form reading
- **Smooth Transitions** - Polished enter/exit animations

### Customization Options
- **Font Size** - 4 levels (Small, Medium, Large, Extra Large)
- **Line Height** - 3 spacing options (Compact, Normal, Relaxed)
- **Color Themes** - 4 themes (Light, Sepia, Dark, Black/OLED)
- **Auto-Hide Controls** - Controls fade after 3 seconds of inactivity

### User Experience
- **Progress Indicator** - Visual scroll percentage bar
- **Reading Time Estimate** - Calculated based on word count
- **Keyboard Shortcuts** - Full keyboard navigation support
- **Persistent Preferences** - Settings saved to localStorage
- **Print Optimized** - Clean print styles

### Accessibility
- **Screen Reader Support** - ARIA labels and live regions
- **Keyboard Navigation** - Full keyboard control
- **Focus Trap** - Keeps focus within reading mode
- **High Contrast Mode** - Respects prefers-contrast
- **Reduced Motion** - Respects prefers-reduced-motion

## Usage

### Basic Implementation

#### In Template Files

```php
<?php
// Display reading mode button
if (is_singular('saga_entity')) {
    saga_reading_mode_button();
}
?>
```

#### Automatic Integration

Reading mode is automatically integrated on all `saga_entity` post types. The button appears before the content by default.

To disable auto-insertion:

```php
add_filter('saga_auto_insert_reading_mode_button', '__return_false');
```

### Customization

#### Change Button Text

```php
saga_reading_mode_button([
    'text' => 'Focus Mode',
    'icon' => true,
    'class' => 'my-custom-button',
]);
```

#### Add to Custom Post Types

```php
add_filter('saga_reading_mode_button_show_on', function($post_types) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
});
```

#### Customize Default Theme

```php
add_filter('saga_reading_mode_default_theme', function() {
    return 'dark'; // light, sepia, dark, or black
});
```

#### Modify Custom Styles

```php
add_filter('saga_reading_mode_custom_styles', function($styles) {
    $styles['--rm-max-width'] = '800px'; // Wider reading area
    $styles['--rm-font-family-serif'] = 'Merriweather, serif';
    return $styles;
});
```

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `Esc` | Exit reading mode |
| `+` or `=` | Increase font size |
| `-` | Decrease font size |
| `1` | Light theme |
| `2` | Sepia theme |
| `3` | Dark theme |
| `4` | Black theme |
| `Space` | Toggle controls panel |

## Color Themes

### Light Theme
- Background: `#ffffff`
- Text: `#1f2937`
- Best for: Bright environments, daytime reading

### Sepia Theme (Default)
- Background: `#f4f1ea`
- Text: `#3e2723`
- Best for: Reduced eye strain, warm appearance

### Dark Theme
- Background: `#1f2937`
- Text: `#f9fafb`
- Best for: Low-light environments, night reading

### Black Theme (OLED)
- Background: `#000000`
- Text: `#e5e7eb`
- Best for: OLED screens, maximum contrast, battery saving

## Typography Scale

### Font Sizes
- **Small**: 16px
- **Medium**: 18px (default)
- **Large**: 20px
- **Extra Large**: 24px

### Line Heights
- **Compact**: 1.5
- **Normal**: 1.75 (default)
- **Relaxed**: 2.0

## API Reference

### PHP Functions

#### `saga_reading_mode_button($args)`
Display the reading mode button.

**Parameters:**
- `$args` (array) Optional arguments:
  - `text` (string) Button text
  - `icon` (bool) Show icon
  - `class` (string) Custom CSS class
  - `show_on` (array) Post types to show on

**Example:**
```php
saga_reading_mode_button([
    'text' => 'Read',
    'icon' => true,
    'class' => 'btn-primary',
]);
```

#### `saga_calculate_reading_time($content, $wpm)`
Calculate estimated reading time.

**Parameters:**
- `$content` (string) Content to analyze
- `$wpm` (int) Words per minute (default: 200)

**Returns:** (int) Estimated reading time in minutes

**Example:**
```php
$reading_time = saga_calculate_reading_time(get_the_content());
echo "Estimated reading time: {$reading_time} minutes";
```

#### `saga_get_reading_mode_meta($post_id)`
Get reading mode metadata.

**Parameters:**
- `$post_id` (int|null) Post ID (default: current post)

**Returns:** (array) Meta information

**Example:**
```php
$meta = saga_get_reading_mode_meta();
echo "Reading time: {$meta['reading_time']} minutes";
echo "Word count: {$meta['word_count']}";
```

### JavaScript API

#### `window.sagaReadingMode`
Global reading mode manager instance.

**Methods:**
- `enter()` - Enter reading mode
- `exit()` - Exit reading mode
- `setFontSize(size)` - Set font size ('small', 'medium', 'large', 'xlarge')
- `setLineHeight(spacing)` - Set line height ('compact', 'normal', 'relaxed')
- `setTheme(theme)` - Set color theme ('light', 'sepia', 'dark', 'black')

**Example:**
```javascript
// Programmatically enter reading mode
window.sagaReadingMode.enter();

// Change theme
window.sagaReadingMode.setTheme('dark');

// Exit reading mode
window.sagaReadingMode.exit();
```

## WordPress Filters

### `saga_auto_insert_reading_mode_button`
Control automatic button insertion.

```php
add_filter('saga_auto_insert_reading_mode_button', '__return_false');
```

### `saga_show_reading_mode_button`
Conditionally show/hide button.

```php
add_filter('saga_show_reading_mode_button', function($show, $post) {
    // Only show for posts longer than 500 words
    return str_word_count(strip_tags($post->post_content)) > 500;
}, 10, 2);
```

### `saga_reading_mode_meta`
Modify reading mode metadata.

```php
add_filter('saga_reading_mode_meta', function($meta, $post_id) {
    $meta['author'] = get_the_author_meta('display_name', get_post_field('post_author', $post_id));
    return $meta;
}, 10, 2);
```

### `saga_reading_mode_custom_styles`
Customize CSS custom properties.

```php
add_filter('saga_reading_mode_custom_styles', function($styles) {
    $styles['--rm-max-width'] = '750px';
    $styles['--rm-font-family-serif'] = 'Lora, Georgia, serif';
    return $styles;
});
```

## Theme Customizer Integration

Reading Mode settings are available in **Appearance > Customize > Reading Mode**:

1. **Auto-insert Reading Mode Button** - Enable/disable automatic button insertion
2. **Default Theme** - Choose default color theme (Light, Sepia, Dark, Black)

## Mobile Optimization

- Touch-friendly controls (minimum 44x44px tap targets)
- Responsive layout adjusts for small screens
- Simplified settings on mobile devices
- Full-width reading area
- Bottom-aligned controls for thumb accessibility

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile Safari (iOS 14+)
- Chrome Mobile (Android 90+)

## Performance

- **Lazy Loading** - Assets only loaded on singular posts
- **No Server Requests** - Preferences stored in localStorage
- **Minimal DOM** - Controls generated on-demand
- **Smooth Animations** - CSS-based transitions
- **Efficient Scroll** - RequestAnimationFrame for progress updates

## Accessibility Compliance

- WCAG 2.1 Level AA compliant
- ARIA landmarks and labels
- Keyboard navigation
- Screen reader announcements
- Focus management
- High contrast support
- Reduced motion support

## Troubleshooting

### Button Not Appearing

Check that:
1. You're on a singular post (`is_singular()`)
2. Post type is in allowed list (default: `saga_entity`)
3. Post has content
4. Auto-insert filter is not disabled

### Preferences Not Saving

Check:
1. Browser localStorage is enabled
2. No JavaScript errors in console
3. Browser is not in private/incognito mode

### Styles Not Applying

Check:
1. CSS file is enqueued (`saga-reading-mode`)
2. No theme conflicts
3. Browser cache is cleared

## Development

### File Structure

```
saga-manager-theme/
├── assets/
│   ├── css/
│   │   └── reading-mode.css          # All reading mode styles
│   └── js/
│       └── reading-mode.js            # Reading mode controller
├── inc/
│   └── reading-mode-helpers.php       # PHP helper functions
└── template-parts/
    └── reading-mode-controls.php      # Controls template reference
```

### Extending Reading Mode

#### Add Custom Font Family

```css
.reading-mode[data-font-family="custom"] {
    font-family: 'Your Custom Font', serif;
}
```

```javascript
// Extend JavaScript to support custom font
window.sagaReadingMode.setFontFamily('custom');
```

#### Add Custom Theme

```css
.reading-mode[data-theme="custom"] {
    background: #your-bg-color;
    color: #your-text-color;
}
```

```html
<button data-theme="custom">Custom Theme</button>
```

## Examples

### Example 1: Minimal Button

```php
saga_reading_mode_button([
    'text' => 'Read',
    'icon' => false,
]);
```

### Example 2: Custom Styling

```php
saga_reading_mode_button([
    'class' => 'btn btn-primary btn-lg',
]);
```

### Example 3: Conditional Display

```php
if (get_post_meta(get_the_ID(), '_enable_reading_mode', true)) {
    saga_reading_mode_button();
}
```

### Example 4: JavaScript Integration

```javascript
// Auto-enter reading mode from URL parameter
if (window.location.search.includes('reading-mode=1')) {
    window.sagaReadingMode.enter();
}

// Exit reading mode after custom action
document.addEventListener('saga:custom-event', function() {
    window.sagaReadingMode.exit();
});
```

## Support

For issues, feature requests, or contributions, please refer to the main Saga Manager Theme documentation.

## License

Reading Mode is part of Saga Manager Theme and follows the same license.

## Credits

- Developed for Saga Manager Theme
- Inspired by Medium's reading experience
- Icons from Lucide Icons
- Typography based on modern web standards
