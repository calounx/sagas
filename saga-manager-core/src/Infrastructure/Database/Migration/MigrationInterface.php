<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Migration;

use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;

/**
 * Migration Interface
 *
 * Defines the contract for database migrations.
 * Each migration must implement up() for applying changes
 * and down() for reverting them.
 *
 * @package SagaManagerCore\Infrastructure\Database\Migration
 */
interface MigrationInterface
{
    /**
     * Get the unique name/identifier for this migration
     *
     * Format: YYYY_MM_DD_HHMMSS_description
     * Example: 2024_01_15_120000_create_entities_table
     */
    public function getName(): string;

    /**
     * Get the migration version (for ordering)
     *
     * Format: YYYYMMDDHHMMSS
     */
    public function getVersion(): string;

    /**
     * Apply the migration
     *
     * @param SchemaManagerInterface $schema The schema manager
     */
    public function up(SchemaManagerInterface $schema): void;

    /**
     * Revert the migration
     *
     * @param SchemaManagerInterface $schema The schema manager
     */
    public function down(SchemaManagerInterface $schema): void;

    /**
     * Get a description of what this migration does
     */
    public function getDescription(): string;
}
