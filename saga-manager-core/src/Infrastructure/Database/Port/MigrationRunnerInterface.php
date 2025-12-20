<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Port;

use SagaManagerCore\Infrastructure\Database\Migration\MigrationInterface;
use SagaManagerCore\Infrastructure\Database\Exception\MigrationException;

/**
 * Migration Runner Port Interface
 *
 * Manages database schema migrations with version tracking,
 * rollback support, and batch execution.
 *
 * Usage:
 * ```php
 * // Run all pending migrations
 * $runner->migrate();
 *
 * // Run specific migration
 * $runner->run(new CreateEntitiesTable());
 *
 * // Rollback last batch
 * $runner->rollback();
 *
 * // Rollback all
 * $runner->reset();
 *
 * // Get status
 * $status = $runner->status();
 * ```
 *
 * @package SagaManagerCore\Infrastructure\Database\Port
 */
interface MigrationRunnerInterface
{
    /**
     * Run all pending migrations
     *
     * @param bool $pretend If true, only show SQL without executing
     * @return array<string> Names of migrations that were run
     * @throws MigrationException When migration fails
     */
    public function migrate(bool $pretend = false): array;

    /**
     * Run a specific migration
     *
     * @param MigrationInterface $migration Migration to run
     * @param bool $pretend If true, only show SQL without executing
     * @throws MigrationException When migration fails
     */
    public function run(MigrationInterface $migration, bool $pretend = false): void;

    /**
     * Rollback the last batch of migrations
     *
     * @param int $steps Number of batches to rollback (default: 1)
     * @param bool $pretend If true, only show SQL without executing
     * @return array<string> Names of migrations that were rolled back
     * @throws MigrationException When rollback fails
     */
    public function rollback(int $steps = 1, bool $pretend = false): array;

    /**
     * Rollback all migrations
     *
     * @param bool $pretend If true, only show SQL without executing
     * @return array<string> Names of migrations that were rolled back
     * @throws MigrationException When rollback fails
     */
    public function reset(bool $pretend = false): array;

    /**
     * Reset and re-run all migrations
     *
     * @param bool $pretend If true, only show SQL without executing
     * @return array<string> Names of migrations that were run
     * @throws MigrationException When migration fails
     */
    public function refresh(bool $pretend = false): array;

    /**
     * Get migration status
     *
     * @return array<array{name: string, batch: int|null, ran: bool, ran_at: string|null}>
     */
    public function status(): array;

    /**
     * Get list of pending migrations
     *
     * @return array<MigrationInterface>
     */
    public function getPending(): array;

    /**
     * Get list of completed migrations
     *
     * @return array<string>
     */
    public function getCompleted(): array;

    /**
     * Check if there are pending migrations
     *
     * @return bool
     */
    public function hasPending(): bool;

    /**
     * Register a migration
     *
     * @param MigrationInterface $migration
     */
    public function register(MigrationInterface $migration): void;

    /**
     * Register multiple migrations
     *
     * @param array<MigrationInterface> $migrations
     */
    public function registerAll(array $migrations): void;

    /**
     * Get current schema version
     *
     * @return string Version string (e.g., '2024.01.15.001')
     */
    public function getCurrentVersion(): string;

    /**
     * Get the latest available version
     *
     * @return string Version string
     */
    public function getLatestVersion(): string;

    /**
     * Get the next batch number
     *
     * @return int
     */
    public function getNextBatchNumber(): int;

    /**
     * Create the migrations tracking table if it doesn't exist
     */
    public function createMigrationsTable(): void;

    /**
     * Check if the migrations table exists
     *
     * @return bool
     */
    public function hasMigrationsTable(): bool;

    /**
     * Get the SQL that would be executed by pending migrations
     *
     * @return array<string, array<string>> Migration name => SQL statements
     */
    public function preview(): array;

    /**
     * Set the migrations directory path
     *
     * @param string $path Absolute path to migrations directory
     */
    public function setMigrationsPath(string $path): void;

    /**
     * Load migrations from the configured path
     *
     * @throws MigrationException When loading fails
     */
    public function loadMigrations(): void;

    /**
     * Generate a new migration file
     *
     * @param string $name Migration name (e.g., 'create_entities_table')
     * @param string|null $table Table name for scaffold (optional)
     * @param bool $create True for create table, false for alter
     * @return string Path to created migration file
     * @throws MigrationException When generation fails
     */
    public function generate(string $name, ?string $table = null, bool $create = true): string;
}
