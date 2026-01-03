<?php
/**
 * Database Migrator for AI Consistency Guardian
 *
 * Creates and manages consistency_issues table
 * Runs on theme activation with proper rollback support
 *
 * @package SagaManager\AI
 * @version 1.4.0
 */

declare(strict_types=1);

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create consistency issues table
 *
 * @return bool True on success, false on failure
 */
function saga_ai_create_consistency_table(): bool {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$tableName      = $wpdb->prefix . 'saga_consistency_issues';
	$charsetCollate = $wpdb->get_charset_collate();

	// Check if table already exists
	$tableExists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = %s
        AND table_name = %s',
			DB_NAME,
			$tableName
		)
	);

	if ( $tableExists > 0 ) {
		// Check if foreign keys exist
		$fkExists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = %s
            AND TABLE_NAME = %s
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
				DB_NAME,
				$tableName
			)
		);

		// If table exists but foreign keys don't, drop and recreate
		if ( $fkExists == 0 ) {
			error_log( '[SAGA][AI] Consistency issues table exists without foreign keys, recreating...' );
			$wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );
		} else {
			error_log( '[SAGA][AI] Consistency issues table already exists' );
			return true;
		}
	}

	$sql = "CREATE TABLE {$tableName} (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        saga_id INT UNSIGNED NOT NULL,
        issue_type ENUM('timeline','character','location','relationship','logical') NOT NULL,
        severity ENUM('critical','high','medium','low','info') NOT NULL,
        entity_id BIGINT UNSIGNED,
        related_entity_id BIGINT UNSIGNED,
        description TEXT NOT NULL,
        context JSON COMMENT 'Relevant entity data, timestamps, etc.',
        suggested_fix TEXT,
        status ENUM('open','resolved','dismissed','false_positive') DEFAULT 'open',
        detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        resolved_by BIGINT UNSIGNED COMMENT 'User ID',
        ai_confidence DECIMAL(3,2) COMMENT '0.00-1.00',
        FOREIGN KEY (saga_id) REFERENCES {$wpdb->prefix}saga_sagas(id) ON DELETE CASCADE,
        FOREIGN KEY (entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
        FOREIGN KEY (related_entity_id) REFERENCES {$wpdb->prefix}saga_entities(id) ON DELETE CASCADE,
        INDEX idx_saga_status (saga_id, status),
        INDEX idx_severity (severity),
        INDEX idx_detected (detected_at DESC),
        INDEX idx_entity (entity_id),
        INDEX idx_status (status)
    ) {$charsetCollate};";

	dbDelta( $sql );

	// Verify table was created
	$tableCreated = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = %s
        AND table_name = %s',
			DB_NAME,
			$tableName
		)
	);

	if ( $tableCreated > 0 ) {
		error_log( '[SAGA][AI] Successfully created consistency issues table' );
		update_option( 'saga_ai_db_version', '1.4.0' );
		return true;
	}

	error_log( '[SAGA][AI][ERROR] Failed to create consistency issues table' );
	return false;
}

/**
 * Drop consistency issues table (for uninstall)
 *
 * @return bool
 */
function saga_ai_drop_consistency_table(): bool {
	global $wpdb;

	$tableName = $wpdb->prefix . 'saga_consistency_issues';

	$result = $wpdb->query( "DROP TABLE IF EXISTS {$tableName}" );

	if ( $result !== false ) {
		error_log( '[SAGA][AI] Successfully dropped consistency issues table' );
		delete_option( 'saga_ai_db_version' );
		return true;
	}

	error_log( '[SAGA][AI][ERROR] Failed to drop consistency issues table' );
	return false;
}

/**
 * Check if database needs migration
 *
 * @return bool
 */
function saga_ai_needs_migration(): bool {
	$currentVersion = get_option( 'saga_ai_db_version', '0.0.0' );
	return version_compare( $currentVersion, '1.4.0', '<' );
}

/**
 * Run database migrations
 *
 * @return bool
 */
function saga_ai_run_migrations(): bool {
	if ( ! saga_ai_needs_migration() ) {
		return true;
	}

	return saga_ai_create_consistency_table();
}

/**
 * Verify table integrity
 *
 * @return array Validation errors (empty if valid)
 */
function saga_ai_verify_table_integrity(): array {
	global $wpdb;

	$errors    = array();
	$tableName = $wpdb->prefix . 'saga_consistency_issues';

	// Check if table exists
	$tableExists = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = %s
        AND table_name = %s',
			DB_NAME,
			$tableName
		)
	);

	if ( ! $tableExists ) {
		$errors[] = 'Table does not exist';
		return $errors;
	}

	// Check required columns
	$requiredColumns = array(
		'id',
		'saga_id',
		'issue_type',
		'severity',
		'description',
		'status',
		'detected_at',
	);

	$columns = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = %s
        AND TABLE_NAME = %s',
			DB_NAME,
			$tableName
		)
	);

	foreach ( $requiredColumns as $column ) {
		if ( ! in_array( $column, $columns, true ) ) {
			$errors[] = "Missing required column: {$column}";
		}
	}

	// Check indexes
	$indexes = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT INDEX_NAME FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = %s
        AND TABLE_NAME = %s
        AND INDEX_NAME != 'PRIMARY'
        GROUP BY INDEX_NAME",
			DB_NAME,
			$tableName
		)
	);

	$requiredIndexes = array( 'idx_saga_status', 'idx_severity', 'idx_detected' );

	foreach ( $requiredIndexes as $index ) {
		if ( ! in_array( $index, $indexes, true ) ) {
			$errors[] = "Missing index: {$index}";
		}
	}

	return $errors;
}

/**
 * Get table statistics
 *
 * @return array
 */
function saga_ai_get_table_stats(): array {
	global $wpdb;

	$tableName = $wpdb->prefix . 'saga_consistency_issues';

	$stats = $wpdb->get_row(
		"
        SELECT
            COUNT(*) as total_rows,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            MIN(detected_at) as oldest_issue,
            MAX(detected_at) as newest_issue
        FROM {$tableName}
    ",
		ARRAY_A
	);

	if ( $stats === null ) {
		return array(
			'total_rows'     => 0,
			'open_count'     => 0,
			'resolved_count' => 0,
			'oldest_issue'   => null,
			'newest_issue'   => null,
		);
	}

	return $stats;
}

/**
 * Initialize database on theme activation
 *
 * @return void
 */
function saga_ai_activate_database(): void {
	$result = saga_ai_run_migrations();

	if ( $result ) {
		// Verify integrity
		$errors = saga_ai_verify_table_integrity();

		if ( ! empty( $errors ) ) {
			error_log( '[SAGA][AI][ERROR] Table integrity check failed: ' . implode( ', ', $errors ) );
		}
	}
}

// Hook into theme activation
add_action( 'after_switch_theme', 'saga_ai_activate_database' );

/**
 * Clean up on theme deactivation (optional)
 *
 * Note: Data is preserved by default
 * Only drops table if SAGA_AI_REMOVE_DATA constant is defined
 *
 * @return void
 */
function saga_ai_deactivate_database(): void {
	// Only remove data if explicitly requested
	if ( defined( 'SAGA_AI_REMOVE_DATA' ) && SAGA_AI_REMOVE_DATA === true ) {
		saga_ai_drop_consistency_table();
	}
}

// Hook into theme deactivation (data preserved by default)
add_action( 'switch_theme', 'saga_ai_deactivate_database' );
