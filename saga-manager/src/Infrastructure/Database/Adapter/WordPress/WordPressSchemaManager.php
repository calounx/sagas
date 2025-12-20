<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\WordPress;

use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * WordPress Schema Manager
 *
 * Manages database schema operations using WordPress wpdb and dbDelta.
 *
 * @example
 *   $db->schema()->createTable('entities', [
 *       'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
 *       'name' => ['type' => 'varchar', 'length' => 255],
 *   ]);
 */
final class WordPressSchemaManager implements SchemaManagerInterface
{
    private string $charset;
    private string $collate;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly string $tablePrefix = '',
    ) {
        $this->charset = $this->wpdb->get_charset_collate();
        // Parse charset and collate from the string
        if (preg_match('/DEFAULT CHARSET=(\w+)/', $this->charset, $matches)) {
            $this->charset = $matches[1];
        } else {
            $this->charset = 'utf8mb4';
        }
        if (preg_match('/COLLATE (\w+)/', $this->wpdb->get_charset_collate(), $matches)) {
            $this->collate = $matches[1];
        } else {
            $this->collate = 'utf8mb4_unicode_ci';
        }
    }

    public function tableExists(string $table): bool
    {
        $tableName = $this->tablePrefix . $table;
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $tableName
            )
        );
        return $result !== null;
    }

    public function createTable(string $table, array $columns, array $options = []): void
    {
        $tableName = $this->tablePrefix . $table;

        $columnDefs = [];
        $primaryKey = null;

        foreach ($columns as $name => $definition) {
            $columnDefs[] = $this->buildColumnDefinition($name, $definition);

            // Track primary key for auto-increment columns
            if ($definition['autoincrement'] ?? false) {
                $primaryKey = $name;
            }
        }

        // Primary key from options or auto-detect
        if (isset($options['primary'])) {
            $pk = implode(', ', (array) $options['primary']);
            $columnDefs[] = "PRIMARY KEY ({$pk})";
        } elseif ($primaryKey !== null) {
            $columnDefs[] = "PRIMARY KEY ({$primaryKey})";
        }

        // Unique constraints
        if (isset($options['unique'])) {
            foreach ($options['unique'] as $name => $cols) {
                $colList = implode(', ', (array) $cols);
                $columnDefs[] = "UNIQUE KEY {$name} ({$colList})";
            }
        }

        // Indexes
        if (isset($options['indexes'])) {
            foreach ($options['indexes'] as $name => $cols) {
                $colList = implode(', ', (array) $cols);
                $columnDefs[] = "KEY {$name} ({$colList})";
            }
        }

        $sql = "CREATE TABLE {$tableName} (\n    " .
               implode(",\n    ", $columnDefs) .
               "\n) " . $this->wpdb->get_charset_collate();

        // Use dbDelta for WordPress-style table creation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Check if table was created
        if (!$this->tableExists($table)) {
            throw new DatabaseException("Failed to create table '{$table}'");
        }
    }

    public function dropTable(string $table): void
    {
        $tableName = $this->tablePrefix . $table;
        $result = $this->wpdb->query("DROP TABLE {$tableName}");

        if ($result === false) {
            throw new DatabaseException(
                "Failed to drop table '{$table}': " . $this->wpdb->last_error
            );
        }
    }

    public function dropTableIfExists(string $table): void
    {
        $tableName = $this->tablePrefix . $table;
        $this->wpdb->query("DROP TABLE IF EXISTS {$tableName}");
    }

    public function renameTable(string $from, string $to): void
    {
        $fromName = $this->tablePrefix . $from;
        $toName = $this->tablePrefix . $to;

        $result = $this->wpdb->query("RENAME TABLE {$fromName} TO {$toName}");

        if ($result === false) {
            throw new DatabaseException(
                "Failed to rename table: " . $this->wpdb->last_error
            );
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        $columns = $this->getColumns($table);
        return isset($columns[$column]);
    }

    public function addColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;
        $columnDef = $this->buildColumnDefinition($column, $definition);

        $sql = "ALTER TABLE {$tableName} ADD COLUMN {$columnDef}";

        if (isset($definition['after'])) {
            $sql .= " AFTER {$definition['after']}";
        }

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new DatabaseException(
                "Failed to add column '{$column}': " . $this->wpdb->last_error
            );
        }
    }

    public function modifyColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;
        $columnDef = $this->buildColumnDefinition($column, $definition);

        $result = $this->wpdb->query(
            "ALTER TABLE {$tableName} MODIFY COLUMN {$columnDef}"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to modify column '{$column}': " . $this->wpdb->last_error
            );
        }
    }

    public function dropColumn(string $table, string $column): void
    {
        $tableName = $this->tablePrefix . $table;

        $result = $this->wpdb->query(
            "ALTER TABLE {$tableName} DROP COLUMN {$column}"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to drop column '{$column}': " . $this->wpdb->last_error
            );
        }
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        $tableName = $this->tablePrefix . $table;

        $result = $this->wpdb->query(
            "ALTER TABLE {$tableName} RENAME COLUMN {$from} TO {$to}"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to rename column: " . $this->wpdb->last_error
            );
        }
    }

    public function addIndex(string $table, string $name, array $columns, string $type = 'INDEX'): void
    {
        $tableName = $this->tablePrefix . $table;
        $columnList = implode(', ', $columns);

        $indexType = match (strtoupper($type)) {
            'UNIQUE' => 'UNIQUE INDEX',
            'FULLTEXT' => 'FULLTEXT INDEX',
            'SPATIAL' => 'SPATIAL INDEX',
            default => 'INDEX',
        };

        $result = $this->wpdb->query(
            "CREATE {$indexType} {$name} ON {$tableName} ({$columnList})"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to create index '{$name}': " . $this->wpdb->last_error
            );
        }
    }

    public function dropIndex(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;

        $result = $this->wpdb->query(
            "DROP INDEX {$name} ON {$tableName}"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to drop index '{$name}': " . $this->wpdb->last_error
            );
        }
    }

    public function indexExists(string $table, string $name): bool
    {
        $indexes = $this->getIndexes($table);
        return isset($indexes[$name]);
    }

    public function addForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referencedTable,
        array $referencedColumns,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        $tableName = $this->tablePrefix . $table;
        $refTableName = $this->tablePrefix . $referencedTable;

        $columnList = implode(', ', $columns);
        $refColumnList = implode(', ', $referencedColumns);

        $sql = "ALTER TABLE {$tableName} ADD CONSTRAINT {$name} " .
               "FOREIGN KEY ({$columnList}) REFERENCES {$refTableName} ({$refColumnList}) " .
               "ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new DatabaseException(
                "Failed to add foreign key '{$name}': " . $this->wpdb->last_error
            );
        }
    }

    public function dropForeignKey(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;

        $result = $this->wpdb->query(
            "ALTER TABLE {$tableName} DROP FOREIGN KEY {$name}"
        );

        if ($result === false) {
            throw new DatabaseException(
                "Failed to drop foreign key '{$name}': " . $this->wpdb->last_error
            );
        }
    }

    public function getColumns(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        $results = $this->wpdb->get_results("SHOW COLUMNS FROM {$tableName}", ARRAY_A);

        if ($results === null) {
            return [];
        }

        $columns = [];
        foreach ($results as $row) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'nullable' => $row['Null'] === 'YES',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra'],
            ];
        }

        return $columns;
    }

    public function getIndexes(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        $results = $this->wpdb->get_results("SHOW INDEX FROM {$tableName}", ARRAY_A);

        if ($results === null) {
            return [];
        }

        $indexes = [];
        foreach ($results as $row) {
            $name = $row['Key_name'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'unique' => $row['Non_unique'] === '0',
                    'columns' => [],
                ];
            }
            $indexes[$name]['columns'][] = $row['Column_name'];
        }

        return $indexes;
    }

    public function truncate(string $table): void
    {
        $tableName = $this->tablePrefix . $table;

        $result = $this->wpdb->query("TRUNCATE TABLE {$tableName}");

        if ($result === false) {
            throw new DatabaseException(
                "Failed to truncate table '{$table}': " . $this->wpdb->last_error
            );
        }
    }

    public function raw(string $sql): void
    {
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new DatabaseException(
                "Failed to execute DDL: " . $this->wpdb->last_error
            );
        }
    }

    /**
     * Build column definition SQL
     *
     * @param string $name Column name
     * @param array<string, mixed> $definition Column definition
     * @return string
     */
    private function buildColumnDefinition(string $name, array $definition): string
    {
        $type = $this->mapColumnType($definition['type'] ?? 'varchar', $definition);
        $sql = "{$name} {$type}";

        // UNSIGNED
        if ($definition['unsigned'] ?? false) {
            $sql .= ' UNSIGNED';
        }

        // NOT NULL
        if (!($definition['nullable'] ?? true)) {
            $sql .= ' NOT NULL';
        }

        // AUTO_INCREMENT
        if ($definition['autoincrement'] ?? false) {
            $sql .= ' AUTO_INCREMENT';
        }

        // DEFAULT
        if (array_key_exists('default', $definition)) {
            $default = $definition['default'];
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif ($default === 'CURRENT_TIMESTAMP') {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? '1' : '0');
            } elseif (is_numeric($default)) {
                $sql .= ' DEFAULT ' . $default;
            } else {
                $sql .= " DEFAULT '" . esc_sql($default) . "'";
            }
        }

        // ON UPDATE
        if (isset($definition['on_update'])) {
            $sql .= ' ON UPDATE ' . $definition['on_update'];
        }

        // COMMENT
        if (isset($definition['comment'])) {
            $sql .= " COMMENT '" . esc_sql($definition['comment']) . "'";
        }

        return $sql;
    }

    /**
     * Map abstract column type to MySQL type
     *
     * @param string $type Abstract type
     * @param array<string, mixed> $definition Column definition
     * @return string
     */
    private function mapColumnType(string $type, array $definition): string
    {
        $length = $definition['length'] ?? null;
        $precision = $definition['precision'] ?? null;
        $scale = $definition['scale'] ?? null;

        return match ($type) {
            'bigint' => 'BIGINT',
            'int', 'integer' => 'INT',
            'smallint' => 'SMALLINT',
            'tinyint' => 'TINYINT',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'decimal' => sprintf('DECIMAL(%d,%d)', $precision ?? 10, $scale ?? 2),
            'varchar' => 'VARCHAR(' . ($length ?? 255) . ')',
            'char' => 'CHAR(' . ($length ?? 1) . ')',
            'text' => 'TEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'longtext' => 'LONGTEXT',
            'blob' => 'BLOB',
            'boolean', 'bool' => 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'json' => 'JSON',
            'enum' => 'ENUM(' . implode(',', array_map(
                fn($v) => "'" . esc_sql($v) . "'",
                $definition['values'] ?? []
            )) . ')',
            default => $type,
        };
    }
}
