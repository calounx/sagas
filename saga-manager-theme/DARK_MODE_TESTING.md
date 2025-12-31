# Dark Mode Testing Guide

## Quick Verification

Run these tests to verify dark mode implementation.

## 1. Visual Inspection

### Files Exist
```bash
# Verify all files created
ls -lh assets/css/dark-mode.css
ls -lh assets/js/dark-mode.js
ls -lh template-parts/header-dark-mode-toggle.php

# Check functions.php updated
grep -A 10 "saga_enqueue_dark_mode_assets" functions.php
```

### Browser Console Tests

Open browser console and run:

```javascript
// 1. Check global API exists
console.log(window.sagaDarkMode);
// Expected: Object with toggle, setTheme, getTheme, isDark methods

// 2. Test toggle functionality
window.sagaDarkMode.toggle();
// Expected: Theme switches, icons animate

// 3. Check current theme
console.log(window.sagaDarkMode.getTheme());
// Expected: "light" or "dark"

// 4. Test localStorage
console.log(localStorage.getItem('saga-theme-preference'));
// Expected: "light" or "dark"

// 5. Check HTML attribute
console.log(document.documentElement.getAttribute('data-theme'));
// Expected: null (light) or "dark"

// 6. Listen for theme changes
document.addEventListener('sagaThemeChange', (e) => {
    console.log('Theme changed:', e.detail);
});
window.sagaDarkMode.toggle();
// Expected: Console log with theme details
```

## 2. Functional Testing

### Toggle Button Tests

| Test | Steps | Expected Result | Pass/Fail |
|------|-------|-----------------|-----------|
| Button appears | Load page | Toggle button visible in navigation | ☐ |
| Click toggles | Click button | Theme switches | ☐ |
| Icons animate | Click button | Sun/moon rotates and fades | ☐ |
| Persistence | Reload page | Theme remains same | ☐ |
| ARIA state | Click button | aria-pressed updates | ☐ |
| Screen reader | Use screen reader | Announces "Toggle dark mode" | ☐ |

### Keyboard Navigation Tests

| Test | Key | Expected Result | Pass/Fail |
|------|-----|-----------------|-----------|
| Focus button | Tab | Button receives focus ring | ☐ |
| Toggle with Enter | Enter | Theme switches | ☐ |
| Toggle with Space | Space | Theme switches | ☐ |
| Focus visible | Tab | Focus ring clearly visible | ☐ |

### Color Contrast Tests

Use WebAIM Contrast Checker: https://webaim.org/resources/contrastchecker/

#### Light Mode
| Element | Foreground | Background | Ratio | Pass/Fail |
|---------|------------|------------|-------|-----------|
| Body text | #1f2937 | #ffffff | 15.8:1 | ☐ AAA |
| Links | #3b82f6 | #ffffff | 4.8:1 | ☐ AA |
| Secondary | #6b7280 | #ffffff | 4.6:1 | ☐ AA |

#### Dark Mode
| Element | Foreground | Background | Ratio | Pass/Fail |
|---------|------------|------------|-------|-----------|
| Body text | #f9fafb | #111827 | 16.1:1 | ☐ AAA |
| Links | #60a5fa | #111827 | 9.7:1 | ☐ AAA |
| Secondary | #d1d5db | #111827 | 12.6:1 | ☐ AAA |

## 3. Browser Testing

### Desktop Browsers

| Browser | Version | Toggle Works | Colors Correct | Persist | Pass/Fail |
|---------|---------|--------------|----------------|---------|-----------|
| Chrome | Latest | ☐ | ☐ | ☐ | ☐ |
| Firefox | Latest | ☐ | ☐ | ☐ | ☐ |
| Safari | Latest | ☐ | ☐ | ☐ | ☐ |
| Edge | Latest | ☐ | ☐ | ☐ | ☐ |

### Mobile Browsers

| Browser | Device | Toggle Works | Position | Touch Size | Pass/Fail |
|---------|--------|--------------|----------|------------|-----------|
| Safari | iPhone | ☐ | Fixed bottom-right | ☐ 44x44px | ☐ |
| Chrome | Android | ☐ | Fixed bottom-right | ☐ 44x44px | ☐ |

## 4. Accessibility Testing

### Screen Reader Tests

| Screen Reader | OS | Button Announced | State Updates | Pass/Fail |
|---------------|----|--------------------|---------------|-----------|
| NVDA | Windows | ☐ | ☐ | ☐ |
| JAWS | Windows | ☐ | ☐ | ☐ |
| VoiceOver | macOS | ☐ | ☐ | ☐ |
| VoiceOver | iOS | ☐ | ☐ | ☐ |
| TalkBack | Android | ☐ | ☐ | ☐ |

### WCAG Compliance

