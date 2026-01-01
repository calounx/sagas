<?php
/**
 * Unit Tests for SummaryRequest Value Object
 *
 * @package SagaManager
 * @subpackage Tests\Unit\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\SummaryGenerator;

use SagaManager\Tests\TestCase;
use SagaManager\AI\Entities\SummaryRequest;
use SagaManager\AI\Entities\SummaryType;
use SagaManager\AI\Entities\SummaryScope;
use SagaManager\AI\Entities\RequestStatus;
use SagaManager\AI\Entities\AIProvider;

class SummaryRequestTest extends TestCase
{
    /**
     * Test basic request creation
     */
    public function test_can_create_summary_request(): void
    {
        $request = new SummaryRequest(
            id: null,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: 123,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $this->assertNull($request->id);
        $this->assertEquals(1, $request->saga_id);
        $this->assertEquals(1, $request->user_id);
        $this->assertEquals(SummaryType::CHARACTER_ARC, $request->summary_type);
        $this->assertEquals(123, $request->entity_id);
        $this->assertEquals(SummaryScope::FULL, $request->scope);
        $this->assertEquals(RequestStatus::PENDING, $request->status);
        $this->assertEquals(5, $request->priority);
    }

    /**
     * Test priority validation
     */
    public function test_validates_priority_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Priority must be between 1 and 10');

        new SummaryRequest(
            id: null,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 15, // Invalid
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );
    }

    /**
     * Test retry count validation
     */
    public function test_validates_retry_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry count cannot exceed 5');

        new SummaryRequest(
            id: null,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            retry_count: 10 // Invalid
        );
    }

    /**
     * Test entity requirement validation
     */
    public function test_validates_entity_requirement_for_character_arc(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an entity_id');

        new SummaryRequest(
            id: null,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: null, // Invalid - character arc requires entity
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );
    }

    /**
     * Test status transition to generating
     */
    public function test_can_transition_to_generating_status(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            created_at: time() - 10
        );

        $updated = $request->withStatus(RequestStatus::GENERATING);

        $this->assertEquals(RequestStatus::GENERATING, $updated->status);
        $this->assertNotNull($updated->started_at);
        $this->assertEquals(RequestStatus::PENDING, $request->status); // Original unchanged
    }

    /**
     * Test status transition to completed
     */
    public function test_can_transition_to_completed_status(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::RELATIONSHIP,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::GENERATING,
            priority: 5,
            ai_provider: AIProvider::ANTHROPIC,
            ai_model: 'claude-3-opus',
            created_at: time() - 100,
            started_at: time() - 50
        );

        $updated = $request->withStatus(RequestStatus::COMPLETED);

        $this->assertEquals(RequestStatus::COMPLETED, $updated->status);
        $this->assertNotNull($updated->completed_at);
        $this->assertNotNull($updated->processing_time);
        $this->assertGreaterThan(0, $updated->processing_time);
    }

    /**
     * Test cannot change from final status
     */
    public function test_cannot_change_from_final_status(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot change status from completed');

        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::FACTION,
            entity_id: 100,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::COMPLETED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $request->withStatus(RequestStatus::PENDING); // Should fail
    }

    /**
     * Test marking request as failed
     */
    public function test_can_mark_as_failed_with_error(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::LOCATION,
            entity_id: 200,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::GENERATING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            created_at: time() - 100,
            started_at: time() - 50
        );

        $error_msg = 'API rate limit exceeded';
        $updated = $request->withError($error_msg);

        $this->assertEquals(RequestStatus::FAILED, $updated->status);
        $this->assertEquals($error_msg, $updated->error_message);
        $this->assertNotNull($updated->completed_at);
        $this->assertNotNull($updated->processing_time);
    }

    /**
     * Test retry increment
     */
    public function test_can_increment_retry_count(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: 123,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::FAILED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            retry_count: 2,
            error_message: 'Previous error'
        );

        $retried = $request->withRetry();

        $this->assertEquals(3, $retried->retry_count);
        $this->assertEquals(RequestStatus::PENDING, $retried->status);
        $this->assertNull($retried->error_message);
    }

    /**
     * Test maximum retry limit
     */
    public function test_cannot_retry_beyond_maximum(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum retry count (5) reached');

        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::FAILED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            retry_count: 5
        );

        $request->withRetry();
    }

    /**
     * Test token usage tracking
     */
    public function test_can_track_token_usage(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: 123,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::GENERATING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $updated = $request->withTokenUsage(2000, 500);

        $this->assertEquals(2500, $updated->actual_tokens);
        $this->assertNotNull($updated->actual_cost);
        $this->assertGreaterThan(0, $updated->actual_cost);
    }

    /**
     * Test estimated tokens calculation
     */
    public function test_can_estimate_tokens(): void
    {
        $request = new SummaryRequest(
            id: null,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::TIMELINE,
            entity_id: null,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::ANTHROPIC,
            ai_model: 'claude-3-opus'
        );

        $context_length = 8000; // characters
        $updated = $request->withEstimatedTokens($context_length);

        $this->assertNotNull($updated->estimated_tokens);
        $this->assertNotNull($updated->estimated_cost);
        $this->assertGreaterThan(0, $updated->estimated_tokens);
    }

    /**
     * Test retry eligibility
     */
    public function test_can_check_retry_eligibility(): void
    {
        $failed_request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::FACTION,
            entity_id: 100,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::FAILED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4',
            retry_count: 2
        );

        $this->assertTrue($failed_request->canRetry());

        $completed_request = new SummaryRequest(
            id: 2,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::LOCATION,
            entity_id: 200,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::COMPLETED,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $this->assertFalse($completed_request->canRetry());
    }

    /**
     * Test progress percentage calculation
     */
    public function test_calculates_progress_percentage(): void
    {
        $pending = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::CHARACTER_ARC,
            entity_id: 123,
            scope: SummaryScope::FULL,
            scope_params: [],
            status: RequestStatus::PENDING,
            priority: 5,
            ai_provider: AIProvider::OPENAI,
            ai_model: 'gpt-4'
        );

        $this->assertEquals(0.0, $pending->getProgressPercentage());

        $generating = $pending->withStatus(RequestStatus::GENERATING);
        $this->assertEquals(50.0, $generating->getProgressPercentage());

        $completed = $generating->withStatus(RequestStatus::COMPLETED);
        $this->assertEquals(100.0, $completed->getProgressPercentage());
    }

    /**
     * Test array conversion
     */
    public function test_converts_to_array(): void
    {
        $request = new SummaryRequest(
            id: 1,
            saga_id: 1,
            user_id: 1,
            summary_type: SummaryType::RELATIONSHIP,
            entity_id: null,
            scope: SummaryScope::DATE_RANGE,
            scope_params: ['start' => '2020-01-01', 'end' => '2020-12-31'],
            status: RequestStatus::COMPLETED,
            priority: 7,
            ai_provider: AIProvider::ANTHROPIC,
            ai_model: 'claude-3-opus',
            actual_tokens: 3000,
            actual_cost: 0.15
        );

        $array = $request->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('relationship', $array['summary_type']);
        $this->assertEquals('Relationship Overview', $array['summary_type_label']);
        $this->assertEquals('date_range', $array['scope']);
        $this->assertEquals('Date Range', $array['scope_label']);
        $this->assertEquals('completed', $array['status']);
        $this->assertEquals(100.0, $array['progress_percentage']);
        $this->assertFalse($array['can_retry']);
    }

    /**
     * Test database hydration
     */
    public function test_creates_from_database_row(): void
    {
        $row = (object)[
            'id' => 1,
            'saga_id' => 1,
            'user_id' => 1,
            'summary_type' => 'character_arc',
            'entity_id' => 123,
            'scope' => 'full',
            'scope_params' => '{}',
            'status' => 'pending',
            'priority' => 5,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'estimated_tokens' => null,
            'actual_tokens' => null,
            'estimated_cost' => null,
            'actual_cost' => null,
            'processing_time' => null,
            'error_message' => null,
            'retry_count' => 0,
            'created_at' => '2024-01-01 12:00:00',
            'started_at' => null,
            'completed_at' => null,
        ];

        $request = SummaryRequest::fromDatabase($row);

        $this->assertEquals(1, $request->id);
        $this->assertEquals(SummaryType::CHARACTER_ARC, $request->summary_type);
        $this->assertEquals(RequestStatus::PENDING, $request->status);
        $this->assertEquals(AIProvider::OPENAI, $request->ai_provider);
    }
}
