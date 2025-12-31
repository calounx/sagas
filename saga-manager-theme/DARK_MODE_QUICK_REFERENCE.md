# Dark Mode Quick Reference

## 🚀 Quick Start

```php
// Dark mode is automatically enabled - nothing to configure!
// Toggle appears in navigation on theme activation
```

## 📁 File Locations

```
saga-manager-theme/
├── assets/
│   ├── css/dark-mode.css              # All color variables & transitions
│   └── js/dark-mode.js                # Toggle logic & persistence
├── template-parts/
│   └── header-dark-mode-toggle.php    # Toggle button HTML
└── functions.php                       # Enqueue functions (lines 246-343)
```

## 🎨 CSS Variables

### Using Variables in Custom CSS

```css
/* Use these variables anywhere in your theme */
.my-custom-element {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--border-primary);
}

/* Variables automatically update on theme change */
```

### Available Variables

```css
/* Backgrounds */
--bg-primary       /* Main background */
--bg-secondary     /* Card backgrounds */
--bg-tertiary      /* Hover states */
--bg-elevated      /* Elevated elements */
--bg-overlay       /* Modal overlays */

/* Text Colors */
--text-primary     /* Body text */
--text-secondary   /* Subheadings */
--text-tertiary    /* Muted text */
--text-inverse     /* Button text */

/* Borders */
--border-primary   /* Default borders */
--border-secondary /* Emphasized borders */
--border-focus     /* Focus rings */

/* Accents */
--accent-primary         /* Links, buttons */
--accent-primary-hover   /* Hover states */
--accent-secondary       /* Secondary actions */
--accent-success         /* Success states */
--accent-warning         /* Warning states */
--accent-error           /* Error states */

/* Entity Types */
--entity-character
--entity-location
--entity-event
--entity-faction
--entity-artifact
--entity-concept

/* Shadows */
--shadow-sm, --shadow-md, --shadow-lg, --shadow-xl
```

## 💻 JavaScript API

### Basic Usage

```javascript
// Toggle theme
window.sagaDarkMode.toggle();

// Set specific theme
window.sagaDarkMode.setTheme('dark');
window.sagaDarkMode.setTheme('light');

// Get current theme
const theme = window.sagaDarkMode.getTheme(); // 'dark' or 'light'

// Check if dark mode active
if (window.sagaDarkMode.isDark()) {
    console.log('Dark mode is active');
}
```

### Listen for Theme Changes

```javascript
document.addEventListener('sagaThemeChange', function(event) {
    console.log('New theme:', event.detail.theme);
    console.log('Is dark:', event.detail.isDark);

    // Your custom logic here
    updateChart(event.detail.theme);
});
```

## 🎯 Common Customizations

### Change Toggle Position

```php
// In functions.php, replace line 308:

// Default: Inside navigation (right side)
add_action('generate_inside_navigation', 'saga_add_dark_mode_toggle_to_header', 10);

// Option 1: After header
add_action('generate_after_header', 'saga_add_dark_mode_toggle_to_header', 10);

// Option 2: Before header
add_action('generate_before_header', 'saga_add_dark_mode_toggle_to_header', 10);

// Option 3: Custom hook
add_action('your_custom_hook', 'saga_add_dark_mode_toggle_to_header', 10);
```

### Manually Place Toggle

```php
// In any template file:
<?php get_template_part('template-parts/header-dark-mode-toggle'); ?>
```

### Customize Colors

```css
/* In child theme style.css or Customizer Additional CSS */
:root {
    /* Light mode custom colors */
    --bg-primary: #f0f0f0;
    --accent-primary: #ff6b6b;
}

[data-theme="dark"] {
    /* Dark mode custom colors */
    --bg-primary: #0a0a0a;
    --accent-primary: #ff8787;
}
```

### Change Transition Speed

```css
/* In child theme style.css */
:root {
    --transition-duration: 0.5s; /* Default: 0.3s */
}
```