| Criterion | Level | Status | Pass/Fail |
|-----------|-------|--------|-----------|
| 1.4.3 Contrast (Minimum) | AA | All text meets 4.5:1 | ☐ |
| 2.1.1 Keyboard | A | All functions keyboard accessible | ☐ |
| 2.4.7 Focus Visible | AA | Focus indicators visible | ☐ |
| 4.1.2 Name, Role, Value | A | ARIA attributes correct | ☐ |
| 2.3.3 Animation from Interactions | AAA | Respects reduced-motion | ☐ |

## 5. Performance Testing

### Load Time Impact

```javascript
// Measure dark mode CSS load time
performance.getEntriesByName('http://yoursite.com/wp-content/themes/saga-manager-theme/assets/css/dark-mode.css')[0].duration
// Expected: < 50ms

// Measure dark mode JS execution time
performance.getEntriesByName('http://yoursite.com/wp-content/themes/saga-manager-theme/assets/js/dark-mode.js')[0].duration
// Expected: < 50ms
```

### Toggle Performance

```javascript
// Measure toggle speed
console.time('toggle');
window.sagaDarkMode.toggle();
console.timeEnd('toggle');
// Expected: < 10ms
```

### localStorage Write Frequency

```javascript
// Monitor localStorage writes
let writeCount = 0;
const originalSetItem = localStorage.setItem;
localStorage.setItem = function(key, value) {
    if (key === 'saga-theme-preference') {
        writeCount++;
        console.log('localStorage write #' + writeCount);
    }
    return originalSetItem.apply(this, arguments);
};

// Rapidly toggle 10 times
for (let i = 0; i < 10; i++) {
    window.sagaDarkMode.toggle();
}

// Wait 500ms and check
setTimeout(() => {
    console.log('Total writes:', writeCount);
    // Expected: 1-2 (debounced)
}, 500);
```

## 6. Responsive Testing

### Breakpoint Tests

| Breakpoint | Width | Button Position | Visible | Pass/Fail |
|------------|-------|-----------------|---------|-----------|
| Desktop | 1920px | Inside navigation | ☐ | ☐ |
| Laptop | 1440px | Inside navigation | ☐ | ☐ |
| Tablet | 768px | Inside navigation | ☐ | ☐ |
| Mobile | 375px | Fixed bottom-right | ☐ | ☐ |

### Touch Target Size

Verify minimum 44x44px on all devices:

```javascript
// Measure button size
const button = document.querySelector('.saga-dark-mode-toggle');
const rect = button.getBoundingClientRect();
console.log(`Width: ${rect.width}px, Height: ${rect.height}px`);
// Expected: Both >= 44
```

## 7. System Preference Testing

### Test Sequence

1. **Clear localStorage**: `localStorage.removeItem('saga-theme-preference')`
2. **Set system to dark**:
   - macOS: System Preferences → General → Appearance → Dark
   - Windows: Settings → Personalization → Colors → Dark
3. **Reload page**: Should auto-detect dark mode
4. **Toggle manually**: Should override system preference
5. **Reload page**: Should remember manual preference
6. **Clear localStorage again**: Should revert to system preference

### Console Test

```javascript
// 1. Clear saved preference
localStorage.removeItem('saga-theme-preference');

// 2. Check system preference
const isDarkPreferred = window.matchMedia('(prefers-color-scheme: dark)').matches;
console.log('System prefers dark:', isDarkPreferred);

// 3. Reload and verify
location.reload();
// Expected: Theme matches system preference
```

## 8. Edge Cases

### LocalStorage Disabled

Test in private/incognito mode with localStorage blocked:

| Test | Expected Behavior | Pass/Fail |
|------|-------------------|-----------|
| Page loads | No errors in console | ☐ |
| Toggle works | Theme changes (no persistence) | ☐ |
| Reload page | Reverts to default/system preference | ☐ |
| Console warning | "localStorage not available" logged | ☐ |

### No JavaScript

Disable JavaScript and test:

| Test | Expected Behavior | Pass/Fail |
|------|-------------------|-----------|
| Page loads | Uses system preference via CSS | ☐ |
| Toggle button | Hidden or non-functional | ☐ |
| Theme applied | Via prefers-color-scheme media query | ☐ |

### Multiple Tabs

1. Open site in two tabs
2. Toggle in Tab 1
3. Check Tab 2

| Test | Expected Behavior | Pass/Fail |
|------|-------------------|-----------|
| Tab 2 updates | On focus/reload, theme syncs | ☐ |
| No conflicts | Both tabs use same localStorage key | ☐ |

## 9. Integration Testing

### GeneratePress Compatibility

| Element | Light Mode | Dark Mode | Pass/Fail |
|---------|------------|-----------|-----------|
| Header | Correct colors | Correct colors | ☐ |
| Navigation | Correct colors | Correct colors | ☐ |
| Footer | Correct colors | Correct colors | ☐ |
| Sidebar | Correct colors | Correct colors | ☐ |
| Buttons | Correct colors | Correct colors | ☐ |

