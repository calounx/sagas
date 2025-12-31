<?php
/**
 * PWA Headers and WordPress Integration
 *
 * Handles:
 * - Service worker registration
 * - Manifest link
 * - PWA meta tags
 * - Theme color
 * - Apple touch icons
 * - Security headers for HTTPS
 *
 * @package SagaManager
 * @version 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add PWA meta tags to head
 */
function saga_pwa_meta_tags(): void
{
    $theme_uri = get_template_directory_uri();
    ?>
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#1f2937">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Saga Manager">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Saga Manager">

    <!-- Manifest -->
    <link rel="manifest" href="<?php echo esc_url($theme_uri . '/manifest.json'); ?>">

    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo esc_url($theme_uri . '/assets/images/icon-72.png'); ?>">
    <link rel="apple-touch-icon" sizes="96x96" href="<?php echo esc_url($theme_uri . '/assets/images/icon-96.png'); ?>">
    <link rel="apple-touch-icon" sizes="128x128" href="<?php echo esc_url($theme_uri . '/assets/images/icon-128.png'); ?>">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo esc_url($theme_uri . '/assets/images/icon-144.png'); ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url($theme_uri . '/assets/images/icon-152.png'); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo esc_url($theme_uri . '/assets/images/icon-192.png'); ?>">
    <link rel="apple-touch-icon" sizes="384x384" href="<?php echo esc_url($theme_uri . '/assets/images/icon-384.png'); ?>">
    <link rel="apple-touch-icon" sizes="512x512" href="<?php echo esc_url($theme_uri . '/assets/images/icon-512.png'); ?>">

    <!-- Apple Splash Screens -->
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-2048x2732.png'); ?>" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-1668x2388.png'); ?>" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-1536x2048.png'); ?>" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-1125x2436.png'); ?>" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-1242x2688.png'); ?>" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-828x1792.png'); ?>" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-1242x2208.png'); ?>" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-750x1334.png'); ?>" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="<?php echo esc_url($theme_uri . '/assets/images/splash-640x1136.png'); ?>" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">

    <!-- MS Tile -->
    <meta name="msapplication-TileColor" content="#1f2937">
    <meta name="msapplication-TileImage" content="<?php echo esc_url($theme_uri . '/assets/images/icon-144.png'); ?>">
    <meta name="msapplication-config" content="<?php echo esc_url($theme_uri . '/browserconfig.xml'); ?>">
    <?php
}
add_action('wp_head', 'saga_pwa_meta_tags');

/**
 * Register service worker
 */
function saga_register_service_worker(): void
{
    $theme_uri = get_template_directory_uri();
    ?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?php echo esc_url($theme_uri . '/sw.js'); ?>', {
                scope: '/'
            }).then(function(registration) {
                console.log('[PWA] Service worker registered:', registration.scope);

                // Check for updates on page load
                registration.update();
            }).catch(function(error) {
                console.error('[PWA] Service worker registration failed:', error);
            });
        });
    }
    </script>
    <?php
}
add_action('wp_footer', 'saga_register_service_worker');

/**
 * Enqueue PWA scripts
 */
function saga_enqueue_pwa_scripts(): void
{
    $theme_uri = get_template_directory_uri();
    $theme_version = wp_get_theme()->get('Version');

    // PWA Install handler
    wp_enqueue_script(
        'saga-pwa-install',
        $theme_uri . '/assets/js/pwa-install.js',
        [],
        $theme_version,
        true
    );

    // Offline sync manager
    wp_enqueue_script(
        'saga-offline-sync',
        $theme_uri . '/assets/js/offline-sync.js',
        [],
        $theme_version,
        true
    );

    // Pass data to JavaScript
    wp_localize_script('saga-offline-sync', 'sagaVars', [
        'nonce' => wp_create_nonce('saga_sync'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('saga/v1/'),
        'isOnline' => true, // Will be updated by JS
    ]);
}
add_action('wp_enqueue_scripts', 'saga_enqueue_pwa_scripts');

/**
 * Add PWA styles
 */
function saga_enqueue_pwa_styles(): void
{
    $theme_uri = get_template_directory_uri();
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'saga-pwa-styles',
        $theme_uri . '/assets/css/pwa.css',
        [],
        $theme_version
    );
}
add_action('wp_enqueue_scripts', 'saga_enqueue_pwa_styles');

/**
 * Add offline indicator to body
 */
function saga_offline_indicator(): void
{
    ?>
    <div class="offline-indicator" aria-live="polite" hidden>
        <div class="offline-indicator-content">
            <svg class="offline-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3"></path>
            </svg>
            <span>You're offline. Some features may be limited.</span>
        </div>
    </div>
    <?php
}
add_action('wp_body_open', 'saga_offline_indicator');

/**
 * Add sync status indicator
 */
function saga_sync_status_indicator(): void
{
    ?>
    <div class="sync-status-container">
        <div class="sync-status-indicator">
            <svg class="sync-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span class="sync-status-text">Ready to sync</span>
            <span class="sync-queue-badge" style="display: none;">0</span>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'saga_sync_status_indicator', 5);

/**
 * Add security headers for PWA
 */
function saga_pwa_security_headers(): void
{
    // Only on HTTPS
    if (!is_ssl()) {
        return;
    }

    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:;");

    // Permissions Policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // X-Content-Type-Options
    header("X-Content-Type-Options: nosniff");

    // X-Frame-Options
    header("X-Frame-Options: SAMEORIGIN");
}
add_action('send_headers', 'saga_pwa_security_headers');

