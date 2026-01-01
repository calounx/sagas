<?php
/**
 * ConsistencyIssue Value Object Unit Tests
 *
 * Tests the ConsistencyIssue immutable value object including:
 * - Construction and validation
 * - Status transitions (resolve, dismiss)
 * - Array conversions (toArray, fromDatabase)
 * - Edge cases and error handling
 *
 * @package SagaManager\Tests\Unit\ConsistencyGuardian
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\ConsistencyGuardian;

use SagaManager\Tests\TestCase;
use SagaManager\AI\Entities\ConsistencyIssue;

/**
 * ConsistencyIssue Unit Test Class
 */
class ConsistencyIssueTest extends TestCase
{
    /**
     * Test that ConsistencyIssue can be created with valid data
     */
    public function test_can_create_consistency_issue(): void
    {
        $issue = new ConsistencyIssue(
            id: 1,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: 10,
            relatedEntityId: 20,
            description: 'Character age inconsistency',
            context: ['age_at_event_1' => 25, 'age_at_event_2' => 20],
            suggestedFix: 'Adjust timeline dates',
            status: 'open',
            detectedAt: '2024-01-01 12:00:00',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: 0.85
        );

        $this->assertInstanceOf(ConsistencyIssue::class, $issue);
        $this->assertEquals(1, $issue->id);
        $this->assertEquals(1, $issue->sagaId);
        $this->assertEquals('timeline', $issue->issueType);
        $this->assertEquals('high', $issue->severity);
        $this->assertEquals(10, $issue->entityId);
        $this->assertEquals(20, $issue->relatedEntityId);
        $this->assertEquals('Character age inconsistency', $issue->description);
        $this->assertEquals(['age_at_event_1' => 25, 'age_at_event_2' => 20], $issue->context);
        $this->assertEquals('Adjust timeline dates', $issue->suggestedFix);
        $this->assertEquals('open', $issue->status);
        $this->assertEquals('2024-01-01 12:00:00', $issue->detectedAt);
        $this->assertNull($issue->resolvedAt);
        $this->assertNull($issue->resolvedBy);
        $this->assertEquals(0.85, $issue->aiConfidence);
    }

    /**
     * Test that invalid issue type throws exception
     */
    public function test_validates_issue_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid issue type');

