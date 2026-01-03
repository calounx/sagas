<?php
/**
 * AJAX Handlers for AI Consistency Guardian
 *
 * Handles all AJAX requests for consistency issue management
 * with proper nonce verification and capability checks
 *
 * @package SagaManager\Ajax
 * @version 1.4.0
 */

declare(strict_types=1);

use SagaManager\AI\ConsistencyAnalyzer;
use SagaManager\AI\ConsistencyRepository;
use SagaManager\AI\ConsistencyRuleEngine;
use SagaManager\AI\AIClient;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saga_Consistency_Ajax_Handler Class
 *
 * Handles AJAX operations for consistency guardian
 */
final class Saga_Consistency_Ajax_Handler {

	/**
	 * @var ConsistencyAnalyzer
	 */
	private ConsistencyAnalyzer $analyzer;

	/**
	 * @var ConsistencyRepository
	 */
	private ConsistencyRepository $repository;

	/**
	 * @var string Scan progress transient key prefix
	 */
	private const SCAN_PROGRESS_KEY = 'saga_scan_progress_';

	/**
	 * Constructor - Register AJAX hooks
	 */
	public function __construct() {
		// Initialize dependencies
		$this->repository = new ConsistencyRepository();

		$ruleEngine     = new ConsistencyRuleEngine();
		$aiClient       = new AIClient();
		$this->analyzer = new ConsistencyAnalyzer( $ruleEngine, $aiClient, $this->repository );

		// Register AJAX hooks
		add_action( 'wp_ajax_saga_run_consistency_scan', array( $this, 'runConsistencyScan' ) );
		add_action( 'wp_ajax_saga_get_scan_progress', array( $this, 'getScanProgress' ) );
		add_action( 'wp_ajax_saga_resolve_issue', array( $this, 'resolveIssue' ) );
		add_action( 'wp_ajax_saga_dismiss_issue', array( $this, 'dismissIssue' ) );
		add_action( 'wp_ajax_saga_bulk_action', array( $this, 'bulkAction' ) );
		add_action( 'wp_ajax_saga_export_issues', array( $this, 'exportIssues' ) );
		add_action( 'wp_ajax_saga_get_issue_details', array( $this, 'getIssueDetails' ) );
		add_action( 'wp_ajax_saga_load_issues', array( $this, 'loadIssues' ) );

		// Real-time editor endpoints
		add_action( 'wp_ajax_saga_check_entity_realtime', array( $this, 'checkEntityRealtime' ) );
		add_action( 'wp_ajax_saga_get_entity_issues', array( $this, 'getEntityIssues' ) );
		add_action( 'wp_ajax_saga_dismiss_inline_warning', array( $this, 'dismissInlineWarning' ) );
	}

	/**
	 * Run consistency scan
	 *
	 * @return void
	 */
	public function runConsistencyScan(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$sagaId = absint( $_POST['saga_id'] ?? 0 );

		if ( $sagaId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid saga ID', 'saga-manager-theme' ) ), 400 );
		}

		// Initialize progress tracking
		$progressKey = self::SCAN_PROGRESS_KEY . $sagaId;
		set_transient(
			$progressKey,
			array(
				'status'       => 'running',
				'progress'     => 0,
				'current_step' => __( 'Initializing scan...', 'saga-manager-theme' ),
				'started_at'   => current_time( 'mysql' ),
			),
			300
		); // 5 minutes TTL

		try {
			// Update progress: Rule-based checks
			$this->updateProgress( $progressKey, 25, __( 'Running rule-based checks...', 'saga-manager-theme' ) );

			// Run analysis
			$useAI  = isset( $_POST['use_ai'] ) && $_POST['use_ai'] === 'true';
			$issues = $this->analyzer->analyze( $sagaId, array(), $useAI );

			// Update progress: Complete
			$this->updateProgress( $progressKey, 100, __( 'Scan complete', 'saga-manager-theme' ), 'completed' );

			// Store results in transient
			set_transient(
				$progressKey . '_results',
				array(
					'total_issues' => count( $issues ),
					'by_severity'  => $this->groupBySeverity( $issues ),
					'by_type'      => $this->groupByType( $issues ),
				),
				3600
			); // 1 hour TTL

			wp_send_json_success(
				array(
					'message'      => sprintf(
						__( 'Scan completed. Found %d issues.', 'saga-manager-theme' ),
						count( $issues )
					),
					'total_issues' => count( $issues ),
					'by_severity'  => $this->groupBySeverity( $issues ),
				)
			);

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Consistency scan failed: ' . $e->getMessage() );

			set_transient(
				$progressKey,
				array(
					'status'       => 'failed',
					'progress'     => 0,
					'current_step' => __( 'Scan failed', 'saga-manager-theme' ),
					'error'        => $e->getMessage(),
				),
				300
			);

			wp_send_json_error(
				array(
					'message' => __( 'Scan failed. Please try again.', 'saga-manager-theme' ),
					'error'   => WP_DEBUG ? $e->getMessage() : null,
				),
				500
			);
		}
	}

