<?php

declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\WordPress;

use SagaManagerCore\Infrastructure\Database\Exception\SchemaException;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;

/**
 * WordPress Schema Manager Implementation
 *
 * Manages database schema operations using WordPress dbDelta() for table creation
 * and direct ALTER TABLE statements for modifications.
 *
 * Note: dbDelta has limitations with foreign keys, so they are added separately.
 */
class WordPressSchemaManager implements SchemaManagerInterface
{
    private const SCHEMA_VERSION_OPTION = 'saga_manager_db_version';
    private const MIGRATIONS_TABLE = 'migrations';

    private WordPressConnection $connection;
    private string $charsetCollate;

    public function __construct(WordPressConnection $connection)
    {
        $this->connection = $connection;
        $this->charsetCollate = $connection->getCharsetCollate();
    }

    // =========================================================================
    // Table Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function createTable(string $table, string $definition, array $options = []): bool
    {
        $fullTable = $this->connection->getFullTableName($table);

        if ($this->tableExists($table)) {
            return false;
        }

        $engine = $options['engine'] ?? 'InnoDB';
        $rowFormat = $options['row_format'] ?? '';

        $sql = "CREATE TABLE {$fullTable} ({$definition}) ENGINE={$engine}";

        if ($rowFormat !== '') {
            $sql .= " ROW_FORMAT={$rowFormat}";
        }

        $sql .= " {$this->charsetCollate}";

        return $this->createTableRaw($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function createTableRaw(string $sql): bool
    {
        // Ensure wp-admin/includes/upgrade.php is loaded
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        // dbDelta requires semicolon at the end
        if (!str_ends_with(trim($sql), ';')) {
            $sql .= ';';
        }

        $results = dbDelta($sql);

        $wpdb = $this->connection->getWpdb();

        if ($wpdb->last_error !== '') {
            throw SchemaException::fromDbDelta('unknown', $wpdb->last_error);
        }

        return !empty($results);
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable(string $table): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        // Disable foreign key checks temporarily
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        $result = $wpdb->query("DROP TABLE IF EXISTS {$fullTable}");

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        if ($result === false) {
            throw SchemaException::tableCreationFailed($table, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropTables(array $tables): void
    {
        $wpdb = $this->connection->getWpdb();

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $fullTable = $this->connection->getFullTableName($table);
            $wpdb->query("DROP TABLE IF EXISTS {$fullTable}");
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * {@inheritdoc}
     */
    public function tableExists(string $table): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            $wpdb->dbname,
            $fullTable
        ));

        return (int) $result > 0;
    }

    // =========================================================================
    // Column Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function addColumn(string $table, string $column, string $definition, ?string $after = null): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} ADD COLUMN `{$column}` {$definition}";

        if ($after !== null) {
            $sql .= " AFTER `{$after}`";
        }

        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::columnAddFailed($table, $column, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function modifyColumn(string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($table, $column)) {
            throw SchemaException::columnNotFound($table, $column);
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} MODIFY COLUMN `{$column}` {$definition}";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::columnAddFailed($table, $column, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropColumn(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} DROP COLUMN `{$column}`";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::columnAddFailed($table, $column, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function columnExists(string $table, string $column): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $wpdb->dbname,
            $fullTable,
            $column
        ));

        return (int) $result > 0;
    }

    // =========================================================================
    // Index Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function addIndex(string $table, string $name, string|array $columns, string $type = 'INDEX'): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $columns = is_array($columns) ? $columns : [$columns];
        $columnList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));

        $type = strtoupper($type);
        $indexType = match ($type) {
            'UNIQUE' => 'UNIQUE INDEX',
            'FULLTEXT' => 'FULLTEXT INDEX',
            'SPATIAL' => 'SPATIAL INDEX',
            default => 'INDEX',
        };

