<?php
/**
 * PHPUnit Bootstrap File
 *
 * Initializes WordPress test environment and autoloader for Saga Manager tests.
 */

declare(strict_types=1);

// Determine if we're running integration tests (require WordPress)
$isIntegrationTest = in_array('--testsuite=Integration', $_SERVER['argv'] ?? [], true);

// Load Composer autoloader
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Please run 'composer install' before running tests.\n");
}
require_once $autoloadPath;

// Load WordPress test suite for integration tests
if ($isIntegrationTest || !isset($_SERVER['argv'])) {
    // WordPress tests directory
    $wpTestsDir = getenv('WP_TESTS_DIR');
    if (!$wpTestsDir) {
        $wpTestsDir = '/tmp/wordpress-tests-lib';
    }

    // WordPress core directory
    $wpCoreDir = getenv('WP_CORE_DIR');
    if (!$wpCoreDir) {
        $wpCoreDir = '/tmp/wordpress';
    }

    if (!file_exists($wpTestsDir . '/includes/functions.php')) {
        echo "WordPress test suite not found.\n";
        echo "Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
        exit(1);
    }

    // Load WordPress test functions
    require_once $wpTestsDir . '/includes/functions.php';

    /**
     * Manually load the plugin for testing
     */
    function _manually_load_plugin(): void
    {
        require dirname(__DIR__) . '/saga-manager.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    // Start up the WordPress testing environment
    require $wpTestsDir . '/includes/bootstrap.php';

    // Activate the plugin
    activate_plugin('saga-manager/saga-manager.php');

    echo "WordPress test environment loaded.\n";
} else {
    // For unit tests, we don't need WordPress
    echo "Running unit tests (no WordPress dependency).\n";
}

// Test constants
define('SAGA_TESTS_DIR', __DIR__);
define('SAGA_TESTS_FIXTURES_DIR', __DIR__ . '/Fixtures');