/**
 * Add service worker to allowed file types
 */
function saga_allow_service_worker_mime_type(array $mimes): array
{
    $mimes['js'] = 'application/javascript';
    $mimes['json'] = 'application/json';
    $mimes['webmanifest'] = 'application/manifest+json';

    return $mimes;
}
add_filter('upload_mimes', 'saga_allow_service_worker_mime_type');

/**
 * Add manifest to REST API
 */
function saga_register_manifest_endpoint(): void
{
    register_rest_route('saga/v1', '/manifest', [
        'methods' => 'GET',
        'callback' => 'saga_get_manifest',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'saga_register_manifest_endpoint');

/**
 * Get manifest data
 */
function saga_get_manifest(): array
{
    $theme_uri = get_template_directory_uri();
    $site_name = get_bloginfo('name');
    $site_description = get_bloginfo('description');

    return [
        'name' => $site_name . ' - Saga Manager',
        'short_name' => $site_name,
        'description' => $site_description ?: 'Explore and manage complex fictional universes',
        'start_url' => home_url('/'),
        'scope' => home_url('/'),
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'background_color' => '#ffffff',
        'theme_color' => '#1f2937',
        'icons' => [
            [
                'src' => $theme_uri . '/assets/images/icon-72.png',
                'sizes' => '72x72',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-96.png',
                'sizes' => '96x96',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-128.png',
                'sizes' => '128x128',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-144.png',
                'sizes' => '144x144',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-152.png',
                'sizes' => '152x152',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-384.png',
                'sizes' => '384x384',
                'type' => 'image/png',
            ],
            [
                'src' => $theme_uri . '/assets/images/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ];
}

/**
 * Add browserconfig.xml for Windows tiles
 */
function saga_browserconfig_xml(): void
{
    $theme_uri = get_template_directory_uri();

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="utf-8"?>';
    ?>
    <browserconfig>
        <msapplication>
            <tile>
                <square70x70logo src="<?php echo esc_url($theme_uri . '/assets/images/icon-72.png'); ?>"/>
                <square150x150logo src="<?php echo esc_url($theme_uri . '/assets/images/icon-152.png'); ?>"/>
                <square310x310logo src="<?php echo esc_url($theme_uri . '/assets/images/icon-384.png'); ?>"/>
                <TileColor>#1f2937</TileColor>
            </tile>
        </msapplication>
    </browserconfig>
    <?php
    exit;
}

// Handle browserconfig.xml request
add_action('init', function() {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/browserconfig.xml') !== false) {
        saga_browserconfig_xml();
    }
});

/**
 * Register REST API endpoints for annotations and bookmarks
 */
function saga_register_sync_endpoints(): void
{
    // Annotations endpoint
    register_rest_route('saga/v1', '/annotations', [
        'methods' => 'POST',
        'callback' => 'saga_save_annotation',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => [
            'entity_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => 'absint',
            ],
            'content' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'position' => [
                'required' => false,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    // Bookmarks endpoint
    register_rest_route('saga/v1', '/bookmarks', [
        'methods' => 'POST',
        'callback' => 'saga_save_bookmark',
        'permission_callback' => function() {
            return is_user_logged_in();
        },
        'args' => [
            'entity_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'sanitize_callback' => 'absint',
            ],
            'title' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'note' => [
                'required' => false,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'saga_register_sync_endpoints');

/**
 * Save annotation from sync
 */
function saga_save_annotation(WP_REST_Request $request): WP_REST_Response
{
    $entity_id = $request->get_param('entity_id');
    $content = $request->get_param('content');
    $position = $request->get_param('position');
    $user_id = get_current_user_id();

    // Save as post meta or custom table
    $annotation_id = wp_insert_post([
        'post_type' => 'saga_annotation',
        'post_title' => 'Annotation for Entity ' . $entity_id,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_author' => $user_id,
        'meta_input' => [
            'entity_id' => $entity_id,
            'position' => $position,
        ],
    ]);

    if (is_wp_error($annotation_id)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to save annotation',
        ], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'annotation_id' => $annotation_id,
    ], 201);
}

/**
 * Save bookmark from sync
 */
function saga_save_bookmark(WP_REST_Request $request): WP_REST_Response
{
    $entity_id = $request->get_param('entity_id');
    $title = $request->get_param('title');
    $note = $request->get_param('note');
    $user_id = get_current_user_id();

    // Save as user meta
    $bookmarks = get_user_meta($user_id, 'saga_bookmarks', true) ?: [];

    $bookmarks[] = [
        'entity_id' => $entity_id,
        'title' => $title,
        'note' => $note,
        'created_at' => current_time('mysql'),
    ];

    $updated = update_user_meta($user_id, 'saga_bookmarks', $bookmarks);

    if (!$updated) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to save bookmark',
        ], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'bookmark_id' => count($bookmarks) - 1,
    ], 201);
}

/**
 * Check if HTTPS is enabled
 */
function saga_check_pwa_requirements(): void
{
    if (!is_ssl() && !is_admin()) {
        add_action('wp_footer', function() {
            ?>
            <div class="pwa-https-warning" style="position: fixed; bottom: 20px; right: 20px; background: #fee2e2; color: #991b1b; padding: 16px; border-radius: 8px; max-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 9999;">
                <strong>HTTPS Required</strong>
                <p style="margin: 8px 0 0; font-size: 14px;">PWA features require HTTPS to work properly.</p>
            </div>
            <?php
        });
    }
}
add_action('init', 'saga_check_pwa_requirements');