        new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'invalid_type',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null
        );
    }

    /**
     * Test that valid issue types are accepted
     */
    public function test_accepts_valid_issue_types(): void
    {
        $validTypes = ['timeline', 'character', 'location', 'relationship', 'logical'];

        foreach ($validTypes as $type) {
            $issue = new ConsistencyIssue(
                id: null,
                sagaId: 1,
                issueType: $type,
                severity: 'medium',
                entityId: null,
                relatedEntityId: null,
                description: 'Test',
                context: [],
                suggestedFix: null
            );

            $this->assertEquals($type, $issue->issueType);
        }
    }

    /**
     * Test that invalid severity throws exception
     */
    public function test_validates_severity_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid severity');

        new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'catastrophic',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null
        );
    }

    /**
     * Test that all valid severity levels are accepted
     */
    public function test_accepts_valid_severity_levels(): void
    {
        $validSeverities = ['critical', 'high', 'medium', 'low', 'info'];

        foreach ($validSeverities as $severity) {
            $issue = new ConsistencyIssue(
                id: null,
                sagaId: 1,
                issueType: 'timeline',
                severity: $severity,
                entityId: null,
                relatedEntityId: null,
                description: 'Test',
                context: [],
                suggestedFix: null
            );

            $this->assertEquals($severity, $issue->severity);
        }
    }

    /**
     * Test that invalid status throws exception
     */
    public function test_validates_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');

        new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'invalid_status'
        );
    }

    /**
     * Test that all valid statuses are accepted
     */
    public function test_accepts_valid_statuses(): void
    {
        $validStatuses = ['open', 'resolved', 'dismissed', 'false_positive'];

        foreach ($validStatuses as $status) {
            $issue = new ConsistencyIssue(
                id: null,
                sagaId: 1,
                issueType: 'timeline',
                severity: 'medium',
                entityId: null,
                relatedEntityId: null,
                description: 'Test',
                context: [],
                suggestedFix: null,
                status: $status
            );

            $this->assertEquals($status, $issue->status);
        }
    }

    /**
     * Test that AI confidence is validated to 0-1 range
     */
    public function test_validates_ai_confidence_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AI confidence must be between 0.00 and 1.00');

        new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open',
            detectedAt: '',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: 1.5
        );
    }

    /**
     * Test that AI confidence below 0 throws exception
     */
    public function test_validates_ai_confidence_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open',
            detectedAt: '',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: -0.1
        );
    }

    /**
     * Test that AI confidence is rounded to 2 decimals
     */
    public function test_rounds_ai_confidence(): void
    {
        $issue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open',
            detectedAt: '',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: 0.8567
        );

        $this->assertEquals(0.86, $issue->aiConfidence);
    }

    /**
     * Test that null AI confidence is accepted
     */
    public function test_accepts_null_ai_confidence(): void
    {
        $issue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open',
            detectedAt: '',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: null
        );

        $this->assertNull($issue->aiConfidence);
    }

    /**
     * Test converting ConsistencyIssue to array for database storage
     */
    public function test_converts_to_array(): void
    {
        $issue = new ConsistencyIssue(
            id: 1,
            sagaId: 2,
            issueType: 'character',
            severity: 'medium',
            entityId: 10,
            relatedEntityId: 20,
            description: 'Test issue',
            context: ['key' => 'value'],
            suggestedFix: 'Fix suggestion',
            status: 'open',
            detectedAt: '2024-01-01 12:00:00',
            resolvedAt: null,
            resolvedBy: null,
            aiConfidence: 0.75
        );

        $array = $issue->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('saga_id', $array);
        $this->assertArrayHasKey('issue_type', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('entity_id', $array);
        $this->assertArrayHasKey('related_entity_id', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertArrayHasKey('suggested_fix', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('detected_at', $array);
        $this->assertArrayHasKey('resolved_at', $array);
        $this->assertArrayHasKey('resolved_by', $array);
        $this->assertArrayHasKey('ai_confidence', $array);

        $this->assertEquals(1, $array['id']);
        $this->assertEquals(2, $array['saga_id']);
        $this->assertEquals('character', $array['issue_type']);
        $this->assertEquals('medium', $array['severity']);
        $this->assertEquals(0.75, $array['ai_confidence']);
    }

    /**
     * Test creating ConsistencyIssue from database row
     */
    public function test_creates_from_database_row(): void
    {
        $row = [
            'id' => '1',
            'saga_id' => '2',
            'issue_type' => 'timeline',
            'severity' => 'high',
            'entity_id' => '10',
            'related_entity_id' => '20',
            'description' => 'Test issue',
            'context' => '{"key":"value"}',
            'suggested_fix' => 'Fix it',
            'status' => 'open',
            'detected_at' => '2024-01-01 12:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
            'ai_confidence' => '0.85'
        ];

        $issue = ConsistencyIssue::fromDatabase($row);

        $this->assertInstanceOf(ConsistencyIssue::class, $issue);
        $this->assertEquals(1, $issue->id);
        $this->assertEquals(2, $issue->sagaId);
        $this->assertEquals('timeline', $issue->issueType);
        $this->assertEquals('high', $issue->severity);
        $this->assertEquals(10, $issue->entityId);
        $this->assertEquals(20, $issue->relatedEntityId);
        $this->assertEquals(['key' => 'value'], $issue->context);
        $this->assertEquals(0.85, $issue->aiConfidence);
    }

    /**
     * Test creating from database with object instead of array
     */
    public function test_creates_from_database_object(): void
    {
        $row = (object)[
            'id' => 1,
            'saga_id' => 2,
            'issue_type' => 'character',
            'severity' => 'medium',
            'entity_id' => null,
            'related_entity_id' => null,
            'description' => 'Test',
            'context' => '[]',
            'suggested_fix' => null,
            'status' => 'open',
            'detected_at' => '2024-01-01 12:00:00',
            'resolved_at' => null,
            'resolved_by' => null,
            'ai_confidence' => null
        ];

        $issue = ConsistencyIssue::fromDatabase($row);

        $this->assertInstanceOf(ConsistencyIssue::class, $issue);
        $this->assertEquals(1, $issue->id);
    }

    /**
     * Test resolving an issue creates new instance with resolved status
     */
    public function test_resolve_creates_resolved_copy(): void
    {
        $issue = new ConsistencyIssue(
            id: 1,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open'
        );

        $userId = 5;
        $resolved = $issue->resolve($userId);

        $this->assertInstanceOf(ConsistencyIssue::class, $resolved);
        $this->assertNotSame($issue, $resolved); // Different instance
        $this->assertEquals('resolved', $resolved->status);
        $this->assertEquals($userId, $resolved->resolvedBy);
        $this->assertNotNull($resolved->resolvedAt);

        // Original issue unchanged
        $this->assertEquals('open', $issue->status);
        $this->assertNull($issue->resolvedBy);
    }

    /**
     * Test dismissing an issue
     */
    public function test_dismiss_creates_dismissed_copy(): void
    {
        $issue = new ConsistencyIssue(
            id: 1,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'low',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open'
        );

        $userId = 3;
        $dismissed = $issue->dismiss($userId, false);

        $this->assertEquals('dismissed', $dismissed->status);
        $this->assertEquals($userId, $dismissed->resolvedBy);
        $this->assertNotNull($dismissed->resolvedAt);
    }

    /**
     * Test dismissing as false positive
     */
    public function test_dismiss_as_false_positive(): void
    {
        $issue = new ConsistencyIssue(
            id: 1,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'low',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open'
        );

        $userId = 3;
        $dismissed = $issue->dismiss($userId, true);

        $this->assertEquals('false_positive', $dismissed->status);
        $this->assertEquals($userId, $dismissed->resolvedBy);
    }

    /**
     * Test isOpen method
     */
    public function test_is_open(): void
    {
        $openIssue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open'
        );

        $resolvedIssue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'resolved'
        );

        $this->assertTrue($openIssue->isOpen());
        $this->assertFalse($resolvedIssue->isOpen());
    }

    /**
     * Test isResolved method
     */
    public function test_is_resolved(): void
    {
        $openIssue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'open'
        );

        $resolvedIssue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'high',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null,
            status: 'resolved'
        );

        $this->assertFalse($openIssue->isResolved());
        $this->assertTrue($resolvedIssue->isResolved());
    }

    /**
     * Test severity label translations
     */
    public function test_get_severity_label(): void
    {
        $severities = [
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            'info' => 'Info'
        ];

        foreach ($severities as $severity => $expectedLabel) {
            $issue = new ConsistencyIssue(
                id: null,
                sagaId: 1,
                issueType: 'timeline',
                severity: $severity,
                entityId: null,
                relatedEntityId: null,
                description: 'Test',
                context: [],
                suggestedFix: null
            );

            $this->assertStringContainsString($expectedLabel, $issue->getSeverityLabel());
        }
    }

    /**
     * Test issue type label translations
     */
    public function test_get_issue_type_label(): void
    {
        $types = [
            'timeline' => 'Timeline',
            'character' => 'Character',
            'location' => 'Location',
            'relationship' => 'Relationship',
            'logical' => 'Logical'
        ];

        foreach ($types as $type => $expectedPart) {
            $issue = new ConsistencyIssue(
                id: null,
                sagaId: 1,
                issueType: $type,
                severity: 'medium',
                entityId: null,
                relatedEntityId: null,
                description: 'Test',
                context: [],
                suggestedFix: null
            );

            $this->assertStringContainsString($expectedPart, $issue->getIssueTypeLabel());
        }
    }

    /**
     * Test issue with empty context
     */
    public function test_handles_empty_context(): void
    {
        $issue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'medium',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: [],
            suggestedFix: null
        );

        $this->assertEquals([], $issue->context);

        $array = $issue->toArray();
        $this->assertEquals('[]', $array['context']);
    }

    /**
     * Test issue with complex context data
     */
    public function test_handles_complex_context(): void
    {
        $complexContext = [
            'event_1' => [
                'date' => '2024-01-01',
                'age' => 25,
                'location' => 'Earth'
            ],
            'event_2' => [
                'date' => '2023-12-31',
                'age' => 30,
                'location' => 'Mars'
            ],
            'conflict' => 'Age decreased over time'
        ];

        $issue = new ConsistencyIssue(
            id: null,
            sagaId: 1,
            issueType: 'timeline',
            severity: 'critical',
            entityId: null,
            relatedEntityId: null,
            description: 'Test',
            context: $complexContext,
            suggestedFix: null
        );

        $this->assertEquals($complexContext, $issue->context);

        // Test roundtrip through database format
        $array = $issue->toArray();
        $reconstructed = ConsistencyIssue::fromDatabase($array);
        $this->assertEquals($complexContext, $reconstructed->context);
    }
}
