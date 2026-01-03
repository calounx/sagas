<?php
/**
 * Suggestion Repository Interface
 *
 * Public contract for relationship suggestion data access layer.
 * Defines operations for suggestions, features, feedback, and learning weights.
 *
 * @package SagaManager
 * @subpackage AI\Interfaces
 * @since 1.5.0
 */

declare(strict_types=1);

namespace SagaManager\AI\Interfaces;

use SagaManager\AI\PredictiveRelationships\Entities\RelationshipSuggestion;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeature;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeedback;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Suggestion Repository Interface
 *
 * Dependency Inversion Principle: Prediction services depend on this interface,
 * not on concrete MariaDB implementation.
 */
interface SuggestionRepositoryInterface
{
    // =========================================================================
    // RELATIONSHIP SUGGESTIONS
    // =========================================================================

    /**
     * Create new suggestion
     *
     * @param RelationshipSuggestion $suggestion Suggestion object (id should be null)
     * @return int Suggestion ID
     * @throws \Exception If creation fails
     */
    public function createSuggestion(RelationshipSuggestion $suggestion): int;

    /**
     * Update existing suggestion
     *
     * @param RelationshipSuggestion $suggestion Suggestion with ID
     * @return bool Success
     * @throws \Exception If suggestion ID is null or update fails
     */
    public function updateSuggestion(RelationshipSuggestion $suggestion): bool;

    /**
     * Find suggestion by ID
     *
     * @param int $id Suggestion ID
     * @return RelationshipSuggestion|null
     */
    public function findById(int $id): ?RelationshipSuggestion;

    /**
     * Find pending suggestions for saga
     *
     * @param int $saga_id Saga ID
     * @param int $limit Maximum results
     * @return array Array of RelationshipSuggestion objects
     */
    public function findPendingSuggestions(int $saga_id, int $limit = 50): array;

    /**
     * Find suggestions by entities
     *
     * @param int $entity1_id First entity ID
     * @param int $entity2_id Second entity ID
     * @return array Array of RelationshipSuggestion objects
     */
    public function findByEntities(int $entity1_id, int $entity2_id): array;

    /**
     * Get actioned suggestions for saga
     *
     * @param int $saga_id Saga ID
     * @return array Array of RelationshipSuggestion objects
     */
    public function getActionedSuggestions(int $saga_id): array;

    /**
     * Count pending suggestions
     *
     * @param int $saga_id Saga ID
     * @return int Count
     */
    public function countPendingSuggestions(int $saga_id): int;

    /**
     * Get acceptance rate for saga
     *
     * @param int $saga_id Saga ID
     * @return float Acceptance rate 0-100
     */
    public function getAcceptanceRate(int $saga_id): float;

    // =========================================================================
    // SUGGESTION FEATURES
    // =========================================================================

    /**
     * Save features for suggestion
     *
     * @param int $suggestion_id Suggestion ID
     * @param array $features Array of SuggestionFeature objects
     * @return bool Success
     * @throws \Exception If save fails
     */
    public function saveFeatures(int $suggestion_id, array $features): bool;

    /**
     * Get features for suggestion
     *
     * @param int $suggestion_id Suggestion ID
     * @return array Array of SuggestionFeature objects
     */
    public function getFeatures(int $suggestion_id): array;

    // =========================================================================
    // SUGGESTION FEEDBACK
    // =========================================================================

    /**
     * Save feedback
     *
     * @param SuggestionFeedback $feedback Feedback object
     * @return int Feedback ID
     * @throws \Exception If save fails
     */
    public function saveFeedback(SuggestionFeedback $feedback): int;

    /**
     * Get feedback for saga
     *
     * @param int $saga_id Saga ID
     * @return array Array of SuggestionFeedback objects
     */
    public function getFeedbackForSaga(int $saga_id): array;

    // =========================================================================
    // LEARNING WEIGHTS
    // =========================================================================

    /**
     * Get weights for saga
     *
     * @param int $saga_id Saga ID
     * @return array Weights [feature_type => weight]
     */
    public function getWeightsForSaga(int $saga_id): array;

    /**
     * Update weight for feature type
     *
     * @param int $saga_id Saga ID
     * @param string $feature_type Feature type
     * @param float $weight New weight
     * @param int $samples_count Number of samples
     * @return bool Success
     * @throws \Exception If update fails
     */
    public function updateWeight(
        int $saga_id,
        string $feature_type,
        float $weight,
        int $samples_count = 0
    ): bool;

    /**
     * Update accuracy for saga
     *
     * @param int $saga_id Saga ID
     * @param float $accuracy Accuracy percentage
     * @return bool Success
     */
    public function updateAccuracy(int $saga_id, float $accuracy): bool;

    /**
     * Reset weights for saga
     *
     * @param int $saga_id Saga ID
     * @return bool Success
     */
    public function resetWeights(int $saga_id): bool;

    // =========================================================================
    // BATCH OPERATIONS
    // =========================================================================

    /**
     * Batch create suggestions with features
     *
     * @param array $suggestions Array of [suggestion, features] pairs
     * @return array Created suggestion IDs
     * @throws \Exception If batch creation fails
     */
    public function batchCreateSuggestions(array $suggestions): array;

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get suggestion statistics for saga
     *
     * @param int $saga_id Saga ID
     * @return array Statistics ['total', 'pending', 'accepted', 'rejected', ...]
     */
    public function getSuggestionStatistics(int $saga_id): array;
}