### Saga Entity Pages

| Page Type | Light Mode | Dark Mode | Pass/Fail |
|-----------|------------|-----------|-----------|
| Entity Single | Correct colors | Correct colors | ☐ |
| Entity Archive | Correct colors | Correct colors | ☐ |
| Entity Card | Correct colors | Correct colors | ☐ |
| Entity Badge | Correct colors | Correct colors | ☐ |
| Relationships | Correct colors | Correct colors | ☐ |
| Timeline | Correct colors | Correct colors | ☐ |

## 10. Automated Testing Script

Save as `test-dark-mode.js` and run in console:

```javascript
/**
 * Automated Dark Mode Test Suite
 */
(async function testDarkMode() {
    const tests = [];

    // Test 1: Global API exists
    tests.push({
        name: 'Global API exists',
        test: () => window.sagaDarkMode !== undefined,
        expected: true
    });

    // Test 2: Initial theme detection
    tests.push({
        name: 'Initial theme set',
        test: () => ['light', 'dark'].includes(window.sagaDarkMode.getTheme()),
        expected: true
    });

    // Test 3: Toggle functionality
    const initialTheme = window.sagaDarkMode.getTheme();
    window.sagaDarkMode.toggle();
    tests.push({
        name: 'Toggle changes theme',
        test: () => window.sagaDarkMode.getTheme() !== initialTheme,
        expected: true
    });

    // Test 4: HTML attribute updates
    tests.push({
        name: 'HTML attribute correct',
        test: () => {
            const isDark = window.sagaDarkMode.isDark();
            const hasAttr = document.documentElement.hasAttribute('data-theme');
            return isDark ? hasAttr : !hasAttr;
        },
        expected: true
    });

    // Test 5: localStorage persistence
    tests.push({
        name: 'localStorage saves preference',
        test: () => {
            const stored = localStorage.getItem('saga-theme-preference');
            return ['light', 'dark'].includes(stored);
        },
        expected: true
    });

    // Test 6: Button ARIA state
    const button = document.querySelector('.saga-dark-mode-toggle');
    tests.push({
        name: 'Button has ARIA attributes',
        test: () => button && button.hasAttribute('aria-pressed'),
        expected: true
    });

    // Test 7: Button accessibility
    tests.push({
        name: 'Button has aria-label',
        test: () => button && button.hasAttribute('aria-label'),
        expected: true
    });

    // Test 8: CSS custom properties
    tests.push({
        name: 'CSS variables defined',
        test: () => {
            const style = getComputedStyle(document.documentElement);
            return style.getPropertyValue('--bg-primary').trim() !== '';
        },
        expected: true
    });

    // Run all tests
    console.log('🧪 Running Dark Mode Tests...\n');
    let passed = 0;
    let failed = 0;

    tests.forEach((test, index) => {
        const result = test.test();
        const status = result === test.expected ? '✅ PASS' : '❌ FAIL';

        if (result === test.expected) {
            passed++;
        } else {
            failed++;
        }

        console.log(`${index + 1}. ${test.name}: ${status}`);
    });

    console.log(`\n📊 Results: ${passed} passed, ${failed} failed, ${tests.length} total`);

    if (failed === 0) {
        console.log('🎉 All tests passed!');
    } else {
        console.log('⚠️  Some tests failed. Check implementation.');
    }
})();
```

## Test Results Template

Copy this template to document your test results:

```
# Dark Mode Test Results

**Date:** YYYY-MM-DD
**Tester:** Name
**Environment:** Browser/OS

## Summary
- Total Tests: X
- Passed: X
- Failed: X
- Success Rate: X%

## Failed Tests
1. [Test Name] - [Reason]
2. [Test Name] - [Reason]

## Browser Issues
- [Browser Name]: [Issue Description]

## Accessibility Issues
- [Issue Description]

## Performance Issues
- [Issue Description]

## Recommendations
1. [Recommendation]
2. [Recommendation]

## Screenshots
- [Attach screenshots of issues]

## Sign-off
☐ All critical tests passed
☐ No accessibility blockers
☐ Ready for production
```

## Continuous Testing

Add to your CI/CD pipeline:

```yaml
# .github/workflows/test-dark-mode.yml
name: Dark Mode Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Check files exist
        run: |
          test -f assets/css/dark-mode.css
          test -f assets/js/dark-mode.js
          test -f template-parts/header-dark-mode-toggle.php
      - name: Validate CSS
        run: npx stylelint assets/css/dark-mode.css
      - name: Validate JavaScript
        run: npx eslint assets/js/dark-mode.js
      - name: Check WCAG compliance
        run: npx pa11y-ci --config .pa11yci.json
```

## Support

If tests fail, check:
1. Browser console for errors
2. WordPress debug log
3. Browser compatibility
4. Cache issues
5. Plugin conflicts

For help, reference `DARK_MODE_IMPLEMENTATION.md` troubleshooting section.
