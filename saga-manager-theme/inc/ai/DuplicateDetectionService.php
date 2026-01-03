<?php
/**
 * Duplicate Detection Service
 *
 * Detects potential duplicate entities during extraction using multiple algorithms:
 * - Exact name matching
 * - Fuzzy string matching (Levenshtein distance)
 * - Alias/alternative name matching
 * - Semantic similarity (embeddings-based, if available)
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor;

use SagaManager\AI\EntityExtractor\Entities\ExtractedEntity;
use SagaManager\AI\EntityExtractor\Entities\DuplicateMatch;
use SagaManager\AI\EntityExtractor\Entities\MatchType;
use SagaManager\AI\EntityExtractor\Entities\DuplicateAction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Duplicate Detection Service
 *
 * Multi-algorithm approach to finding potential duplicates.
 */
class DuplicateDetectionService {

	private float $exact_match_threshold    = 100.0;
	private float $fuzzy_match_threshold    = 85.0;
	private float $semantic_match_threshold = 80.0;
	private int $max_results_per_entity     = 5;

	/**
	 * Find potential duplicates for an extracted entity
	 *
	 * @param ExtractedEntity $entity Extracted entity
	 * @param int             $saga_id Saga ID to search within
	 * @return array Array of DuplicateMatch objects
	 */
	public function findDuplicates( ExtractedEntity $entity, int $saga_id ): array {
		global $wpdb;

		$matches = array();

		// 1. Exact name matching
		$exact_matches = $this->findExactMatches( $entity, $saga_id );
		$matches       = array_merge( $matches, $exact_matches );

		// 2. Fuzzy name matching
		$fuzzy_matches = $this->findFuzzyMatches( $entity, $saga_id );
		$matches       = array_merge( $matches, $fuzzy_matches );

		// 3. Alias matching
		$alias_matches = $this->findAliasMatches( $entity, $saga_id );
		$matches       = array_merge( $matches, $alias_matches );

		// 4. Semantic matching (if embeddings available)
		// $semantic_matches = $this->findSemanticMatches($entity, $saga_id);
		// $matches = array_merge($matches, $semantic_matches);

		// Remove duplicates and sort by priority
		$matches = $this->deduplicateAndSort( $matches );

		// Limit results
		return array_slice( $matches, 0, $this->max_results_per_entity );
	}

