<?php
/**
 * ConsistencyAnalyzer
 *
 * Orchestrates rule-based and AI-powered consistency analysis
 * Hybrid approach for optimal performance
 *
 * @package SagaManager\AI
 * @version 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI;

use SagaManager\AI\Entities\ConsistencyIssue;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConsistencyAnalyzer Class
 *
 * Main orchestrator for consistency checking
 */
final class ConsistencyAnalyzer {

	/**
	 * @var ConsistencyRuleEngine Rule engine
	 */
	private ConsistencyRuleEngine $ruleEngine;

	/**
	 * @var AIClient AI client
	 */
	private AIClient $aiClient;

	/**
	 * @var ConsistencyRepository Repository
	 */
	private ConsistencyRepository $repository;

	/**
	 * @var \wpdb WordPress database object
	 */
	private \wpdb $wpdb;

	/**
	 * @var string Table prefix
	 */
	private string $prefix;

	/**
	 * Constructor
	 *
	 * @param ConsistencyRuleEngine $ruleEngine Rule engine
	 * @param AIClient              $aiClient   AI client
	 * @param ConsistencyRepository $repository Repository
	 */
	public function __construct(
		ConsistencyRuleEngine $ruleEngine,
		AIClient $aiClient,
		ConsistencyRepository $repository
	) {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->prefix = $wpdb->prefix . 'saga_';

		$this->ruleEngine = $ruleEngine;
		$this->aiClient   = $aiClient;
		$this->repository = $repository;
	}

	/**
	 * Analyze saga for consistency issues
	 *
	 * Uses hybrid approach:
	 * 1. Fast rule-based checks first
	 * 2. AI semantic analysis for complex issues
	 *
	 * @param int   $sagaId      Saga ID
	 * @param array $options     Analysis options
	 * @param bool  $useAI       Whether to use AI analysis
	 * @param array $ruleTypes   Rule types to check
	 * @return ConsistencyIssue[]
	 */
	public function analyze(
		int $sagaId,
		array $options = array(),
		bool $useAI = true,
		array $ruleTypes = array()
	): array {
		$startTime = microtime( true );

		// Step 1: Run rule-based checks (fast)
		$ruleIssues = $this->ruleEngine->runRules( $sagaId, $ruleTypes );

		error_log(
			sprintf(
				'[SAGA][AI] Rule engine found %d issues in %.2fms',
				count( $ruleIssues ),
				( microtime( true ) - $startTime ) * 1000
			)
		);

		// Step 2: Run AI analysis if enabled
		$aiIssues = array();

		if ( $useAI && $this->isAIEnabled() ) {
			$context  = $this->buildAnalysisContext( $sagaId, $options );
			$aiIssues = $this->aiClient->analyzeConsistency( $sagaId, $context );

			error_log(
				sprintf(
					'[SAGA][AI] AI analysis found %d issues in %.2fms',
					count( $aiIssues ),
					( microtime( true ) - $startTime ) * 1000
				)
			);
		}

		// Step 3: Merge and deduplicate issues
		$allIssues = $this->mergeIssues( $ruleIssues, $aiIssues );

		// Step 4: Persist new issues to database
		$this->persistIssues( $allIssues );

		$duration = ( microtime( true ) - $startTime ) * 1000;

		error_log(
			sprintf(
				'[SAGA][AI] Total analysis: %d issues found in %.2fms',
				count( $allIssues ),
				$duration
			)
		);

		return $allIssues;
	}

	/**
	 * Build analysis context for AI
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $options Options
	 * @return array
	 */
	private function buildAnalysisContext( int $sagaId, array $options ): array {
		$context = array(
			'entities'      => $this->getEntitiesContext( $sagaId, $options ),
			'relationships' => $this->getRelationshipsContext( $sagaId, $options ),
			'timeline'      => $this->getTimelineContext( $sagaId, $options ),
		);

		return $context;
	}

