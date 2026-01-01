<?php
/**
 * RelationshipSuggestion Value Object Unit Tests
 *
 * @package SagaManager\Tests\Unit\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\PredictiveRelationships;

use SagaManager\Tests\TestCase;
use SagaManager\AI\PredictiveRelationships\Entities\RelationshipSuggestion;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionStatus;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionMethod;
use SagaManager\AI\PredictiveRelationships\Entities\UserActionType;

class RelationshipSuggestionTest extends TestCase
{
    public function test_can_create_relationship_suggestion(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: 1,
            saga_id: 10,
            source_entity_id: 100,
            target_entity_id: 200,
            suggested_type: 'ally',
            confidence_score: 85.0,
            strength: 75,
            reasoning: 'They appear together frequently',
            evidence: ['co_occurrence' => 15],
            suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4',
            status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE,
            user_feedback_text: null,
            accepted_at: null,
            rejected_at: null,
            actioned_by: null,
            created_relationship_id: null,
            priority_score: 80.0,
            created_at: time(),
            updated_at: time()
        );

        $this->assertInstanceOf(RelationshipSuggestion::class, $suggestion);
        $this->assertEquals('ally', $suggestion->suggested_type);
        $this->assertEquals(85.0, $suggestion->confidence_score);
        $this->assertEquals(75, $suggestion->strength);
    }

    public function test_validates_confidence_score_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 150.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );
    }

    public function test_validates_strength_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 150,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );
    }

    public function test_validates_no_self_relationship(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot suggest relationship to self');

        new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 100, target_entity_id: 100,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );
    }

    public function test_get_confidence_level(): void
    {
        $veryHigh = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 92.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::HYBRID,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $high = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 80.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $medium = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 65.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $low = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 45.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $this->assertEquals('very_high', $veryHigh->getConfidenceLevel());
        $this->assertEquals('high', $high->getConfidenceLevel());
        $this->assertEquals('medium', $medium->getConfidenceLevel());
        $this->assertEquals('low', $low->getConfidenceLevel());
    }

    public function test_accept_changes_status(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: 1, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $accepted = $suggestion->accept(5, 100);

        $this->assertEquals(SuggestionStatus::ACCEPTED, $accepted->status);
        $this->assertEquals(UserActionType::ACCEPT, $accepted->user_action_type);
        $this->assertEquals(5, $accepted->actioned_by);
        $this->assertEquals(100, $accepted->created_relationship_id);
        $this->assertNotNull($accepted->accepted_at);

        // Original unchanged
        $this->assertEquals(SuggestionStatus::PENDING, $suggestion->status);
    }

    public function test_reject_changes_status(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: 1, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $rejected = $suggestion->reject(3, 'Not accurate');

        $this->assertEquals(SuggestionStatus::REJECTED, $rejected->status);
        $this->assertEquals(UserActionType::REJECT, $rejected->user_action_type);
        $this->assertEquals(3, $rejected->actioned_by);
        $this->assertEquals('Not accurate', $rejected->user_feedback_text);
        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_modify_changes_status(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: 1, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $modified = $suggestion->modify(5, 'enemy', 80, 200);

        $this->assertEquals(SuggestionStatus::MODIFIED, $modified->status);
        $this->assertEquals(UserActionType::MODIFY, $modified->user_action_type);
        $this->assertEquals('enemy', $modified->suggested_type);
        $this->assertEquals(80, $modified->strength);
        $this->assertEquals(200, $modified->created_relationship_id);
    }

    public function test_calculate_priority_score(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'family', confidence_score: 70.0, strength: 85,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::HYBRID,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $priority = $suggestion->calculatePriorityScore();

        // Should be boosted due to: high strength, hybrid method, and family type
        $this->assertGreaterThan(70.0, $priority);
        $this->assertLessThanOrEqual(100.0, $priority);
    }

    public function test_should_auto_accept(): void
    {
        $autoAcceptable = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 96.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::HYBRID,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $notAutoAcceptable = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 90.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $this->assertTrue($autoAcceptable->shouldAutoAccept());
        $this->assertFalse($notAutoAcceptable->shouldAutoAccept());
    }

    public function test_is_pending(): void
    {
        $pending = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::PENDING,
            user_action_type: UserActionType::NONE, user_feedback_text: null,
            accepted_at: null, rejected_at: null, actioned_by: null,
            created_relationship_id: null, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $accepted = new RelationshipSuggestion(
            id: null, saga_id: 1, source_entity_id: 1, target_entity_id: 2,
            suggested_type: 'ally', confidence_score: 75.0, strength: 50,
            reasoning: null, evidence: null, suggestion_method: SuggestionMethod::CONTENT,
            ai_model: 'gpt-4', status: SuggestionStatus::ACCEPTED,
            user_action_type: UserActionType::ACCEPT, user_feedback_text: null,
            accepted_at: time(), rejected_at: null, actioned_by: 5,
            created_relationship_id: 100, priority_score: 50.0,
            created_at: time(), updated_at: time()
        );

        $this->assertTrue($pending->isPending());
        $this->assertFalse($accepted->isPending());
    }

    public function test_converts_to_array(): void
    {
        $suggestion = new RelationshipSuggestion(
            id: 1, saga_id: 10, source_entity_id: 100, target_entity_id: 200,
            suggested_type: 'mentor', confidence_score: 88.0, strength: 90,
            reasoning: 'Teaching relationship', evidence: ['classes' => 5],
            suggestion_method: SuggestionMethod::SEMANTIC, ai_model: 'claude-3',
            status: SuggestionStatus::ACCEPTED, user_action_type: UserActionType::ACCEPT,
            user_feedback_text: 'Good suggestion', accepted_at: time(),
            rejected_at: null, actioned_by: 5, created_relationship_id: 150,
            priority_score: 92.0, created_at: time(), updated_at: time()
        );

        $array = $suggestion->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('saga_id', $array);
        $this->assertArrayHasKey('suggested_type', $array);
        $this->assertArrayHasKey('confidence_score', $array);
        $this->assertEquals('semantic', $array['suggestion_method']);
        $this->assertEquals('accepted', $array['status']);
    }

    public function test_creates_from_array(): void
    {
        $data = [
            'id' => 1,
            'saga_id' => 10,
            'source_entity_id' => 100,
            'target_entity_id' => 200,
            'suggested_type' => 'ally',
            'confidence_score' => 85.5,
            'strength' => 70,
            'reasoning' => 'Test reasoning',
            'evidence' => json_encode(['test' => 'data']),
            'suggestion_method' => 'hybrid',
            'ai_model' => 'gpt-4',
            'status' => 'pending',
            'user_action_type' => 'none',
            'user_feedback_text' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'actioned_by' => null,
            'created_relationship_id' => null,
            'priority_score' => 80.0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $suggestion = RelationshipSuggestion::fromArray($data);

        $this->assertInstanceOf(RelationshipSuggestion::class, $suggestion);
        $this->assertEquals('ally', $suggestion->suggested_type);
        $this->assertEquals(85.5, $suggestion->confidence_score);
        $this->assertEquals(SuggestionMethod::HYBRID, $suggestion->suggestion_method);
    }
}
