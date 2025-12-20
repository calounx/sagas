<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Migration;

use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;

/**
 * Abstract Migration Base Class
 *
 * Provides common functionality for migrations including
 * automatic version extraction from class name.
 *
 * @package SagaManagerCore\Infrastructure\Database\Migration
 */
abstract class AbstractMigration implements MigrationInterface
{
    /**
     * Get migration name from class name
     *
     * Converts class name like "CreateEntitiesTable_2024_01_15_120000"
     * to "2024_01_15_120000_create_entities_table"
     */
    public function getName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();

        // Try to extract timestamp from class name
        if (preg_match('/(\d{4})_(\d{2})_(\d{2})_(\d{6})/', $className, $matches)) {
            return $className;
        }

        // Generate based on class name with current timestamp
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
        return date('Y_m_d_His_') . $snake;
    }

    /**
     * Extract version from migration name
     */
    public function getVersion(): string
    {
        $name = $this->getName();

        if (preg_match('/(\d{4})_(\d{2})_(\d{2})_(\d{6})/', $name, $matches)) {
            return $matches[1] . $matches[2] . $matches[3] . $matches[4];
        }

        return date('YmdHis');
    }

    /**
     * Default description based on class name
     */
    public function getDescription(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        $readable = preg_replace('/([A-Z])/', ' $1', $className);
        return trim($readable);
    }
}
