<?php
/**
 * PHPUnit Bootstrap File for Saga Manager Theme
 *
 * Sets up WordPress test environment and loads theme for testing.
 *
 * @package SagaManager
 * @subpackage Tests
 */

// Composer autoloader
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

// WordPress tests directory
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// WordPress core directory
$_core_dir = getenv('WP_CORE_DIR');
if (!$_core_dir) {
    $_core_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    echo "Please run: bash bin/install-wp-tests.sh wordpress_test wordpress wordpress db latest\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the theme being tested
 */
function _manually_load_theme() {
    // Switch to theme
    switch_theme('saga-manager-theme');

    // Load theme functions
    require dirname(__DIR__) . '/functions.php';

    // Run theme setup
    do_action('after_setup_theme');
}

tests_add_filter('muplugins_loaded', '_manually_load_theme');

/**
 * Set up test database tables
 */
function _setup_test_tables() {
    global $wpdb;

    // Suppress errors during table creation
    $wpdb->suppress_errors();

    // Load migration files
    $migrators = [
        dirname(__DIR__) . '/inc/ai/database-migrator.php',
        dirname(__DIR__) . '/inc/ai/entity-extractor-migrator.php',
        dirname(__DIR__) . '/inc/ai/predictive-relationships-migrator.php',
    ];

    foreach ($migrators as $migrator_file) {
        if (file_exists($migrator_file)) {
            require_once $migrator_file;
        }
    }

    // Run migrations
    if (class_exists('SagaManager\\AI\\ConsistencyGuardian\\ConsistencyGuardianMigrator')) {
        \SagaManager\AI\ConsistencyGuardian\ConsistencyGuardianMigrator::migrate();
    }

    if (class_exists('SagaManager\\AI\\EntityExtractor\\EntityExtractorMigrator')) {
        \SagaManager\AI\EntityExtractor\EntityExtractorMigrator::migrate();
    }

    if (class_exists('SagaManager\\AI\\PredictiveRelationships\\PredictiveRelationshipsMigrator')) {
        \SagaManager\AI\PredictiveRelationships\PredictiveRelationshipsMigrator::migrate();
    }
}

tests_add_filter('wp_install', '_setup_test_tables');

/**
 * Load PHPUnit Polyfills for compatibility
 */
if (defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    require_once WP_TESTS_PHPUNIT_POLYFILLS_PATH . '/autoload.php';
}

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load additional test utilities
require_once __DIR__ . '/includes/TestCase.php';
require_once __DIR__ . '/includes/FactoryTrait.php';

echo "WordPress Test Suite loaded successfully.\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHPUnit Version: " . PHPUnit\Runner\Version::id() . "\n";