	/**
	 * Find exact name matches
	 *
	 * @param ExtractedEntity $entity Entity to check
	 * @param int             $saga_id Saga ID
	 * @return array DuplicateMatch array
	 */
	private function findExactMatches( ExtractedEntity $entity, int $saga_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'saga_entities';

		// Check exact canonical name match
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, canonical_name, entity_type
             FROM {$table}
             WHERE saga_id = %d
             AND entity_type = %s
             AND LOWER(canonical_name) = LOWER(%s)
             LIMIT 10",
				$saga_id,
				$entity->entity_type->value,
				$entity->canonical_name
			),
			ARRAY_A
		);

		$matches = array();
		foreach ( $results as $row ) {
			$matches[] = new DuplicateMatch(
				id: null,
				extracted_entity_id: $entity->id ?? 0,
				existing_entity_id: (int) $row['id'],
				similarity_score: 100.0,
				match_type: MatchType::EXACT,
				matching_field: 'canonical_name',
				confidence: 95.0,
				user_action: DuplicateAction::PENDING,
				merged_attributes: null,
				created_at: time(),
				reviewed_at: null
			);
		}

		return $matches;
	}

	/**
	 * Find fuzzy string matches using Levenshtein distance
	 *
	 * @param ExtractedEntity $entity Entity to check
	 * @param int             $saga_id Saga ID
	 * @return array DuplicateMatch array
	 */
	private function findFuzzyMatches( ExtractedEntity $entity, int $saga_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'saga_entities';

		// Get all entities of same type in saga
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, canonical_name, entity_type
             FROM {$table}
             WHERE saga_id = %d
             AND entity_type = %s
             ORDER BY canonical_name
             LIMIT 500",
				$saga_id,
				$entity->entity_type->value
			),
			ARRAY_A
		);

		$matches     = array();
		$entity_name = strtolower( $entity->canonical_name );

		foreach ( $results as $row ) {
			$existing_name = strtolower( $row['canonical_name'] );

			// Calculate similarity
			$similarity = $this->calculateStringSimilarity( $entity_name, $existing_name );

			// Only include if above threshold and not exact match (handled above)
			if ( $similarity >= $this->fuzzy_match_threshold && $similarity < 100 ) {
				$matches[] = new DuplicateMatch(
					id: null,
					extracted_entity_id: $entity->id ?? 0,
					existing_entity_id: (int) $row['id'],
					similarity_score: $similarity,
					match_type: MatchType::FUZZY,
					matching_field: 'canonical_name',
					confidence: $this->calculateConfidence( $similarity, MatchType::FUZZY ),
					user_action: DuplicateAction::PENDING,
					merged_attributes: null,
					created_at: time(),
					reviewed_at: null
				);
			}
		}

		return $matches;
	}

	/**
	 * Find alias/alternative name matches
	 *
	 * @param ExtractedEntity $entity Entity to check
	 * @param int             $saga_id Saga ID
	 * @return array DuplicateMatch array
	 */
	private function findAliasMatches( ExtractedEntity $entity, int $saga_id ): array {
		global $wpdb;

		if ( empty( $entity->alternative_names ) ) {
			return array();
		}

		$matches        = array();
		$table          = $wpdb->prefix . 'saga_entities';
		$attr_table     = $wpdb->prefix . 'saga_attribute_values';
		$attr_def_table = $wpdb->prefix . 'saga_attribute_definitions';

		foreach ( $entity->alternative_names as $alias ) {
			// Check if alias matches any canonical name
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, canonical_name
                 FROM {$table}
                 WHERE saga_id = %d
                 AND entity_type = %s
                 AND LOWER(canonical_name) = LOWER(%s)
                 LIMIT 5",
					$saga_id,
					$entity->entity_type->value,
					$alias
				),
				ARRAY_A
			);

			foreach ( $results as $row ) {
				$matches[] = new DuplicateMatch(
					id: null,
					extracted_entity_id: $entity->id ?? 0,
					existing_entity_id: (int) $row['id'],
					similarity_score: 95.0,
					match_type: MatchType::ALIAS,
					matching_field: 'alternative_name',
					confidence: 85.0,
					user_action: DuplicateAction::PENDING,
					merged_attributes: null,
					created_at: time(),
					reviewed_at: null
				);
			}
		}

		return $matches;
	}

	/**
	 * Find semantic matches using embeddings
	 *
	 * Note: Requires embeddings to be generated for entities
	 *
	 * @param ExtractedEntity $entity Entity to check
	 * @param int             $saga_id Saga ID
	 * @return array DuplicateMatch array
	 */
	private function findSemanticMatches( ExtractedEntity $entity, int $saga_id ): array {
		// TODO: Implement when embedding system is in place
		// This would use vector similarity search on entity descriptions
		return array();
	}

	/**
	 * Calculate string similarity using multiple algorithms
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity score 0-100
	 */
	private function calculateStringSimilarity( string $str1, string $str2 ): float {
		// Exact match
		if ( $str1 === $str2 ) {
			return 100.0;
		}

		// Levenshtein distance
		$max_len = max( strlen( $str1 ), strlen( $str2 ) );
		if ( $max_len === 0 ) {
			return 100.0;
		}

		$distance               = levenshtein( $str1, $str2 );
		$levenshtein_similarity = ( 1 - ( $distance / $max_len ) ) * 100;

		// Similar text percentage
		$similar_text_percent = 0;
		similar_text( $str1, $str2, $similar_text_percent );

		// Jaro-Winkler similarity (approximation)
		$jaro = $this->jaroWinkler( $str1, $str2 );

		// Combine algorithms (weighted average)
		$similarity = ( $levenshtein_similarity * 0.4 ) +
					( $similar_text_percent * 0.3 ) +
					( $jaro * 0.3 );

		return round( $similarity, 2 );
	}

	/**
	 * Calculate Jaro-Winkler similarity
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity 0-100
	 */
	private function jaroWinkler( string $str1, string $str2 ): float {
		$len1 = strlen( $str1 );
		$len2 = strlen( $str2 );

		if ( $len1 === 0 && $len2 === 0 ) {
			return 100.0;
		}
		if ( $len1 === 0 || $len2 === 0 ) {
			return 0.0;
		}

		// Calculate match window
		$match_distance = (int) ( max( $len1, $len2 ) / 2 ) - 1;
		$match_distance = max( $match_distance, 0 );

		$matches        = 0;
		$transpositions = 0;
		$str1_matches   = array_fill( 0, $len1, false );
		$str2_matches   = array_fill( 0, $len2, false );

		// Find matches
		for ( $i = 0; $i < $len1; $i++ ) {
			$start = max( 0, $i - $match_distance );
			$end   = min( $i + $match_distance + 1, $len2 );

			for ( $j = $start; $j < $end; $j++ ) {
				if ( $str2_matches[ $j ] || $str1[ $i ] !== $str2[ $j ] ) {
					continue;
				}
				$str1_matches[ $i ] = true;
				$str2_matches[ $j ] = true;
				++$matches;
				break;
			}
		}

		if ( $matches === 0 ) {
			return 0.0;
		}

		// Count transpositions
		$k = 0;
		for ( $i = 0; $i < $len1; $i++ ) {
			if ( ! $str1_matches[ $i ] ) {
				continue;
			}
			while ( ! $str2_matches[ $k ] ) {
				++$k;
			}
			if ( $str1[ $i ] !== $str2[ $k ] ) {
				++$transpositions;
			}
			++$k;
		}

		// Calculate Jaro similarity
		$jaro = ( ( $matches / $len1 ) +
				( $matches / $len2 ) +
				( ( $matches - $transpositions / 2 ) / $matches ) ) / 3;

		// Apply Winkler modification
		$prefix_length = 0;
		for ( $i = 0; $i < min( min( $len1, $len2 ), 4 ); $i++ ) {
			if ( $str1[ $i ] === $str2[ $i ] ) {
				++$prefix_length;
			} else {
				break;
			}
		}

		$jaro_winkler = $jaro + ( $prefix_length * 0.1 * ( 1 - $jaro ) );

		return round( $jaro_winkler * 100, 2 );
	}

	/**
	 * Calculate confidence score based on similarity and match type
	 *
	 * @param float     $similarity Similarity score
	 * @param MatchType $match_type Type of match
	 * @return float Confidence 0-100
	 */
	private function calculateConfidence( float $similarity, MatchType $match_type ): float {
		$base_confidence = $similarity;

		// Boost confidence for certain match types
		$boost = match ( $match_type ) {
			MatchType::EXACT => 10,
			MatchType::ALIAS => 5,
			MatchType::FUZZY => 0,
			MatchType::SEMANTIC => -5 // Slightly lower confidence
		};

		$confidence = min( $base_confidence + $boost, 100 );

		return round( $confidence, 2 );
	}

	/**
	 * Remove duplicate matches and sort by priority
	 *
	 * @param array $matches DuplicateMatch array
	 * @return array Deduplicated and sorted array
	 */
	private function deduplicateAndSort( array $matches ): array {
		// Remove duplicates (same existing entity)
		$seen   = array();
		$unique = array();

		foreach ( $matches as $match ) {
			if ( ! ( $match instanceof DuplicateMatch ) ) {
				continue;
			}

			$key = $match->existing_entity_id;

			// Keep highest similarity match for each entity
			if ( ! isset( $seen[ $key ] ) || $match->similarity_score > $seen[ $key ]->similarity_score ) {
				$seen[ $key ] = $match;
			}
		}

		$unique = array_values( $seen );

		// Sort by priority score (descending)
		usort(
			$unique,
			function ( $a, $b ) {
				return $b->getPriorityScore() <=> $a->getPriorityScore();
			}
		);

		return $unique;
	}

	/**
	 * Batch find duplicates for multiple entities
	 *
	 * @param array $entities Array of ExtractedEntity objects
	 * @param int   $saga_id Saga ID
	 * @return array Associative array [entity_id => [DuplicateMatch, ...]]
	 */
	public function batchFindDuplicates( array $entities, int $saga_id ): array {
		$all_matches = array();

		foreach ( $entities as $entity ) {
			if ( ! ( $entity instanceof ExtractedEntity ) ) {
				continue;
			}

			$matches = $this->findDuplicates( $entity, $saga_id );

			if ( ! empty( $matches ) ) {
				$entity_key                 = $entity->id ?? spl_object_hash( $entity );
				$all_matches[ $entity_key ] = $matches;
			}
		}

		return $all_matches;
	}

	/**
	 * Calculate duplicate detection statistics
	 *
	 * @param array $matches Array of DuplicateMatch arrays
	 * @return array Statistics
	 */
	public function calculateStatistics( array $matches ): array {
		$total_entities  = count( $matches );
		$total_matches   = 0;
		$exact_matches   = 0;
		$fuzzy_matches   = 0;
		$alias_matches   = 0;
		$high_confidence = 0;

		foreach ( $matches as $entity_matches ) {
			foreach ( $entity_matches as $match ) {
				if ( ! ( $match instanceof DuplicateMatch ) ) {
					continue;
				}

				++$total_matches;

				match ( $match->match_type ) {
					MatchType::EXACT => $exact_matches++,
					MatchType::FUZZY => $fuzzy_matches++,
					MatchType::ALIAS => $alias_matches++,
					default => null
				};

				if ( $match->confidence >= 90 ) {
					++$high_confidence;
				}
			}
		}

		return array(
			'total_entities_with_duplicates' => $total_entities,
			'total_matches_found'            => $total_matches,
			'exact_matches'                  => $exact_matches,
			'fuzzy_matches'                  => $fuzzy_matches,
			'alias_matches'                  => $alias_matches,
			'high_confidence_matches'        => $high_confidence,
			'duplicate_rate_percent'         => $total_entities > 0
				? round( ( $total_entities / $total_entities ) * 100, 2 )
				: 0,
		);
	}

	/**
	 * Set matching thresholds
	 *
	 * @param float $fuzzy Fuzzy match threshold (0-100)
	 * @param float $semantic Semantic match threshold (0-100)
	 * @return self
	 */
	public function setThresholds( float $fuzzy = 85.0, float $semantic = 80.0 ): self {
		$this->fuzzy_match_threshold    = $fuzzy;
		$this->semantic_match_threshold = $semantic;
		return $this;
	}

	/**
	 * Set max results per entity
	 *
	 * @param int $max Maximum duplicate matches to return per entity
	 * @return self
	 */
	public function setMaxResults( int $max ): self {
		$this->max_results_per_entity = $max;
		return $this;
	}
}
