<?php
/**
 * Extraction Job Value Object
 *
 * Immutable value object representing an entity extraction job.
 * Tracks the entire extraction process from text input to batch entity creation.
 *
 * @package SagaManager
 * @subpackage AI\EntityExtractor\Entities
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\AI\EntityExtractor\Entities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extraction Job Status Enum
 */
enum JobStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Check if job is in final state
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Check if job can be processed
     *
     * @return bool
     */
    public function canProcess(): bool
    {
        return $this === self::PENDING;
    }
}

/**
 * Source Type Enum
 */
enum SourceType: string
{
    case MANUAL = 'manual';
    case FILE_UPLOAD = 'file_upload';
    case API = 'api';
}

/**
 * Extraction Job Value Object
 *
 * Immutable representation of an entity extraction job.
 */
final readonly class ExtractionJob
{
    /**
     * Constructor
     *
     * @param int|null $id Job ID (null for new jobs)
     * @param int $saga_id Target saga ID
     * @param int $user_id User who created job
     * @param string $source_text Text to extract entities from
     * @param SourceType $source_type Source of the text
     * @param int $chunk_size Text chunk size for processing
     * @param int $total_chunks Total number of chunks
     * @param int $processed_chunks Chunks processed so far
     * @param JobStatus $status Current job status
     * @param int $total_entities_found Total entities found
     * @param int $entities_created Entities actually created
     * @param int $entities_rejected Entities rejected by user
     * @param int $duplicates_found Duplicates detected
     * @param string $ai_provider AI provider used (openai, claude)
     * @param string $ai_model Model name (gpt-4, claude-3-opus)
     * @param float|null $accuracy_score Estimated accuracy 0-100
     * @param int|null $processing_time_ms Total processing time
     * @param float|null $api_cost_usd Estimated API cost
     * @param string|null $error_message Error message if failed
     * @param array|null $metadata Additional job metadata
     * @param int $created_at Unix timestamp
     * @param int|null $started_at Unix timestamp
     * @param int|null $completed_at Unix timestamp
     */
    public function __construct(
        public ?int $id,
        public int $saga_id,
        public int $user_id,
        public string $source_text,
        public SourceType $source_type,
        public int $chunk_size,
        public int $total_chunks,
        public int $processed_chunks,
        public JobStatus $status,
        public int $total_entities_found,
        public int $entities_created,
        public int $entities_rejected,
        public int $duplicates_found,
        public string $ai_provider,
        public string $ai_model,
        public ?float $accuracy_score,
        public ?int $processing_time_ms,
        public ?float $api_cost_usd,
        public ?string $error_message,
        public ?array $metadata,
        public int $created_at,
        public ?int $started_at,
        public ?int $completed_at
    ) {
        // Validation
        if ($this->chunk_size < 100 || $this->chunk_size > 50000) {
            throw new \InvalidArgumentException('Chunk size must be between 100 and 50000');
        }

        if ($this->total_chunks < 1) {
            throw new \InvalidArgumentException('Total chunks must be at least 1');
        }

        if ($this->processed_chunks > $this->total_chunks) {
            throw new \InvalidArgumentException('Processed chunks cannot exceed total chunks');
        }

        if ($this->accuracy_score !== null && ($this->accuracy_score < 0 || $this->accuracy_score > 100)) {
            throw new \InvalidArgumentException('Accuracy score must be between 0 and 100');
        }

        if ($this->api_cost_usd !== null && $this->api_cost_usd < 0) {
            throw new \InvalidArgumentException('API cost cannot be negative');
        }
    }

    /**
     * Create from database row
     *
     * @param array $row Database row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            saga_id: (int)$row['saga_id'],
            user_id: (int)$row['user_id'],
            source_text: (string)$row['source_text'],
            source_type: SourceType::from($row['source_type'] ?? 'manual'),
            chunk_size: (int)($row['chunk_size'] ?? 5000),
            total_chunks: (int)($row['total_chunks'] ?? 1),
            processed_chunks: (int)($row['processed_chunks'] ?? 0),
            status: JobStatus::from($row['status'] ?? 'pending'),
            total_entities_found: (int)($row['total_entities_found'] ?? 0),
            entities_created: (int)($row['entities_created'] ?? 0),
            entities_rejected: (int)($row['entities_rejected'] ?? 0),
            duplicates_found: (int)($row['duplicates_found'] ?? 0),
            ai_provider: (string)($row['ai_provider'] ?? 'openai'),
            ai_model: (string)($row['ai_model'] ?? 'gpt-4'),
            accuracy_score: isset($row['accuracy_score']) ? (float)$row['accuracy_score'] : null,
            processing_time_ms: isset($row['processing_time_ms']) ? (int)$row['processing_time_ms'] : null,
            api_cost_usd: isset($row['api_cost_usd']) ? (float)$row['api_cost_usd'] : null,
            error_message: $row['error_message'] ?? null,
            metadata: isset($row['metadata']) ? json_decode($row['metadata'], true) : null,
            created_at: strtotime($row['created_at']),
            started_at: isset($row['started_at']) ? strtotime($row['started_at']) : null,
            completed_at: isset($row['completed_at']) ? strtotime($row['completed_at']) : null
        );
    }

    /**
     * Convert to database array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'saga_id' => $this->saga_id,
            'user_id' => $this->user_id,
            'source_text' => $this->source_text,
            'source_type' => $this->source_type->value,
            'chunk_size' => $this->chunk_size,
            'total_chunks' => $this->total_chunks,
            'processed_chunks' => $this->processed_chunks,
            'status' => $this->status->value,
            'total_entities_found' => $this->total_entities_found,
            'entities_created' => $this->entities_created,
            'entities_rejected' => $this->entities_rejected,
            'duplicates_found' => $this->duplicates_found,
            'ai_provider' => $this->ai_provider,
            'ai_model' => $this->ai_model,
            'accuracy_score' => $this->accuracy_score,
            'processing_time_ms' => $this->processing_time_ms,
            'api_cost_usd' => $this->api_cost_usd,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ? json_encode($this->metadata) : null,
            'created_at' => date('Y-m-d H:i:s', $this->created_at),
            'started_at' => $this->started_at ? date('Y-m-d H:i:s', $this->started_at) : null,
            'completed_at' => $this->completed_at ? date('Y-m-d H:i:s', $this->completed_at) : null
        ];
    }

    /**
     * Calculate progress percentage
     *
     * @return float 0-100
     */
    public function getProgressPercentage(): float
    {
        if ($this->total_chunks === 0) {
            return 0.0;
        }

        return round(($this->processed_chunks / $this->total_chunks) * 100, 2);
    }

    /**
     * Calculate acceptance rate
     *
     * @return float 0-100 percentage of entities accepted
     */
    public function getAcceptanceRate(): float
    {
        $total = $this->entities_created + $this->entities_rejected;
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->entities_created / $total) * 100, 2);
    }

    /**
     * Get text length
     *
     * @return int
     */
    public function getTextLength(): int
    {
        return mb_strlen($this->source_text);
    }

    /**
     * Get estimated cost per entity
     *
     * @return float|null
     */
    public function getCostPerEntity(): ?float
    {
        if ($this->api_cost_usd === null || $this->total_entities_found === 0) {
            return null;
        }

        return round($this->api_cost_usd / $this->total_entities_found, 4);
    }

    /**
     * Check if job is complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Check if job is successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status === JobStatus::COMPLETED;
    }

    /**
     * Get processing duration in seconds
     *
     * @return int|null
     */
    public function getProcessingDuration(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->completed_at ?? time();
        return $end - $this->started_at;
    }

    /**
     * Create a new job with updated status
     *
     * @param JobStatus $status
     * @return self
     */
    public function withStatus(JobStatus $status): self
    {
        $data = $this->toArray();
        $data['status'] = $status->value;

        if ($status === JobStatus::PROCESSING && $this->started_at === null) {
            $data['started_at'] = date('Y-m-d H:i:s');
        }

        if ($status->isFinal() && $this->completed_at === null) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        return self::fromArray($data);
    }

    /**
     * Create a new job with updated progress
     *
     * @param int $processed_chunks
     * @return self
     */
    public function withProgress(int $processed_chunks): self
    {
        $data = $this->toArray();
        $data['processed_chunks'] = $processed_chunks;
        return self::fromArray($data);
    }
}
