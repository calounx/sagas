<?php
/**
 * Semantic Search Scorer
 *
 * Relevance scoring algorithm for semantic search results.
 * Uses TF-IDF, keyword matching, entity importance, and
 * semantic similarity for comprehensive ranking.
 *
 * @package SagaManager
 * @since 1.3.0
 */

declare(strict_types=1);

namespace SagaManager\Search;

class SemanticScorer {

    /**
     * Scoring weights
     */
    private const WEIGHT_EXACT_MATCH = 10.0;
    private const WEIGHT_TITLE_MATCH = 5.0;
    private const WEIGHT_CONTENT_MATCH = 2.0;
    private const WEIGHT_IMPORTANCE = 1.5;
    private const WEIGHT_RECENCY = 0.5;
    private const WEIGHT_SEMANTIC = 3.0;

    /**
     * Synonym dictionary for semantic matching
     */
    private array $synonyms = [
        'battle' => ['fight', 'combat', 'war', 'conflict', 'engagement'],
        'character' => ['person', 'individual', 'protagonist', 'hero', 'figure'],
        'location' => ['place', 'area', 'region', 'site', 'locale'],
        'event' => ['occurrence', 'incident', 'happening', 'episode'],
        'faction' => ['group', 'organization', 'alliance', 'coalition', 'party'],
        'artifact' => ['item', 'object', 'relic', 'treasure'],
        'power' => ['ability', 'skill', 'capability', 'force'],
        'ancient' => ['old', 'historic', 'primordial', 'archaic'],
        'leader' => ['commander', 'chief', 'ruler', 'head', 'captain'],
        'dark' => ['evil', 'sinister', 'malevolent', 'wicked'],
        'light' => ['good', 'righteous', 'benevolent', 'pure'],
    ];