	/**
	 * Get scan progress
	 *
	 * @return void
	 */
	public function getScanProgress(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		$sagaId = absint( $_GET['saga_id'] ?? 0 );

		if ( $sagaId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid saga ID', 'saga-manager-theme' ) ), 400 );
		}

		$progressKey = self::SCAN_PROGRESS_KEY . $sagaId;
		$progress    = get_transient( $progressKey );

		if ( $progress === false ) {
			wp_send_json_error( array( 'message' => __( 'No active scan found', 'saga-manager-theme' ) ), 404 );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * Resolve issue
	 *
	 * @return void
	 */
	public function resolveIssue(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$issueId = absint( $_POST['issue_id'] ?? 0 );

		if ( $issueId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid issue ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			$success = $this->analyzer->resolveIssue( $issueId, get_current_user_id() );

			if ( $success ) {
				wp_send_json_success(
					array(
						'message' => __( 'Issue resolved successfully', 'saga-manager-theme' ),
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to resolve issue', 'saga-manager-theme' ),
					),
					500
				);
			}
		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Resolve issue failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while resolving the issue', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Dismiss issue
	 *
	 * @return void
	 */
	public function dismissIssue(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$issueId         = absint( $_POST['issue_id'] ?? 0 );
		$isFalsePositive = isset( $_POST['false_positive'] ) && $_POST['false_positive'] === 'true';

		if ( $issueId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid issue ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			$success = $this->analyzer->dismissIssue( $issueId, get_current_user_id(), $isFalsePositive );

			if ( $success ) {
				$message = $isFalsePositive
					? __( 'Issue marked as false positive', 'saga-manager-theme' )
					: __( 'Issue dismissed successfully', 'saga-manager-theme' );

				wp_send_json_success( array( 'message' => $message ) );
			} else {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to dismiss issue', 'saga-manager-theme' ),
					),
					500
				);
			}
		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Dismiss issue failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while dismissing the issue', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Bulk action handler
	 *
	 * @return void
	 */
	public function bulkAction(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$action   = sanitize_key( $_POST['action_type'] ?? '' );
		$issueIds = isset( $_POST['issue_ids'] ) && is_array( $_POST['issue_ids'] )
			? array_map( 'absint', $_POST['issue_ids'] )
			: array();

		if ( empty( $issueIds ) ) {
			wp_send_json_error( array( 'message' => __( 'No issues selected', 'saga-manager-theme' ) ), 400 );
		}

		$userId       = get_current_user_id();
		$successCount = 0;
		$failCount    = 0;

		foreach ( $issueIds as $issueId ) {
			try {
				$success = match ( $action ) {
					'resolve' => $this->analyzer->resolveIssue( $issueId, $userId ),
					'dismiss' => $this->analyzer->dismissIssue( $issueId, $userId, false ),
					'mark_false_positive' => $this->analyzer->dismissIssue( $issueId, $userId, true ),
					default => false,
				};

				if ( $success ) {
					++$successCount;
				} else {
					++$failCount;
				}
			} catch ( Exception $e ) {
				error_log( '[SAGA][AJAX][ERROR] Bulk action failed for issue ' . $issueId . ': ' . $e->getMessage() );
				++$failCount;
			}
		}

		wp_send_json_success(
			array(
				'message'       => sprintf(
					__( 'Processed %1$d issues (%2$d successful, %3$d failed)', 'saga-manager-theme' ),
					count( $issueIds ),
					$successCount,
					$failCount
				),
				'success_count' => $successCount,
				'fail_count'    => $failCount,
			)
		);
	}

	/**
	 * Export issues to CSV
	 *
	 * @return void
	 */
	public function exportIssues(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$sagaId = absint( $_GET['saga_id'] ?? 0 );
		$status = sanitize_key( $_GET['status'] ?? 'open' );

		if ( $sagaId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid saga ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			$issues = $this->repository->findBySaga( $sagaId, $status, 1000 );

			// Generate CSV
			$csv = $this->generateCSV( $issues );

			// Set headers for file download
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="consistency-issues-' . date( 'Y-m-d' ) . '.csv"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $csv;
			exit;

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Export failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Export failed', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Get issue details
	 *
	 * @return void
	 */
	public function getIssueDetails(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		$issueId = absint( $_GET['issue_id'] ?? 0 );

		if ( $issueId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid issue ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			$issue = $this->repository->findById( $issueId );

			if ( $issue === null ) {
				wp_send_json_error( array( 'message' => __( 'Issue not found', 'saga-manager-theme' ) ), 404 );
			}

			// Get entity names
			$entityName        = $this->getEntityName( $issue->entityId );
			$relatedEntityName = $this->getEntityName( $issue->relatedEntityId );

			wp_send_json_success(
				array(
					'issue' => array(
						'id'                  => $issue->id,
						'type'                => $issue->issueType,
						'type_label'          => $issue->getIssueTypeLabel(),
						'severity'            => $issue->severity,
						'severity_label'      => $issue->getSeverityLabel(),
						'description'         => $issue->description,
						'entity_id'           => $issue->entityId,
						'entity_name'         => $entityName,
						'related_entity_id'   => $issue->relatedEntityId,
						'related_entity_name' => $relatedEntityName,
						'suggested_fix'       => $issue->suggestedFix,
						'context'             => $issue->context,
						'status'              => $issue->status,
						'detected_at'         => $issue->detectedAt,
						'ai_confidence'       => $issue->aiConfidence,
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Get issue details failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to load issue details', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Load issues with pagination
	 *
	 * @return void
	 */
	public function loadIssues(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		$sagaId    = absint( $_GET['saga_id'] ?? 0 );
		$status    = sanitize_key( $_GET['status'] ?? '' );
		$severity  = sanitize_key( $_GET['severity'] ?? '' );
		$issueType = sanitize_key( $_GET['issue_type'] ?? '' );
		$page      = max( 1, absint( $_GET['page'] ?? 1 ) );
		$perPage   = 25;
		$offset    = ( $page - 1 ) * $perPage;

		if ( $sagaId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid saga ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			global $wpdb;
			$tableName = $wpdb->prefix . 'saga_consistency_issues';

			// Build WHERE clause
			$where  = array( 'saga_id = %d' );
			$params = array( $sagaId );

			if ( ! empty( $status ) ) {
				$where[]  = 'status = %s';
				$params[] = $status;
			}

			if ( ! empty( $severity ) ) {
				$where[]  = 'severity = %s';
				$params[] = $severity;
			}

			if ( ! empty( $issueType ) ) {
				$where[]  = 'issue_type = %s';
				$params[] = $issueType;
			}

			$whereClause = implode( ' AND ', $where );

			// Get total count
			$totalCount = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$tableName} WHERE {$whereClause}",
					...$params
				)
			);

			// Get issues
			$params[] = $perPage;
			$params[] = $offset;

			$issues = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tableName}
                WHERE {$whereClause}
                ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low', 'info'), detected_at DESC
                LIMIT %d OFFSET %d",
					...$params
				)
			);

			$issueData = array_map(
				function ( $row ) {
					$issue = \SagaManager\AI\Entities\ConsistencyIssue::fromDatabase( $row );
					return array(
						'id'             => $issue->id,
						'type'           => $issue->issueType,
						'type_label'     => $issue->getIssueTypeLabel(),
						'severity'       => $issue->severity,
						'severity_label' => $issue->getSeverityLabel(),
						'description'    => $issue->description,
						'entity_id'      => $issue->entityId,
						'entity_name'    => $this->getEntityName( $issue->entityId ),
						'detected_at'    => $issue->detectedAt,
						'detected_ago'   => human_time_diff( strtotime( $issue->detectedAt ), current_time( 'timestamp' ) ),
						'status'         => $issue->status,
						'ai_confidence'  => $issue->aiConfidence,
					);
				},
				$issues
			);

			wp_send_json_success(
				array(
					'issues'     => $issueData,
					'pagination' => array(
						'total'        => (int) $totalCount,
						'per_page'     => $perPage,
						'current_page' => $page,
						'total_pages'  => ceil( $totalCount / $perPage ),
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Load issues failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to load issues', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Update scan progress
	 *
	 * @param string $key      Progress key
	 * @param int    $progress Progress percentage
	 * @param string $step     Current step description
	 * @param string $status   Status (running, completed, failed)
	 * @return void
	 */
	private function updateProgress( string $key, int $progress, string $step, string $status = 'running' ): void {
		set_transient(
			$key,
			array(
				'status'       => $status,
				'progress'     => $progress,
				'current_step' => $step,
				'updated_at'   => current_time( 'mysql' ),
			),
			300
		);
	}

	/**
	 * Group issues by severity
	 *
	 * @param array $issues Issues array
	 * @return array
	 */
	private function groupBySeverity( array $issues ): array {
		$grouped = array(
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'info'     => 0,
		);

		foreach ( $issues as $issue ) {
			if ( isset( $grouped[ $issue->severity ] ) ) {
				++$grouped[ $issue->severity ];
			}
		}

		return $grouped;
	}

	/**
	 * Group issues by type
	 *
	 * @param array $issues Issues array
	 * @return array
	 */
	private function groupByType( array $issues ): array {
		$grouped = array();

		foreach ( $issues as $issue ) {
			if ( ! isset( $grouped[ $issue->issueType ] ) ) {
				$grouped[ $issue->issueType ] = 0;
			}
			++$grouped[ $issue->issueType ];
		}

		return $grouped;
	}

	/**
	 * Generate CSV from issues
	 *
	 * @param array $issues Issues array
	 * @return string
	 */
	private function generateCSV( array $issues ): string {
		$output = fopen( 'php://temp', 'r+' );

		// Headers
		fputcsv(
			$output,
			array(
				'ID',
				'Type',
				'Severity',
				'Description',
				'Entity',
				'Related Entity',
				'Status',
				'Detected At',
				'AI Confidence',
			)
		);

		// Data
		foreach ( $issues as $issue ) {
			fputcsv(
				$output,
				array(
					$issue->id,
					$issue->getIssueTypeLabel(),
					$issue->getSeverityLabel(),
					$issue->description,
					$this->getEntityName( $issue->entityId ),
					$this->getEntityName( $issue->relatedEntityId ),
					ucfirst( $issue->status ),
					$issue->detectedAt,
					$issue->aiConfidence !== null ? round( $issue->aiConfidence * 100, 1 ) . '%' : 'N/A',
				)
			);
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Get entity name by ID
	 *
	 * @param int|null $entityId Entity ID
	 * @return string
	 */
	private function getEntityName( ?int $entityId ): string {
		if ( $entityId === null ) {
			return '';
		}

		global $wpdb;
		$tableName = $wpdb->prefix . 'saga_entities';

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT canonical_name FROM {$tableName} WHERE id = %d",
				$entityId
			)
		);

		return $name ?? '';
	}

	/**
	 * Check entity in real-time (while editing)
	 *
	 * @return void
	 */
	public function checkEntityRealtime(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		// Check capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'saga-manager-theme' ) ), 403 );
		}

		$entityId    = absint( $_POST['entity_id'] ?? 0 );
		$postContent = wp_kses_post( $_POST['content'] ?? '' );

		if ( $entityId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entity ID', 'saga-manager-theme' ) ), 400 );
		}

		// Check cache first (60 second TTL)
		$cacheKey = 'saga_realtime_check_' . $entityId . '_' . md5( $postContent );
		$cached   = get_transient( $cacheKey );

		if ( $cached !== false ) {
			wp_send_json_success( $cached );
			return;
		}

		try {
			global $wpdb;
			$entityTable = $wpdb->prefix . 'saga_entities';

			// Get entity data
			$entity = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT saga_id, entity_type FROM {$entityTable} WHERE id = %d",
					$entityId
				)
			);

			if ( ! $entity ) {
				wp_send_json_error( array( 'message' => __( 'Entity not found', 'saga-manager-theme' ) ), 404 );
			}

			// Run lightweight check (only rule-based, no AI)
			$issues = $this->analyzer->analyze( $entity->saga_id, array( $entityId ), false );

			// Filter to only this entity's issues
			$entityIssues = array_filter( $issues, fn( $issue ) => $issue->entityId === $entityId );

			// Group by severity
			$bySeverity = $this->groupBySeverity( $entityIssues );

			// Calculate consistency score (0-100)
			$score = $this->calculateConsistencyScore( $bySeverity );

			$result = array(
				'entity_id'       => $entityId,
				'score'           => $score,
				'issues_count'    => count( $entityIssues ),
				'by_severity'     => $bySeverity,
				'critical_issues' => array_values( array_filter( $entityIssues, fn( $i ) => $i->severity === 'critical' ) ),
				'has_critical'    => $bySeverity['critical'] > 0,
				'status'          => $this->getScoreStatus( $score ),
			);

			// Cache for 60 seconds
			set_transient( $cacheKey, $result, 60 );

			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Real-time check failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Consistency check failed', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Get entity issues for sidebar display
	 *
	 * @return void
	 */
	public function getEntityIssues(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		$entityId = absint( $_GET['entity_id'] ?? 0 );

		if ( $entityId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entity ID', 'saga-manager-theme' ) ), 400 );
		}

		try {
			global $wpdb;
			$tableName = $wpdb->prefix . 'saga_consistency_issues';

			// Get open issues for this entity
			$issues = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$tableName}
                WHERE (entity_id = %d OR related_entity_id = %d)
                AND status = 'open'
                ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low', 'info')
                LIMIT 20",
					$entityId,
					$entityId
				)
			);

			$issueData = array_map(
				function ( $row ) {
					$issue = \SagaManager\AI\Entities\ConsistencyIssue::fromDatabase( $row );
					return array(
						'id'             => $issue->id,
						'type'           => $issue->issueType,
						'type_label'     => $issue->getIssueTypeLabel(),
						'severity'       => $issue->severity,
						'severity_label' => $issue->getSeverityLabel(),
						'description'    => $issue->description,
						'suggested_fix'  => $issue->suggestedFix,
						'ai_confidence'  => $issue->aiConfidence,
					);
				},
				$issues
			);

			wp_send_json_success(
				array(
					'issues' => $issueData,
					'count'  => count( $issueData ),
				)
			);

		} catch ( Exception $e ) {
			error_log( '[SAGA][AJAX][ERROR] Get entity issues failed: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => __( 'Failed to load issues', 'saga-manager-theme' ),
				),
				500
			);
		}
	}

	/**
	 * Dismiss inline warning
	 *
	 * @return void
	 */
	public function dismissInlineWarning(): void {
		// Verify nonce
		check_ajax_referer( 'saga_consistency_nonce', 'nonce' );

		$issueId = absint( $_POST['issue_id'] ?? 0 );
		$userId  = get_current_user_id();

		if ( $issueId === 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid issue ID', 'saga-manager-theme' ) ), 400 );
		}

		// Store dismissed warnings in user meta
		$dismissed = get_user_meta( $userId, 'saga_dismissed_warnings', true ) ?: array();
		if ( ! in_array( $issueId, $dismissed ) ) {
			$dismissed[] = $issueId;
			update_user_meta( $userId, 'saga_dismissed_warnings', $dismissed );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Warning dismissed', 'saga-manager-theme' ),
			)
		);
	}

	/**
	 * Calculate consistency score from severity counts
	 *
	 * @param array $severityCounts Severity counts array
	 * @return int Score 0-100
	 */
	private function calculateConsistencyScore( array $severityCounts ): int {
		$penalties = array(
			'critical' => 25,
			'high'     => 15,
			'medium'   => 8,
			'low'      => 3,
			'info'     => 1,
		);

		$totalPenalty = 0;
		foreach ( $severityCounts as $severity => $count ) {
			if ( isset( $penalties[ $severity ] ) ) {
				$totalPenalty += $penalties[ $severity ] * $count;
			}
		}

		// Cap at 100
		$score = max( 0, 100 - $totalPenalty );
		return $score;
	}

	/**
	 * Get score status label
	 *
	 * @param int $score Score 0-100
	 * @return string
	 */
	private function getScoreStatus( int $score ): string {
		if ( $score >= 90 ) {
			return 'excellent';
		} elseif ( $score >= 75 ) {
			return 'good';
		} elseif ( $score >= 50 ) {
			return 'fair';
		} else {
			return 'poor';
		}
	}
}

// Initialize handler
new Saga_Consistency_Ajax_Handler();
