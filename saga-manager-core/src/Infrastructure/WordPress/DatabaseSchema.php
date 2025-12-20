<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\WordPress;

/**
 * Database Schema Manager
 *
 * Handles creation and updates of all Saga Manager database tables
 * with proper WordPress prefix support.
 */
class DatabaseSchema
{
    private string $charset_collate;
    private string $prefix;

    public function __construct()
    {
        global $wpdb;

        $this->charset_collate = $wpdb->get_charset_collate();
        $this->prefix = $wpdb->prefix . 'saga_';
    }

    /**
     * Create all database tables
     */
    public function createTables(): void
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $tables = [
            $this->getSagasTableSQL(),
            $this->getEntitiesTableSQL(),
            $this->getAttributeDefinitionsTableSQL(),
            $this->getAttributeValuesTableSQL(),
            $this->getRelationshipsTableSQL(),
            $this->getTimelineEventsTableSQL(),
            $this->getContentFragmentsTableSQL(),
            $this->getQualityMetricsTableSQL(),
        ];

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        // Store schema version
        update_option('saga_manager_core_db_version', SAGA_MANAGER_CORE_VERSION);
    }

    private function getSagasTableSQL(): string
    {
        $table_name = $this->prefix . 'sagas';

        return "CREATE TABLE {$table_name} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            universe VARCHAR(100) NOT NULL COMMENT 'e.g. Dune, Star Wars, LOTR',
            calendar_type ENUM('absolute','epoch_relative','age_based') NOT NULL,
            calendar_config JSON NOT NULL COMMENT 'Epoch dates, age definitions',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_name (name),
            INDEX idx_universe (universe)
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getEntitiesTableSQL(): string
    {
        $table_name = $this->prefix . 'entities';

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
            canonical_name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            importance_score TINYINT UNSIGNED DEFAULT 50 COMMENT '0-100 scale',
            embedding_hash CHAR(64) COMMENT 'SHA256 of embedding for duplicate detection',
            wp_post_id BIGINT UNSIGNED COMMENT 'Link to wp_posts for display',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_saga_type (saga_id, entity_type),
            INDEX idx_importance (importance_score DESC),
            INDEX idx_embedding (embedding_hash),
            INDEX idx_slug (slug),
            INDEX idx_wp_post (wp_post_id),
            INDEX idx_wp_sync (wp_post_id, updated_at),
            INDEX idx_search_cover (saga_id, entity_type, importance_score),
            UNIQUE KEY uk_saga_name (saga_id, canonical_name)
        ) ENGINE=InnoDB ROW_FORMAT=COMPRESSED {$this->charset_collate};";
    }

    private function getAttributeDefinitionsTableSQL(): string
    {
        $table_name = $this->prefix . 'attribute_definitions';

        return "CREATE TABLE {$table_name} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type ENUM('character','location','event','faction','artifact','concept') NOT NULL,
            attribute_key VARCHAR(100) NOT NULL,
            display_name VARCHAR(150) NOT NULL,
            data_type ENUM('string','int','float','bool','date','text','json') NOT NULL,
            is_searchable BOOLEAN DEFAULT FALSE,
            is_required BOOLEAN DEFAULT FALSE,
            validation_rule JSON COMMENT 'regex, min, max, enum',
            default_value VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_type_key (entity_type, attribute_key)
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getAttributeValuesTableSQL(): string
    {
        $table_name = $this->prefix . 'attribute_values';

        return "CREATE TABLE {$table_name} (
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
            INDEX idx_entity_attribute (entity_id, attribute_id),
            INDEX idx_searchable_string (attribute_id, value_string(100)),
            INDEX idx_searchable_int (attribute_id, value_int),
            INDEX idx_searchable_date (attribute_id, value_date)
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getRelationshipsTableSQL(): string
    {
        $table_name = $this->prefix . 'entity_relationships';

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_entity_id BIGINT UNSIGNED NOT NULL,
            target_entity_id BIGINT UNSIGNED NOT NULL,
            relationship_type VARCHAR(50) NOT NULL,
            strength TINYINT UNSIGNED DEFAULT 50 COMMENT '0-100 relationship strength',
            valid_from DATE,
            valid_until DATE,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_source_type (source_entity_id, relationship_type),
            INDEX idx_target (target_entity_id),
            INDEX idx_target_type (target_entity_id, relationship_type),
            INDEX idx_temporal (valid_from, valid_until)
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getTimelineEventsTableSQL(): string
    {
        $table_name = $this->prefix . 'timeline_events';

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            event_entity_id BIGINT UNSIGNED,
            canon_date VARCHAR(100) NOT NULL COMMENT 'Original saga date format',
            normalized_timestamp BIGINT NOT NULL COMMENT 'Unix-like timestamp for sorting',
            title VARCHAR(255) NOT NULL,
            description TEXT,
            participants JSON COMMENT 'Array of entity IDs',
            locations JSON COMMENT 'Array of location entity IDs',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_saga_time (saga_id, normalized_timestamp),
            INDEX idx_canon_date (canon_date(50))
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getContentFragmentsTableSQL(): string
    {
        $table_name = $this->prefix . 'content_fragments';

        return "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_id BIGINT UNSIGNED NOT NULL,
            fragment_text TEXT NOT NULL,
            embedding BLOB COMMENT 'Vector embedding (384-dim float32)',
            token_count SMALLINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity_fragments (entity_id, created_at),
            FULLTEXT INDEX ft_fragment (fragment_text)
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    private function getQualityMetricsTableSQL(): string
    {
        $table_name = $this->prefix . 'quality_metrics';

        return "CREATE TABLE {$table_name} (
            entity_id BIGINT UNSIGNED PRIMARY KEY,
            completeness_score TINYINT UNSIGNED DEFAULT 0 COMMENT 'Percentage of required attrs',
            consistency_score TINYINT UNSIGNED DEFAULT 100 COMMENT 'Cross-ref validation score',
            last_verified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            issues JSON COMMENT 'Array of issue codes'
        ) ENGINE=InnoDB {$this->charset_collate};";
    }

    /**
     * Add foreign key constraints
     */
    public function addForeignKeys(): void
    {
        global $wpdb;

        $wpdb->suppress_errors();

        // Foreign key: entities.saga_id -> sagas(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'entities',
            'fk_entity_saga',
            'saga_id',
            $this->prefix . 'sagas',
            'id',
            'CASCADE'
        );

        // Foreign key: attribute_values.entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'attribute_values',
            'fk_attrval_entity',
            'entity_id',
            $this->prefix . 'entities',
            'id',
            'CASCADE'
        );

        // Foreign key: attribute_values.attribute_id -> attribute_definitions(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'attribute_values',
            'fk_attrval_definition',
            'attribute_id',
            $this->prefix . 'attribute_definitions',
            'id',
            'CASCADE'
        );

        // Foreign key: entity_relationships.source_entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'entity_relationships',
            'fk_rel_source',
            'source_entity_id',
            $this->prefix . 'entities',
            'id',
            'CASCADE'
        );

        // Foreign key: entity_relationships.target_entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'entity_relationships',
            'fk_rel_target',
            'target_entity_id',
            $this->prefix . 'entities',
            'id',
            'CASCADE'
        );

        // Foreign key: timeline_events.saga_id -> sagas(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'timeline_events',
            'fk_timeline_saga',
            'saga_id',
            $this->prefix . 'sagas',
            'id',
            'CASCADE'
        );

        // Foreign key: timeline_events.event_entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'timeline_events',
            'fk_timeline_entity',
            'event_entity_id',
            $this->prefix . 'entities',
            'id',
            'SET NULL'
        );

        // Foreign key: content_fragments.entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'content_fragments',
            'fk_fragment_entity',
            'entity_id',
            $this->prefix . 'entities',
            'id',
            'CASCADE'
        );

        // Foreign key: quality_metrics.entity_id -> entities(id)
        $this->addForeignKeyIfNotExists(
            $this->prefix . 'quality_metrics',
            'fk_quality_entity',
            'entity_id',
            $this->prefix . 'entities',
            'id',
            'CASCADE'
        );

        // CHECK constraints
        $this->addCheckConstraintIfNotExists(
            $this->prefix . 'entity_relationships',
            'chk_no_self_ref',
            'source_entity_id != target_entity_id'
        );

        $this->addCheckConstraintIfNotExists(
            $this->prefix . 'entity_relationships',
            'chk_valid_dates',
            'valid_until IS NULL OR valid_until >= valid_from'
        );

        $wpdb->suppress_errors(false);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SAGA][DB] Foreign keys and constraints added successfully');
        }
    }

    private function addForeignKeyIfNotExists(
        string $table,
        string $constraint_name,
        string $column,
        string $ref_table,
        string $ref_column,
        string $on_delete
    ): void {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = %s
            AND TABLE_NAME = %s
            AND CONSTRAINT_NAME = %s
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            DB_NAME,
            $table,
            $constraint_name
        ));

        if ($exists > 0) {
            return;
        }

        $sql = "ALTER TABLE {$table}
                ADD CONSTRAINT {$constraint_name}
                FOREIGN KEY ({$column})
                REFERENCES {$ref_table}({$ref_column})
                ON DELETE {$on_delete}";

        $result = $wpdb->query($sql);

        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SAGA][DB] Failed to add FK {$constraint_name}: {$wpdb->last_error}");
        }
    }

    private function addCheckConstraintIfNotExists(
        string $table,
        string $constraint_name,
        string $check_condition
    ): void {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = %s
            AND TABLE_NAME = %s
            AND CONSTRAINT_NAME = %s
            AND CONSTRAINT_TYPE = 'CHECK'",
            DB_NAME,
            $table,
            $constraint_name
        ));

        if ($exists > 0) {
            return;
        }

        $sql = "ALTER TABLE {$table}
                ADD CONSTRAINT {$constraint_name}
                CHECK ({$check_condition})";

        $result = $wpdb->query($sql);

        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SAGA][DB] Failed to add CHECK {$constraint_name}: {$wpdb->last_error}");
        }
    }

    /**
     * Drop all tables (used on uninstall)
     */
    public function dropTables(): void
    {
        global $wpdb;

        $tables = [
            $this->prefix . 'quality_metrics',
            $this->prefix . 'content_fragments',
            $this->prefix . 'timeline_events',
            $this->prefix . 'entity_relationships',
            $this->prefix . 'attribute_values',
            $this->prefix . 'attribute_definitions',
            $this->prefix . 'entities',
            $this->prefix . 'sagas',
        ];

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        delete_option('saga_manager_core_db_version');
    }
}