	/**
	 * Get entities context
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $options Options
	 * @return array
	 */
	private function getEntitiesContext( int $sagaId, array $options ): array {
		$limit = $options['entity_limit'] ?? 50;

		$entities = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, canonical_name, entity_type, importance_score
            FROM {$this->prefix}entities
            WHERE saga_id = %d
            ORDER BY importance_score DESC
            LIMIT %d",
				$sagaId,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $entity ) {
				return array(
					'id'          => (int) $entity['id'],
					'name'        => $entity['canonical_name'],
					'type'        => $entity['entity_type'],
					'importance'  => (int) $entity['importance_score'],
					'description' => $this->getEntityDescription( $entity['id'] ),
				);
			},
			$entities
		);
	}

	/**
	 * Get entity description from content fragments
	 *
	 * @param int $entityId Entity ID
	 * @return string
	 */
	private function getEntityDescription( int $entityId ): string {
		$fragment = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT fragment_text
            FROM {$this->prefix}content_fragments
            WHERE entity_id = %d
            ORDER BY token_count DESC
            LIMIT 1",
				$entityId
			)
		);

		return $fragment ? substr( $fragment, 0, 200 ) : '';
	}

	/**
	 * Get relationships context
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $options Options
	 * @return array
	 */
	private function getRelationshipsContext( int $sagaId, array $options ): array {
		$limit = $options['relationship_limit'] ?? 100;

		$relationships = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT r.id, e1.canonical_name as source_name, e2.canonical_name as target_name,
                   r.relationship_type, r.strength, r.valid_from, r.valid_until
            FROM {$this->prefix}entity_relationships r
            INNER JOIN {$this->prefix}entities e1 ON r.source_entity_id = e1.id
            INNER JOIN {$this->prefix}entities e2 ON r.target_entity_id = e2.id
            WHERE e1.saga_id = %d
            ORDER BY r.strength DESC
            LIMIT %d",
				$sagaId,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $rel ) {
				return array(
					'id'          => (int) $rel['id'],
					'source'      => $rel['source_name'],
					'target'      => $rel['target_name'],
					'type'        => $rel['relationship_type'],
					'strength'    => (int) $rel['strength'],
					'valid_from'  => $rel['valid_from'],
					'valid_until' => $rel['valid_until'],
				);
			},
			$relationships
		);
	}

	/**
	 * Get timeline context
	 *
	 * @param int   $sagaId  Saga ID
	 * @param array $options Options
	 * @return array
	 */
	private function getTimelineContext( int $sagaId, array $options ): array {
		$limit = $options['timeline_limit'] ?? 50;

		$events = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, title, canon_date, normalized_timestamp, description
            FROM {$this->prefix}timeline_events
            WHERE saga_id = %d
            ORDER BY normalized_timestamp ASC
            LIMIT %d",
				$sagaId,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $event ) {
				return array(
					'id'          => (int) $event['id'],
					'date'        => $event['canon_date'],
					'timestamp'   => (int) $event['normalized_timestamp'],
					'description' => $event['title'] . ': ' . ( $event['description'] ?? '' ),
				);
			},
			$events
		);
	}

	/**
	 * Merge rule-based and AI issues, removing duplicates
	 *
	 * @param ConsistencyIssue[] $ruleIssues Rule issues
	 * @param ConsistencyIssue[] $aiIssues   AI issues
	 * @return ConsistencyIssue[]
	 */
	private function mergeIssues( array $ruleIssues, array $aiIssues ): array {
		$merged = $ruleIssues;

		// Add AI issues that are not duplicates
		foreach ( $aiIssues as $aiIssue ) {
			if ( ! $this->isDuplicateIssue( $aiIssue, $merged ) ) {
				$merged[] = $aiIssue;
			}
		}

		// Sort by severity
		usort(
			$merged,
			function ( $a, $b ) {
				$severityOrder = array(
					'critical' => 0,
					'high'     => 1,
					'medium'   => 2,
					'low'      => 3,
					'info'     => 4,
				);
				return ( $severityOrder[ $a->severity ] ?? 99 ) <=> ( $severityOrder[ $b->severity ] ?? 99 );
			}
		);

		return $merged;
	}

	/**
	 * Check if issue is duplicate
	 *
	 * @param ConsistencyIssue   $issue   Issue to check
	 * @param ConsistencyIssue[] $existing Existing issues
	 * @return bool
	 */
	private function isDuplicateIssue( ConsistencyIssue $issue, array $existing ): bool {
		foreach ( $existing as $existingIssue ) {
			if (
				$existingIssue->issueType === $issue->issueType &&
				$existingIssue->entityId === $issue->entityId &&
				similar_text( $existingIssue->description, $issue->description, $percent ) &&
				$percent > 80.0
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persist new issues to database
	 *
	 * @param ConsistencyIssue[] $issues Issues to persist
	 * @return void
	 */
	private function persistIssues( array $issues ): void {
		foreach ( $issues as $issue ) {
			if ( $issue->id === null ) {
				$this->repository->save( $issue );
			}
		}
	}

	/**
	 * Check if AI is enabled in settings
	 *
	 * @return bool
	 */
	private function isAIEnabled(): bool {
		return (bool) get_option( 'saga_ai_consistency_enabled', false );
	}

	/**
	 * Get issues for a saga
	 *
	 * @param int    $sagaId Saga ID
	 * @param string $status Status filter
	 * @return ConsistencyIssue[]
	 */
	public function getIssues( int $sagaId, string $status = 'open' ): array {
		return $this->repository->findBySaga( $sagaId, $status );
	}

	/**
	 * Resolve an issue
	 *
	 * @param int $issueId Issue ID
	 * @param int $userId  User ID
	 * @return bool
	 */
	public function resolveIssue( int $issueId, int $userId ): bool {
		$issue = $this->repository->findById( $issueId );

		if ( $issue === null ) {
			return false;
		}

		$resolved = $issue->resolve( $userId );
		return $this->repository->update( $resolved );
	}

	/**
	 * Dismiss an issue
	 *
	 * @param int  $issueId         Issue ID
	 * @param int  $userId          User ID
	 * @param bool $isFalsePositive Whether this is a false positive
	 * @return bool
	 */
	public function dismissIssue( int $issueId, int $userId, bool $isFalsePositive = false ): bool {
		$issue = $this->repository->findById( $issueId );

		if ( $issue === null ) {
			return false;
		}

		$dismissed = $issue->dismiss( $userId, $isFalsePositive );
		return $this->repository->update( $dismissed );
	}

	/**
	 * Get consistency statistics
	 *
	 * @param int $sagaId Saga ID
	 * @return array
	 */
	public function getStatistics( int $sagaId ): array {
		return $this->repository->getStatistics( $sagaId );
	}
}