### Customize Toggle Button

```css
/* In child theme style.css */
.saga-dark-mode-toggle {
    width: 50px;          /* Default: 44px */
    height: 50px;         /* Default: 44px */
    border-radius: 8px;   /* Default: 50% (circle) */
    border-width: 3px;    /* Default: 2px */
}
```

## 🔧 Troubleshooting

### Toggle Not Appearing

```bash
# Check if files exist
ls assets/css/dark-mode.css
ls assets/js/dark-mode.js
ls template-parts/header-dark-mode-toggle.php

# Verify in browser console
console.log(window.sagaDarkMode);
# Should output: Object with methods

# Check if CSS/JS enqueued (view page source)
# Look for: dark-mode.css and dark-mode.js
```

### Theme Not Persisting

```javascript
// Check localStorage in browser console
localStorage.getItem('saga-theme-preference');
// Should return: 'light' or 'dark'

// Clear and test
localStorage.removeItem('saga-theme-preference');
window.sagaDarkMode.setTheme('dark');
localStorage.getItem('saga-theme-preference');
// Should return: 'dark'
```

### Colors Not Changing

```javascript
// Check HTML attribute in console
document.documentElement.getAttribute('data-theme');
// Should return: null (light) or 'dark'

// Check CSS variables
getComputedStyle(document.documentElement).getPropertyValue('--bg-primary');
// Should return: color value

// Force theme change
document.documentElement.setAttribute('data-theme', 'dark');
```

### Styles Conflicting

```css
/* Ensure your custom CSS uses variables */
/* ❌ WRONG - hard-coded colors */
.my-element {
    background: #ffffff;
    color: #000000;
}

/* ✅ CORRECT - uses CSS variables */
.my-element {
    background: var(--bg-primary);
    color: var(--text-primary);
}
```

## 🧪 Testing Commands

### Browser Console Tests

```javascript
// Run all automated tests
// Copy from DARK_MODE_TESTING.md and paste in console

// Quick manual tests:
window.sagaDarkMode.toggle();              // Should switch theme
window.sagaDarkMode.getTheme();           // Should return current theme
localStorage.getItem('saga-theme-preference'); // Should show saved theme
```

### Check Accessibility

```javascript
// Check button ARIA state
const btn = document.querySelector('.saga-dark-mode-toggle');
console.log(btn.getAttribute('aria-pressed'));  // Should be 'true' or 'false'
console.log(btn.getAttribute('aria-label'));    // Should be descriptive text
```

### Performance Test

```javascript
// Measure toggle speed
console.time('toggle');
window.sagaDarkMode.toggle();
console.timeEnd('toggle');
// Should be: < 10ms
```

## 📱 Mobile Considerations

```css
/* On mobile (<768px), toggle is fixed bottom-right */
/* Customize position: */
@media (max-width: 768px) {
    .saga-dark-mode-toggle {
        bottom: 2rem;  /* Default: 1rem */
        right: 2rem;   /* Default: 1rem */
    }
}
```

## ♿ Accessibility Features

### Keyboard Navigation
- **Tab:** Focus toggle button
- **Enter or Space:** Activate toggle
- **Shift+Tab:** Focus previous element

### Screen Readers
- Button announced as "Toggle dark mode"
- State announced: "pressed" or "not pressed"
- Label updates on theme change

### Visual
- Focus ring always visible (2px outline)
- Minimum 4.5:1 contrast ratio (WCAG AA)
- Respects `prefers-reduced-motion`
- Works with high contrast mode

## 🎨 Entity Type Colors

```css
/* Use these for custom entity styling */
.my-character-card {
    border-color: var(--entity-character); /* Purple */
}

.my-location-card {
    border-color: var(--entity-location);  /* Blue */
}

.my-event-card {
    border-color: var(--entity-event);     /* Orange */
}

.my-faction-card {
    border-color: var(--entity-faction);   /* Red */
}

.my-artifact-card {
    border-color: var(--entity-artifact);  /* Green */
}

.my-concept-card {
    border-color: var(--entity-concept);   /* Indigo */
}
```

