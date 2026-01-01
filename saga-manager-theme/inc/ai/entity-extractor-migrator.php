<?php
/**
 * Entity Extractor Database Migrator
 *
 * Creates and manages database tables for AI-powered entity extraction
 * from text. Tracks extraction jobs, extracted entities awaiting approval,
 * and duplicate detection results.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Entity Extractor Database Migrator
 *
 * Handles database schema creation and versioning for entity extraction feature.
 */
class EntityExtractorMigrator
{
    private const SCHEMA_VERSION = '1.0.0';
    private const VERSION_OPTION = 'saga_extractor_schema_version';

    /**
     * Run database migrations
     *
     * @return bool True on success, false on failure
     */
    public static function migrate(): bool
    {
        global $wpdb;

        $current_version = get_option(self::VERSION_OPTION, '0.0.0');

        if (version_compare($current_version, self::SCHEMA_VERSION, '>=')) {
            return true; // Already up to date
        }

        $wpdb->hide_errors();
        $success = true;

        try {
            // Create extraction_jobs table
            $success = $success && self::createExtractionJobsTable();

            // Create extracted_entities table
            $success = $success && self::createExtractedEntitiesTable();

            // Create extraction_duplicates table
            $success = $success && self::createExtractionDuplicatesTable();

            if ($success) {
                update_option(self::VERSION_OPTION, self::SCHEMA_VERSION);
                error_log('[SAGA][EXTRACTOR] Database schema created successfully');
            } else {
                error_log('[SAGA][EXTRACTOR][ERROR] Database schema creation failed');
            }

        } catch (\Exception $e) {
            error_log('[SAGA][EXTRACTOR][ERROR] Migration failed: ' . $e->getMessage());
            return false;
        }

        return $success;
    }

    /**
     * Create extraction_jobs table
     *
     * Tracks extraction job requests, status, and results
     */
    private static function createExtractionJobsTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_extraction_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            source_text LONGTEXT NOT NULL,
            source_type VARCHAR(50) DEFAULT 'manual' COMMENT 'manual, file_upload, api',
            chunk_size INT UNSIGNED DEFAULT 5000,
            total_chunks SMALLINT UNSIGNED DEFAULT 1,
            processed_chunks SMALLINT UNSIGNED DEFAULT 0,
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            total_entities_found SMALLINT UNSIGNED DEFAULT 0,
            entities_created SMALLINT UNSIGNED DEFAULT 0,
            entities_rejected SMALLINT UNSIGNED DEFAULT 0,
            duplicates_found SMALLINT UNSIGNED DEFAULT 0,
            ai_provider VARCHAR(20) DEFAULT 'openai' COMMENT 'openai, claude',
            ai_model VARCHAR(50) DEFAULT 'gpt-4',
            accuracy_score DECIMAL(5,2) COMMENT 'Estimated accuracy 0-100',
            processing_time_ms INT UNSIGNED COMMENT 'Total processing time',
            api_cost_usd DECIMAL(10,4) COMMENT 'Estimated API cost',
            error_message TEXT,
            metadata JSON COMMENT 'Additional job data',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_saga_status (saga_id, status),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at DESC),
            INDEX idx_status (status),
            FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Create extracted_entities table
     *
     * Stores extracted entities awaiting user approval before batch creation
     */
    private static function createExtractedEntitiesTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_extracted_entities';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT UNSIGNED NOT NULL,
            entity_type ENUM('character', 'location', 'event', 'faction', 'artifact', 'concept') NOT NULL,
            canonical_name VARCHAR(255) NOT NULL,
            alternative_names JSON COMMENT 'Array of aliases',
            description TEXT,
            attributes JSON COMMENT 'Extracted entity attributes',
            context_snippet TEXT COMMENT 'Text where entity was found',
            confidence_score DECIMAL(5,2) NOT NULL COMMENT '0-100 AI confidence',
            chunk_index SMALLINT UNSIGNED DEFAULT 0,
            position_in_text INT UNSIGNED COMMENT 'Character offset in source',
            status ENUM('pending', 'approved', 'rejected', 'duplicate', 'created') DEFAULT 'pending',
            duplicate_of BIGINT UNSIGNED COMMENT 'ID of existing entity if duplicate',
            duplicate_similarity DECIMAL(5,2) COMMENT 'Similarity score 0-100',
            created_entity_id BIGINT UNSIGNED COMMENT 'ID after batch creation',
            reviewed_by BIGINT UNSIGNED,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job (job_id),
            INDEX idx_status (status),
            INDEX idx_type (entity_type),
            INDEX idx_confidence (confidence_score DESC),
            INDEX idx_name (canonical_name(100)),
            FOREIGN KEY (job_id) REFERENCES {$wpdb->prefix}saga_extraction_jobs(id) ON DELETE CASCADE,
            FOREIGN KEY (created_entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Create extraction_duplicates table
     *
     * Tracks potential duplicate entities found during extraction
     */
    private static function createExtractionDuplicatesTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_extraction_duplicates';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            extracted_entity_id BIGINT UNSIGNED NOT NULL,
            existing_entity_id BIGINT UNSIGNED NOT NULL,
            similarity_score DECIMAL(5,2) NOT NULL COMMENT '0-100 similarity',
            match_type VARCHAR(50) NOT NULL COMMENT 'exact, fuzzy, semantic, alias',
            matching_field VARCHAR(100) COMMENT 'name, description, attributes',
            confidence DECIMAL(5,2) NOT NULL COMMENT 'AI confidence in duplicate',
            user_action ENUM('pending', 'confirmed_duplicate', 'confirmed_unique', 'merged') DEFAULT 'pending',
            merged_attributes JSON COMMENT 'Attributes merged if confirmed duplicate',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL,
            INDEX idx_extracted (extracted_entity_id),
            INDEX idx_existing (existing_entity_id),
            INDEX idx_similarity (similarity_score DESC),
            INDEX idx_action (user_action),
            FOREIGN KEY (extracted_entity_id) REFERENCES {$wpdb->prefix}saga_extracted_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (existing_entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
            UNIQUE KEY uk_pair (extracted_entity_id, existing_entity_id)
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Rollback database changes
     *
     * Drops all extraction-related tables. Use with extreme caution.
     *
     * @return bool True on success
     */
    public static function rollback(): bool
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'saga_extraction_duplicates',
            $wpdb->prefix . 'saga_extracted_entities',
            $wpdb->prefix . 'saga_extraction_jobs'
        ];

        $success = true;
        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
            $success = $success && ($result !== false);
        }

        if ($success) {
            delete_option(self::VERSION_OPTION);
            error_log('[SAGA][EXTRACTOR] Database schema rolled back');
        }

        return $success;
    }

    /**
     * Get current schema version
     *
     * @return string Version number
     */
    public static function getCurrentVersion(): string
    {
        return get_option(self::VERSION_OPTION, '0.0.0');
    }

    /**
     * Check if migration is needed
     *
     * @return bool True if migration needed
     */
    public static function needsMigration(): bool
    {
        $current = self::getCurrentVersion();
        return version_compare($current, self::SCHEMA_VERSION, '<');
    }

    /**
     * Get table statistics
     *
     * @return array Table names and row counts
     */
    public static function getTableStats(): array
    {
        global $wpdb;

        $stats = [];

        $tables = [
            'jobs' => $wpdb->prefix . 'saga_extraction_jobs',
            'entities' => $wpdb->prefix . 'saga_extracted_entities',
            'duplicates' => $wpdb->prefix . 'saga_extraction_duplicates'
        ];

        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $stats[$key] = $count !== null ? (int)$count : 0;
        }

        return $stats;
    }
}