        $sql = "ALTER TABLE {$fullTable} ADD {$indexType} `{$name}` ({$columnList})";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::indexCreationFailed($table, $name, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $table, string $name): void
    {
        if (!$this->indexExists($table, $name)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} DROP INDEX `{$name}`";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::indexCreationFailed($table, $name, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function indexExists(string $table, string $name): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
            $wpdb->dbname,
            $fullTable,
            $name
        ));

        return (int) $result > 0;
    }

    // =========================================================================
    // Foreign Key Operations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function addForeignKey(
        string $table,
        string $name,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $fullRefTable = $this->connection->getFullTableName($referenceTable);
        $wpdb = $this->connection->getWpdb();

        $sql = sprintf(
            "ALTER TABLE %s ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES %s(`%s`) ON DELETE %s ON UPDATE %s",
            $fullTable,
            $name,
            $column,
            $fullRefTable,
            $referenceColumn,
            strtoupper($onDelete),
            strtoupper($onUpdate)
        );

        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::foreignKeyFailed($table, $name, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeignKey(string $table, string $name): void
    {
        if (!$this->foreignKeyExists($table, $name)) {
            return;
        }

        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} DROP FOREIGN KEY `{$name}`";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::foreignKeyFailed($table, $name, $wpdb->last_error);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function foreignKeyExists(string $table, string $name): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = %s
             AND TABLE_NAME = %s
             AND CONSTRAINT_NAME = %s
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            $wpdb->dbname,
            $fullTable,
            $name
        ));

        return (int) $result > 0;
    }

    // =========================================================================
    // Schema Information
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getTables(): array
    {
        $prefix = $this->connection->getSagaTablePrefix();
        $wpdb = $this->connection->getWpdb();

        $tables = $wpdb->get_col($wpdb->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s",
            $wpdb->dbname,
            $prefix . '%'
        ));

        // Remove prefix to return base names
        return array_map(
            fn($t) => str_replace($prefix, '', $t),
            $tables
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(string $table): array
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
             ORDER BY ORDINAL_POSITION",
            $wpdb->dbname,
            $fullTable
        ), ARRAY_A);

        $result = [];

        foreach ($columns as $column) {
            $result[$column['COLUMN_NAME']] = [
                'name' => $column['COLUMN_NAME'],
                'type' => $column['COLUMN_TYPE'],
                'null' => $column['IS_NULLABLE'] === 'YES',
                'key' => $column['COLUMN_KEY'],
                'default' => $column['COLUMN_DEFAULT'],
                'extra' => $column['EXTRA'],
            ];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexes(string $table): array
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $indexes = $wpdb->get_results($wpdb->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
             ORDER BY INDEX_NAME, SEQ_IN_INDEX",
            $wpdb->dbname,
            $fullTable
        ), ARRAY_A);

        $result = [];

        foreach ($indexes as $index) {
            $name = $index['INDEX_NAME'];

            if (!isset($result[$name])) {
                $result[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'unique' => $index['NON_UNIQUE'] === '0',
                    'type' => $index['INDEX_TYPE'],
                ];
            }

            $result[$name]['columns'][] = $index['COLUMN_NAME'];
        }

        return $result;
    }

    // =========================================================================
    // Schema Version & Migrations
    // =========================================================================

    /**
     * {@inheritdoc}
     */
    public function getSchemaVersion(): string
    {
        return get_option(self::SCHEMA_VERSION_OPTION, '0.0.0');
    }

    /**
     * {@inheritdoc}
     */
    public function setSchemaVersion(string $version): void
    {
        update_option(self::SCHEMA_VERSION_OPTION, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function migrate(): array
    {
        // This is a placeholder - actual migrations would be loaded from files
        // For now, we return empty array indicating no migrations were run
        $executed = [];

        // Create migrations table if not exists
        if (!$this->tableExists(self::MIGRATIONS_TABLE)) {
            $this->createTable(
                self::MIGRATIONS_TABLE,
                "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                 migration VARCHAR(255) NOT NULL,
                 batch INT UNSIGNED NOT NULL,
                 executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                 UNIQUE KEY uk_migration (migration)"
            );
        }

        return $executed;
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackMigration(): array
    {
        // Placeholder - would rollback the last batch of migrations
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCharsetCollate(): string
    {
        return $this->charsetCollate;
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Add a CHECK constraint
     *
     * Note: CHECK constraints are supported in MariaDB 10.2+ and MySQL 8.0.16+
     *
     * @param string $table Table name (without prefix)
     * @param string $name Constraint name
     * @param string $condition Check condition
     */
    public function addCheckConstraint(string $table, string $name, string $condition): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        // Check if constraint already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = %s
             AND TABLE_NAME = %s
             AND CONSTRAINT_NAME = %s
             AND CONSTRAINT_TYPE = 'CHECK'",
            $wpdb->dbname,
            $fullTable,
            $name
        ));

        if ((int) $exists > 0) {
            return;
        }

        $sql = "ALTER TABLE {$fullTable} ADD CONSTRAINT `{$name}` CHECK ({$condition})";
        $wpdb->query($sql);

        if ($wpdb->last_error !== '' && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SAGA][DB] Failed to add CHECK {$name}: {$wpdb->last_error}");
        }
    }

    /**
     * Rename a table
     *
     * @param string $from Current table name (without prefix)
     * @param string $to New table name (without prefix)
     */
    public function renameTable(string $from, string $to): void
    {
        $fullFrom = $this->connection->getFullTableName($from);
        $fullTo = $this->connection->getFullTableName($to);
        $wpdb = $this->connection->getWpdb();

        $sql = "RENAME TABLE {$fullFrom} TO {$fullTo}";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::tableCreationFailed($to, $wpdb->last_error);
        }
    }

    /**
     * Rename a column
     *
     * @param string $table Table name (without prefix)
     * @param string $from Current column name
     * @param string $to New column name
     * @param string $definition Column definition
     */
    public function renameColumn(string $table, string $from, string $to, string $definition): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $wpdb = $this->connection->getWpdb();

        $sql = "ALTER TABLE {$fullTable} CHANGE `{$from}` `{$to}` {$definition}";
        $result = $wpdb->query($sql);

        if ($result === false) {
            throw SchemaException::columnAddFailed($table, $to, $wpdb->last_error);
        }
    }

    /**
     * Optimize a table
     *
     * @param string $table Table name (without prefix)
     */
    public function optimizeTable(string $table): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $this->connection->getWpdb()->query("OPTIMIZE TABLE {$fullTable}");
    }

    /**
     * Analyze a table for query optimization
     *
     * @param string $table Table name (without prefix)
     */
    public function analyzeTable(string $table): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $this->connection->getWpdb()->query("ANALYZE TABLE {$fullTable}");
    }
}
