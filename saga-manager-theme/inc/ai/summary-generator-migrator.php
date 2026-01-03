<?php
/**
 * Auto-Generated Summaries Database Migration
 *
 * Creates database tables for AI-powered summary generation and management.
 *
 * Tables:
 * - saga_summary_requests: Summary generation requests and metadata
 * - saga_generated_summaries: Generated summary content with versioning
 * - saga_summary_templates: Reusable summary templates and prompts
 * - saga_summary_feedback: User feedback for summary quality improvement
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\AI\SummaryGenerator;

class SummaryGeneratorMigrator {

	/**
	 * Run all migrations
	 *
	 * @return bool Success status
	 */
	public static function migrate(): bool {
		$results = array(
			self::createSummaryRequestsTable(),
			self::createGeneratedSummariesTable(),
			self::createSummaryTemplatesTable(),
			self::createSummaryFeedbackTable(),
		);

		return ! in_array( false, $results, true );
	}

	/**
	 * Create summary_requests table
	 *
	 * Tracks summary generation requests with metadata and status.
	 *
	 * @return bool Success status
	 */
	private static function createSummaryRequestsTable(): bool {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'saga_summary_requests';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            saga_id INT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            summary_type VARCHAR(50) NOT NULL COMMENT 'character_arc, timeline, relationship, faction, location',
            entity_id BIGINT UNSIGNED COMMENT 'Target entity ID for entity-specific summaries',
            scope VARCHAR(50) DEFAULT 'full' COMMENT 'full, chapter, date_range',
            scope_params JSON COMMENT 'Date range, chapter numbers, etc.',
            status ENUM('pending', 'generating', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            priority TINYINT UNSIGNED DEFAULT 5 COMMENT '1-10 priority scale',
            ai_provider VARCHAR(50) DEFAULT 'openai' COMMENT 'openai, anthropic',
            ai_model VARCHAR(50) DEFAULT 'gpt-4' COMMENT 'gpt-4, claude-3-opus',
            estimated_tokens INT UNSIGNED COMMENT 'Estimated input tokens',
            actual_tokens INT UNSIGNED COMMENT 'Actual tokens used',
            estimated_cost DECIMAL(10,4) COMMENT 'Estimated cost in USD',
            actual_cost DECIMAL(10,4) COMMENT 'Actual cost in USD',
            processing_time INT UNSIGNED COMMENT 'Processing time in seconds',
            error_message TEXT COMMENT 'Error details if failed',
            retry_count TINYINT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_saga_status (saga_id, status),
            INDEX idx_user (user_id),
            INDEX idx_entity (entity_id),
            INDEX idx_type_status (summary_type, status),
            INDEX idx_created (created_at DESC),
            FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
            FOREIGN KEY (entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
            CONSTRAINT chk_priority_range CHECK (priority >= 1 AND priority <= 10),
            CONSTRAINT chk_retry_count CHECK (retry_count <= 5)
        ) ENGINE=InnoDB {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Create generated_summaries table
	 *
	 * Stores generated summary content with versioning and caching.
	 *
	 * @return bool Success status
	 */
	private static function createGeneratedSummariesTable(): bool {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'saga_generated_summaries';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id BIGINT UNSIGNED NOT NULL,
            saga_id INT UNSIGNED NOT NULL,
            entity_id BIGINT UNSIGNED COMMENT 'Target entity for entity-specific summaries',
            summary_type VARCHAR(50) NOT NULL,
            version INT UNSIGNED DEFAULT 1 COMMENT 'Summary version number',
            title VARCHAR(255) NOT NULL,
            summary_text LONGTEXT NOT NULL COMMENT 'Generated summary content',
            word_count INT UNSIGNED,
            key_points JSON COMMENT 'Extracted key points as array',
            metadata JSON COMMENT 'Additional metadata (themes, tags, etc.)',
            quality_score DECIMAL(5,2) COMMENT 'AI-assessed quality score 0-100',
            readability_score DECIMAL(5,2) COMMENT 'Readability score (Flesch-Kincaid)',
            is_current BOOLEAN DEFAULT TRUE COMMENT 'Is this the current version?',
            regeneration_reason VARCHAR(100) COMMENT 'Why was this regenerated?',
            cache_key VARCHAR(64) COMMENT 'MD5 hash for cache lookup',
            cache_expires_at TIMESTAMP NULL,
            ai_model VARCHAR(50) NOT NULL,
            token_count INT UNSIGNED,
            generation_cost DECIMAL(10,4),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_saga_type (saga_id, summary_type),
            INDEX idx_entity (entity_id),
            INDEX idx_current (is_current),
            INDEX idx_cache (cache_key, cache_expires_at),
            INDEX idx_request (request_id),
            UNIQUE KEY uk_cache (cache_key),
            FOREIGN KEY (request_id) REFERENCES {$wpdb->prefix}saga_summary_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
            FOREIGN KEY (entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
            CONSTRAINT chk_quality_range CHECK (quality_score >= 0 AND quality_score <= 100)
        ) ENGINE=InnoDB {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Create summary_templates table
	 *
	 * Stores reusable templates and prompts for different summary types.
	 *
	 * @return bool Success status
	 */
	private static function createSummaryTemplatesTable(): bool {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'saga_summary_templates';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(100) NOT NULL,
            summary_type VARCHAR(50) NOT NULL,
            description TEXT,
            system_prompt TEXT NOT NULL COMMENT 'System prompt for AI',
            user_prompt_template TEXT NOT NULL COMMENT 'Template with {{variables}}',
            output_format VARCHAR(50) DEFAULT 'markdown' COMMENT 'markdown, html, plain',
            max_length INT UNSIGNED DEFAULT 1000 COMMENT 'Max summary length in words',
            style VARCHAR(50) DEFAULT 'professional' COMMENT 'professional, casual, academic',
            include_quotes BOOLEAN DEFAULT TRUE,
            include_analysis BOOLEAN DEFAULT TRUE,
            temperature DECIMAL(3,2) DEFAULT 0.7 COMMENT 'AI temperature 0.0-1.0',
            is_default BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            usage_count INT UNSIGNED DEFAULT 0,
            avg_quality_score DECIMAL(5,2),
            created_by BIGINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type_active (summary_type, is_active),
            INDEX idx_default (is_default),
            UNIQUE KEY uk_template_name (template_name),
            FOREIGN KEY (created_by) REFERENCES {$wpdb->prefix}users(ID) ON DELETE SET NULL,
            CONSTRAINT chk_temperature_range CHECK (temperature >= 0.0 AND temperature <= 1.0)
        ) ENGINE=InnoDB {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Insert default templates
		self::insertDefaultTemplates();

		return true;
	}

	/**
	 * Create summary_feedback table
	 *
	 * Tracks user feedback on generated summaries for quality improvement.
	 *
	 * @return bool Success status
	 */
	private static function createSummaryFeedbackTable(): bool {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'saga_summary_feedback';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            summary_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL COMMENT '1-5 star rating',
            accuracy_score TINYINT UNSIGNED COMMENT '1-5 accuracy rating',
            completeness_score TINYINT UNSIGNED COMMENT '1-5 completeness rating',
            readability_score TINYINT UNSIGNED COMMENT '1-5 readability rating',
            feedback_text TEXT,
            issues_found JSON COMMENT 'Array of issue types',
            was_regenerated BOOLEAN DEFAULT FALSE,
            action_taken ENUM('none', 'edited', 'regenerated', 'deleted') DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_summary (summary_id),
            INDEX idx_user (user_id),
            INDEX idx_rating (rating),
            INDEX idx_regenerated (was_regenerated),
            FOREIGN KEY (summary_id) REFERENCES {$wpdb->prefix}saga_generated_summaries(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}users(ID) ON DELETE CASCADE,
            CONSTRAINT chk_rating_range CHECK (rating >= 1 AND rating <= 5),
            CONSTRAINT chk_accuracy_range CHECK (accuracy_score >= 1 AND accuracy_score <= 5),
            CONSTRAINT chk_completeness_range CHECK (completeness_score >= 1 AND completeness_score <= 5),
            CONSTRAINT chk_readability_range CHECK (readability_score >= 1 AND readability_score <= 5)
        ) ENGINE=InnoDB {$charset_collate}";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Insert default summary templates
	 *
	 * @return void
	 */
	private static function insertDefaultTemplates(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'saga_summary_templates';

		$templates = array(
			array(
				'template_name'        => 'character_arc_default',
				'summary_type'         => 'character_arc',
				'description'          => 'Comprehensive character arc summary',
				'system_prompt'        => 'You are an expert literary analyst specializing in character development and narrative arcs. Analyze the provided character information and create a compelling summary of their journey.',
				'user_prompt_template' => 'Create a comprehensive summary of {{character_name}}\'s character arc in {{saga_name}}. Include:\n\n1. Introduction and initial state\n2. Key events and turning points\n3. Character development and growth\n4. Relationships and interactions\n5. Current state and future implications\n\nCharacter Data:\n{{character_data}}\n\nRelated Events:\n{{timeline_events}}\n\nRelationships:\n{{relationships}}',
				'output_format'        => 'markdown',
				'max_length'           => 800,
				'style'                => 'professional',
				'include_quotes'       => true,
				'include_analysis'     => true,
				'temperature'          => 0.7,
				'is_default'           => true,
				'is_active'            => true,
			),
			array(
				'template_name'        => 'timeline_summary_default',
				'summary_type'         => 'timeline',
				'description'          => 'Chronological timeline summary',
				'system_prompt'        => 'You are a skilled historian and timeline analyst. Create clear, chronological summaries of complex sequences of events.',
				'user_prompt_template' => 'Create a chronological summary of events in {{saga_name}} {{scope_description}}. Organize events logically and highlight cause-and-effect relationships.\n\nEvents:\n{{timeline_events}}\n\nParticipating Entities:\n{{entities}}\n\nProvide:\n1. Chronological overview\n2. Major plot points\n3. Key consequences\n4. Character involvement',
				'output_format'        => 'markdown',
				'max_length'           => 1000,
				'style'                => 'professional',
				'include_quotes'       => false,
				'include_analysis'     => true,
				'temperature'          => 0.6,
				'is_default'           => true,
				'is_active'            => true,
			),
			array(
				'template_name'        => 'relationship_overview_default',
				'summary_type'         => 'relationship',
				'description'          => 'Relationship network summary',
				'system_prompt'        => 'You are an expert in social dynamics and interpersonal relationships. Analyze relationship networks and create insightful summaries.',
				'user_prompt_template' => 'Summarize the relationship network {{scope_description}} in {{saga_name}}.\n\nRelationships:\n{{relationships}}\n\nEntities Involved:\n{{entities}}\n\nKey Events:\n{{relevant_events}}\n\nProvide:\n1. Relationship overview\n2. Key alliances and conflicts\n3. Relationship evolution\n4. Network dynamics',
				'output_format'        => 'markdown',
				'max_length'           => 700,
				'style'                => 'professional',
				'include_quotes'       => true,
				'include_analysis'     => true,
				'temperature'          => 0.7,
				'is_default'           => true,
				'is_active'            => true,
			),
			array(
				'template_name'        => 'faction_summary_default',
				'summary_type'         => 'faction',
				'description'          => 'Faction analysis summary',
				'system_prompt'        => 'You are a political analyst and organizational expert. Analyze factions, their goals, and their impact on the narrative.',
				'user_prompt_template' => 'Analyze the faction {{faction_name}} in {{saga_name}}.\n\nFaction Data:\n{{faction_data}}\n\nMembers:\n{{members}}\n\nActivities:\n{{events}}\n\nProvide:\n1. Faction overview and purpose\n2. Key members and hierarchy\n3. Major actions and goals\n4. Influence and relationships',
				'output_format'        => 'markdown',
				'max_length'           => 600,
				'style'                => 'professional',
				'include_quotes'       => false,
				'include_analysis'     => true,
				'temperature'          => 0.6,
				'is_default'           => true,
				'is_active'            => true,
			),
			array(
				'template_name'        => 'location_summary_default',
				'summary_type'         => 'location',
				'description'          => 'Location and setting summary',
				'system_prompt'        => 'You are a world-building expert and setting analyst. Create vivid, informative summaries of locations and their significance.',
				'user_prompt_template' => 'Summarize the location {{location_name}} in {{saga_name}}.\n\nLocation Data:\n{{location_data}}\n\nEvents at Location:\n{{events}}\n\nEntities Associated:\n{{entities}}\n\nProvide:\n1. Physical description\n2. Historical significance\n3. Key events that occurred here\n4. Cultural/strategic importance',
				'output_format'        => 'markdown',
				'max_length'           => 500,
				'style'                => 'professional',
				'include_quotes'       => false,
				'include_analysis'     => true,
				'temperature'          => 0.7,
				'is_default'           => true,
				'is_active'            => true,
			),
		);

		foreach ( $templates as $template ) {
			// Check if template exists
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE template_name = %s",
					$template['template_name']
				)
			);

			if ( ! $exists ) {
				$wpdb->insert( $table_name, $template );
			}
		}
	}

	/**
	 * Rollback all migrations
	 *
	 * @return bool Success status
	 */
	public static function rollback(): bool {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'saga_summary_feedback',
			$wpdb->prefix . 'saga_generated_summaries',
			$wpdb->prefix . 'saga_summary_templates',
			$wpdb->prefix . 'saga_summary_requests',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		return true;
	}
}
