<?php
/**
 * Predictive Relationships Database Migrator
 *
 * Creates and manages database tables for AI-powered relationship prediction.
 * Tracks relationship suggestions, user feedback, and learning data to improve
 * future predictions through machine learning.
 *
 * @package SagaManager
 * @subpackage AI\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\PredictiveRelationships;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Predictive Relationships Database Migrator
 *
 * Handles database schema creation and versioning for relationship prediction feature.
 */
class PredictiveRelationshipsMigrator
{
    private const SCHEMA_VERSION = '1.0.0';
    private const VERSION_OPTION = 'saga_predictive_relationships_schema_version';

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
            // Create relationship_suggestions table
            $success = $success && self::createRelationshipSuggestionsTable();

            // Create suggestion_features table
            $success = $success && self::createSuggestionFeaturesTable();

            // Create suggestion_feedback table
            $success = $success && self::createSuggestionFeedbackTable();

            // Create learning_weights table
            $success = $success && self::createLearningWeightsTable();

            if ($success) {
                update_option(self::VERSION_OPTION, self::SCHEMA_VERSION);
                error_log('[SAGA][PREDICTIVE] Database schema created successfully');
            } else {
                error_log('[SAGA][PREDICTIVE][ERROR] Database schema creation failed');
            }

        } catch (\Exception $e) {
            error_log('[SAGA][PREDICTIVE][ERROR] Migration failed: ' . $e->getMessage());
            return false;
        }

        return $success;
    }

    /**
     * Create relationship_suggestions table
     *
     * Stores AI-generated relationship suggestions awaiting user review
     */
    private static function createRelationshipSuggestionsTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_relationship_suggestions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            source_entity_id BIGINT UNSIGNED NOT NULL,
            target_entity_id BIGINT UNSIGNED NOT NULL,
            suggested_type VARCHAR(50) NOT NULL COMMENT 'ally, enemy, family, mentor, etc',
            confidence_score DECIMAL(5,2) NOT NULL COMMENT '0-100 AI confidence',
            strength TINYINT UNSIGNED DEFAULT 50 COMMENT '0-100 relationship strength',
            reasoning TEXT COMMENT 'AI explanation for suggestion',
            evidence JSON COMMENT 'Supporting evidence (quotes, co-occurrences)',
            suggestion_method VARCHAR(50) NOT NULL COMMENT 'content, timeline, attribute, semantic',
            ai_model VARCHAR(50) DEFAULT 'gpt-4',
            status ENUM('pending', 'accepted', 'rejected', 'modified', 'auto_accepted') DEFAULT 'pending',
            user_action_type ENUM('none', 'accept', 'reject', 'modify', 'dismiss') DEFAULT 'none',
            user_feedback_text TEXT COMMENT 'User explanation for decision',
            accepted_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            actioned_by BIGINT UNSIGNED COMMENT 'User who took action',
            created_relationship_id BIGINT UNSIGNED COMMENT 'ID if accepted and created',
            priority_score DECIMAL(5,2) DEFAULT 50 COMMENT 'Display priority 0-100',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_saga_status (saga_id, status),
            INDEX idx_source (source_entity_id),
            INDEX idx_target (target_entity_id),
            INDEX idx_confidence (confidence_score DESC),
            INDEX idx_priority (priority_score DESC),
            INDEX idx_created (created_at DESC),
            INDEX idx_status (status),
            FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
            FOREIGN KEY (source_entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (target_entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
            FOREIGN KEY (actioned_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
            FOREIGN KEY (created_relationship_id) REFERENCES {$wpdb->prefix}saga_entity_relationships(id) ON DELETE SET NULL,
            UNIQUE KEY uk_suggestion (source_entity_id, target_entity_id, suggested_type),
            CONSTRAINT chk_no_self_suggestion CHECK (source_entity_id != target_entity_id),
            CONSTRAINT chk_confidence_range CHECK (confidence_score >= 0 AND confidence_score <= 100),
            CONSTRAINT chk_strength_range CHECK (strength >= 0 AND strength <= 100)
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Create suggestion_features table
     *
     * Stores extracted features used for relationship prediction
     */
    private static function createSuggestionFeaturesTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_suggestion_features';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            suggestion_id BIGINT UNSIGNED NOT NULL,
            feature_type VARCHAR(50) NOT NULL COMMENT 'co_occurrence, timeline_proximity, attribute_similarity, etc',
            feature_name VARCHAR(100) NOT NULL,
            feature_value DECIMAL(10,4) NOT NULL COMMENT 'Normalized value 0-1',
            weight DECIMAL(5,4) DEFAULT 0.5 COMMENT 'Feature importance weight',
            metadata JSON COMMENT 'Additional feature data',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_suggestion (suggestion_id),
            INDEX idx_type (feature_type),
            FOREIGN KEY (suggestion_id) REFERENCES {$wpdb->prefix}saga_relationship_suggestions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Create suggestion_feedback table
     *
     * Tracks user feedback for learning and accuracy improvement
     */
    private static function createSuggestionFeedbackTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_suggestion_feedback';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            suggestion_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action ENUM('accept', 'reject', 'modify', 'dismiss') NOT NULL,
            modified_type VARCHAR(50) COMMENT 'User corrected relationship type',
            modified_strength TINYINT UNSIGNED COMMENT 'User corrected strength',
            feedback_text TEXT COMMENT 'User explanation',
            confidence_at_decision DECIMAL(5,2) COMMENT 'Confidence when user decided',
            features_at_decision JSON COMMENT 'Feature values when decided',
            time_to_decision_seconds INT UNSIGNED COMMENT 'Time from creation to decision',
            was_auto_accepted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_suggestion (suggestion_id),
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at DESC),
            FOREIGN KEY (suggestion_id) REFERENCES {$wpdb->prefix}saga_relationship_suggestions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Create learning_weights table
     *
     * Stores learned feature weights for improving prediction accuracy
     */
    private static function createLearningWeightsTable(): bool
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'saga_learning_weights';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            feature_type VARCHAR(50) NOT NULL,
            relationship_type VARCHAR(50) COMMENT 'Specific to relationship type or NULL for global',
            weight DECIMAL(5,4) NOT NULL DEFAULT 0.5 COMMENT 'Learned weight 0-1',
            accuracy_score DECIMAL(5,2) COMMENT 'Accuracy with this weight',
            samples_count INT UNSIGNED DEFAULT 0 COMMENT 'Number of feedback samples',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata JSON COMMENT 'Learning metadata',
            INDEX idx_saga_feature (saga_id, feature_type),
            INDEX idx_type (feature_type),
            INDEX idx_relationship_type (relationship_type),
            INDEX idx_accuracy (accuracy_score DESC),
            FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
            UNIQUE KEY uk_saga_feature_type (saga_id, feature_type, relationship_type)
        ) ENGINE=InnoDB {$charset_collate}";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Rollback database changes
     *
     * Drops all predictive relationships tables. Use with extreme caution.
     *
     * @return bool True on success
     */
    public static function rollback(): bool
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'saga_learning_weights',
            $wpdb->prefix . 'saga_suggestion_feedback',
            $wpdb->prefix . 'saga_suggestion_features',
            $wpdb->prefix . 'saga_relationship_suggestions'
        ];

        $success = true;
        foreach ($tables as $table) {
            $result = $wpdb->query("DROP TABLE IF EXISTS {$table}");
            $success = $success && ($result !== false);
        }

        if ($success) {
            delete_option(self::VERSION_OPTION);
            error_log('[SAGA][PREDICTIVE] Database schema rolled back');
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
            'suggestions' => $wpdb->prefix . 'saga_relationship_suggestions',
            'features' => $wpdb->prefix . 'saga_suggestion_features',
            'feedback' => $wpdb->prefix . 'saga_suggestion_feedback',
            'weights' => $wpdb->prefix . 'saga_learning_weights'
        ];

        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $stats[$key] = $count !== null ? (int)$count : 0;
        }

        return $stats;
    }

    /**
     * Get learning statistics
     *
     * @param int $saga_id Saga ID
     * @return array Learning stats
     */
    public static function getLearningStats(int $saga_id): array
    {
        global $wpdb;

        $suggestions_table = $wpdb->prefix . 'saga_relationship_suggestions';
        $feedback_table = $wpdb->prefix . 'saga_suggestion_feedback';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$suggestions_table} WHERE saga_id = %d",
            $saga_id
        ));

        $accepted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$suggestions_table} WHERE saga_id = %d AND status = 'accepted'",
            $saga_id
        ));

        $rejected = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$suggestions_table} WHERE saga_id = %d AND status = 'rejected'",
            $saga_id
        ));

        $avg_confidence = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(confidence_score) FROM {$suggestions_table} WHERE saga_id = %d",
            $saga_id
        ));

        $accuracy = $total > 0 ? round(($accepted / $total) * 100, 2) : 0;

        return [
            'total_suggestions' => (int)$total,
            'accepted' => (int)$accepted,
            'rejected' => (int)$rejected,
            'pending' => (int)($total - $accepted - $rejected),
            'accuracy_percent' => $accuracy,
            'avg_confidence' => round((float)$avg_confidence, 2)
        ];
    }
}
