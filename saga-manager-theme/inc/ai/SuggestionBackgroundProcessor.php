<?php
declare(strict_types=1);

namespace SagaManager\AI;

use SagaManager\AI\Services\RelationshipPredictionService;
use SagaManager\AI\Services\SuggestionRepository;

/**
 * Background processor for relationship suggestions generation
 *
 * Uses WordPress Cron to process suggestions in batches without blocking the UI.
 */
class SuggestionBackgroundProcessor {

    private RelationshipPredictionService $predictionService;
    private SuggestionRepository $repository;

    /** Batch size for processing entity pairs */
    private const BATCH_SIZE = 50;

    /** Cron hook name */
    private const CRON_HOOK = 'saga_generate_relationship_suggestions';

    /** Daily refresh hook */
    private const DAILY_REFRESH_HOOK = 'saga_daily_suggestions_refresh';

    public function __construct(
        RelationshipPredictionService $predictionService,
        SuggestionRepository $repository
    ) {
        $this->predictionService = $predictionService;
        $this->repository = $repository;
    }

    /**
     * Initialize WordPress Cron hooks
     */
    public function init(): void {
        // Register cron hooks
        add_action(self::CRON_HOOK, [$this, 'processSagaSuggestions'], 10, 1);
        add_action(self::DAILY_REFRESH_HOOK, [$this, 'refreshAllSagas']);

        // Schedule daily refresh if not already scheduled
        if (!wp_next_scheduled(self::DAILY_REFRESH_HOOK)) {
            wp_schedule_event(
                strtotime('tomorrow 3:00 AM'),
                'daily',
                self::DAILY_REFRESH_HOOK
            );
        }
    }

    /**
     * Schedule a background job to generate suggestions for a saga
     */
    public function scheduleGenerationJob(int $saga_id): bool {
        global $wpdb;

        // Check if job is already running
        $progress = get_transient("saga_suggestion_generation_{$saga_id}");
        if ($progress !== false && ($progress['status'] ?? '') === 'running') {
            error_log("[SAGA][PREDICTIVE][BG] Job already running for saga {$saga_id}");
            return false;
        }

        // Initialize progress transient
        set_transient("saga_suggestion_generation_{$saga_id}", [
            'status' => 'queued',
            'progress' => 0,
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'started_at' => time(),
        ], 3600); // 1 hour TTL

        // Schedule immediate execution
        wp_schedule_single_event(time(), self::CRON_HOOK, [$saga_id]);

        error_log("[SAGA][PREDICTIVE][BG] Scheduled generation job for saga {$saga_id}");

        return true;
    }

