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
    // Define theme directory for WordPress
    define('SAGA_THEME_DIR', dirname(__DIR__));
    define('SAGA_THEME_URL', 'http://localhost:8081/wp-content/themes/saga-manager-theme');

    // Load theme functions directly
    require dirname(__DIR__) . '/functions.php';

    // Run theme setup
    do_action('after_setup_theme');
}

tests_add_filter('muplugins_loaded', '_manually_load_theme');

/**
 * Create base saga tables for testing
 *
 * Creates complete schema needed by all saga features
 */
function _create_base_saga_tables() {
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Sagas table (multi-tenant)
    $sql_sagas = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_sagas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        universe VARCHAR(100) NOT NULL,
        calendar_type ENUM('absolute','epoch_relative','age_based') NOT NULL DEFAULT 'absolute',
        calendar_config JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_name (name),
        INDEX idx_universe (universe)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_sagas);
    echo "✓ saga_sagas table created\n";

    // 2. Core entity table
    $sql_entities = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_entities (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        saga_id INT UNSIGNED NOT NULL,
        entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
        canonical_name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        importance_score TINYINT UNSIGNED DEFAULT 50,
        embedding_hash CHAR(64),
        wp_post_id BIGINT UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_saga_type (saga_id, entity_type),
        INDEX idx_entity_saga_type (saga_id, entity_type, importance_score),
        INDEX idx_importance (importance_score DESC),
        INDEX idx_embedding (embedding_hash),
        INDEX idx_slug (slug),
        INDEX idx_wp_post (wp_post_id),
        UNIQUE KEY uk_saga_name (saga_id, canonical_name)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_entities);
    echo "✓ saga_entities table created\n";

    // 3. Entity relationships table
    $sql_relationships = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_entity_relationships (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_entity_id BIGINT UNSIGNED NOT NULL,
        target_entity_id BIGINT UNSIGNED NOT NULL,
        relationship_type VARCHAR(50) NOT NULL,
        strength TINYINT UNSIGNED DEFAULT 50,
        valid_from DATE,
        valid_until DATE,
        metadata JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_source_type (source_entity_id, relationship_type),
        INDEX idx_target (target_entity_id),
        INDEX idx_temporal (valid_from, valid_until)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_relationships);
    echo "✓ saga_entity_relationships table created\n";

    // 4. Timeline events table
    $sql_timeline = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_timeline_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        saga_id INT UNSIGNED NOT NULL,
        event_entity_id BIGINT UNSIGNED,
        canon_date VARCHAR(100) NOT NULL,
        normalized_timestamp BIGINT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        participants JSON,
        locations JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_saga_time (saga_id, normalized_timestamp),
        INDEX idx_canon_date (canon_date(50))
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_timeline);
    echo "✓ saga_timeline_events table created\n";

    // 5. Attribute definitions table (EAV schema)
    $sql_attr_defs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_attribute_definitions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
        attribute_key VARCHAR(100) NOT NULL,
        display_name VARCHAR(150) NOT NULL,
        data_type ENUM('string','int','float','bool','date','text','json') NOT NULL,
        is_searchable BOOLEAN DEFAULT FALSE,
        is_required BOOLEAN DEFAULT FALSE,
        validation_rule JSON,
        default_value VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_type_key (entity_type, attribute_key)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_attr_defs);
    echo "✓ saga_attribute_definitions table created\n";

    // 6. Attribute values table (EAV values)
    $sql_attr_vals = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_attribute_values (
        entity_id BIGINT UNSIGNED NOT NULL,
        attribute_id INT UNSIGNED NOT NULL,
        value_string VARCHAR(500),
        value_int BIGINT,
        value_float DOUBLE,
        value_bool BOOLEAN,
        value_date DATE,
        value_text TEXT,
        value_json JSON,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (entity_id, attribute_id),
        INDEX idx_searchable_string (attribute_id, value_string(100)),
        INDEX idx_searchable_int (attribute_id, value_int),
        INDEX idx_searchable_date (attribute_id, value_date)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_attr_vals);
    echo "✓ saga_attribute_values table created\n";

    // 7. Content fragments table (for semantic search)
    $sql_fragments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_content_fragments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_id BIGINT UNSIGNED NOT NULL,
        fragment_text TEXT NOT NULL,
        embedding BLOB,
        token_count SMALLINT UNSIGNED,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FULLTEXT INDEX ft_fragment (fragment_text)
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_fragments);
    echo "✓ saga_content_fragments table created\n";

    // 8. Quality metrics table
    $sql_quality = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}saga_quality_metrics (
        entity_id BIGINT UNSIGNED PRIMARY KEY,
        completeness_score TINYINT UNSIGNED DEFAULT 0,
        consistency_score TINYINT UNSIGNED DEFAULT 100,
        last_verified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        issues JSON
    ) ENGINE=InnoDB {$charset_collate};";

    dbDelta($sql_quality);
    echo "✓ saga_quality_metrics table created\n";

    echo "✓ All base saga tables created\n";
}

