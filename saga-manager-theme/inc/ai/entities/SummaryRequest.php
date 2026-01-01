<?php
/**
 * Summary Request Value Object
 *
 * Immutable value object representing a summary generation request.
 *
 * @package SagaManager
 * @subpackage AI\SummaryGenerator
 */

declare(strict_types=1);

namespace SagaManager\AI\Entities;

/**
 * Summary request status enum
 */
enum RequestStatus: string
{
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Check if status is final (cannot be changed)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Check if request can be processed
     */
    public function canProcess(): bool
    {
        return $this === self::PENDING;
    }
}

/**
 * Summary type enum
 */
enum SummaryType: string
{
    case CHARACTER_ARC = 'character_arc';
    case TIMELINE = 'timeline';
    case RELATIONSHIP = 'relationship';
    case FACTION = 'faction';
    case LOCATION = 'location';

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match($this) {
            self::CHARACTER_ARC => 'Character Arc',
            self::TIMELINE => 'Timeline Summary',
            self::RELATIONSHIP => 'Relationship Overview',
            self::FACTION => 'Faction Analysis',
            self::LOCATION => 'Location Summary',
        };
    }

    /**
     * Check if this type requires an entity
     */
    public function requiresEntity(): bool
    {
        return in_array($this, [
            self::CHARACTER_ARC,
            self::FACTION,
            self::LOCATION
        ], true);
    }
}

/**
 * Summary scope enum
 */
enum SummaryScope: string
{
    case FULL = 'full';
    case CHAPTER = 'chapter';
    case DATE_RANGE = 'date_range';

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match($this) {
            self::FULL => 'Full Saga',
            self::CHAPTER => 'Chapter/Section',
            self::DATE_RANGE => 'Date Range',
        };
    }
}

/**
 * AI provider enum
 */
enum AIProvider: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';

    /**
     * Get default model for provider
     */
    public function getDefaultModel(): string
    {
        return match($this) {
            self::OPENAI => 'gpt-4',
            self::ANTHROPIC => 'claude-3-opus-20240229',
        };
    }

    /**
     * Get cost per 1K tokens (input)
     */
    public function getInputCostPer1K(): float
    {
        return match($this) {
            self::OPENAI => 0.03,      // GPT-4
            self::ANTHROPIC => 0.015,  // Claude 3 Opus
        };
    }

    /**
     * Get cost per 1K tokens (output)
     */
    public function getOutputCostPer1K(): float
    {
        return match($this) {
            self::OPENAI => 0.06,      // GPT-4
            self::ANTHROPIC => 0.075,  // Claude 3 Opus
        };
    }
}

/**
 * Summary Request value object
 */
