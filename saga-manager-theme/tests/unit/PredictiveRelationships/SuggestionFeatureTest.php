<?php
/**
 * SuggestionFeature Value Object Unit Tests
 *
 * @package SagaManager\Tests\Unit\PredictiveRelationships
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\PredictiveRelationships;

use SagaManager\Tests\TestCase;
use SagaManager\AI\PredictiveRelationships\Entities\SuggestionFeature;
use SagaManager\AI\PredictiveRelationships\Entities\FeatureType;

class SuggestionFeatureTest extends TestCase
{
    public function test_can_create_suggestion_feature(): void
    {
        $feature = new SuggestionFeature(
            id: 1,
            suggestion_id: 10,
            feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Character Co-occurrence',
            feature_value: 0.75,
            weight: 0.8,
            metadata: ['count' => 15],
            created_at: time()
        );

        $this->assertInstanceOf(SuggestionFeature::class, $feature);
        $this->assertEquals(FeatureType::CO_OCCURRENCE, $feature->feature_type);
        $this->assertEquals(0.75, $feature->feature_value);
        $this->assertEquals(0.8, $feature->weight);
    }

    public function test_validates_feature_value_range_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feature value must be between 0 and 1');

        new SuggestionFeature(
            id: null,
            suggestion_id: 1,
            feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test',
            feature_value: -0.5,
            weight: 0.5,
            metadata: null,
            created_at: time()
        );
    }

    public function test_validates_feature_value_range_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SuggestionFeature(
            id: null,
            suggestion_id: 1,
            feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test',
            feature_value: 1.5,
            weight: 0.5,
            metadata: null,
            created_at: time()
        );
    }

    public function test_validates_weight_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Weight must be between 0 and 1');

        new SuggestionFeature(
            id: null,
            suggestion_id: 1,
            feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test',
            feature_value: 0.5,
            weight: 1.5,
            metadata: null,
            created_at: time()
        );
    }

    public function test_get_weighted_value(): void
    {
        $feature = new SuggestionFeature(
            id: null,
            suggestion_id: 1,
            feature_type: FeatureType::SEMANTIC_SIMILARITY,
            feature_name: 'Semantic Match',
            feature_value: 0.8,
            weight: 0.7,
            metadata: null,
            created_at: time()
        );

        $weighted = $feature->getWeightedValue();

        // Use delta for floating point comparison to avoid precision issues
        $this->assertEqualsWithDelta(0.56, $weighted, 0.0001, '0.8 * 0.7 should equal 0.56');
    }

    public function test_get_strength_label(): void
    {
        $veryStrong = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.85, weight: 0.5,
            metadata: null, created_at: time()
        );

        $strong = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.65, weight: 0.5,
            metadata: null, created_at: time()
        );

        $moderate = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.50, weight: 0.5,
            metadata: null, created_at: time()
        );

        $weak = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.30, weight: 0.5,
            metadata: null, created_at: time()
        );

        $this->assertEquals('very_strong', $veryStrong->getStrengthLabel());
        $this->assertEquals('strong', $strong->getStrengthLabel());
        $this->assertEquals('moderate', $moderate->getStrengthLabel());
        $this->assertEquals('weak', $weak->getStrengthLabel());
    }

    public function test_with_weight_creates_new_instance(): void
    {
        $feature = new SuggestionFeature(
            id: 1,
            suggestion_id: 1,
            feature_type: FeatureType::TIMELINE_PROXIMITY,
            feature_name: 'Timeline Distance',
            feature_value: 0.6,
            weight: 0.5,
            metadata: null,
            created_at: time()
        );

        $updated = $feature->withWeight(0.9);

        $this->assertInstanceOf(SuggestionFeature::class, $updated);
        $this->assertNotSame($feature, $updated);
        $this->assertEquals(0.5, $feature->weight);
        $this->assertEquals(0.9, $updated->weight);
    }

    public function test_with_weight_validates_range(): void
    {
        $feature = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.5, weight: 0.5,
            metadata: null, created_at: time()
        );

        $this->expectException(\InvalidArgumentException::class);
        $feature->withWeight(1.5);
    }

    public function test_get_contribution(): void
    {
        $feature = new SuggestionFeature(
            id: null,
            suggestion_id: 1,
            feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test',
            feature_value: 0.8,
            weight: 0.5,
            metadata: null,
            created_at: time()
        );

        $totalWeightedSum = 2.0;
        $contribution = $feature->getContribution($totalWeightedSum);

        // (0.8 * 0.5) / 2.0 * 100 = 20%
        $this->assertEquals(20.0, $contribution);
    }

    public function test_get_contribution_with_zero_total(): void
    {
        $feature = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::CO_OCCURRENCE,
            feature_name: 'Test', feature_value: 0.5, weight: 0.5,
            metadata: null, created_at: time()
        );

        $contribution = $feature->getContribution(0.0);

        $this->assertEquals(0.0, $contribution);
    }

    public function test_is_high_value(): void
    {
        $highValue = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::SEMANTIC_SIMILARITY,
            feature_name: 'Test', feature_value: 0.75, weight: 0.7,
            metadata: null, created_at: time()
        );

        $notHighValue = new SuggestionFeature(
            id: null, suggestion_id: 1, feature_type: FeatureType::ATTRIBUTE_SIMILARITY,
            feature_name: 'Test', feature_value: 0.65, weight: 0.5,
            metadata: null, created_at: time()
        );

        $this->assertTrue($highValue->isHighValue());
        $this->assertFalse($notHighValue->isHighValue());
    }

    public function test_create_normalized(): void
    {
        $feature = SuggestionFeature::createNormalized(
            suggestion_id: 10,
            type: FeatureType::CO_OCCURRENCE,
            name: 'Co-occurrence Count',
            raw_value: 15.0,
            min: 0.0,
            max: 30.0,
            weight: null,
            metadata: ['raw' => 15]
        );

        $this->assertInstanceOf(SuggestionFeature::class, $feature);
        $this->assertEquals(0.5, $feature->feature_value); // (15-0)/(30-0) = 0.5
        $this->assertEquals(FeatureType::CO_OCCURRENCE->getDefaultWeight(), $feature->weight);
    }

    public function test_create_normalized_with_custom_weight(): void
    {
        $feature = SuggestionFeature::createNormalized(
            suggestion_id: 10,
            type: FeatureType::TIMELINE_PROXIMITY,
            name: 'Timeline Distance',
            raw_value: 100.0,
            min: 0.0,
            max: 200.0,
            weight: 0.9,
            metadata: null
        );

        $this->assertEquals(0.5, $feature->feature_value);
        $this->assertEquals(0.9, $feature->weight);
    }

    public function test_create_normalized_clamps_to_range(): void
    {
        $featureAbove = SuggestionFeature::createNormalized(
            suggestion_id: 1,
            type: FeatureType::CO_OCCURRENCE,
            name: 'Test',
            raw_value: 50.0,
            min: 0.0,
            max: 30.0,
            weight: null,
            metadata: null
        );

        $featureBelow = SuggestionFeature::createNormalized(
            suggestion_id: 1,
            type: FeatureType::CO_OCCURRENCE,
            name: 'Test',
            raw_value: -10.0,
            min: 0.0,
            max: 30.0,
            weight: null,
            metadata: null
        );

        $this->assertEquals(1.0, $featureAbove->feature_value);
        $this->assertEquals(0.0, $featureBelow->feature_value);
    }

    public function test_converts_to_array(): void
    {
        $feature = new SuggestionFeature(
            id: 1,
            suggestion_id: 10,
            feature_type: FeatureType::SHARED_LOCATION,
            feature_name: 'Common Locations',
            feature_value: 0.85,
            weight: 0.6,
            metadata: ['locations' => ['Tatooine', 'Naboo']],
            created_at: time()
        );

        $array = $feature->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('suggestion_id', $array);
        $this->assertArrayHasKey('feature_type', $array);
        $this->assertArrayHasKey('feature_value', $array);
        $this->assertArrayHasKey('weight', $array);
        $this->assertEquals('shared_location', $array['feature_type']);
        $this->assertEquals(0.85, $array['feature_value']);
    }

    public function test_creates_from_array(): void
    {
        $data = [
            'id' => 1,
            'suggestion_id' => 10,
            'feature_type' => 'semantic_similarity',
            'feature_name' => 'Embedding Similarity',
            'feature_value' => 0.92,
            'weight' => 0.8,
            'metadata' => json_encode(['model' => 'text-embedding-ada-002']),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $feature = SuggestionFeature::fromArray($data);

        $this->assertInstanceOf(SuggestionFeature::class, $feature);
        $this->assertEquals(FeatureType::SEMANTIC_SIMILARITY, $feature->feature_type);
        $this->assertEquals(0.92, $feature->feature_value);
        $this->assertEquals(0.8, $feature->weight);
    }

    public function test_feature_type_descriptions(): void
    {
        $this->assertStringContainsString('appear together', FeatureType::CO_OCCURRENCE->getDescription());
        $this->assertStringContainsString('Timeline', FeatureType::TIMELINE_PROXIMITY->getDescription());
        $this->assertStringContainsString('similarity', FeatureType::SEMANTIC_SIMILARITY->getDescription());
    }

    public function test_feature_type_default_weights(): void
    {
        $this->assertEquals(0.7, FeatureType::CO_OCCURRENCE->getDefaultWeight());
        $this->assertEquals(0.8, FeatureType::SEMANTIC_SIMILARITY->getDefaultWeight());
        $this->assertEquals(0.6, FeatureType::TIMELINE_PROXIMITY->getDefaultWeight());
        $this->assertGreaterThanOrEqual(0.0, FeatureType::NETWORK_CENTRALITY->getDefaultWeight());
        $this->assertLessThanOrEqual(1.0, FeatureType::SHARED_FACTION->getDefaultWeight());
    }
}
