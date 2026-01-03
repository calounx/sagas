<?php
/**
 * ConsistencyIssue Value Object
 *
 * Represents a single consistency issue detected in saga entities
 * Immutable value object following DDD principles
 *
 * @package SagaManager\AI\Entities
 * @version 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Entities;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ConsistencyIssue Entity
 *
 * Immutable value object representing a detected consistency issue
 */
final readonly class ConsistencyIssue {

	/**
	 * @var int|null Issue ID (null for new issues)
	 */
	public ?int $id;

	/**
	 * @var int Saga ID
	 */
	public int $sagaId;

	/**
	 * @var string Issue type (timeline, character, location, relationship, logical)
	 */
	public string $issueType;

	/**
	 * @var string Severity (critical, high, medium, low, info)
	 */
	public string $severity;

	/**
	 * @var int|null Primary entity ID involved
	 */
	public ?int $entityId;

	/**
	 * @var int|null Related secondary entity ID
	 */
	public ?int $relatedEntityId;

	/**
	 * @var string Issue description
	 */
	public string $description;

	/**
	 * @var array Context data (timestamps, attribute values, etc.)
	 */
	public array $context;

	/**
	 * @var string|null Suggested fix/resolution
	 */
	public ?string $suggestedFix;

	/**
	 * @var string Status (open, resolved, dismissed, false_positive)
	 */
	public string $status;

	/**
	 * @var string Detection timestamp
	 */
	public string $detectedAt;

	/**
	 * @var string|null Resolution timestamp
	 */
	public ?string $resolvedAt;

	/**
	 * @var int|null User ID who resolved the issue
	 */
	public ?int $resolvedBy;

	/**
	 * @var float|null AI confidence score (0.00-1.00)
	 */
	public ?float $aiConfidence;

	/**
	 * Constructor - all properties readonly
	 *
	 * @param int|null    $id              Issue ID
	 * @param int         $sagaId          Saga ID
	 * @param string      $issueType       Issue type
	 * @param string      $severity        Severity level
	 * @param int|null    $entityId        Primary entity ID
	 * @param int|null    $relatedEntityId Secondary entity ID
	 * @param string      $description     Issue description
	 * @param array       $context         Context data
	 * @param string|null $suggestedFix    Suggested fix
	 * @param string      $status          Status
	 * @param string      $detectedAt      Detection timestamp
	 * @param string|null $resolvedAt      Resolution timestamp
	 * @param int|null    $resolvedBy      Resolver user ID
	 * @param float|null  $aiConfidence    AI confidence
	 */
	public function __construct(
		?int $id,
		int $sagaId,
		string $issueType,
		string $severity,
		?int $entityId,
		?int $relatedEntityId,
		string $description,
		array $context,
		?string $suggestedFix,
		string $status = 'open',
		string $detectedAt = '',
		?string $resolvedAt = null,
		?int $resolvedBy = null,
		?float $aiConfidence = null
	) {
		$this->id              = $id;
		$this->sagaId          = $sagaId;
		$this->issueType       = $this->validateIssueType( $issueType );
		$this->severity        = $this->validateSeverity( $severity );
		$this->entityId        = $entityId;
		$this->relatedEntityId = $relatedEntityId;
		$this->description     = $description;
		$this->context         = $context;
		$this->suggestedFix    = $suggestedFix;
		$this->status          = $this->validateStatus( $status );
		$this->detectedAt      = $detectedAt ?: current_time( 'mysql' );
		$this->resolvedAt      = $resolvedAt;
		$this->resolvedBy      = $resolvedBy;
		$this->aiConfidence    = $this->validateConfidence( $aiConfidence );
	}

	/**
	 * Validate issue type
	 *
	 * @param string $type Issue type
	 * @return string Validated type
	 * @throws \InvalidArgumentException
	 */
	private function validateIssueType( string $type ): string {
		$validTypes = array( 'timeline', 'character', 'location', 'relationship', 'logical' );

		if ( ! in_array( $type, $validTypes, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid issue type: %s. Must be one of: %s', $type, implode( ', ', $validTypes ) )
			);
		}

		return $type;
	}

	/**
	 * Validate severity level
	 *
	 * @param string $severity Severity level
	 * @return string Validated severity
	 * @throws \InvalidArgumentException
	 */
	private function validateSeverity( string $severity ): string {
		$validSeverities = array( 'critical', 'high', 'medium', 'low', 'info' );

		if ( ! in_array( $severity, $validSeverities, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid severity: %s. Must be one of: %s', $severity, implode( ', ', $validSeverities ) )
			);
		}

		return $severity;
	}

	/**
	 * Validate status
	 *
	 * @param string $status Status
	 * @return string Validated status
	 * @throws \InvalidArgumentException
	 */
	private function validateStatus( string $status ): string {
		$validStatuses = array( 'open', 'resolved', 'dismissed', 'false_positive' );

		if ( ! in_array( $status, $validStatuses, true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid status: %s. Must be one of: %s', $status, implode( ', ', $validStatuses ) )
			);
		}

		return $status;
	}

	/**
	 * Validate AI confidence score
	 *
	 * @param float|null $confidence Confidence score
	 * @return float|null Validated confidence
	 * @throws \InvalidArgumentException
	 */
	private function validateConfidence( ?float $confidence ): ?float {
		if ( $confidence === null ) {
			return null;
		}

		if ( $confidence < 0.0 || $confidence > 1.0 ) {
			throw new \InvalidArgumentException(
				sprintf( 'AI confidence must be between 0.00 and 1.00, got: %f', $confidence )
			);
		}

		return round( $confidence, 2 );
	}

	/**
	 * Convert to array for database storage
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'id'                => $this->id,
			'saga_id'           => $this->sagaId,
			'issue_type'        => $this->issueType,
			'severity'          => $this->severity,
			'entity_id'         => $this->entityId,
			'related_entity_id' => $this->relatedEntityId,
			'description'       => $this->description,
			'context'           => wp_json_encode( $this->context ),
			'suggested_fix'     => $this->suggestedFix,
			'status'            => $this->status,
			'detected_at'       => $this->detectedAt,
			'resolved_at'       => $this->resolvedAt,
			'resolved_by'       => $this->resolvedBy,
			'ai_confidence'     => $this->aiConfidence,
		);
	}

	/**
	 * Create from database row
	 *
	 * @param object|array $row Database row
	 * @return self
	 */
	public static function fromDatabase( $row ): self {
		$data = is_object( $row ) ? (array) $row : $row;

		return new self(
			id: isset( $data['id'] ) ? (int) $data['id'] : null,
			sagaId: (int) $data['saga_id'],
			issueType: (string) $data['issue_type'],
			severity: (string) $data['severity'],
			entityId: isset( $data['entity_id'] ) ? (int) $data['entity_id'] : null,
			relatedEntityId: isset( $data['related_entity_id'] ) ? (int) $data['related_entity_id'] : null,
			description: (string) $data['description'],
			context: isset( $data['context'] ) ? json_decode( $data['context'], true ) : array(),
			suggestedFix: $data['suggested_fix'] ?? null,
			status: $data['status'] ?? 'open',
			detectedAt: $data['detected_at'] ?? current_time( 'mysql' ),
			resolvedAt: $data['resolved_at'] ?? null,
			resolvedBy: isset( $data['resolved_by'] ) ? (int) $data['resolved_by'] : null,
			aiConfidence: isset( $data['ai_confidence'] ) ? (float) $data['ai_confidence'] : null
		);
	}

	/**
	 * Create a resolved copy of this issue
	 *
	 * @param int $userId User ID who resolved
	 * @return self
	 */
	public function resolve( int $userId ): self {
		return new self(
			id: $this->id,
			sagaId: $this->sagaId,
			issueType: $this->issueType,
			severity: $this->severity,
			entityId: $this->entityId,
			relatedEntityId: $this->relatedEntityId,
			description: $this->description,
			context: $this->context,
			suggestedFix: $this->suggestedFix,
			status: 'resolved',
			detectedAt: $this->detectedAt,
			resolvedAt: current_time( 'mysql' ),
			resolvedBy: $userId,
			aiConfidence: $this->aiConfidence
		);
	}

	/**
	 * Create a dismissed copy of this issue
	 *
	 * @param int  $userId User ID who dismissed
	 * @param bool $isFalsePositive Whether this is a false positive
	 * @return self
	 */
	public function dismiss( int $userId, bool $isFalsePositive = false ): self {
		return new self(
			id: $this->id,
			sagaId: $this->sagaId,
			issueType: $this->issueType,
			severity: $this->severity,
			entityId: $this->entityId,
			relatedEntityId: $this->relatedEntityId,
			description: $this->description,
			context: $this->context,
			suggestedFix: $this->suggestedFix,
			status: $isFalsePositive ? 'false_positive' : 'dismissed',
			detectedAt: $this->detectedAt,
			resolvedAt: current_time( 'mysql' ),
			resolvedBy: $userId,
			aiConfidence: $this->aiConfidence
		);
	}

	/**
	 * Check if issue is open
	 *
	 * @return bool
	 */
	public function isOpen(): bool {
		return $this->status === 'open';
	}

	/**
	 * Check if issue is resolved
	 *
	 * @return bool
	 */
	public function isResolved(): bool {
		return $this->status === 'resolved';
	}

	/**
	 * Get human-readable severity label
	 *
	 * @return string
	 */
	public function getSeverityLabel(): string {
		return match ( $this->severity ) {
			'critical' => __( 'Critical', 'saga-manager-theme' ),
			'high' => __( 'High', 'saga-manager-theme' ),
			'medium' => __( 'Medium', 'saga-manager-theme' ),
			'low' => __( 'Low', 'saga-manager-theme' ),
			'info' => __( 'Info', 'saga-manager-theme' ),
			default => ucfirst( $this->severity ),
		};
	}

	/**
	 * Get human-readable issue type label
	 *
	 * @return string
	 */
	public function getIssueTypeLabel(): string {
		return match ( $this->issueType ) {
			'timeline' => __( 'Timeline Inconsistency', 'saga-manager-theme' ),
			'character' => __( 'Character Contradiction', 'saga-manager-theme' ),
			'location' => __( 'Location Conflict', 'saga-manager-theme' ),
			'relationship' => __( 'Relationship Error', 'saga-manager-theme' ),
			'logical' => __( 'Logical Error', 'saga-manager-theme' ),
			default => ucfirst( $this->issueType ),
		};
	}
}