    /**
     * Process suggestions for a saga in batches
     */
    public function processSagaSuggestions(int $saga_id): void {
        global $wpdb;

        try {
            error_log("[SAGA][PREDICTIVE][BG] Starting suggestion generation for saga {$saga_id}");

            // Update progress
            $this->updateProgress($saga_id, 'running', 0, 0, 0, 0);

            // Get all entities in saga
            $table = $wpdb->prefix . 'saga_entities';
            $entities = $wpdb->get_results($wpdb->prepare(
                "SELECT id, entity_type, canonical_name
                FROM {$table}
                WHERE saga_id = %d
                ORDER BY importance_score DESC",
                $saga_id
            ), ARRAY_A);

            if (empty($entities)) {
                error_log("[SAGA][PREDICTIVE][BG] No entities found for saga {$saga_id}");
                $this->updateProgress($saga_id, 'completed', 100, 0, 0, 0);
                return;
            }

            // Generate entity pairs (only one direction to avoid duplicates)
            $pairs = [];
            $entity_count = count($entities);

            for ($i = 0; $i < $entity_count; $i++) {
                for ($j = $i + 1; $j < $entity_count; $j++) {
                    $pairs[] = [
                        'source' => $entities[$i],
                        'target' => $entities[$j],
                    ];
                }
            }

            $total_pairs = count($pairs);
            error_log("[SAGA][PREDICTIVE][BG] Generated {$total_pairs} entity pairs for saga {$saga_id}");

            if ($total_pairs === 0) {
                $this->updateProgress($saga_id, 'completed', 100, 0, 0, 0);
                return;
            }

            // Process in batches
            $processed = 0;
            $created = 0;
            $batches = array_chunk($pairs, self::BATCH_SIZE);

            foreach ($batches as $batch_index => $batch) {
                foreach ($batch as $pair) {
                    try {
                        // Generate suggestion
                        $suggestion = $this->predictionService->generateSuggestion(
                            $saga_id,
                            (int) $pair['source']['id'],
                            (int) $pair['target']['id']
                        );

                        if ($suggestion !== null) {
                            $created++;
                        }

                    } catch (\Exception $e) {
                        error_log("[SAGA][PREDICTIVE][BG] Error generating suggestion: " . $e->getMessage());
                    }

                    $processed++;

                    // Update progress every 10 pairs
                    if ($processed % 10 === 0) {
                        $progress_pct = (int) (($processed / $total_pairs) * 100);
                        $this->updateProgress($saga_id, 'running', $progress_pct, $total_pairs, $processed, $created);
                    }
                }

                // Small delay between batches to prevent overload
                usleep(100000); // 100ms
            }

            // Final progress update
            $this->updateProgress($saga_id, 'completed', 100, $total_pairs, $processed, $created);

            error_log("[SAGA][PREDICTIVE][BG] Completed generation for saga {$saga_id}: {$created} suggestions created");

        } catch (\Exception $e) {
            error_log("[SAGA][PREDICTIVE][BG][ERROR] Failed to process saga {$saga_id}: " . $e->getMessage());
            $this->updateProgress($saga_id, 'error', 0, 0, 0, 0, $e->getMessage());
        }
    }

    /**
     * Refresh suggestions for all active sagas (daily cron)
     */
    public function refreshAllSagas(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'saga_sagas';
        $saga_ids = $wpdb->get_col("SELECT id FROM {$table}");

        foreach ($saga_ids as $saga_id) {
            // Check rate limiting
            if (!$this->checkRateLimit((int) $saga_id)) {
                continue;
            }

            $this->scheduleGenerationJob((int) $saga_id);

            // Space out jobs by 5 minutes
            sleep(300);
        }
    }

    /**
     * Get current generation progress for a saga
     */
    public function getProgress(int $saga_id): ?array {
        $progress = get_transient("saga_suggestion_generation_{$saga_id}");

        if ($progress === false) {
            return null;
        }

        return $progress;
    }

    /**
     * Cancel a running generation job
     */
    public function cancelJob(int $saga_id): bool {
        delete_transient("saga_suggestion_generation_{$saga_id}");

        // Note: Can't actually cancel wp_cron jobs mid-execution
        // This just removes progress tracking

        return true;
    }

    /**
     * Check rate limiting for saga (max 5 generations per hour)
     */
    private function checkRateLimit(int $saga_id): bool {
        $rate_limit_key = "saga_generation_rate_{$saga_id}";
        $count = get_transient($rate_limit_key);

        if ($count === false) {
            set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
            return true;
        }

        if ($count >= 5) {
            error_log("[SAGA][PREDICTIVE][BG] Rate limit exceeded for saga {$saga_id}");
            return false;
        }

        set_transient($rate_limit_key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Update progress transient
     */
    private function updateProgress(
        int $saga_id,
        string $status,
        int $progress_pct,
        int $total,
        int $processed,
        int $created,
        ?string $error = null
    ): void {
        $data = [
            'status' => $status,
            'progress' => $progress_pct,
            'total' => $total,
            'processed' => $processed,
            'created' => $created,
            'updated_at' => time(),
        ];

        if ($error !== null) {
            $data['error'] = $error;
        }

        set_transient("saga_suggestion_generation_{$saga_id}", $data, 3600);
    }
}
