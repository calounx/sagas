<?php
/**
 * ConsistencyRepository Integration Tests
 *
 * Tests database operations, WordPress integration, and caching for ConsistencyRepository
 *
 * @package SagaManager\Tests\Integration\ConsistencyGuardian
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Integration\ConsistencyGuardian;

use SagaManager\Tests\TestCase;

class ConsistencyRepositoryTest extends TestCase
{
    /**
     * Test that consistency issues table exists with correct structure
     */
    public function test_consistency_issues_table_exists(): void
    {
        $this->assertTableExists('saga_consistency_issues');

        $this->assertTableHasColumns('saga_consistency_issues', [
            'id',
            'saga_id',
            'issue_type',
            'severity',
            'entity_id',
            'related_entity_id',
            'description',
            'context',
            'suggested_fix',
            'status',
            'detected_at',
            'resolved_at',
            'resolved_by',
            'ai_confidence'
        ]);
    }

    /**
     * Test creating consistency issue in database
     */
    public function test_can_create_consistency_issue(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        $issueData = [
            'saga_id' => $sagaId,
            'issue_type' => 'timeline',
            'severity' => 'high',
            'entity_id' => null,
            'related_entity_id' => null,
            'description' => 'Test timeline inconsistency',
            'context' => json_encode(['event1' => 'date1', 'event2' => 'date2']),
            'suggested_fix' => 'Adjust timeline',
            'status' => 'open',
            'detected_at' => current_time('mysql'),
            'resolved_at' => null,
            'resolved_by' => null,
            'ai_confidence' => 0.85
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'saga_consistency_issues',
            $issueData
        );

        $this->assertEquals(1, $result);
        $this->assertGreaterThan(0, $wpdb->insert_id);

        // Verify data was saved
        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues WHERE id = %d",
            $wpdb->insert_id
        ), ARRAY_A);

        $this->assertNotNull($saved);
        $this->assertEquals($sagaId, $saved['saga_id']);
        $this->assertEquals('timeline', $saved['issue_type']);
        $this->assertEquals('high', $saved['severity']);
        $this->assertEquals(0.85, (float)$saved['ai_confidence']);
    }

    /**
     * Test retrieving issues by saga ID
     */
    public function test_can_retrieve_issues_by_saga(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        // Create multiple issues
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'timeline']);
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'character']);
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'logical']);

        $issues = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues WHERE saga_id = %d",
            $sagaId
        ), ARRAY_A);

        $this->assertCount(3, $issues);
    }

    /**
     * Test filtering issues by status
     */
    public function test_can_filter_by_status(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        $this->createConsistencyIssue($sagaId, ['status' => 'open']);
        $this->createConsistencyIssue($sagaId, ['status' => 'open']);
        $this->createConsistencyIssue($sagaId, ['status' => 'resolved']);

        $openIssues = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d AND status = %s",
            $sagaId,
            'open'
        ), ARRAY_A);

        $this->assertCount(2, $openIssues);
    }

    /**
     * Test filtering by severity
     */
    public function test_can_filter_by_severity(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        $this->createConsistencyIssue($sagaId, ['severity' => 'critical']);
        $this->createConsistencyIssue($sagaId, ['severity' => 'high']);
        $this->createConsistencyIssue($sagaId, ['severity' => 'medium']);
        $this->createConsistencyIssue($sagaId, ['severity' => 'low']);

        $highPriorityIssues = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d AND severity IN ('critical', 'high')
             ORDER BY FIELD(severity, 'critical', 'high')",
            $sagaId
        ), ARRAY_A);

        $this->assertCount(2, $highPriorityIssues);
        $this->assertEquals('critical', $highPriorityIssues[0]['severity']);
    }

    /**
     * Test updating issue status
     */
    public function test_can_update_issue_status(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $userId = $this->createTestUser();
        $issueId = $this->createConsistencyIssue($sagaId, ['status' => 'open']);

        $result = $wpdb->update(
            $wpdb->prefix . 'saga_consistency_issues',
            [
                'status' => 'resolved',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $userId
            ],
            ['id' => $issueId]
        );

        $this->assertEquals(1, $result);

        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues WHERE id = %d",
            $issueId
        ), ARRAY_A);

        $this->assertEquals('resolved', $updated['status']);
        $this->assertNotNull($updated['resolved_at']);
        $this->assertEquals($userId, $updated['resolved_by']);
    }

    /**
     * Test deleting issues when saga is deleted (CASCADE)
     */
    public function test_cascade_delete_when_saga_deleted(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $this->createConsistencyIssue($sagaId);
        $this->createConsistencyIssue($sagaId);

        // Delete saga
        $wpdb->delete(
            $wpdb->prefix . 'saga_sagas',
            ['id' => $sagaId]
        );

        // Check issues are deleted
        $remainingIssues = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_consistency_issues WHERE saga_id = %d",
            $sagaId
        ));

        $this->assertEquals(0, $remainingIssues);
    }

    /**
     * Test counting issues by type
     */
    public function test_can_count_issues_by_type(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        $this->createConsistencyIssue($sagaId, ['issue_type' => 'timeline']);
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'timeline']);
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'character']);
        $this->createConsistencyIssue($sagaId, ['issue_type' => 'logical']);

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT issue_type, COUNT(*) as count
             FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d
             GROUP BY issue_type",
            $sagaId
        ), ARRAY_A);

        $this->assertCount(3, $stats);

        $timelineCount = array_filter($stats, fn($s) => $s['issue_type'] === 'timeline');
        $this->assertEquals(2, reset($timelineCount)['count']);
    }

    /**
     * Test WordPress object cache integration
     */
    public function test_wordpress_object_cache(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();
        $issueId = $this->createConsistencyIssue($sagaId);

        // Fetch issue
        $issue = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues WHERE id = %d",
            $issueId
        ), ARRAY_A);

        // Cache it
        $cacheKey = "saga_issue_{$issueId}";
        wp_cache_set($cacheKey, $issue, 'saga', 300);

        // Retrieve from cache
        $cached = wp_cache_get($cacheKey, 'saga');

        $this->assertNotFalse($cached);
        $this->assertEquals($issue['id'], $cached['id']);
        $this->assertEquals($issue['description'], $cached['description']);

        // Clear cache
        wp_cache_delete($cacheKey, 'saga');

        $notCached = wp_cache_get($cacheKey, 'saga');
        $this->assertFalse($notCached);
    }

    /**
     * Test calculating statistics
     */
    public function test_calculates_statistics(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        // Create issues with different statuses and severities
        $this->createConsistencyIssue($sagaId, ['status' => 'open', 'severity' => 'critical']);
        $this->createConsistencyIssue($sagaId, ['status' => 'open', 'severity' => 'high']);
        $this->createConsistencyIssue($sagaId, ['status' => 'resolved', 'severity' => 'medium']);

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count
             FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d",
            $sagaId
        ), ARRAY_A);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['open_count']);
        $this->assertEquals(1, $stats['resolved_count']);
        $this->assertEquals(1, $stats['critical_count']);
    }

    /**
     * Test ordering by severity and date
     */
    public function test_orders_by_priority(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        // Create issues in random order
        $this->createConsistencyIssue($sagaId, ['severity' => 'low']);
        sleep(1);
        $this->createConsistencyIssue($sagaId, ['severity' => 'critical']);
        sleep(1);
        $this->createConsistencyIssue($sagaId, ['severity' => 'high']);

        $issues = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d
             ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low', 'info'), detected_at DESC",
            $sagaId
        ), ARRAY_A);

        $this->assertCount(3, $issues);
        $this->assertEquals('critical', $issues[0]['severity']);
        $this->assertEquals('high', $issues[1]['severity']);
        $this->assertEquals('low', $issues[2]['severity']);
    }

    /**
     * Test searching in context JSON
     */
    public function test_searches_json_context(): void
    {
        global $wpdb;

        $sagaId = $this->createSaga();

        $this->createConsistencyIssue($sagaId, [
            'context' => json_encode(['event' => 'Battle of Yavin', 'year' => 0])
        ]);

        $this->createConsistencyIssue($sagaId, [
            'context' => json_encode(['event' => 'Battle of Hoth', 'year' => 3])
        ]);

        // Search for specific event in JSON
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saga_consistency_issues
             WHERE saga_id = %d AND JSON_EXTRACT(context, '$.event') LIKE %s",
            $sagaId,
            '%Yavin%'
        ));

        $this->assertEquals(1, $result);
    }
}
