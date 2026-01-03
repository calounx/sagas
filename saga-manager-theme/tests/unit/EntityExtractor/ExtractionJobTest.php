<?php
/**
 * ExtractionJob Value Object Unit Tests
 *
 * Tests the ExtractionJob immutable value object including:
 * - Construction and validation
 * - Progress calculation
 * - Acceptance rate calculation
 * - Cost estimation
 * - Status transitions
 * - Edge cases and error handling
 *
 * @package SagaManager\Tests\Unit\EntityExtractor
 * @since 1.4.0
 */

declare(strict_types=1);

namespace SagaManager\Tests\Unit\EntityExtractor;

use SagaManager\Tests\TestCase;
use SagaManager\AI\EntityExtractor\Entities\ExtractionJob;
use SagaManager\AI\EntityExtractor\Entities\JobStatus;
use SagaManager\AI\EntityExtractor\Entities\SourceType;

/**
 * ExtractionJob Unit Test Class
 */
class ExtractionJobTest extends TestCase
{
    /**
     * Test that ExtractionJob can be created with valid data
     */
    public function test_can_create_extraction_job(): void
    {
        $job = new ExtractionJob(
            id: 1,
            saga_id: 10,
            user_id: 5,
            source_text: 'Test extraction text',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 3,
            processed_chunks: 1,
            status: JobStatus::PROCESSING,
            total_entities_found: 10,
            entities_created: 5,
            entities_rejected: 2,
            duplicates_found: 3,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: 85.5,
            processing_time_ms: 2500,
            api_cost_usd: 0.15,
            error_message: null,
            metadata: ['test' => 'value'],
            created_at: time(),
            started_at: time(),
            completed_at: null
        );

        $this->assertInstanceOf(ExtractionJob::class, $job);
        $this->assertEquals(1, $job->id);
        $this->assertEquals(10, $job->saga_id);
        $this->assertEquals(5, $job->user_id);
        $this->assertEquals('Test extraction text', $job->source_text);
        $this->assertEquals(SourceType::MANUAL, $job->source_type);
        $this->assertEquals(5000, $job->chunk_size);
        $this->assertEquals(3, $job->total_chunks);
        $this->assertEquals(1, $job->processed_chunks);
        $this->assertEquals(JobStatus::PROCESSING, $job->status);
    }

