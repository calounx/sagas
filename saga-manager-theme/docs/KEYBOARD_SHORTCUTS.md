# Keyboard Shortcuts and Command Palette

The Saga Manager theme includes a comprehensive keyboard shortcuts system with a command palette for power users.

## Features

### Command Palette
- **Ctrl/Cmd + K**: Open command palette
- Fuzzy search for all available commands
- Recent commands shown at top
- Keyboard navigation (arrow keys, Enter)
- Categorized command organization
- Dark theme support

### Navigation Shortcuts
- **G then H**: Go to Home
- **G then S**: Go to Search
- **G then C**: Go to Collections
- **G then A**: Go to Annotations
- **G then T**: Go to Timeline
- **G then R**: Go to Relationship Graph

### Action Shortcuts
- **N**: New Annotation (requires login)
- **B**: Bookmark current entity (requires login)
- **R**: Toggle Reading Mode
- **S**: Focus Search Bar
- **Shift + S**: Share current entity

### Appearance Shortcuts
- **D**: Toggle Dark Mode
- **Ctrl/Cmd + =**: Increase Font Size
- **Ctrl/Cmd + -**: Decrease Font Size

### Filter Shortcuts (on search pages)
- **1**: Filter by Characters
- **2**: Filter by Locations
- **3**: Filter by Events
- **4**: Filter by Factions
- **5**: Filter by Artifacts
- **6**: Filter by Concepts
- **0**: Clear All Filters

### Help Shortcuts
- **?**: Show Keyboard Shortcuts Help
- **Shift + ?**: Open Help Documentation

### System Shortcuts
- **Esc**: Close modals and palettes

## Architecture

### Files

```
saga-manager-theme/
├── inc/
│   └── command-registry.php          # Command definitions and registry
├── assets/
│   ├── js/
│   │   ├── keyboard-shortcuts.js     # Shortcut handler with sequence support
│   │   └── command-palette.js        # Command palette UI with fuzzy search
│   └── css/
│       └── command-palette.css       # Styling with dark mode support
└── template-parts/
    └── shortcuts-help.php            # Help overlay template
```

### Command Registry

Commands are defined in `inc/command-registry.php` with the following structure:

```php
[
    'id' => 'unique-command-id',
    'label' => 'Command Label',
    'description' => 'Optional description',
    'keys' => 'G H',                  // Keyboard shortcut
    'sequence' => true,               // true for sequences (G then H), false for combinations (Ctrl+K)
    'action' => 'functionName',       // Action to execute or URL
    'icon' => '🏠',                   // Optional emoji icon
    'category' => 'navigation',       // Category for grouping
    'requiresAuth' => false,          // Optional: requires logged-in user
    'requiresEntity' => false,        // Optional: requires entity page
    'requiresSearch' => false,        // Optional: requires search context
]
```

### JavaScript API

The keyboard shortcuts system exposes global objects for integration:

```javascript
// Execute a command programmatically
window.sagaShortcuts.executeCommand(command);

// Open command palette
window.sagaPalette.open();

// Close command palette
window.sagaPalette.close();

// Toggle command palette
window.sagaPalette.toggle();
```

### Custom Events

The system dispatches custom events for integration:

```javascript
// Filter by entity type
document.addEventListener('saga:filterByType', (e) => {
    console.log('Filter by:', e.detail.type);
});

// Clear all filters
document.addEventListener('saga:clearFilters', () => {
    console.log('Filters cleared');
});

// Open annotation modal
document.addEventListener('saga:openAnnotation', () => {
    console.log('Open annotation');
});

// Show notification
document.addEventListener('saga:notify', (e) => {
    console.log('Notification:', e.detail.message, e.detail.type);
});
```

## Adding Custom Commands

To add custom commands, use the WordPress filter:

```php
add_filter('saga_keyboard_commands', function($commands) {
    $commands['custom'] = [
        [
            'id' => 'my-custom-command',
            'label' => 'My Custom Command',
            'keys' => 'X',
            'sequence' => false,
            'action' => home_url('/custom-page/'),
            'icon' => '⚡',
            'category' => 'custom'
        ]
    ];

    return $commands;
});
```

## Accessibility

### Screen Reader Support
- ARIA labels on all interactive elements
- Live regions for announcements
- Focus management for modals
- Keyboard navigation throughout

### Keyboard Accessibility
- All features accessible via keyboard
- Focus trap in modals
- Visual focus indicators
- Skip shortcuts when typing in forms

### Visual Accessibility
- High contrast mode support
- Reduced motion support
- Configurable font sizes
- Dark mode support

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (limited support for keyboard shortcuts)

## Performance

- Lazy loading of command palette
- Debounced search (100ms)
- Local storage for recent commands
- Minimal DOM manipulation

## Customization

### Custom Shortcuts

Users can customize shortcuts via localStorage:

```javascript
const customShortcuts = {
    'bookmark': 'Shift+B',
    'dark-mode': 'Shift+D'
};

localStorage.setItem('sagaCustomShortcuts', JSON.stringify(customShortcuts));
```

### Styling

The command palette respects CSS custom properties:

```css
:root {
    --color-primary: #2563eb;
    --color-background: #ffffff;
    --color-text: #1f2937;
    --color-border: #e5e7eb;
}

.dark-mode {
    --color-primary-dark: #3b82f6;
    --color-background-dark: #1a1a1a;
    --color-text-dark: #f3f4f6;
    --color-border-dark: #374151;
}
```

## Troubleshooting

### Shortcuts Not Working

1. Check that scripts are loaded: View source and look for `saga-keyboard-shortcuts.js`
2. Check browser console for errors
3. Ensure you're not in a text input field
4. Check for conflicts with browser extensions

### Command Palette Not Opening

1. Press Ctrl/Cmd + K
2. Check that `sagaCommands` is defined in console
3. Verify command-palette.js is loaded
4. Check for JavaScript errors

### Sequences Not Recognized

1. Ensure you press keys in order (not simultaneously)
2. Check sequence timeout (1 second between keys)
3. Look for sequence indicator in bottom-right corner

## Future Enhancements

- [ ] Command history with statistics
- [ ] Custom command creation UI
- [ ] Shortcut conflict detection
- [ ] Command palette themes
- [ ] Mobile gesture support
- [ ] Voice command integration
- [ ] Command suggestions based on context
- [ ] Bulk actions via command palette
