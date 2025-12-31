# Progressive Web App (PWA) Implementation

Complete PWA implementation for the Saga Manager theme with offline capabilities, background sync, and native app-like experience.

## Features

### Core PWA Features
- ✅ Service Worker with multi-strategy caching
- ✅ Web App Manifest with icons and metadata
- ✅ Install prompt with custom UI
- ✅ Offline fallback page
- ✅ Background sync for annotations and bookmarks
- ✅ Push notifications support
- ✅ Offline indicator
- ✅ Sync status indicator

### Offline Capabilities
- Cache visited entity pages
- Cache search results
- Cache relationship graphs
- Store user annotations offline
- Store bookmarks offline
- Queue writes for background sync
- Show cached content with offline badge

## Files Created

### Core PWA Files
```
saga-manager-theme/
├── sw.js                           # Service worker
├── manifest.json                   # App manifest
├── offline.html                    # Offline fallback page
├── generate-pwa-icons.sh           # Icon generator script
├── inc/
│   └── pwa-headers.php            # WordPress integration
├── assets/
│   ├── js/
│   │   ├── pwa-install.js         # Install prompt handler
│   │   └── offline-sync.js        # Background sync manager
│   ├── css/
│   │   └── pwa.css                # PWA component styles
│   └── images/                    # PWA icons (to be generated)
```

## Installation

### 1. Generate PWA Icons

Run the icon generator script to create all required icons:

```bash
cd /home/calounx/repositories/sagas/saga-manager-theme
./generate-pwa-icons.sh
```

For production, provide a source image (1024x1024 PNG):

```bash
./generate-pwa-icons.sh path/to/your-icon.png
```

### 2. Configure HTTPS

**CRITICAL:** Service workers require HTTPS. Enable SSL on your WordPress site:

```bash
# Using Let's Encrypt (recommended)
sudo certbot --nginx -d yourdomain.com

# Or configure your hosting provider's SSL
```

### 3. Test PWA Installation

1. Visit your site on mobile (Chrome/Edge/Safari)
2. Look for "Add to Home Screen" prompt
3. Install the app
4. Test offline functionality by:
   - Enabling airplane mode
   - Visiting cached pages
   - Creating annotations (will sync when online)

## Service Worker Caching Strategies

### Network First (API calls, dynamic content)
```javascript
// Fresh data when online, cached fallback when offline
/wp-json/saga/v1/*
/wp-admin/admin-ajax.php
```

### Cache First (Static assets)
```javascript
// Fast loading from cache
*.css, *.js, *.jpg, *.png, *.svg, *.woff2
```

### Stale While Revalidate (Entity pages)
```javascript
// Instant cache response, background update
/saga_entity/*
/entity/*
```

### Network Only (Admin pages)
```javascript
// Always fresh, no caching
/wp-admin/*
```

## Offline Sync

### How It Works

1. **User creates annotation/bookmark while offline**
   - Stored in IndexedDB
   - Added to sync queue
   - Shows "queued" status

2. **Connection restored**
   - Background sync triggers automatically
   - Queued items sent to server
   - Queue cleared on success

3. **Manual sync**
   - Click sync button anytime
   - Shows sync progress
   - Displays last sync time

### API Endpoints

#### Save Annotation
```
POST /wp-json/saga/v1/annotations
{
  "entity_id": 123,
  "content": "My annotation",
  "position": 42
}
```

#### Save Bookmark
```
POST /wp-json/saga/v1/bookmarks
{
  "entity_id": 123,
  "title": "Bookmark title",
  "note": "Optional note"
}
```

## JavaScript Events

### PWA Install Events

```javascript
// Install prompt shown
window.addEventListener('saga:install-prompt', (event) => {
  console.log('Install prompt shown');
});

// App installed
window.addEventListener('saga:app-installed', (event) => {
  console.log('App installed successfully');
});
```

### Online/Offline Events

```javascript
// Online status changed
window.addEventListener('saga:online-status', (event) => {
  if (event.detail.online) {
    console.log('Connection restored');
  } else {
    console.log('Connection lost');
  }
});
```

### Sync Events

```javascript
// Sync queue updated
window.addEventListener('saga:sync-queue-updated', (event) => {
  console.log('Queued items:', event.detail);
  // { annotations: 2, bookmarks: 1 }
});
```

## Customization

### Cache Size Limits

Edit `sw.js` to adjust cache limits:

```javascript
const MAX_CACHE_SIZE = 50; // Maximum items per cache
const MAX_CACHE_AGE = 7 * 24 * 60 * 60 * 1000; // 7 days
```

### Install Prompt Timing

Edit `assets/js/pwa-install.js`:

```javascript
const PWAInstall = {
  minPageViews: 2, // Show after 2 page views
  // ...
};
```

### Sync Interval

Edit `assets/js/offline-sync.js`:

```javascript
// Sync every 5 minutes
setInterval(() => {
  // ...
}, 5 * 60 * 1000);
```

### Theme Colors

Edit `manifest.json`:

```json
{
  "background_color": "#ffffff",
  "theme_color": "#1f2937"
}
```

## Browser Support

### Full Support
- ✅ Chrome/Edge 90+ (Android, Desktop, iOS)
- ✅ Safari 15.4+ (iOS, macOS)
- ✅ Firefox 90+ (Android, Desktop)
- ✅ Samsung Internet 15+
- ✅ Opera 76+

### Limited Support
- ⚠️ Safari < 15.4 (no background sync)
- ⚠️ Firefox iOS (uses Safari engine)

### Not Supported
- ❌ Internet Explorer (all versions)
- ❌ Opera Mini

## Performance

### Cache Storage Usage

