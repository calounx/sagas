<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Performance;

/**
 * Batch Executor - Optimized Bulk Database Operations
 *
 * Features:
 * - Bulk INSERT with multi-row VALUES
 * - Bulk UPDATE with CASE statements
 * - Bulk DELETE with IN clauses
 * - Transaction batching
 * - Memory-efficient streaming for large datasets
 * - Automatic chunk sizing based on data size
 */
final class BatchExecutor
{
    private const DEFAULT_BATCH_SIZE = 500;
    private const MAX_BATCH_SIZE = 1000;
    private const MAX_QUERY_SIZE_BYTES = 1048576; // 1MB max query size

    private \wpdb $wpdb;
    private string $tablePrefix;

    /** @var array<string, int> Operation statistics */
    private array $stats = [
        'inserts' => 0,
        'updates' => 0,
        'deletes' => 0,
        'batches_executed' => 0,
    ];

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'saga_';
    }

    /**
     * Bulk insert with multi-row VALUES syntax
     *
     * @param string $table Table name (without prefix)
     * @param array<string> $columns Column names
     * @param array<array<mixed>> $rows Row data arrays
     * @param int $batchSize Rows per batch
     * @return int Total rows inserted
     */
    public function bulkInsert(
        string $table,
        array $columns,
        array $rows,
        int $batchSize = self::DEFAULT_BATCH_SIZE
    ): int {
        if (empty($rows)) {
            return 0;
        }

        $fullTable = $this->tablePrefix . $table;
        $batchSize = min($batchSize, self::MAX_BATCH_SIZE);

        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = $this->buildPlaceholders($columns);

        $totalInserted = 0;
        $batches = array_chunk($rows, $batchSize);

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($batches as $batch) {
                $values = [];
                $params = [];

                foreach ($batch as $row) {
                    $values[] = $placeholders;
                    foreach ($row as $value) {
                        $params[] = $value;
                    }
                }

                $sql = sprintf(
                    "INSERT INTO `%s` (%s) VALUES %s",
                    $fullTable,
                    $columnList,
                    implode(', ', $values)
                );

                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->query($prepared);

                if ($result === false) {
                    throw new \RuntimeException(
                        "Bulk insert failed: {$this->wpdb->last_error}"
                    );
                }

                $totalInserted += count($batch);
                $this->stats['batches_executed']++;
            }

            $this->wpdb->query('COMMIT');
            $this->stats['inserts'] += $totalInserted;

            return $totalInserted;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Insert failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk insert with ON DUPLICATE KEY UPDATE
     *
     * @param string $table Table name (without prefix)
     * @param array<string> $columns Column names
     * @param array<array<mixed>> $rows Row data
     * @param array<string> $updateColumns Columns to update on duplicate
     * @return int Rows affected
     */
    public function bulkUpsert(
        string $table,
        array $columns,
        array $rows,
        array $updateColumns
    ): int {
        if (empty($rows)) {
            return 0;
        }

        $fullTable = $this->tablePrefix . $table;
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = $this->buildPlaceholders($columns);

        // Build UPDATE clause
        $updates = array_map(
            fn($c) => "`{$c}` = VALUES(`{$c}`)",
            $updateColumns
        );
        $updateClause = implode(', ', $updates);

        $totalAffected = 0;
        $batches = array_chunk($rows, self::DEFAULT_BATCH_SIZE);

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($batches as $batch) {
                $values = [];
                $params = [];

                foreach ($batch as $row) {
                    $values[] = $placeholders;
                    foreach ($row as $value) {
                        $params[] = $value;
                    }
                }

                $sql = sprintf(
                    "INSERT INTO `%s` (%s) VALUES %s ON DUPLICATE KEY UPDATE %s",
                    $fullTable,
                    $columnList,
                    implode(', ', $values),
                    $updateClause
                );

                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->query($prepared);

                if ($result === false) {
                    throw new \RuntimeException(
                        "Bulk upsert failed: {$this->wpdb->last_error}"
                    );
                }

                $totalAffected += $this->wpdb->rows_affected;
                $this->stats['batches_executed']++;
            }

            $this->wpdb->query('COMMIT');
            return $totalAffected;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Upsert failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk update with CASE statement optimization
     *
     * @param string $table Table name (without prefix)
     * @param string $keyColumn Primary key or unique column
     * @param string $updateColumn Column to update
     * @param array<int|string, mixed> $updates Map of key => new value
     * @return int Rows updated
     */
    public function bulkUpdate(
        string $table,
        string $keyColumn,
        string $updateColumn,
        array $updates
    ): int {
        if (empty($updates)) {
            return 0;
        }

        $fullTable = $this->tablePrefix . $table;
        $totalUpdated = 0;
        $batches = array_chunk($updates, self::DEFAULT_BATCH_SIZE, true);

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($batches as $batch) {
                $caseStatements = [];
                $params = [];
                $ids = [];

                foreach ($batch as $key => $value) {
                    $caseStatements[] = "WHEN %s THEN %s";
                    $params[] = $key;
                    $params[] = $value;
                    $ids[] = $key;
                }

                $idPlaceholders = implode(', ', array_fill(0, count($ids), '%s'));
                $params = array_merge($params, $ids);

                $sql = sprintf(
                    "UPDATE `%s` SET `%s` = CASE `%s` %s END WHERE `%s` IN (%s)",
                    $fullTable,
                    $updateColumn,
                    $keyColumn,
                    implode(' ', $caseStatements),
                    $keyColumn,
                    $idPlaceholders
                );

                $prepared = $this->wpdb->prepare($sql, $params);
                $result = $this->wpdb->query($prepared);

                if ($result === false) {
                    throw new \RuntimeException(
                        "Bulk update failed: {$this->wpdb->last_error}"
                    );
                }

                $totalUpdated += $this->wpdb->rows_affected;
                $this->stats['batches_executed']++;
            }

            $this->wpdb->query('COMMIT');
            $this->stats['updates'] += $totalUpdated;

            return $totalUpdated;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk update multiple columns
     *
     * @param string $table Table name (without prefix)
     * @param string $keyColumn Primary key column
     * @param array<int|string, array<string, mixed>> $updates Map of key => [column => value]
     * @return int Rows updated
     */
    public function bulkUpdateMultiple(
        string $table,
        string $keyColumn,
        array $updates
    ): int {
        if (empty($updates)) {
            return 0;
        }

        $fullTable = $this->tablePrefix . $table;
        $totalUpdated = 0;

        // Group by columns being updated for efficiency
        $columnGroups = [];
        foreach ($updates as $key => $columnValues) {
            $columnsKey = implode('|', array_keys($columnValues));
            $columnGroups[$columnsKey][$key] = $columnValues;
        }

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($columnGroups as $group) {
                $batches = array_chunk($group, self::DEFAULT_BATCH_SIZE, true);

                foreach ($batches as $batch) {
                    $firstRow = reset($batch);
                    $columns = array_keys($firstRow);

                    $setClauses = [];
                    $params = [];
                    $ids = [];

                    foreach ($columns as $column) {
                        $caseStatements = [];
                        foreach ($batch as $key => $values) {
                            $caseStatements[] = "WHEN %s THEN %s";
                            $params[] = $key;
                            $params[] = $values[$column];
                            if (!in_array($key, $ids, true)) {
                                $ids[] = $key;
                            }
                        }
                        $setClauses[] = sprintf(
                            "`%s` = CASE `%s` %s END",
                            $column,
                            $keyColumn,
                            implode(' ', $caseStatements)
                        );
                    }

                    $idPlaceholders = implode(', ', array_fill(0, count($ids), '%s'));
                    $params = array_merge($params, $ids);

                    $sql = sprintf(
                        "UPDATE `%s` SET %s WHERE `%s` IN (%s)",
                        $fullTable,
                        implode(', ', $setClauses),
                        $keyColumn,
                        $idPlaceholders
                    );

                    $prepared = $this->wpdb->prepare($sql, $params);
                    $result = $this->wpdb->query($prepared);

                    if ($result === false) {
                        throw new \RuntimeException(
                            "Bulk multi-update failed: {$this->wpdb->last_error}"
                        );
                    }

                    $totalUpdated += $this->wpdb->rows_affected;
                    $this->stats['batches_executed']++;
                }
            }

            $this->wpdb->query('COMMIT');
            $this->stats['updates'] += $totalUpdated;

            return $totalUpdated;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Multi-update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk delete with IN clause
     *
     * @param string $table Table name (without prefix)
     * @param string $column Column to match
     * @param array<int|string> $values Values to delete
     * @return int Rows deleted
     */
    public function bulkDelete(
        string $table,
        string $column,
        array $values
    ): int {
        if (empty($values)) {
            return 0;
        }

        $fullTable = $this->tablePrefix . $table;
        $totalDeleted = 0;
        $batches = array_chunk($values, self::DEFAULT_BATCH_SIZE);

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($batches as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '%s'));

                $sql = sprintf(
                    "DELETE FROM `%s` WHERE `%s` IN (%s)",
                    $fullTable,
                    $column,
                    $placeholders
                );

                $prepared = $this->wpdb->prepare($sql, $batch);
                $result = $this->wpdb->query($prepared);

                if ($result === false) {
                    throw new \RuntimeException(
                        "Bulk delete failed: {$this->wpdb->last_error}"
                    );
                }

                $totalDeleted += $this->wpdb->rows_affected;
                $this->stats['batches_executed']++;
            }

            $this->wpdb->query('COMMIT');
            $this->stats['deletes'] += $totalDeleted;

            return $totalDeleted;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Delete failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stream large result sets using generator
     *
     * Memory-efficient iteration over large datasets.
     *
     * @param string $table Table name (without prefix)
     * @param array<string, mixed> $conditions WHERE conditions
     * @param int $chunkSize Rows per chunk
     * @return \Generator<int, array>
     */
    public function streamResults(
        string $table,
        array $conditions = [],
        int $chunkSize = self::DEFAULT_BATCH_SIZE
    ): \Generator {
        $fullTable = $this->tablePrefix . $table;
        $offset = 0;

        $whereClause = '';
        $params = [];
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "`{$column}` = %s";
                $params[] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }

        while (true) {
            $sql = sprintf(
                "SELECT * FROM `%s` %s ORDER BY id LIMIT %d OFFSET %d",
                $fullTable,
                $whereClause,
                $chunkSize,
                $offset
            );

            if (!empty($params)) {
                $sql = $this->wpdb->prepare($sql, $params);
            }

            $rows = $this->wpdb->get_results($sql, ARRAY_A);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                yield $row;
            }

            if (count($rows) < $chunkSize) {
                break;
            }

            $offset += $chunkSize;
        }
    }

    /**
     * Execute batch with callback for each chunk
     *
     * @param array<mixed> $items Items to process
     * @param callable $processor Function(array $batch): int returning affected rows
     * @param int $batchSize Items per batch
     * @return int Total items processed
     */
    public function processBatch(
        array $items,
        callable $processor,
        int $batchSize = self::DEFAULT_BATCH_SIZE
    ): int {
        $totalProcessed = 0;
        $batches = array_chunk($items, $batchSize);

        $this->wpdb->query('START TRANSACTION');

        try {
            foreach ($batches as $batch) {
                $affected = $processor($batch);
                $totalProcessed += $affected;
                $this->stats['batches_executed']++;
            }

            $this->wpdb->query('COMMIT');
            return $totalProcessed;

        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            error_log('[SAGA][BATCH] Process batch failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get batch execution statistics
     *
     * @return array{inserts: int, updates: int, deletes: int, batches_executed: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'inserts' => 0,
            'updates' => 0,
            'deletes' => 0,
            'batches_executed' => 0,
        ];
    }

    /**
     * Calculate optimal batch size based on data
     *
     * @param array<mixed> $sampleRow Sample row data
     * @return int Optimal batch size
     */
    public function calculateOptimalBatchSize(array $sampleRow): int
    {
        $rowSizeEstimate = strlen(serialize($sampleRow)) * 2; // Rough estimate with overhead
        $maxRows = (int) floor(self::MAX_QUERY_SIZE_BYTES / $rowSizeEstimate);

        return min(
            max($maxRows, 1),
            self::MAX_BATCH_SIZE
        );
    }

    /**
     * Build placeholder string for prepared statement
     */
    private function buildPlaceholders(array $columns): string
    {
        $placeholders = array_fill(0, count($columns), '%s');
        return '(' . implode(', ', $placeholders) . ')';
    }
}