    /**
     * Test chunk size validation - too small
     */
    public function test_validates_chunk_size_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be between 100 and 50000');

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 50, // Too small
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test chunk size validation - too large
     */
    public function test_validates_chunk_size_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 60000, // Too large
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test total chunks validation
     */
    public function test_validates_total_chunks_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total chunks must be at least 1');

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 0,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test processed chunks cannot exceed total chunks
     */
    public function test_validates_processed_chunks_not_exceeding_total(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Processed chunks cannot exceed total chunks');

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 3,
            processed_chunks: 5,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test accuracy score validation - below 0
     */
    public function test_validates_accuracy_score_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Accuracy score must be between 0 and 100');

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: -5.0,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test accuracy score validation - above 100
     */
    public function test_validates_accuracy_score_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: 105.0,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test API cost validation - negative
     */
    public function test_validates_api_cost_not_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API cost cannot be negative');

        new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: -0.50,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );
    }

    /**
     * Test creating from array
     */
    public function test_creates_from_array(): void
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'id' => 1,
            'saga_id' => 10,
            'user_id' => 5,
            'source_text' => 'Test text',
            'source_type' => 'manual',
            'chunk_size' => 5000,
            'total_chunks' => 2,
            'processed_chunks' => 1,
            'status' => 'processing',
            'total_entities_found' => 5,
            'entities_created' => 3,
            'entities_rejected' => 1,
            'duplicates_found' => 1,
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4',
            'accuracy_score' => 90.5,
            'processing_time_ms' => 3000,
            'api_cost_usd' => 0.25,
            'error_message' => null,
            'metadata' => json_encode(['key' => 'value']),
            'created_at' => $now,
            'started_at' => $now,
            'completed_at' => null
        ];

        $job = ExtractionJob::fromArray($data);

        $this->assertInstanceOf(ExtractionJob::class, $job);
        $this->assertEquals(1, $job->id);
        $this->assertEquals(10, $job->saga_id);
        $this->assertEquals(5, $job->user_id);
        $this->assertEquals('Test text', $job->source_text);
        $this->assertEquals(SourceType::MANUAL, $job->source_type);
        $this->assertEquals(JobStatus::PROCESSING, $job->status);
        $this->assertEquals(90.5, $job->accuracy_score);
        $this->assertEquals(0.25, $job->api_cost_usd);
    }

    /**
     * Test converting to array
     */
    public function test_converts_to_array(): void
    {
        $job = new ExtractionJob(
            id: 1,
            saga_id: 10,
            user_id: 5,
            source_text: 'Test',
            source_type: SourceType::FILE_UPLOAD,
            chunk_size: 5000,
            total_chunks: 2,
            processed_chunks: 1,
            status: JobStatus::PROCESSING,
            total_entities_found: 8,
            entities_created: 5,
            entities_rejected: 2,
            duplicates_found: 1,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: 88.0,
            processing_time_ms: 2000,
            api_cost_usd: 0.10,
            error_message: null,
            metadata: ['test' => 'data'],
            created_at: time(),
            started_at: time(),
            completed_at: null
        );

        $array = $job->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('saga_id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('source_text', $array);
        $this->assertArrayHasKey('source_type', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('total_entities_found', $array);
        $this->assertArrayHasKey('api_cost_usd', $array);

        $this->assertEquals('file_upload', $array['source_type']);
        $this->assertEquals('processing', $array['status']);
        $this->assertEquals(88.0, $array['accuracy_score']);
    }

    /**
     * Test progress percentage calculation
     */
    public function test_calculates_progress_percentage(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 4,
            processed_chunks: 1,
            status: JobStatus::PROCESSING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(25.0, $job->getProgressPercentage());
    }

    /**
     * Test progress percentage with all chunks processed
     */
    public function test_calculates_100_percent_progress(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 5,
            processed_chunks: 5,
            status: JobStatus::COMPLETED,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(100.0, $job->getProgressPercentage());
    }

    /**
     * Test acceptance rate calculation
     */
    public function test_calculates_acceptance_rate(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 10,
            entities_created: 7,
            entities_rejected: 3,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(70.0, $job->getAcceptanceRate());
    }

    /**
     * Test acceptance rate with no entities
     */
    public function test_calculates_zero_acceptance_rate_with_no_entities(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(0.0, $job->getAcceptanceRate());
    }

    /**
     * Test text length calculation
     */
    public function test_gets_text_length(): void
    {
        $text = 'This is a test extraction text with some content.';
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: $text,
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(mb_strlen($text), $job->getTextLength());
    }

    /**
     * Test cost per entity calculation
     */
    public function test_calculates_cost_per_entity(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 10,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: 1.00,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertEquals(0.1, $job->getCostPerEntity());
    }

    /**
     * Test cost per entity returns null when no cost
     */
    public function test_cost_per_entity_returns_null_when_no_cost(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 10,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertNull($job->getCostPerEntity());
    }

    /**
     * Test isComplete method
     */
    public function test_is_complete(): void
    {
        // Use array with explicit keys to avoid enum object as array key
        $test_cases = [
            ['status' => JobStatus::PENDING, 'expected' => false],
            ['status' => JobStatus::PROCESSING, 'expected' => false],
            ['status' => JobStatus::COMPLETED, 'expected' => true],
            ['status' => JobStatus::FAILED, 'expected' => true],
            ['status' => JobStatus::CANCELLED, 'expected' => true],
        ];

        foreach ($test_cases as $test_case) {
            $status = $test_case['status'];
            $expected = $test_case['expected'];

            $job = new ExtractionJob(
                id: null,
                saga_id: 1,
                user_id: 1,
                source_text: 'Test',
                source_type: SourceType::MANUAL,
                chunk_size: 5000,
                total_chunks: 1,
                processed_chunks: 0,
                status: $status,
                total_entities_found: 0,
                entities_created: 0,
                entities_rejected: 0,
                duplicates_found: 0,
                ai_provider: 'openai',
                ai_model: 'gpt-4',
                accuracy_score: null,
                processing_time_ms: null,
                api_cost_usd: null,
                error_message: null,
                metadata: null,
                created_at: time(),
                started_at: null,
                completed_at: null
            );

            $this->assertEquals($expected, $job->isComplete(), "Status {$status->value} should " . ($expected ? '' : 'not ') . 'be complete');
        }
    }

    /**
     * Test isSuccessful method
     */
    public function test_is_successful(): void
    {
        $completedJob = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $failedJob = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::FAILED,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertTrue($completedJob->isSuccessful());
        $this->assertFalse($failedJob->isSuccessful());
    }

    /**
     * Test withStatus creates new instance
     */
    public function test_with_status_creates_new_instance(): void
    {
        $job = new ExtractionJob(
            id: 1,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $updated = $job->withStatus(JobStatus::PROCESSING);

        $this->assertInstanceOf(ExtractionJob::class, $updated);
        $this->assertNotSame($job, $updated);
        $this->assertEquals(JobStatus::PENDING, $job->status);
        $this->assertEquals(JobStatus::PROCESSING, $updated->status);
    }

    /**
     * Test withProgress creates new instance
     */
    public function test_with_progress_creates_new_instance(): void
    {
        $job = new ExtractionJob(
            id: 1,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 5,
            processed_chunks: 2,
            status: JobStatus::PROCESSING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: time(),
            completed_at: null
        );

        $updated = $job->withProgress(4);

        $this->assertInstanceOf(ExtractionJob::class, $updated);
        $this->assertNotSame($job, $updated);
        $this->assertEquals(2, $job->processed_chunks);
        $this->assertEquals(4, $updated->processed_chunks);
    }

    /**
     * Test getProcessingDuration
     */
    public function test_gets_processing_duration(): void
    {
        $startTime = time() - 300; // 5 minutes ago
        $endTime = time();

        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 1,
            status: JobStatus::COMPLETED,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: $startTime,
            completed_at: $endTime
        );

        $duration = $job->getProcessingDuration();
        $this->assertGreaterThanOrEqual(300, $duration);
        $this->assertLessThanOrEqual(305, $duration); // Allow small variance
    }

    /**
     * Test getProcessingDuration returns null when not started
     */
    public function test_processing_duration_null_when_not_started(): void
    {
        $job = new ExtractionJob(
            id: null,
            saga_id: 1,
            user_id: 1,
            source_text: 'Test',
            source_type: SourceType::MANUAL,
            chunk_size: 5000,
            total_chunks: 1,
            processed_chunks: 0,
            status: JobStatus::PENDING,
            total_entities_found: 0,
            entities_created: 0,
            entities_rejected: 0,
            duplicates_found: 0,
            ai_provider: 'openai',
            ai_model: 'gpt-4',
            accuracy_score: null,
            processing_time_ms: null,
            api_cost_usd: null,
            error_message: null,
            metadata: null,
            created_at: time(),
            started_at: null,
            completed_at: null
        );

        $this->assertNull($job->getProcessingDuration());
    }

    /**
     * Test JobStatus enum methods
     */
    public function test_job_status_enum_methods(): void
    {
        $this->assertTrue(JobStatus::COMPLETED->isFinal());
        $this->assertTrue(JobStatus::FAILED->isFinal());
        $this->assertTrue(JobStatus::CANCELLED->isFinal());
        $this->assertFalse(JobStatus::PENDING->isFinal());
        $this->assertFalse(JobStatus::PROCESSING->isFinal());

        $this->assertTrue(JobStatus::PENDING->canProcess());
        $this->assertFalse(JobStatus::PROCESSING->canProcess());
        $this->assertFalse(JobStatus::COMPLETED->canProcess());
    }
}