/**
 * Set up test database tables
 */
function _setup_test_tables() {
    global $wpdb;

    // Suppress errors during table creation
    $wpdb->suppress_errors();

    echo "Running database migrations...\n";

    // Create base saga tables required by foreign keys
    _create_base_saga_tables();

    // Load migration files (now that ABSPATH is defined)
    $migrators = [
        dirname(__DIR__) . '/inc/ai/database-migrator.php',
        dirname(__DIR__) . '/inc/ai/entity-extractor-migrator.php',
        dirname(__DIR__) . '/inc/ai/predictive-relationships-migrator.php',
        dirname(__DIR__) . '/inc/ai/summary-generator-migrator.php',
    ];

    foreach ($migrators as $migrator_file) {
        if (file_exists($migrator_file)) {
            require_once $migrator_file;
        }
    }

    // Run migrations
    // Consistency Guardian uses functions (no namespace)
    if (function_exists('saga_ai_create_consistency_table')) {
        $result = saga_ai_create_consistency_table();
        echo $result ? "✓ Consistency Guardian table created\n" : "✗ Consistency Guardian failed\n";
    }

    // Entity Extractor uses namespaced class
    if (class_exists('SagaManager\\AI\\EntityExtractor\\EntityExtractorMigrator')) {
        $result = \SagaManager\AI\EntityExtractor\EntityExtractorMigrator::migrate();
        echo $result ? "✓ Entity Extractor tables created\n" : "✗ Entity Extractor failed\n";
    }

    // Predictive Relationships uses namespaced class
    if (class_exists('SagaManager\\AI\\PredictiveRelationships\\PredictiveRelationshipsMigrator')) {
        $result = \SagaManager\AI\PredictiveRelationships\PredictiveRelationshipsMigrator::migrate();
        echo $result ? "✓ Predictive Relationships tables created\n" : "✗ Predictive Relationships failed\n";
    }

    // Summary Generator uses namespaced class
    if (class_exists('SagaManager\\AI\\SummaryGenerator\\SummaryGeneratorMigrator')) {
        $result = \SagaManager\AI\SummaryGenerator\SummaryGeneratorMigrator::migrate();
        echo $result ? "✓ Summary Generator tables created\n" : "✗ Summary Generator failed\n";
    }

    $wpdb->show_errors();
}

tests_add_filter('wp_install', '_setup_test_tables');

/**
 * Load PHPUnit Polyfills for compatibility
 */
if (file_exists(dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Ensure tables are created (call directly in case hook doesn't fire)
_setup_test_tables();

// Load additional test utilities (FactoryTrait must be loaded first)
require_once __DIR__ . '/includes/FactoryTrait.php';
require_once __DIR__ . '/includes/TestCase.php';

echo "WordPress Test Suite loaded successfully.\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHPUnit Version: " . PHPUnit\Runner\Version::id() . "\n";