final readonly class SummaryRequest
{
    /**
     * Constructor
     *
     * @param int|null $id Request ID
     * @param int $saga_id Saga ID
     * @param int $user_id User ID
     * @param SummaryType $summary_type Type of summary
     * @param int|null $entity_id Target entity ID
     * @param SummaryScope $scope Summary scope
     * @param array $scope_params Scope parameters
     * @param RequestStatus $status Current status
     * @param int $priority Priority (1-10)
     * @param AIProvider $ai_provider AI provider
     * @param string $ai_model AI model name
     * @param int|null $estimated_tokens Estimated token count
     * @param int|null $actual_tokens Actual token count
     * @param float|null $estimated_cost Estimated cost
     * @param float|null $actual_cost Actual cost
     * @param int|null $processing_time Processing time in seconds
     * @param string|null $error_message Error message
     * @param int $retry_count Retry count
     * @param int $created_at Creation timestamp
     * @param int|null $started_at Start timestamp
     * @param int|null $completed_at Completion timestamp
     */
    public function __construct(
        public ?int $id,
        public int $saga_id,
        public int $user_id,
        public SummaryType $summary_type,
        public ?int $entity_id,
        public SummaryScope $scope,
        public array $scope_params,
        public RequestStatus $status,
        public int $priority,
        public AIProvider $ai_provider,
        public string $ai_model,
        public ?int $estimated_tokens = null,
        public ?int $actual_tokens = null,
        public ?float $estimated_cost = null,
        public ?float $actual_cost = null,
        public ?int $processing_time = null,
        public ?string $error_message = null,
        public int $retry_count = 0,
        public int $created_at = 0,
        public ?int $started_at = null,
        public ?int $completed_at = null
    ) {
        if ($this->priority < 1 || $this->priority > 10) {
            throw new \InvalidArgumentException('Priority must be between 1 and 10');
        }

        if ($this->retry_count > 5) {
            throw new \InvalidArgumentException('Retry count cannot exceed 5');
        }

        if ($this->summary_type->requiresEntity() && $this->entity_id === null) {
            throw new \InvalidArgumentException(
                "Summary type {$this->summary_type->value} requires an entity_id"
            );
        }

        if ($this->created_at === 0) {
            $this->created_at = time();
        }
    }

    /**
     * Create new request with updated status
     *
     * @param RequestStatus $status New status
     * @return self
     */
    public function withStatus(RequestStatus $status): self
    {
        if ($this->status->isFinal()) {
            throw new \LogicException(
                "Cannot change status from {$this->status->value} (final state)"
            );
        }

        $data = get_object_vars($this);
        $data['status'] = $status;

        if ($status === RequestStatus::GENERATING && $this->started_at === null) {
            $data['started_at'] = time();
        }

        if ($status->isFinal() && $this->completed_at === null) {
            $data['completed_at'] = time();
            $data['processing_time'] = time() - ($this->started_at ?? $this->created_at);
        }

        return new self(...$data);
    }

    /**
     * Mark request as failed with error message
     *
     * @param string $error_message Error message
     * @return self
     */
    public function withError(string $error_message): self
    {
        $data = get_object_vars($this);
        $data['status'] = RequestStatus::FAILED;
        $data['error_message'] = $error_message;
        $data['completed_at'] = time();
        $data['processing_time'] = time() - ($this->started_at ?? $this->created_at);

        return new self(...$data);
    }

    /**
     * Increment retry count
     *
     * @return self
     */
    public function withRetry(): self
    {
        if ($this->retry_count >= 5) {
            throw new \LogicException('Maximum retry count (5) reached');
        }

        $data = get_object_vars($this);
        $data['retry_count'] = $this->retry_count + 1;
        $data['status'] = RequestStatus::PENDING;
        $data['error_message'] = null;

        return new self(...$data);
    }

    /**
     * Set token usage and cost
     *
     * @param int $input_tokens Input token count
     * @param int $output_tokens Output token count
     * @return self
     */
    public function withTokenUsage(int $input_tokens, int $output_tokens): self
    {
        $total_tokens = $input_tokens + $output_tokens;
        $cost = $this->calculateCost($input_tokens, $output_tokens);

        $data = get_object_vars($this);
        $data['actual_tokens'] = $total_tokens;
        $data['actual_cost'] = $cost;

        return new self(...$data);
    }

    /**
     * Calculate cost based on token usage
     *
     * @param int $input_tokens Input tokens
     * @param int $output_tokens Output tokens
     * @return float Cost in USD
     */
    private function calculateCost(int $input_tokens, int $output_tokens): float
    {
        $input_cost = ($input_tokens / 1000) * $this->ai_provider->getInputCostPer1K();
        $output_cost = ($output_tokens / 1000) * $this->ai_provider->getOutputCostPer1K();

        return round($input_cost + $output_cost, 4);
    }

    /**
     * Estimate tokens for request
     *
     * @param int $context_length Estimated context length
     * @return self
     */
    public function withEstimatedTokens(int $context_length): self
    {
        // Rough estimation: 1 token ≈ 4 characters
        $estimated_tokens = (int)ceil($context_length / 4);
        $estimated_cost = $this->calculateCost($estimated_tokens, 500); // Assume 500 output tokens

        $data = get_object_vars($this);
        $data['estimated_tokens'] = $estimated_tokens;
        $data['estimated_cost'] = $estimated_cost;

        return new self(...$data);
    }

    /**
     * Check if request can be retried
     *
     * @return bool
     */
    public function canRetry(): bool
    {
        return $this->status === RequestStatus::FAILED && $this->retry_count < 5;
    }

    /**
     * Get progress percentage
     *
     * @return float
     */
    public function getProgressPercentage(): float
    {
        return match($this->status) {
            RequestStatus::PENDING => 0.0,
            RequestStatus::GENERATING => 50.0,
            RequestStatus::COMPLETED => 100.0,
            RequestStatus::FAILED, RequestStatus::CANCELLED => 0.0,
        };
    }

    /**
     * Get estimated time remaining (in seconds)
     *
     * @return int|null
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if ($this->status !== RequestStatus::GENERATING || $this->started_at === null) {
            return null;
        }

        $elapsed = time() - $this->started_at;

        // Estimate based on token count
        if ($this->estimated_tokens !== null) {
            // Rough estimate: 1000 tokens per 2 seconds
            $estimated_total = (int)ceil(($this->estimated_tokens / 1000) * 2);
            return max(0, $estimated_total - $elapsed);
        }

        // Default: 30 seconds
        return max(0, 30 - $elapsed);
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'saga_id' => $this->saga_id,
            'user_id' => $this->user_id,
            'summary_type' => $this->summary_type->value,
            'summary_type_label' => $this->summary_type->getLabel(),
            'entity_id' => $this->entity_id,
            'scope' => $this->scope->value,
            'scope_label' => $this->scope->getLabel(),
            'scope_params' => $this->scope_params,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'ai_provider' => $this->ai_provider->value,
            'ai_model' => $this->ai_model,
            'estimated_tokens' => $this->estimated_tokens,
            'actual_tokens' => $this->actual_tokens,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'processing_time' => $this->processing_time,
            'error_message' => $this->error_message,
            'retry_count' => $this->retry_count,
            'can_retry' => $this->canRetry(),
            'progress_percentage' => $this->getProgressPercentage(),
            'estimated_time_remaining' => $this->getEstimatedTimeRemaining(),
            'created_at' => $this->created_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }

    /**
     * Create from database row
     *
     * @param object $row Database row
     * @return self
     */
    public static function fromDatabase(object $row): self
    {
        return new self(
            id: (int)$row->id,
            saga_id: (int)$row->saga_id,
            user_id: (int)$row->user_id,
            summary_type: SummaryType::from($row->summary_type),
            entity_id: $row->entity_id ? (int)$row->entity_id : null,
            scope: SummaryScope::from($row->scope),
            scope_params: json_decode($row->scope_params ?? '{}', true),
            status: RequestStatus::from($row->status),
            priority: (int)$row->priority,
            ai_provider: AIProvider::from($row->ai_provider),
            ai_model: $row->ai_model,
            estimated_tokens: $row->estimated_tokens ? (int)$row->estimated_tokens : null,
            actual_tokens: $row->actual_tokens ? (int)$row->actual_tokens : null,
            estimated_cost: $row->estimated_cost ? (float)$row->estimated_cost : null,
            actual_cost: $row->actual_cost ? (float)$row->actual_cost : null,
            processing_time: $row->processing_time ? (int)$row->processing_time : null,
            error_message: $row->error_message,
            retry_count: (int)$row->retry_count,
            created_at: strtotime($row->created_at),
            started_at: $row->started_at ? strtotime($row->started_at) : null,
            completed_at: $row->completed_at ? strtotime($row->completed_at) : null
        );
    }
}