```javascript
// Check cache storage quota
navigator.storage.estimate().then(estimate => {
  console.log(`Used: ${estimate.usage} bytes`);
  console.log(`Quota: ${estimate.quota} bytes`);
  console.log(`Percentage: ${(estimate.usage / estimate.quota * 100).toFixed(2)}%`);
});
```

### Cache Cleanup

Caches are automatically cleaned:
- LRU eviction when cache size limit reached
- Automatic deletion of entries older than 7 days
- Manual cleanup available via service worker message

```javascript
// Clear all caches
navigator.serviceWorker.controller.postMessage({
  type: 'CLEAR_CACHE'
});
```

## Debugging

### Chrome DevTools

1. Open DevTools (F12)
2. Go to **Application** tab
3. Check:
   - **Service Workers**: Registration status
   - **Cache Storage**: Cached resources
   - **IndexedDB**: Offline data
   - **Manifest**: App metadata

### Console Logging

Enable debug mode by adding to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Service worker logs:
```javascript
// In sw.js
console.log('[SW] Service worker registered');
console.log('[SW] Cache hit:', request.url);
```

### Common Issues

#### Service Worker Not Registering

**Cause:** HTTPS not enabled or scope issues

**Fix:**
```javascript
// Check registration
navigator.serviceWorker.getRegistrations().then(registrations => {
  console.log('Registrations:', registrations);
});
```

#### Install Prompt Not Showing

**Cause:** Already installed or browser criteria not met

**Fix:**
- Clear site data in Chrome DevTools
- Check browser support
- Verify manifest.json is valid

#### Background Sync Not Working

**Cause:** Browser doesn't support Background Sync API

**Fix:**
- Manual sync fallback is automatically used
- Check browser support: `'sync' in self.registration`

## Security

### HTTPS Requirement

PWA features **require HTTPS** in production:
- Service workers won't register on HTTP
- Install prompt won't show
- Background sync unavailable

Exception: `localhost` for development

### Content Security Policy

Headers added in `pwa-headers.php`:

```php
Content-Security-Policy: default-src 'self';
  script-src 'self' 'unsafe-inline' 'unsafe-eval';
  style-src 'self' 'unsafe-inline';
```

### Nonce Verification

All sync endpoints verify nonces:

```php
check_ajax_referer('saga_sync', 'nonce');
```

## Testing Checklist

### Desktop Testing
- [ ] Service worker registers successfully
- [ ] Install prompt shows after 2 page views
- [ ] Install button works in header
- [ ] Offline page displays when disconnected
- [ ] Cached pages load when offline
- [ ] Online/offline indicator works

### Mobile Testing (Android)
- [ ] Add to Home Screen prompt appears
- [ ] App installs with correct icon
- [ ] Splash screen shows on launch
- [ ] Standalone mode works (no browser UI)
- [ ] Navigation gestures work
- [ ] Cached content accessible offline

### Mobile Testing (iOS)
- [ ] Add to Home Screen works (Safari)
- [ ] App icon displays correctly
- [ ] Splash screen shows
- [ ] Standalone mode works
- [ ] Theme color applied to status bar
- [ ] Cached content accessible offline

### Offline Functionality
- [ ] Entity pages cache when visited
- [ ] Search results cache properly
- [ ] Annotations save to IndexedDB
- [ ] Bookmarks save to IndexedDB
- [ ] Sync queue displays count
- [ ] Manual sync button works
- [ ] Auto-sync on reconnection

### Performance
- [ ] First load < 2s on 4G
- [ ] Cached page load < 500ms
- [ ] Service worker update < 1s
- [ ] IndexedDB operations < 100ms

## Maintenance

### Updating Service Worker

When updating `sw.js`, increment `CACHE_VERSION`:

```javascript
const CACHE_VERSION = 'saga-v2'; // Changed from v1
```

This forces cache cleanup on next load.

### Monitoring

Track PWA metrics:

```javascript
// In pwa-install.js
function trackInstallEvent(action) {
  if (typeof gtag !== 'undefined') {
    gtag('event', 'pwa_install', {
      event_category: 'PWA',
      event_label: action
    });
  }
}
```

Available events:
- `banner_shown`
- `accepted`
- `dismissed`
- `dismissed_forever`
- `installed`

## Future Enhancements

### Planned Features
- [ ] Periodic background sync for automatic updates
- [ ] Share Target API for sharing to app
- [ ] Badge API for unread notifications
- [ ] File handling for opening saga files
- [ ] Shortcuts API for quick actions

### Experimental Features
- [ ] Web Share API for entity sharing
- [ ] Clipboard API for copy/paste
- [ ] Contact Picker for character relationships
- [ ] Wake Lock API for reading mode

## Resources

### Documentation
- [MDN: Progressive Web Apps](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Google: PWA Training](https://web.dev/progressive-web-apps/)
- [W3C: Service Workers](https://www.w3.org/TR/service-workers/)

### Tools
- [PWA Builder](https://www.pwabuilder.com/) - Generate PWA assets
- [Lighthouse](https://developers.google.com/web/tools/lighthouse) - PWA auditing
- [Workbox](https://developers.google.com/web/tools/workbox) - Service worker library

### Testing
- [Chrome DevTools](https://developers.google.com/web/tools/chrome-devtools)
- [PWA Checklist](https://web.dev/pwa-checklist/)
- [Manifest Validator](https://manifest-validator.appspot.com/)

## Support

For issues or questions about the PWA implementation:

1. Check browser console for errors
2. Review Chrome DevTools Application tab
3. Test on different browsers/devices
4. Check HTTPS configuration
5. Verify service worker registration

## License

This PWA implementation is part of the Saga Manager theme and follows the same license.
