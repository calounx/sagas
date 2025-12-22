<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database;

/**
 * Database schema definitions
 *
 * Uses WordPress table prefix and dbDelta-compatible SQL
 */
final class Schema
{
    public const VERSION = '1.2.0';

    /**
     * Get all table creation SQL statements
     *
     * @param string $prefix Table prefix (e.g., 'wp_saga_')
     * @param string $charset_collate Charset and collation string
     * @return array<string, string> Table name => SQL statement
     */
    public static function getCreateTableStatements(string $prefix, string $charset_collate): array
    {
        return [
            'sagas' => self::getSagasTable($prefix, $charset_collate),
            'entities' => self::getEntitiesTable($prefix, $charset_collate),
            'attribute_definitions' => self::getAttributeDefinitionsTable($prefix, $charset_collate),
            'attribute_values' => self::getAttributeValuesTable($prefix, $charset_collate),
            'entity_relationships' => self::getRelationshipsTable($prefix, $charset_collate),
            'timeline_events' => self::getTimelineEventsTable($prefix, $charset_collate),
            'content_fragments' => self::getContentFragmentsTable($prefix, $charset_collate),
            'quality_metrics' => self::getQualityMetricsTable($prefix, $charset_collate),
        ];
    }

    /**
     * Get table names
     */
    public static function getTableNames(string $prefix): array
    {
        return [
            'sagas' => $prefix . 'sagas',
            'entities' => $prefix . 'entities',
            'attribute_definitions' => $prefix . 'attribute_definitions',
            'attribute_values' => $prefix . 'attribute_values',
            'entity_relationships' => $prefix . 'entity_relationships',
            'timeline_events' => $prefix . 'timeline_events',
            'content_fragments' => $prefix . 'content_fragments',
            'quality_metrics' => $prefix . 'quality_metrics',
        ];
    }

    private static function getSagasTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'sagas';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            universe varchar(100) NOT NULL,
            calendar_type enum('absolute','epoch_relative','age_based') NOT NULL,
            calendar_config longtext NOT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_name (name),
            KEY idx_universe (universe)
        ) {$charset_collate};";
    }

    private static function getEntitiesTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'entities';
        $sagasTable = $prefix . 'sagas';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            saga_id int(10) unsigned NOT NULL,
            entity_type enum('character','location','event','faction','artifact','concept') NOT NULL,
            canonical_name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            importance_score tinyint(3) unsigned DEFAULT 50,
            embedding_hash char(64) DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_saga_name (saga_id,canonical_name),
            KEY idx_saga_type (saga_id,entity_type),
            KEY idx_importance (importance_score),
            KEY idx_embedding (embedding_hash),
            KEY idx_slug (slug)
        ) {$charset_collate};";
    }

    private static function getAttributeDefinitionsTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'attribute_definitions';

        return "CREATE TABLE {$table} (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            entity_type enum('character','location','event','faction','artifact','concept') NOT NULL,
            attribute_key varchar(100) NOT NULL,
            display_name varchar(150) NOT NULL,
            data_type enum('string','int','float','bool','date','text','json') NOT NULL,
            is_searchable tinyint(1) DEFAULT 0,
            is_required tinyint(1) DEFAULT 0,
            validation_rule longtext DEFAULT NULL,
            default_value varchar(255) DEFAULT NULL,
            sort_order int(10) unsigned DEFAULT 0,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_type_key (entity_type,attribute_key),
            KEY idx_sort (entity_type,sort_order)
        ) {$charset_collate};";
    }

    private static function getAttributeValuesTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'attribute_values';

        return "CREATE TABLE {$table} (
            entity_id bigint(20) unsigned NOT NULL,
            attribute_id int(10) unsigned NOT NULL,
            value_string varchar(500) DEFAULT NULL,
            value_int bigint(20) DEFAULT NULL,
            value_float double DEFAULT NULL,
            value_bool tinyint(1) DEFAULT NULL,
            value_date date DEFAULT NULL,
            value_text longtext DEFAULT NULL,
            value_json longtext DEFAULT NULL,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_id,attribute_id),
            KEY idx_searchable_string (attribute_id,value_string(100)),
            KEY idx_searchable_int (attribute_id,value_int),
            KEY idx_searchable_date (attribute_id,value_date)
        ) {$charset_collate};";
    }

    private static function getRelationshipsTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'entity_relationships';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_entity_id bigint(20) unsigned NOT NULL,
            target_entity_id bigint(20) unsigned NOT NULL,
            relationship_type varchar(50) NOT NULL,
            strength tinyint(3) unsigned DEFAULT 50,
            valid_from date DEFAULT NULL,
            valid_until date DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_type (source_entity_id,relationship_type),
            KEY idx_target (target_entity_id),
            KEY idx_temporal (valid_from,valid_until)
        ) {$charset_collate};";
    }

    private static function getTimelineEventsTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'timeline_events';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            saga_id int(10) unsigned NOT NULL,
            event_entity_id bigint(20) unsigned DEFAULT NULL,
            canon_date varchar(100) NOT NULL,
            normalized_timestamp bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description longtext DEFAULT NULL,
            participants longtext DEFAULT NULL,
            locations longtext DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_saga_time (saga_id,normalized_timestamp),
            KEY idx_canon_date (canon_date(50)),
            KEY idx_event_entity (event_entity_id)
        ) {$charset_collate};";
    }

    private static function getContentFragmentsTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'content_fragments';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_id bigint(20) unsigned NOT NULL,
            fragment_text longtext NOT NULL,
            embedding longblob DEFAULT NULL,
            token_count smallint(5) unsigned DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_entity (entity_id),
            FULLTEXT KEY ft_fragment (fragment_text)
        ) {$charset_collate};";
    }

    private static function getQualityMetricsTable(string $prefix, string $charset_collate): string
    {
        $table = $prefix . 'quality_metrics';

        return "CREATE TABLE {$table} (
            entity_id bigint(20) unsigned NOT NULL,
            completeness_score tinyint(3) unsigned DEFAULT 0,
            consistency_score tinyint(3) unsigned DEFAULT 100,
            last_verified timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            issues longtext DEFAULT NULL,
            PRIMARY KEY (entity_id)
        ) {$charset_collate};";
    }

    /**
     * Get drop table statements for uninstall
     */
    public static function getDropTableStatements(string $prefix): array
    {
        $tables = array_reverse(array_values(self::getTableNames($prefix)));

        return array_map(
            fn(string $table) => "DROP TABLE IF EXISTS {$table};",
            $tables
        );
    }
}