## 🔐 Security Notes

- All HTML is properly escaped
- No eval() or dynamic code execution
- localStorage only stores theme preference (non-sensitive)
- XSS prevention via WordPress functions
- No server-side requests (client-only)

## 📊 Performance Tips

```php
// Preload dark mode assets for faster loading
function saga_preload_dark_mode_assets() {
    echo '<link rel="preload" href="' . SAGA_THEME_URI . '/assets/css/dark-mode.css" as="style">';
    echo '<link rel="preload" href="' . SAGA_THEME_URI . '/assets/js/dark-mode.js" as="script">';
}
add_action('wp_head', 'saga_preload_dark_mode_assets', 1);
```

## 🌐 Browser Support

| Browser | Support |
|---------|---------|
| Chrome 88+ | ✅ Full |
| Firefox 85+ | ✅ Full |
| Safari 14+ | ✅ Full |
| Edge 88+ | ✅ Full |
| IE 11 | ⚠️ Light only |

## 📚 Documentation

- **Full Guide:** `DARK_MODE_IMPLEMENTATION.md`
- **Testing:** `DARK_MODE_TESTING.md`
- **Summary:** `DARK_MODE_SUMMARY.md`
- **This Reference:** `DARK_MODE_QUICK_REFERENCE.md`

## 💡 Pro Tips

```javascript
// 1. Set theme based on user role
if (userRole === 'admin') {
    window.sagaDarkMode.setTheme('dark');
}

// 2. Auto-switch based on time
const hour = new Date().getHours();
if (hour >= 18 || hour <= 6) {
    window.sagaDarkMode.setTheme('dark');
}

// 3. Remember per-page preference
const pageTheme = sessionStorage.getItem('page-theme');
if (pageTheme) {
    window.sagaDarkMode.setTheme(pageTheme);
}

// 4. Sync with system changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    if (!localStorage.getItem('saga-theme-preference')) {
        window.sagaDarkMode.setTheme(e.matches ? 'dark' : 'light');
    }
});
```

## 🚨 Common Mistakes to Avoid

```css
/* ❌ DON'T hard-code colors */
.element { color: #000000; }

/* ✅ DO use CSS variables */
.element { color: var(--text-primary); }

/* ❌ DON'T use !important (breaks theming) */
.element { color: #000 !important; }

/* ✅ DO increase specificity if needed */
.parent .element { color: var(--text-primary); }

/* ❌ DON'T create custom dark mode styles */
.dark-mode .element { background: #000; }

/* ✅ DO use [data-theme] attribute */
[data-theme="dark"] .element { background: var(--bg-primary); }
```

## ⚡ Quick Commands

```bash
# Find all files using dark mode
grep -r "sagaDarkMode" .

# Check CSS variable usage
grep -r "var(--" assets/css/

# Verify localStorage key
grep -r "saga-theme-preference" assets/js/

# Test file sizes
ls -lh assets/css/dark-mode.css assets/js/dark-mode.js
```

## 🔄 Disable Dark Mode (if needed)

```php
// In functions.php, comment out or remove:
// remove_action('wp_enqueue_scripts', 'saga_enqueue_dark_mode_assets', 5);
// remove_action('generate_inside_navigation', 'saga_add_dark_mode_toggle_to_header', 10);
```

## 📞 Support

- Check: `DARK_MODE_IMPLEMENTATION.md` → Troubleshooting section
- Test: Run automated test suite in browser console
- Debug: Enable `WP_DEBUG` to see console logs
- Issues: Check browser console for errors

---

**Need help?** Refer to the comprehensive guides:
- Implementation details: `DARK_MODE_IMPLEMENTATION.md`
- Testing procedures: `DARK_MODE_TESTING.md`
- Full summary: `DARK_MODE_SUMMARY.md`