    /**
     * Stop words to exclude from scoring
     */
    private array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
        'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that',
        'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'
    ];

    /**
     * Calculate relevance score for a search result
     *
     * @param array  $entity Entity data
     * @param string $query  Search query
     * @param array  $parsed Parsed query data
     * @return float Relevance score
     */
    public function score(array $entity, string $query, array $parsed): float {
        $score = 0.0;

        // Extract query terms
        $queryTerms = $this->extractTerms($query);

        // 1. Exact match scoring
        $score += $this->scoreExactMatch($entity, $query);

        // 2. Title match scoring
        $score += $this->scoreTitleMatch($entity, $queryTerms);

        // 3. Content match scoring
        $score += $this->scoreContentMatch($entity, $queryTerms);

        // 4. Importance scoring
        $score += $this->scoreImportance($entity);

        // 5. Recency scoring
        $score += $this->scoreRecency($entity);

        // 6. Semantic similarity
        $score += $this->scoreSemanticSimilarity($entity, $queryTerms);

        // 7. Boolean operator compliance
        $score *= $this->applyBooleanOperators($entity, $parsed);

        // 8. Type boost (if filtering by type)
        $score *= $this->applyTypeBoost($entity, $parsed);

        return max(0.0, $score);
    }

    /**
     * Score exact phrase match
     */
    private function scoreExactMatch(array $entity, string $query): float {
        $score = 0.0;
        $queryLower = strtolower($query);

        // Check canonical name
        if (isset($entity['canonical_name'])) {
            $nameLower = strtolower($entity['canonical_name']);
            if ($nameLower === $queryLower) {
                $score += self::WEIGHT_EXACT_MATCH * 2.0; // Perfect match
            } elseif (strpos($nameLower, $queryLower) !== false) {
                $score += self::WEIGHT_EXACT_MATCH;
            }
        }

        // Check aliases
        if (isset($entity['aliases']) && is_array($entity['aliases'])) {
            foreach ($entity['aliases'] as $alias) {
                if (strtolower($alias) === $queryLower) {
                    $score += self::WEIGHT_EXACT_MATCH * 1.5;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * Score title/name matching
     */
    private function scoreTitleMatch(array $entity, array $queryTerms): float {
        if (!isset($entity['canonical_name'])) {
            return 0.0;
        }

        $titleTerms = $this->extractTerms($entity['canonical_name']);
        $matches = count(array_intersect($queryTerms, $titleTerms));

        if ($matches === 0) {
            return 0.0;
        }

        // Calculate TF-IDF style score
        $tf = $matches / max(1, count($titleTerms));
        $idf = log(1 + count($queryTerms) / max(1, $matches));

        return self::WEIGHT_TITLE_MATCH * $tf * $idf;
    }

    /**
     * Score content matching
     */
    private function scoreContentMatch(array $entity, array $queryTerms): float {
        $score = 0.0;

        // Check description/content
        $content = '';
        if (isset($entity['description'])) {
            $content .= ' ' . $entity['description'];
        }
        if (isset($entity['content'])) {
            $content .= ' ' . $entity['content'];
        }

        if (empty($content)) {
            return 0.0;
        }

        $contentTerms = $this->extractTerms($content);
        $matches = count(array_intersect($queryTerms, $contentTerms));

        if ($matches === 0) {
            return 0.0;
        }

        // TF-IDF scoring
        $tf = $matches / max(1, count($contentTerms));
        $idf = log(1 + count($queryTerms) / max(1, $matches));

        return self::WEIGHT_CONTENT_MATCH * $tf * $idf;
    }

    /**
     * Score based on entity importance
     */
    private function scoreImportance(array $entity): float {
        $importance = $entity['importance_score'] ?? 50;

        // Normalize to 0-1 range and apply weight
        return self::WEIGHT_IMPORTANCE * ($importance / 100.0);
    }

    /**
     * Score based on recency
     */
    private function scoreRecency(array $entity): float {
        if (!isset($entity['updated_at'])) {
            return 0.0;
        }

        $updatedTime = strtotime($entity['updated_at']);
        $daysSinceUpdate = (time() - $updatedTime) / 86400; // Days

        // Decay function: newer entities get higher scores
        // Score drops to ~0.37 after 30 days
        $recencyScore = exp(-$daysSinceUpdate / 30);

        return self::WEIGHT_RECENCY * $recencyScore;
    }

    /**
     * Score semantic similarity using synonym matching
     */
    private function scoreSemanticSimilarity(array $entity, array $queryTerms): float {
        $score = 0.0;

        // Get all entity text
        $entityText = implode(' ', [
            $entity['canonical_name'] ?? '',
            $entity['entity_type'] ?? '',
            $entity['description'] ?? '',
        ]);

        $entityTerms = $this->extractTerms($entityText);

        // Check for synonym matches
        foreach ($queryTerms as $queryTerm) {
            if (isset($this->synonyms[$queryTerm])) {
                $synonyms = $this->synonyms[$queryTerm];

                foreach ($synonyms as $synonym) {
                    if (in_array($synonym, $entityTerms, true)) {
                        $score += self::WEIGHT_SEMANTIC * 0.7; // Partial match
                    }
                }
            }

            // Also check reverse: if entity term has synonyms matching query
            foreach ($entityTerms as $entityTerm) {
                if (isset($this->synonyms[$entityTerm])) {
                    if (in_array($queryTerm, $this->synonyms[$entityTerm], true)) {
                        $score += self::WEIGHT_SEMANTIC * 0.7;
                    }
                }
            }
        }

        return $score;
    }

    /**
     * Apply boolean operator constraints
     */
    private function applyBooleanOperators(array $entity, array $parsed): float {
        $entityText = strtolower(implode(' ', [
            $entity['canonical_name'] ?? '',
            $entity['description'] ?? '',
        ]));

        // Check NOT operators (excluded terms)
        if (!empty($parsed['operators']['not'])) {
            foreach ($parsed['operators']['not'] as $term) {
                if (strpos($entityText, strtolower($term)) !== false) {
                    return 0.0; // Exclude this result
                }
            }
        }

        // Check excluded terms
        if (!empty($parsed['exclude'])) {
            foreach ($parsed['exclude'] as $term) {
                if (strpos($entityText, strtolower($term)) !== false) {
                    return 0.0; // Exclude this result
                }
            }
        }

        // Check AND operators (all must be present)
        if (!empty($parsed['operators']['and'])) {
            foreach ($parsed['operators']['and'] as $term) {
                if (strpos($entityText, strtolower($term)) === false) {
                    return 0.5; // Reduce score significantly
                }
            }
        }

        // Check OR operators (at least one should be present)
        if (!empty($parsed['operators']['or'])) {
            $hasMatch = false;
            foreach ($parsed['operators']['or'] as $term) {
                if (strpos($entityText, strtolower($term)) !== false) {
                    $hasMatch = true;
                    break;
                }
            }
            if (!$hasMatch) {
                return 0.7; // Reduce score moderately
            }
        }

        // Check exact phrases
        if (!empty($parsed['exact'])) {
            foreach ($parsed['exact'] as $phrase) {
                if (strpos($entityText, strtolower($phrase)) !== false) {
                    return 1.5; // Boost score for exact phrase match
                }
            }
        }

        return 1.0; // No modification
    }

    /**
     * Apply type-specific boost
     */
    private function applyTypeBoost(array $entity, array $parsed): float {
        // If searching for specific types, boost matching types
        if (!empty($parsed['types'])) {
            $entityType = $entity['entity_type'] ?? '';
            if (in_array($entityType, $parsed['types'], true)) {
                return 1.3; // 30% boost
            }
        }

        return 1.0;
    }

    /**
     * Extract meaningful terms from text
     */
    private function extractTerms(string $text): array {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove special characters
        $text = preg_replace('/[^\w\s]/', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        $words = array_filter($words, function($word) {
            return !in_array($word, $this->stopWords, true) && strlen($word) > 2;
        });

        // Stem words (simple approach - remove common suffixes)
        $words = array_map(function($word) {
            return $this->stem($word);
        }, $words);

        return array_unique(array_values($words));
    }

    /**
     * Simple word stemming
     */
    private function stem(string $word): string {
        $suffixes = ['ing', 'ed', 'es', 's', 'ly', 'er', 'est'];

        foreach ($suffixes as $suffix) {
            if (substr($word, -strlen($suffix)) === $suffix) {
                $stem = substr($word, 0, -strlen($suffix));
                if (strlen($stem) > 3) {
                    return $stem;
                }
            }
        }

        return $word;
    }

    /**
     * Sort results by relevance score
     *
     * @param array  $results Array of results to sort
     * @param string $query   Search query
     * @param array  $parsed  Parsed query
     * @return array Sorted results
     */
    public function sortByRelevance(array $results, string $query, array $parsed): array {
        // Calculate scores
        foreach ($results as &$result) {
            $result['relevance_score'] = $this->score($result, $query, $parsed);
        }

        // Sort by score (descending)
        usort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $results;
    }

    /**
     * Generate search suggestions based on query
     *
     * @param string $query Original query
     * @param array  $results Search results
     * @return array Suggested queries
     */
    public function generateSuggestions(string $query, array $results): array {
        $suggestions = [];

        // If no results, suggest corrections
        if (empty($results)) {
            $suggestions = $this->suggestSpellingCorrections($query);
        }

        // Suggest related terms from synonyms
        $queryTerms = $this->extractTerms($query);
        foreach ($queryTerms as $term) {
            if (isset($this->synonyms[$term])) {
                foreach ($this->synonyms[$term] as $synonym) {
                    $suggested = str_ireplace($term, $synonym, $query);
                    if ($suggested !== $query && !in_array($suggested, $suggestions, true)) {
                        $suggestions[] = $suggested;
                    }
                }
            }
        }

        // Limit suggestions
        return array_slice($suggestions, 0, 5);
    }

    /**
     * Suggest spelling corrections
     */
    private function suggestSpellingCorrections(string $query): array {
        global $wpdb;

        $suggestions = [];
        $terms = $this->extractTerms($query);

        foreach ($terms as $term) {
            // Find similar entity names using Levenshtein distance
            $table = $wpdb->prefix . 'saga_entities';

            $entities = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT canonical_name
                FROM {$table}
                WHERE canonical_name LIKE %s
                LIMIT 5",
                '%' . $wpdb->esc_like($term) . '%'
            ), ARRAY_A);

            foreach ($entities as $entity) {
                $name = $entity['canonical_name'];
                $nameLower = strtolower($name);

                // Calculate similarity
                $distance = levenshtein(strtolower($term), $nameLower);

                if ($distance > 0 && $distance <= 3) {
                    $suggested = str_ireplace($term, $name, $query);
                    if (!in_array($suggested, $suggestions, true)) {
                        $suggestions[] = $suggested;
                    }
                }
            }
        }

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Add custom synonym mapping
     */
    public function addSynonym(string $word, array $synonyms): void {
        $this->synonyms[strtolower($word)] = array_map('strtolower', $synonyms);
    }

    /**
     * Add custom stop word
     */
    public function addStopWord(string $word): void {
        $this->stopWords[] = strtolower($word);
    }
}
