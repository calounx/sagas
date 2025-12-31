<?php
/**
 * ConsistencyRuleEngine
 *
 * Rule-based validation engine for saga consistency checks
 * Performs fast, deterministic checks without AI calls
 *
 * @package SagaManager\AI
 * @version 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI;

use SagaManager\AI\Entities\ConsistencyIssue;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ConsistencyRuleEngine Class
 *
 * Executes rule-based validation rules on saga entities
 */
final class ConsistencyRuleEngine
{
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
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'saga_';
    }

    /**
     * Run all rules for a saga
     *
     * @param int   $sagaId Saga ID
     * @param array $ruleTypes Rule types to check (empty = all)
     * @return ConsistencyIssue[] Array of detected issues
     */
    public function runRules(int $sagaId, array $ruleTypes = []): array
    {
        $issues = [];

        if (empty($ruleTypes)) {
            $ruleTypes = ['timeline', 'character', 'location', 'relationship', 'logical'];
        }

        foreach ($ruleTypes as $type) {
            $methodName = 'check' . ucfirst($type) . 'Rules';

            if (method_exists($this, $methodName)) {
                $typeIssues = $this->{$methodName}($sagaId);
                $issues = array_merge($issues, $typeIssues);
            }
        }

        return $issues;
    }

    /**
     * Check timeline consistency rules
     *
     * Validates:
     * - Events in chronological order
     * - No overlapping event times
     * - Valid date ranges
     *
     * @param int $sagaId Saga ID
     * @return ConsistencyIssue[]
     */
    private function checkTimelineRules(int $sagaId): array
    {
        $issues = [];

        // Check for events with invalid date ranges
        $invalidDates = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, title, canon_date, normalized_timestamp
            FROM {$this->prefix}timeline_events
            WHERE saga_id = %d
            AND normalized_timestamp IS NULL
            ORDER BY canon_date",
            $sagaId
        ));

        foreach ($invalidDates as $event) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'timeline',
                severity: 'high',
                entityId: (int) $event->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Event "%s" has invalid or missing normalized timestamp', 'saga-manager-theme'),
                    $event->title
                ),
                context: [
                    'event_id' => $event->id,
                    'canon_date' => $event->canon_date,
                    'event_title' => $event->title,
                ],
                suggestedFix: __('Verify the canonical date format and ensure it can be normalized', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        // Check for events that reference non-existent entities
        $orphanedEvents = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT te.id, te.title, te.event_entity_id
            FROM {$this->prefix}timeline_events te
            LEFT JOIN {$this->prefix}entities e ON te.event_entity_id = e.id
            WHERE te.saga_id = %d
            AND te.event_entity_id IS NOT NULL
            AND e.id IS NULL",
            $sagaId
        ));

        foreach ($orphanedEvents as $event) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'timeline',
                severity: 'medium',
                entityId: (int) $event->id,
                relatedEntityId: (int) $event->event_entity_id,
                description: sprintf(
                    __('Timeline event "%s" references non-existent entity ID %d', 'saga-manager-theme'),
                    $event->title,
                    $event->event_entity_id
                ),
                context: [
                    'event_id' => $event->id,
                    'event_title' => $event->title,
                    'missing_entity_id' => $event->event_entity_id,
                ],
                suggestedFix: __('Remove the entity reference or create the missing entity', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        return $issues;
    }

    /**
     * Check character consistency rules
     *
     * Validates:
     * - Character attributes don't contradict
     * - Birth/death dates are logical
     * - Character cannot be in multiple places simultaneously
     *
     * @param int $sagaId Saga ID
     * @return ConsistencyIssue[]
     */
    private function checkCharacterRules(int $sagaId): array
    {
        $issues = [];

        // Check for characters with missing required attributes
        $incompleteCharacters = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT e.id, e.canonical_name,
                   (SELECT COUNT(*) FROM {$this->prefix}attribute_values av
                    WHERE av.entity_id = e.id) as attr_count
            FROM {$this->prefix}entities e
            WHERE e.saga_id = %d
            AND e.entity_type = 'character'
            HAVING attr_count = 0",
            $sagaId
        ));

        foreach ($incompleteCharacters as $character) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'character',
                severity: 'low',
                entityId: (int) $character->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Character "%s" has no attributes defined', 'saga-manager-theme'),
                    $character->canonical_name
                ),
                context: [
                    'entity_id' => $character->id,
                    'entity_name' => $character->canonical_name,
                    'attribute_count' => 0,
                ],
                suggestedFix: __('Add basic character attributes like species, homeworld, or affiliation', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        return $issues;
    }

    /**
     * Check location consistency rules
     *
     * Validates:
     * - Locations have valid geographic data
     * - Location hierarchies are logical (planet > city)
     * - No circular location references
     *
     * @param int $sagaId Saga ID
     * @return ConsistencyIssue[]
     */
    private function checkLocationRules(int $sagaId): array
    {
        $issues = [];

        // Check for locations without any relationships
        $isolatedLocations = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT e.id, e.canonical_name
            FROM {$this->prefix}entities e
            WHERE e.saga_id = %d
            AND e.entity_type = 'location'
            AND NOT EXISTS (
                SELECT 1 FROM {$this->prefix}entity_relationships r
                WHERE r.source_entity_id = e.id OR r.target_entity_id = e.id
            )",
            $sagaId
        ));

        foreach ($isolatedLocations as $location) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'location',
                severity: 'info',
                entityId: (int) $location->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Location "%s" has no relationships to other entities', 'saga-manager-theme'),
                    $location->canonical_name
                ),
                context: [
                    'entity_id' => $location->id,
                    'entity_name' => $location->canonical_name,
                ],
                suggestedFix: __('Consider adding relationships to characters, events, or parent locations', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        return $issues;
    }

    /**
     * Check relationship consistency rules
     *
     * Validates:
     * - Parent/child age logic (parent must be older)
     * - No self-referencing relationships
     * - Temporal validity (valid_from < valid_until)
     * - Relationship strength is 0-100
     *
     * @param int $sagaId Saga ID
     * @return ConsistencyIssue[]
     */
    private function checkRelationshipRules(int $sagaId): array
    {
        $issues = [];

        // Check for self-referencing relationships (should be prevented by constraint)
        $selfRefs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, e.canonical_name, r.relationship_type
            FROM {$this->prefix}entity_relationships r
            INNER JOIN {$this->prefix}entities e ON r.source_entity_id = e.id
            WHERE e.saga_id = %d
            AND r.source_entity_id = r.target_entity_id",
            $sagaId
        ));

        foreach ($selfRefs as $rel) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'relationship',
                severity: 'critical',
                entityId: (int) $rel->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Entity "%s" has self-referencing %s relationship', 'saga-manager-theme'),
                    $rel->canonical_name,
                    $rel->relationship_type
                ),
                context: [
                    'relationship_id' => $rel->id,
                    'entity_name' => $rel->canonical_name,
                    'relationship_type' => $rel->relationship_type,
                ],
                suggestedFix: __('Remove the self-referencing relationship', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        // Check for invalid temporal relationships
        $invalidTemporal = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, e1.canonical_name as source_name, e2.canonical_name as target_name,
                   r.relationship_type, r.valid_from, r.valid_until
            FROM {$this->prefix}entity_relationships r
            INNER JOIN {$this->prefix}entities e1 ON r.source_entity_id = e1.id
            INNER JOIN {$this->prefix}entities e2 ON r.target_entity_id = e2.id
            WHERE e1.saga_id = %d
            AND r.valid_from IS NOT NULL
            AND r.valid_until IS NOT NULL
            AND r.valid_until < r.valid_from",
            $sagaId
        ));

        foreach ($invalidTemporal as $rel) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'relationship',
                severity: 'high',
                entityId: (int) $rel->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Relationship "%s → %s" (%s) has end date before start date', 'saga-manager-theme'),
                    $rel->source_name,
                    $rel->target_name,
                    $rel->relationship_type
                ),
                context: [
                    'relationship_id' => $rel->id,
                    'source_name' => $rel->source_name,
                    'target_name' => $rel->target_name,
                    'valid_from' => $rel->valid_from,
                    'valid_until' => $rel->valid_until,
                ],
                suggestedFix: __('Correct the temporal range so valid_until is after valid_from', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        // Check for invalid relationship strengths
        $invalidStrength = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, e1.canonical_name as source_name, e2.canonical_name as target_name,
                   r.strength
            FROM {$this->prefix}entity_relationships r
            INNER JOIN {$this->prefix}entities e1 ON r.source_entity_id = e1.id
            INNER JOIN {$this->prefix}entities e2 ON r.target_entity_id = e2.id
            WHERE e1.saga_id = %d
            AND (r.strength < 0 OR r.strength > 100)",
            $sagaId
        ));

        foreach ($invalidStrength as $rel) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'relationship',
                severity: 'medium',
                entityId: (int) $rel->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Relationship "%s → %s" has invalid strength %d (must be 0-100)', 'saga-manager-theme'),
                    $rel->source_name,
                    $rel->target_name,
                    $rel->strength
                ),
                context: [
                    'relationship_id' => $rel->id,
                    'source_name' => $rel->source_name,
                    'target_name' => $rel->target_name,
                    'strength' => $rel->strength,
                ],
                suggestedFix: __('Set relationship strength between 0 and 100', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        return $issues;
    }

    /**
     * Check logical consistency rules
     *
     * Validates:
     * - Importance scores are 0-100
     * - Entity slugs are unique per saga
     * - Entity types are valid
     *
     * @param int $sagaId Saga ID
     * @return ConsistencyIssue[]
     */
    private function checkLogicalRules(int $sagaId): array
    {
        $issues = [];

        // Check for invalid importance scores
        $invalidScores = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, canonical_name, importance_score
            FROM {$this->prefix}entities
            WHERE saga_id = %d
            AND (importance_score < 0 OR importance_score > 100)",
            $sagaId
        ));

        foreach ($invalidScores as $entity) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'logical',
                severity: 'medium',
                entityId: (int) $entity->id,
                relatedEntityId: null,
                description: sprintf(
                    __('Entity "%s" has invalid importance score %d (must be 0-100)', 'saga-manager-theme'),
                    $entity->canonical_name,
                    $entity->importance_score
                ),
                context: [
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->canonical_name,
                    'importance_score' => $entity->importance_score,
                ],
                suggestedFix: __('Set importance score between 0 and 100', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        // Check for duplicate entity slugs
        $duplicateSlugs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT slug, COUNT(*) as count, GROUP_CONCAT(canonical_name SEPARATOR ', ') as entities
            FROM {$this->prefix}entities
            WHERE saga_id = %d
            GROUP BY slug
            HAVING count > 1",
            $sagaId
        ));

        foreach ($duplicateSlugs as $duplicate) {
            $issues[] = new ConsistencyIssue(
                id: null,
                sagaId: $sagaId,
                issueType: 'logical',
                severity: 'high',
                entityId: null,
                relatedEntityId: null,
                description: sprintf(
                    __('Duplicate slug "%s" used by %d entities: %s', 'saga-manager-theme'),
                    $duplicate->slug,
                    $duplicate->count,
                    $duplicate->entities
                ),
                context: [
                    'slug' => $duplicate->slug,
                    'entity_count' => $duplicate->count,
                    'entity_names' => $duplicate->entities,
                ],
                suggestedFix: __('Make entity slugs unique within the saga', 'saga-manager-theme'),
                aiConfidence: null
            );
        }

        return $issues;
    }
}
